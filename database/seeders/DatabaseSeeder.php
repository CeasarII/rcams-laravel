<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data the workflow needs (roles, request types, statuses).
        $this->call(ProcessASeeder::class);
        $this->call(LiquidationSeeder::class);
        $this->call(RevolvingFundSeeder::class);

        // Sample companies/vessels and one login per role — local/testing only.
        if (! app()->environment('production')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
