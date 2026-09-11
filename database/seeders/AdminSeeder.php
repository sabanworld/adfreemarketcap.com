<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->updateOrCreate(
            ['email' => 'admin@adfreemarketcap.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
            ],
        );
    }
}
