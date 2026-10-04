<?php

namespace Database\Seeders;

use App\Demo\DemoUser;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        DemoUser::ensure();
    }
}
