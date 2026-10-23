<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('imported_owner_name')->nullable();
            $table->string('imported_department_name')->nullable();
            $table->string('assignment_note')->nullable();
            $table->string('wecom_external_userid')->nullable();
            $table->timestamp('wecom_added_at')->nullable();
            $table->string('conflict_wecom_userid')->nullable();
            $table->string('conflict_external_userid')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
