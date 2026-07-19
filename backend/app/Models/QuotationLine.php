<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuotationLineFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a quotation (doc 06). Frozen once its quote leaves draft (DB trigger).
 *
 * @property string $id
 * @property string $quotation_id
 * @property int $position
 * @property string $kind
 * @property string $label
 * @property string $quantity
 * @property int $unit_price_minor
 */
final class QuotationLine extends Model
{
    /** @use HasFactory<QuotationLineFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'quotation_id', 'position', 'kind', 'label', 'quantity', 'unit_price_minor',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'unit_price_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
