<?php

declare(strict_types=1);

namespace App\Livewire\Mail;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Services\EmailTemplateRenderer;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class EmailTemplateManager extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';

    public ?int $editingTemplateId = null;

    public string $name = '';

    public string $subject_en = '';

    public string $body_en = '';

    public string $subject_bn = '';

    public string $body_bn = '';

    public bool $is_active = true;

    /**
     * @var list<string>
     */
    public array $allowed_variables = [];

    public ?int $previewTemplateId = null;

    public string $previewLocale = 'en';

    /**
     * @var array{subject?: string, body_html?: string, body_text?: string}
     */
    public array $previewOutput = [];

    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;
        $this->authorizeAccess();

        if (EmailTemplate::query()->where('workspace_id', $this->workspace)->count() === 0) {
            EmailTemplateSeeder::seedForWorkspace($this->workspace);
        }
    }

    /**
     * Assert managerial authorization and workspace tenancy access per T-08-06.
     */
    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (! $user) {
            App::abort(401, 'Unauthenticated.');
        }

        $canManage = $user->can('platform_services.manage_settings')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (! $canManage) {
            App::abort(403, 'Unauthorized: platform_services.manage_settings permission required.');
        }

        if (! $user->hasRole(['superadmin', 'global_admin']) && (string) $this->workspace !== (string) $user->workspace_id) {
            App::abort(403, 'Unauthorized workspace access.');
        }
    }

    /**
     * Load a workspace email template into the editor form.
     */
    public function editTemplate(int $templateId): void
    {
        $this->authorizeAccess();
        $this->resetValidation();

        $template = EmailTemplate::query()
            ->where('workspace_id', $this->workspace)
            ->findOrFail($templateId);

        $this->editingTemplateId = $template->id;
        $this->name = (string) $template->name;
        $this->subject_en = (string) $template->subject_en;
        $this->body_en = (string) $template->body_en;
        $this->subject_bn = (string) ($template->subject_bn ?? '');
        $this->body_bn = (string) ($template->body_bn ?? '');
        $this->is_active = (bool) $template->is_active;
        $this->allowed_variables = is_array($template->allowed_variables)
            ? $template->allowed_variables
            : EmailTemplateRenderer::ALLOWED_PLACEHOLDERS;
    }

    /**
     * Close the editor panel.
     */
    public function cancelEdit(): void
    {
        $this->editingTemplateId = null;
        $this->resetValidation();
    }

    /**
     * Persist updated bilingual email template copy and record an immutable audit event.
     */
    public function saveTemplate(): void
    {
        $this->authorizeAccess();

        if (! $this->editingTemplateId) {
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject_en' => ['required', 'string', 'max:255'],
            'body_en' => ['required', 'string', 'min:10'],
            'subject_bn' => ['nullable', 'string', 'max:255'],
            'body_bn' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $template = EmailTemplate::query()
            ->where('workspace_id', $this->workspace)
            ->findOrFail($this->editingTemplateId);

        $oldValues = [
            'name' => $template->name,
            'subject_en' => $template->subject_en,
            'body_en' => $template->body_en,
            'subject_bn' => $template->subject_bn,
            'body_bn' => $template->body_bn,
            'is_active' => (bool) $template->is_active,
        ];

        $cleanBodyEn = $this->stripDangerousTags($this->body_en);
        $cleanBodyBn = $this->stripDangerousTags($this->body_bn);

        $user = Auth::user();

        $template->update([
            'name' => trim($this->name),
            'subject_en' => trim($this->subject_en),
            'body_en' => $cleanBodyEn,
            'subject_bn' => trim($this->subject_bn) !== '' ? trim($this->subject_bn) : null,
            'body_bn' => $cleanBodyBn !== '' ? $cleanBodyBn : null,
            'is_active' => (bool) $this->is_active,
            'updated_by_user_id' => $user?->id,
        ]);

        $newValues = [
            'name' => $template->name,
            'subject_en' => $template->subject_en,
            'body_en' => $template->body_en,
            'subject_bn' => $template->subject_bn,
            'body_bn' => $template->body_bn,
            'is_active' => (bool) $template->is_active,
        ];

        AuditLog::create([
            'workspace_id' => $this->workspace,
            'actor_id' => $user?->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_EMAIL_TEMPLATE_UPDATED,
            'auditable_type' => EmailTemplate::class,
            'auditable_id' => $template->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => "Updated lifecycle email template [{$template->template_key}].",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'Livewire/EmailTemplateManager',
            'created_at' => now(),
        ]);

        $this->editingTemplateId = null;
        session()->flash('status', 'Email template updated successfully.');
    }

    /**
     * Open live preview modal and render sample variables for the given locale ('en', 'bn', or 'bilingual').
     */
    public function openPreview(int $templateId, string $locale = 'en'): void
    {
        $this->authorizeAccess();

        $template = EmailTemplate::query()
            ->where('workspace_id', $this->workspace)
            ->findOrFail($templateId);

        $validLocale = in_array($locale, ['en', 'bn', 'bilingual'], true) ? $locale : 'en';

        $renderer = app(EmailTemplateRenderer::class);

        $this->previewTemplateId = $template->id;
        $this->previewLocale = $validLocale;
        $this->previewOutput = $renderer->render($template, $renderer->sampleVariables(), $validLocale);
    }

    /**
     * Close the live preview modal.
     */
    public function closePreview(): void
    {
        $this->previewTemplateId = null;
        $this->previewOutput = [];
    }

    /**
     * Strip <script> tags per T-08-05.
     */
    private function stripDangerousTags(string $html): string
    {
        $cleaned = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html) ?? $html;

        return trim($cleaned);
    }

    public function render(): View
    {
        $templates = EmailTemplate::query()
            ->where('workspace_id', $this->workspace)
            ->orderBy('id', 'asc')
            ->get();

        return view('livewire.mail.email-template-manager', [
            'templates' => $templates,
            'allPlaceholders' => EmailTemplateRenderer::ALLOWED_PLACEHOLDERS,
        ]);
    }
}
