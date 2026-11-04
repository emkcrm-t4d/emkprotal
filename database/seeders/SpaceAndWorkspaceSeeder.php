<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpaceAndWorkspaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $spaces = [
            [
                'code' => 'EMK-DHK',
                'name' => 'EMK Center (Dhaka)',
                'category' => 'Center',
                'division' => 'Dhaka',
                'district' => 'Dhaka',
                'is_active' => true,
            ],
            [
                'code' => 'AC-DHK',
                'name' => 'The American Center (Dhaka)',
                'category' => 'Center',
                'division' => 'Dhaka',
                'district' => 'Dhaka',
                'is_active' => true,
            ],
            [
                'code' => 'CR-BAR',
                'name' => 'American Corner Barishal',
                'category' => 'Corner',
                'division' => 'Barishal',
                'district' => 'Barishal',
                'is_active' => true,
            ],
            [
                'code' => 'CR-CTG',
                'name' => 'American Corner Chattogram',
                'category' => 'Corner',
                'division' => 'Chattogram',
                'district' => 'Chattogram',
                'is_active' => true,
            ],
            [
                'code' => 'CR-RAJ',
                'name' => 'American Corner Rajshahi',
                'category' => 'Corner',
                'division' => 'Rajshahi',
                'district' => 'Rajshahi',
                'is_active' => true,
            ],
            [
                'code' => 'CR-SYL',
                'name' => 'American Corner Sylhet',
                'category' => 'Corner',
                'division' => 'Sylhet',
                'district' => 'Sylhet',
                'is_active' => true,
            ],
        ];

        foreach ($spaces as $space) {
            DB::table('spaces')->updateOrInsert(
                ['code' => $space['code']],
                array_merge($space, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }

        $emkSpace = DB::table('spaces')->where('code', 'EMK-DHK')->first();

        if ($emkSpace) {
            DB::table('workspaces')->updateOrInsert(
                ['id' => 'ws-emk-dhaka'],
                [
                    'space_id' => $emkSpace->id,
                    'name' => 'EMK Center Dhaka',
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
