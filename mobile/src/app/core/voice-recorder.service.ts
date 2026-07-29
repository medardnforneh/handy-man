import { Injectable } from '@angular/core';

/** A finished recording, ready to upload. */
export interface Recording {
  blob: Blob;
  /** Measured wall-clock length; the server stores it so a bubble can size itself without the audio. */
  durationMs: number;
  /** A filename whose extension matches the container the browser actually produced. */
  filename: string;
}

/**
 * The mime types we ask for, best first. Browsers differ: Chrome/Android record webm/opus, Safari
 * records mp4/aac. We hand the browser the first it claims to support rather than assuming, and
 * the filename extension follows whatever it chose — the server's allow-list accepts all of these.
 */
const PREFERRED: { mime: string; ext: string }[] = [
  { mime: 'audio/webm;codecs=opus', ext: 'webm' },
  { mime: 'audio/webm', ext: 'webm' },
  { mime: 'audio/ogg;codecs=opus', ext: 'ogg' },
  { mime: 'audio/mp4', ext: 'm4a' },
];

/**
 * Records a voice note from the microphone (build plan P4-05).
 *
 * Speaking a problem is far easier than typing it in a second language, so this is a first-class
 * way to send a message rather than a novelty. The service owns the MediaRecorder and the stream,
 * and always releases the microphone when it stops — a page that keeps the mic open leaves the
 * browser's recording indicator lit, which users reasonably read as being spied on.
 */
@Injectable({ providedIn: 'root' })
export class VoiceRecorderService {
  private recorder: MediaRecorder | null = null;
  private stream: MediaStream | null = null;
  private chunks: Blob[] = [];
  private startedAt = 0;

  get recording(): boolean {
    return this.recorder !== null;
  }

  /** True when this browser can record at all — the composer hides the affordance otherwise. */
  static get supported(): boolean {
    return typeof navigator !== 'undefined'
      && navigator.mediaDevices?.getUserMedia !== undefined
      && typeof MediaRecorder !== 'undefined';
  }

  /**
   * Ask for the microphone and start recording. Rejects if permission is refused — the caller
   * should treat that as "no voice notes", not as an error worth shouting about.
   */
  async start(): Promise<void> {
    if (this.recorder !== null) {
      return;
    }

    this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const choice = PREFERRED.find((c) => MediaRecorder.isTypeSupported(c.mime));

    this.chunks = [];
    this.recorder = new MediaRecorder(
      this.stream,
      choice === undefined ? undefined : { mimeType: choice.mime },
    );
    this.recorder.ondataavailable = (e) => {
      if (e.data.size > 0) {
        this.chunks.push(e.data);
      }
    };
    this.recorder.start();
    this.startedAt = Date.now();
  }

  /** Stop and hand back the recording. Always releases the mic, even if nothing was captured. */
  async stop(): Promise<Recording | null> {
    const recorder = this.recorder;
    if (recorder === null) {
      return null;
    }

    const durationMs = Date.now() - this.startedAt;
    const finished = new Promise<void>((resolve) => {
      recorder.onstop = () => resolve();
    });
    recorder.stop();
    await finished;

    this.release();

    const type = recorder.mimeType || 'audio/webm';
    const blob = new Blob(this.chunks, { type });
    this.chunks = [];

    // A recording with no bytes is a failed capture, not a message — the server would refuse it.
    if (blob.size === 0) {
      return null;
    }

    const ext = PREFERRED.find((c) => type.startsWith(c.mime.split(';')[0]))?.ext ?? 'webm';

    return { blob, durationMs, filename: `voice-note.${ext}` };
  }

  /** Abandon a recording in progress — used when the user cancels. */
  cancel(): void {
    try {
      this.recorder?.stop();
    } catch {
      // Already stopped.
    }
    this.chunks = [];
    this.release();
  }

  private release(): void {
    this.stream?.getTracks().forEach((t) => t.stop());
    this.stream = null;
    this.recorder = null;
  }
}
