<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailThread;
use App\Domain\Members\Models\Member;
use App\Livewire\Portal\MemberPortalCommunicationHistory;
use App\Livewire\Portal\MemberPortalProfile;
use App\Models\MemberUser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PortalReadOnlyProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createMemberFixture(
        string $email,
        string $memberId,
        string $firstName = 'Alice',
        string $middleName = 'Marie',
        string $lastName = 'Member',
        string $status = 'active'
    ): array {
        $term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'REG-1YR'],
            [
                'name' => '1 Year Regular Membership',
                'duration_months' => 12,
                'fee_bdt' => 500.00,
                'is_active' => true,
            ]
        );

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXID' . strtoupper(Str::random(6)),
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'email' => $email,
            'contact_number' => '+8801712345678',
            'date_of_birth' => '1998-05-15',
            'age_at_submission' => 28,
            'sex' => 'female',
            'address_house_no' => 'House 42',
            'address_road_no' => 'Road 9/A',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'researcher',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'masters_degree',
            'area_of_subject' => 'Biochemistry',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now(),
        ]);

        $memberUser = MemberUser::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => "{$firstName} {$lastName}",
            'email' => $email,
            'password' => Hash::make('MemberPass123!'),
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $memberUser->assignRole('member');

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $memberId,
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $memberUser->id,
            'membership_term_id' => $term->id,
            'status' => $status,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'email' => $email,
            'contact_number' => '+8801712345678',
            'date_of_birth' => '1998-05-15',
            'sex' => 'female',
            'address_house_no' => 'House 42',
            'address_road_no' => 'Road 9/A',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'researcher',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'masters_degree',
            'area_of_subject' => 'Biochemistry',
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        $idCard = IdCard::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $member->id,
            'card_number' => 'CRD-' . strtoupper(Str::random(8)),
            'status' => 'card_printed_ready_for_pickup',
            'batch_reference' => 'BATCH-2026-01',
            'printed_at' => now(),
        ]);

        return [$memberUser, $member, $lead, $term, $idCard];
    }

    public function test_guest_visiting_profile_is_redirected_to_login(): void
    {
        $response = $this->get(route('portal.profile'));

        $response->assertRedirect(route('portal.login'));
    }

    public function test_authenticated_member_can_view_profile_demographics_and_membership_terms(): void
    {
        [$memberUser, $member, $lead, $term, $idCard] = $this->createMemberFixture(
            email: 'alice.profile@emk.test',
            memberId: 'EMK-2026-00042',
            firstName: 'Alice',
            middleName: 'Marie',
            lastName: 'Walker'
        );

        $response = $this->actingAs($memberUser, 'member')
            ->get(route('portal.profile'));

        $response->assertOk();

        // 1. Identity & badges
        $response->assertSee('EMK-2026-00042');
        $response->assertSee('Alice Marie Walker');
        $response->assertSee('Active Member');

        // 2. Term & ID card status
        $response->assertSee('1 Year Regular Membership');
        $response->assertSee('Ready for Pickup at Front Desk');
        $response->assertSee($idCard->card_number);

        // 3. Demographic & contact attributes in read-only cards
        $response->assertSee('Alice');
        $response->assertSee('Marie');
        $response->assertSee('Walker');
        $response->assertSee('May 15, 1998');
        $response->assertSee('Female');
        $response->assertSee('alice.profile@emk.test');
        $response->assertSee('+8801712345678');
        $response->assertSee('House 42');
        $response->assertSee('Road 9/A');
        $response->assertSee('Dhanmondi');
        $response->assertSee('1209');
        $response->assertSee('Dhaka');
        $response->assertSee('Researcher');
        $response->assertSee('University of Dhaka');
        $response->assertSee('Biochemistry');
    }

    public function test_internal_staff_notes_audit_logs_and_invoices_are_strictly_hidden(): void
    {
        [$memberUser, $member, $lead] = $this->createMemberFixture(
            email: 'secret.guard@emk.test',
            memberId: 'EMK-2026-00099'
        );

        // 1. Create sensitive invoice (hidden per DECISION-REGISTER Priority 2)
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SECRET-99999',
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $member->id,
            'lead_id' => $lead->id,
            'amount_bdt' => 1500.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXN-CONFIDENTIAL-888',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // 2. Create internal staff verification note on lead_verifications table
        DB::table('lead_verifications')->insert([
            'lead_id' => $lead->id,
            'stream' => 'document',
            'status' => 'verified',
            'verified_by_user_id' => 1,
            'verified_at' => now(),
            'evidence_payload' => json_encode(['doc' => 'ok']),
            'notes' => 'INTERNAL STAFF SECRET DELIBERATION NOTE 12345',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Create confidential audit log entry
        AuditLog::create([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => 1,
            'actor_guard' => 'web',
            'event_type' => 'staff_security_investigation',
            'auditable_type' => Member::class,
            'auditable_id' => $member->id,
            'reason' => 'CONFIDENTIAL INTERNAL AUDIT REASON 67890',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Internal Security Console',
            'created_at' => now(),
        ]);

        // 4. Test Livewire component and HTTP response
        $component = Livewire::actingAs($memberUser, 'member')
            ->test(MemberPortalProfile::class);

        // Assert strictly not visible in rendered output
        $component->assertDontSee('INV-SECRET-99999');
        $component->assertDontSee('TXN-CONFIDENTIAL-888');
        $component->assertDontSee('1500.00');
        $component->assertDontSee('INTERNAL STAFF SECRET DELIBERATION NOTE 12345');
        $component->assertDontSee('CONFIDENTIAL INTERNAL AUDIT REASON 67890');

        // Assert HTTP get also does not contain leaked values
        $response = $this->actingAs($memberUser, 'member')->get(route('portal.profile'));
        $response->assertDontSee('INV-SECRET-99999');
        $response->assertDontSee('TXN-CONFIDENTIAL-888');
        $response->assertDontSee('INTERNAL STAFF SECRET DELIBERATION NOTE 12345');
        $response->assertDontSee('CONFIDENTIAL INTERNAL AUDIT REASON 67890');
    }

    public function test_profile_active_tab_switching(): void
    {
        [$memberUser] = $this->createMemberFixture(
            email: 'tab.switch@emk.test',
            memberId: 'EMK-2026-00077'
        );

        $component = Livewire::actingAs($memberUser, 'member')
            ->test(MemberPortalProfile::class);

        $component->assertSet('activeTab', 'profile');

        $component->call('setTab', 'documents')
            ->assertSet('activeTab', 'documents');

        $component->call('setTab', 'communications')
            ->assertSet('activeTab', 'communications');

        // Invalid tab is rejected and preserves current state
        $component->call('setTab', 'malicious_tab')
            ->assertSet('activeTab', 'communications');
    }

    public function test_member_can_securely_download_own_documents(): void
    {
        Storage::fake('private');

        [$memberUser, $member, $lead] = $this->createMemberFixture(
            email: 'doc.downloader@emk.test',
            memberId: 'EMK-2026-00088'
        );

        $docPath = 'workspaces/ws-emk-dhaka/leads/' . $lead->uuid . '/nid_scan.pdf';
        Storage::disk('private')->put($docPath, 'BINARY_PDF_DOCUMENT_CONTENT');

        $attachment = LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'document_type' => 'identity_document',
            'storage_path' => $docPath,
            'original_filename' => 'national_id_scan.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 28,
            'file_hash' => hash('sha256', 'BINARY_PDF_DOCUMENT_CONTENT'),
            'youth_protection' => false,
        ]);

        $response = $this->actingAs($memberUser, 'member')
            ->get(route('portal.documents.download', ['attachment' => $attachment->id]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename="national_id_scan.pdf"');

        // Verify audit log entry was written
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $memberUser->id,
            'actor_guard' => 'member',
            'event_type' => 'portal_document_accessed',
            'auditable_type' => LeadAttachment::class,
            'auditable_id' => $attachment->id,
        ]);
    }

    public function test_idor_defense_blocks_cross_member_document_download_with_403(): void
    {
        Storage::fake('private');

        [$aliceUser, $aliceMember, $aliceLead] = $this->createMemberFixture(
            email: 'alice.idor@emk.test',
            memberId: 'EMK-2026-00101',
            firstName: 'Alice'
        );

        [$bobUser, $bobMember, $bobLead] = $this->createMemberFixture(
            email: 'bob.idor@emk.test',
            memberId: 'EMK-2026-00102',
            firstName: 'Bob'
        );

        $bobPath = 'workspaces/ws-emk-dhaka/leads/' . $bobLead->uuid . '/bob_passport.pdf';
        Storage::disk('private')->put($bobPath, 'BOB_CONFIDENTIAL_PASSPORT');

        $bobAttachment = LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $bobLead->id,
            'document_type' => 'identity_document',
            'storage_path' => $bobPath,
            'original_filename' => 'bob_passport.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 24,
            'file_hash' => hash('sha256', 'BOB_CONFIDENTIAL_PASSPORT'),
            'youth_protection' => false,
        ]);

        // Alice attempts to download Bob's document -> must be blocked with HTTP 403 Forbidden
        $response = $this->actingAs($aliceUser, 'member')
            ->get(route('portal.documents.download', ['attachment' => $bobAttachment->id]));

        $response->assertStatus(403);
    }

    public function test_guest_cannot_download_member_documents(): void
    {
        Storage::fake('private');

        [$memberUser, $member, $lead] = $this->createMemberFixture(
            email: 'guest.block@emk.test',
            memberId: 'EMK-2026-00103'
        );

        $docPath = 'workspaces/ws-emk-dhaka/leads/' . $lead->uuid . '/doc.pdf';
        Storage::disk('private')->put($docPath, 'DOC_CONTENT');

        $attachment = LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'document_type' => 'identity_document',
            'storage_path' => $docPath,
            'original_filename' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 11,
            'file_hash' => hash('sha256', 'DOC_CONTENT'),
            'youth_protection' => false,
        ]);

        $response = $this->get(route('portal.documents.download', ['attachment' => $attachment->id]));

        $response->assertRedirect(route('portal.login'));
    }

    public function test_nonexistent_document_returns_404(): void
    {
        [$memberUser] = $this->createMemberFixture(
            email: 'notfound@emk.test',
            memberId: 'EMK-2026-00104'
        );

        $response = $this->actingAs($memberUser, 'member')
            ->get(route('portal.documents.download', ['attachment' => 999999]));

        $response->assertStatus(404);
    }

    public function test_member_can_view_threaded_email_correspondence(): void
    {
        [$memberUser, $member, $lead] = $this->createMemberFixture(
            email: 'comm.alice@emk.test',
            memberId: 'EMK-2026-00201'
        );

        $thread = EmailThread::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $member->id,
            'lead_id' => $lead->id,
            'participant_email' => $member->email,
            'subject' => 'Application Review Confirmation',
            'last_message_at' => now(),
            'message_count' => 1,
            'is_resolved' => false,
        ]);

        $msg = EmailMessage::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'email_thread_id' => $thread->id,
            'member_id' => $member->id,
            'direction' => 'outbound',
            'status' => 'sent',
            'from_email' => 'membership@emk.com.bd',
            'to_email' => $member->email,
            'subject' => 'Application Review Confirmation',
            'body_text' => 'Welcome Alice, your membership verification is complete.',
            'body_html' => '<p>Welcome Alice, your membership verification is complete.</p>',
            'sent_at' => now(),
        ]);

        $component = Livewire::actingAs($memberUser, 'member')
            ->test(MemberPortalCommunicationHistory::class);

        $component->assertSee('Application Review Confirmation');
        $component->assertSee('Welcome Alice, your membership verification is complete.');
        $component->assertSee('From: EMK Information Desk');
    }

    public function test_member_cannot_see_other_members_email_threads(): void
    {
        [$aliceUser, $aliceMember, $aliceLead] = $this->createMemberFixture(
            email: 'alice.private@emk.test',
            memberId: 'EMK-2026-00202',
            firstName: 'Alice'
        );

        [$bobUser, $bobMember, $bobLead] = $this->createMemberFixture(
            email: 'bob.private@emk.test',
            memberId: 'EMK-2026-00203',
            firstName: 'Bob'
        );

        // Thread belonging solely to Bob
        $bobThread = EmailThread::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $bobMember->id,
            'lead_id' => $bobLead->id,
            'participant_email' => $bobMember->email,
            'subject' => 'Bob Confidential Grievance Notice 998877',
            'last_message_at' => now(),
            'message_count' => 1,
        ]);

        EmailMessage::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'email_thread_id' => $bobThread->id,
            'member_id' => $bobMember->id,
            'direction' => 'inbound',
            'status' => 'received',
            'from_email' => $bobMember->email,
            'to_email' => 'membership@emk.com.bd',
            'subject' => 'Bob Confidential Grievance Notice 998877',
            'body_text' => 'This is Bob personal grievance message content.',
            'received_at' => now(),
        ]);

        // Alice views correspondence -> must NOT see Bob's thread or messages
        $component = Livewire::actingAs($aliceUser, 'member')
            ->test(MemberPortalCommunicationHistory::class);

        $component->assertDontSee('Bob Confidential Grievance Notice 998877');
        $component->assertDontSee('This is Bob personal grievance message content.');
    }

    public function test_email_html_body_is_sanitized_against_xss(): void
    {
        [$memberUser, $member, $lead] = $this->createMemberFixture(
            email: 'xss.target@emk.test',
            memberId: 'EMK-2026-00204'
        );

        $xssThread = EmailThread::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $member->id,
            'lead_id' => $lead->id,
            'participant_email' => $member->email,
            'subject' => 'Security Audit Notice',
            'last_message_at' => now(),
            'message_count' => 1,
        ]);

        EmailMessage::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'email_thread_id' => $xssThread->id,
            'member_id' => $member->id,
            'direction' => 'inbound',
            'status' => 'received',
            'from_email' => $member->email,
            'to_email' => 'membership@emk.com.bd',
            'subject' => 'Security Audit Notice',
            'body_html' => '<p>Safe paragraph text</p><script>alert("XSS_ATTACK_VECTOR")</script><img src="missing.png" onerror="alert(1)"><a href="javascript:alert(2)">Malicious Link</a>',
            'received_at' => now(),
        ]);

        $component = Livewire::actingAs($memberUser, 'member')
            ->test(MemberPortalCommunicationHistory::class, ['initialThreadId' => (int) $xssThread->id]);

        $component->assertSee('Safe paragraph text');
        $component->assertDontSee('<script>', false);
        $component->assertDontSee('alert("XSS_ATTACK_VECTOR")', false);
        $component->assertDontSee('onerror=', false);
        $component->assertDontSee('javascript:', false);
    }
}
