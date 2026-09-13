/**
 * The product's payment methods (founder decision 2026-09-13): MTN Mobile Money, Orange Money and
 * cash — the server's `PaymentMethod` enum, mirrored for the two things the client does with it:
 * offer the choice, and pre-select it from the number typed.
 *
 * The prefix table is the same as the server's (ART allocations: MTN 650–654 / 67x / 680–684,
 * Orange 655–659 / 69x / 685–689) and is a DEFAULT, never an oracle — a ported number belongs to
 * whichever operator its owner says, which is why every money sheet shows the choice pre-selected
 * rather than hiding it. The server infers the same way when the app sends nothing, so an older
 * build keeps working.
 */
export type MobileRail = 'mtn_momo' | 'orange_money';

export const MOBILE_RAILS: readonly MobileRail[] = ['mtn_momo', 'orange_money'];

export function railFor(msisdn: string): MobileRail | null {
  let digits = msisdn.replace(/\D/g, '');
  if (digits.startsWith('237')) {
    digits = digits.slice(3);
  }
  if (digits.length < 3 || digits[0] !== '6') {
    return null;
  }
  const head = Number(digits.slice(0, 3));
  if ((head >= 650 && head <= 654) || (head >= 670 && head <= 679) || (head >= 680 && head <= 684)) {
    return 'mtn_momo';
  }
  if ((head >= 655 && head <= 659) || (head >= 690 && head <= 699) || (head >= 685 && head <= 689)) {
    return 'orange_money';
  }
  return null;
}
