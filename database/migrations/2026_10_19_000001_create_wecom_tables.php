<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wecom_department_id')->unique();
            $table->string('name');
            $table->unsignedBigInteger('parent_wecom_id')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('type');
            $table->timestamp('missing_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('is_admin')->constrained('departments')->nullOnDelete();
            $table->foreignId('counter_id')->nullable()->after('store_id')->constrained('departments')->nullOnDelete();
        });

        Schema::create('wecom_settings', function (Blueprint $table) {
            $table->id();
            $table->string('corp_id');
            $table->string('agent_id');
            $table->text('secret');
            $table->timestamps();
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->unsignedInteger('departments_created')->default(0);
            $table->unsignedInteger('departments_updated')->default(0);
            $table->unsignedInteger('users_created')->default(0);
            $table->unsignedInteger('users_updated')->default(0);
            $table->unsignedInteger('users_left')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('wecom_settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counter_id');
            $table->dropConstrainedForeignId('store_id');
        });
        Schema::dropIfExists('departments');
    }
};
