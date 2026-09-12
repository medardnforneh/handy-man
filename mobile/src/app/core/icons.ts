import { addIcons } from 'ionicons';
import {
  ArrowLeftRight, ArrowRight, Award, Ban, Banknote, Bell, Briefcase, Brush, Calendar, Camera, Car,
  Check, CheckCheck, ChevronRight, Circle, CircleAlert, CircleCheck, CircleStop, CircleX, Clock,
  CloudUpload, Compass, Copy, Download, Droplet, Ellipsis, FileText, Flag, Gift, Globe,
  GraduationCap, Hammer, Image, Info, Laptop, LayoutGrid, Leaf, Lock, LogOut, Map, MapPin,
  MessageCircleMore, MessagesSquare, Mic, Pause, Play, Plus, Power, Receipt, Repeat, Scissors,
  Search, Send, Share2, Shield, ShieldCheck, Shirt, Smartphone, Snowflake, Sparkles, Star, StarHalf,
  Ticket, TriangleAlert, User, Users, Wallet, Wrench, X, Zap,
} from 'lucide';
import type { IconNode } from 'lucide';

/**
 * The icon set is Lucide, as the Modernist design system asks — registered under the Ionicons
 * NAMES the templates already use.
 *
 * Ionicons resolves `<ion-icon name="hammer-outline">` by looking in its registry first and only
 * then fetching `svg/hammer-outline.svg`, so registering a Lucide glyph under each name in use
 * swaps the whole set without touching a template, and a name nobody registered still falls
 * through to the Ionicon it always was — visible in the console as the one thing left to map.
 *
 * Lucide ships each icon as a node list, not markup; the SVG is assembled here with the set's own
 * attributes (24-grid, 2px stroke, round joins) and handed over as a `data:` URL, which Ionicons
 * parses in place rather than fetching. Everything is stroke, so `fill="none"` on the root is what
 * keeps `ion-icon`'s inherited `fill: currentColor` from painting the glyphs solid. The few icons
 * the product uses FILLED — a rated star, the play/pause/stop transport — are filled explicitly.
 */
type Entry = IconNode | { node: IconNode; filled: true };

const filled = (node: IconNode): Entry => ({ node, filled: true });

const lucideByIonicon: Record<string, Entry> = {
  'add': Plus,
  'alert-circle': CircleAlert,
  'alert-circle-outline': CircleAlert,
  'arrow-forward': ArrowRight,
  'ban-outline': Ban,
  'briefcase': Briefcase,
  'briefcase-outline': Briefcase,
  'brush-outline': Brush,
  'calendar-outline': Calendar,
  'camera-outline': Camera,
  'car-outline': Car,
  'cash-outline': Banknote,
  'chatbubble-ellipses-outline': MessageCircleMore,
  'chatbubbles-outline': MessagesSquare,
  'checkmark': Check,
  'checkmark-circle': CircleCheck,
  'checkmark-circle-outline': CircleCheck,
  'checkmark-done-outline': CheckCheck,
  'chevron-forward': ChevronRight,
  'close': X,
  'close-circle': CircleX,
  'cloud-upload-outline': CloudUpload,
  'compass-outline': Compass,
  'construct': Wrench,
  'construct-outline': Wrench,
  'copy-outline': Copy,
  'cut-outline': Scissors,
  'document-text-outline': FileText,
  'download-outline': Download,
  'ellipse-outline': Circle,
  'ellipsis-horizontal': Ellipsis,
  'flag-outline': Flag,
  'flash-outline': Zap,
  'gift-outline': Gift,
  'globe-outline': Globe,
  'grid-outline': LayoutGrid,
  'hammer-outline': Hammer,
  'image-outline': Image,
  'information-circle-outline': Info,
  'laptop-outline': Laptop,
  'leaf-outline': Leaf,
  'location': MapPin,
  'location-outline': MapPin,
  'lock-closed-outline': Lock,
  'log-out-outline': LogOut,
  'map-outline': Map,
  'mic-outline': Mic,
  'notifications-outline': Bell,
  'pause': filled(Pause),
  'people-outline': Users,
  'person-outline': User,
  'phone-portrait-outline': Smartphone,
  'play': filled(Play),
  'power-outline': Power,
  'receipt-outline': Receipt,
  'repeat-outline': Repeat,
  'ribbon-outline': Award,
  'school-outline': GraduationCap,
  'search-outline': Search,
  'send': Send,
  'share-social-outline': Share2,
  'shield-checkmark': ShieldCheck,
  'shield-checkmark-outline': ShieldCheck,
  'shield-outline': Shield,
  'shirt-outline': Shirt,
  'snow-outline': Snowflake,
  'sparkles-outline': Sparkles,
  'star': filled(Star),
  'star-half': filled(StarHalf),
  'star-outline': Star,
  'stop-circle': CircleStop,
  'swap-horizontal-outline': ArrowLeftRight,
  'ticket-outline': Ticket,
  'time-outline': Clock,
  'wallet-outline': Wallet,
  'warning': TriangleAlert,
  'water-outline': Droplet,
};

function svgDataUrl(entry: Entry): string {
  const node = Array.isArray(entry) ? entry : entry.node;
  const fill = Array.isArray(entry) ? 'none' : 'currentColor';
  const children = node
    .map(([tag, attrs]) => `<${tag} ${Object.entries(attrs).map(([k, v]) => `${k}="${v}"`).join(' ')}/>`)
    .join('');
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="${fill}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${children}</svg>`;
  // `;utf8,` is the form Ionicons parses in place; `#` would end the URL, so it is escaped.
  return 'data:image/svg+xml;utf8,' + svg.replace(/#/g, '%23');
}

/** Register once, before the first `<ion-icon>` renders — the app module's constructor. */
export function registerIcons(): void {
  const icons: Record<string, string> = {};
  for (const [name, entry] of Object.entries(lucideByIonicon)) {
    icons[name] = svgDataUrl(entry);
  }
  addIcons(icons);
}
