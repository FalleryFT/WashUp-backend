<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Chat;
use App\Models\User;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $admin     = User::where('role', 'admin')->first();
        $customers = User::where('role', 'customer')->take(3)->get();

        $conversations = [
            [
                ['sender' => 'customer', 'message' => 'Halo min, cucian saya sudah selesai belum?'],
                ['sender' => 'admin',    'message' => 'Halo! Cucian Anda sedang dalam proses, estimasi selesai besok pagi.'],
                ['sender' => 'customer', 'message' => 'Oke, terima kasih min!'],
                ['sender' => 'admin',    'message' => 'Sama-sama, kami akan notifikasi jika sudah siap diambil 😊'],
            ],
            [
                ['sender' => 'customer', 'message' => 'Min, bisa antar ke rumah ga?'],
                ['sender' => 'admin',    'message' => 'Mohon maaf, saat ini kami belum menyediakan layanan antar. Silakan ambil langsung di toko.'],
                ['sender' => 'customer', 'message' => 'Oh gitu, oke deh. Jam berapa bukanya?'],
                ['sender' => 'admin',    'message' => 'Kami buka setiap hari pukul 08.00 - 20.00 WIB.'],
            ],
            [
                ['sender' => 'customer', 'message' => 'Minta struk pembayaran min?'],
                ['sender' => 'admin',    'message' => 'Bisa, silakan ambil saat pickup ya, kami siapkan stuknya.'],
            ],
        ];

        foreach ($customers as $idx => $customer) {
            $conv = $conversations[$idx] ?? $conversations[0];
            foreach ($conv as $msg) {
                Chat::create([
                    'sender_id'   => $msg['sender'] === 'admin' ? $admin->id : $customer->id,
                    'receiver_id' => $msg['sender'] === 'admin' ? $customer->id : $admin->id,
                    'message'     => $msg['message'],
                    'is_read'     => true,
                ]);
            }
        }
    }
}
