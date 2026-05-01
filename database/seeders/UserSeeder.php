<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── ADMIN ──────────────────────────────────────────────────────────────
        User::create([
            'name'     => 'admin',
            'phone'    => '081000000000',
            'address'  => 'Jl. Kutai Utara, Sumber, Banjarsari, Solo',
            'password' => Hash::make('admin123'),
            'role'     => 'admin',
        ]);

        // ── CUSTOMER — sesuai CustomerData.jsx dummy data ──────────────────────
        // customer_id di frontend disimpan sebagai name (username login)
        $customers = [
            [
                'name'    => 'daniel_spatzek',   // username login
                'display' => 'Daniel Spatzek',
                'phone'   => '081234567890',
                'address' => 'Jalan Kutai Utara No. 1, Kelurahan Sumber, Kecamatan Banjarsari, Kota Surakarta (Solo), Jawa Tengah 57138',
                'pass'    => 'pass1234',
            ],
            [
                'name'    => 'haley_takeda',
                'display' => 'Haley Takeda',
                'phone'   => '082345678901',
                'address' => 'Jalan Veteran No. 12-14, Kelurahan Ketawanggede, Kecamatan Lowokwaru, Kota Malang, Jawa Timur 65145',
                'pass'    => 'haley999',
            ],
            [
                'name'    => 'ridd',
                'display' => 'Ridd',
                'phone'   => '083456789012',
                'address' => 'Jalan Kertanegara No. 4, Kelurahan Selong, Kecamatan Kebayoran Baru, Jakarta Selatan, DKI Jakarta',
                'pass'    => 'ridd2023',
            ],
            [
                'name'    => 'olivia_xu',
                'display' => 'Olivia Xu',
                'phone'   => '084567890123',
                'address' => 'Desa Bojong Koneng, Kecamatan Babakan Madang, Kabupaten Bogor, Jawa Barat',
                'pass'    => 'olivia88',
            ],
            [
                'name'    => 'anton_sten',
                'display' => 'Anton Sten',
                'phone'   => '085678901234',
                'address' => 'Jalan Puncak Dieng, Kunci, Kalisongo, Kecamatan Dau, Kabupaten Malang, Jawa Timur 65151',
                'pass'    => 'anton007',
            ],
            [
                'name'    => 'mizko',
                'display' => 'Mizko',
                'phone'   => '086789012345',
                'address' => '548 Market St PMB 60761, San Francisco, CA 94104, Amerika Serikat',
                'pass'    => 'mizko321',
            ],
            [
                'name'    => 'steve_schoger',
                'display' => 'Steve Schoger',
                'phone'   => '087890123456',
                'address' => '455 Bryant St, San Francisco, CA 94107, Amerika Serikat',
                'pass'    => 'steve456',
            ],
            [
                'name'    => 'adam_wathan',
                'display' => 'Adam Wathan',
                'phone'   => '088901234567',
                'address' => '5000 Forbes Ave, Pittsburgh, PA 15213, Amerika Serikat',
                'pass'    => 'adam2024',
            ],
            [
                'name'    => 'lapa_ninja',
                'display' => 'Lapa Ninja',
                'phone'   => '089012345678',
                'address' => '1600 Amphitheatre Parkway, Mountain View, CA 94043, Amerika Serikat',
                'pass'    => 'lapa9999',
            ],
            [
                'name'    => 'brittany_chiang',
                'display' => 'Brittany Chiang',
                'phone'   => '081122334455',
                'address' => 'One Microsoft Way, Redmond, WA 98052, Amerika Serikat',
                'pass'    => 'brit2021',
            ],
            [
                'name'    => 'chris_davidson',
                'display' => 'Chris Davidson',
                'phone'   => '081233445566',
                'address' => 'Jalan Sudirman No. 5, Jakarta Pusat, DKI Jakarta 10220',
                'pass'    => 'chris123',
            ],
            [
                'name'    => 'nisa_pratiwi',
                'display' => 'Nisa Pratiwi',
                'phone'   => '082233445567',
                'address' => 'Jalan Ahmad Yani No. 15, Kota Malang, Jawa Timur 65115',
                'pass'    => 'nisa2022',
            ],
        ];

        foreach ($customers as $c) {
            User::create([
                'name'     => $c['display'], // tampilan nama
                'phone'    => $c['phone'],
                'address'  => $c['address'],
                'password' => Hash::make($c['pass']),
                'role'     => 'customer',
            ]);
        }
    }
}
