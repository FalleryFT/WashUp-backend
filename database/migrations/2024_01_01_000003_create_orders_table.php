<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel transaksi/order utama
// Mencakup data dari halaman Dashboard, OrderList, New Transaction

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Nomor nota unik (8 digit angka, tampil di tabel)
            $table->string('nota', 20)->unique();

            // Pelanggan — bisa member (user terdaftar) atau non-member (walk-in)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name');              // nama pelanggan (member maupun non-member)
            $table->string('customer_phone')->nullable(); // no HP non-member
            $table->enum('customer_type', ['member', 'non-member'])->default('non-member');

            // Detail cucian
            $table->foreignId('service_id')->constrained('services'); // layanan kiloan
            $table->decimal('weight', 8, 2);                          // berat dalam Kg
            $table->unsignedBigInteger('total_price');                // total harga (Rupiah)

            // Status — sesuai badge di tampilan
            $table->enum('status', [
                'Order Diterima',
                'Sedang Di Pilah',
                'Sedang Dicuci',
                'Siap Diambil',
                'Selesai',
                'Dibatalkan',
            ])->default('Order Diterima');

            // Timeline progress (JSON array 4 step)
            // Contoh: ["Order di terima\n17 Jan 10.30", "Sedang Di Pilah\n...", null, null]
            $table->json('timeline')->nullable();

            $table->date('order_date');       // tanggal order
            $table->date('estimated_date');   // estimasi selesai

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
