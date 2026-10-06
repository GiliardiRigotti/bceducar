<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bc_registration_requests', function (Blueprint $table) {
            // Optional integration; host migrations run before PMD tables on a clean installation.
            $table->unsignedBigInteger('pmd_preregistration_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('bc_registration_requests', fn (Blueprint $table) => $table->dropColumn('pmd_preregistration_id'));
    }
};
