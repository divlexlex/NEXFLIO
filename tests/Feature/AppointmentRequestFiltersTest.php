<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin Change Requests automatic filter bar — status/type selects plus
 * client-or-service search, mirroring the Admin Services page pattern.
 */
class AppointmentRequestFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $alphaClient;

    private User $betaClient;

    private Service $alphaService;

    private Service $betaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $this->alphaClient = User::create([
            'name' => 'Alpha Filterclient', 'email' => 'alpha.filter@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_CLIENT,
        ]);
        $this->betaClient = User::create([
            'name' => 'Beta Filterclient', 'email' => 'beta.filter@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_CLIENT,
        ]);

        $this->alphaService = Service::create([
            'name' => 'Alpha Filter Facial', 'category' => 'Facial', 'description' => 'Test',
            'price' => 500, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
        $this->betaService = Service::create([
            'name' => 'Beta Filter Massage', 'category' => 'Massage', 'description' => 'Test',
            'price' => 600, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function makeRequest(User $client, Service $service, string $type, string $status = 'pending'): AppointmentChangeRequest
    {
        $appointment = Appointment::create([
            'user_id' => $client->id,
            'service_id' => $service->id,
            'personnel_id' => null,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'status' => 'booked',
        ]);

        return AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $client->id,
            'type' => $type,
            'status' => $status,
        ]);
    }

    private function listHtml(array $params = []): string
    {
        return $this->actingAs($this->manager)
            ->get(route('admin.appointment-requests', $params))
            ->assertOk()
            ->getContent();
    }

    public function test_default_load_shows_only_pending(): void
    {
        $this->makeRequest($this->alphaClient, $this->alphaService, 'cancellation', 'pending');
        $this->makeRequest($this->betaClient, $this->betaService, 'cancellation', 'approved');

        $html = $this->listHtml();

        $this->assertStringContainsString('Alpha Filterclient', $html);
        $this->assertStringNotContainsString('Beta Filterclient', $html);
    }

    public function test_status_param_filters(): void
    {
        $this->makeRequest($this->alphaClient, $this->alphaService, 'cancellation', 'approved');
        $this->makeRequest($this->betaClient, $this->betaService, 'cancellation', 'rejected');

        $approved = $this->listHtml(['status' => 'approved']);
        $this->assertStringContainsString('Alpha Filterclient', $approved);
        $this->assertStringNotContainsString('Beta Filterclient', $approved);

        $rejected = $this->listHtml(['status' => 'rejected']);
        $this->assertStringContainsString('Beta Filterclient', $rejected);
        $this->assertStringNotContainsString('Alpha Filterclient', $rejected);

        $all = $this->listHtml(['status' => 'all']);
        $this->assertStringContainsString('Alpha Filterclient', $all);
        $this->assertStringContainsString('Beta Filterclient', $all);
    }

    public function test_type_param_filters_alone_and_combined_with_status(): void
    {
        $this->makeRequest($this->alphaClient, $this->alphaService, 'cancellation', 'pending');
        $this->makeRequest($this->betaClient, $this->betaService, 'reschedule', 'pending');
        $this->makeRequest($this->alphaClient, $this->alphaService, 'reschedule', 'approved');

        $cancellation = $this->listHtml(['type' => 'cancellation']);
        $this->assertStringContainsString('Alpha Filterclient', $cancellation);
        $this->assertStringNotContainsString('Beta Filterclient', $cancellation);

        $reschedule = $this->listHtml(['type' => 'reschedule']);
        $this->assertStringContainsString('Beta Filterclient', $reschedule);

        $combined = $this->listHtml(['status' => 'approved', 'type' => 'reschedule']);
        $this->assertStringContainsString('Alpha Filter Facial', $combined);
        $this->assertStringNotContainsString('Beta Filterclient', $combined);
    }

    public function test_q_matches_client_name_and_service_name(): void
    {
        $this->makeRequest($this->alphaClient, $this->alphaService, 'cancellation', 'pending');
        $this->makeRequest($this->betaClient, $this->betaService, 'cancellation', 'pending');

        $byClient = $this->listHtml(['q' => 'Alpha Filterclient']);
        $this->assertStringContainsString('Alpha Filterclient', $byClient);
        $this->assertStringNotContainsString('Beta Filterclient', $byClient);

        $byService = $this->listHtml(['q' => 'Beta Filter Massage']);
        $this->assertStringContainsString('Beta Filterclient', $byService);
        $this->assertStringNotContainsString('Alpha Filterclient', $byService);

        $none = $this->listHtml(['q' => 'zzz-no-such-request-xyz']);
        $this->assertStringNotContainsString('Alpha Filterclient', $none);
        $this->assertStringContainsString('No requests match your filters.', $none);
    }

    public function test_form_preserves_values_and_shows_counts(): void
    {
        $this->makeRequest($this->alphaClient, $this->alphaService, 'cancellation', 'pending');
        $this->makeRequest($this->betaClient, $this->betaService, 'reschedule', 'approved');

        $total = AppointmentChangeRequest::count();

        $html = $this->listHtml(['status' => 'approved', 'type' => 'reschedule', 'q' => 'Beta']);

        $this->assertStringContainsString('id="requests-filter-form"', $html);
        $this->assertStringContainsString('id="filter-status"', $html);
        $this->assertStringContainsString('id="filter-type"', $html);
        $this->assertStringContainsString('value="Beta"', $html);
        $this->assertMatchesRegularExpression('/<option value="approved"[^>]*selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="reschedule"[^>]*selected/', $html);
        $this->assertStringContainsString("Showing 1 of {$total} requests", $html);
    }
}
