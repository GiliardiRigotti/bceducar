<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bc_registration_documents', function (Blueprint $table) {
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('sha256', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bc_registration_documents', function (Blueprint $table) {
            $table->dropColumn(['original_filename', 'mime_type', 'file_size', 'sha256']);
        });
    }
};
