<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Service;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::all()->keyBy('name');
        $findUser = fn($name) => User::where('name', $name)->first();

        $startDate = now()->subDays(6);

        $customers = [
            ['name' => 'Hamba Allah', 'user' => 'Steve Schoger', 'type' => 'member'],
            ['name' => 'Alan Cooper', 'user' => 'Anton Sten', 'type' => 'member'],
            ['name' => 'Steve Krug', 'user' => null, 'type' => 'non-member'],
            ['name' => 'Jeff Gothelf', 'user' => null, 'type' => 'non-member'],
            ['name' => 'Jared Spool', 'user' => 'Olivia Xu', 'type' => 'member'],
            ['name' => 'Khoi Vinh', 'user' => null, 'type' => 'non-member'],
            ['name' => 'Brad Frost', 'user' => 'Ridd', 'type' => 'member'],
        ];

        // ✅ Semua status ENUM kamu
        $allStatuses = [
            'Order Diterima',
            'Sedang Di Pilah',
            'Sedang Dicuci',
            'Siap Diambil',
            'Selesai',
            'Dibatalkan',
        ];

        $statusIndex = 0;

        foreach (range(0, 6) as $dayOffset) {

            $date = $startDate->copy()->addDays($dayOffset);

            foreach (range(1, 2) as $i) {

                $cust = $customers[array_rand($customers)];
                $serviceName = rand(0,1) ? 'Cuci Kering' : 'Cuci Setrika';
                $service = $services->get($serviceName);

                if (!$service) continue;

                $weight = rand(2, 10);

                // 🔥 Ambil status satu per satu supaya semua muncul
                $status = $allStatuses[$statusIndex % count($allStatuses)];
                $statusIndex++;

                // Timeline menyesuaikan status
                $timeline = [
                    "Order diterima\n" . $date->format('d M H:i'),
                    in_array($status, ['Sedang Di Pilah','Sedang Dicuci','Siap Diambil','Selesai'])
                        ? "Sedang Di Pilah\n" . $date->copy()->addHours(2)->format('d M H:i')
                        : null,
                    in_array($status, ['Sedang Dicuci','Siap Diambil','Selesai'])
                        ? "Sedang Dicuci\n" . $date->copy()->addHours(4)->format('d M H:i')
                        : null,
                    in_array($status, ['Siap Diambil','Selesai'])
                        ? "Siap Diambil\n" . $date->copy()->addDay()->format('d M H:i')
                        : null,
                ];

                $nota = rand(10000000, 99999999);
                $total = $weight * $service->price;

                $user = $cust['user'] ? $findUser($cust['user']) : null;

                $order = Order::create([
                    'nota'           => $nota,
                    'user_id'        => $user?->id,
                    'customer_name'  => $cust['name'],
                    'customer_phone' => null,
                    'customer_type'  => $cust['type'],
                    'service_id'     => $service->id,
                    'weight'         => $weight,
                    'total_price'    => $total,
                    'status'         => $status,
                    'timeline'       => json_encode($timeline),
                    'order_date'     => $date,
                    'estimated_date' => $date->copy()->addDay(),
                ]);

                // Item utama
                OrderItem::create([
                    'order_id'   => $order->id,
                    'service_id' => $service->id,
                    'item_name'  => 'Kiloan',
                    'quantity'   => $weight,
                    'unit'       => 'kg',
                    'unit_price' => $service->price,
                    'subtotal'   => $total,
                ]);

                // Addon random
                if (rand(0,1)) {
                    $addon = $services->random();

                    OrderItem::create([
                        'order_id'   => $order->id,
                        'service_id' => $addon->id,
                        'item_name'  => $addon->name,
                        'quantity'   => 1,
                        'unit'       => 'pcs',
                        'unit_price' => $addon->price,
                        'subtotal'   => $addon->price,
                    ]);
                }
            }
        }
    }
}