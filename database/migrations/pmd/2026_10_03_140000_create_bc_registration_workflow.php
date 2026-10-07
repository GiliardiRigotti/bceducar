<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bc_demo_entities', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('key');
            $table->unsignedBigInteger('legacy_id');
            $table->unique(['source', 'key']);
        });
        Schema::create('bc_registration_requests', function (Blueprint $table) {
            $table->id();
            $table->string('protocol')->unique();
            $table->integer('student_id');
            $table->integer('guardian_id');
            $table->integer('school_id');
            $table->integer('grade_id');
            $table->integer('school_class_id');
            $table->integer('school_year');
            $table->string('attendance_mode');
            $table->string('status')->index();
            $table->string('kind')->default('NOVA');
            $table->string('source')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('seed_source')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('document_deadline')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('registration_id')->nullable()->unique();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'school_year']);
            $table->foreign('student_id')->references('cod_aluno')->on('pmieducar.aluno');
            $table->foreign('guardian_id')->references('idpes')->on('cadastro.fisica');
            $table->foreign('school_id')->references('cod_escola')->on('pmieducar.escola');
            $table->foreign('grade_id')->references('cod_serie')->on('pmieducar.serie');
            $table->foreign('school_class_id')->references('cod_turma')->on('pmieducar.turma');
            $table->foreign('registration_id')->references('cod_matricula')->on('pmieducar.matricula');
        });
        Schema::create('bc_registration_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_request_id')->constrained('bc_registration_requests');
            $table->string('document_type');
            $table->string('status');
            $table->boolean('required')->default(false);
            $table->string('path')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->integer('reviewed_by')->nullable();
            $table->foreignId('replaces_id')->nullable()->constrained('bc_registration_documents');
            $table->timestamps();
            $table->index(['registration_request_id', 'document_type']);
        });
        Schema::create('bc_registration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_request_id')->constrained('bc_registration_requests');
            $table->string('event');
            $table->integer('actor_id')->nullable();
            $table->string('actor_type');
            $table->foreignId('document_id')->nullable()->constrained('bc_registration_documents');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bc_registration_events');
        Schema::dropIfExists('bc_registration_documents');
        Schema::dropIfExists('bc_registration_requests');
        Schema::dropIfExists('bc_demo_entities');
    }
};
