<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin Employees (Roster & Attendance) filtration: search by name or
 * username, position and status dropdowns (session-persisted), combined
 * AND logic, a "Showing X of Y" line, and a date picker untouched by
 * filtering.
 */
class EmployeeFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
    }

    private function makeStaff(string $first, string $last, string $username, string $position = 'Massage Technician', string $status = 'active'): User
    {
        $user = User::create([
            'name' => "{$first} {$last}",
            'first_name' => $first,
            'last_name' => $last,
            'username' => $username,
            'email' => "{$username}@nexflio.test",
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
        ]);
        StaffProfile::create([
            'user_id' => $user->id,
            'position' => $position,
            'employment_status' => $status,
        ]);

        return $user;
    }

    private function listHtml(array $params = []): string
    {
        return $this->actingAs($this->manager)
            ->get(route('admin.employees', $params))
            ->assertOk()
            ->getContent();
    }

    public function test_search_narrows_by_name_and_username(): void
    {
        $this->makeStaff('Wanda', 'Searchwick', 'wanda.s');

        $byName = $this->listHtml(['q' => 'Searchwick']);
        $this->assertStringContainsString('Wanda Searchwick', $byName);
        $this->assertStringNotContainsString('Sheila Marie Dela Cruz', $byName);

        // Case-insensitive partial match on either column.
        $byUsername = $this->listHtml(['q' => 'WANDA']);
        $this->assertStringContainsString('Wanda Searchwick', $byUsername);

        $seeded = $this->listHtml(['q' => 'delacruz.sheila']);
        $this->assertStringContainsString('Sheila Marie Dela Cruz', $seeded);
        $this->assertStringNotContainsString('Wanda Searchwick', $seeded);
    }

    public function test_position_filter_returns_matching_positions_including_custom(): void
    {
        Position::create(['name' => 'Lash Artist', 'service_category' => 'Lashes & Brows']);
        $this->makeStaff('Lena', 'Lasher', 'lena.l', 'Lash Artist');

        $custom = $this->listHtml(['position' => 'Lash Artist']);
        $this->assertStringContainsString('Lena Lasher', $custom);
        $this->assertStringNotContainsString('Sheila Marie Dela Cruz', $custom);

        $classic = $this->listHtml(['position' => 'Massage Technician']);
        $this->assertStringContainsString('Sheila Marie Dela Cruz', $classic);
        $this->assertStringNotContainsString('Lena Lasher', $classic);
    }

    public function test_status_filter_returns_matching_statuses(): void
    {
        $this->makeStaff('Ivy', 'Inactive', 'ivy.i', 'Massage Technician', 'inactive');
        $this->makeStaff('Tina', 'Terminated', 'tina.t', 'Massage Technician', 'terminated');

        $inactive = $this->listHtml(['status' => 'inactive']);
        $this->assertStringContainsString('Ivy Inactive', $inactive);
        $this->assertStringNotContainsString('Sheila Marie Dela Cruz', $inactive);
        $this->assertStringNotContainsString('Tina Terminated', $inactive);

        $terminated = $this->listHtml(['status' => 'terminated']);
        $this->assertStringContainsString('Tina Terminated', $terminated);
        $this->assertStringNotContainsString('Ivy Inactive', $terminated);

        $active = $this->listHtml(['status' => 'active']);
        $this->assertStringContainsString('Sheila Marie Dela Cruz', $active);
        $this->assertStringNotContainsString('Ivy Inactive', $active);
    }

    public function test_combined_filters_intersect_with_count_line(): void
    {
        $this->makeStaff('Andy', 'Active', 'andy.a', 'Nail Technician', 'active');
        $this->makeStaff('Nina', 'Offduty', 'nina.o', 'Nail Technician', 'inactive');

        // Seeded staff + 2 created = 3 total.
        $html = $this->listHtml(['position' => 'Nail Technician', 'status' => 'inactive', 'q' => 'nina']);

        $this->assertStringContainsString('Nina Offduty', $html);
        $this->assertStringNotContainsString('Andy Active', $html);
        $this->assertStringContainsString('Showing 1 of 3 employees', $html);
    }

    public function test_filtering_preserves_attendance_date(): void
    {
        $this->makeStaff('Wanda', 'Searchwick', 'wanda.s');

        $date = now()->addDays(9)->toDateString();
        $label = now()->addDays(9)->format('M j');

        $html = $this->listHtml(['date' => $date, 'q' => 'wanda']);

        $this->assertStringContainsString('Wanda Searchwick', $html);
        $this->assertStringContainsString('Attendance ('.$label.')', $html);
        $this->assertStringContainsString('value="'.$date.'"', $html);
    }

    public function test_status_filter_persists_in_session(): void
    {
        $this->makeStaff('Ivy', 'Inactive', 'ivy.i', 'Massage Technician', 'inactive');

        $this->listHtml(['status' => 'inactive']);

        $html = $this->listHtml();

        $this->assertStringContainsString('Ivy Inactive', $html);
        $this->assertStringNotContainsString('Sheila Marie Dela Cruz', $html);
        $this->assertMatchesRegularExpression('/<option value="inactive"[^>]*selected/', $html);
    }

    public function test_fresh_session_defaults_to_all(): void
    {
        $this->makeStaff('Ivy', 'Inactive', 'ivy.i', 'Massage Technician', 'inactive');

        $html = $this->listHtml();

        $this->assertStringContainsString('Ivy Inactive', $html);
        $this->assertStringContainsString('Sheila Marie Dela Cruz', $html);
        $this->assertMatchesRegularExpression('/<option value="all"[^>]*selected/', $html);
        $this->assertStringNotContainsString('Showing', $html);
    }

    public function test_no_leaked_script_text_on_employees_page(): void
    {
        $html = $this->listHtml();

        // The "+ New position" handler must live inside <script> tags:
        // strip script blocks and the handler text must be gone, while
        // the modal markup and the handler itself remain in the response.
        $textOnly = (string) preg_replace('#<script\b[^>]*>.*?</script>#s', '', $html);
        $this->assertStringNotContainsString('shared modal instead of submitting', $textOnly);
        $this->assertStringContainsString('id="newPositionModal"', $html);
        $this->assertStringContainsString("document.getElementById('newPositionForm')", $html);
        $this->assertSame(substr_count($html, '<script'), substr_count($html, '</script>'));
    }
}
