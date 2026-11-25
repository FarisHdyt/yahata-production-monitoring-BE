<?php

namespace Database\Seeders;

use App\Models\DowntimeCategory;
use Illuminate\Database\Seeder;

/** 13 kategori downtime (sumber: config/yahata.php). Kode 1 = Running Production (bukan downtime). */
class DowntimeCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('yahata.categories') as $category) {
            DowntimeCategory::updateOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name'], 'is_downtime' => $category['is_downtime']]
            );
        }
    }
}