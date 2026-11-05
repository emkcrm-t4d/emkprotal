<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Invoices\InvoiceReceiptController;
use App\Http\Controllers\Portal\PortalDocumentDownloadController;
use App\Http\Controllers\Reports\WorkspaceExportController;
use App\Livewire\Portal\MemberPortalProfile;
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

Route::get('/login', function () {
    return response('Login required', 200);
})->name('login');

Route::get('/password/reset-required', function () {
    return response('Password reset required', 200);
})->name('password.reset-required');

Route::post('/password/reset-required', function (\Illuminate\Http\Request $request) {
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
        Route::get('/leads', \App\Livewire\Leads\LeadDirectory::class)
            ->name('crm.leads')
            ->middleware(['can:leads.view']);

        Route::get('/leads/{lead}', \App\Livewire\Leads\LeadProfile::class)
            ->name('crm.leads.show')
            ->middleware(['can:leads.view']);

        Route::get('/members', \App\Livewire\Members\MemberDirectory::class)
            ->name('crm.members')
            ->middleware(['can:members.view']);

        Route::get('/members/change-requests', \App\Livewire\Members\MemberChangeRequestDirectory::class)
            ->name('crm.members.change_requests')
            ->middleware(['can:members.view']);

        Route::get('/members/{member:uuid}', \App\Livewire\Members\MemberProfile::class)
            ->name('crm.members.show')
            ->middleware(['can:members.view']);

        Route::get('/invoices', \App\Livewire\Invoices\InvoiceDirectory::class)
            ->name('crm.invoices')
            ->middleware(['can:invoices.view']);

        Route::get('/invoices/{invoice}/receipt', [InvoiceReceiptController::class, 'show'])
            ->name('crm.invoices.receipt')
            ->middleware(['can:invoices.view']);

        Route::get('/id-cards', \App\Livewire\IdCards\IdCardDirectory::class)
            ->name('crm.id_cards.index')
            ->middleware(['can:members.manage_cards']);

        Route::get('/reports', \App\Livewire\Reports\OperationalDashboard::class)
            ->name('crm.reports')
            ->middleware(['can:dashboard_reports.view_dashboard']);

        Route::get('/dashboard', \App\Livewire\Reports\OperationalDashboard::class)
            ->name('crm.dashboard')
            ->middleware(['can:dashboard_reports.view_dashboard']);

        Route::get('/dashboard_reports', \App\Livewire\Reports\OperationalDashboard::class)
            ->name('crm.dashboard_reports')
            ->middleware(['can:dashboard_reports.view_dashboard']);

        Route::get('/exports/members', [WorkspaceExportController::class, 'members'])->name('crm.exports.members');
        Route::get('/exports/leads', [WorkspaceExportController::class, 'leads'])->name('crm.exports.leads');
        Route::get('/exports/invoices', [WorkspaceExportController::class, 'invoices'])->name('crm.exports.invoices');
        Route::get('/exports/metrics', [WorkspaceExportController::class, 'metrics'])->name('crm.exports.metrics');

        Route::get('/forms', \App\Livewire\Forms\FormIndex::class)
            ->name('crm.forms.index');

        Route::get('/forms/{form}/builder', \App\Livewire\Forms\FormBuilder::class)
            ->name('crm.forms.builder');

        Route::get('/forms/{form}/submissions', \App\Livewire\Forms\SubmissionViewer::class)
            ->name('crm.forms.submissions')
            ->middleware(['can:forms.view_entries']);

        Route::get('/forms/{form}/submissions/export', function (
            $workspace,
            $form,
            \Illuminate\Http\Request $request,
            \App\Domain\Forms\Services\FormSubmissionExportService $exportService
        ) {
            $workspaceId = $workspace instanceof \App\Domain\Workspaces\Models\Workspace
                ? $workspace->id
                : (string) $workspace;

            if (!($form instanceof \App\Domain\Forms\Models\Form)) {
                $form = \App\Domain\Forms\Models\Form::withoutGlobalScopes()->findOrFail($form);
            }

            return $exportService->export($form, $workspaceId, $request->all());
        })->name('crm.forms.submissions.export')->middleware(['can:forms.view_entries']);

        Route::get('/staff', \App\Livewire\Staff\StaffDirectory::class)
            ->name('crm.staff.index');

        Route::get('/staff/{staff}/delegation', \App\Livewire\Staff\RoleDelegation::class)
            ->name('crm.staff.delegation');

        Route::get('/audit-logs', \App\Livewire\Audit\AuditLogViewer::class)
            ->name('crm.audit_logs.index')
            ->middleware(['can:platform_services.view_audit_logs']);

        Route::get('/settings/terms', \App\Livewire\Members\ManageTerms::class)
            ->name('crm.terms.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/settings/email-templates', \App\Livewire\Mail\EmailTemplateManager::class)
            ->name('crm.email_templates.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/settings/email-delivery', \App\Livewire\Mail\EmailDeliveryMonitor::class)
            ->name('crm.email_delivery.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/settings/workflow-rules', \App\Livewire\Mail\WorkflowRuleManager::class)
            ->name('crm.workflow_rules.index')
            ->middleware(['can:platform_services.manage_settings']);

        Route::get('/tracer-check', function ($workspace) {
            $user = auth()->user();

            $hasAccess = ($user && method_exists($user, 'can') && $user->can('forms.view'))
                || ($user && method_exists($user, 'hasRole') && $user->hasRole(['workspace_manager', 'superadmin', 'global_admin']));

            if (!$hasAccess) {
                abort(403, 'Forbidden: Insufficient permissions.');
            }

            $workspaceId = $workspace instanceof \App\Domain\Workspaces\Models\Workspace
                ? $workspace->id
                : (string) $workspace;

            return response()->json([
                'status' => 'ok',
                'workspace_id' => $workspaceId,
                'records_count' => \App\Domain\Leads\Models\Lead::count(),
            ]);
        })->name('crm.tracer-check');
    });

/**
 * Public Membership Application Routes per FORM-02, FORM-03, and Task 1
 */
Route::get('/apply', function () {
    return redirect()->route('public.forms.show', 'membership-application');
});

Route::get('/apply/{slug}', \App\Livewire\Forms\PublicMembershipForm::class)
    ->name('public.forms.show');

Route::post('/apply/{slug}/submit', function (string $slug, \Illuminate\Http\Request $request) {
    $form = \App\Domain\Forms\Models\Form::withoutGlobalScopes()
        ->where('slug', $slug)
        ->where('is_published', true)
        ->first();

    if (!$form) {
        abort(404, 'Form not found or unpublished.');
    }

    $submission = \App\Domain\Forms\Models\FormSubmission::create([
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
Route::get('/portal/login', \App\Livewire\Portal\PortalLogin::class)
    ->name('portal.login')
    ->middleware('guest:member');

Route::post('/portal/logout', function () {
    \Illuminate\Support\Facades\Auth::guard('member')->logout();
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
    Route::get('/portal/password/reset', \App\Livewire\Portal\PortalFirstPasswordReset::class)
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



