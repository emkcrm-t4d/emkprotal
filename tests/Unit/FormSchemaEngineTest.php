<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Forms\Services\FormSchemaEngine;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FormSchemaEngineTest extends TestCase
{
    protected FormSchemaEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FormSchemaEngine();
    }

    public function test_get_field_catalog_contains_all_thirteen_field_types(): void
    {
        $catalog = $this->engine->getFieldCatalog();

        $this->assertCount(13, $catalog);
        $this->assertArrayHasKey('text', $catalog);
        $this->assertArrayHasKey('email', $catalog);
        $this->assertArrayHasKey('select', $catalog);
        $this->assertArrayHasKey('cascading_select', $catalog);
        $this->assertArrayHasKey('radio', $catalog);
        $this->assertArrayHasKey('checkbox', $catalog);
        $this->assertArrayHasKey('date', $catalog);
        $this->assertArrayHasKey('file', $catalog);
        $this->assertArrayHasKey('multi_file', $catalog);
        $this->assertArrayHasKey('number', $catalog);
        $this->assertArrayHasKey('textarea', $catalog);
        $this->assertArrayHasKey('instructions', $catalog);
        $this->assertArrayHasKey('section_break', $catalog);
    }

    public function test_normalize_field_applies_defaults_correctly(): void
    {
        $rawTextField = [
            'name' => 'first_name',
            'type' => 'text',
        ];

        $normalized = $this->engine->normalizeField($rawTextField);

        $this->assertSame('first_name', $normalized['name']);
        $this->assertSame('text', $normalized['type']);
        $this->assertSame('First Name', $normalized['label']);
        $this->assertFalse($normalized['required']);
        $this->assertTrue($normalized['visible']);
        $this->assertSame([], $normalized['options']);
        $this->assertSame('none', $normalized['casing']);

        $rawConsentField = [
            'name' => 'parental_consent',
            'type' => 'file',
            'required' => true,
            'max_kb' => 4096,
            'mimes' => 'pdf,jpg,png',
        ];

        $normalizedConsent = $this->engine->normalizeField($rawConsentField);
        $this->assertTrue($normalizedConsent['required']);
        $this->assertSame(4096, $normalizedConsent['max_kb']);
        $this->assertSame('pdf,jpg,png', $normalizedConsent['mimes']);
    }

    public function test_validate_schema_accepts_valid_schema(): void
    {
        $validSchema = [
            'fields' => [
                ['name' => 'first_name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'division', 'type' => 'select', 'dataset' => 'bangladesh_divisions'],
                ['name' => 'district', 'type' => 'cascading_select', 'parent_field' => 'division'],
                ['name' => 'terms_agreed', 'type' => 'checkbox', 'required' => true],
            ],
        ];

        $this->assertTrue($this->engine->validateSchema($validSchema));
        $this->assertEmpty($this->engine->getErrors());
    }

    public function test_validate_schema_rejects_duplicate_field_names(): void
    {
        $invalidSchema = [
            'fields' => [
                ['name' => 'contact_number', 'type' => 'text'],
                ['name' => 'contact_number', 'type' => 'text'],
            ],
        ];

        $this->assertFalse($this->engine->validateSchema($invalidSchema));
        $errors = $this->engine->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Duplicate field name [contact_number]', $errors[0]);
    }

    public function test_validate_schema_rejects_unsupported_field_type(): void
    {
        $invalidSchema = [
            'fields' => [
                ['name' => 'custom_widget', 'type' => 'unknown_eval_widget'],
            ],
        ];

        $this->assertFalse($this->engine->validateSchema($invalidSchema));
        $errors = $this->engine->getErrors();
        $this->assertStringContainsString('invalid or missing type [unknown_eval_widget]', $errors[0]);
    }

    public function test_validate_schema_rejects_more_than_one_hundred_fields(): void
    {
        $fields = [];
        for ($i = 1; $i <= 101; $i++) {
            $fields[] = ['name' => "field_{$i}", 'type' => 'text'];
        }

        $this->assertFalse($this->engine->validateSchema(['fields' => $fields]));
        $errors = $this->engine->getErrors();
        $this->assertStringContainsString('exceeds maximum allowed field count of 100', $errors[0]);
    }

    public function test_validate_schema_rejects_disallowed_conditional_operators(): void
    {
        $schemaWithCodeInjection = [
            'fields' => [
                [
                    'name' => 'dangerous_field',
                    'type' => 'text',
                    'conditional_rules' => [
                        'field' => 'status',
                        'operator' => 'system("ls")',
                        'value' => 'active',
                    ],
                ],
            ],
        ];

        $this->assertFalse($this->engine->validateSchema($schemaWithCodeInjection));
        $errors = $this->engine->getErrors();
        $this->assertStringContainsString('Disallowed operator [system("ls")]', $errors[0]);
    }

    public function test_evaluate_visibility_for_under_eighteen_youth_consent(): void
    {
        $consentField = [
            'name' => 'parental_consent',
            'type' => 'file',
            'conditional_rules' => [
                'field' => 'age_years',
                'operator' => 'less_than',
                'value' => 18,
            ],
        ];

        // 16 years old: youth consent MUST be visible
        $under18Dob = Carbon::now()->subYears(16)->format('Y-m-d');
        $visibleForMinor = $this->engine->evaluateVisibility($consentField, [
            'date_of_birth' => $under18Dob,
        ]);
        $this->assertTrue($visibleForMinor);

        // 22 years old: youth consent MUST be hidden
        $adultDob = Carbon::now()->subYears(22)->format('Y-m-d');
        $visibleForAdult = $this->engine->evaluateVisibility($consentField, [
            'date_of_birth' => $adultDob,
        ]);
        $this->assertFalse($visibleForAdult);
    }

    public function test_evaluate_visibility_for_boolean_radio_switches(): void
    {
        $usLocationField = [
            'name' => 'us_experience_location',
            'type' => 'text',
            'conditional_rules' => [
                'field' => 'has_us_experience',
                'operator' => 'equals',
                'value' => true,
            ],
        ];

        $this->assertTrue($this->engine->evaluateVisibility($usLocationField, ['has_us_experience' => '1']));
        $this->assertTrue($this->engine->evaluateVisibility($usLocationField, ['has_us_experience' => true]));
        $this->assertFalse($this->engine->evaluateVisibility($usLocationField, ['has_us_experience' => '0']));
        $this->assertFalse($this->engine->evaluateVisibility($usLocationField, ['has_us_experience' => false]));
        $this->assertFalse($this->engine->evaluateVisibility($usLocationField, []));
    }

    public function test_evaluate_visibility_with_multiple_conditions_and_or_logic(): void
    {
        $specialField = [
            'name' => 'special_notes',
            'type' => 'text',
            'conditional_rules' => [
                'logic' => 'or',
                'conditions' => [
                    ['field' => 'occupation', 'operator' => 'equals', 'value' => 'student'],
                    ['field' => 'occupation', 'operator' => 'equals', 'value' => 'educator'],
                ],
            ],
        ];

        $this->assertTrue($this->engine->evaluateVisibility($specialField, ['occupation' => 'student']));
        $this->assertTrue($this->engine->evaluateVisibility($specialField, ['occupation' => 'educator']));
        $this->assertFalse($this->engine->evaluateVisibility($specialField, ['occupation' => 'entrepreneur']));
    }

    public function test_calculate_age_accurately_derives_years_from_dob(): void
    {
        $dob = Carbon::now()->subYears(25)->subDays(10)->format('Y-m-d');
        $this->assertSame(25, $this->engine->calculateAge($dob));

        $this->assertNull($this->engine->calculateAge(null));
        $this->assertNull($this->engine->calculateAge('invalid-date-string'));
    }

    public function test_get_dataset_resolves_bangladesh_divisions_and_cascading_districts(): void
    {
        $divisions = $this->engine->getDataset('bangladesh_divisions');
        $this->assertCount(8, $divisions);
        $this->assertArrayHasKey('dhaka', $divisions);
        $this->assertArrayHasKey('sylhet', $divisions);

        $dhakaDistricts = $this->engine->getDataset('districts', 'dhaka');
        $this->assertCount(13, $dhakaDistricts);
        $this->assertContains('dhaka', $dhakaDistricts);
        $this->assertContains('gazipur', $dhakaDistricts);

        $sylhetDistricts = $this->engine->getDataset('districts', 'sylhet');
        $this->assertCount(4, $sylhetDistricts);
        $this->assertContains('sylhet', $sylhetDistricts);
        $this->assertContains('moulvibazar', $sylhetDistricts);

        $allDistricts = $this->engine->getAllDistricts();
        $this->assertCount(64, $allDistricts);

        $spaces = $this->engine->getDataset('american_spaces');
        $this->assertCount(6, $spaces);
        $this->assertArrayHasKey('emk_center', $spaces);
    }

    public function test_zero_eval_invariant_never_executes_php_strings(): void
    {
        $flag = false;
        $injectedValue = function () use (&$flag) {
            $flag = true;
            return true;
        };

        // Pass callable or tricky string to condition
        $result = $this->engine->evaluateCondition('equals', 'malicious_input', '$injectedValue()');
        $this->assertFalse($result);
        $this->assertFalse($flag);
    }
}
