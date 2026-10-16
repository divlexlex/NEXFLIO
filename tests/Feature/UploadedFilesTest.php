<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploaded files served through GET /files/{path} (named files.show) off
 * the 'public' disk — no public/storage symlink needed. Payment proofs
 * are manager-only; service/promo images are public.
 */
class UploadedFilesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function jpegBytes(): string
    {
        return UploadedFile::fake()->image('photo.jpg')->getContent();
    }

    public function test_guest_can_get_public_service_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('services/facial.jpg', $this->jpegBytes());

        $response = $this->get(url('files/services/facial.jpg'))->assertOk();
        $this->assertStringContainsString('image/', $response->headers->get('Content-Type'));
    }

    public function test_generated_file_url_resolves(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('services/facial.jpg', $this->jpegBytes());

        // Named-route generation keeps slashes unencoded, so the URL
        // resolves directly with no url() workaround needed.
        $url = route('files.show', ['path' => 'services/facial.jpg']);
        $this->assertStringContainsString('/files/services/facial.jpg', $url);

        $response = $this->get($url)->assertOk();
        $this->assertStringContainsString('image/', $response->headers->get('Content-Type'));
    }

    public function test_payment_proof_denied_for_guest_client_and_staff(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('payment_proofs/proof.jpg', $this->jpegBytes());

        $this->get(url('files/payment_proofs/proof.jpg'))->assertForbidden();

        $this->actingAs(User::where('email', 'client@nexflio.test')->firstOrFail())
            ->get(url('files/payment_proofs/proof.jpg'))->assertForbidden();

        $this->actingAs(User::where('email', 'staff@nexflio.test')->firstOrFail())
            ->get(url('files/payment_proofs/proof.jpg'))->assertForbidden();
    }

    public function test_payment_proof_allowed_for_manager(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('payment_proofs/proof.jpg', $this->jpegBytes());

        $response = $this->actingAs(User::where('email', 'manager@nexflio.test')->firstOrFail())
            ->get(url('files/payment_proofs/proof.jpg'))->assertOk();
        $this->assertStringContainsString('image/', $response->headers->get('Content-Type'));
    }

    public function test_missing_file_is_404(): void
    {
        Storage::fake('public');

        $this->get(url('files/services/nope.jpg'))->assertNotFound();
    }

    public function test_traversal_path_is_never_served(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('services/facial.jpg', $this->jpegBytes());

        $this->actingAs(User::where('email', 'manager@nexflio.test')->firstOrFail())
            ->get(url('files/payment_proofs/../../services/facial.jpg'))
            ->assertNotFound();
    }

    public function test_payments_page_renders_files_urls_and_no_storage_links(): void
    {
        Storage::fake('public');

        $client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $service = Service::where('status', 'active')->firstOrFail();

        $appointment = Appointment::create([
            'user_id' => $client->id,
            'service_id' => $service->id,
            'personnel_id' => $staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'status' => AppointmentStatus::Unverified,
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => 300,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/proof.jpg',
            'status' => PaymentStatus::Pending,
        ]);

        $html = $this->actingAs(User::where('email', 'manager@nexflio.test')->firstOrFail())
            ->get(route('admin.payments'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('/files/payment_proofs/proof.jpg', $html);
        $this->assertStringNotContainsString('/storage/', $html);
    }

    public function test_has_image_returns_files_url(): void
    {
        $service = Service::create([
            'name' => 'Image URL (test)', 'category' => 'Facial', 'description' => 'Test',
            'price' => 100, 'duration_minutes' => 30, 'status' => 'active',
            'service_location_type' => 'branch', 'image_path' => 'services/facial.jpg',
        ]);

        $this->assertStringContainsString('/files/services/facial.jpg', $service->imageUrl);
    }
}
