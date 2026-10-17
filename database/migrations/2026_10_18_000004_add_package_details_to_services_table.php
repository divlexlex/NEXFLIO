<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Package inclusions for services in the "Packages" category, entered as
 * free text (one inclusion per line) on the admin Service form and rendered
 * as a list on the client side. Plain TEXT, nullable — every non-package
 * service leaves it empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->text('package_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('package_details');
        });
    }
};
