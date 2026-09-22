<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            // percentage/fixed_amount discount a % or ₱ off each linked
            // Service's own price; bundle is a flat, standalone `price` (the
            // existing behavior every promo had before this migration).
            $table->string('discount_type', 20)->default('bundle')->after('description');
            $table->decimal('discount_value', 8, 2)->nullable()->after('discount_type');
            $table->date('starts_at')->nullable()->after('price');
            $table->date('ends_at')->nullable()->after('starts_at');
            // price is now only meaningful for a 'bundle' promo (a
            // percentage/fixed_amount promo computes its price per linked
            // Service instead — see Promo::effectivePriceFor()). ->change()
            // (via doctrine/dbal) keeps this portable across MySQL/SQLite/SQL
            // Server, unlike driver-specific raw ALTER TABLE SQL.
            $table->decimal('price', 8, 2)->nullable()->change();
        });

        // Promos can now apply to more than one Service (the wizard's
        // "Select Services" step) — replaces the single nullable service_id.
        Schema::create('promo_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unique(['promo_id', 'service_id']);
        });

        // Carry over each existing single service_id into the new pivot
        // before dropping the column, so no existing promo loses its link.
        DB::table('promos')->whereNotNull('service_id')->get(['id', 'service_id'])->each(function ($promo) {
            DB::table('promo_service')->insert([
                'promo_id' => $promo->id,
                'service_id' => $promo->service_id,
            ]);
        });

        Schema::table('promos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('price')->constrained()->nullOnDelete();
        });

        DB::table('promo_service')->select('promo_id', 'service_id')
            ->orderBy('id')
            ->get()
            ->unique('promo_id')
            ->each(function ($row) {
                DB::table('promos')->where('id', $row->promo_id)->update(['service_id' => $row->service_id]);
            });

        Schema::dropIfExists('promo_service');

        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'starts_at', 'ends_at']);
            $table->decimal('price', 8, 2)->nullable(false)->change();
        });
    }
};
