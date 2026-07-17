<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_remaining');
            $table->decimal('unit_cost', 10, 2);
            $table->dateTime('received_at');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['inventory_id', 'received_at']);
        });

        // Backfill: current on-hand stock becomes each item's opening batch so
        // FIFO consumption has something to draw from.
        $now = now();
        $items = DB::table('inventories')->where('quantity', '>', 0)->get();

        foreach ($items as $item) {
            DB::table('inventory_batches')->insert([
                'inventory_id' => $item->id,
                'quantity_received' => $item->quantity,
                'quantity_remaining' => $item->quantity,
                'unit_cost' => $item->price_per_unit,
                'received_at' => $item->created_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
