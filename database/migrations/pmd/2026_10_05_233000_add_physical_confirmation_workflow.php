<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->unsignedSmallInteger('physical_delivery_days')->default(7);
            $table->unsignedSmallInteger('physical_retry_days')->default(3);
        });
        Schema::table('bc_registration_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('workflow_version')->default(1);
            $table->integer('intermediate_registration_id')->nullable()->unique();
            $table->string('integration_status', 20)->nullable();
            $table->timestamp('integrated_at')->nullable();
            $table->timestamp('physical_deadline')->nullable();
            $table->timestamp('physical_confirmed_at')->nullable();
            $table->integer('physical_confirmed_by')->nullable();
        });
        Schema::create('bc_physical_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_request_id')->constrained('bc_registration_requests');
            $table->string('subject', 100);
            $table->string('status', 20);
            $table->text('reason')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->integer('reviewed_by');
            $table->timestamps();
            $table->unique(['registration_request_id', 'subject']);
        });
    }

    public function down(): void
    {
        if (DB::table('bc_registration_requests')->whereNotNull('intermediate_registration_id')->exists()) {
            throw new RuntimeException('Vínculos intermediários exigem tratamento explícito antes de desfazer a migração.');
        }
        Schema::dropIfExists('bc_physical_reviews');
        Schema::table('bc_registration_requests', fn (Blueprint $table) => $table->dropColumn(['workflow_version', 'intermediate_registration_id', 'integration_status', 'integrated_at', 'physical_deadline', 'physical_confirmed_at', 'physical_confirmed_by']));
        Schema::table('processes', fn (Blueprint $table) => $table->dropColumn(['physical_delivery_days', 'physical_retry_days']));
    }
};
