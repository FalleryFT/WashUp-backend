<?php
// database/migrations/2024_01_01_000007_add_softdelete_to_services_and_create_settings.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Tambah soft delete ke tabel services ──────────────────────────────
        Schema::table('services', function (Blueprint $table) {
            $table->softDeletes(); // kolom deleted_at
        });

        // ── Tabel settings (key-value) untuk max_berat_per_nota, dsb ─────────
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();   // 'max_berat_per_nota'
            $table->string('value');           // '7'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::dropIfExists('settings');
    }
};
