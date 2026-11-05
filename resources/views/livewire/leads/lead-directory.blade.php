<div class="space-y-6">
    <!-- Breadcrumbs & Directory Header with Glass Blur View -->
    <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 sm:p-7 shadow-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-white">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-400 mb-1.5">
                <a href="/crm/{{ $workspace }}/leads" class="hover:text-amber-400 transition-colors">CRM</a>
                <span class="text-slate-600">/</span>
                <span class="text-slate-200 font-semibold">Leads Directory</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Inbound Leads & Applications</h1>
                <span class="sr-only">{{ $workspace }}</span>
            </div>
        </div>

        <div class="mt-2 sm:mt-0 flex items-center gap-3">
            <button
                type="button"
                wire:click="resetFilters"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/10 hover:bg-white/20 border border-white/10 rounded-xl text-xs font-semibold text-slate-200 hover:text-white shadow-xs transition-colors cursor-pointer"
                id="btn-reset-filters-top"
            >
                <svg class="w-3.5 h-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Reset Filters</span>
            </button>
        </div>
    </div>

    <!-- Summary Metrics Cards in 3D Frosted Glass -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-white/10 shadow-lg">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Leads</p>
            <p class="text-2xl font-black text-white mt-1">{{ number_format($metrics['total']) }}</p>
        </div>

        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-blue-500/20 shadow-lg">
            <p class="text-xs font-semibold text-blue-400 uppercase tracking-wider">Submitted</p>
            <p class="text-2xl font-black text-blue-300 mt-1">{{ number_format($metrics['submitted']) }}</p>
        </div>

        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-indigo-500/20 shadow-lg">
            <p class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">Under Review</p>
            <p class="text-2xl font-black text-indigo-300 mt-1">{{ number_format($metrics['under_review']) }}</p>
        </div>

        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-amber-500/20 shadow-lg">
            <p class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Missing Items</p>
            <p class="text-2xl font-black text-amber-300 mt-1">{{ number_format($metrics['missing_items']) }}</p>
        </div>

        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-emerald-500/20 shadow-lg">
            <p class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Approved</p>
            <p class="text-2xl font-black text-emerald-300 mt-1">{{ number_format($metrics['approved']) }}</p>
        </div>

        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-purple-500/20 shadow-lg">
            <p class="text-xs font-semibold text-purple-400 uppercase tracking-wider">Converted</p>
            <p class="text-2xl font-black text-purple-300 mt-1">{{ number_format($metrics['converted']) }}</p>
        </div>
    </div>

    <!-- Multi-Facet Filter Toolbar -->
    <div class="bg-white p-4 rounded-xl shadow-xs border border-slate-200 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Search Keyword Input -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-medium text-slate-600 mb-1">Search Leads</label>
                <div class="relative">
                    <input
                        type="text"
                        id="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search applicant name, email, phone, or bKash TxID..."
                        class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 pl-9"
                    />
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Lead Lifecycle Status Filter -->
            <div>
                <label for="statusFilter" class="block text-xs font-medium text-slate-600 mb-1">Lead Status</label>
                <select
                    id="statusFilter"
                    wire:model.live="statusFilter"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">All Statuses</option>
                    <option value="submitted">Submitted</option>
                    <option value="under_review">Under Review</option>
                    <option value="missing_items">Missing Items</option>
                    <option value="approved">Approved</option>
                    <option value="converted">Converted</option>
                    <option value="rejected">Rejected</option>
                    <option value="blocked">Blocked</option>
                </select>
            </div>

            <!-- Payment Stream Filter -->
            <div>
                <label for="paymentFilter" class="block text-xs font-medium text-slate-600 mb-1">Payment Stream (P)</label>
                <select
                    id="paymentFilter"
                    wire:model.live="paymentFilter"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">All Payments</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2 border-t border-slate-100">
            <!-- Document Stream Filter -->
            <div>
                <label for="documentFilter" class="block text-xs font-medium text-slate-600 mb-1">Document Stream (D)</label>
                <select
                    id="documentFilter"
                    wire:model.live="documentFilter"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">All Documents</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="verified">Verified</option>
                    <option value="missing_items">Missing Items</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <!-- Photo Stream Filter -->
            <div>
                <label for="photoFilter" class="block text-xs font-medium text-slate-600 mb-1">Photo Stream (Ph)</label>
                <select
                    id="photoFilter"
                    wire:model.live="photoFilter"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">All Photos</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="verified">Verified</option>
                    <option value="reupload_requested">Reupload Requested</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label for="dateFrom" class="block text-xs font-medium text-slate-600 mb-1">Submitted From</label>
                <input
                    type="date"
                    id="dateFrom"
                    wire:model.live="dateFrom"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <!-- Date To -->
            <div>
                <label for="dateTo" class="block text-xs font-medium text-slate-600 mb-1">Submitted To</label>
                <input
                    type="date"
                    id="dateTo"
                    wire:model.live="dateTo"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
        </div>
    </div>

    <!-- Leads Data Table Card -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left cursor-pointer hover:bg-slate-100 transition-colors select-none" wire:click="sortBy('submitted_at')">
                            <div class="flex items-center gap-1">
                                <span>Submitted</span>
                                @if($sortField === 'submitted_at')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3 text-left cursor-pointer hover:bg-slate-100 transition-colors select-none" wire:click="sortBy('first_name')">
                            <div class="flex items-center gap-1">
                                <span>Applicant Name</span>
                                @if($sortField === 'first_name')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3 text-left">Email & Phone</th>
                        <th scope="col" class="px-4 py-3 text-left">bKash TxID</th>
                        <th scope="col" class="px-4 py-3 text-center cursor-pointer hover:bg-slate-100 transition-colors select-none" wire:click="sortBy('status')">
                            <div class="flex items-center justify-center gap-1">
                                <span>Lead Status</span>
                                @if($sortField === 'status')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3 text-center">
                            <span title="Tri-State Verification Streams: Payment (P), Document (D), Photo (Ph)">Tri-State (P / D / Ph)</span>
                        </th>
                        <th scope="col" class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 divide-opacity-60">
                    @forelse($leads as $lead)
                        @php
                            $fullName = $lead->full_name;
                            $status = $lead->status;

                            // Badge styling map
                            $statusBadgeClass = match($status) {
                                'submitted' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'under_review' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                'missing_items' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'converted' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                                'blocked' => 'bg-zinc-100 text-zinc-700 border-zinc-300',
                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                            };

                            // Stream dot indicator helper
                            $getDotClass = function($streamStatus) {
                                return match($streamStatus) {
                                    'verified' => 'bg-emerald-500 ring-emerald-200',
                                    'in_progress' => 'bg-blue-500 ring-blue-200',
                                    'missing_items', 'reupload_requested' => 'bg-amber-500 ring-amber-200',
                                    'rejected' => 'bg-rose-500 ring-rose-200',
                                    default => 'bg-slate-300 ring-slate-100', // pending
                                };
                            };
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors" wire:key="lead-row-{{ $lead->id }}">
                            <!-- Submitted At -->
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap font-mono text-xs">
                                {{ $lead->submitted_at ? $lead->submitted_at->format('Y-m-d H:i') : $lead->created_at->format('Y-m-d H:i') }}
                            </td>

                            <!-- Applicant Name -->
                            <td class="px-4 py-3 font-medium text-slate-900 whitespace-nowrap">
                                <a
                                    href="{{ route('crm.leads.show', ['workspace' => $workspace, 'lead' => $lead->id]) }}"
                                    class="text-indigo-600 hover:text-indigo-900 font-semibold hover:underline"
                                >
                                    {{ $fullName }}
                                </a>
                                @if($lead->age_at_submission < 18)
                                    <span class="ml-1.5 inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-100 text-amber-800" title="Youth Protection Active per DEC-09">
                                        Minor ({{ $lead->age_at_submission }})
                                    </span>
                                @endif
                            </td>

                            <!-- Email & Phone -->
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                                <div class="text-xs font-mono text-slate-800">{{ $lead->email }}</div>
                                <div class="text-xs text-slate-500">{{ $lead->contact_number }}</div>
                            </td>

                            <!-- bKash TxID -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-pink-50 text-pink-700 border border-pink-200">
                                    {{ $lead->bkash_txid }}
                                </span>
                            </td>

                            <!-- Lead Status Badge -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $statusBadgeClass }}">
                                    {{ ucwords(str_replace('_', ' ', $status)) }}
                                </span>
                            </td>

                            <!-- 3-Dot Tri-State Indicators -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-2 bg-slate-50 px-2 py-1 rounded-md border border-slate-200">
                                    <!-- Payment (P) -->
                                    <span
                                        class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold text-white ring-2 {{ $getDotClass($lead->payment_verification_status) }}"
                                        title="Payment: {{ ucfirst(str_replace('_', ' ', $lead->payment_verification_status)) }}"
                                    >
                                        P
                                    </span>

                                    <!-- Document (D) -->
                                    <span
                                        class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold text-white ring-2 {{ $getDotClass($lead->document_verification_status) }}"
                                        title="Document: {{ ucfirst(str_replace('_', ' ', $lead->document_verification_status)) }}"
                                    >
                                        D
                                    </span>

                                    <!-- Photo (Ph) -->
                                    <span
                                        class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold text-white ring-2 {{ $getDotClass($lead->photo_verification_status) }}"
                                        title="Photo: {{ ucfirst(str_replace('_', ' ', $lead->photo_verification_status)) }}"
                                    >
                                        Ph
                                    </span>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a
                                    href="{{ route('crm.leads.show', ['workspace' => $workspace, 'lead' => $lead->id]) }}"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-medium transition-colors"
                                    id="btn-inspect-lead-{{ $lead->id }}"
                                >
                                    <span>Profile</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <p class="text-sm font-medium text-slate-700">No leads found</p>
                                    <p class="text-xs text-slate-400">No records match the current search keywords, filters, or workspace boundary.</p>
                                    <button
                                        type="button"
                                        wire:click="resetFilters"
                                        class="mt-2 inline-flex items-center px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md text-xs font-medium transition-colors"
                                    >
                                        Clear Search Filters
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($leads->hasPages())
            <div class="px-4 py-3 border-t border-slate-200 bg-slate-50">
                {{ $leads->links() }}
            </div>
        @endif
    </div>
</div>
