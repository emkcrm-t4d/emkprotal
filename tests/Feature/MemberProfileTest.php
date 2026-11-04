<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Members\Models\Member;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Members\EditMemberContactModal;
use App\Livewire\Members\MemberProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected User $otherStaff;
    protected MembershipTerm $term;
    protected Lead $lead;
    protected Member $member;
    protected LeadAttachment $attachmentDoc;
    protected LeadAttachment $attachmentPhoto;
    protected Invoice $invoice;
    protected IdCard $idCard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Ensure other workspace exists for cross-workspace tests
        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'Chattogram American Corner',
                'is_active' => true,
            ]
        );

        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Admissions Officer',
            'email' => 'officer.profile@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        $this->otherStaff = User::create([
            'workspace_id' => 'ws-chattogram',
            'name' => 'Chattogram Officer',
            'email' => 'ctg.profile@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->otherStaff->assignRole('delegated_staff');

        $this->term = MembershipTerm::first();

        // 1. Create fully populated 31-field Lead record
        $this->lead = Lead::create([
            'uuid' => Str::uuid()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXIDPROF01',
            'first_name' => 'Nusrat',
            'middle_name' => 'Jahan',
            'last_name' => 'Chowdhury',
            'email' => 'nusrat.chowdhury@example.com',
            'contact_number' => '+8801755555555',
            'date_of_birth' => '1996-08-20',
            'age_at_submission' => 30,
            'sex' => 'female',
            'address_house_no' => 'Flat 5A, House 12',
            'address_road_no' => 'Road 4',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'educator',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'graduate',
            'area_of_subject' => 'Applied Linguistics',
            'has_us_experience' => true,
            'us_experience_location' => 'Boston, MA',
            'is_american_alumni' => true,
            'american_institution_name' => 'Harvard University',
            'employer_name' => 'EMK Foundation',
            'job_title' => 'Program Associate',
            'work_phone' => '+8801766666666',
            'website' => 'https://nusrat.example.org',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now()->subDays(15),
        ]);

        // 2. Attach identity document and studio photo
        $this->attachmentDoc = LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->lead->id,
            'document_type' => 'identity_document',
            'storage_path' => 'workspaces/ws-emk-dhaka/leads/' . $this->lead->uuid . '/nid.pdf',
            'original_filename' => 'national_id_front.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 204800,
            'file_hash' => hash('sha256', 'mock_nid_content'),
            'youth_protection' => false,
        ]);

        $this->attachmentPhoto = LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->lead->id,
            'document_type' => 'studio_photo',
            'storage_path' => 'workspaces/ws-emk-dhaka/leads/' . $this->lead->uuid . '/photo.jpg',
            'original_filename' => 'passport_studio_photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 153600,
            'file_hash' => hash('sha256', 'mock_photo_content'),
            'youth_protection' => false,
        ]);

        // 3. Create Portal User for Member
        $memberUser = User::create([
            'name' => 'Nusrat Jahan Chowdhury',
            'email' => 'nusrat.chowdhury@example.com',
            'password' => bcrypt('secret123'),
            'workspace_id' => 'ws-emk-dhaka',
            'guard' => 'member',
            'is_active' => true,
        ]);

        // 4. Create Member record
        $this->member = Member::create([
            'uuid' => Str::uuid()->toString(),
            'member_id' => 'EMK-2026-00099',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->lead->id,
            'user_id' => $memberUser->id,
            'membership_term_id' => $this->term->id,
            'status' => 'active',
            'first_name' => 'Nusrat',
            'middle_name' => 'Jahan',
            'last_name' => 'Chowdhury',
            'email' => 'nusrat.chowdhury@example.com',
            'contact_number' => '+8801755555555',
            'date_of_birth' => '1996-08-20',
            'sex' => 'female',
            'address_house_no' => 'Flat 5A, House 12',
            'address_road_no' => 'Road 4',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'educator',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'graduate',
            'area_of_subject' => 'Applied Linguistics',
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        // 5. Create linked Invoice
        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-2026-00099',
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'lead_id' => $this->lead->id,
            'amount_bdt' => 1500.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXIDPROF01',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // 6. Create linked ID Card
        $this->idCard = IdCard::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'card_number' => 'CARD-2026-00099',
            'status' => 'provided_for_printing',
            'batch_reference' => 'BATCH-2026-W1',
        ]);
    }

    public function test_authorized_staff_can_view_member_profile_and_31_fields(): void
    {
        $response = $this->actingAs($this->staff, 'web')
            ->get("/crm/ws-emk-dhaka/members/{$this->member->uuid}");

        $response->assertStatus(200);
        $response->assertSeeLivewire(MemberProfile::class);

        // Header & Core identity
        $response->assertSee('Nusrat Jahan Chowdhury');
        $response->assertSee('EMK-2026-00099');
        $response->assertSee('Active');

        // Personal details
        $response->assertSee('1996-08-20');
        $response->assertSee('Female');

        // Contact & Address
        $response->assertSee('nusrat.chowdhury@example.com');
        $response->assertSee('+8801755555555');
        $response->assertSee('Flat 5A, House 12');
        $response->assertSee('Road 4');
        $response->assertSee('Banani');
        $response->assertSee('1213');
        $response->assertSee('Dhaka');

        // Academic & Professional
        $response->assertSee('Educator');
        $response->assertSee('Dhaka University');
        $response->assertSee('Applied Linguistics');
        $response->assertSee('EMK Foundation');
        $response->assertSee('Program Associate');
        $response->assertSee('Boston, MA');
        $response->assertSee('Harvard University');
        $response->assertSee('TXIDPROF01');
    }

    public function test_verified_document_previews_and_download_links(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(MemberProfile::class, [
                'workspace' => 'ws-emk-dhaka',
                'member' => $this->member,
            ]);

        $component->assertSee('national_id_front.pdf');
        $component->assertSee('passport_studio_photo.jpg');

        $downloadUrl = route('documents.download', [
            'workspace' => 'ws-emk-dhaka',
            'attachment' => $this->attachmentDoc->id,
        ]);

        $component->assertSee($downloadUrl, false);
    }

    public function test_invoices_tab_displays_billing_records(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(MemberProfile::class, [
                'workspace' => 'ws-emk-dhaka',
                'member' => $this->member,
            ]);

        // Switch to invoices tab
        $component->call('setTab', 'invoices');
        $component->assertSet('activeTab', 'invoices');

        $component->assertSee('INV-2026-00099');
        $component->assertSee('1,500.00');
        $component->assertSee('TXIDPROF01');
        $component->assertSee('Paid');
    }

    public function test_activity_timeline_tab_displays_lifecycle_events(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(MemberProfile::class, [
                'workspace' => 'ws-emk-dhaka',
                'member' => $this->member,
            ]);

        // Switch to activity timeline tab
        $component->call('setTab', 'activity_timeline');
        $component->assertSet('activeTab', 'activity_timeline');

        $component->assertSee('Membership Induction and Term Validity Started');
        $component->assertSee('CARD-2026-00099');
        $component->assertSee('BATCH-2026-W1');
    }

    public function test_cross_workspace_member_inspection_returns_403(): void
    {
        // Staff from ws-chattogram attempting to view ws-emk-dhaka member
        $response = $this->actingAs($this->otherStaff, 'web')
            ->get("/crm/ws-emk-dhaka/members/{$this->member->uuid}");

        $response->assertStatus(403);
    }

    public function test_staff_with_members_edit_can_update_contact_and_address_details(): void
    {
        $staffWithEdit = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Editor Staff',
            'email' => 'editor@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $staffWithEdit->assignRole('delegated_staff');
        $staffWithEdit->givePermissionTo('members.edit');

        $component = Livewire::actingAs($staffWithEdit, 'web')
            ->test(EditMemberContactModal::class, [
                'workspace' => 'ws-emk-dhaka',
                'member' => $this->member,
            ]);

        $component->call('openModal');
        $component->assertSet('isOpen', true);

        // Update fields
        $component->set('contact_number', '+8801799999999');
        $component->set('address_house_no', 'Flat 9B, House 99');
        $component->set('address_road_no', 'Road 11');
        $component->set('address_area', 'Gulshan 2');
        $component->set('address_zip_code', '1212');
        $component->set('address_division', 'dhaka');
        $component->set('address_district', 'dhaka');
        $component->set('occupation', 'entrepreneur');
        $component->set('institution_study', 'IBA, Dhaka University');
        $component->set('highest_certification', 'postgraduate');
        $component->set('area_of_subject', 'Finance');

        $component->call('save');
        $component->assertDispatched('member-contact-updated');
        $component->assertSet('isOpen', false);

        // Assert database state updated
        $this->assertDatabaseHas('members', [
            'id' => $this->member->id,
            'contact_number' => '+8801799999999',
            'address_house_no' => 'Flat 9B, House 99',
            'address_road_no' => 'Road 11',
            'address_area' => 'Gulshan 2',
            'occupation' => 'entrepreneur',
        ]);

        // Assert immutable audit log recorded per DEC-11
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'member_contact_updated',
            'auditable_type' => Member::class,
            'auditable_id' => $this->member->id,
            'actor_id' => $staffWithEdit->id,
        ]);
    }

    public function test_staff_without_members_edit_cannot_invoke_modal_or_save(): void
    {
        // $this->staff has delegated_staff without members.edit
        // 1. Invoking openEditContactModal on MemberProfile fails with 403
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(MemberProfile::class, [
                'workspace' => 'ws-emk-dhaka',
                'member' => $this->member,
            ]);

        $component->call('openEditContactModal');
        $component->assertStatus(403);

        // 2. Direct save on EditMemberContactModal fails with 403
        $modalComponent = Livewire::actingAs($this->staff, 'web')
            ->test(EditMemberContactModal::class, [
                'workspace' => 'ws-emk-dhaka',
                'member' => $this->member,
            ]);

        $modalComponent->call('save');
        $modalComponent->assertStatus(403);
    }

    public function test_core_identity_fields_remain_strictly_immutable(): void
    {
        $staffWithEdit = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Editor Staff 2',
            'email' => 'editor2@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $staffWithEdit->assignRole('delegated_staff');
        $staffWithEdit->givePermissionTo('members.edit');

        $originalMemberId = $this->member->member_id;
        $originalEmail = $this->member->email;
        $originalDob = $this->member->date_of_birth->toDateString();
        $originalName = $this->member->first_name;

        $component = Livewire::actingAs($staffWithEdit, 'web')
            ->test(EditMemberContactModal::class, [
                'workspace' => 'ws-emk-dhaka',
                'member' => $this->member,
            ]);

        $component->set('contact_number', '+8801788888888');
        $component->call('save');

        $freshMember = $this->member->fresh();
        $this->assertEquals('+8801788888888', $freshMember->contact_number);
        $this->assertEquals($originalMemberId, $freshMember->member_id);
        $this->assertEquals($originalEmail, $freshMember->email);
        $this->assertEquals($originalDob, $freshMember->date_of_birth->toDateString());
        $this->assertEquals($originalName, $freshMember->first_name);
    }
}
