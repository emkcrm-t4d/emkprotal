<?php

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Services\FormSubmissionExportService;
use App\Domain\Leads\Models\Lead;
use App\Domain\Platform\Services\BackupService;
use App\Domain\Workspaces\Models\Workspace;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Invoices\InvoiceReceiptController;
use App\Http\Controllers\Portal\PortalDocumentDownloadController;
use App\Http\Controllers\Reports\WorkspaceExportController;
use App\Livewire\Audit\AuditLogViewer;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\StaffLogin;
use App\Livewire\Forms\FormBuilder;
use App\Livewire\Forms\FormIndex;
use App\Livewire\Forms\PublicMembershipForm;
use App\Livewire\Forms\SubmissionViewer;
use App\Livewire\IdCards\IdCardDirectory;
use App\Livewire\Invoices\InvoiceDirectory;
use App\Livewire\Leads\LeadDirectory;
use App\Livewire\Leads\LeadProfile;
use App\Livewire\Mail\EmailDeliveryMonitor;
use App\Livewire\Mail\EmailTemplateManager;
use App\Livewire\Mail\SmtpRelayManager;
use App\Livewire\Mail\WorkflowRuleManager;
use App\Livewire\Members\ManageTerms;
use App\Livewire\Members\MemberChangeRequestDirectory;
use App\Livewire\Members\MemberDirectory;
use App\Livewire\Members\MemberProfile;
use App\Livewire\Migration\ReconciliationDashboard;
use App\Livewire\Platform\BackupManager;
use App\Livewire\Platform\PlatformSettingsHub;
use App\Livewire\Portal\MemberPortalProfile;
use App\Livewire\Portal\PortalFirstPasswordReset;
use App\Livewire\Portal\PortalLogin;
use App\Livewire\Reports\DataExportCenter;
use App\Livewire\Reports\OperationalDashboard;
use App\Livewire\Staff\RoleDelegation;
use App\Livewire\Staff\StaffDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Health probe tracer endpoints per FOUN-05 and T-03-01
 */
Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/healthz', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'environment' => app()->environment(),
    ]);
});

/**
 * Encrypted Private Document Streaming Route per FOUN-03, DEC-09, and DEC-11
 */
Route::get('/workspaces/{workspace}/documents/{attachment}', [DocumentDownloadController::class, 'download'])
    ->name('documents.download');

Route::get('/login', StaffLogin::class)
    ->name('login')
    ->middleware('guest:web');

Route::match(['get', 'post'], '/logout', function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::get('/dashboard', function () {
    $user = auth()->user();
    if (! $user) {
        return redirect()->route('login');
    }
    $workspace = $user->workspace_id ?: 'ws-emk-dhaka';

    return redirect()->route('crm.dashboard', $workspace);
})->name('dashboard')->middleware('auth:web');

Route::get('/password/reset/{token}', ResetPassword::class)
    ->name('password.reset');

Route::get('/password/reset-required', function () {
    return response('Password reset required', 200);
})->name('password.reset-required');

Route::post('/password/reset-required', function (Request $request) {
    $user = auth()->user();
    if ($user) {
        $user->must_reset_password = false;
        if ($request->filled('password')) {
            $user->password = bcrypt($request->input('password'));
        }
        $user->save();
    }

    return response()->json(['status' => 'password_updated']);
})->name('password.update-required');

/**
 * Phase 4 Canonical Workspace CRM Route Group per AUTH-02, DEC-01, and TENANCY-SPEC.md
 */
Route::prefix('/crm/{workspace}')
    ->middleware(['auth:web', 'workspace.access', 'password.reset'])
    ->group(function () {
        Route::get('/leads', LeadDirectory::class)
            ->name('crm.leads')
            ->middleware(['can:leads.view']);

        Route::get('/leads/{lead}', LeadProfile::class)
            ->name('crm.leads.show')
            ->middleware(['can:leads.view']);

        Route::get('/members', MemberDirectory::class)
            ->name('crm.members')
            ->middleware(['can:members.view']);

        Route::get('/members/change-requests', MemberChangeRequestDirectory::class)
            ->name('crm.members.change_requests')
            ->middleware(['can:members.view']);

        Route::get('/members/{member:uuid}', MemberProfile::class)
            ->name('crm.members.show')
            ->middleware(['can:members.view']);

        Route::get('/invoices', InvoiceDirectory::class)
            ->name('crm.invoices')
            ->middleware(['can:invoices.view']);

        Route::get('/invoices/{invoice}/receipt', [InvoiceReceiptController::class, 'show'])
            ->name('crm.invoices.receipt')
            ->middleware(['can:invoices.view']);

        Route::get('/id-cards', IdCardDirectory::class)
            ->name('crm.id_cards.index')
            ->middleware(['can:members.manage_cards']);

        Route::get('/reports', OperationalDashboard::class)
            ->name('crm.reports')
            ->middleware(['can:dashboard_reports.view_dashboard']);

        Route::get('/dashboard', OperationalDashboard::class)
            ->name('crm.dashboard')
            ->middleware(['can:dashboard_reports.view_dashboard']);

        Route::get('/dashboard_reports', OperationalDashboard::class)
            ->name('crm.dashboard_reports')
            ->middleware(['can:dashboard_reports.view_dashboard']);

        Route::get('/exports/members', [WorkspaceExportController::class, 'members'])->name('crm.exports.members');
        Route::get('/exports/leads', [WorkspaceExportController::class, 'leads'])->name('crm.exports.leads');
        Route::get('/exports/invoices', [WorkspaceExportController::class, 'invoices'])->name('crm.exports.invoices');
        Route::get('/exports/metrics', [WorkspaceExportController::class, 'metrics'])->name('crm.exports.metrics');

        Route::get('/data-export', DataExportCenter::class)
            ->name('crm.data_export.index')
            ->middleware(['can:dashboard_reports.view_dashboard']);

        Route::get('/migration/reconciliation', ReconciliationDashboard::class)
            ->name('crm.migration.reconciliation')
            ->middleware(['can:migration.reconcile_data']);

        Route::get('/forms', FormIndex::class)
            ->name('crm.forms.index');

        Route::get('/forms/{form}/builder', FormBuilder::class)
            ->name('crm.forms.builder');

        Route::get('/forms/{form}/submissions', SubmissionViewer::class)
            ->name('crm.forms.submissions')
            ->middleware(['can:forms.view_entries']);

        Route::get('/forms/{form}/submissions/export', function (
            $workspace,
            $form,
            Request $request,
            FormSubmissionExportService $exportService
        ) {
            $workspaceId = $workspace instanceof Workspace
                ? $workspace->id
                : (string) $workspace;

            if (! ($form instanceof Form)) {
                $form = Form::withoutGlobalScopes()->findOrFail($form);
            }

            return $exportService->export($form, $workspaceId, $request->all());
        })->name('crm.forms.submissions.export')->middleware(['can:forms.view_entries']);

        Route::get('/staff', StaffDirectory::class)
            ->name('crm.staff.index');

        Route::get('/staff/{staff}/delegation', RoleDelegation::class)
            ->name('crm.staff.delegation');

        Route::get('/audit-logs', AuditLogViewer::class)
            ->name('crm.audit_logs.index')
            ->middleware(['can:platform_services.view_audit_logs']);

        Route::get('/settings', PlatformSettingsHub::class)
            ->name('crm.settings.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/platform_services', function ($workspace) {
            $workspaceId = $workspace instanceof Workspace
                ? $workspace->id
                : (string) $workspace;

            return redirect()->route('crm.settings.index', $workspaceId);
        })->name('crm.platform_services');

        Route::get('/settings/terms', ManageTerms::class)
            ->name('crm.terms.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/settings/email-templates', EmailTemplateManager::class)
            ->name('crm.email_templates.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/settings/smtp', SmtpRelayManager::class)
            ->name('crm.smtp.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/settings/email-delivery', EmailDeliveryMonitor::class)
            ->name('crm.email_delivery.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/settings/workflow-rules', WorkflowRuleManager::class)
            ->name('crm.workflow_rules.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/settings/backups', BackupManager::class)
            ->name('crm.backups.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/settings/backups/download/{filename}', function (
            $workspace,
            $filename,
            BackupService $service
        ) {
            $user = auth()->user();
            if (! $user) {
                abort(401, 'Unauthenticated.');
            }

            $isElevatedAdmin = method_exists($user, 'hasRole')
                && $user->hasRole(['superadmin', 'global_admin']);

            $workspaceId = $workspace instanceof Workspace
                ? (string) $workspace->id
                : (string) $workspace;

            if (! $isElevatedAdmin && (string) $user->workspace_id !== $workspaceId) {
                abort(403, 'Unauthorized workspace access.');
            }

            if (! $user->can('platform_services.manage_settings') && ! $isElevatedAdmin) {
                abort(403, 'Forbidden: platform_services.manage_settings permission required.');
            }

            $cleanFilename = basename((string) $filename);
            $backupDir = (string) (config('backup.path') ?: storage_path('app/backups'));
            $filePath = $backupDir.DIRECTORY_SEPARATOR.$cleanFilename;

            if (! file_exists($filePath)) {
                abort(404, 'Backup archive not found.');
            }

            return response()->download($filePath, $cleanFilename, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => "attachment; filename=\"{$cleanFilename}\"",
            ]);
        })->name('crm.backups.download')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/tracer-check', function ($workspace) {
            $user = auth()->user();

            $hasAccess = ($user && method_exists($user, 'can') && $user->can('forms.view'))
                || ($user && method_exists($user, 'hasRole') && $user->hasRole(['workspace_manager', 'superadmin', 'global_admin']));

            if (! $hasAccess) {
                abort(403, 'Forbidden: Insufficient permissions.');
            }

            $workspaceId = $workspace instanceof Workspace
                ? $workspace->id
                : (string) $workspace;

            return response()->json([
                'status' => 'ok',
                'workspace_id' => $workspaceId,
                'records_count' => Lead::count(),
            ]);
        })->name('crm.tracer-check');
    });

/**
 * Public Membership Application Routes per FORM-02, FORM-03, and Task 1
 */
Route::get('/apply', function () {
    return redirect()->route('public.forms.show', 'membership-application');
});

Route::get('/apply/{slug}', PublicMembershipForm::class)
    ->name('public.forms.show');

Route::post('/apply/{slug}/submit', function (string $slug, Request $request) {
    $form = Form::withoutGlobalScopes()
        ->where('slug', $slug)
        ->where('is_published', true)
        ->first();

    if (! $form) {
        abort(404, 'Form not found or unpublished.');
    }

    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'workspace_id' => $form->workspace_id,
        'payload' => $request->all(),
        'status' => 'submitted',
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'submitted_at' => now(),
    ]);

    return response()->json([
        'status' => 'ok',
        'message' => 'Application submitted successfully.',
        'submission_uuid' => $submission->uuid,
    ], 200);
})->name('public.forms.submit');

/**
 * Member Portal Authentication & Entry Routes per PORT-01, DEC-06, and DEC-11
 */
Route::get('/portal/login', PortalLogin::class)
    ->name('portal.login')
    ->middleware('guest:member');

Route::post('/portal/logout', function () {
    Auth::guard('member')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('portal.login');
})->name('portal.logout');

Route::get('/portal', function () {
    return redirect()->route('portal.profile');
});

/**
 * Member Portal First-Login Password Reset Routes per DEC-06 (D-06)
 */
Route::middleware(['auth:member'])->group(function () {
    Route::get('/portal/password/reset', PortalFirstPasswordReset::class)
        ->name('portal.password.reset');
});

/**
 * Protected Member Portal Routes (requires member auth + completed password reset)
 */
Route::prefix('/portal')->middleware(['auth:member', 'member.password.reset'])->group(function () {
    Route::get('/profile', MemberPortalProfile::class)
        ->name('portal.profile');

    Route::get('/documents/{attachment}', [PortalDocumentDownloadController::class, 'download'])
        ->name('portal.documents.download');
});
