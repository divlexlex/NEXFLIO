<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
    }

    public function test_owner_can_download_the_csv(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.reports.financial.export', 'csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('financial-report-'.now()->year.'.csv', $response->headers->get('content-disposition'));
        $body = $response->streamedContent();
        $this->assertStringContainsString('Verified Revenue (YTD)', $body);
        $this->assertStringContainsString('Monthly Revenue', $body);
        $this->assertStringContainsString('Top Services by Revenue', $body);
    }

    public function test_owner_can_download_the_pdf(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.reports.financial.export', 'pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_unknown_format_is_404(): void
    {
        $this->actingAs($this->owner)->get(route('admin.reports.financial.export', 'xlsx'))->assertNotFound();
    }

    public function test_manager_cannot_export_owner_only_report(): void
    {
        $this->actingAs($this->manager)->get(route('admin.reports.financial.export', 'csv'))->assertForbidden();
    }

    public function test_financial_page_shows_export_button(): void
    {
        $this->actingAs($this->owner)->get(route('admin.reports.financial'))
            ->assertOk()
            ->assertSee('Export')
            ->assertSee(route('admin.reports.financial.export', 'pdf'), false);
    }
}
