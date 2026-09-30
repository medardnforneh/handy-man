/**
 * Shrink a picked photo before it is uploaded.
 *
 * A modern phone camera produces 4–8 MB per shot, and an on-site job report may carry several of
 * them. The API declares `photos` as up to 20 files of 10 MB each, which PHP cannot actually accept
 * — a body over `post_max_size` is discarded before Laravel sees it, so `$_FILES` arrives empty and
 * the validator answers "photos.*.file is required", which reads as a bug in the app rather than as
 * "too large". Raising `post_max_size` bought room for a realistic report; it cannot buy room for
 * twenty full-resolution photos, and the declared rule cannot be tightened because the API is
 * additive-only (CLAUDE.md rule #4).
 *
 * So the fix belongs here: a report photo exists to show a worker's before-and-after, and 2000px on
 * the long edge at JPEG quality 0.8 shows that on any screen anyone will look at it on. It takes a
 * 6 MB capture to a few hundred KB, which also matters on the connection this product is built for
 * — the upload is the slowest thing a provider does on site.
 *
 * Deliberately best-effort: if anything in the decode-and-draw path fails (an unsupported type, a
 * browser that will not decode it, a file that is not really an image), the ORIGINAL file is
 * returned. A photo that uploads large is a slow success; a photo that fails to upload because the
 * optimiser threw is a lost one, and the worker is standing in someone's kitchen.
 */

/** Longest edge, in pixels, after scaling. Enough for any screen a report is read on. */
const MAX_EDGE = 2000;

/** JPEG quality. 0.8 is the usual knee — visually indistinguishable, a fraction of the bytes. */
const QUALITY = 0.8;

/** Files already smaller than this are left alone; the re-encode would not pay for itself. */
const SKIP_BELOW_BYTES = 400 * 1024;

/** A decoded image ready to draw, its true pixel size, and how to let it go. */
interface Decoded {
  source: CanvasImageSource;
  width: number;
  height: number;
  release: () => void;
}

export async function downscaleImage(file: File): Promise<File> {
  if (!file.type.startsWith('image/') || file.size <= SKIP_BELOW_BYTES) {
    return file;
  }

  let decoded: Decoded | null = null;

  try {
    decoded = await decode(file);
    const scale = Math.min(1, MAX_EDGE / Math.max(decoded.width, decoded.height));

    // Already small enough in both dimensions and already a JPEG: re-encoding would only lose
    // quality for nothing.
    if (scale === 1 && file.type === 'image/jpeg') {
      return file;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(decoded.width * scale));
    canvas.height = Math.max(1, Math.round(decoded.height * scale));

    const context = canvas.getContext('2d');
    if (context === null) {
      return file;
    }

    context.drawImage(decoded.source, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise<Blob | null>((resolve) => {
      canvas.toBlob(resolve, 'image/jpeg', QUALITY);
    });

    if (blob === null || blob.size >= file.size) {
      return file; // no saving to be had
    }

    return new File([blob], renameToJpeg(file.name), {
      type: 'image/jpeg',
      lastModified: file.lastModified,
    });
  } catch {
    return file;
  } finally {
    // After the draw, never before it: an object URL revoked while the element is still a draw
    // source gives an empty canvas on some WebViews rather than an error, which would upload a
    // blank photo and look like the camera failed.
    decoded?.release();
  }
}

/**
 * `createImageBitmap` where it exists — every target browser and both WebViews — because it decodes
 * off the main thread. The `<img>` path is the fallback for the one case it does not, and it returns
 * the element itself, which is a perfectly good `drawImage` source.
 */
async function decode(file: File): Promise<Decoded> {
  if (typeof createImageBitmap === 'function') {
    const bitmap = await createImageBitmap(file);

    return {
      source: bitmap,
      width: bitmap.width,
      height: bitmap.height,
      release: () => bitmap.close(),
    };
  }

  const url = URL.createObjectURL(file);

  try {
    const image = await new Promise<HTMLImageElement>((resolve, reject) => {
      const el = new Image();
      el.onload = () => resolve(el);
      el.onerror = () => reject(new Error('image decode failed'));
      el.src = url;
    });

    return {
      source: image,
      width: image.naturalWidth,
      height: image.naturalHeight,
      release: () => URL.revokeObjectURL(url),
    };
  } catch (error) {
    URL.revokeObjectURL(url);
    throw error;
  }
}

/** `kitchen.heic` → `kitchen.jpg`, because the bytes really are a JPEG now. */
function renameToJpeg(name: string): string {
  return name.replace(/\.[^./\\]+$/, '') + '.jpg';
}
