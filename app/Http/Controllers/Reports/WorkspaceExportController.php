<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\Services\WorkspaceDataExportService;
use App\Domain\Workspaces\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceExportController extends Controller
{
    /**
     * Export members dataset for the given workspace.
     */
    public function members(
        Request $request,
        string|Workspace $workspace,
        WorkspaceDataExportService $exportService
    ): StreamedResponse {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isSuperadmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);
        if (!$isSuperadmin && !$user->can('members.export_csv') && !$user->can('members.export_excel')) {
            App::abort(403, 'Forbidden: members export permission required.');
        }

        $workspaceId = $workspace instanceof Workspace ? (string) $workspace->id : (string) $workspace;

        return $exportService->exportMembers(
            $workspaceId,
            $request->all(),
            (string) $request->query('format', 'csv')
        );
    }

    /**
     * Export leads pipeline dataset for the given workspace.
     */
    public function leads(
        Request $request,
        string|Workspace $workspace,
        WorkspaceDataExportService $exportService
    ): StreamedResponse {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isSuperadmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);
        if (!$isSuperadmin && !$user->can('leads.view')) {
            App::abort(403, 'Forbidden: leads view permission required.');
        }

        $workspaceId = $workspace instanceof Workspace ? (string) $workspace->id : (string) $workspace;

        return $exportService->exportLeads(
            $workspaceId,
            $request->all(),
            (string) $request->query('format', 'csv')
        );
    }

    /**
     * Export invoices billing dataset for the given workspace.
     */
    public function invoices(
        Request $request,
        string|Workspace $workspace,
        WorkspaceDataExportService $exportService
    ): StreamedResponse {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isSuperadmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);
        if (!$isSuperadmin && !$user->can('invoices.export')) {
            App::abort(403, 'Forbidden: invoices export permission required.');
        }

        $workspaceId = $workspace instanceof Workspace ? (string) $workspace->id : (string) $workspace;

        return $exportService->exportInvoices(
            $workspaceId,
            $request->all(),
            (string) $request->query('format', 'csv')
        );
    }

    /**
     * Export operational metrics summary for the given workspace.
     */
    public function metrics(
        Request $request,
        string|Workspace $workspace,
        WorkspaceDataExportService $exportService
    ): StreamedResponse {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isSuperadmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);
        if (!$isSuperadmin && !$user->can('dashboard_reports.export_metrics')) {
            App::abort(403, 'Forbidden: dashboard reports export metrics permission required.');
        }

        $workspaceId = $workspace instanceof Workspace ? (string) $workspace->id : (string) $workspace;

        return $exportService->exportMetrics(
            $workspaceId,
            $request->all()
        );
    }
}
