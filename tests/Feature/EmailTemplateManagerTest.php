<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Mail\EmailTemplateManager;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplateManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SpaceAndWorkspaceSeeder::class,
            RolesAndPermissionsSeeder::class,
            EmailTemplateSeeder::class,
        ]);
    }

    private function createStaffUser(string $workspaceId, string $email, string $role): User
    {
        $user = User::create([
            'workspace_id' => $workspaceId,
            'name' => 'Staff ' . $role,
            'email' => $email,
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_workspace_manager_can_access_email_template_manager_and_view_all_canonical_milestones(): void
    {
        $manager = $this->createStaffUser('ws-emk-dhaka', 'manager.tpl@emk.test', 'workspace_manager');

        $response = $this->actingAs($manager, 'web')
            ->get('/crm/ws-emk-dhaka/settings/email-templates');

        $response->assertStatus(200);
        $response->assertSee('Bilingual Lifecycle Email Templates');

        foreach (EmailTemplate::CANONICAL_MILESTONES as $milestoneKey) {
            $response->assertSee($milestoneKey);
        }

        $this->assertSame(
            11,
            EmailTemplate::withoutGlobalScopes()->where('workspace_id', 'ws-emk-dhaka')->count()
        );
    }

    public function test_editing_bilingual_template_persists_changes_strips_scripts_and_logs_audit_event(): void
    {
        $manager = $this->createStaffUser('ws-emk-dhaka', 'manager.edit@emk.test', 'workspace_manager');

        $template = EmailTemplate::withoutGlobalScopes()
            ->where('workspace_id', 'ws-emk-dhaka')
            ->where('template_key', EmailTemplate::MILESTONE_APPLICATION_RECEIPT)
            ->firstOrFail();

        Livewire::actingAs($manager, 'web')
            ->test(EmailTemplateManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('editTemplate', $template->id)
            ->set('name', 'Updated Receipt Confirmation')
            ->set('subject_en', 'Custom Receipt for {{applicant_name}} at {{workspace_name}}')
            ->set('body_en', '<p>Hello {{applicant_name}}, your fee of BDT {{amount_bdt}} was received.</p><script>alert(1)</script>')
            ->set('subject_bn', 'আপডেটকৃত রসিদ — {{applicant_name}}')
            ->set('body_bn', '<p>প্রিয় {{applicant_name}}, আপনার {{amount_bdt}} টাকা গৃহীত হয়েছে।</p><script>evil()</script>')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $template->refresh();

        $this->assertSame('Updated Receipt Confirmation', $template->name);
        $this->assertSame('Custom Receipt for {{applicant_name}} at {{workspace_name}}', $template->subject_en);
        $this->assertStringNotContainsString('<script>', $template->body_en);
        $this->assertSame('আপডেটকৃত রসিদ — {{applicant_name}}', $template->subject_bn);
        $this->assertStringNotContainsString('<script>', (string) $template->body_bn);
        $this->assertSame($manager->id, $template->updated_by_user_id);

        $audit = AuditLog::withoutGlobalScopes()
            ->where('workspace_id', 'ws-emk-dhaka')
            ->where('event_type', AuditLog::EVENT_EMAIL_TEMPLATE_UPDATED)
            ->where('auditable_id', $template->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($manager->id, $audit->actor_id);
        $this->assertSame('Updated Receipt Confirmation', $audit->new_values['name']);
    }

    public function test_open_preview_renders_interpolated_sample_variables_in_en_bn_and_bilingual_modes(): void
    {
        $manager = $this->createStaffUser('ws-emk-dhaka', 'manager.preview@emk.test', 'workspace_manager');

        $template = EmailTemplate::withoutGlobalScopes()
            ->where('workspace_id', 'ws-emk-dhaka')
            ->where('template_key', EmailTemplate::MILESTONE_CONVERSION_CONFIRMATION)
            ->firstOrFail();

        $component = Livewire::actingAs($manager, 'web')
            ->test(EmailTemplateManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openPreview', $template->id, 'en');

        $previewEn = $component->get('previewOutput');
        $this->assertStringContainsString('EMK Center Dhaka', $previewEn['subject']);
        $this->assertStringContainsString('EMK-2026-00042', $previewEn['subject']);
        $this->assertStringContainsString('Nusrat Jahan', $previewEn['body_html']);

        $component->call('openPreview', $template->id, 'bn');
        $previewBn = $component->get('previewOutput');
        $this->assertStringContainsString('EMK Center Dhaka', $previewBn['subject']);
        $this->assertStringContainsString('স্বাগতম', $previewBn['subject']);
        $this->assertStringContainsString('Nusrat Jahan', $previewBn['body_html']);

        $component->call('openPreview', $template->id, 'bilingual');
        $previewBilingual = $component->get('previewOutput');
        $this->assertStringContainsString('|', $previewBilingual['subject']);
        $this->assertStringContainsString('<hr', $previewBilingual['body_html']);
    }

    public function test_unauthorized_staff_and_cross_workspace_managers_receive_403(): void
    {
        $regularStaff = $this->createStaffUser('ws-emk-dhaka', 'staff.tpl@emk.test', 'verification_officer');

        $this->actingAs($regularStaff, 'web')
            ->get('/crm/ws-emk-dhaka/settings/email-templates')
            ->assertStatus(403);

        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::query()->firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'American Corner Chattogram',
                'is_active' => true,
            ]
        );

        $otherManager = $this->createStaffUser('ws-chattogram', 'manager.ctg@emk.test', 'workspace_manager');

        $this->actingAs($otherManager, 'web')
            ->get('/crm/ws-emk-dhaka/settings/email-templates')
            ->assertStatus(403);
    }
}
