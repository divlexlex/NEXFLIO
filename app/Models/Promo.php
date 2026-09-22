<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use Auditable, HasFactory, HasImage, SoftDeletes;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    public const TYPE_BUNDLE = 'bundle';

    public const TYPES = [self::TYPE_PERCENTAGE, self::TYPE_FIXED_AMOUNT, self::TYPE_BUNDLE];

    protected $fillable = [
        'title',
        'description',
        'discount_type',
        'discount_value',
        'image_path',
        'price',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'discount_value' => 'decimal:2',
            'price' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    protected $appends = ['image_url'];

    /**
     * A promo can apply to several Services (the "Select Services" wizard
     * step) — replaces the old single nullable service_id.
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'promo_service');
    }

    /**
     * Only promos an Admin/Manager has switched on AND that are within
     * their scheduled date range — what the public site (Home teaser,
     * /offers, /promos/{id}) is allowed to show. See displayStatus() for the
     * human-readable version of this same rule, used in the Admin list.
     */
    public function scopeLive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today));
    }

    /**
     * 'inactive' | 'expired' | 'scheduled' | 'active' — the Admin Promotions
     * list groups by this; only 'active' is ever shown to the public (see
     * scopeLive(), which encodes the identical rule for querying).
     */
    public function displayStatus(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        $today = now()->toDateString();

        if ($this->ends_at && $this->ends_at->toDateString() < $today) {
            return 'expired';
        }

        if ($this->starts_at && $this->starts_at->toDateString() > $today) {
            return 'scheduled';
        }

        return 'active';
    }

    /**
     * "20% OFF" / "₱200 OFF" for a percentage/fixed_amount promo; null for a
     * bundle (which has no single rate — it's just a flat `price`).
     */
    public function discountLabel(): ?string
    {
        return match ($this->discount_type) {
            self::TYPE_PERCENTAGE => rtrim(rtrim((string) $this->discount_value, '0'), '.').'% OFF',
            self::TYPE_FIXED_AMOUNT => '₱'.number_format((float) $this->discount_value, 0).' OFF',
            default => null,
        };
    }

    /**
     * What a Client actually pays for the given linked Service under this
     * promo. A bundle promo ignores $service entirely and returns its own
     * flat price.
     */
    public function effectivePriceFor(Service $service): float
    {
        return match ($this->discount_type) {
            self::TYPE_PERCENTAGE => round((float) $service->price * (1 - (float) $this->discount_value / 100), 2),
            self::TYPE_FIXED_AMOUNT => max(0, (float) $service->price - (float) $this->discount_value),
            default => (float) $this->price,
        };
    }

    /**
     * A single representative price for card/list display: `price` itself
     * for a bundle, otherwise the discounted price of its first linked
     * Service (there's no one "the" price for a percentage/fixed_amount
     * promo spanning several services — this is just what a card shows).
     * Null when there's nothing to compute from (no linked service, no
     * price). Expects `services` to already be eager-loaded.
     */
    public function displayPrice(): ?float
    {
        if ($this->discount_type === self::TYPE_BUNDLE) {
            return $this->price !== null ? (float) $this->price : null;
        }

        $service = $this->services->first();

        return $service ? $this->effectivePriceFor($service) : null;
    }
}
