import { downscaleImage } from './downscale-image';

/**
 * Downscaling a picked photo before upload.
 *
 * The bug it exists for: a phone camera produces 4–8 MB a shot, a job report carries several, and
 * PHP discards a body over `post_max_size` WHOLE — so the worker gets "photos.*.file is required"
 * and reads it as a broken app rather than as "too large". The API's declared limits cannot be
 * tightened (additive-only), so the fix is here.
 *
 * The behaviour worth pinning is the failure mode as much as the success: a photo this cannot
 * process must still be attached at full size, because a lost photo means going back to the site.
 */
describe('downscaleImage', () => {
  /**
   * A real PNG of the given size, drawn through a canvas so the browser will decode it.
   *
   * PER-PIXEL noise, not blocks of colour. The first version of this drew 8×8 squares and a
   * 3000×2000 image came out at 128 KB — under the function's own "already small enough" threshold,
   * so it was correctly left alone and the test failed for the right reason. The threshold is on
   * BYTES, because the thing being fixed is upload size, not megapixels; so a fixture that tests
   * shrinking has to actually be big.
   */
  async function imageFile(width: number, height: number, name = 'shot.png'): Promise<File> {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d')!;

    const data = context.createImageData(width, height);
    for (let i = 0; i < data.data.length; i += 4) {
      data.data[i] = Math.random() * 256;
      data.data[i + 1] = Math.random() * 256;
      data.data[i + 2] = Math.random() * 256;
      data.data[i + 3] = 255;
    }
    context.putImageData(data, 0, 0);

    const blob = await new Promise<Blob>((resolve) => canvas.toBlob((b) => resolve(b!), 'image/png'));

    return new File([blob], name, { type: 'image/png' });
  }

  it('shrinks a large photo to the long-edge bound and re-encodes it as JPEG', async () => {
    const original = await imageFile(3000, 2000);
    expect(original.size).toBeGreaterThan(400 * 1024); // the fixture is genuinely a big photo

    const result = await downscaleImage(original);

    expect(result).not.toBe(original);
    expect(result.type).toBe('image/jpeg');
    expect(result.name).toBe('shot.jpg'); // the bytes really are a JPEG now
    expect(result.size).toBeLessThan(original.size);

    // And it is actually 2000px on the long edge, not merely smaller in bytes.
    const bitmap = await createImageBitmap(result);
    expect(Math.max(bitmap.width, bitmap.height)).toBe(2000);
    expect(bitmap.width / bitmap.height).toBeCloseTo(3 / 2, 1);
    bitmap.close();
  });

  it('leaves a small file alone rather than re-encoding it for nothing', async () => {
    const small = new File([new Uint8Array(1024)], 'tiny.jpg', { type: 'image/jpeg' });

    await expectAsync(downscaleImage(small)).toBeResolvedTo(small);
  });

  it('returns the original when the file is not an image', async () => {
    const notAnImage = new File(['summary text'], 'notes.txt', { type: 'text/plain' });

    await expectAsync(downscaleImage(notAnImage)).toBeResolvedTo(notAnImage);
  });

  it('returns the original when the bytes cannot be decoded', async () => {
    // Claims to be an image, is not. The worker keeps their photo either way: best-effort means
    // a slow success, never a lost one.
    const corrupt = new File([new Uint8Array(600 * 1024).fill(0x7f)], 'broken.png', { type: 'image/png' });

    await expectAsync(downscaleImage(corrupt)).toBeResolvedTo(corrupt);
  });
});
