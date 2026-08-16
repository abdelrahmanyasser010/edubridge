<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->after('password')->index();
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('tokenable_id')->constrained()->nullOnDelete();
            $table->string('device_id', 128)->nullable()->after('abilities');
            $table->string('app_type', 32)->nullable()->after('device_id');
            $table->string('device_name', 128)->nullable()->after('app_type');
            $table->string('last_ip_address', 64)->nullable()->after('device_name');
            $table->timestamp('revoked_at')->nullable()->after('last_used_at')->index();

            $table->index(['tokenable_type', 'tokenable_id', 'school_id', 'device_id'], 'pat_device_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('pat_device_lookup_index');
            $table->dropConstrainedForeignId('school_id');
            $table->dropColumn(['device_id', 'app_type', 'device_name', 'last_ip_address', 'revoked_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
