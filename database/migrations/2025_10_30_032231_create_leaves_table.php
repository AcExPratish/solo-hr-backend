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
        Schema::create('leaves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id');
            $table->foreignUuid('leave_type_id');
            $table->string('from_date');
            $table->string('to_date');
            $table->unsignedInteger('total_days');
            $table->string('reason')->nullable();
            $table->string('status');
            $table->foreignUuid('approved_by_id')->nullable();
            $table->foreignUuid('created_by_id')->nullable();
            $table->foreignUuid('updated_by_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
