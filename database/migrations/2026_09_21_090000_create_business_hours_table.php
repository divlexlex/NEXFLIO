<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sun, 6=Sat
            $table->time('open_at');
            $table->time('close_at');
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->unique('day_of_week');
        });

        // Seed per-day-type defaults: weekdays (Mon–Fri) 12:00 PM–1:00 AM,
        // weekends (Sat–Sun) 10:00 AM–1:00 AM (all Open).
        foreach (range(0, 6) as $day) {
            $weekend = in_array($day, [0, 6], true);
            DB::table('business_hours')->insert([
                'day_of_week' => $day,
                'open_at'     => $weekend ? '10:00:00' : '12:00:00',
                'close_at'    => '01:00:00',
                'is_open'     => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
