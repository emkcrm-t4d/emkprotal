<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Domain\Forms\Models\Form;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class FormIndex extends Component
{
    use ValidatesWorkspaceOnHydration;
    use WithPagination;

    public string $workspace = '';
    public string $search = '';

    public bool $showCreateModal = false;
    public string $newTitle = '';
    public string $newSlug = '';
    public string $newDescription = '';
    public ?string $newWorkspaceId = null;

    public bool $showEmbedModal = false;
    public ?int $selectedFormId = null;
    public string $selectedFormTitle = '';
    public string $embedPermalink = '';
    public string $embedIframe = '';
    public string $embedHtml = '';

    public ?string $statusMessage = null;
    public ?string $errorMessage = null;

    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        if (!($user->hasRole(['superadmin', 'global_admin'])) && $user->workspace_id !== $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        if (!$user->can('forms.view') && !$user->hasRole(['workspace_manager', 'superadmin', 'global_admin'])) {
            App::abort(403, 'Forbidden: Insufficient privileges to view forms.');
        }
    }

    public function hydrate(): void
    {
        $user = Auth::user();
        if ($user && !$user->hasRole(['superadmin', 'global_admin']) && !empty($this->workspace) && $user->workspace_id !== $this->workspace) {
            App::abort(403, 'Unauthorized workspace entity.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can('forms.create') && !$user->hasRole(['workspace_manager', 'superadmin', 'global_admin']))) {
            App::abort(403, 'Forbidden: forms.create required.');
        }

        $this->newTitle = '';
        $this->newSlug = '';
        $this->newDescription = '';
        $this->newWorkspaceId = $this->workspace;
        $this->errorMessage = null;
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function updatedNewTitle(string $value): void
    {
        if (empty($this->newSlug) || Str::slug($this->newTitle) === $this->newSlug) {
            $this->newSlug = Str::slug($value);
        }
    }

    public function createForm(): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can('forms.create') && !$user->hasRole(['workspace_manager', 'superadmin', 'global_admin']))) {
            App::abort(403, 'Forbidden: forms.create required.');
        }

        $this->validate([
            'newTitle' => 'required|string|min:2|max:200',
            'newSlug' => 'required|string|min:2|max:100|regex:/^[a-z0-9\-]+$/|unique:forms,slug',
            'newDescription' => 'nullable|string|max:1000',
        ]);

        $targetWorkspace = ($user->hasRole(['superadmin', 'global_admin']) && !empty($this->newWorkspaceId))
            ? $this->newWorkspaceId
            : $this->workspace;

        $form = Form::create([
            'workspace_id' => $targetWorkspace,
            'title' => $this->newTitle,
            'slug' => Str::slug($this->newSlug),
            'description' => $this->newDescription ?: null,
            'schema' => ['fields' => []],
            'settings' => [],
            'is_published' => false,
            'is_default' => false,
            'version' => '1.0',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->showCreateModal = false;
        $this->statusMessage = "Form '{$form->title}' created successfully.";
        $this->errorMessage = null;
    }

    public function openEmbedModal(int $formId): void
    {
        $form = Form::where('workspace_id', $this->workspace)->findOrFail($formId);

        $this->selectedFormId = $form->id;
        $this->selectedFormTitle = $form->title;

        $permalink = route('public.forms.show', ['slug' => $form->slug]);
        $this->embedPermalink = $permalink;
        $this->embedIframe = '<iframe src="' . $permalink . '" width="100%" height="800" frameborder="0" style="border:none; max-width:100%;"></iframe>';
        $this->embedHtml = '<div class="emk-form-embed" data-slug="' . e($form->slug) . '">' . "\n"
            . '    <iframe src="' . $permalink . '" width="100%" height="800" frameborder="0" style="border:none; width:100%; min-height:800px;"></iframe>' . "\n"
            . '</div>';

        $this->showEmbedModal = true;
    }

    public function closeEmbedModal(): void
    {
        $this->showEmbedModal = false;
        $this->selectedFormId = null;
    }

    public function publish(int $formId): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can('forms.publish') && !$user->hasRole(['superadmin', 'global_admin']))) {
            App::abort(403, 'Forbidden: forms.publish required.');
        }

        $form = Form::where('workspace_id', $this->workspace)->findOrFail($formId);
        $form->update([
            'is_published' => true,
            'updated_by' => $user->id,
        ]);

        $this->statusMessage = "Form '{$form->title}' has been published.";
        $this->errorMessage = null;
    }

    public function archive(int $formId): void
    {
        $user = Auth::user();
        if (!$user || (!$user->can('forms.archive') && !$user->hasRole(['superadmin', 'global_admin']))) {
            App::abort(403, 'Forbidden: forms.archive required.');
        }

        $form = Form::where('workspace_id', $this->workspace)->findOrFail($formId);
        $form->update([
            'is_published' => false,
            'updated_by' => $user->id,
        ]);

        $this->statusMessage = "Form '{$form->title}' has been archived.";
        $this->errorMessage = null;
    }

    public function render(): View
    {
        $query = Form::where('workspace_id', $this->workspace)
            ->withCount('submissions')
            ->orderByDesc('id');

        if (!empty(trim($this->search))) {
            $searchTerm = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('slug', 'like', $searchTerm);
            });
        }

        $forms = $query->paginate(10);

        return view('livewire.forms.form-index', [
            'forms' => $forms,
            'workspace' => $this->workspace,
        ]);
    }
}
