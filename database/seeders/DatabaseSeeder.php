<?php

namespace Database\Seeders;

use App\Models\DowntimeCategory;
use App\Models\Machine;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('yahata.machines') as $name) {
            Machine::firstOrCreate(['name' => $name]);
        }

        foreach (config('yahata.categories') as $category) {
            DowntimeCategory::updateOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name'], 'is_downtime' => $category['is_downtime']]
            );
        }
    }
}