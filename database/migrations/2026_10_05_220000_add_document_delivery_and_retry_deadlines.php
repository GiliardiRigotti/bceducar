<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->unsignedSmallInteger('documentation_delivery_days')->nullable();
            $table->unsignedSmallInteger('documentation_retry_days')->default(3);
        });
        Schema::table('bc_registration_documents', function (Blueprint $table) {
            $table->timestamp('delivery_deadline')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bc_registration_documents', fn (Blueprint $table) => $table->dropColumn('delivery_deadline'));
        Schema::table('processes', fn (Blueprint $table) => $table->dropColumn(['documentation_delivery_days', 'documentation_retry_days']));
    }
};
