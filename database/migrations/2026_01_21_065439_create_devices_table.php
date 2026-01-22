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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // PENTING: Serial Number jadi kunci unik untuk ADMS (Solution X-100C)
            // Mesin ADMS mengidentifikasi diri pakai SN, bukan IP
            $table->string('serial_number')->unique()->nullable();

            // IP boleh kosong (kalau mesin offline/USB) dan tidak harus unique
            // karena IP di cabang bisa dinamis
            $table->string('ip_address')->nullable();
            $table->integer('port')->default(4370);

            // Protocol: 'push' (ADMS), 'standalone' (IP Langsung), 'offline' (USB)
            $table->string('protocol')->default('standalone');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_activity')->nullable();

            // Tambahan untuk tracking lokasi device
            $table->string('location')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
