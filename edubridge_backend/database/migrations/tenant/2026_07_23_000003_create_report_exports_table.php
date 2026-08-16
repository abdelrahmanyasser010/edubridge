<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->string('report_type', 80)->index();
            $table->string('status', 32)->default('queued')->index();
            $table->json('payload');
            $table->string('outbox_event_id', 80)->nullable()->index();
            $table->string('download_url', 500)->nullable();
            $table->unsignedBigInteger('requested_by_central_user_id')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('report_exports');
    }
};
