<div class="space-y-6">
    <!-- Breadcrumbs & Directory Header in 3D Frosted Glass -->
    <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 sm:p-7 shadow-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-white">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-400 mb-1.5">
                <a href="/crm/{{ $workspace }}/invoices" class="hover:text-amber-400 transition-colors">CRM</a>
                <span class="text-slate-600">/</span>
                <span class="text-slate-200 font-semibold">Invoices &amp; Billing</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Billing &amp; Invoice Records</h1>
                <span class="sr-only">{{ $workspace }}</span>
            </div>
        </div>

        <div class="mt-2 sm:mt-0 flex items-center gap-3">
            <button
                type="button"
                wire:click="resetFilters"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/10 hover:bg-white/20 border border-white/10 rounded-full text-xs font-semibold text-slate-200 hover:text-white shadow-xs transition-colors cursor-pointer"
                id="btn-reset-filters"
            >
                <svg class="w-3.5 h-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Reset</span>
            </button>

            @can('invoices.export')
            <button
                type="button"
                wire:click="exportCsv"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-full text-xs font-bold shadow-md shadow-emerald-500/20 transition-all disabled:opacity-50 cursor-pointer"
                id="btn-export-csv"
            >
                <span wire:loading.remove wire:target="exportCsv" class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                </span>
                <span wire:loading wire:target="exportCsv" class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Exporting...
                </span>
            </button>
            @endcan
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Invoices</p>
            <p class="text-2xl font-bold text-slate-900 mt-1" id="metric-total">{{ number_format($metrics['total']) }}</p>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-emerald-600 uppercase tracking-wider">Paid</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1" id="metric-paid">{{ number_format($metrics['paid']) }}</p>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Unpaid</p>
            <p class="text-2xl font-bold text-amber-700 mt-1" id="metric-unpaid">{{ number_format($metrics['unpaid']) }}</p>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-rose-600 uppercase tracking-wider">Voided</p>
            <p class="text-2xl font-bold text-rose-700 mt-1" id="metric-voided">{{ number_format($metrics['voided']) }}</p>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-purple-600 uppercase tracking-wider">Reversed</p>
            <p class="text-2xl font-bold text-purple-700 mt-1" id="metric-reversed">{{ number_format($metrics['void_reversed']) }}</p>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider">Revenue Collected</p>
            <p class="text-2xl font-bold text-indigo-700 mt-1" id="metric-revenue">৳ {{ number_format((float) $metrics['total_revenue'], 2) }}</p>
        </div>
    </div>

    <!-- Multi-Facet Filter Toolbar -->
    <div class="bg-white p-4 rounded-xl shadow-xs border border-slate-200 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Search Keyword Input -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-medium text-slate-600 mb-1">Search Billing Records</label>
                <div class="relative">
                    <input
                        type="text"
                        id="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search Invoice #, bKash TxID, Member ID, or name..."
                        class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Status Dropdown -->
            <div>
                <label for="statusFilter" class="block text-xs font-medium text-slate-600 mb-1">Invoice Status</label>
                <select
                    id="statusFilter"
                    wire:model.live="statusFilter"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="">All Statuses</option>
                    <option value="paid">Paid</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="voided">Voided</option>
                    <option value="void_reversed">Void Reversed</option>
                </select>
            </div>

            <!-- Date Range Filters -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="dateFrom" class="block text-xs font-medium text-slate-600 mb-1">Paid From</label>
                    <input
                        type="date"
                        id="dateFrom"
                        wire:model.live="dateFrom"
                        class="w-full px-2.5 py-2 border border-slate-300 rounded-lg text-xs bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="dateTo" class="block text-xs font-medium text-slate-600 mb-1">Paid To</label>
                    <input
                        type="date"
                        id="dateTo"
                        wire:model.live="dateTo"
                        class="w-full px-2.5 py-2 border border-slate-300 rounded-lg text-xs bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Invoices Data Table -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-white/5 backdrop-blur-md text-slate-200 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th scope="col" class="px-4 py-3.5 cursor-pointer hover:bg-white/10 hover:text-amber-400 transition-colors" wire:click="sortBy('invoice_number')">
                            <div class="flex items-center gap-1.5">
                                <span>Invoice Number</span>
                                @if($sortField === 'invoice_number')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3.5">Member Particulars</th>
                        <th scope="col" class="px-4 py-3.5 cursor-pointer hover:bg-white/10 hover:text-amber-400 transition-colors" wire:click="sortBy('amount_bdt')">
                            <div class="flex items-center gap-1.5">
                                <span>Amount (BDT)</span>
                                @if($sortField === 'amount_bdt')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3.5">bKash Transaction ID</th>
                        <th scope="col" class="px-4 py-3.5 cursor-pointer hover:bg-white/10 hover:text-amber-400 transition-colors" wire:click="sortBy('status')">
                            <div class="flex items-center gap-1.5">
                                <span>Status</span>
                                @if($sortField === 'status')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3.5 cursor-pointer hover:bg-white/10 hover:text-amber-400 transition-colors" wire:click="sortBy('paid_at')">
                            <div class="flex items-center gap-1.5">
                                <span>Payment Date</span>
                                @if($sortField === 'paid_at')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-slate-200" id="invoices-table-body">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-white/5 transition-colors" wire:key="inv-{{ $invoice->id }}">
                            <td class="px-4 py-3 font-mono font-bold text-white whitespace-nowrap">
                                {{ $invoice->invoice_number }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($invoice->member)
                                    <div class="font-bold text-white">{{ $invoice->member->full_name }}</div>
                                    <div class="text-xs font-mono text-slate-300">{{ $invoice->member->member_id }}</div>
                                @else
                                    <span class="text-xs text-slate-400 italic">No Member Associated</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-bold text-amber-300 whitespace-nowrap">
                                ৳ {{ number_format((float) $invoice->amount_bdt, 2) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-950/80 border border-white/20 text-white font-mono text-xs font-semibold shadow-inner">
                                    {{ $invoice->payment_reference }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($invoice->status === 'paid')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 shadow-xs">
                                        Paid
                                    </span>
                                @elseif($invoice->status === 'unpaid')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 shadow-xs">
                                        Unpaid
                                    </span>
                                @elseif($invoice->status === 'voided')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30 shadow-xs">
                                        Voided
                                    </span>
                                @elseif($invoice->status === 'void_reversed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30 shadow-xs">
                                        Void Reversed
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-white/10 text-slate-300 border border-white/15">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-300 text-xs whitespace-nowrap">
                                {{ $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a
                                    href="/crm/{{ $workspace }}/invoices/{{ $invoice->id }}/receipt"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold bg-white text-slate-950 hover:bg-amber-400 hover:text-slate-950 shadow-md shadow-white/10 transition-all cursor-pointer"
                                    title="View Printable Receipt"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Receipt
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                </svg>
                                <p class="mt-2 text-sm font-medium text-slate-900">No invoices found</p>
                                <p class="mt-1 text-xs text-slate-500">No billing records match your search or filter criteria.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div class="px-4 py-3 border-t border-slate-200 bg-slate-50">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
