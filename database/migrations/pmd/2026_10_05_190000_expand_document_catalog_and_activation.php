<?php

use App\EnrollmentRequests\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bc_registration_documents', fn (Blueprint $table) => $table->string('document_name', 150)->nullable());
        Schema::table('processes', function (Blueprint $table) {
            $table->boolean('document_workflow_enabled')->nullable();
            $table->integer('documentation_configured_by')->nullable();
            $table->timestamp('documentation_configured_at')->nullable();
        });
        // Preserve existing processes as null (legacy compatibility), require activation for newly created ones.
        DB::statement('ALTER TABLE processes ALTER COLUMN document_workflow_enabled SET DEFAULT false');
    }

    public function down(): void
    {
        if (DB::table('bc_registration_documents')->whereNotIn('document_type', array_column(DocumentType::cases(), 'value'))->exists()
            || DB::table('processes')->whereNotNull('document_workflow_enabled')->exists()) {
            throw new RuntimeException('Rollback bloqueado: existem categorias adicionais ou decisões de ativação a preservar.');
        }
        Schema::table('bc_registration_documents', fn (Blueprint $table) => $table->dropColumn('document_name'));
        Schema::table('processes', fn (Blueprint $table) => $table->dropColumn(['document_workflow_enabled', 'documentation_configured_by', 'documentation_configured_at']));
    }
};
