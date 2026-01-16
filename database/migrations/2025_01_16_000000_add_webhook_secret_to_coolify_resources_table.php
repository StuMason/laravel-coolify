<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('coolify_resources', 'webhook_secret')) {
            Schema::table('coolify_resources', function (Blueprint $table) {
                $table->string('webhook_secret')->nullable()->after('redis_uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('coolify_resources', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};
