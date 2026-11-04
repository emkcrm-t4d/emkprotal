<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Mail\LeadRejectionMail;
use App\Domain\Leads\Mail\MissingItemsNotificationMail;
use App\Domain\Leads\Models\Lead;
use App\Livewire\Leads\Modals\MissingItemsModal;
use App\Livewire\Leads\Modals\RejectLeadModal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LeadCommunicationModalTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $staff;
    protected User $unauthorized;
    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // 1. Setup Workspace Manager
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Manager User',
            'email' => 'manager.modal@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Setup Delegated Staff
        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff User',
            'email' => 'staff.modal@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        // 3. Unauthorized User
        $this->unauthorized = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'No Perms User',
            'email' => 'noperms.modal@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);

        // 4. Test Lead
        $this->lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'under_review',
            'bkash_txid' => 'MODALTX001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'contact_number' => '+8801712345055',
            'date_of_birth' => '1998-04-12',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '5',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'entrepreneur',
            'institution_study' => 'IUB',
            'highest_certification' => 'masters',
            'area_of_subject' => 'Business',
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    public function test_staff_can_open_and_submit_missing_items_modal(): void
    {
        Mail::fake();

        $selectedItems = [
            'National ID front side unreadable/cropped',
            'Both ears not clearly visible',
        ];
        $customInstructions = 'Please upload a clearer scan of your NID and ensure both ears are visible in the photo.';

        Livewire::actingAs($this->staff)
            ->test(MissingItemsModal::class, [
                'lead' => $this->lead,
                'workspace' => 'ws-emk-dhaka',
            ])
            ->assertSet('showModal', false)
            ->call('openModal')
            ->assertSet('showModal', true)
            ->set('selectedItems', $selectedItems)
            ->set('customInstructions', $customInstructions)
            ->call('dispatchMissingItemsNotification')
            ->assertSet('showModal', false)
            ->assertDispatched('leadUpdated');

        Mail::assertQueued(MissingItemsNotificationMail::class, function (MissingItemsNotificationMail $mail) use ($selectedItems, $customInstructions) {
            return $mail->hasTo('john.doe@example.com')
                && $mail->missingItems === $selectedItems
                && $mail->customInstructions === $customInstructions;
        });

        $this->assertEquals('missing_items', $this->lead->fresh()->status);
        $this->assertEquals('missing_items', $this->lead->fresh()->document_verification_status);
        $this->assertEquals('reupload_requested', $this->lead->fresh()->photo_verification_status);
    }

    public function test_manager_can_open_and_submit_reject_lead_modal(): void
    {
        Mail::fake();

        Livewire::actingAs($this->manager)
            ->test(RejectLeadModal::class, [
                'lead' => $this->lead,
                'workspace' => 'ws-emk-dhaka',
            ])
            ->assertSet('showModal', false)
            ->call('openModal')
            ->assertSet('showModal', true)
            ->set('rejectionReasonCode', 'SELFIE_DETECTED')
            ->set('internalNotes', 'Casual selfie with filter submitted instead of studio passport photo.')
            ->call('confirmRejection')
            ->assertSet('showModal', false)
            ->assertDispatched('leadUpdated');

        Mail::assertQueued(LeadRejectionMail::class, function (LeadRejectionMail $mail) {
            return $mail->hasTo('john.doe@example.com')
                && $mail->rejectionReasonCode === 'SELFIE_DETECTED'
                && str_contains($mail->applicantFacingMessage, 'Selfies and filtered photos cannot be accepted');
        });

        $this->assertEquals('rejected', $this->lead->fresh()->status);
        $this->assertEquals('rejected', $this->lead->fresh()->photo_verification_status);
    }

    public function test_reject_lead_modal_validates_required_reason_code(): void
    {
        Livewire::actingAs($this->manager)
            ->test(RejectLeadModal::class, [
                'lead' => $this->lead,
                'workspace' => 'ws-emk-dhaka',
            ])
            ->call('openModal')
            ->set('rejectionReasonCode', '') // Empty
            ->call('confirmRejection')
            ->assertHasErrors(['rejectionReasonCode']);
    }

    public function test_manager_can_block_applicant_email_via_rejection_modal(): void
    {
        Mail::fake();

        Livewire::actingAs($this->manager)
            ->test(RejectLeadModal::class, [
                'lead' => $this->lead,
                'workspace' => 'ws-emk-dhaka',
            ])
            ->call('openModal')
            ->set('rejectionReasonCode', 'DUPLICATE_TXID')
            ->set('internalNotes', 'Repeat submitter using counterfeit transaction ID.')
            ->set('blockEmail', true)
            ->call('confirmRejection')
            ->assertSet('showModal', false)
            ->assertDispatched('leadUpdated');

        $this->assertEquals('blocked', $this->lead->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_LEAD_BLOCKED,
            'auditable_type' => Lead::class,
            'auditable_id' => $this->lead->id,
        ]);
    }

    public function test_staff_cannot_open_or_submit_rejection_modal(): void
    {
        Livewire::actingAs($this->staff)
            ->test(RejectLeadModal::class, [
                'lead' => $this->lead,
                'workspace' => 'ws-emk-dhaka',
            ])
            ->call('openModal')
            ->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_open_missing_items_modal(): void
    {
        Livewire::actingAs($this->unauthorized)
            ->test(MissingItemsModal::class, [
                'lead' => $this->lead,
                'workspace' => 'ws-emk-dhaka',
            ])
            ->call('openModal')
            ->assertStatus(403);
    }
}
