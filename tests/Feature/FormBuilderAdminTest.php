<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\AccessControl\Services\StaffManagementService;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Services\FormSchemaEngine;
use App\Livewire\Forms\FormBuilder;
use App\Livewire\Forms\FormIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FormBuilderAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $managerDhaka;
    protected User $managerSylhet;
    protected User $staffDhaka;
    protected User $unauthorizedStaff;
    protected Form $dhakaForm;
    protected Form $sylhetForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        // Provision Sylhet workspace
        $sylhetSpace = DB::table('spaces')->where('code', 'CR-SYL')->first();
        DB::table('workspaces')->updateOrInsert(
            ['id' => 'ws-corner-sylhet'],
            [
                'space_id' => $sylhetSpace ? $sylhetSpace->id : 1,
                'name' => 'American Corner Sylhet',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->managerDhaka = User::where('email', 'manager.dhaka@emk.test')->first();

        $this->managerSylhet = User::create([
            'name' => 'Sylhet Manager',
            'email' => 'manager.sylhet@emk.test',
            'workspace_id' => 'ws-corner-sylhet',
            'password' => Hash::make('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->managerSylhet->assignRole('workspace_manager');

        $staffService = app(StaffManagementService::class);

        $this->staffDhaka = $staffService->createStaffUser([
            'name' => 'Dhaka Delegated Staff',
            'email' => 'staff.dhaka@emk.test',
        ], $this->managerDhaka);
        // By default delegated_staff has forms.view and forms.view_entries

        $this->unauthorizedStaff = User::create([
            'name' => 'Unauthorized Staff',
            'email' => 'no.perms@emk.test',
            'workspace_id' => 'ws-emk-dhaka',
            'password' => Hash::make('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        // Has no roles or permissions

        // Seed sample forms in both workspaces
        $this->dhakaForm = Form::create([
            'workspace_id' => 'ws-emk-dhaka',
            'slug' => 'dhaka-membership-test',
            'title' => 'Dhaka Membership Test Form',
            'description' => 'Test form for Dhaka workspace.',
            'schema' => [
                'fields' => [
                    ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                    ['name' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true],
                ],
            ],
            'is_published' => false,
            'is_default' => false,
            'version' => '1.0',
        ]);

        $this->sylhetForm = Form::create([
            'workspace_id' => 'ws-corner-sylhet',
            'slug' => 'sylhet-membership-test',
            'title' => 'Sylhet Membership Test Form',
            'description' => 'Test form for Sylhet workspace.',
            'schema' => [
                'fields' => [
                    ['name' => 'full_name', 'type' => 'text', 'label' => 'Full Name', 'required' => true],
                ],
            ],
            'is_published' => false,
            'is_default' => false,
            'version' => '1.0',
        ]);
    }

    public function test_workspace_staff_with_forms_view_can_access_form_index_and_sees_only_own_workspace_forms(): void
    {
        $this->actingAs($this->staffDhaka);

        // Delegated staff has forms.view
        Livewire::test(FormIndex::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertStatus(200)
            ->assertSee('Dhaka Membership Test Form')
            ->assertSee('dhaka-membership-test')
            ->assertDontSee('Sylhet Membership Test Form')
            ->assertDontSee('sylhet-membership-test');
    }

    public function test_staff_without_forms_view_receives_403_on_form_index(): void
    {
        $this->actingAs($this->unauthorizedStaff);

        Livewire::test(FormIndex::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertStatus(403);
    }

    public function test_staff_cannot_access_mismatched_workspace_form_index(): void
    {
        $this->actingAs($this->staffDhaka);

        // Dhaka staff trying to access Sylhet workspace
        Livewire::test(FormIndex::class, ['workspace' => 'ws-corner-sylhet'])
            ->assertStatus(403);
    }

    public function test_superadmin_can_access_any_workspace_form_index(): void
    {
        $superadmin = User::where('email', 'admin@emk.test')->first();
        $this->actingAs($superadmin);

        Livewire::test(FormIndex::class, ['workspace' => 'ws-corner-sylhet'])
            ->assertStatus(200)
            ->assertSee('Sylhet Membership Test Form');
    }

    public function test_authorized_user_can_create_new_form_via_form_index(): void
    {
        $this->actingAs($this->managerDhaka);

        Livewire::test(FormIndex::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->set('newTitle', 'Youth Innovation Grant 2026')
            ->set('newSlug', 'youth-innovation-grant-2026')
            ->set('newDescription', 'Application form for the youth innovation grant.')
            ->call('createForm')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false)
            ->assertSee('Youth Innovation Grant 2026');

        $this->assertDatabaseHas('forms', [
            'workspace_id' => 'ws-emk-dhaka',
            'slug' => 'youth-innovation-grant-2026',
            'title' => 'Youth Innovation Grant 2026',
            'is_published' => 0,
        ]);
    }

    public function test_staff_without_forms_create_cannot_create_form(): void
    {
        $this->actingAs($this->staffDhaka);
        // staffDhaka only has forms.view, not forms.create

        Livewire::test(FormIndex::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openCreateModal')
            ->assertStatus(403);
    }

    public function test_embed_modal_generates_valid_permalink_and_iframe_code(): void
    {
        $this->actingAs($this->managerDhaka);

        $component = Livewire::test(FormIndex::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openEmbedModal', $this->dhakaForm->id)
            ->assertSet('showEmbedModal', true);

        $permalink = route('public.forms.show', ['slug' => $this->dhakaForm->slug]);
        $component->assertSet('embedPermalink', $permalink);
        $this->assertStringContainsString($permalink, $component->get('embedIframe'));
        $this->assertStringContainsString('<iframe', $component->get('embedIframe'));
        $this->assertStringContainsString('width="100%"', $component->get('embedIframe'));
        $this->assertStringContainsString('emk-form-embed', $component->get('embedHtml'));
    }

    public function test_form_builder_requires_forms_edit_permission(): void
    {
        // staffDhaka does not have forms.edit yet
        $this->actingAs($this->staffDhaka);

        Livewire::test(FormBuilder::class, [
            'workspace' => 'ws-emk-dhaka',
            'form' => $this->dhakaForm,
        ])->assertStatus(403);
    }

    public function test_staff_with_delegated_forms_edit_can_access_builder_and_update_schema(): void
    {
        $staffService = app(StaffManagementService::class);
        $staffService->delegatePermission($this->managerDhaka, $this->staffDhaka, 'forms.edit');

        $this->actingAs($this->staffDhaka);

        $component = Livewire::test(FormBuilder::class, [
            'workspace' => 'ws-emk-dhaka',
            'form' => $this->dhakaForm,
        ]);

        $component->assertStatus(200)
            ->assertSee('Dhaka Membership Test Form')
            ->assertSee('first_name')
            ->assertSee('email');

        // Add a new phone field
        $component->call('openAddFieldModal')
            ->assertSet('showFieldModal', true)
            ->set('fieldType', 'text')
            ->set('fieldLabel', 'Contact Number')
            ->set('fieldName', 'contact_number')
            ->set('fieldRequired', true)
            ->set('fieldRegex', '^\+8801[3-9]\d{8}$')
            ->call('saveField')
            ->assertHasNoErrors()
            ->assertSet('showFieldModal', false);

        // Call saveSchema
        $component->call('saveSchema')
            ->assertHasNoErrors()
            ->assertSee('Form schema successfully saved');

        $this->dhakaForm->refresh();
        $fields = $this->dhakaForm->schema['fields'];
        $this->assertCount(3, $fields);
        $this->assertSame('contact_number', $fields[2]['name']);
        $this->assertTrue($fields[2]['required']);
    }

    public function test_manager_can_publish_and_archive_form(): void
    {
        $this->actingAs($this->managerDhaka);

        // Test publish from FormBuilder
        Livewire::test(FormBuilder::class, [
            'workspace' => 'ws-emk-dhaka',
            'form' => $this->dhakaForm,
        ])
            ->assertSet('isPublished', false)
            ->call('publish')
            ->assertSet('isPublished', true)
            ->assertSee('has been published');

        $this->dhakaForm->refresh();
        $this->assertTrue($this->dhakaForm->is_published);

        // Test archive from FormIndex
        Livewire::test(FormIndex::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('archive', $this->dhakaForm->id)
            ->assertSee('has been archived');

        $this->dhakaForm->refresh();
        $this->assertFalse($this->dhakaForm->is_published);
    }

    public function test_delegated_staff_with_forms_edit_receives_403_when_attempting_publish_in_builder(): void
    {
        $staffService = app(StaffManagementService::class);
        $staffService->delegatePermission($this->managerDhaka, $this->staffDhaka, 'forms.edit');

        $this->actingAs($this->staffDhaka);

        Livewire::test(FormBuilder::class, [
            'workspace' => 'ws-emk-dhaka',
            'form' => $this->dhakaForm,
        ])
            ->call('publish')
            ->assertStatus(403);
    }

    public function test_delegated_staff_with_forms_edit_receives_403_when_attempting_archive_in_builder(): void
    {
        $staffService = app(StaffManagementService::class);
        $staffService->delegatePermission($this->managerDhaka, $this->staffDhaka, 'forms.edit');

        $this->actingAs($this->staffDhaka);

        Livewire::test(FormBuilder::class, [
            'workspace' => 'ws-emk-dhaka',
            'form' => $this->dhakaForm,
        ])
            ->call('archive')
            ->assertStatus(403);
    }

    public function test_delegated_staff_receives_403_when_attempting_publish_in_index(): void
    {
        $this->actingAs($this->staffDhaka);

        Livewire::test(FormIndex::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('publish', $this->dhakaForm->id)
            ->assertStatus(403);
    }

    public function test_delegated_staff_receives_403_when_attempting_archive_in_index(): void
    {
        $this->actingAs($this->staffDhaka);

        Livewire::test(FormIndex::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('archive', $this->dhakaForm->id)
            ->assertStatus(403);
    }

    public function test_cross_workspace_form_hydration_triggers_403_tampering_block(): void
    {
        $this->actingAs($this->managerDhaka);

        // Dhaka manager trying to load Sylhet form into FormBuilder
        Livewire::test(FormBuilder::class, [
            'workspace' => 'ws-emk-dhaka',
            'form' => $this->sylhetForm,
        ])->assertStatus(403);
    }

    public function test_http_routes_are_mounted_and_accessible_for_authorized_users(): void
    {
        $this->actingAs($this->managerDhaka);

        $indexResponse = $this->get('/crm/ws-emk-dhaka/forms');
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Form Management');

        $builderResponse = $this->get('/crm/ws-emk-dhaka/forms/' . $this->dhakaForm->id . '/builder');
        $builderResponse->assertStatus(200);
        $builderResponse->assertSee('Dhaka Membership Test Form');
    }
}
