<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preregistration_document_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preregistration_id')->constrained('preregistrations');
            $table->string('event');
            $table->string('actor_type');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['preregistration_id', 'created_at'], 'pmd_document_event_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preregistration_document_events');
    }
};
