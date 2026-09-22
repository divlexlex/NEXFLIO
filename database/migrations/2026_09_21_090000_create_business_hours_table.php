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

        // Seed default: 10AM–9PM daily
        $days = range(0, 6);
        foreach ($days as $day) {
            DB::table('business_hours')->insert([
                'day_of_week' => $day,
                'open_at'     => '10:00:00',
                'close_at'    => '21:00:00',
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
