<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reviews became feedback-only (sha, 2026-10-30): no more star
     * ratings, only a comment. New feedback rows carry NULL ratings,
     * so service_rating must be nullable (personnel_rating already
     * is). Legacy rows keep their old ratings in the DB, unused.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('service_rating')->nullable()->change();
        });
    }

    /**
     * Reverse: fails if any NULL service_rating rows exist (e.g. new
     * feedback-only rows) — those must be given a rating first.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('service_rating')->nullable(false)->change();
        });
    }
};
