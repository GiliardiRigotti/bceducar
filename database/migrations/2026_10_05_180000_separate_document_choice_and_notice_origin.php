<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bc_registration_requests', fn (Blueprint $table) => $table->string('attendance_mode')->nullable()->change());
        Schema::table('bc_guardian_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('registration_event_id')->nullable()->change();
            $table->foreignId('pmd_document_event_id')->nullable()->unique()->constrained('preregistration_document_events');
        });
        DB::statement('ALTER TABLE bc_guardian_notifications ADD CONSTRAINT bc_notice_one_origin CHECK ((registration_event_id IS NOT NULL)::int + (pmd_document_event_id IS NOT NULL)::int = 1)');
    }

    public function down(): void
    {
        if (DB::table('bc_registration_requests')->whereNull('attendance_mode')->exists()
            || DB::table('bc_guardian_notifications')->whereNotNull('pmd_document_event_id')->exists()) {
            throw new RuntimeException('Rollback bloqueado: preserve as escolhas pendentes e os avisos PMD antes de remover esta estrutura.');
        }
        DB::statement('ALTER TABLE bc_guardian_notifications DROP CONSTRAINT bc_notice_one_origin');
        Schema::table('bc_guardian_notifications', function (Blueprint $table) {
            $table->dropForeign(['pmd_document_event_id']);
            $table->dropUnique(['pmd_document_event_id']);
            $table->dropColumn('pmd_document_event_id');
            $table->unsignedBigInteger('registration_event_id')->nullable(false)->change();
        });
        Schema::table('bc_registration_requests', fn (Blueprint $table) => $table->string('attendance_mode')->nullable(false)->change());
    }
};
