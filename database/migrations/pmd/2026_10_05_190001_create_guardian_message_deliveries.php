<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bc_guardian_message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('bc_guardian_notifications');
            $table->string('channel', 20);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->unique(['notification_id', 'channel']);
        });
    }

    public function down(): void
    {
        if (DB::table('bc_guardian_message_deliveries')->exists()) {
            throw new RuntimeException('Rollback bloqueado: preserve o histórico de entregas dos canais.');
        }
        Schema::dropIfExists('bc_guardian_message_deliveries');
    }
};
