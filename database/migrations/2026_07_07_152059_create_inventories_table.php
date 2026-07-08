<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 public function up()
{
    Schema::create('inventories', function (Blueprint $table) {
        $table->id();
        $table->string('item_name');
        $table->string('unit'); 
        $table->integer('quantity'); 
        $table->integer('reorder_point'); 
        $table->decimal('price_per_unit', 8, 2); 
        $table->timestamps();
        $table->softDeletes();
    });
}
};
