<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preregistrations', function (Blueprint $table) {
            $table->string('document_submission_method')->nullable();
            $table->string('documentation_status')->nullable();
            $table->timestamp('documentation_deadline')->nullable();
        });

        Schema::table('processes', function (Blueprint $table) {
            $table->timestamp('documentation_deadline')->nullable();
        });

        Schema::create('preregistration_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('process_document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained('processes');
            $table->foreignId('document_type_id')->constrained('preregistration_document_types');
            $table->boolean('required')->default(true);
            $table->timestamps();
            $table->unique(['process_id', 'document_type_id']);
        });

        Schema::create('preregistration_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preregistration_id')->constrained('preregistrations');
            $table->foreignId('document_type_id')->constrained('preregistration_document_types');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->char('hash', 64);
            $table->string('status')->default('PENDING');
            $table->integer('uploaded_by')->nullable();
            $table->integer('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('replaces_id')->nullable()->constrained('preregistration_documents');
            $table->timestamps();
            $table->index(['preregistration_id', 'document_type_id', 'status'], 'pmd_documents_prereg_type_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preregistration_documents');
        Schema::dropIfExists('process_document_types');
        Schema::dropIfExists('preregistration_document_types');

        Schema::table('processes', fn (Blueprint $table) => $table->dropColumn('documentation_deadline'));
        Schema::table('preregistrations', fn (Blueprint $table) => $table->dropColumn([
            'document_submission_method', 'documentation_status', 'documentation_deadline',
        ]));
    }
};
