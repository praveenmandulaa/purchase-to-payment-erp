<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::updateOrCreate(
            ['code' => 'WH-HYD-01'],
            [
                'name' => 'Hyderabad Main Warehouse',
                'location' => 'Hyderabad',
                'is_active' => true,
            ]
        );

        // Use the same document_sequences schema as Phase 3.
        DB::table('document_sequences')->insertOrIgnore([
            'document_type' => 'GRN',
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}