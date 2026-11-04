<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Livewire\Leads\Partials\VerificationPanel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class VerificationPanelTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $staff;
    protected Lead $adultLead;
    protected Lead $minorLead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // 1. Manager User
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Manager User',
            'email' => 'manager.panel@emk.test',
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Delegated Staff User
        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff User',
            'email' => 'staff.panel@emk.test',
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        // 3. Adult Lead
        $this->adultLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'submitted',
            'bkash_txid' => 'PNLTX00001',
            'first_name' => 'Panel',
            'last_name' => 'Applicant',
            'email' => 'panel.adult@example.com',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '2000-01-01',
            'age_at_submission' => 26,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'DU',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'CSE',
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Attach files to adult lead
        LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->adultLead->id,
            'document_type' => 'identity_document',
            'storage_path' => 'private/leads/nid.jpg',
            'original_filename' => 'nid_card.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 150000,
            'file_hash' => hash('sha256', 'nid_card_content'),
        ]);

        LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->adultLead->id,
            'document_type' => 'studio_photo',
            'storage_path' => 'private/leads/photo.jpg',
            'original_filename' => 'passport_photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 250000,
            'file_hash' => hash('sha256', 'photo_content'),
        ]);

        // 4. Minor Lead
        $this->minorLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'submitted',
            'bkash_txid' => 'PNLTX00002',
            'first_name' => 'Minor',
            'last_name' => 'Applicant',
            'email' => 'panel.minor@example.com',
            'contact_number' => '+8801700000002',
            'date_of_birth' => '2010-05-15',
            'age_at_submission' => 16,
            'sex' => 'female',
            'address_house_no' => '44',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'Scholastica',
            'highest_certification' => 'o_level',
            'area_of_subject' => 'General',
            'has_parental_consent' => true,
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->minorLead->id,
            'document_type' => 'parental_consent',
            'storage_path' => 'private/leads/consent.pdf',
            'original_filename' => 'signed_consent.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 85000,
            'file_hash' => hash('sha256', 'consent_pdf_content'),
            'youth_protection' => true,
        ]);
    }

    public function test_verification_panel_mounts_and_switches_tabs(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(VerificationPanel::class, [
            'lead' => $this->adultLead,
            'workspace' => 'ws-emk-dhaka',
        ])
            ->assertSet('activeTab', 'payment')
            ->assertSee('Stream A: bKash Transaction Reconciliation')
            ->call('setActiveTab', 'document')
            ->assertSet('activeTab', 'document')
            ->assertSee('Stream B: Identity Document')
            ->call('setActiveTab', 'photo')
            ->assertSet('activeTab', 'photo')
            ->assertSee('Stream C: Studio Passport Photo 5-Point Checklist');
    }

    public function test_manager_submitting_payment_verification_updates_stream(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(VerificationPanel::class, [
            'lead' => $this->adultLead,
            'workspace' => 'ws-emk-dhaka',
        ])
            ->set('bkashTxid', 'PNLTX00001')
            ->set('amountReceived', 1000.0)
            ->set('paymentNotes', 'Confirmed in portal')
            ->call('submitPaymentVerification', 'verified')
            ->assertDispatched('lead-updated');

        $this->assertEquals('verified', $this->adultLead->fresh()->payment_verification_status);
    }

    public function test_duplicate_txid_triggers_warning_alert(): void
    {
        $this->actingAs($this->manager);

        // Pre-create existing lead with TxID 'DUPLI12345'
        Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'submitted',
            'bkash_txid' => 'DUPLI12345',
            'first_name' => 'Existing',
            'last_name' => 'One',
            'email' => 'existing.dupli@example.com',
            'contact_number' => '+8801700000099',
            'date_of_birth' => '1999-01-01',
            'age_at_submission' => 27,
            'sex' => 'female',
            'address_house_no' => '5',
            'address_area' => 'Mirpur',
            'address_zip_code' => '1216',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'DU',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'Art',
            'submitted_at' => now(),
        ]);

        Livewire::test(VerificationPanel::class, [
            'lead' => $this->adultLead,
            'workspace' => 'ws-emk-dhaka',
        ])
            ->set('bkashTxid', 'DUPLI12345')
            ->assertSet('duplicateDetected', true)
            ->assertSee('DUPLICATE TRANSACTION ID DETECTED');
    }

    public function test_staff_cannot_submit_payment_verification(): void
    {
        $this->actingAs($this->staff);

        Livewire::test(VerificationPanel::class, [
            'lead' => $this->adultLead,
            'workspace' => 'ws-emk-dhaka',
        ])
            ->call('submitPaymentVerification', 'verified')
            ->assertStatus(403);
    }

    public function test_staff_submitting_document_verification_with_minor_consent(): void
    {
        $this->actingAs($this->staff);

        Livewire::test(VerificationPanel::class, [
            'lead' => $this->minorLead,
            'workspace' => 'ws-emk-dhaka',
        ])
            ->call('setActiveTab', 'document')
            ->assertSee('YOUTH OUTREACH COMPLIANCE REQUIRED')
            ->set('nameMatches', true)
            ->set('dobMatches', true)
            ->set('under18ConsentVerified', true)
            ->set('idDocumentType', 'passport')
            ->set('idDocumentNumber', 'EA1234567')
            ->call('submitDocumentVerification', 'verified')
            ->assertDispatched('lead-updated');

        $this->assertEquals('verified', $this->minorLead->fresh()->document_verification_status);
    }

    public function test_staff_evaluating_photo_checklist_and_verifying_photo(): void
    {
        $this->actingAs($this->staff);

        $component = Livewire::test(VerificationPanel::class, [
            'lead' => $this->adultLead,
            'workspace' => 'ws-emk-dhaka',
        ])
            ->call('setActiveTab', 'photo')
            ->set('checkWhiteBackground', true)
            ->set('checkNoGlasses', true)
            ->set('checkEarsVisible', true)
            ->set('checkRegularAttire', true)
            ->set('checkNoSelfie', false) // One unchecked
            ->assertSet('areAllPhotoCriteriaMet', false);

        // Check the 5th item
        $component->set('checkNoSelfie', true)
            ->assertSet('areAllPhotoCriteriaMet', true)
            ->call('submitPhotoVerification', 'verified')
            ->assertDispatched('lead-updated');

        $this->assertEquals('verified', $this->adultLead->fresh()->photo_verification_status);
    }

    public function test_manager_can_approve_lead_when_all_streams_verified(): void
    {
        $this->actingAs($this->manager);

        // Pre-set all streams to verified
        $this->adultLead->update([
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
        ]);

        Livewire::test(VerificationPanel::class, [
            'lead' => $this->adultLead,
            'workspace' => 'ws-emk-dhaka',
        ])
            ->assertSet('canApproveLead', true)
            ->assertSee('Approve Lead for Induction')
            ->call('approveLead')
            ->assertDispatched('lead-updated');

        $this->assertEquals('approved', $this->adultLead->fresh()->status);
    }
}
