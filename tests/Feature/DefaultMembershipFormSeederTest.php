<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Services\FormSchemaEngine;
use App\Domain\Forms\Services\FormValidationCompiler;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultMembershipFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultMembershipFormSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_default_membership_form_seeder_provisions_canonical_record(): void
    {
        $this->seed(DefaultMembershipFormSeeder::class);

        $form = Form::withoutGlobalScopes()
            ->where('slug', 'membership-application')
            ->where('workspace_id', 'ws-emk-dhaka')
            ->first();

        $this->assertNotNull($form, 'Default membership application form must be seeded.');
        $this->assertSame('membership-application', $form->slug);
        $this->assertSame('ws-emk-dhaka', $form->workspace_id);
        $this->assertSame('Membership Form for American Spaces in Bangladesh', $form->title);
        $this->assertSame('1.0-31f', $form->version);
        $this->assertTrue($form->is_published);
        $this->assertTrue($form->is_default);

        // Assert Settings
        $this->assertIsArray($form->settings);
        $this->assertTrue($form->settings['confirmation_email']);
        $this->assertCount(6, $form->settings['allowed_spaces']);
        $this->assertArrayHasKey('applicant_instructions', $form->settings);
        $this->assertArrayHasKey('studio_photo_guidelines', $form->settings['applicant_instructions']);
        $this->assertArrayHasKey('payment_instructions', $form->settings['applicant_instructions']);
        $this->assertArrayHasKey('under_18_consent_guidance', $form->settings['applicant_instructions']);
    }

    public function test_seeded_form_schema_contains_exactly_thirty_one_fields_with_valid_keys(): void
    {
        $this->seed(DefaultMembershipFormSeeder::class);

        $form = Form::withoutGlobalScopes()->where('slug', 'membership-application')->firstOrFail();
        $fields = $form->schema['fields'] ?? [];

        $this->assertCount(31, $fields, 'Seeded form must contain exactly 31 fields per FIELD-DICTIONARY.md.');

        $expectedFieldNames = [
            1 => 'first_name',
            2 => 'middle_name',
            3 => 'last_name',
            4 => 'american_space',
            5 => 'sex',
            6 => 'date_of_birth',
            7 => 'parental_consent_path',
            8 => 'address_house_no',
            9 => 'address_road_no',
            10 => 'address_area',
            11 => 'address_zip_code',
            12 => 'address_division',
            13 => 'address_district',
            14 => 'contact_number',
            15 => 'email',
            16 => 'website',
            17 => 'occupation',
            18 => 'institution_study',
            19 => 'highest_certification',
            20 => 'area_of_subject',
            21 => 'has_us_experience',
            22 => 'us_experience_location',
            23 => 'is_american_alumni',
            24 => 'american_institution_name',
            25 => 'employer_name',
            26 => 'job_title',
            27 => 'work_phone',
            28 => 'identity_document_paths',
            29 => 'photo_path',
            30 => 'bkash_txid',
            31 => 'terms_agreed',
        ];

        foreach ($expectedFieldNames as $index => $expectedName) {
            $field = $fields[$index - 1];
            $this->assertSame(
                $expectedName,
                $field['name'],
                "Field at index {$index} must be named '{$expectedName}'."
            );
        }

        // Validate complete schema passes FormSchemaEngine validation
        $engine = app(FormSchemaEngine::class);
        $this->assertTrue($engine->validateSchema($form->schema), 'Seeded schema must be 100% valid.');
    }

    public function test_division_and_district_mapping_conforms_to_bangladesh_geography(): void
    {
        $this->seed(DefaultMembershipFormSeeder::class);

        $form = Form::withoutGlobalScopes()->where('slug', 'membership-application')->firstOrFail();
        $fields = collect($form->schema['fields'])->keyBy('name');

        $divisionField = $fields['address_division'];
        $districtField = $fields['address_district'];

        $this->assertSame(FormSchemaEngine::TYPE_SELECT, $divisionField['type']);
        $this->assertSame(FormSchemaEngine::TYPE_CASCADING_SELECT, $districtField['type']);
        $this->assertSame('address_division', $districtField['parent_field']);

        // Assert 8 divisions
        $engine = app(FormSchemaEngine::class);
        $divisions = $engine->getDataset('bangladesh_divisions');
        $this->assertCount(8, $divisions);

        // Assert 64 districts partitioned across divisions
        $allDistricts = $engine->getAllDistricts();
        $this->assertCount(64, $allDistricts);

        $divisionDistrictMap = FormSchemaEngine::DIVISION_DISTRICT_MAP;
        $this->assertCount(6, $divisionDistrictMap['barishal']);
        $this->assertCount(11, $divisionDistrictMap['chattogram']);
        $this->assertCount(13, $divisionDistrictMap['dhaka']);
        $this->assertCount(10, $divisionDistrictMap['khulna']);
        $this->assertCount(4, $divisionDistrictMap['mymensingh']);
        $this->assertCount(8, $divisionDistrictMap['rajshahi']);
        $this->assertCount(8, $divisionDistrictMap['rangpur']);
        $this->assertCount(4, $divisionDistrictMap['sylhet']);
    }

    public function test_field_seven_parental_consent_enforces_under_eighteen_conditional_rule(): void
    {
        $this->seed(DefaultMembershipFormSeeder::class);

        $form = Form::withoutGlobalScopes()->where('slug', 'membership-application')->firstOrFail();
        $fields = collect($form->schema['fields'])->keyBy('name');

        $consentField = $fields['parental_consent_path'];
        $this->assertSame(FormSchemaEngine::TYPE_FILE, $consentField['type']);
        $this->assertNotEmpty($consentField['conditional_rules']);

        $cond = $consentField['conditional_rules'];
        $this->assertSame('age_years', $cond['field']);
        $this->assertSame('less_than', $cond['operator']);
        $this->assertEquals(18, $cond['value']);

        // Test compiler validation compilation with minor vs adult
        $compiler = app(FormValidationCompiler::class);

        $minorRules = $compiler->compileRules($form->schema, [
            'date_of_birth' => now()->subYears(16)->format('Y-m-d'),
        ]);
        $this->assertContains('required', $minorRules['parental_consent_path']);

        $adultRules = $compiler->compileRules($form->schema, [
            'date_of_birth' => now()->subYears(22)->format('Y-m-d'),
        ]);
        $this->assertContains('nullable', $adultRules['parental_consent_path']);
    }

    public function test_field_thirty_bkash_txid_enforces_ten_character_uppercase_constraint(): void
    {
        $this->seed(DefaultMembershipFormSeeder::class);

        $form = Form::withoutGlobalScopes()->where('slug', 'membership-application')->firstOrFail();
        $fields = collect($form->schema['fields'])->keyBy('name');

        $bkashField = $fields['bkash_txid'];
        $this->assertSame(FormSchemaEngine::TYPE_TEXT, $bkashField['type']);
        $this->assertTrue($bkashField['required']);
        $this->assertSame(10, $bkashField['min_length']);
        $this->assertSame(10, $bkashField['max_length']);
        $this->assertSame('uppercase', $bkashField['casing']);
        $this->assertSame('^[A-Z0-9]{10}$', $bkashField['regex']);

        $compiler = app(FormValidationCompiler::class);
        $rules = $compiler->compileRules($form->schema);
        $this->assertContains('size:10', $rules['bkash_txid']);
        $this->assertContains('regex:/^[A-Z0-9]{10}$/', $rules['bkash_txid']);
    }

    public function test_database_seeder_executes_default_membership_form_seeder_cleanly(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('forms', [
            'slug' => 'membership-application',
            'workspace_id' => 'ws-emk-dhaka',
            'is_published' => 1,
            'is_default' => 1,
            'version' => '1.0-31f',
        ]);
    }
}
