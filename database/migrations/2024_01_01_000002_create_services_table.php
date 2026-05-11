<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel layanan kiloan (Cuci Setrika, Cuci Kering, Setrika Saja)
// Dikelola via halaman Price Setting admin

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // Cuci Setrika, Cuci Kering, Setrika Saja
            $table->unsignedInteger('price'); // harga per Kg (Rp)
            $table->enum('type', ['kiloan', 'addon'])->default('kiloan');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
