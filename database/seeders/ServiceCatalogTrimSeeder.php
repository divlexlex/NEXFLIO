<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentAddress;
use App\Models\AppointmentChangeRequest;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ONE-OFF cleanup (do NOT register in DatabaseSeeder). Run exactly once with:
 *
 *   php artisan db:seed --class=ServiceCatalogTrimSeeder
 *
 * Removes every service that is not on the owner's KEEP list — plus its
 * dependent data — so the local database matches the trimmed ServiceSeeder.
 * Already-soft-deleted rows are included (withTrashed()) and forceDelete()d:
 * a soft delete is NOT acceptable here, the data must be gone.
 *
 * Idempotent: a second run finds nothing to delete. Services only — no
 * category/inventory/user/config code is touched, and promos are never
 * deleted (only their links to a removed service are detached).
 */
class ServiceCatalogTrimSeeder extends Seeder
{
    /** Exact names that survive. Anything else in `services` is removed. */
    private const KEEP = [
        // Facial
        'Basic Facial', 'Signature Facial', 'Hydra Facial',
        // Massage
        'Swedish Massage', 'Hot Stone Massage', 'Aromatherapy (Herbal Ball)',
        // Nails
        'Manicure', 'Pedicure', 'Gel Manicure',
        // Lashes & Brows
        'Classic Lashes', 'Lash Lift', 'Brow Lamination',
        // Aesthetics
        'BB Glow', 'Warts Removal (Per Area)', 'Whitening Body Scrub',
        // Head Spa (packages + kiddie)
        'Package EXA', 'Package DEE', 'Package SAM', 'Perfect Package', 'Package LIZ', 'Kiddie Japanese Head Spa',
        // Home Service
        'Home Service — Swedish Massage', 'Home Service — Hot Stone Massage', 'Home Service — Perfect Signature Massage',
    ];

    /**
     * The full catalog the original ServiceSeeder used to contain — used only
     * to group the report into "from the original seed set" vs
     * "manually-added (found only in the DB)".
     */
    private const ORIGINAL_SEED = [
        'Basic Facial', 'Signature Facial', 'Hydra Facial', 'Galvanic Facial', 'Pimple & Acne Treatment',
        'Swedish Massage', 'Combination Massage', 'Deep Tissue Massage', 'Shiatsu / Dry Massage', 'Ventosa Massage',
        'Hot Stone Massage', 'Foot Reflexology', 'Hand Reflexology', 'Aromatherapy (Herbal Ball)', 'Prenatal Massage',
        'Postnatal Massage w/ Lactation', 'Twin Massage (Four Hands)',
        'Manicure', 'Pedicure', 'Gel Manicure', 'Gel Pedicure', 'Gel Removal', 'French Tip', 'Cat Eye Polish',
        'Soft Gel Extension', 'Soft Gel Removal', 'Nail Repair (Per Nail)', 'Nail Art — Cat Eye',
        'Classic Lashes', 'Cat Eye Lashes', 'Wispy Lashes', 'Hybrid Volume Lashes', 'Lash Lift', 'Lash Tint',
        'Brow Lamination', 'Brow Threading', 'Brow Tint',
        'Korean Pico Laser — Full Face', 'IPL Hair Removal — Underarm', 'IPL Hair Removal — Brazilian',
        'RF Therapy — Full Face', 'RF Therapy — Tummy', 'BB Glow', 'BB Blush', 'HIFU — Full Face',
        'Meso Lipo Injection — V-Line', 'Warts Removal (Per Area)', 'Whitening Body Scrub',
        'Package EXA', 'Package DEE', 'Package SAM', 'Perfect Package', 'Package LIZ', 'Kiddie Japanese Head Spa',
        'Home Service — Swedish Massage', 'Home Service — Hot Stone Massage', 'Home Service — Prenatal Massage',
        'Home Service — Perfect Signature Massage',
    ];

    private const CATEGORY_ORDER = ['Facial', 'Massage', 'Nails', 'Lashes & Brows', 'Aesthetics', 'Head Spa', 'Home Service'];

    public function run(): void
    {
        $this->printCounts('BEFORE');

        $deletable = Service::withTrashed()
            ->whereNotIn('name', self::KEEP)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $deletedNames = [];
        $deletedFromSeed = [];
        $deletedManual = [];
        $appointmentsDeleted = 0;
        $paymentsDeleted = 0;
        $changeRequestsDeleted = 0;
        $addressesDeleted = 0;

        foreach ($deletable as $service) {
            // (a) Detach promo links — the promos themselves stay.
            DB::table('promo_service')->where('service_id', $service->id)->delete();

            // Every appointment of this service, including soft-deleted ones,
            // since a trashed row still holds the FK.
            $appointmentIds = Appointment::withTrashed()
                ->where('service_id', $service->id)
                ->pluck('id');

            if ($appointmentIds->isNotEmpty()) {
                // (b) Payments of those appointments.
                $paymentsDeleted += $this->forceDeleteCount(Payment::withTrashed()->whereIn('appointment_id', $appointmentIds));

                // (c) Change requests + address snapshots.
                $changeRequestsDeleted += AppointmentChangeRequest::whereIn('appointment_id', $appointmentIds)->delete();
                $addressesDeleted += $this->forceDeleteCount(AppointmentAddress::withTrashed()->whereIn('appointment_id', $appointmentIds));

                // (d) The appointments themselves.
                $appointmentsDeleted += $this->forceDeleteCount(Appointment::withTrashed()->whereIn('id', $appointmentIds));
            }

            // (e) The service itself — a soft delete is not enough. (Any
            // service_variants / wishlists rows cascade away with it.)
            $deletedNames[] = $service->name;
            if (in_array($service->name, self::ORIGINAL_SEED, true)) {
                $deletedFromSeed[] = $service->name;
            } else {
                $deletedManual[] = $service->name;
            }

            $service->forceDelete();
        }

        sort($deletedNames);
        sort($deletedFromSeed);
        sort($deletedManual);

        $this->command->newLine();
        $this->command->info('Deleted '.count($deletedNames).' service(s).');
        $this->command->line('  From the original seed set: '.count($deletedFromSeed));
        foreach ($deletedFromSeed as $name) {
            $this->command->line('    - '.$name);
        }
        $this->command->line('  Manually-added (found only in the DB): '.count($deletedManual));
        foreach ($deletedManual as $name) {
            $this->command->line('    - '.$name);
        }

        $this->command->newLine();
        $this->command->line('Dependents removed: '.$appointmentsDeleted.' appointments, '
            .$paymentsDeleted.' payments, '.$changeRequestsDeleted.' change requests, '
            .$addressesDeleted.' address snapshots.');

        // Promos left with zero linked services (never deleted here).
        $emptyPromos = Promo::with('services')->get()
            ->filter(fn (Promo $promo) => $promo->services->isEmpty())
            ->pluck('title')
            ->all();
        $this->command->line('Promos now with zero linked services: '
            .(count($emptyPromos) ? implode(', ', $emptyPromos) : 'none'));

        $this->printCounts('AFTER');
    }

    /** Mass-delete a soft-deletable query for real, returning the row count. */
    private function forceDeleteCount($query): int
    {
        return $query->forceDelete();
    }

    private function printCounts(string $label): void
    {
        $live = Service::query()->get(['category'])->groupBy('category')->map->count();
        $liveTotal = Service::query()->count();
        $withTrashedTotal = Service::withTrashed()->count();

        $this->command->newLine();
        $this->command->info("Service counts ({$label}) — live:");
        foreach (self::CATEGORY_ORDER as $category) {
            $this->command->line(sprintf('    %-16s %d', $category, (int) ($live[$category] ?? 0)));
        }
        $this->command->line('    -------------------------');
        $this->command->line(sprintf('    %-16s %d', 'TOTAL (live)', $liveTotal));
        $this->command->line(sprintf('    %-16s %d', 'TOTAL (+trashed)', $withTrashedTotal));
    }
}
