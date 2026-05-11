<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;
use App\Models\Order;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $admin    = User::where('role', 'admin')->first();
        $orders   = Order::all();
        $customers = User::where('role', 'customer')->get();

        // Notifikasi untuk Admin
        foreach ($orders->take(5) as $order) {
            Notification::create([
                'user_id'  => $admin->id,
                'order_id' => $order->id,
                'title'    => 'Order Baru Masuk',
                'message'  => "Order baru dari {$order->customer_name} - Nota #{$order->nota} telah masuk.",
                'is_read'  => false,
            ]);
        }

        // Notifikasi untuk Customer
        $notifTemplates = [
            ['title' => 'Pesanan Diterima',   'message' => 'Pesanan Anda telah diterima dan sedang diproses.'],
            ['title' => 'Cucian Sedang Dicuci','message' => 'Cucian Anda sedang dalam proses pencucian.'],
            ['title' => 'Cucian Siap Diambil', 'message' => 'Cucian Anda sudah selesai dan siap diambil.'],
        ];

        foreach ($customers->take(6) as $i => $customer) {
            $order  = $orders->get($i);
            $tmpl   = $notifTemplates[$i % count($notifTemplates)];

            Notification::create([
                'user_id'  => $customer->id,
                'order_id' => $order?->id,
                'title'    => $tmpl['title'],
                'message'  => $tmpl['message'],
                'is_read'  => ($i % 2 === 0), // sebagian sudah dibaca
            ]);
        }
    }
}
