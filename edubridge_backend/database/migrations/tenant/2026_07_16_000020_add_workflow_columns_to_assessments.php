<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('assessments', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->unsignedBigInteger('approved_by_central_user_id')->nullable()->index()->after('submitted_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by_central_user_id');
            $table->timestamp('published_at')->nullable()->after('approved_at');
            $table->timestamp('locked_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('assessments', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'approved_by_central_user_id', 'approved_at', 'published_at', 'locked_at']);
        });
    }
};
