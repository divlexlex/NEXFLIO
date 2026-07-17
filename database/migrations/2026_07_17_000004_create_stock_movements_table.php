<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only ledger: no softDeletes on purpose — movements are never
        // edited or removed (immutability is also enforced at the model level).
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches');
            $table->string('type', 20); // in | out | pull_out | adjustment
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['inventory_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
