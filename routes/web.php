<?php

use App\Http\Controllers\DocumentDownloadController;
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
        Route::get('/leads', function ($workspace) {
            $workspaceId = $workspace instanceof \App\Domain\Workspaces\Models\Workspace
                ? $workspace->id
                : (string) $workspace;

            return response()->json([
                'status' => 'ok',
                'workspace' => $workspaceId,
                'module' => 'leads',
            ]);
        })->name('crm.leads');

        Route::get('/members', function ($workspace) {
            $workspaceId = $workspace instanceof \App\Domain\Workspaces\Models\Workspace
                ? $workspace->id
                : (string) $workspace;

            return response()->json([
                'status' => 'ok',
                'workspace' => $workspaceId,
                'module' => 'members',
            ]);
        })->name('crm.members');

        Route::get('/invoices', function ($workspace) {
            $workspaceId = $workspace instanceof \App\Domain\Workspaces\Models\Workspace
                ? $workspace->id
                : (string) $workspace;

            return response()->json([
                'status' => 'ok',
                'workspace' => $workspaceId,
                'module' => 'invoices',
            ]);
        })->name('crm.invoices');

        Route::get('/reports', function ($workspace) {
            $workspaceId = $workspace instanceof \App\Domain\Workspaces\Models\Workspace
                ? $workspace->id
                : (string) $workspace;

            return response()->json([
                'status' => 'ok',
                'workspace' => $workspaceId,
                'module' => 'reports',
            ]);
        })->name('crm.reports');

        Route::get('/staff', \App\Livewire\Staff\StaffDirectory::class)
            ->name('crm.staff.index');

        Route::get('/staff/{staff}/delegation', \App\Livewire\Staff\RoleDelegation::class)
            ->name('crm.staff.delegation');

        Route::get('/audit-logs', \App\Livewire\Audit\AuditLogViewer::class)
            ->name('crm.audit_logs.index')
            ->middleware(['can:platform_services.view_audit_logs']);

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
 * Public Form Tracer Routes per FORM-01, DEC-01, and DEC-02
 */
Route::get('/apply/{slug}', function (string $slug, \Illuminate\Http\Request $request) {
    $form = \App\Domain\Forms\Models\Form::withoutGlobalScopes()
        ->where('slug', $slug)
        ->where('is_published', true)
        ->first();

    if (!$form) {
        abort(404, 'Form not found or unpublished.');
    }

    if ($request->wantsJson()) {
        return response()->json([
            'status' => 'ok',
            'form' => [
                'uuid' => $form->uuid,
                'slug' => $form->slug,
                'title' => $form->title,
                'description' => $form->description,
                'schema' => $form->schema,
                'settings' => $form->settings,
            ],
        ]);
    }

    return response('<!DOCTYPE html><html><head><title>' . e($form->title) . '</title></head><body><h1>' . e($form->title) . '</h1><p>' . e($form->description ?? '') . '</p></body></html>', 200)
        ->header('Content-Type', 'text/html');
})->name('public.forms.show');

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


