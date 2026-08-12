import { booleanAttribute, Component, EventEmitter, Input, Output } from '@angular/core';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';

/**
 * The one empty state.
 *
 * Every list in this app used to end in `<p class="hm-empty">` — a grey sentence pinned to the top
 * of a full-height scroll area with a screen and a half of black underneath it, and no way forward.
 * That reads as a screen that failed to load, which is the opposite of what an empty state is for:
 * it should say what belongs here, and offer the one action that fills it.
 *
 * Two shapes, because there are two situations:
 *  - **full** (default) — the whole screen has nothing. It fills the remaining height and centres,
 *    so there is no void; the page wrapper opts in with `.hm-page-fill` (see ui.scss).
 *  - **inline** — one section of an otherwise populated screen is empty (a skills row, a search that
 *    matched nothing). It stays compact and left-aligned so it doesn't shout over its neighbours.
 *
 * Titles and bodies are i18n KEYS, resolved here — callers never pass a literal string.
 */
@Component({
  selector: 'app-empty-state',
  templateUrl: './empty-state.component.html',
  styleUrls: ['./empty-state.component.scss'],
  imports: [IonicModule, TranslatePipe],
})
export class EmptyStateComponent {
  /** Ionic icon name for the illustration. */
  @Input() icon = 'ellipse-outline';

  /** i18n key: what is missing, as a short noun phrase. */
  @Input({ required: true }) heading = '';

  /** i18n key: one line on what will appear here, or how to make it appear. Optional. */
  @Input() body = '';

  /** i18n key for the single action that fills this emptiness. Omitted when there isn't one. */
  @Input() actionLabel = '';

  @Input() actionIcon = '';

  /** Compact, left-aligned variant for one empty section inside a populated page. */
  @Input({ transform: booleanAttribute }) inline = false;

  @Output() readonly action = new EventEmitter<void>();
}
