<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coolify_resources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('server_uuid');
            $table->string('project_uuid');
            $table->string('environment')->default('production');
            $table->string('deploy_key_uuid')->nullable();
            $table->string('repository')->nullable();
            $table->string('branch')->nullable();
            $table->string('application_uuid')->nullable();
            $table->string('database_uuid')->nullable();
            $table->string('redis_uuid')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coolify_resources');
    }
};
