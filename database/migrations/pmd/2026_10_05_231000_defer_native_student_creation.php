<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bc_registration_requests', function (Blueprint $table) {
            $table->integer('student_id')->nullable()->change();
            $table->integer('guardian_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('bc_registration_requests')->whereNull('student_id')->orWhereNull('guardian_id')->exists()) {
            throw new RuntimeException('Existem dados declarados ainda não consolidados. Não é seguro remover este fluxo.');
        }
        Schema::table('bc_registration_requests', function (Blueprint $table) {
            $table->integer('student_id')->nullable(false)->change();
            $table->integer('guardian_id')->nullable(false)->change();
        });
    }
};
