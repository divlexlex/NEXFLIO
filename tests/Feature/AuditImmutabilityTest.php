<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function makeService(): Service
    {
        return Service::create([
            'name' => 'Swedish Massage',
            'category' => 'Massage',
            'description' => 'Test',
            'price' => 800.00,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    public function test_model_lifecycle_writes_audit_rows_with_before_and_after_values(): void
    {
        $service = $this->makeService();

        $created = AuditLog::where('auditable_type', Service::class)
            ->where('auditable_id', $service->id)
            ->where('event', 'created')
            ->first();
        $this->assertNotNull($created);
        $this->assertSame('Swedish Massage', $created->new_values['name']);

        $service->update(['price' => 900.00]);

        $updated = AuditLog::where('auditable_type', Service::class)
            ->where('auditable_id', $service->id)
            ->where('event', 'updated')
            ->first();
        $this->assertNotNull($updated);
        $this->assertArrayHasKey('price', $updated->old_values);
        $this->assertArrayHasKey('price', $updated->new_values);

        $service->delete();

        $this->assertNotNull(
            AuditLog::where('auditable_type', Service::class)
                ->where('auditable_id', $service->id)
                ->where('event', 'deleted')
                ->first()
        );
    }

    public function test_audit_logs_cannot_be_updated(): void
    {
        $this->makeService();
        $log = AuditLog::firstOrFail();

        $this->expectException(\RuntimeException::class);
        $log->update(['event' => 'tampered']);
    }

    public function test_audit_logs_cannot_be_deleted(): void
    {
        $this->makeService();
        $log = AuditLog::firstOrFail();

        $this->expectException(\RuntimeException::class);
        $log->delete();
    }

    public function test_updates_without_changes_write_no_audit_row(): void
    {
        $service = $this->makeService();
        // Raw table delete: bypasses model events on purpose (test setup only).
        \Illuminate\Support\Facades\DB::table('audit_logs')->delete();

        $service->update(['name' => 'Swedish Massage']); // no actual change

        $this->assertSame(0, AuditLog::count());
    }
}
