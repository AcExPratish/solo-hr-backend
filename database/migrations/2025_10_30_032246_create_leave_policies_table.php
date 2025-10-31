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
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('leave_type_id');
            $table->foreignUuid('user_id');
            $table->string('policy_name')->nullable();
            $table->unsignedInteger('total_days')->default(0);
            $table->unsignedInteger('remaining_days')->default(0);
            $table->foreignUuid('created_by_id')->nullable();
            $table->foreignUuid('updated_by_id')->nullable();
            $table->unique(['leave_type_id', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
    }
};
