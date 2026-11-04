<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TestTenantHydrationComponent extends Component
{
    use ValidatesWorkspaceOnHydration;

    public ?Lead $lead = null;

    public function mount(?Lead $lead = null): void
    {
        $this->lead = $lead;
    }

    public function triggerAction(): void
    {
        // No-op action to trigger Livewire hydration cycle
    }

    public function render()
    {
        return '<div>Lead: ' . ($this->lead ? $this->lead->first_name : 'none') . '</div>';
    }
}

class TenancyDefenseLayersTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaStaff;
    protected User $sylhetStaff;
    protected User $unassignedStaff;
    protected User $superAdmin;
    protected Lead $dhakaLead;
    protected Lead $sylhetLead;

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
        $this->dhakaStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Staff',
            'email' => 'dhaka.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaStaff->assignRole('workspace_manager');

        $this->sylhetStaff = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Staff',
            'email' => 'sylhet.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->sylhetStaff->assignRole('workspace_manager');

        $this->unassignedStaff = User::create([
            'workspace_id' => null,
            'name' => 'Unassigned Staff',
            'email' => 'unassigned.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->unassignedStaff->assignRole('workspace_manager');

        $this->superAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Super Administrator',
            'email' => 'superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        // 4. Seed leads in both workspaces (using raw insert or unscoped to establish baseline)
        $this->dhakaLead = Lead::withoutGlobalScopes()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDDHK100',
            'first_name' => 'Dhaka',
            'last_name' => 'Applicant',
            'email' => 'dhaka.applicant@example.com',
            'contact_number' => '+8801711111111',
            'date_of_birth' => '1999-01-01',
            'age_at_submission' => 27,
            'sex' => 'male',
            'address_house_no' => '10',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'DU',
            'highest_certification' => 'BSc',
            'area_of_subject' => 'Science',
            'submitted_at' => now(),
        ]);

        $this->sylhetLead = Lead::withoutGlobalScopes()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'workspace_id' => 'ws-corner-sylhet',
            'target_space_code' => 'CR-SYL',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDSYL100',
            'first_name' => 'Sylhet',
            'last_name' => 'Applicant',
            'email' => 'sylhet.applicant@example.com',
            'contact_number' => '+8801722222222',
            'date_of_birth' => '2000-02-02',
            'age_at_submission' => 26,
            'sex' => 'female',
            'address_house_no' => '20',
            'address_area' => 'Zindabazar',
            'address_zip_code' => '3100',
            'address_division' => 'Sylhet',
            'address_district' => 'Sylhet',
            'occupation' => 'Student',
            'institution_study' => 'SUST',
            'highest_certification' => 'HSC',
            'area_of_subject' => 'Arts',
            'submitted_at' => now(),
        ]);
    }

    public function test_eloquent_queries_automatically_filter_by_authenticated_user_workspace(): void
    {
        $this->actingAs($this->dhakaStaff, 'web');

        $leads = Lead::all();
        $this->assertCount(1, $leads);
        $this->assertSame($this->dhakaLead->id, $leads->first()->id);

        $this->assertNull(Lead::find($this->sylhetLead->id));
        $this->assertNull(Lead::where('id', $this->sylhetLead->id)->first());

        $this->actingAs($this->sylhetStaff, 'web');

        $sylhetLeads = Lead::all();
        $this->assertCount(1, $sylhetLeads);
        $this->assertSame($this->sylhetLead->id, $sylhetLeads->first()->id);

        $this->assertNull(Lead::find($this->dhakaLead->id));
    }

    public function test_unassigned_user_queries_fail_closed_with_zero_results(): void
    {
        $this->actingAs($this->unassignedStaff, 'web');

        $leads = Lead::all();
        $this->assertCount(0, $leads);
        $this->assertNull(Lead::find($this->dhakaLead->id));
        $this->assertNull(Lead::find($this->sylhetLead->id));
    }

    public function test_superadmin_queries_bypass_tenant_filter(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $this->assertCount(2, Lead::all());
        $this->assertNotNull(Lead::find($this->dhakaLead->id));
        $this->assertNotNull(Lead::find($this->sylhetLead->id));
    }

    public function test_lead_creation_automatically_sets_workspace_id_from_authenticated_user(): void
    {
        $this->actingAs($this->dhakaStaff, 'web');

        $newLead = Lead::create([
            'uuid' => Uuid::uuid4()->toString(),
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDNEW001',
            'first_name' => 'Auto',
            'last_name' => 'Workspace',
            'email' => 'auto.workspace@example.com',
            'contact_number' => '+8801733333333',
            'date_of_birth' => '1995-05-05',
            'age_at_submission' => 31,
            'sex' => 'male',
            'address_house_no' => '50',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Engineer',
            'institution_study' => 'BUET',
            'highest_certification' => 'BSc',
            'area_of_subject' => 'Engineering',
            'submitted_at' => now(),
        ]);

        $this->assertSame('ws-emk-dhaka', $newLead->workspace_id);
        $this->assertDatabaseHas('leads', [
            'id' => $newLead->id,
            'workspace_id' => 'ws-emk-dhaka',
        ]);
    }

    public function test_lead_attachment_implements_tenant_entity_and_is_scoped(): void
    {
        $attachment = LeadAttachment::withoutGlobalScopes()->create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->dhakaLead->id,
            'document_type' => 'identity_document',
            'storage_path' => 'leads/test/nid.pdf',
            'original_filename' => 'nid.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'file_hash' => hash('sha256', 'dummy'),
            'youth_protection' => false,
        ]);

        $this->assertInstanceOf(TenantEntityInterface::class, $attachment);
        $this->assertSame('ws-emk-dhaka', $attachment->getWorkspaceId());

        $this->actingAs($this->dhakaStaff, 'web');
        $this->assertNotNull(LeadAttachment::find($attachment->id));

        $this->actingAs($this->sylhetStaff, 'web');
        $this->assertNull(LeadAttachment::find($attachment->id));
    }

    public function test_livewire_component_hydration_allows_owned_workspace_model(): void
    {
        $this->actingAs($this->dhakaStaff, 'web');

        Livewire::test(TestTenantHydrationComponent::class, ['lead' => $this->dhakaLead])
            ->call('triggerAction')
            ->assertStatus(200)
            ->assertSee('Dhaka');
    }

    public function test_livewire_component_hydration_triggers_403_on_cross_workspace_model(): void
    {
        $this->actingAs($this->dhakaStaff, 'web');

        // Direct trait invocation assertion
        $component = new TestTenantHydrationComponent();
        $component->lead = $this->sylhetLead;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unauthorized workspace entity.');

        $component->hydrateValidatesWorkspaceOnHydration();
    }

    public function test_livewire_component_hydration_lifecycle_aborts_403_on_tampered_lead(): void
    {
        $this->actingAs($this->dhakaStaff, 'web');

        // When a Livewire action runs against a foreign lead property
        Livewire::test(TestTenantHydrationComponent::class, ['lead' => $this->sylhetLead])
            ->call('triggerAction')
            ->assertStatus(403);
    }

    public function test_livewire_component_hydration_bypasses_for_superadmin(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        // Superadmin can hydrate foreign leads across any workspace
        Livewire::test(TestTenantHydrationComponent::class, ['lead' => $this->sylhetLead])
            ->call('triggerAction')
            ->assertStatus(200)
            ->assertSee('Sylhet');
    }
}
