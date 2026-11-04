<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdentityTracerTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffUser;
    protected User $superAdminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed spaces
        DB::table('spaces')->insert([
            ['id' => 1, 'code' => 'EMK-DHK', 'name' => 'EMK Center Dhaka', 'category' => 'Center', 'division' => 'Dhaka', 'district' => 'Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'CR-SYL', 'name' => 'American Corner Sylhet', 'category' => 'Corner', 'division' => 'Sylhet', 'district' => 'Sylhet', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Seed workspaces
        DB::table('workspaces')->insert([
            ['id' => 'ws-emk-dhaka', 'space_id' => 1, 'name' => 'EMK Center Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'ws-corner-sylhet', 'space_id' => 2, 'name' => 'American Corner Sylhet', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. Seed staff user in ws-emk-dhaka with workspace_manager role
        $this->staffUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Manager',
            'email' => 'manager.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staffUser->assignRole('workspace_manager');

        // 4. Seed superadmin user
        $this->superAdminUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Super Administrator',
            'email' => 'superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdminUser->assignRole('superadmin');

        // 5. Seed leads in both workspaces
        Lead::withoutGlobalScopes()->create([
            'uuid' => (string) \Ramsey\Uuid\Uuid::uuid4(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDDHK001',
            'first_name' => 'Rahim',
            'last_name' => 'Uddin',
            'email' => 'rahim@example.com',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '2000-01-01',
            'age_at_submission' => 26,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'HSC',
            'area_of_subject' => 'Business',
            'submitted_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'uuid' => (string) \Ramsey\Uuid\Uuid::uuid4(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDDHK002',
            'first_name' => 'Karim',
            'last_name' => 'Hasan',
            'email' => 'karim@example.com',
            'contact_number' => '+8801700000002',
            'date_of_birth' => '1998-05-10',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '45',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Professional',
            'institution_study' => 'NSU',
            'highest_certification' => 'BSc',
            'area_of_subject' => 'CSE',
            'submitted_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'uuid' => (string) \Ramsey\Uuid\Uuid::uuid4(),
            'workspace_id' => 'ws-corner-sylhet',
            'target_space_code' => 'CR-SYL',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDSYL001',
            'first_name' => 'Fatima',
            'last_name' => 'Begum',
            'email' => 'fatima@example.com',
            'contact_number' => '+8801700000003',
            'date_of_birth' => '2001-09-15',
            'age_at_submission' => 25,
            'sex' => 'female',
            'address_house_no' => '78',
            'address_area' => 'Zindabazar',
            'address_zip_code' => '3100',
            'address_division' => 'Sylhet',
            'address_district' => 'Sylhet',
            'occupation' => 'Student',
            'institution_study' => 'SUST',
            'highest_certification' => 'HSC',
            'area_of_subject' => 'Physics',
            'submitted_at' => now(),
        ]);
    }

    public function test_valid_staff_in_workspace_receives_200_and_tenant_scoped_count(): void
    {
        $response = $this->actingAs($this->staffUser, 'web')
            ->getJson('/crm/ws-emk-dhaka/tracer-check');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'workspace_id' => 'ws-emk-dhaka',
                'records_count' => 2,
            ]);
    }

    public function test_staff_attempting_to_access_mismatched_workspace_receives_403_forbidden(): void
    {
        $response = $this->actingAs($this->staffUser, 'web')
            ->getJson('/crm/ws-corner-sylhet/tracer-check');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_receives_401_or_redirects_to_login(): void
    {
        $jsonResponse = $this->getJson('/crm/ws-emk-dhaka/tracer-check');
        $jsonResponse->assertStatus(401);

        $htmlResponse = $this->get('/crm/ws-emk-dhaka/tracer-check');
        $this->assertTrue(
            $htmlResponse->status() === 401 || $htmlResponse->isRedirect(route('login'))
        );
    }

    public function test_superadmin_user_can_access_any_workspace_route_with_unscoped_visibility(): void
    {
        $responseDhaka = $this->actingAs($this->superAdminUser, 'web')
            ->getJson('/crm/ws-emk-dhaka/tracer-check');

        $responseDhaka->assertOk()
            ->assertJson([
                'status' => 'ok',
                'workspace_id' => 'ws-emk-dhaka',
                'records_count' => 3,
            ]);

        $responseSylhet = $this->actingAs($this->superAdminUser, 'web')
            ->getJson('/crm/ws-corner-sylhet/tracer-check');

        $responseSylhet->assertOk()
            ->assertJson([
                'status' => 'ok',
                'workspace_id' => 'ws-corner-sylhet',
                'records_count' => 3,
            ]);
    }
}
