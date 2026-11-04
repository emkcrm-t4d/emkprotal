<?php

declare(strict_types=1);

namespace App\Livewire\Invoices;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.admin')]
class InvoiceDirectory extends Component
{
    use ValidatesWorkspaceOnHydration;
    use WithPagination;

    public string $workspace = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    #[Url(as: 'sort', except: 'created_at')]
    public string $sortField = 'created_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDirection = 'desc';

    /**
     * Mount component asserting authentication, workspace isolation, and Spatie permission.
     */
    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        // Assert staff belongs to the route workspace
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // Assert invoices.view permission
        if (!$isElevatedAdmin && !$user->can('invoices.view')) {
            App::abort(403, 'Forbidden: invoices.view permission required.');
        }
    }

    /**
     * Hydration guard ensuring workspace context integrity.
     */
    public function hydrate(): void
    {
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && !$user->hasRole(['superadmin', 'global_admin'])) {
            if (!empty($this->workspace) && (string) $user->workspace_id !== (string) $this->workspace) {
                App::abort(403, 'Unauthorized workspace entity.');
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->sortField = 'created_at';
        $this->sortDirection = 'desc';

        $this->resetPage();
    }

    /**
     * Build base query with current filters.
     */
    private function buildFilteredQuery()
    {
        $user = Auth::user();
        $isElevatedAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        $query = Invoice::with(['member', 'lead']);

        if ($isElevatedAdmin && !empty($this->workspace)) {
            $query->where('workspace_id', $this->workspace);
        }

        // Keyword search across invoice number, payment reference, and member particulars
        if (!empty(trim($this->search))) {
            $term = trim($this->search);
            $searchTerm = '%' . $term . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', $searchTerm)
                    ->orWhere('payment_reference', 'like', $searchTerm)
                    ->orWhereHas('member', function ($mq) use ($searchTerm) {
                        $mq->where('member_id', 'like', $searchTerm)
                            ->orWhere('first_name', 'like', $searchTerm)
                            ->orWhere('middle_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm)
                            ->orWhere('email', 'like', $searchTerm);
                    });
            });
        }

        // Status filter
        if (!empty($this->statusFilter) && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Date range filters (on paid_at or created_at)
        if (!empty($this->dateFrom)) {
            $query->whereDate('paid_at', '>=', $this->dateFrom);
        }
        if (!empty($this->dateTo)) {
            $query->whereDate('paid_at', '<=', $this->dateTo);
        }

        return $query;
    }

    /**
     * Export filtered invoices as CSV with formula sanitization and audit trail per DEC-11 / T-07-08.
     */
    public function exportCsv(): StreamedResponse
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // Assert invoices.export permission per MODULE-REGISTRY.md
        if (!$isElevatedAdmin && !$user->can('invoices.export') && !$user->hasRole('workspace_manager')) {
            App::abort(403, 'Forbidden: invoices.export permission required.');
        }

        $query = $this->buildFilteredQuery()->orderByDesc('created_at');
        $exportCount = (clone $query)->count();
        $filename = "invoices-{$this->workspace}-" . now()->format('Ymd-His') . ".csv";

        // Immutable audit trail event per DEC-11
        AuditLog::create([
            'workspace_id' => $this->workspace,
            'actor_id' => $user->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_INVOICES_EXPORTED,
            'auditable_type' => Invoice::class,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => [
                'filters' => [
                    'search' => $this->search,
                    'status' => $this->statusFilter,
                    'date_from' => $this->dateFrom,
                    'date_to' => $this->dateTo,
                ],
                'exported_rows' => $exportCount,
                'filename' => $filename,
            ],
            'reason' => "Exported {$exportCount} invoices for workspace: {$this->workspace}",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System/Console',
        ]);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Headers
            fputcsv($handle, [
                'Invoice Number',
                'Member ID',
                'Member Name',
                'Member Email',
                'Amount BDT',
                'Payment Method',
                'Payment Reference (bKash TxID)',
                'Status',
                'Paid At',
                'Created At',
            ]);

            $query->chunk(100, function ($invoices) use ($handle) {
                foreach ($invoices as $inv) {
                    $member = $inv->member;
                    $row = [
                        $this->sanitizeCsvCell($inv->invoice_number),
                        $this->sanitizeCsvCell($member?->member_id ?? ''),
                        $this->sanitizeCsvCell($member?->full_name ?? ''),
                        $this->sanitizeCsvCell($member?->email ?? ''),
                        $this->sanitizeCsvCell(number_format((float) $inv->amount_bdt, 2, '.', '')),
                        $this->sanitizeCsvCell($inv->payment_method),
                        $this->sanitizeCsvCell($inv->payment_reference),
                        $this->sanitizeCsvCell($inv->status),
                        $this->sanitizeCsvCell($inv->paid_at ? $inv->paid_at->toIso8601String() : ''),
                        $this->sanitizeCsvCell($inv->created_at ? $inv->created_at->toIso8601String() : ''),
                    ];

                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * Neutralize CSV formula injection attacks by prepending single quote to =, +, -, @ per T-05-10 / T-07-08.
     */
    public function sanitizeCsvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;
        $str = trim($str);

        if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@'], true)) {
            return "'" . $str;
        }

        return $str;
    }

    /**
     * Render the interactive invoices directory.
     */
    public function render(): View
    {
        $user = Auth::user();
        $isElevatedAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        // Metrics base query
        $metricsBase = Invoice::query();
        if ($isElevatedAdmin && !empty($this->workspace)) {
            $metricsBase->where('workspace_id', $this->workspace);
        }

        $metrics = [
            'total' => (clone $metricsBase)->count(),
            'paid' => (clone $metricsBase)->where('status', 'paid')->count(),
            'unpaid' => (clone $metricsBase)->where('status', 'unpaid')->count(),
            'voided' => (clone $metricsBase)->where('status', 'voided')->count(),
            'void_reversed' => (clone $metricsBase)->where('status', 'void_reversed')->count(),
            'total_revenue' => (clone $metricsBase)->where('status', 'paid')->sum('amount_bdt'),
        ];

        // Paginated query
        $query = $this->buildFilteredQuery();

        $validSortFields = ['created_at', 'paid_at', 'invoice_number', 'amount_bdt', 'status'];
        $field = in_array($this->sortField, $validSortFields, true) ? $this->sortField : 'created_at';
        $direction = strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($field, $direction);

        $invoices = $query->paginate(15);

        return view('livewire.invoices.invoice-directory', [
            'invoices' => $invoices,
            'metrics' => $metrics,
            'activeModule' => 'invoices',
        ])->layout('layouts.admin', [
            'activeModule' => 'invoices',
            'title' => 'Billing & Invoices Directory - ' . $this->workspace,
        ]);
    }
}
