<?php

declare(strict_types=1);

namespace App\Domain\Verification;

/**
 * The kind of verification document (doc 04). The kind implies the tier it works toward: national ID
 * front/back + selfie build to tier 2 (provide services); a trade licence / insurance cert, or an
 * org's RCCM + NIU, build to tier 3 (high-risk skills, company status).
 */
enum DocKind: string
{
    case NationalIdFront = 'national_id_front';
    case NationalIdBack = 'national_id_back';
    case Selfie = 'selfie';
    case TradeLicense = 'trade_license';
    case InsuranceCert = 'insurance_cert';
    case Rccm = 'rccm';
    case Niu = 'niu';

    /**
     * The verification tier this document works toward once approved (doc 04 tier table).
     */
    public function grantsTier(): int
    {
        return match ($this) {
            self::NationalIdFront, self::NationalIdBack, self::Selfie => 2,
            self::TradeLicense, self::InsuranceCert, self::Rccm, self::Niu => 3,
        };
    }
}
