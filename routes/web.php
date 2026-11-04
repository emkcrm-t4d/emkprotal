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
 * Phase 4 Identity, Workspace Tenancy, and Authorization Tracer Slice per AUTH-01, AUTH-02, DEC-01, DEC-06
 */
Route::middleware(['auth:web', \App\Http\Middleware\VerifyWorkspaceAccess::class, 'password.reset'])
    ->get('/crm/{workspace}/tracer-check', function ($workspace) {
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

