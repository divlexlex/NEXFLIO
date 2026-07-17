<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryFifoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
    }

    private function createItemWithTwoBatches(): Inventory
    {
        $response = $this->actingAs($this->manager, 'sanctum')->postJson('/api/inventory', [
            'item_name' => 'Nail Polish - Rose',
            'unit' => 'bottle',
            'reorder_point' => 3,
            'price_per_unit' => 150.00,
        ]);
        $response->assertCreated();
        $inventory = Inventory::findOrFail($response->json('id'));

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/inventory/{$inventory->id}/batches", [
                'quantity' => 5,
                'unit_cost' => 100.00,
            ])->assertCreated();

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/inventory/{$inventory->id}/batches", [
                'quantity' => 5,
                'unit_cost' => 120.00,
            ])->assertCreated();

        return $inventory->fresh();
    }

    public function test_receiving_batches_updates_cache_and_writes_in_movements(): void
    {
        $inventory = $this->createItemWithTwoBatches();

        $this->assertSame(10, (int) $inventory->quantity);
        $this->assertSame(2, $inventory->batches()->count());
        $this->assertSame(2, StockMovement::where('inventory_id', $inventory->id)->where('type', 'in')->count());
    }

    public function test_consumption_is_fifo_across_batch_boundaries(): void
    {
        $inventory = $this->createItemWithTwoBatches();

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/inventory/{$inventory->id}/pull-out", [
                'quantity' => 7,
                'reason' => 'Damaged in storage',
            ])->assertOk();

        $inventory->refresh();
        $batches = $inventory->batches()->orderBy('received_at')->orderBy('id')->get();

        $this->assertSame(3, (int) $inventory->quantity);
        $this->assertSame(0, (int) $batches[0]->quantity_remaining, 'Oldest batch must be drained first');
        $this->assertSame(3, (int) $batches[1]->quantity_remaining);

        $pullOuts = StockMovement::where('inventory_id', $inventory->id)
            ->where('type', 'pull_out')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $pullOuts, 'One movement per batch touched');
        $this->assertSame(5, (int) $pullOuts[0]->quantity);
        $this->assertSame('100.00', (string) $pullOuts[0]->unit_cost);
        $this->assertSame(2, (int) $pullOuts[1]->quantity);
        $this->assertSame('120.00', (string) $pullOuts[1]->unit_cost);
    }

    public function test_insufficient_stock_is_rejected_and_nothing_changes(): void
    {
        $inventory = $this->createItemWithTwoBatches();

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/inventory/{$inventory->id}/pull-out", [
                'quantity' => 11,
                'reason' => 'Too much',
            ])->assertStatus(422);

        $this->assertSame(10, (int) $inventory->fresh()->quantity);
        $this->assertSame(0, StockMovement::where('inventory_id', $inventory->id)->where('type', 'pull_out')->count());
    }

    public function test_pull_out_requires_reason_and_whole_units(): void
    {
        $inventory = $this->createItemWithTwoBatches();

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/inventory/{$inventory->id}/pull-out", ['quantity' => 2])
            ->assertStatus(422);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/inventory/{$inventory->id}/pull-out", [
                'quantity' => 1.5,
                'reason' => 'Fractions are not allowed',
            ])->assertStatus(422);
    }

    public function test_stock_movements_are_immutable(): void
    {
        $inventory = $this->createItemWithTwoBatches();
        $movement = StockMovement::where('inventory_id', $inventory->id)->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $movement->update(['quantity' => 999]);
    }
}
