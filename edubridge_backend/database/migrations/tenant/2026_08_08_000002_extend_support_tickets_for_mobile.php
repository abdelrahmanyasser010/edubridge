<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('support_tickets', function (Blueprint $table) {
            $table->string('category_key', 64)->default('general')->after('opened_by_central_user_id')->index();
            $table->timestamp('resolved_at')->nullable()->after('status');
            $table->timestamp('closed_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['category_key', 'resolved_at', 'closed_at']);
        });
    }
};
