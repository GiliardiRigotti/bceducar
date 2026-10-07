<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bc_guardian_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('bc_guardian_profiles');
            $table->string('name');
            $table->string('cpf', 14)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('gender')->nullable();
            $table->string('rg', 50)->nullable();
            $table->string('birth_certificate')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->timestamps();
            $table->unique(['id', 'profile_id']);
            $table->index(['profile_id', 'name']);
        });
        Schema::table('bc_guardian_profile_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('dependent_id')->nullable();
            $table->foreign(['dependent_id', 'profile_id'], 'bc_application_dependent_owner')
                ->references(['id', 'profile_id'])->on('bc_guardian_dependents');
        });
        Schema::create('bc_guardian_dependent_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('dependent_id');
            $table->foreign(['dependent_id', 'profile_id'], 'bc_dependent_event_owner')
                ->references(['id', 'profile_id'])->on('bc_guardian_dependents');
            $table->unsignedBigInteger('pmd_id')->nullable();
            $table->string('event');
            $table->jsonb('changes');
            $table->string('reason', 1000)->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('bc_guardian_notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('bc_guardian_profiles');
            $table->foreignId('notification_id')->constrained('bc_guardian_notifications');
            $table->timestamp('read_at');
            $table->unique(['profile_id', 'notification_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('bc_guardian_dependents')->exists() || DB::table('bc_guardian_notice_reads')->exists()) {
            throw new RuntimeException('Rollback bloqueado: preserve dependentes, vínculos e histórico de comunicações.');
        }
        Schema::dropIfExists('bc_guardian_notice_reads');
        Schema::dropIfExists('bc_guardian_dependent_events');
        Schema::table('bc_guardian_profile_applications', function (Blueprint $table) {
            $table->dropForeign('bc_application_dependent_owner');
            $table->dropColumn('dependent_id');
        });
        Schema::dropIfExists('bc_guardian_dependents');
    }
};
