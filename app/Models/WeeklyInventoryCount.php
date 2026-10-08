<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A weekly full physical inventory count (every Thursday). The week is
 * identified by its Thursday date — one session per week — and it starts life
 * as a draft snapshot of every item's On Hand, then becomes read-only once
 * finalized (variances posted through InventoryService::adjust()).
 */
class WeeklyInventoryCount extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'count_date',
        'status',
        'conducted_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'count_date' => 'date:Y-m-d',
            'finalized_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(WeeklyInventoryCountItem::class);
    }

    public function conductedBy()
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    /**
     * The Thursday date this week's count session is keyed on (app timezone,
     * so it rolls over exactly when the business starts counting).
     */
    public static function thisWeekDate(): string
    {
        return now()->startOfWeek()->addDays(3)->toDateString();
    }

    /**
     * Matched / over / short / blank tallies. Pass the line collection you are
     * displaying (e.g. after the category filter) or nothing for all lines.
     *
     * @param  iterable<WeeklyInventoryCountItem>|null  $items
     * @return array{matched: int, over: int, short: int, blank: int, variances: int}
     */
    public function summary(?iterable $items = null): array
    {
        $matched = $over = $short = $blank = 0;

        foreach ($items ?? $this->items as $item) {
            $variance = $item->variance();

            if ($variance === null) {
                $blank++;
            } elseif ($variance === 0) {
                $matched++;
            } elseif ($variance > 0) {
                $over++;
            } else {
                $short++;
            }
        }

        return [
            'matched' => $matched,
            'over' => $over,
            'short' => $short,
            'blank' => $blank,
            'variances' => $over + $short,
        ];
    }
}
