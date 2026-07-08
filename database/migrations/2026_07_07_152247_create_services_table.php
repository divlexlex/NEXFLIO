<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::create('services', function (Blueprint $table) {
        $table->id();
        $table->string('name'); 
        $table->string('category'); 
        $table->text('description'); 
        $table->decimal('price', 8, 2); 
        $table->integer('duration_minutes'); 
        $table->enum('status', ['active', 'inactive', 'suspended'])->default('active'); // Service status [cite: 139]
        $table->timestamps();
        $table->softDeletes();
    });
}
};
