<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('tenant')->create('files', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('owner_central_user_id')->index();
            $table->string('disk')->default('private');
            $table->string('path', 700)->unique();
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('bytes');
            $table->char('checksum_sha256', 64)->index();
            $table->string('scan_status', 32)->default('pending')->index();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->index(['owner_central_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('files');
    }
};
