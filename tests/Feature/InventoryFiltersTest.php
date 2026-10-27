<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin Inventory list filtration + pagination. A search term spans ALL
 * categories (the selected category is ignored while `q` is present) and
 * swaps the counter for the "result(s) for …" copy; otherwise the category
 * and stock filters narrow the list. paginate(10) keeps name order with
 * params in page URLs, and the count line reflects the filtered set.
 */
class InventoryFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
    }

    private function makeItem(string $name, string $category, int $quantity, int $reorder = 3): Inventory
    {
        return Inventory::create([
            'item_name' => $name,
            'category' => $category,
            'unit' => 'pc',
            'quantity' => $quantity,
            'reorder_point' => $reorder,
            'price_per_unit' => 100,
        ]);
    }

    private function makeCategory(string $name): void
    {
        InventoryCategory::create(['name' => $name]);
    }

    private function listHtml(array $params = []): string
    {
        return $this->actingAs($this->manager)
            ->get(route('admin.inventory', $params))
            ->assertOk()
            ->getContent();
    }

    public function test_pagination_splits_large_categories_and_keeps_params(): void
    {
        $this->makeCategory('Pagetest');
        for ($i = 1; $i <= 12; $i++) {
            $this->makeItem(sprintf('Filter Item %02d', $i), 'Pagetest', 10);
        }

        $page1 = $this->listHtml(['category' => 'Pagetest']);
        $this->assertStringContainsString('Filter Item 01', $page1);
        $this->assertStringContainsString('Filter Item 10', $page1);
        $this->assertStringNotContainsString('Filter Item 11', $page1);
        $this->assertStringContainsString('page=2', $page1);

        $page2 = $this->listHtml(['category' => 'Pagetest', 'page' => 2]);
        $this->assertStringContainsString('Filter Item 11', $page2);
        $this->assertStringContainsString('Filter Item 12', $page2);
        $this->assertStringNotContainsString('Filter Item 01', $page2);

        // Filter params survive in the pagination URLs.
        $withSearch = $this->listHtml(['category' => 'Pagetest', 'q' => 'Filter']);
        $this->assertStringContainsString('category=Pagetest', $withSearch);
        $this->assertStringContainsString('page=2', $withSearch);
    }

    public function test_search_narrows_by_name_case_insensitively(): void
    {
        $this->makeCategory('Searchtest');
        $this->makeItem('Alpha Polish', 'Searchtest', 10);
        $this->makeItem('Beta Cream', 'Searchtest', 10);

        $html = $this->listHtml(['category' => 'Searchtest', 'q' => 'alpha']);

        $this->assertStringContainsString('Alpha Polish', $html);
        $this->assertStringNotContainsString('Beta Cream', $html);
        // A search swaps the counter for the "across all categories" copy.
        $this->assertStringContainsString('Showing 1 result(s) for "alpha" across all categories', $html);
    }

    public function test_stock_filters_follow_the_badge_rule(): void
    {
        $this->makeCategory('Stocktest');
        $this->makeItem('Overstock Oil', 'Stocktest', 10, 3);
        $this->makeItem('Edge Lotion', 'Stocktest', 3, 3);
        $this->makeItem('Low Tonic', 'Stocktest', 2, 3);
        $this->makeItem('Empty Jar', 'Stocktest', 0, 3);

        $in = $this->listHtml(['category' => 'Stocktest', 'stock' => 'in']);
        $this->assertStringContainsString('Overstock Oil', $in);
        $this->assertStringNotContainsString('Edge Lotion', $in);
        $this->assertStringNotContainsString('Low Tonic', $in);
        $this->assertStringNotContainsString('Empty Jar', $in);

        // Low mirrors the badge rule (quantity <= reorder_point): the
        // at-the-point item and even the zero item count as Low.
        $low = $this->listHtml(['category' => 'Stocktest', 'stock' => 'low']);
        $this->assertStringContainsString('Edge Lotion', $low);
        $this->assertStringContainsString('Low Tonic', $low);
        $this->assertStringContainsString('Empty Jar', $low);
        $this->assertStringNotContainsString('Overstock Oil', $low);

        $out = $this->listHtml(['category' => 'Stocktest', 'stock' => 'out']);
        $this->assertStringContainsString('Empty Jar', $out);
        $this->assertStringNotContainsString('Overstock Oil', $out);
        $this->assertStringNotContainsString('Low Tonic', $out);
    }

    public function test_combined_filters_intersect_with_count_line(): void
    {
        $this->makeCategory('Geltest');
        $this->makeItem('Gel A Plenty', 'Geltest', 10, 3);
        $this->makeItem('Gel B Empty', 'Geltest', 0, 3);
        $this->makeItem('Gel C Other', 'Nails', 0, 3);

        // Category alone keeps the legacy wording.
        $plain = $this->listHtml(['category' => 'Geltest']);
        $this->assertStringContainsString('2 item(s) in this category', $plain);

        // A search term spans every category (the selected category is
        // ignored while `q` is present), so the Nails item is included;
        // the stock filter still applies to the matched set.
        $html = $this->listHtml(['category' => 'Geltest', 'q' => 'gel', 'stock' => 'out']);

        $this->assertStringContainsString('Gel B Empty', $html);
        $this->assertStringContainsString('Gel C Other', $html);
        $this->assertStringNotContainsString('Gel A Plenty', $html);
        // 2 test rows (B + C) plus the 4 seeded Nails "gel" items, all of
        // which are out of stock and now match because search is not scoped
        // to the selected category.
        $this->assertStringContainsString('Showing 6 result(s) for "gel" across all categories', $html);
    }
}
