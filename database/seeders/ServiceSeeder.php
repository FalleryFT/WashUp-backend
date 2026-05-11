<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // ── KILOAN (harga per Kg) — dari initKiloan Price.jsx ─────────────────
        $kiloan = [
            ['name' => 'Cuci Setrika', 'price' => 7000],
            ['name' => 'Cuci Kering',  'price' => 5000],
            ['name' => 'Setrika Saja', 'price' => 4000],
        ];

        foreach ($kiloan as $item) {
            Service::create([
                'name'      => $item['name'],
                'price'     => $item['price'],
                'type'      => 'kiloan',
                'is_active' => true,
            ]);
        }

        // ── ADD-ON (harga satuan per item) — dari initAddon Price.jsx ─────────
        $addon = [
            ['name' => 'Selimut',  'price' => 10000],
            ['name' => 'Bedcover', 'price' => 15000],
            ['name' => 'Pelembut', 'price' => 11000],
            ['name' => 'Sabun',    'price' =>  1000],
        ];

        foreach ($addon as $item) {
            Service::create([
                'name'      => $item['name'],
                'price'     => $item['price'],
                'type'      => 'addon',
                'is_active' => true,
            ]);
        }
    }
}
