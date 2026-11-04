<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MembershipTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $terms = [
            [
                'workspace_id' => 'ws-emk-dhaka',
                'code' => 'std-1yr',
                'name' => 'Standard 1-Year Membership',
                'duration_months' => 12,
                'fee_bdt' => 500.00,
                'is_active' => true,
            ],
            [
                'workspace_id' => 'ws-emk-dhaka',
                'code' => 'std-2yr',
                'name' => 'Standard 2-Year Membership',
                'duration_months' => 24,
                'fee_bdt' => 1000.00,
                'is_active' => true,
            ],
        ];

        foreach ($terms as $term) {
            DB::table('membership_terms')->updateOrInsert(
                [
                    'workspace_id' => $term['workspace_id'],
                    'code' => $term['code'],
                ],
                array_merge($term, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}
