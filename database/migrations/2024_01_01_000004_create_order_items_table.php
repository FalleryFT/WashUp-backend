<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel item per order — kiloan utama + add-on (selimut, bedcover, pelembut, sabun)
// Ditampilkan di modal Detail Transaksi (tabel Item, Jumlah, Harga Satuan, Sub Total)

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services'); // relasi ke tabel services

            // Deskripsi tampilan (Kiloan / Selimut / Bedcover / Pelembut / Sabun)
            $table->string('item_name');

            // Untuk kiloan: quantity = berat (kg), unit = 'kg'
            // Untuk addon : quantity = jumlah item, unit = 'pcs'
            $table->decimal('quantity', 8, 2);
            $table->string('unit', 10)->default('kg'); // 'kg' | 'pcs'

            $table->unsignedInteger('unit_price');  // harga satuan
            $table->unsignedBigInteger('subtotal'); // quantity * unit_price

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
