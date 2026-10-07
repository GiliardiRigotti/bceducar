<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bc_guardian_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
            $table->timestamp('verified_at');
            $table->timestamps();
        });
        Schema::create('bc_guardian_profile_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('bc_guardian_profiles');
            $table->unsignedBigInteger('pmd_id');
            $table->timestamp('verified_at');
            $table->unique(['profile_id', 'pmd_id']);
        });
        Schema::create('bc_guardian_profile_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('bc_guardian_profiles');
            $table->string('event');
            $table->unsignedBigInteger('pmd_id')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bc_guardian_profile_events');
        Schema::dropIfExists('bc_guardian_profile_applications');
        Schema::dropIfExists('bc_guardian_profiles');
    }
};
