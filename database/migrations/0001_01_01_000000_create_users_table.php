<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();             // username login
            $table->string('phone')->unique()->nullable(); // no_hp
            $table->text('address')->nullable();           // alamat
            $table->string('password');
            $table->enum('role', ['admin', 'customer'])->default('customer');
            $table->rememberToken();
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
 