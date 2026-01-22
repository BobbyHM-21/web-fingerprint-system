<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('badge_number')->unique(); // Kunci Utama (NIK)
            $table->string('name');
            $table->string('password')->nullable(); // Device password, not web login
            $table->string('card_number')->nullable();
            $table->integer('privilege')->default(0);

            // Tambahan untuk data HRD
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
