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
        // Ambil service kiloan berdasarkan nama
        $services = Service::all()->keyBy('name');

        // Helper: cari user berdasarkan nama tampilan
        $findUser = fn($name) => User::where('name', $name)->first();

        // ── DATA ORDER — dari initialData OrderList.jsx ────────────────────────
        // Setiap entry berisi: nota, customer, service, weight, items, status, timeline, dates
        $orders = [
            // 1 ─ Hamba Allah (Member, Cuci Kering, 10Kg)
            [
                'nota'          => '17081945',
                'user'          => 'Steve Schoger', // sesuai customerId 17081945 di CustomerData
                'customer_name' => 'Hamba Allah',
                'customer_phone'=> null,
                'customer_type' => 'member',
                'service'       => 'Cuci Kering',
                'weight'        => 10,
                'status'        => 'order diterima',
                'order_date'    => '1983-01-17',
                'estimated_date'=> '1983-01-18',
                'timeline'      => ["Order di terima\n17 Jan 10.30", "Sedang Di Pilah\n17 Jan 11.00", "Sedang Di cuci\nSedang Berjalan", null],
                'items'         => [
                    ['service' => 'Cuci Kering', 'name' => 'Kiloan', 'qty' => 10, 'unit' => 'kg'],
                ],
            ],

            // 2 ─ Alan Cooper (Member, Cuci Setrika, 4Kg + addons)
            [
                'nota'          => '31122023',
                'user'          => 'Anton Sten',     // customerId 31122023 = Anton Sten
                'customer_name' => 'Alan Cooper',
                'customer_phone'=> null,
                'customer_type' => 'member',
                'service'       => 'Cuci Setrika',
                'weight'        => 4,
                'status'        => 'order diterima',
                'order_date'    => '2010-10-06',
                'estimated_date'=> '2010-10-06',
                'timeline'      => ["Order di terima\n6 Okt 10.30", "Sedang Di Pilah\n6 Okt 12.30", "Sedang Di cuci\nSedang Berjalan", null],
                'items'         => [
                    ['service' => 'Cuci Setrika', 'name' => 'Kiloan',  'qty' => 4,  'unit' => 'kg'],
                    ['service' => 'Selimut',      'name' => 'Selimut', 'qty' => 1,  'unit' => 'pcs'],
                    ['service' => 'Bedcover',     'name' => 'Bedcover','qty' => 1,  'unit' => 'pcs'],
                    ['service' => 'Pelembut',     'name' => 'Pelembut','qty' => 1,  'unit' => 'pcs'],
                    ['service' => 'Sabun',        'name' => 'Sabun',   'qty' => 1,  'unit' => 'pcs'],
                ],
            ],

            // 3 ─ Steve Krug (Non-Member, Cuci Kering, 1Kg)
            [
                'nota'          => '01072006',
                'user'          => null,
                'customer_name' => 'Steve Krug',
                'customer_phone'=> null,
                'customer_type' => 'non-member',
                'service'       => 'Cuci Kering',
                'weight'        => 1,
                'status'        => 'Selesai',
                'order_date'    => '2012-06-07',
                'estimated_date'=> '2012-06-08',
                'timeline'      => ["Order di terima\n7 Jun 09.00", "Sedang Di Pilah\n7 Jun 10.00", "Sedang Di cuci\n7 Jun 12.00", "Siap Di ambil\n8 Jun 08.00"],
                'items'         => [
                    ['service' => 'Cuci Kering', 'name' => 'Kiloan', 'qty' => 1, 'unit' => 'kg'],
                ],
            ],

            // 4 ─ Jeff Gothelf (Non-Member, Cuci Setrika, 3Kg) — Dibatalkan
            [
                'nota'          => '15081945',
                'user'          => null,
                'customer_name' => 'Jeff Gothelf',
                'customer_phone'=> null,
                'customer_type' => 'non-member',
                'service'       => 'Cuci Setrika',
                'weight'        => 3,
                'status'        => 'Dibatalkan',
                'order_date'    => '2015-10-01',
                'estimated_date'=> '2015-10-02',
                'timeline'      => ["Order di terima\n1 Okt 14.00", null, null, null],
                'items'         => [
                    ['service' => 'Cuci Setrika', 'name' => 'Kiloan', 'qty' => 3, 'unit' => 'kg'],
                ],
            ],

            // 5 ─ Jared Spool (Member, Cuci Kering, 9Kg)
            [
                'nota'          => '24682468',
                'user'          => 'Olivia Xu',      // customerId 24682468
                'customer_name' => 'Jared Spool',
                'customer_phone'=> null,
                'customer_type' => 'member',
                'service'       => 'Cuci Kering',
                'weight'        => 9,
                'status'        => 'Sedang Dicuci',
                'order_date'    => '2020-11-12',
                'estimated_date'=> '2020-11-13',
                'timeline'      => ["Order di terima\n12 Nov 08.00", "Sedang Di Pilah\n12 Nov 09.30", "Sedang Di cuci\nSedang Berjalan", null],
                'items'         => [
                    ['service' => 'Cuci Kering', 'name' => 'Kiloan', 'qty' => 9, 'unit' => 'kg'],
                ],
            ],

            // 6 ─ Khoi Vinh (Non-Member, Cuci Kering, 5Kg) — Dibatalkan
            [
                'nota'          => '13571357',
                'user'          => null,
                'customer_name' => 'Khoi Vinh',
                'customer_phone'=> null,
                'customer_type' => 'non-member',
                'service'       => 'Cuci Kering',
                'weight'        => 5,
                'status'        => 'Dibatalkan',
                'order_date'    => '2021-10-05',
                'estimated_date'=> '2021-10-06',
                'timeline'      => ["Order di terima\n5 Okt 13.00", null, null, null],
                'items'         => [
                    ['service' => 'Cuci Kering', 'name' => 'Kiloan', 'qty' => 5, 'unit' => 'kg'],
                ],
            ],

            // 7 ─ Brad Frost (Member, Cuci Setrika, 7Kg)
            [
                'nota'          => '12344321',
                'user'          => 'Ridd',            // customerId 12344321
                'customer_name' => 'Brad Frost',
                'customer_phone'=> null,
                'customer_type' => 'member',
                'service'       => 'Cuci Setrika',
                'weight'        => 7,
                'status'        => 'Selesai',
                'order_date'    => '2022-06-08',
                'estimated_date'=> '2022-06-09',
                'timeline'      => ["Order di terima\n8 Jun 10.00", "Sedang Di Pilah\n8 Jun 11.00", "Sedang Di cuci\n8 Jun 14.00", "Siap Di ambil\n9 Jun 08.00"],
                'items'         => [
                    ['service' => 'Cuci Setrika', 'name' => 'Kiloan', 'qty' => 7, 'unit' => 'kg'],
                ],
            ],

            // 8 ─ Tim Brown (Non-Member, Cuci Kering, 5Kg) — Dibatalkan
            [
                'nota'          => '12122021',
                'user'          => null,
                'customer_name' => 'Tim Brown',
                'customer_phone'=> null,
                'customer_type' => 'non-member',
                'service'       => 'Cuci Kering',
                'weight'        => 5,
                'status'        => 'Dibatalkan',
                'order_date'    => '2005-04-27',
                'estimated_date'=> '2005-04-28',
                'timeline'      => ["Order di terima\n27 Apr 09.00", null, null, null],
                'items'         => [
                    ['service' => 'Cuci Kering', 'name' => 'Kiloan', 'qty' => 5, 'unit' => 'kg'],
                ],
            ],

            // 9 ─ Julie Zhuo (Member, Cuci Kering, 4Kg)
            [
                'nota'          => '99999999',
                'user'          => 'Lapa Ninja',      // customerId 31415926 ≈ Lapa Ninja
                'customer_name' => 'Julie Zhuo',
                'customer_phone'=> null,
                'customer_type' => 'member',
                'service'       => 'Cuci Kering',
                'weight'        => 4,
                'status'        => 'Selesai',
                'order_date'    => '2005-02-14',
                'estimated_date'=> '2005-02-15',
                'timeline'      => ["Order di terima\n14 Feb 08.00", "Sedang Di Pilah\n14 Feb 09.00", "Sedang Di cuci\n14 Feb 11.00", "Siap Di ambil\n15 Feb 08.00"],
                'items'         => [
                    ['service' => 'Cuci Kering', 'name' => 'Kiloan', 'qty' => 4, 'unit' => 'kg'],
                ],
            ],

            // 10 ─ Jonathan Ive (Member, Cuci Setrika, 10Kg) — Siap Diambil
            [
                'nota'          => '10101010',
                'user'          => 'Brittany Chiang', // customerId 12122021
                'customer_name' => 'Jonathan Ive',
                'customer_phone'=> null,
                'customer_type' => 'member',
                'service'       => 'Cuci Setrika',
                'weight'        => 10,
                'status'        => 'Siap Diambil',
                'order_date'    => '2005-04-23',
                'estimated_date'=> '2005-04-24',
                'timeline'      => ["Order di terima\n23 Apr 08.00", "Sedang Di Pilah\n23 Apr 09.00", "Sedang Di cuci\n23 Apr 13.00", "Siap Di ambil\n24 Apr 08.00"],
                'items'         => [
                    ['service' => 'Cuci Setrika', 'name' => 'Kiloan', 'qty' => 10, 'unit' => 'kg'],
                ],
            ],
        ];

        foreach ($orders as $o) {
            // Hitung total harga dari items
            $total = 0;
            foreach ($o['items'] as $item) {
                $svc = $services->get($item['service']);
                if ($svc) {
                    $total += $item['qty'] * $svc->price;
                }
            }

            // Buat order
            $user  = $o['user'] ? $findUser($o['user']) : null;
            $order = Order::create([
                'nota'           => $o['nota'],
                'user_id'        => $user?->id,
                'customer_name'  => $o['customer_name'],
                'customer_phone' => $o['customer_phone'],
                'customer_type'  => $o['customer_type'],
                'service_id'     => $services->get($o['service'])->id,
                'weight'         => $o['weight'],
                'total_price'    => $total,
                'status'         => $o['status'],
                'timeline'       => json_encode($o['timeline']),
                'order_date'     => $o['order_date'],
                'estimated_date' => $o['estimated_date'],
            ]);

            // Buat order items
            foreach ($o['items'] as $item) {
                $svc = $services->get($item['service']);
                if (!$svc) continue;

                $subtotal = $item['qty'] * $svc->price;

                OrderItem::create([
                    'order_id'   => $order->id,
                    'service_id' => $svc->id,
                    'item_name'  => $item['name'],
                    'quantity'   => $item['qty'],
                    'unit'       => $item['unit'],
                    'unit_price' => $svc->price,
                    'subtotal'   => $subtotal,
                ]);
            }
        }
    }
}
