<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaManager;
    protected User $sylhetManager;
    protected User $superAdmin;
    protected Lead $dhakaLead1;
    protected Lead $dhakaLead2;
    protected Lead $sylhetLead1;
    protected Lead $sylhetLead2;

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

        // 3. Seed users
        $this->dhakaManager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Manager',
            'email' => 'manager.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaManager->assignRole('workspace_manager');

        $this->sylhetManager = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Manager',
            'email' => 'manager.sylhet@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->sylhetManager->assignRole('workspace_manager');

        $this->superAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Super Administrator',
            'email' => 'superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        // 4. Seed leads in ws-emk-dhaka
        $this->dhakaLead1 = Lead::withoutGlobalScopes()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDDHK201',
            'first_name' => 'Tanvir',
            'last_name' => 'Ahmed',
            'email' => 'tanvir.ahmed@example.com',
            'contact_number' => '+8801710000001',
            'date_of_birth' => '1998-03-15',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '12/A',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'BSc',
            'area_of_subject' => 'Physics',
            'submitted_at' => now(),
        ]);

        $this->dhakaLead2 = Lead::withoutGlobalScopes()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDDHK202',
            'first_name' => 'Nusrat',
            'last_name' => 'Jahan',
            'email' => 'nusrat.jahan@example.com',
            'contact_number' => '+8801710000002',
            'date_of_birth' => '1999-07-20',
            'age_at_submission' => 27,
            'sex' => 'female',
            'address_house_no' => '34',
            'address_area' => 'Mirpur',
            'address_zip_code' => '1216',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Professional',
            'institution_study' => 'IBA DU',
            'highest_certification' => 'MBA',
            'area_of_subject' => 'Marketing',
            'submitted_at' => now(),
        ]);

        // 5. Seed leads in ws-corner-sylhet
        $this->sylhetLead1 = Lead::withoutGlobalScopes()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'workspace_id' => 'ws-corner-sylhet',
            'target_space_code' => 'CR-SYL',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDSYL201',
            'first_name' => 'Farhan',
            'last_name' => 'Chowdhury',
            'email' => 'farhan.chowdhury@example.com',
            'contact_number' => '+8801720000001',
            'date_of_birth' => '2001-11-10',
            'age_at_submission' => 25,
            'sex' => 'male',
            'address_house_no' => '56',
            'address_area' => 'Zindabazar',
            'address_zip_code' => '3100',
            'address_division' => 'Sylhet',
            'address_district' => 'Sylhet',
            'occupation' => 'Student',
            'institution_study' => 'SUST',
            'highest_certification' => 'HSC',
            'area_of_subject' => 'Computer Science',
            'submitted_at' => now(),
        ]);

        $this->sylhetLead2 = Lead::withoutGlobalScopes()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'workspace_id' => 'ws-corner-sylhet',
            'target_space_code' => 'CR-SYL',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDSYL202',
            'first_name' => 'Sumaiya',
            'last_name' => 'Khatun',
            'email' => 'sumaiya.khatun@example.com',
            'contact_number' => '+8801720000002',
            'date_of_birth' => '2002-04-12',
            'age_at_submission' => 24,
            'sex' => 'female',
            'address_house_no' => '89',
            'address_area' => 'Amberkhana',
            'address_zip_code' => '3100',
            'address_division' => 'Sylhet',
            'address_district' => 'Sylhet',
            'occupation' => 'Student',
            'institution_study' => 'Sylhet College',
            'highest_certification' => 'HSC',
            'area_of_subject' => 'Humanities',
            'submitted_at' => now(),
        ]);
    }

    public function test_workspace_manager_can_only_count_and_find_leads_in_own_workspace(): void
    {
        $this->actingAs($this->dhakaManager, 'web');

        // Only Dhaka leads visible (2 leads)
        $this->assertSame(2, Lead::count());
        $this->assertNotNull(Lead::find($this->dhakaLead1->id));
        $this->assertNotNull(Lead::find($this->dhakaLead2->id));

        // Sylhet leads are completely invisible
        $this->assertNull(Lead::find($this->sylhetLead1->id));
        $this->assertNull(Lead::find($this->sylhetLead2->id));
    }

    public function test_cross_workspace_eloquent_update_affects_zero_rows(): void
    {
        $this->actingAs($this->dhakaManager, 'web');

        // Attempting to update a Sylhet lead ID returns 0 affected rows
        $affectedRows = Lead::where('id', $this->sylhetLead1->id)
            ->update(['first_name' => 'TamperedName']);

        $this->assertSame(0, $affectedRows);

        // Verify the database record remains completely intact
        $unchangedSylhetLead = DB::table('leads')->where('id', $this->sylhetLead1->id)->first();
        $this->assertSame('Farhan', $unchangedSylhetLead->first_name);
    }

    public function test_cross_workspace_eloquent_delete_affects_zero_rows(): void
    {
        $this->actingAs($this->dhakaManager, 'web');

        // Attempting to delete a Sylhet lead ID returns 0 affected rows
        $affectedRows = Lead::where('id', $this->sylhetLead2->id)->delete();

        $this->assertSame(0, $affectedRows);

        // Verify the database record is not soft-deleted
        $unchangedSylhetLead = DB::table('leads')->where('id', $this->sylhetLead2->id)->first();
        $this->assertNull($unchangedSylhetLead->deleted_at);
    }

    public function test_superadmin_has_unrestricted_cross_workspace_visibility_and_management(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        // Superadmin sees all 4 leads across all workspaces
        $this->assertSame(4, Lead::count());

        // Resolves both Dhaka and Sylhet leads
        $resolvedDhaka = Lead::find($this->dhakaLead1->id);
        $resolvedSylhet = Lead::find($this->sylhetLead1->id);

        $this->assertNotNull($resolvedDhaka);
        $this->assertNotNull($resolvedSylhet);
        $this->assertSame('Tanvir', $resolvedDhaka->first_name);
        $this->assertSame('Farhan', $resolvedSylhet->first_name);

        // Superadmin update succeeds across workspace boundaries
        $affectedRows = Lead::where('id', $this->sylhetLead1->id)
            ->update(['middle_name' => 'Hossain']);

        $this->assertSame(1, $affectedRows);
    }

    public function test_workspace_isolation_is_symmetric_for_sylhet_manager(): void
    {
        $this->actingAs($this->sylhetManager, 'web');

        // Only Sylhet leads visible (2 leads)
        $this->assertSame(2, Lead::count());
        $this->assertNotNull(Lead::find($this->sylhetLead1->id));
        $this->assertNotNull(Lead::find($this->sylhetLead2->id));

        // Dhaka leads are invisible
        $this->assertNull(Lead::find($this->dhakaLead1->id));
        $this->assertNull(Lead::find($this->dhakaLead2->id));

        // Attempting to update a Dhaka lead returns 0 affected rows
        $affectedRows = Lead::where('id', $this->dhakaLead1->id)
            ->update(['first_name' => 'TamperedBySylhet']);

        $this->assertSame(0, $affectedRows);
    }
}
