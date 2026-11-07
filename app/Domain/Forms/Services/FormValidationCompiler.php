<?php

declare(strict_types=1);

namespace App\Domain\Forms\Services;

class FormValidationCompiler
{
    public function __construct(
        protected ?FormSchemaEngine $schemaEngine = null
    ) {
        $this->schemaEngine = $schemaEngine ?? new FormSchemaEngine();
    }

    /**
     * Compile dynamic form schema into Laravel validation rule arrays.
     */
    public function compileRules(array $schema, array $inputData = []): array
    {
        $fields = $this->extractFields($schema);
        $rules = [];

        foreach ($fields as $rawField) {
            if (!is_array($rawField)) {
                continue;
            }

            $field = $this->schemaEngine->normalizeField($rawField);
            $type = $field['type'];
            $name = $field['name'];

            // Skip layout elements
            if (in_array($type, [FormSchemaEngine::TYPE_INSTRUCTIONS, FormSchemaEngine::TYPE_SECTION_BREAK], true) || empty($name)) {
                continue;
            }

            $fieldRules = $this->compileSingleFieldRules($field, $inputData);
            if (!empty($fieldRules)) {
                $rules[$name] = $fieldRules;
            }

            // Multi-file wildcard item rules
            if ($type === FormSchemaEngine::TYPE_MULTI_FILE) {
                $itemRules = ['file'];
                if (!empty($field['mimes'])) {
                    $itemRules[] = 'mimes:' . $field['mimes'];
                }
                if (!empty($field['max_kb'])) {
                    $itemRules[] = 'max:' . $field['max_kb'];
                }
                $rules["{$name}.*"] = $itemRules;
            }
        }

        return $rules;
    }

    /**
     * Compile human-friendly custom error messages per FORM-01, DEC-05, and DEC-09.
     */
    public function compileMessages(array $schema): array
    {
        $fields = $this->extractFields($schema);
        $messages = [];

        foreach ($fields as $rawField) {
            if (!is_array($rawField)) {
                continue;
            }

            $field = $this->schemaEngine->normalizeField($rawField);
            $name = $field['name'];
            $label = $field['label'];

            if (empty($name)) {
                continue;
            }

            // Default required message
            $messages["{$name}.required"] = "The {$label} field is required.";

            // bKash Transaction ID custom messages per DEC-05
            if ($name === 'bkash_txid') {
                $messages["{$name}.regex"] = 'Please enter a valid 10-character uppercase bKash Transaction ID (e.g., BLK7X9Q12M).';
                $messages["{$name}.size"] = 'Please enter a valid 10-character uppercase bKash Transaction ID (e.g., BLK7X9Q12M).';
            }

            // Parental / Guardian consent custom messages per DEC-09
            if (in_array($name, ['parental_consent', 'parental_consent_path'], true)) {
                $messages["{$name}.required"] = 'Parental/Guardian consent form is mandatory for applicants under 18 years of age.';
                $messages["{$name}.required_if"] = 'Parental/Guardian consent form is mandatory for applicants under 18 years of age.';
            }

            // Cascading district message per FIELD-DICTIONARY.md Section 4
            if (in_array($name, ['district', 'address_district'], true)) {
                $messages["{$name}.in"] = 'Please select a valid district for the chosen division.';
            }

            // Terms agreement message
            if (in_array($name, ['terms_agreed', 'terms_and_conditions'], true)) {
                $messages["{$name}.accepted"] = 'You must read and agree to the Membership Terms and Conditions to submit your application.';
                $messages["{$name}.required"] = 'You must read and agree to the Membership Terms and Conditions to submit your application.';
            }

            // Contact number Bangladeshi regex message
            if (in_array($name, ['contact_number', 'phone'], true)) {
                $messages["{$name}.regex"] = 'Please enter a valid Bangladeshi mobile number in format +8801XXXXXXXXX.';
            }
        }

        return $messages;
    }

    /**
     * Sanitize submitted input payload against XSS, null bytes, and format inconsistencies.
     */
    public function sanitizePayload(array $schema, array $inputData, bool $escapeHtml = true): array
    {
        $fields = $this->extractFields($schema);
        $fieldMap = [];

        foreach ($fields as $rawField) {
            if (is_array($rawField) && !empty($rawField['name'])) {
                $fieldMap[$rawField['name']] = $this->schemaEngine->normalizeField($rawField);
            }
        }

        $sanitized = [];

        foreach ($inputData as $key => $value) {
            $field = $fieldMap[$key] ?? null;
            $sanitized[$key] = $this->sanitizeValue($value, $field, $escapeHtml);
        }

        return $sanitized;
    }

    protected function compileSingleFieldRules(array $field, array $inputData): array
    {
        $type = $field['type'];
        $name = $field['name'];
        $rules = [];

        // Check for bKash Transaction ID special handling per DEC-05
        if ($name === 'bkash_txid') {
            return ['required', 'string', 'size:10', 'regex:/^[A-Z0-9]{10}$/'];
        }

        // Check for conditional youth consent upload per DEC-09
        if (in_array($name, ['parental_consent', 'parental_consent_path'], true)) {
            return $this->compileYouthConsentRules($field, $inputData);
        }

        // Required vs Nullable determination
        if ($type === FormSchemaEngine::TYPE_CHECKBOX && empty($field['options'])) {
            // Standalone agreement checkbox
            return $field['required'] ? ['accepted'] : ['nullable', 'boolean'];
        }

        if ($field['required']) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        switch ($type) {
            case FormSchemaEngine::TYPE_TEXT:
                $rules[] = 'string';
                if (!empty($field['min_length'])) {
                    $rules[] = 'min:' . $field['min_length'];
                }
                if (!empty($field['max_length'])) {
                    $rules[] = 'max:' . $field['max_length'];
                }
                if (!empty($field['regex'])) {
                    $rules[] = 'regex:' . $field['regex'];
                }
                break;

            case FormSchemaEngine::TYPE_EMAIL:
                $rules[] = 'string';
                $rules[] = 'email:rfc';
                $rules[] = 'max:' . ($field['max_length'] ?? 150);
                break;

            case FormSchemaEngine::TYPE_SELECT:
            case FormSchemaEngine::TYPE_RADIO:
                $allowed = $this->resolveSelectOptions($field);
                if (!empty($allowed)) {
                    $rules[] = 'in:' . implode(',', $allowed);
                } else {
                    $rules[] = 'string';
                }
                break;

            case FormSchemaEngine::TYPE_CASCADING_SELECT:
                $allowedDistricts = $this->resolveCascadingDistricts($field, $inputData);
                if (!empty($allowedDistricts)) {
                    $rules[] = 'in:' . implode(',', $allowedDistricts);
                } else {
                    $rules[] = 'string';
                }
                break;

            case FormSchemaEngine::TYPE_CHECKBOX:
                // Multi-select checkbox group
                $rules[] = 'array';
                break;

            case FormSchemaEngine::TYPE_DATE:
                $rules[] = 'date';
                $rules[] = 'date_format:Y-m-d';
                if (!empty($field['validation_rules'])) {
                    foreach ($field['validation_rules'] as $vRule) {
                        if (is_string($vRule) && (str_starts_with($vRule, 'before:') || str_starts_with($vRule, 'after:'))) {
                            $rules[] = $vRule;
                        }
                    }
                }
                break;

            case FormSchemaEngine::TYPE_FILE:
                $rules[] = 'file';
                if (!empty($field['mimes'])) {
                    $rules[] = 'mimes:' . $field['mimes'];
                }
                if (!empty($field['max_kb'])) {
                    $rules[] = 'max:' . $field['max_kb'];
                }
                break;

            case FormSchemaEngine::TYPE_MULTI_FILE:
                $rules[] = 'array';
                if (!empty($field['min_files'])) {
                    $rules[] = 'min:' . $field['min_files'];
                }
                if (!empty($field['max_files'])) {
                    $rules[] = 'max:' . $field['max_files'];
                }
                break;

            case FormSchemaEngine::TYPE_NUMBER:
                $rules[] = 'numeric';
                if ($field['min'] !== null) {
                    $rules[] = 'min:' . $field['min'];
                }
                if ($field['max'] !== null) {
                    $rules[] = 'max:' . $field['max'];
                }
                break;

            case FormSchemaEngine::TYPE_TEXTAREA:
                $rules[] = 'string';
                if (!empty($field['max_length'])) {
                    $rules[] = 'max:' . $field['max_length'];
                }
                break;
        }

        return $rules;
    }

    protected function compileYouthConsentRules(array $field, array $inputData): array
    {
        $rules = [];

        // Check if date_of_birth is present in submitted input
        $dob = $inputData['date_of_birth'] ?? null;
        $age = $this->schemaEngine->calculateAge($dob);

        if ($age !== null) {
            if ($age < 18) {
                $rules[] = 'required';
            } else {
                $rules[] = 'nullable';
            }
        } else {
            // DOB not yet available in input: enforce required_if or fallback to nullable
            $rules[] = 'nullable';
        }

        $rules[] = 'file';
        $rules[] = 'mimes:' . ($field['mimes'] ?? 'pdf,jpg,jpeg,png');
        $rules[] = 'max:' . ($field['max_kb'] ?? 4096);

        return $rules;
    }

    protected function resolveSelectOptions(array $field): array
    {
        if (!empty($field['options'])) {
            return $this->extractOptionKeys($field['options']);
        }

        $dataset = $field['dataset'] ?? null;
        if ($dataset) {
            $data = $this->schemaEngine->getDataset($dataset);
            return array_keys($data);
        }

        return [];
    }

    protected function resolveCascadingDistricts(array $field, array $inputData): array
    {
        $parentField = $field['parent_field'] ?? 'address_division';
        $selectedDivision = $inputData[$parentField] ?? ($inputData['division'] ?? null);

        if ($selectedDivision !== null && is_string($selectedDivision)) {
            $districts = $this->schemaEngine->getDataset('districts', strtolower($selectedDivision));
            if (!empty($districts)) {
                return $districts;
            }
        }

        return $this->schemaEngine->getAllDistricts();
    }

    protected function extractOptionKeys(array $options): array
    {
        $keys = [];
        foreach ($options as $key => $opt) {
            if (is_array($opt) && isset($opt['value'])) {
                $keys[] = (string) $opt['value'];
            } elseif (is_string($key) && !is_numeric($key)) {
                $keys[] = $key;
            } elseif (is_string($opt) || is_numeric($opt)) {
                $keys[] = (string) $opt;
            }
        }
        return $keys;
    }

    protected function sanitizeValue(mixed $value, ?array $field, bool $escapeHtml): mixed
    {
        if (is_array($value)) {
            $cleanArray = [];
            foreach ($value as $k => $v) {
                $cleanArray[$k] = $this->sanitizeValue($v, null, $escapeHtml);
            }
            return $cleanArray;
        }

        if (!is_string($value)) {
            return $value;
        }

        // 1. Ensure UTF-8 validity
        $clean = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        // 2. Strip dangerous control characters (0-8, 11-12, 14-31, 127) while preserving \t, \n, \r
        $clean = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean);

        // 3. Trim leading and trailing whitespace
        $clean = trim($clean);

        $name = $field['name'] ?? null;
        $casing = $field['casing'] ?? 'none';

        // 4. bKash TxID special auto-uppercase normalization and TrxID prefix stripping per DEC-05
        if ($name === 'bkash_txid') {
            return \App\Domain\Payments\Adapters\ManualBkashPaymentAdapter::extractTxid($clean);
        }

        if ($casing === 'uppercase') {
            $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $clean));
            return $clean;
        }

        if ($casing === 'lowercase' || ($field['type'] ?? null) === FormSchemaEngine::TYPE_EMAIL) {
            $clean = strtolower($clean);
        } elseif ($casing === 'capitalize') {
            $clean = ucwords($clean);
        }

        // 5. HTML entity escaping against XSS per SECURITY-SPEC.md
        if ($escapeHtml) {
            $clean = htmlspecialchars($clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $clean;
    }

    protected function extractFields(array $schema): array
    {
        if (isset($schema['fields']) && is_array($schema['fields'])) {
            return $schema['fields'];
        }

        return $schema;
    }
}
