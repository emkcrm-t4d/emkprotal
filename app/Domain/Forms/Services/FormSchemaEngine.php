<?php

declare(strict_types=1);

namespace App\Domain\Forms\Services;

use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;

class FormSchemaEngine
{
    /**
     * Supported field types catalog per FORM-01.
     */
    public const TYPE_TEXT = 'text';
    public const TYPE_EMAIL = 'email';
    public const TYPE_SELECT = 'select';
    public const TYPE_CASCADING_SELECT = 'cascading_select';
    public const TYPE_RADIO = 'radio';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_DATE = 'date';
    public const TYPE_FILE = 'file';
    public const TYPE_MULTI_FILE = 'multi_file';
    public const TYPE_NUMBER = 'number';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_INSTRUCTIONS = 'instructions';
    public const TYPE_SECTION_BREAK = 'section_break';

    public const FIELD_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_EMAIL,
        self::TYPE_SELECT,
        self::TYPE_CASCADING_SELECT,
        self::TYPE_RADIO,
        self::TYPE_CHECKBOX,
        self::TYPE_DATE,
        self::TYPE_FILE,
        self::TYPE_MULTI_FILE,
        self::TYPE_NUMBER,
        self::TYPE_TEXTAREA,
        self::TYPE_INSTRUCTIONS,
        self::TYPE_SECTION_BREAK,
    ];

    /**
     * Permitted conditional evaluation operators (Zero-Eval architecture per DEC-02).
     */
    public const ALLOWED_OPERATORS = [
        'equals',
        '==',
        '=',
        'not_equals',
        '!=',
        '<>',
        'in',
        'not_in',
        'greater_than',
        '>',
        'less_than',
        '<',
        'greater_than_or_equal',
        '>=',
        'less_than_or_equal',
        '<=',
        'age_less_than',
        'age_greater_than_or_equal',
        'is_empty',
        'is_not_empty',
    ];

    /**
     * Maximum allowed fields per form definition to prevent DoS per T-05-02.
     */
    public const MAX_FIELDS = 100;

    /**
     * Administrative divisions of Bangladesh.
     */
    public const BANGLADESH_DIVISIONS = [
        'barishal' => 'Barishal',
        'chattogram' => 'Chattogram',
        'dhaka' => 'Dhaka',
        'khulna' => 'Khulna',
        'mymensingh' => 'Mymensingh',
        'rajshahi' => 'Rajshahi',
        'rangpur' => 'Rangpur',
        'sylhet' => 'Sylhet',
    ];

    /**
     * 64 Districts partitioned by division per FIELD-DICTIONARY.md Section 4.
     */
    public const DIVISION_DISTRICT_MAP = [
        'barishal' => [
            'barguna', 'barishal', 'bhola', 'jhalokati', 'patuakhali', 'pirojpur',
        ],
        'chattogram' => [
            'bandarban', 'brahmanbaria', 'chandpur', 'chattogram', 'coxs_bazar',
            'feni', 'khagrachhari', 'lakshmipur', 'noakhali', 'rangamati', 'cumilla',
        ],
        'dhaka' => [
            'dhaka', 'faridpur', 'gazipur', 'gopalganj', 'kishoreganj',
            'madaripur', 'manikganj', 'munshiganj', 'narayanganj', 'narsingdi',
            'rajbari', 'shariatpur', 'tangail',
        ],
        'khulna' => [
            'bagerhat', 'chuadanga', 'jashore', 'jhenaidah', 'khulna',
            'kushtia', 'magura', 'meherpur', 'narail', 'satkhira',
        ],
        'mymensingh' => [
            'jamalpur', 'mymensingh', 'netrokona', 'sherpur',
        ],
        'rajshahi' => [
            'bogura', 'chapai_nawabganj', 'joypurhat', 'naogaon',
            'natore', 'pabna', 'rajshahi', 'sirajganj',
        ],
        'rangpur' => [
            'dinajpur', 'gaibandha', 'kurigram', 'lalmonirhat',
            'nilphamari', 'panchagarh', 'rangpur', 'thakurgaon',
        ],
        'sylhet' => [
            'habiganj', 'moulvibazar', 'sunamganj', 'sylhet',
        ],
    ];

    /**
     * American Spaces options per FIELD-DICTIONARY.md Section 3.
     */
    public const AMERICAN_SPACES = [
        'american_center' => 'American Center',
        'emk_center' => 'EMK Center',
        'barishal_corner' => 'Barishal Corner',
        'chattogram_corner' => 'Chattogram Corner',
        'rajshahi_corner' => 'Rajshahi Corner',
        'sylhet_corner' => 'Sylhet Corner',
    ];

    /**
     * Validation errors from the most recent validateSchema call.
     *
     * @var array<string>
     */
    protected array $errors = [];

    /**
     * Validate structure of dynamic form schema.
     */
    public function validateSchema(array $schema, bool $throwOnInvalid = false): bool
    {
        $this->errors = [];

        $fields = $this->extractFields($schema);

        if (count($fields) > self::MAX_FIELDS) {
            $this->addError(sprintf('Form exceeds maximum allowed field count of %d.', self::MAX_FIELDS));
        }

        $seenNames = [];

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                $this->addError(sprintf('Field at index %d must be an array.', $index));
                continue;
            }

            $type = $field['type'] ?? null;
            if (!$type || !in_array($type, self::FIELD_TYPES, true)) {
                $this->addError(sprintf('Field at index %d has invalid or missing type [%s].', $index, (string) $type));
                continue;
            }

            $name = $field['name'] ?? null;
            if (empty($name) && !in_array($type, [self::TYPE_SECTION_BREAK, self::TYPE_INSTRUCTIONS], true)) {
                $this->addError(sprintf('Field of type [%s] at index %d requires a name property.', $type, $index));
                continue;
            }

            if (!empty($name)) {
                if (isset($seenNames[$name])) {
                    $this->addError(sprintf('Duplicate field name [%s] detected.', $name));
                }
                $seenNames[$name] = true;
            }

            // Validate conditional rules if present
            if (isset($field['conditional_rules'])) {
                $this->validateConditionalRulesStructure($field['conditional_rules'], $name ?? (string) $index);
            }
        }

        $isValid = count($this->errors) === 0;

        if (!$isValid && $throwOnInvalid) {
            throw new InvalidArgumentException(implode(' ', $this->errors));
        }

        return $isValid;
    }

    /**
     * Normalize field attributes to guarantee consistent schema representation.
     */
    public function normalizeField(array $field): array
    {
        $type = $field['type'] ?? self::TYPE_TEXT;
        $name = $field['name'] ?? '';

        $label = $field['label'] ?? null;
        if ($label === null && !empty($name)) {
            $label = ucwords(str_replace(['_', '-'], ' ', $name));
        }

        return [
            'name' => $name,
            'type' => $type,
            'label' => $label ?? '',
            'required' => (bool) ($field['required'] ?? false),
            'visible' => (bool) ($field['visible'] ?? true),
            'placeholder' => $field['placeholder'] ?? null,
            'help_text' => $field['help_text'] ?? null,
            'default' => $field['default'] ?? null,
            'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
            'dataset' => $field['dataset'] ?? null,
            'parent_field' => $field['parent_field'] ?? null,
            'conditional_rules' => $field['conditional_rules'] ?? [],
            'validation_rules' => $field['validation_rules'] ?? [],
            'casing' => $field['casing'] ?? 'none', // none, uppercase, lowercase, capitalize
            'min_length' => isset($field['min_length']) ? (int) $field['min_length'] : null,
            'max_length' => isset($field['max_length']) ? (int) $field['max_length'] : null,
            'min' => $field['min'] ?? null,
            'max' => $field['max'] ?? null,
            'step' => $field['step'] ?? null,
            'regex' => $field['regex'] ?? null,
            'mimes' => $field['mimes'] ?? null,
            'max_kb' => isset($field['max_kb']) ? (int) $field['max_kb'] : null,
            'min_files' => isset($field['min_files']) ? (int) $field['min_files'] : null,
            'max_files' => isset($field['max_files']) ? (int) $field['max_files'] : null,
            'styling' => $field['styling'] ?? 'info', // info, warning, danger
            'content' => $field['content'] ?? null,
        ];
    }

    /**
     * Evaluate field conditional visibility without dynamic code execution per DEC-02.
     */
    public function evaluateVisibility(array $field, array $formData): bool
    {
        $field = $this->normalizeField($field);
        $rules = $field['conditional_rules'];

        if (empty($rules)) {
            return (bool) ($field['visible'] ?? true);
        }

        // Support single condition object: ['field' => '...', 'operator' => '...', 'value' => '...']
        if (isset($rules['field']) && isset($rules['operator'])) {
            return $this->evaluateSingleCondition($rules, $formData);
        }

        // Support array with logic key: ['logic' => 'or', 'conditions' => [...]]
        $logic = strtolower((string) ($rules['logic'] ?? 'and'));
        $conditions = $rules['conditions'] ?? (isset($rules[0]) ? $rules : []);

        if (empty($conditions)) {
            return (bool) ($field['visible'] ?? true);
        }

        if ($logic === 'or') {
            foreach ($conditions as $condition) {
                if (is_array($condition) && $this->evaluateSingleCondition($condition, $formData)) {
                    return true;
                }
            }
            return false;
        }

        // Default 'and' logic
        foreach ($conditions as $condition) {
            if (is_array($condition) && !$this->evaluateSingleCondition($condition, $formData)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Declarative zero-eval condition evaluator per DEC-02 (D-02).
     */
    public function evaluateCondition(string $operator, mixed $actualValue, mixed $expectedValue): bool
    {
        return match ($operator) {
            'equals', '==', '=' => $this->compareEquals($actualValue, $expectedValue),
            'not_equals', '!=', '<>' => !$this->compareEquals($actualValue, $expectedValue),
            'in' => is_array($expectedValue) ? in_array($actualValue, $expectedValue, false) : false,
            'not_in' => is_array($expectedValue) ? !in_array($actualValue, $expectedValue, false) : true,
            'greater_than', '>' => is_numeric($actualValue) && is_numeric($expectedValue) && ((float) $actualValue > (float) $expectedValue),
            'less_than', '<' => is_numeric($actualValue) && is_numeric($expectedValue) && ((float) $actualValue < (float) $expectedValue),
            'greater_than_or_equal', '>=' => is_numeric($actualValue) && is_numeric($expectedValue) && ((float) $actualValue >= (float) $expectedValue),
            'less_than_or_equal', '<=' => is_numeric($actualValue) && is_numeric($expectedValue) && ((float) $actualValue <= (float) $expectedValue),
            'age_less_than' => $this->evaluateAgeComparison($actualValue, (int) $expectedValue, '<'),
            'age_greater_than_or_equal' => $this->evaluateAgeComparison($actualValue, (int) $expectedValue, '>='),
            'is_empty' => empty($actualValue),
            'is_not_empty' => !empty($actualValue),
            default => false,
        };
    }

    /**
     * Dynamic age calculation formula support without eval().
     */
    public function calculateAge(string|DateTimeInterface|null $dob, ?DateTimeInterface $asOf = null): ?int
    {
        if (empty($dob)) {
            return null;
        }

        try {
            $dobCarbon = $dob instanceof Carbon ? $dob : Carbon::parse($dob);
            $asOfCarbon = $asOf ? ($asOf instanceof Carbon ? $asOf : Carbon::parse($asOf)) : Carbon::now();

            return (int) $dobCarbon->diffInYears($asOfCarbon);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Retrieve static dataset options by dataset key.
     */
    public function getDataset(string $datasetKey, ?string $parentValue = null): array
    {
        return match ($datasetKey) {
            'bangladesh_divisions' => self::BANGLADESH_DIVISIONS,
            'american_spaces' => self::AMERICAN_SPACES,
            'districts' => $parentValue !== null
                ? (self::DIVISION_DISTRICT_MAP[strtolower($parentValue)] ?? [])
                : $this->getAllDistricts(),
            'divisions_districts_map' => self::DIVISION_DISTRICT_MAP,
            default => [],
        };
    }

    /**
     * Get all 64 districts as a flat list.
     */
    public function getAllDistricts(): array
    {
        $all = [];
        foreach (self::DIVISION_DISTRICT_MAP as $districts) {
            foreach ($districts as $district) {
                $all[] = $district;
            }
        }
        return array_unique($all);
    }

    /**
     * Returns full metadata catalog of all 13 field types and elements.
     */
    public function getFieldCatalog(): array
    {
        return [
            self::TYPE_TEXT => [
                'type' => self::TYPE_TEXT,
                'name' => 'Single Line Text',
                'description' => 'Single line text input with casing, length, and regex support.',
                'supported_keys' => ['name', 'label', 'required', 'placeholder', 'casing', 'min_length', 'max_length', 'regex'],
            ],
            self::TYPE_EMAIL => [
                'type' => self::TYPE_EMAIL,
                'name' => 'Email Address',
                'description' => 'RFC/DNS compliant email input with uniqueness validation.',
                'supported_keys' => ['name', 'label', 'required', 'placeholder', 'dns_check', 'unique'],
            ],
            self::TYPE_SELECT => [
                'type' => self::TYPE_SELECT,
                'name' => 'Dropdown Select',
                'description' => 'Single selection dropdown from static options or dynamic datasets.',
                'supported_keys' => ['name', 'label', 'required', 'options', 'dataset'],
            ],
            self::TYPE_CASCADING_SELECT => [
                'type' => self::TYPE_CASCADING_SELECT,
                'name' => 'Cascading Select',
                'description' => 'Child dropdown populated dynamically based on parent field value.',
                'supported_keys' => ['name', 'label', 'required', 'parent_field', 'dataset'],
            ],
            self::TYPE_RADIO => [
                'type' => self::TYPE_RADIO,
                'name' => 'Radio Buttons',
                'description' => 'Single option selection from a visible radio group or boolean toggle.',
                'supported_keys' => ['name', 'label', 'required', 'options'],
            ],
            self::TYPE_CHECKBOX => [
                'type' => self::TYPE_CHECKBOX,
                'name' => 'Checkbox',
                'description' => 'Standalone agreement checkbox or multi-select checkbox group.',
                'supported_keys' => ['name', 'label', 'required', 'options'],
            ],
            self::TYPE_DATE => [
                'type' => self::TYPE_DATE,
                'name' => 'Date Picker',
                'description' => 'Date picker with min/max age constraints and dynamic age computation.',
                'supported_keys' => ['name', 'label', 'required', 'min_date', 'max_date', 'triggers_age'],
            ],
            self::TYPE_FILE => [
                'type' => self::TYPE_FILE,
                'name' => 'Single File Upload',
                'description' => 'Single file upload with mime types, max KB, and dimension constraints.',
                'supported_keys' => ['name', 'label', 'required', 'mimes', 'max_kb', 'min_width', 'min_height'],
            ],
            self::TYPE_MULTI_FILE => [
                'type' => self::TYPE_MULTI_FILE,
                'name' => 'Multiple File Upload',
                'description' => 'Multi-file upload with min/max count, allowed extensions, and max KB.',
                'supported_keys' => ['name', 'label', 'required', 'mimes', 'max_kb', 'min_files', 'max_files'],
            ],
            self::TYPE_NUMBER => [
                'type' => self::TYPE_NUMBER,
                'name' => 'Numeric Input',
                'description' => 'Numeric input with min, max, step, and integer validation.',
                'supported_keys' => ['name', 'label', 'required', 'min', 'max', 'step', 'integer'],
            ],
            self::TYPE_TEXTAREA => [
                'type' => self::TYPE_TEXTAREA,
                'name' => 'Multi-line Textarea',
                'description' => 'Multi-line text input with character counters and max length.',
                'supported_keys' => ['name', 'label', 'required', 'placeholder', 'max_length', 'rows'],
            ],
            self::TYPE_INSTRUCTIONS => [
                'type' => self::TYPE_INSTRUCTIONS,
                'name' => 'Instructions Block',
                'description' => 'Informational notice or callout box with styling attributes.',
                'supported_keys' => ['content', 'styling'],
            ],
            self::TYPE_SECTION_BREAK => [
                'type' => self::TYPE_SECTION_BREAK,
                'name' => 'Section Break',
                'description' => 'Visual divider grouping fields into multi-part form sections.',
                'supported_keys' => ['label', 'description'],
            ],
        ];
    }

    /**
     * Get schema validation errors.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    protected function extractFields(array $schema): array
    {
        if (isset($schema['fields']) && is_array($schema['fields'])) {
            return $schema['fields'];
        }

        return $schema;
    }

    protected function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    protected function validateConditionalRulesStructure(mixed $rules, string $fieldName): void
    {
        if (!is_array($rules)) {
            $this->addError(sprintf('Conditional rules for field [%s] must be an array.', $fieldName));
            return;
        }

        $conditions = isset($rules['conditions']) && is_array($rules['conditions'])
            ? $rules['conditions']
            : (isset($rules['field']) ? [$rules] : $rules);

        foreach ($conditions as $idx => $cond) {
            if (!is_array($cond)) {
                $this->addError(sprintf('Condition [%s] in field [%s] must be an array.', (string) $idx, $fieldName));
                continue;
            }

            $op = $cond['operator'] ?? null;
            if ($op !== null && !in_array($op, self::ALLOWED_OPERATORS, true)) {
                $this->addError(sprintf('Disallowed operator [%s] in field [%s]. Only declarative operators are permitted.', (string) $op, $fieldName));
            }
        }
    }

    protected function evaluateSingleCondition(array $condition, array $formData): bool
    {
        $targetField = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $expectedValue = $condition['value'] ?? null;

        if (empty($targetField)) {
            return true;
        }

        $actualValue = $formData[$targetField] ?? null;

        // Dynamic age derivation support if targetField is age / age_years
        if ($actualValue === null && in_array($targetField, ['age', 'age_years'], true) && !empty($formData['date_of_birth'])) {
            $actualValue = $this->calculateAge($formData['date_of_birth']);
        }

        return $this->evaluateCondition($operator, $actualValue, $expectedValue);
    }

    protected function compareEquals(mixed $a, mixed $b): bool
    {
        if (is_bool($b)) {
            $aBool = filter_var($a, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $aBool === $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return (string) $a === (string) $b;
    }

    protected function evaluateAgeComparison(mixed $dobValue, int $targetAge, string $comparison): bool
    {
        $age = is_numeric($dobValue) ? (int) $dobValue : $this->calculateAge($dobValue);

        if ($age === null) {
            return false;
        }

        return match ($comparison) {
            '<' => $age < $targetAge,
            '<=' => $age <= $targetAge,
            '>' => $age > $targetAge,
            '>=' => $age >= $targetAge,
            default => $age === $targetAge,
        };
    }
}
