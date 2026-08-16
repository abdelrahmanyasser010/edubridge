<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('broadcast_messages', function (Blueprint $table) {
            $table->string('type', 32)->default('announcement')->after('body');
            $table->json('target_ids')->nullable()->after('target');
            $table->json('channels')->nullable()->after('target_ids');
            $table->string('priority', 32)->default('normal')->after('channels');
            $table->string('status', 32)->default('draft')->after('priority')->index();
            $table->unsignedBigInteger('notification_id')->nullable()->after('status')->index();
            $table->timestamp('sent_at')->nullable()->after('scheduled_at');
            $table->timestamp('cancelled_at')->nullable()->after('sent_at');
            $table->unsignedBigInteger('cancelled_by_central_user_id')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('broadcast_messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'target_ids', 'channels', 'priority', 'status', 'notification_id', 'sent_at', 'cancelled_at', 'cancelled_by_central_user_id']);
        });
    }
};
