<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained(); // Sino ang gumawa ng action
        $table->string('event'); // 'created', 'updated', 'deleted'
        $table->string('auditable_type'); // Saan table naganap (e.g., Appointment, Inventory)
        $table->unsignedBigInteger('auditable_id'); // ID ng record na naapektuhan
        $table->json('old_values')->nullable(); // Before state
        $table->json('new_values')->nullable(); // After state
        $table->timestamps();
    });
}
};
