<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * All stock changes go through here so that FIFO order, the running quantity
 * cache on inventories, and the immutable stock_movements ledger stay in sync.
 * Quantities are whole units only — no fractions of a bottle.
 */
class InventoryService
{
    public function receive(
        int $inventoryId,
        int $quantity,
        float $unitCost,
        ?int $userId = null,
        ?string $reason = null,
    ): InventoryBatch {
        return DB::transaction(function () use ($inventoryId, $quantity, $unitCost, $userId, $reason) {
            $inventory = Inventory::lockForUpdate()->findOrFail($inventoryId);

            $batch = $inventory->batches()->create([
                'quantity_received' => $quantity,
                'quantity_remaining' => $quantity,
                'unit_cost' => $unitCost,
                'received_at' => now(),
                'received_by' => $userId,
            ]);

            StockMovement::create([
                'inventory_id' => $inventory->id,
                'inventory_batch_id' => $batch->id,
                'type' => StockMovement::TYPE_IN,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'user_id' => $userId,
                'reason' => $reason,
            ]);

            $inventory->increment('quantity', $quantity);

            return $batch;
        });
    }

    /**
     * Deduct stock FIFO (oldest batch first), one movement row per batch
     * touched. $type is 'out' for service consumption, 'pull_out' for manual
     * manager deductions.
     *
     * @return array<StockMovement>
     *
     * @throws InsufficientStockException
     */
    public function consume(
        int $inventoryId,
        int $quantity,
        string $type,
        ?Model $reference = null,
        ?int $userId = null,
        ?string $reason = null,
    ): array {
        return DB::transaction(function () use ($inventoryId, $quantity, $type, $reference, $userId, $reason) {
            $inventory = Inventory::lockForUpdate()->findOrFail($inventoryId);

            if ($inventory->quantity < $quantity) {
                throw new InsufficientStockException($inventory->item_name, $quantity, $inventory->quantity);
            }

            $batches = $inventory->batches()
                ->where('quantity_remaining', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remaining = $quantity;
            $movements = [];

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($batch->quantity_remaining, $remaining);
                $batch->decrement('quantity_remaining', $take);

                $movements[] = StockMovement::create([
                    'inventory_id' => $inventory->id,
                    'inventory_batch_id' => $batch->id,
                    'type' => $type,
                    'quantity' => $take,
                    'unit_cost' => $batch->unit_cost,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id' => $reference?->getKey(),
                    'user_id' => $userId,
                    'reason' => $reason,
                ]);

                $remaining -= $take;
            }

            // The cached quantity said we had enough, but the batches did not
            // cover it — the cache has drifted. Roll everything back.
            if ($remaining > 0) {
                throw new InsufficientStockException($inventory->item_name, $quantity, $quantity - $remaining);
            }

            $inventory->decrement('quantity', $quantity);

            return $movements;
        });
    }
}
