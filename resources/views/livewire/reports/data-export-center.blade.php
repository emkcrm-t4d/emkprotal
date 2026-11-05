<div class="space-y-6" x-data x-on:start-export-download.window="window.location.href = $event.detail.url">
    <!-- Header & Security Notice -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        Workspace Scoped
                    </span>
                    <span class="text-xs font-mono text-slate-500">{{ $workspaceId }}</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">Data Export Center</h1>
                <p class="text-sm text-slate-500 mt-0.5">
                    Extract filtered operational records, pipelines, and financial ledgers to CSV with memory-protected streaming.
                </p>
            </div>
            <div>
                <a
                    href="{{ route('crm.reports', ['workspace' => $workspaceId]) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 shadow-2xs"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Back to Operational Dashboard</span>
                </a>
            </div>
        </div>

        <!-- DEC-11 Immutable Audit Trail Notice Banner -->
        <div class="mt-5 rounded-lg bg-indigo-50 border border-indigo-200 p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <div class="flex-1 text-xs text-indigo-900 leading-relaxed">
                <p class="font-semibold text-indigo-950">Security Compliance Notice (DEC-11 / D-11)</p>
                <p class="mt-0.5 text-indigo-800">
                    All export operations are logged to the immutable system audit trail per DEC-11. Every download records your user identity, IP address, timestamp, active filters, and exported row count. Dynamic formula characters (<code class="bg-indigo-100 text-indigo-900 px-1 py-0.5 rounded font-mono">= + - @</code>) are neutralized to prevent spreadsheet injection attacks per T-10-04.
                </p>
            </div>
        </div>
    </div>

    <!-- Export Configuration Form Card -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="p-6 space-y-6">
            <!-- Step 1: Select Entity Dataset -->
            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-2">1. Select Dataset to Export</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- Members Option -->
                    <label
                        class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all {{ $entity === 'members' ? 'border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white' }}"
                    >
                        <input
                            type="radio"
                            name="entity"
                            value="members"
                            wire:model.live="entity"
                            class="sr-only"
                        />
                        <div class="flex items-center justify-between mb-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg {{ $entity === 'members' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </span>
                            @if ($entity === 'members')
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                            @endif
                        </div>
                        <span class="text-sm font-bold text-slate-900">Member Master</span>
                        <span class="text-xs text-slate-500 mt-1">Active, inactive, and revoked member directory</span>
                    </label>

                    <!-- Leads Option -->
                    <label
                        class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all {{ $entity === 'leads' ? 'border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white' }}"
                    >
                        <input
                            type="radio"
                            name="entity"
                            value="leads"
                            wire:model.live="entity"
                            class="sr-only"
                        />
                        <div class="flex items-center justify-between mb-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg {{ $entity === 'leads' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </span>
                            @if ($entity === 'leads')
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                            @endif
                        </div>
                        <span class="text-sm font-bold text-slate-900">Lead Pipeline</span>
                        <span class="text-xs text-slate-500 mt-1">Applicant triage, bKash TxIDs, and verification states</span>
                    </label>

                    <!-- Invoices Option -->
                    <label
                        class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all {{ $entity === 'invoices' ? 'border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white' }}"
                    >
                        <input
                            type="radio"
                            name="entity"
                            value="invoices"
                            wire:model.live="entity"
                            class="sr-only"
                        />
                        <div class="flex items-center justify-between mb-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg {{ $entity === 'invoices' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </span>
                            @if ($entity === 'invoices')
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                            @endif
                        </div>
                        <span class="text-sm font-bold text-slate-900">Invoices & Billing</span>
                        <span class="text-xs text-slate-500 mt-1">Membership fee receipts and revenue ledger</span>
                    </label>

                    <!-- Metrics Option -->
                    <label
                        class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all {{ $entity === 'metrics' ? 'border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white' }}"
                    >
                        <input
                            type="radio"
                            name="entity"
                            value="metrics"
                            wire:model.live="entity"
                            class="sr-only"
                        />
                        <div class="flex items-center justify-between mb-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg {{ $entity === 'metrics' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </span>
                            @if ($entity === 'metrics')
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                            @endif
                        </div>
                        <span class="text-sm font-bold text-slate-900">Operational Metrics</span>
                        <span class="text-xs text-slate-500 mt-1">Aggregated KPIs, conversion ratios, and breakdown stats</span>
                    </label>
                </div>
            </div>

            <!-- Step 2: Filter Parameters -->
            <div class="border-t border-slate-100 pt-6">
                <label class="block text-sm font-semibold text-slate-800 mb-3">2. Filter & Refine Dataset</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Dynamic Status Selector -->
                    <div>
                        <label for="status-filter" class="block text-xs font-medium text-slate-700 mb-1">Status Filter</label>
                        <select
                            id="status-filter"
                            wire:model.live="statusFilter"
                            class="w-full text-sm rounded-lg border-slate-300 py-2 px-3 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            @foreach ($statusOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Search Filter -->
                    <div class="md:col-span-2">
                        <label for="search-filter" class="block text-xs font-medium text-slate-700 mb-1">Search Keywords (Optional)</label>
                        <div class="relative">
                            <input
                                type="text"
                                id="search-filter"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search by name, email, member/lead ID, contact number..."
                                class="w-full text-sm rounded-lg border-slate-300 py-2 pl-9 pr-3 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Date Range & Presets -->
                <div class="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                        <span class="text-xs font-semibold text-slate-700">Creation / Submission Date Range:</span>
                        <div class="flex flex-wrap items-center gap-1">
                            @foreach ([
                                'all' => 'All Time',
                                'today' => 'Today',
                                '7_days' => 'Last 7 Days',
                                '30_days' => 'Last 30 Days',
                                '90_days' => 'Last 90 Days',
                                'this_month' => 'This Month',
                                'this_year' => 'This Year'
                            ] as $pKey => $pLabel)
                                <button
                                    type="button"
                                    wire:click="applyDatePreset('{{ $pKey }}')"
                                    class="px-2 py-1 text-xs font-medium rounded-md transition-colors {{ $datePreset === $pKey ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100' }}"
                                >
                                    {{ $pLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="date-from" class="block text-xs font-medium text-slate-600 mb-1">From Date:</label>
                            <input
                                type="date"
                                id="date-from"
                                wire:model.live="dateFrom"
                                class="w-full text-xs rounded-md border-slate-300 py-1.5 px-3 bg-white shadow-2xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="date-to" class="block text-xs font-medium text-slate-600 mb-1">To Date:</label>
                            <input
                                type="date"
                                id="date-to"
                                wire:model.live="dateTo"
                                class="w-full text-xs rounded-md border-slate-300 py-1.5 px-3 bg-white shadow-2xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Format & Export CTA -->
            <div class="border-t border-slate-100 pt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <!-- Live Counter Badge -->
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-slate-100 rounded-xl border border-slate-200">
                        <p class="text-xs font-medium text-slate-500">Records matching criteria:</p>
                        <p class="text-2xl font-black text-slate-900 tracking-tight" wire:loading.class="opacity-50">
                            {{ number_format($estimatedCount) }}
                        </p>
                    </div>
                    <div class="text-xs text-slate-500">
                        <span class="font-medium text-slate-700">Format:</span> RFC 4180 CSV (Excel UTF-8 BOM)<br>
                        <span class="font-medium text-slate-700">Engine:</span> Symfony StreamedResponse (Chunked 200)
                    </div>
                </div>

                <!-- Action Button -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="triggerExport"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center gap-2 px-6 py-3 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all disabled:opacity-50"
                    >
                        <svg wire:loading.remove wire:target="triggerExport" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <svg wire:loading wire:target="triggerExport" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Start Streaming Export</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

