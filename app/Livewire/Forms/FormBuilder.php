<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Services\FormSchemaEngine;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class FormBuilder extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public Form $form;

    public string $formTitle = '';
    public ?string $formDescription = '';
    public string $version = '1.0';
    public bool $isPublished = false;

    /**
     * In-memory array of form fields.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $fields = [];

    // Field Editor Modal State
    public bool $showFieldModal = false;
    public ?int $editingIndex = null;

    public string $fieldName = '';
    public string $fieldLabel = '';
    public string $fieldType = 'text';
    public bool $fieldRequired = false;
    public bool $fieldVisible = true;
    public ?string $fieldPlaceholder = null;
    public ?string $fieldHelpText = null;
    public string $fieldCasing = 'none';
    public ?int $fieldMinLength = null;
    public ?int $fieldMaxLength = null;
    public ?string $fieldRegex = null;
    public string $fieldOptionsRaw = '';
    public ?string $fieldDataset = null;
    public ?string $fieldParentField = null;
    public ?string $fieldMimes = null;
    public ?int $fieldMaxKb = null;
    public ?int $fieldMinFiles = null;
    public ?int $fieldMaxFiles = null;

    // Conditional Logic State
    public bool $fieldHasConditional = false;
    public ?string $condField = null;
    public string $condOperator = 'equals';
    public ?string $condValue = null;

    // Embed Modal State
    public bool $showEmbedModal = false;
    public string $embedPermalink = '';
    public string $embedIframe = '';
    public string $embedHtml = '';

    // Alert Messages
    public ?string $statusMessage = null;
    public ?string $errorMessage = null;

    public function mount(string $workspace, Form|int|string $form): void
    {
        $this->workspace = $workspace;

        if (!($form instanceof Form)) {
            $form = Form::where('workspace_id', $workspace)->findOrFail($form);
        }
        $this->form = $form;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        if (!($user->hasRole(['superadmin', 'global_admin']))) {
            if ($user->workspace_id !== $this->workspace || (string) $this->form->workspace_id !== $this->workspace) {
                App::abort(403, 'Unauthorized workspace entity.');
            }
        }

        if (!$user->can('forms.edit') && !$user->hasRole(['workspace_manager', 'superadmin', 'global_admin'])) {
            App::abort(403, 'Forbidden: Insufficient privileges to edit forms.');
        }

        $this->formTitle = (string) $this->form->title;
        $this->formDescription = $this->form->description ? (string) $this->form->description : null;
        $this->version = (string) ($this->form->version ?: '1.0');
        $this->isPublished = (bool) $this->form->is_published;

        $schema = $this->form->schema ?? [];
        if (isset($schema['fields']) && is_array($schema['fields'])) {
            $this->fields = $schema['fields'];
        } elseif (is_array($schema)) {
            $this->fields = $schema;
        } else {
            $this->fields = [];
        }
    }

    public function hydrate(): void
    {
        $user = Auth::user();
        if ($user && !$user->hasRole(['superadmin', 'global_admin'])) {
            if (!empty($this->workspace) && $user->workspace_id !== $this->workspace) {
                App::abort(403, 'Unauthorized workspace entity.');
            }
            if ($this->form && (string) $this->form->workspace_id !== (string) $user->workspace_id) {
                App::abort(403, 'Unauthorized workspace entity.');
            }
        }
    }

    public function openAddFieldModal(): void
    {
        $this->editingIndex = null;
        $this->resetFieldEditor();
        $this->showFieldModal = true;
        $this->errorMessage = null;
    }

    public function openEditFieldModal(int $index): void
    {
        if (!isset($this->fields[$index])) {
            return;
        }

        $this->editingIndex = $index;
        $field = $this->fields[$index];

        $this->fieldName = (string) ($field['name'] ?? '');
        $this->fieldLabel = (string) ($field['label'] ?? '');
        $this->fieldType = (string) ($field['type'] ?? 'text');
        $this->fieldRequired = (bool) ($field['required'] ?? false);
        $this->fieldVisible = (bool) ($field['visible'] ?? true);
        $this->fieldPlaceholder = isset($field['placeholder']) ? (string) $field['placeholder'] : null;
        $this->fieldHelpText = isset($field['help_text']) ? (string) $field['help_text'] : null;
        $this->fieldCasing = (string) ($field['casing'] ?? 'none');
        $this->fieldMinLength = isset($field['min_length']) ? (int) $field['min_length'] : null;
        $this->fieldMaxLength = isset($field['max_length']) ? (int) $field['max_length'] : null;
        $this->fieldRegex = isset($field['regex']) ? (string) $field['regex'] : null;
        $this->fieldDataset = isset($field['dataset']) ? (string) $field['dataset'] : null;
        $this->fieldParentField = isset($field['parent_field']) ? (string) $field['parent_field'] : null;
        $this->fieldMimes = isset($field['mimes']) ? (string) $field['mimes'] : null;
        $this->fieldMaxKb = isset($field['max_kb']) ? (int) $field['max_kb'] : null;
        $this->fieldMinFiles = isset($field['min_files']) ? (int) $field['min_files'] : null;
        $this->fieldMaxFiles = isset($field['max_files']) ? (int) $field['max_files'] : null;

        // Populate options
        if (isset($field['options']) && is_array($field['options'])) {
            $lines = [];
            foreach ($field['options'] as $k => $v) {
                $lines[] = "{$k}:{$v}";
            }
            $this->fieldOptionsRaw = implode("\n", $lines);
        } else {
            $this->fieldOptionsRaw = '';
        }

        // Populate conditional rules
        $conds = $field['conditional_rules'] ?? null;
        if (!empty($conds)) {
            $this->fieldHasConditional = true;
            if (isset($conds['field'])) {
                $this->condField = (string) $conds['field'];
                $this->condOperator = (string) ($conds['operator'] ?? 'equals');
                $this->condValue = isset($conds['value']) ? (string) $conds['value'] : null;
            } elseif (isset($conds[0]['field'])) {
                $this->condField = (string) $conds[0]['field'];
                $this->condOperator = (string) ($conds[0]['operator'] ?? 'equals');
                $this->condValue = isset($conds[0]['value']) ? (string) $conds[0]['value'] : null;
            }
        } else {
            $this->fieldHasConditional = false;
            $this->condField = null;
            $this->condOperator = 'equals';
            $this->condValue = null;
        }

        $this->showFieldModal = true;
        $this->errorMessage = null;
    }

    public function closeFieldModal(): void
    {
        $this->showFieldModal = false;
        $this->resetFieldEditor();
    }

    public function updatedFieldLabel(string $value): void
    {
        if ($this->editingIndex === null && (empty($this->fieldName) || Str::slug($this->fieldName, '_') === $this->fieldName)) {
            $this->fieldName = Str::slug($value, '_');
        }
    }

    public function saveField(FormSchemaEngine $engine): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can('forms.edit') && !$user->hasRole(['workspace_manager', 'superadmin', 'global_admin']))) {
            App::abort(403, 'Forbidden: forms.edit required.');
        }

        $isLayoutType = in_array($this->fieldType, [
            FormSchemaEngine::TYPE_SECTION_BREAK,
            FormSchemaEngine::TYPE_INSTRUCTIONS,
        ], true);

        $rules = [
            'fieldType' => 'required|in:' . implode(',', FormSchemaEngine::FIELD_TYPES),
            'fieldLabel' => 'required|string|max:150',
        ];

        if (!$isLayoutType) {
            $rules['fieldName'] = 'required|regex:/^[a-z0-9_]+$/|max:50';
        }

        $this->validate($rules);

        // Check for duplicate field name
        if (!$isLayoutType) {
            foreach ($this->fields as $idx => $existing) {
                if ($this->editingIndex !== null && $this->editingIndex === $idx) {
                    continue;
                }
                if (($existing['name'] ?? null) === $this->fieldName) {
                    $this->addError('fieldName', "Field key '{$this->fieldName}' is already used in this form.");
                    return;
                }
            }
        }

        $fieldData = [
            'name' => $isLayoutType ? ($this->fieldName ?: Str::slug($this->fieldLabel, '_')) : $this->fieldName,
            'label' => $this->fieldLabel,
            'type' => $this->fieldType,
            'required' => (bool) $this->fieldRequired,
            'visible' => (bool) $this->fieldVisible,
            'placeholder' => $this->fieldPlaceholder ?: null,
            'help_text' => $this->fieldHelpText ?: null,
            'casing' => $this->fieldCasing ?: 'none',
            'min_length' => $this->fieldMinLength ? (int) $this->fieldMinLength : null,
            'max_length' => $this->fieldMaxLength ? (int) $this->fieldMaxLength : null,
            'regex' => $this->fieldRegex ?: null,
            'dataset' => $this->fieldDataset ?: null,
            'parent_field' => $this->fieldParentField ?: null,
            'mimes' => $this->fieldMimes ?: null,
            'max_kb' => $this->fieldMaxKb ? (int) $this->fieldMaxKb : null,
            'min_files' => $this->fieldMinFiles ? (int) $this->fieldMinFiles : null,
            'max_files' => $this->fieldMaxFiles ? (int) $this->fieldMaxFiles : null,
        ];

        // Parse options
        if (!empty(trim($this->fieldOptionsRaw))) {
            $lines = preg_split('/[\r\n]+/', trim($this->fieldOptionsRaw));
            $options = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $options[trim($k)] = trim($v);
                } else {
                    $options[Str::slug($line, '_')] = $line;
                }
            }
            $fieldData['options'] = $options;
        } else {
            $fieldData['options'] = [];
        }

        // Parse conditional logic
        if ($this->fieldHasConditional && !empty($this->condField)) {
            $fieldData['conditional_rules'] = [
                'field' => $this->condField,
                'operator' => $this->condOperator,
                'value' => $this->condValue,
            ];
        } else {
            $fieldData['conditional_rules'] = [];
        }

        $normalized = $engine->normalizeField($fieldData);

        if ($this->editingIndex !== null) {
            $this->fields[$this->editingIndex] = $normalized;
        } else {
            $this->fields[] = $normalized;
        }

        $this->showFieldModal = false;
        $this->resetFieldEditor();
        $this->statusMessage = "Field '{$normalized['label']}' updated in schema.";
        $this->errorMessage = null;
    }

    public function deleteField(int $index): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can('forms.edit') && !$user->hasRole(['workspace_manager', 'superadmin', 'global_admin']))) {
            App::abort(403, 'Forbidden: forms.edit required.');
        }

        if (isset($this->fields[$index])) {
            $deletedLabel = $this->fields[$index]['label'] ?? 'Field';
            array_splice($this->fields, $index, 1);
            $this->statusMessage = "Field '{$deletedLabel}' removed from schema.";
            $this->errorMessage = null;
        }
    }

    public function moveFieldUp(int $index): void
    {
        if ($index > 0 && isset($this->fields[$index])) {
            $temp = $this->fields[$index - 1];
            $this->fields[$index - 1] = $this->fields[$index];
            $this->fields[$index] = $temp;
        }
    }

    public function moveFieldDown(int $index): void
    {
        if ($index < count($this->fields) - 1 && isset($this->fields[$index])) {
            $temp = $this->fields[$index + 1];
            $this->fields[$index + 1] = $this->fields[$index];
            $this->fields[$index] = $temp;
        }
    }

    public function saveSchema(FormSchemaEngine $engine): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can('forms.edit') && !$user->hasRole(['workspace_manager', 'superadmin', 'global_admin']))) {
            App::abort(403, 'Forbidden: forms.edit required.');
        }

        $this->validate([
            'formTitle' => 'required|string|min:2|max:200',
            'formDescription' => 'nullable|string|max:1000',
        ]);

        $schemaPayload = ['fields' => $this->fields];

        if (!$engine->validateSchema($schemaPayload)) {
            $this->errorMessage = implode(' ', $engine->getErrors());
            $this->statusMessage = null;
            return;
        }

        $this->form->update([
            'title' => $this->formTitle,
            'description' => $this->formDescription ?: null,
            'schema' => $schemaPayload,
            'updated_by' => $user->id,
        ]);

        $this->errorMessage = null;
        $this->statusMessage = 'Form schema successfully saved.';
    }

    /**
     * Managerial Action: Publish form to live public URL.
     * Non-delegable per MODULE-REGISTRY.md.
     */
    public function publish(): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can('forms.publish') && !$user->hasRole(['superadmin', 'global_admin']))) {
            App::abort(403, 'Forbidden: forms.publish is a managerial action.');
        }

        $this->form->update([
            'is_published' => true,
            'updated_by' => $user->id,
        ]);

        $this->isPublished = true;
        $this->statusMessage = "Form '{$this->form->title}' has been published.";
        $this->errorMessage = null;
    }

    /**
     * Managerial Action: Archive form from public access.
     * Non-delegable per MODULE-REGISTRY.md.
     */
    public function archive(): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can('forms.archive') && !$user->hasRole(['superadmin', 'global_admin']))) {
            App::abort(403, 'Forbidden: forms.archive is a managerial action.');
        }

        $this->form->update([
            'is_published' => false,
            'updated_by' => $user->id,
        ]);

        $this->isPublished = false;
        $this->statusMessage = "Form '{$this->form->title}' has been archived.";
        $this->errorMessage = null;
    }

    public function openEmbedModal(): void
    {
        $permalink = route('public.forms.show', ['slug' => $this->form->slug]);
        $this->embedPermalink = $permalink;
        $this->embedIframe = '<iframe src="' . $permalink . '" width="100%" height="800" frameborder="0" style="border:none; max-width:100%;"></iframe>';
        $this->embedHtml = '<div class="emk-form-embed" data-slug="' . e($this->form->slug) . '">' . "\n"
            . '    <iframe src="' . $permalink . '" width="100%" height="800" frameborder="0" style="border:none; width:100%; min-height:800px;"></iframe>' . "\n"
            . '</div>';

        $this->showEmbedModal = true;
    }

    public function closeEmbedModal(): void
    {
        $this->showEmbedModal = false;
    }

    protected function resetFieldEditor(): void
    {
        $this->fieldName = '';
        $this->fieldLabel = '';
        $this->fieldType = 'text';
        $this->fieldRequired = false;
        $this->fieldVisible = true;
        $this->fieldPlaceholder = null;
        $this->fieldHelpText = null;
        $this->fieldCasing = 'none';
        $this->fieldMinLength = null;
        $this->fieldMaxLength = null;
        $this->fieldRegex = null;
        $this->fieldOptionsRaw = '';
        $this->fieldDataset = null;
        $this->fieldParentField = null;
        $this->fieldMimes = null;
        $this->fieldMaxKb = null;
        $this->fieldMinFiles = null;
        $this->fieldMaxFiles = null;

        $this->fieldHasConditional = false;
        $this->condField = null;
        $this->condOperator = 'equals';
        $this->condValue = null;
        $this->resetValidation();
    }

    public function render(FormSchemaEngine $engine): View
    {
        return view('livewire.forms.form-builder', [
            'catalog' => $engine->getFieldCatalog(),
            'operators' => FormSchemaEngine::ALLOWED_OPERATORS,
            'divisions' => FormSchemaEngine::BANGLADESH_DIVISIONS,
        ]);
    }
}
