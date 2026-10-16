<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * The three classic positions with their service-category links — these
 * reproduce the old App\Enums\Position booking eligibility exactly.
 * Idempotent: re-running updates the links in place by name.
 */
class PositionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->positions() as $position) {
            Position::query()->updateOrCreate(
                ['name' => $position['name']],
                ['service_category' => $position['service_category']]
            );
        }
    }

    /**
     * @return array<int, array{name: string, service_category: string|null}>
     */
    public static function positions(): array
    {
        return [
            ['name' => 'Nail Technician', 'service_category' => 'Nails'],
            ['name' => 'Massage Technician', 'service_category' => 'Massage'],
            ['name' => 'Facial Technician', 'service_category' => 'Facial'],
        ];
    }
}
