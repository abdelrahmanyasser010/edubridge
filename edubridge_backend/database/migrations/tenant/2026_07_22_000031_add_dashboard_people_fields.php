<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('teachers', function (Blueprint $table) {
            $table->string('specialization', 120)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('teachers', function (Blueprint $table) {
            $table->dropColumn('specialization');
        });
    }
};
