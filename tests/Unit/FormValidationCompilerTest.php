<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Forms\Services\FormSchemaEngine;
use App\Domain\Forms\Services\FormValidationCompiler;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FormValidationCompilerTest extends TestCase
{
    protected FormValidationCompiler $compiler;
    protected FormSchemaEngine $schemaEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schemaEngine = new FormSchemaEngine();
        $this->compiler = new FormValidationCompiler($this->schemaEngine);
    }

    public function test_compiles_standard_text_date_and_select_validation_rules(): void
    {
        $schema = [
            'fields' => [
                [
                    'name' => 'first_name',
                    'type' => 'text',
                    'required' => true,
                    'min_length' => 2,
                    'max_length' => 50,
                    'regex' => '^[A-Za-z\s\'-]{2,50}$',
                ],
                [
                    'name' => 'email',
                    'type' => 'email',
                    'required' => true,
                ],
                [
                    'name' => 'date_of_birth',
                    'type' => 'date',
                    'required' => true,
                    'validation_rules' => ['before:today', 'after:1900-01-01'],
                ],
                [
                    'name' => 'american_space',
                    'type' => 'select',
                    'required' => true,
                    'dataset' => 'american_spaces',
                ],
            ],
        ];

        $rules = $this->compiler->compileRules($schema);

        $this->assertArrayHasKey('first_name', $rules);
        $this->assertContains('required', $rules['first_name']);
        $this->assertContains('string', $rules['first_name']);
        $this->assertContains('min:2', $rules['first_name']);
        $this->assertContains('max:50', $rules['first_name']);
        $this->assertContains('regex:^[A-Za-z\s\'-]{2,50}$', $rules['first_name']);

        $this->assertArrayHasKey('email', $rules);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('email:rfc', $rules['email']);

        $this->assertArrayHasKey('date_of_birth', $rules);
        $this->assertContains('required', $rules['date_of_birth']);
        $this->assertContains('date', $rules['date_of_birth']);
        $this->assertContains('date_format:Y-m-d', $rules['date_of_birth']);
        $this->assertContains('before:today', $rules['date_of_birth']);
        $this->assertContains('after:1900-01-01', $rules['date_of_birth']);

        $this->assertArrayHasKey('american_space', $rules);
        $this->assertContains('required', $rules['american_space']);
        $this->assertStringStartsWith('in:american_center,emk_center', $rules['american_space'][1]);
    }

    public function test_compiles_cascading_district_rules_restricted_to_selected_division(): void
    {
        $schema = [
            'fields' => [
                [
                    'name' => 'address_division',
                    'type' => 'select',
                    'required' => true,
                    'dataset' => 'bangladesh_divisions',
                ],
                [
                    'name' => 'address_district',
                    'type' => 'cascading_select',
                    'required' => true,
                    'parent_field' => 'address_division',
                ],
            ],
        ];

        // 1. When division is Dhaka: allowed districts must be Dhaka division districts
        $dhakaRules = $this->compiler->compileRules($schema, ['address_division' => 'dhaka']);
        $this->assertArrayHasKey('address_district', $dhakaRules);
        $this->assertContains('required', $dhakaRules['address_district']);
        $dhakaInRule = $dhakaRules['address_district'][1];
        $this->assertStringContainsString('dhaka', $dhakaInRule);
        $this->assertStringContainsString('gazipur', $dhakaInRule);
        $this->assertStringNotContainsString('sylhet', $dhakaInRule);

        // 2. When division is Sylhet: allowed districts must be Sylhet division districts
        $sylhetRules = $this->compiler->compileRules($schema, ['address_division' => 'sylhet']);
        $sylhetInRule = $sylhetRules['address_district'][1];
        $this->assertStringContainsString('sylhet', $sylhetInRule);
        $this->assertStringContainsString('moulvibazar', $sylhetInRule);
        $this->assertStringNotContainsString('gazipur', $sylhetInRule);
    }

    public function test_compiles_conditional_youth_consent_rules_based_on_age(): void
    {
        $schema = [
            'fields' => [
                ['name' => 'date_of_birth', 'type' => 'date', 'required' => true],
                ['name' => 'parental_consent', 'type' => 'file', 'max_kb' => 4096, 'mimes' => 'pdf,jpg,png'],
            ],
        ];

        // Applicant is 16 years old (<18): consent file is REQUIRED
        $minorDob = Carbon::now()->subYears(16)->format('Y-m-d');
        $minorRules = $this->compiler->compileRules($schema, ['date_of_birth' => $minorDob]);
        $this->assertArrayHasKey('parental_consent', $minorRules);
        $this->assertContains('required', $minorRules['parental_consent']);
        $this->assertContains('file', $minorRules['parental_consent']);
        $this->assertContains('max:4096', $minorRules['parental_consent']);

        // Applicant is 25 years old (>=18): consent file is NULLABLE
        $adultDob = Carbon::now()->subYears(25)->format('Y-m-d');
        $adultRules = $this->compiler->compileRules($schema, ['date_of_birth' => $adultDob]);
        $this->assertArrayHasKey('parental_consent', $adultRules);
        $this->assertContains('nullable', $adultRules['parental_consent']);
        $this->assertNotContains('required', $adultRules['parental_consent']);
    }

    public function test_compiles_bkash_txid_validation_rules_per_dec_05(): void
    {
        $schema = [
            'fields' => [
                ['name' => 'bkash_txid', 'type' => 'text', 'required' => true],
            ],
        ];

        $rules = $this->compiler->compileRules($schema);

        $this->assertArrayHasKey('bkash_txid', $rules);
        $this->assertSame([
            'required',
            'string',
            'size:10',
            'regex:/^[A-Z0-9]{10}$/',
        ], $rules['bkash_txid']);
    }

    public function test_compiles_terms_and_conditions_checkbox_rule(): void
    {
        $schema = [
            'fields' => [
                ['name' => 'terms_agreed', 'type' => 'checkbox', 'required' => true],
            ],
        ];

        $rules = $this->compiler->compileRules($schema);
        $this->assertArrayHasKey('terms_agreed', $rules);
        $this->assertSame(['accepted'], $rules['terms_agreed']);
    }

    public function test_compiles_custom_error_messages(): void
    {
        $schema = [
            'fields' => [
                ['name' => 'bkash_txid', 'type' => 'text', 'required' => true],
                ['name' => 'parental_consent', 'type' => 'file', 'required' => true],
                ['name' => 'address_district', 'type' => 'cascading_select', 'required' => true],
                ['name' => 'terms_agreed', 'type' => 'checkbox', 'required' => true],
                ['name' => 'contact_number', 'type' => 'text', 'required' => true],
            ],
        ];

        $messages = $this->compiler->compileMessages($schema);

        $this->assertStringContainsString('valid 10-character uppercase bKash Transaction ID', $messages['bkash_txid.regex']);
        $this->assertStringContainsString('Parental/Guardian consent form is mandatory', $messages['parental_consent.required']);
        $this->assertStringContainsString('valid district for the chosen division', $messages['address_district.in']);
        $this->assertStringContainsString('Membership Terms and Conditions', $messages['terms_agreed.accepted']);
        $this->assertStringContainsString('+8801XXXXXXXXX', $messages['contact_number.regex']);
    }

    public function test_sanitize_payload_trims_whitespace_and_normalizes_bkash_txid(): void
    {
        $schema = [
            'fields' => [
                ['name' => 'first_name', 'type' => 'text'],
                ['name' => 'email', 'type' => 'email'],
                ['name' => 'bkash_txid', 'type' => 'text'],
            ],
        ];

        $rawInput = [
            'first_name' => "  John Doe  \t ",
            'email' => '  APPLICANT@TEST.COM  ',
            'bkash_txid' => '  blk7x9q12m  ',
        ];

        $sanitized = $this->compiler->sanitizePayload($schema, $rawInput);

        $this->assertSame('John Doe', $sanitized['first_name']);
        $this->assertSame('applicant@test.com', $sanitized['email']);
        $this->assertSame('BLK7X9Q12M', $sanitized['bkash_txid']);
    }

    public function test_sanitize_payload_escapes_xss_entities(): void
    {
        $schema = [
            'fields' => [
                ['name' => 'comments', 'type' => 'textarea'],
            ],
        ];

        $rawInput = [
            'comments' => '<script>alert("xss")</script>',
        ];

        $sanitized = $this->compiler->sanitizePayload($schema, $rawInput);

        $this->assertSame('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $sanitized['comments']);
    }
}
