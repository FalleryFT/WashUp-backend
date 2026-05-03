<?php
// database/seeders/SettingSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'max_berat_per_nota', 'value' => '7'],
            ['key' => 'app_name',           'value' => 'WashUp Laundry'],
            ['key' => 'app_address',        'value' => 'Jl. Kutai Utara, Sumber, Banjarsari, Solo'],
            ['key' => 'app_phone',          'value' => '+62801749020'],
            ['key' => 'app_email',          'value' => 'washuplaundry@gmail.com'],
            ['key' => 'app_hours',          'value' => '08.00 - 20.00'],
        ];

        foreach ($defaults as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], ['value' => $setting['value']]);
        }
    }
}
