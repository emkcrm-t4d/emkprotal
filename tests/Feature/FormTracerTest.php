<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Leads\Models\Lead;
use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormTracerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('spaces')->insert([
            ['id' => 1, 'code' => 'EMK-DHK', 'name' => 'EMK Center Dhaka', 'category' => 'Center', 'division' => 'Dhaka', 'district' => 'Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('workspaces')->insert([
            ['id' => 'ws-emk-dhaka', 'space_id' => 1, 'name' => 'EMK Center Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_form_and_form_submission_implement_tenant_entity_interface(): void
    {
        $form = new Form(['workspace_id' => 'ws-emk-dhaka']);
        $submission = new FormSubmission(['workspace_id' => 'ws-emk-dhaka']);

        $this->assertInstanceOf(TenantEntityInterface::class, $form);
        $this->assertInstanceOf(TenantEntityInterface::class, $submission);
        $this->assertSame('ws-emk-dhaka', $form->getWorkspaceId());
        $this->assertSame('ws-emk-dhaka', $submission->getWorkspaceId());
    }

    public function test_can_create_published_form_with_json_schema(): void
    {
        $form = Form::create([
            'workspace_id' => 'ws-emk-dhaka',
            'slug' => 'membership-application-2026',
            'title' => 'EMK Center Membership Application Form',
            'description' => 'Official membership application form for EMK Center Dhaka.',
            'schema' => [
                'fields' => [
                    ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                    ['name' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true],
                ],
            ],
            'settings' => [
                'confirmation_message' => 'Thank you for applying!',
            ],
            'is_published' => true,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'slug' => 'membership-application-2026',
            'is_published' => 1,
            'is_default' => 1,
        ]);

        $this->assertNotEmpty($form->uuid);
        $this->assertIsArray($form->schema);
        $this->assertCount(2, $form->schema['fields']);
        $this->assertSame('1.0', $form->version);
    }

    public function test_get_apply_slug_returns_200_and_renders_form_title(): void
    {
        $form = Form::create([
            'workspace_id' => 'ws-emk-dhaka',
            'slug' => 'membership-application-public',
            'title' => 'EMK Public Membership 2026',
            'description' => 'Join the EMK Center community.',
            'schema' => [
                'fields' => [
                    ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                ],
            ],
            'is_published' => true,
        ]);

        $response = $this->get('/apply/' . $form->slug);

        $response->assertStatus(200);
        $response->assertSee('EMK Public Membership 2026');
    }

    public function test_get_apply_slug_json_returns_metadata_and_schema(): void
    {
        $form = Form::create([
            'workspace_id' => 'ws-emk-dhaka',
            'slug' => 'membership-json-test',
            'title' => 'API Membership Form',
            'schema' => [
                'fields' => [
                    ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                ],
            ],
            'is_published' => true,
        ]);

        $response = $this->getJson('/apply/' . $form->slug);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'form' => [
                'slug' => 'membership-json-test',
                'title' => 'API Membership Form',
                'schema' => [
                    'fields' => [
                        ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                    ],
                ],
            ],
        ]);
    }

    public function test_post_apply_slug_submit_records_submission_and_returns_200_with_uuid(): void
    {
        $form = Form::create([
            'workspace_id' => 'ws-emk-dhaka',
            'slug' => 'membership-submit-test',
            'title' => 'Submission Test Form',
            'schema' => [
                'fields' => [
                    ['name' => 'first_name', 'type' => 'text'],
                    ['name' => 'bkash_txid', 'type' => 'text'],
                ],
            ],
            'is_published' => true,
        ]);

        $payload = [
            'first_name' => 'Tanjim',
            'bkash_txid' => 'TX12345678',
        ];

        $response = $this->postJson('/apply/' . $form->slug . '/submit', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'submission_uuid',
        ]);

        $submissionUuid = $response->json('submission_uuid');
        $this->assertNotEmpty($submissionUuid);

        $this->assertDatabaseHas('form_submissions', [
            'uuid' => $submissionUuid,
            'form_id' => $form->id,
            'workspace_id' => 'ws-emk-dhaka',
            'status' => 'submitted',
        ]);

        $submission = FormSubmission::where('uuid', $submissionUuid)->first();
        $this->assertNotNull($submission);
        $this->assertSame('Tanjim', $submission->payload['first_name']);
        $this->assertSame($form->id, $submission->form->id);
    }

    public function test_unpublished_form_returns_404_on_get_and_post(): void
    {
        $form = Form::create([
            'workspace_id' => 'ws-emk-dhaka',
            'slug' => 'draft-form',
            'title' => 'Draft Form Not Published',
            'schema' => ['fields' => []],
            'is_published' => false,
        ]);

        $getResponse = $this->get('/apply/' . $form->slug);
        $getResponse->assertStatus(404);

        $postResponse = $this->postJson('/apply/' . $form->slug . '/submit', ['test' => 'data']);
        $postResponse->assertStatus(404);

        $missingGetResponse = $this->get('/apply/non-existent-form');
        $missingGetResponse->assertStatus(404);

        $missingPostResponse = $this->postJson('/apply/non-existent-form/submit', ['test' => 'data']);
        $missingPostResponse->assertStatus(404);
    }

    public function test_leads_table_supports_reapplicants_with_same_email_in_workspace(): void
    {
        Lead::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'rejected',
            'bkash_txid' => 'OLDTXID001',
            'first_name' => 'Applicant',
            'last_name' => 'One',
            'email' => 'reapplicant@emk.test',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '2000-01-01',
            'age_at_submission' => 26,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'Business',
            'submitted_at' => now()->subMonths(3),
        ]);

        // Creating a second lead with identical email in the same workspace succeeds due to dropped unique constraint
        $reappliedLead = Lead::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'NEWTXID002',
            'first_name' => 'Applicant',
            'last_name' => 'One',
            'email' => 'reapplicant@emk.test',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '2000-01-01',
            'age_at_submission' => 26,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'Business',
            'submitted_at' => now(),
        ]);

        $this->assertDatabaseCount('leads', 2);
        $count = Lead::withoutGlobalScopes()
            ->where('email', 'reapplicant@emk.test')
            ->where('workspace_id', 'ws-emk-dhaka')
            ->count();
        $this->assertSame(2, $count);
    }
}
