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
        $services  = Service::all()->keyBy('name');
        $findUser  = fn($name) => User::where('name', $name)->first();
        $startDate = now()->subDays(59);

        $customers = [
            ['name' => 'Hamba Allah',  'user' => 'Steve Schoger', 'type' => 'member'],
            ['name' => 'Alan Cooper',  'user' => 'Anton Sten',    'type' => 'member'],
            ['name' => 'Steve Krug',   'user' => null,            'type' => 'non-member'],
            ['name' => 'Jeff Gothelf', 'user' => null,            'type' => 'non-member'],
            ['name' => 'Jared Spool',  'user' => 'Olivia Xu',    'type' => 'member'],
            ['name' => 'Khoi Vinh',    'user' => null,            'type' => 'non-member'],
            ['name' => 'Brad Frost',   'user' => 'Ridd',          'type' => 'member'],
        ];

        $allStatuses = [
            'Order Diterima',
            'Sedang Di Pilah',
            'Sedang Dicuci',
            'Siap Diambil',
            'Selesai',
            'Dibatalkan',
        ];

        $statusIndex = 0;

        // 1. ORDER UMUM — 2 order per hari selama 60 hari
        foreach (range(0, 59) as $dayOffset) {
            $date = $startDate->copy()->addDays($dayOffset);
            foreach (range(1, 2) as $i) {
                $cust        = $customers[array_rand($customers)];
                $serviceName = rand(0, 1) ? 'Cuci Kering' : 'Cuci Setrika';
                $service     = $services->get($serviceName);
                if (!$service) continue;

                $status = $allStatuses[$statusIndex % count($allStatuses)];
                $statusIndex++;

                $this->createOrder(
                    service: $service,
                    userId: $cust['user'] ? $findUser($cust['user'])?->id : null,
                    customerName: $cust['name'],
                    customerType: $cust['type'],
                    weight: rand(2, 10),
                    status: $status,
                    date: $date,
                    services: $services,
                );
            }
        }

        // 2. ORDER UDIN
        $udin = User::firstOrCreate(
            ['name' => 'Udin'],
            [
                'phone'    => '081299990000',
                'address'  => 'Jalan Soekarno-Hatta No. 9, Lowokwaru, Kota Malang, Jawa Timur 65141',
                'password' => bcrypt('udin1234'),
                'role'     => 'customer',
            ]
        );

        $serviceNames = ['Cuci Kering', 'Cuci Setrika'];

        // 10 pesanan SELESAI
        foreach (range(1, 10) as $n) {
            $orderDate   = $startDate->copy()->addDays(($n - 1) * 6 + rand(0, 3));
            $serviceName = $serviceNames[$n % 2];
            $service     = $services->get($serviceName);
            if (!$service) continue;

            $this->createOrder(
                service: $service,
                userId: $udin->id,
                customerName: 'Udin',
                customerType: 'member',
                weight: rand(2, 8),
                status: 'Selesai',
                date: $orderDate,
                services: $services,
            );
        }

        // 4 pesanan BERURUTAN (progress aktif)
        $progressStatuses = ['Order Diterima', 'Sedang Di Pilah', 'Sedang Dicuci', 'Siap Diambil'];
        foreach ($progressStatuses as $idx => $progressStatus) {
            $orderDate   = now()->subDays(3 - $idx);
            $serviceName = $serviceNames[$idx % 2];
            $service     = $services->get($serviceName);
            if (!$service) continue;

            $this->createOrder(
                service: $service,
                userId: $udin->id,
                customerName: 'Udin',
                customerType: 'member',
                weight: rand(3, 7),
                status: $progressStatus,
                date: $orderDate,
                services: $services,
            );
        }
    }

    private function createOrder(
        $service,
        ?int $userId,
        string $customerName,
        string $customerType,
        int $weight,
        string $status,
        $date,
        $services,
    ): void {
        $total = $weight * $service->price;
        $fmt   = fn($dt) => $dt->format('d M H.i');
        $time  = $fmt($date->copy());

        // Bangun 4 slot timeline berdasarkan status saat ini.
        // Slot yang belum tercapai selalu null.
        // Format tiap slot: "Label\nDD Mon HH.mm"
        $timeline = match ($status) {
            'Order Diterima'  => [
                "Order di terima\n{$time}",
                null,
                null,
                null,
            ],
            'Sedang Di Pilah' => [
                "Order di terima\n{$time}",
                "Sedang Di Pilah\n{$time}",
                null,
                null,
            ],
            'Sedang Dicuci'   => [
                "Order di terima\n{$time}",
                "Sedang Di Pilah\n{$time}",
                "Sedang Di cuci\n{$time}",
                null,
            ],
            'Siap Diambil'    => [
                "Order di terima\n{$time}",
                "Sedang Di Pilah\n{$time}",
                "Sedang Di cuci\n{$time}",
                "Siap Di Ambil\n{$time}",
            ],
            'Selesai'         => [
                "Order di terima\n{$time}",
                "Sedang Di Pilah\n{$time}",
                "Sedang Di cuci\n{$time}",
                "Selesai\n{$time}",
            ],
            'Dibatalkan'      => [
                "Order di terima\n{$time}",
                "Dibatalkan\n{$time}",
                null,
                null,
            ],
            default           => [null, null, null, null],
        };

        $order = Order::create([
            'nota'           => rand(10000000, 99999999),
            'user_id'        => $userId,
            'customer_name'  => $customerName,
            'customer_phone' => null,
            'customer_type'  => $customerType,
            'service_id'     => $service->id,
            'weight'         => $weight,
            'total_price'    => $total,
            'status'         => $status,
            'timeline'       => $timeline,
            'order_date'     => $date,
            'estimated_date' => $date->copy()->addDay(),
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'service_id' => $service->id,
            'item_name'  => 'Kiloan',
            'quantity'   => $weight,
            'unit'       => 'kg',
            'unit_price' => $service->price,
            'subtotal'   => $total,
        ]);

        if (rand(0, 1)) {
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