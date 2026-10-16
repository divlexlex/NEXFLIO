<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin Inventory fixes: theme-consistent low-stock rows, item Edit
 * modal + endpoint, and data-driven categories with a "+ New category"
 * modal.
 */
class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $client;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
    }

    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'item_name' => 'Test Cream',
            'category' => 'Facial',
            'unit' => 'bottle',
            'reorder_point' => 3,
            'price_per_unit' => 150,
        ], $overrides);
    }

    public function test_inventory_table_colorway_classes(): void
    {
        $html = $this->actingAs($this->manager)->get(route('admin.inventory'))->assertOk()->getContent();

        // No hardcoded-cream Bootstrap row class …
        $this->assertStringNotContainsString('table-warning', $html);
        // … the approved colorway classes instead. Seeded rows sit at 0
        // on hand, so low-stock rows, badges, and empty-stock lines render.
        foreach ([
            'nx-inv-table',
            'nx-low-stock',
            'nx-inv-name',
            'nx-inv-price',
            'nx-inv-num',
            'nx-inv-low',
            'nx-inv-nostock',
            'nx-inv-btn-receive',
            'nx-inv-btn-pull',
            'nx-inv-btn-edit',
            'nx-inv-filter-label',
            'nx-inv-pager',
        ] as $class) {
            $this->assertStringContainsString($class, $html);
        }
        // Spot-check the mockup hexes are actually applied.
        foreach (['#C6A15B', '#F3E9D7', '#A68B6B', '#8F6236', '#C9A24B'] as $hex) {
            $this->assertStringContainsString($hex, $html);
        }
    }

    public function test_manager_can_edit_item_master_data_without_touching_stock(): void
    {
        $this->actingAs($this->manager)->post(
            route('admin.inventory.store'),
            $this->itemPayload(['initial_quantity' => 5])
        )->assertRedirect()->assertSessionHasNoErrors();

        $item = Inventory::where('item_name', 'Test Cream')->firstOrFail();
        $quantityBefore = (int) $item->quantity;
        $batchesBefore = $item->batches()->count();
        $movementsBefore = StockMovement::where('inventory_id', $item->id)->count();
        $this->assertSame(5, $quantityBefore);
        $this->assertGreaterThan(0, $batchesBefore);

        $this->actingAs($this->manager)->patch(
            route('admin.inventory.update', $item->id),
            [
                'item_name' => 'Test Cream Plus',
                'category' => 'Massage',
                'unit' => 'tube',
                'reorder_point' => 7,
                'price_per_unit' => 199.50,
            ]
        )->assertRedirect()->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('Test Cream Plus', $item->item_name);
        $this->assertSame('Massage', $item->category);
        $this->assertSame('tube', $item->unit);
        $this->assertSame(7, (int) $item->reorder_point);
        $this->assertEquals(199.50, (float) $item->price_per_unit);

        // Stock, batches, and movement history undisturbed.
        $this->assertSame($quantityBefore, (int) $item->quantity);
        $this->assertSame($batchesBefore, $item->batches()->count());
        $this->assertSame($movementsBefore, StockMovement::where('inventory_id', $item->id)->count());
    }

    public function test_edit_rejects_invalid_category(): void
    {
        $item = Inventory::create($this->itemPayload() + ['quantity' => 0]);

        $this->actingAs($this->manager)->patch(
            route('admin.inventory.update', $item->id),
            $this->itemPayload(['item_name' => 'Renamed', 'category' => 'Nope'])
        )->assertSessionHasErrors('category');

        $this->assertSame('Test Cream', $item->fresh()->item_name);
    }

    public function test_edit_forbidden_below_manager(): void
    {
        $item = Inventory::create($this->itemPayload() + ['quantity' => 0]);

        $this->actingAs($this->client)->patch(
            route('admin.inventory.update', $item->id),
            $this->itemPayload(['item_name' => 'Renamed'])
        )->assertForbidden();

        $this->actingAs($this->staff)->patch(
            route('admin.inventory.update', $item->id),
            $this->itemPayload(['item_name' => 'Renamed'])
        )->assertForbidden();

        $this->assertSame('Test Cream', $item->fresh()->item_name);
    }

    public function test_categories_seeded_with_classic_four(): void
    {
        $this->assertSame(
            ['Facial', 'Massage', 'Nails', 'Aesthetic'],
            InventoryCategory::orderBy('id')->pluck('name')->all()
        );
    }

    public function test_item_creation_with_custom_category_and_filter(): void
    {
        $this->actingAs($this->manager)->postJson(
            route('admin.inventory-categories.store'),
            ['name' => 'Tools']
        )->assertCreated();

        $this->actingAs($this->manager)->post(
            route('admin.inventory.store'),
            $this->itemPayload(['item_name' => 'Steel Clippers', 'category' => 'Tools'])
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventories', ['item_name' => 'Steel Clippers', 'category' => 'Tools']);

        $this->actingAs($this->manager)->get(route('admin.inventory', ['category' => 'Tools']))
            ->assertOk()
            ->assertSee('Steel Clippers');

        $this->actingAs($this->manager)->get(route('admin.inventory', ['category' => 'Nails']))
            ->assertOk()
            ->assertDontSee('Steel Clippers');
    }

    public function test_duplicate_category_rejected(): void
    {
        $this->actingAs($this->manager)->postJson(
            route('admin.inventory-categories.store'),
            ['name' => 'Nails']
        )->assertStatus(422);

        $this->actingAs($this->manager)->postJson(
            route('admin.inventory-categories.store'),
            ['name' => 'nails']
        )->assertStatus(422);

        $this->assertSame(4, InventoryCategory::count());
    }

    public function test_category_endpoint_role_guarded(): void
    {
        $this->actingAs($this->client)->postJson(
            route('admin.inventory-categories.store'),
            ['name' => 'Sneaky']
        )->assertForbidden();

        $this->actingAs($this->staff)->postJson(
            route('admin.inventory-categories.store'),
            ['name' => 'Sneaky']
        )->assertForbidden();

        $this->assertDatabaseMissing('inventory_categories', ['name' => 'Sneaky']);
    }

    public function test_item_forms_list_table_categories_with_new_entry(): void
    {
        $this->actingAs($this->manager)->postJson(
            route('admin.inventory-categories.store'),
            ['name' => 'Tools']
        )->assertCreated();

        $html = $this->actingAs($this->manager)->get(route('admin.inventory'))->assertOk()->getContent();

        $this->assertStringContainsString('<option value="Tools">Tools</option>', $html);
        $this->assertSame(2, substr_count($html, '<option value="__new__">+ New category…</option>'));
        $this->assertStringContainsString('id="newCategoryModal"', $html);
        $this->assertStringContainsString('id="editItemModal"', $html);
    }
}
