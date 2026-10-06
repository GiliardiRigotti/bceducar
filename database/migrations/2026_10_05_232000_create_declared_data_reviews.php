<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bc_declared_data_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pmd_id')->unique();
            $table->string('status')->default('PENDING');
            $table->text('reason')->nullable();
            $table->integer('native_student_person_id')->nullable();
            $table->integer('native_guardian_person_id')->nullable();
            $table->integer('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('bc_declared_data_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pmd_id')->index();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id');
            $table->string('event');
            $table->jsonb('changes')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bc_declared_data_events');
        Schema::dropIfExists('bc_declared_data_reviews');
    }
};
