<div class="space-y-6">
    <!-- Header Card -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $report->isReconciled() ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' }}">
                        {{ $report->isReconciled() ? '100% Reconciled' : 'Discrepancies Found' }}
                    </span>
                    <span class="text-xs font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                        {{ $workspaceId }}
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">
                    Zoho Migration Reconciliation & Data Fidelity
                </h1>
                <p class="text-sm text-slate-500 mt-0.5">
                    Automated discrepancy detection and verification against legacy Zoho forms and records per MIGR-04.
                </p>
            </div>

            <!-- Controls: Batch Selector & Export Actions -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-auto">
                    <select
                        wire:model.live="batchId"
                        class="w-full sm:w-auto text-xs rounded-lg border-slate-300 bg-white py-2 pl-3 pr-8 font-medium text-slate-700 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All Batches (Workspace Audit)</option>
                        @foreach ($batches as $b)
                            <option value="{{ $b->id }}">
                                {{ $b->source_filename }} ({{ $b->created_at->format('M d, Y') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <button
                    type="button"
                    wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 shadow-xs transition-colors"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                </button>

                <button
                    type="button"
                    wire:click="exportHtml"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow-xs transition-colors"
                >
                    <svg class="w-4 h-4 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export HTML
                </button>
            </div>
        </div>
    </div>

    <!-- Status Banner -->
    @if ($report->isReconciled())
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <h3 class="text-sm font-semibold text-emerald-800">100% Data Fidelity Validated</h3>
                <p class="text-xs text-emerald-700 mt-0.5">
                    Zero discrepancies detected between source Zoho staging datasets and imported Hub records across counts, unique emails, active statuses, dates, and attachments.
                </p>
            </div>
        </div>
    @else
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <h3 class="text-sm font-semibold text-rose-800">Discrepancies Detected ({{ $report->discrepancyCount }})</h3>
                <p class="text-xs text-rose-700 mt-0.5">
                    Data variance identified between Zoho staging payloads and database entities. Inspect the discrepancy ledger below for remediation details.
                </p>
            </div>
        </div>
    @endif

    <!-- KPI Metric Cards Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <!-- Match Percentage -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Match Percentage</div>
            <div class="mt-2 text-2xl font-bold {{ $report->matchPercentage() >= 100.0 ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ number_format($report->matchPercentage(), 2) }}%
            </div>
            <div class="mt-1 text-xs text-slate-400">Target: 100.00%</div>
        </div>

        <!-- Source Records -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Source Records</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">
                {{ $report->sourceRecordCount }}
            </div>
            <div class="mt-1 text-xs text-slate-400">Total in staging</div>
        </div>

        <!-- Imported Records -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Imported Records</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">
                {{ $report->importedRecordCount }}
            </div>
            <div class="mt-1 text-xs text-slate-400">Resolved in DB</div>
        </div>

        <!-- Total Discrepancies -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Discrepancies</div>
            <div class="mt-2 text-2xl font-bold {{ $report->discrepancyCount > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                {{ $report->discrepancyCount }}
            </div>
            <div class="mt-1 text-xs text-slate-400">Flagged variances</div>
        </div>

        <!-- Date Divergences -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Date Mismatches</div>
            <div class="mt-2 text-2xl font-bold {{ $report->dateMismatchCount > 0 ? 'text-amber-600' : 'text-slate-900' }}">
                {{ $report->dateMismatchCount }}
            </div>
            <div class="mt-1 text-xs text-slate-400">DOB / Start / Expiry</div>
        </div>

        <!-- Missing Attachments -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Missing Attachments</div>
            <div class="mt-2 text-2xl font-bold {{ $report->missingAttachments > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                {{ $report->missingAttachments }}
            </div>
            <div class="mt-1 text-xs text-slate-400">Missing from storage</div>
        </div>
    </div>

    <!-- Discrepancy Ledger Section -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Discrepancy Audit Ledger</h2>
                <p class="text-xs text-slate-500 mt-0.5">Filterable itemized list of inconsistencies detected across Zoho datasets.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Search Zoho ID -->
                <div class="w-full sm:w-64">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchZohoId"
                        placeholder="Search Zoho ID or Message..."
                        class="w-full text-xs rounded-lg border-slate-300 py-2 px-3 text-slate-700 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <!-- Severity Filters -->
                <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                    <button
                        type="button"
                        wire:click="$set('activeFilter', 'all')"
                        class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors {{ $activeFilter === 'all' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900' }}"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeFilter', 'critical')"
                        class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors {{ $activeFilter === 'critical' ? 'bg-rose-600 text-white shadow-xs font-semibold' : 'text-rose-700 hover:text-rose-900' }}"
                    >
                        Critical
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeFilter', 'warning')"
                        class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors {{ $activeFilter === 'warning' ? 'bg-amber-500 text-white shadow-xs font-semibold' : 'text-amber-700 hover:text-amber-900' }}"
                    >
                        Warning
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeFilter', 'info')"
                        class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors {{ $activeFilter === 'info' ? 'bg-indigo-600 text-white shadow-xs font-semibold' : 'text-indigo-700 hover:text-indigo-900' }}"
                    >
                        Info
                    </button>
                </div>
            </div>
        </div>

        <!-- Discrepancy Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4">Zoho ID</th>
                        <th class="py-3 px-4">Entity</th>
                        <th class="py-3 px-4">Field</th>
                        <th class="py-3 px-4">Expected Value</th>
                        <th class="py-3 px-4">Actual Value</th>
                        <th class="py-3 px-4">Severity</th>
                        <th class="py-3 px-4">Message</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-slate-700">
                    @forelse ($discrepancies as $d)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-mono font-medium text-slate-900">
                                {{ $d['zoho_id'] ?? '—' }}
                            </td>
                            <td class="py-3 px-4 capitalize">
                                {{ $d['entity'] ?? '—' }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-900">
                                {{ $d['field'] ?? '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-emerald-700">
                                {{ is_array($d['expected'] ?? null) ? json_encode($d['expected']) : ($d['expected'] ?? '—') }}
                            </td>
                            <td class="py-3 px-4 font-mono text-rose-700">
                                {{ is_array($d['actual'] ?? null) ? json_encode($d['actual']) : ($d['actual'] ?? '—') }}
                            </td>
                            <td class="py-3 px-4">
                                @php
                                    $badgeClass = match ($d['severity'] ?? 'info') {
                                        'critical' => 'bg-rose-100 text-rose-800 border-rose-300',
                                        'warning' => 'bg-amber-100 text-amber-800 border-amber-300',
                                        default => 'bg-indigo-100 text-indigo-800 border-indigo-300',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-bold uppercase border {{ $badgeClass }}">
                                    {{ $d['severity'] ?? 'info' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-600 max-w-xs truncate">
                                {{ $d['message'] ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm font-medium text-slate-700">No discrepancies found</p>
                                <p class="text-xs text-slate-400 mt-0.5">All evaluated records meet data fidelity standards.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($discrepancies->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $discrepancies->links() }}
            </div>
        @endif
    </div>
</div>

