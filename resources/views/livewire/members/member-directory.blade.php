<div class="space-y-6">
    <!-- Breadcrumbs & Directory Header with Glass Blur View -->
    <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 sm:p-7 shadow-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-white">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-400 mb-1.5">
                <a href="/crm/{{ $workspace }}/members" class="hover:text-amber-400 transition-colors">CRM</a>
                <span class="text-slate-600">/</span>
                <span class="text-slate-200 font-semibold">Members Directory</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Member Directory</h1>
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
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-white/10 shadow-lg">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Members</p>
            <p class="text-2xl font-black text-white mt-1" id="metric-total">{{ number_format($metrics['total']) }}</p>
        </div>

        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-emerald-500/20 shadow-lg">
            <p class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Active</p>
            <p class="text-2xl font-black text-emerald-300 mt-1" id="metric-active">{{ number_format($metrics['active']) }}</p>
        </div>

        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-white/10 shadow-lg">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Inactive</p>
            <p class="text-2xl font-black text-slate-300 mt-1" id="metric-inactive">{{ number_format($metrics['inactive']) }}</p>
        </div>

        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-amber-500/20 shadow-lg">
            <p class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Restricted</p>
            <p class="text-2xl font-black text-amber-300 mt-1" id="metric-restricted">{{ number_format($metrics['restricted']) }}</p>
        </div>

        <div class="bg-slate-900/70 backdrop-blur-2xl p-4 rounded-2xl border border-rose-500/20 shadow-lg">
            <p class="text-xs font-semibold text-rose-400 uppercase tracking-wider">Revoked</p>
            <p class="text-2xl font-black text-rose-300 mt-1" id="metric-revoked">{{ number_format($metrics['revoked']) }}</p>
        </div>
    </div>

    <!-- Multi-Facet Filter Toolbar -->
    <div class="bg-white p-4 rounded-xl shadow-xs border border-slate-200 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Search Keyword Input -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-medium text-slate-600 mb-1">Search Members</label>
                <div class="relative">
                    <input
                        type="text"
                        id="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search Member ID, name, email, or contact number..."
                        class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Member Status Dropdown -->
            <div>
                <label for="statusFilter" class="block text-xs font-medium text-slate-600 mb-1">Member Status</label>
                <select
                    id="statusFilter"
                    wire:model.live="statusFilter"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="restricted">Restricted</option>
                    <option value="revoked">Revoked</option>
                </select>
            </div>

            <!-- Membership Term Filter -->
            <div>
                <label for="termFilter" class="block text-xs font-medium text-slate-600 mb-1">Membership Term</label>
                <select
                    id="termFilter"
                    wire:model.live="termFilter"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="">All Terms</option>
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}">{{ $term->name }} ({{ $term->duration_months }}m)</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Members Table Canvas -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm" id="table-members">
                <thead class="bg-white/5 backdrop-blur-md text-slate-200 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th scope="col" class="px-6 py-3.5 cursor-pointer hover:bg-white/10 hover:text-amber-400 transition-colors" wire:click="sortBy('member_id')">
                            <div class="flex items-center gap-1.5">
                                <span>Member ID</span>
                                @if ($sortField === 'member_id')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3.5 cursor-pointer hover:bg-white/10 hover:text-amber-400 transition-colors" wire:click="sortBy('first_name')">
                            <div class="flex items-center gap-1.5">
                                <span>Member Name</span>
                                @if ($sortField === 'first_name')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3.5">
                            Contact Info
                        </th>
                        <th scope="col" class="px-6 py-3.5 cursor-pointer hover:bg-white/10 hover:text-amber-400 transition-colors" wire:click="sortBy('status')">
                            <div class="flex items-center gap-1.5">
                                <span>Status</span>
                                @if ($sortField === 'status')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3.5">
                            Term &amp; Duration
                        </th>
                        <th scope="col" class="px-6 py-3.5 cursor-pointer hover:bg-white/10 hover:text-amber-400 transition-colors" wire:click="sortBy('expiry_date')">
                            <div class="flex items-center gap-1.5">
                                <span>Validity Dates</span>
                                @if ($sortField === 'expiry_date')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3.5 text-right">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-slate-200">
                    @forelse ($members as $member)
                        <tr class="hover:bg-white/5 transition-colors" wire:key="member-{{ $member->id }}">
                            <!-- Member ID -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-slate-950/80 text-white border border-white/20 shadow-inner tracking-wide">
                                    {{ $member->member_id }}
                                </span>
                            </td>

                            <!-- Full Name -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-bold text-white">{{ $member->full_name }}</div>
                                <div class="text-xs text-slate-400 capitalize">{{ $member->occupation ?? 'N/A' }}</div>
                            </td>

                            <!-- Contact Info -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-white text-xs font-medium">{{ $member->email }}</div>
                                <div class="text-slate-300 font-mono text-xs mt-0.5">{{ $member->contact_number }}</div>
                            </td>

                            <!-- Status Pill -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($member->status === 'active')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 shadow-xs">
                                        Active
                                    </span>
                                @elseif ($member->status === 'inactive')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-white/10 text-slate-300 border border-white/15 shadow-xs">
                                        Inactive
                                    </span>
                                @elseif ($member->status === 'restricted')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 shadow-xs">
                                        Restricted
                                    </span>
                                @elseif ($member->status === 'revoked')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30 shadow-xs">
                                        Revoked
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-white/10 text-slate-300 border border-white/15">
                                        {{ ucfirst($member->status) }}
                                    </span>
                                @endif
                            </td>

                            <!-- Term & Duration -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-white text-xs font-semibold">
                                    {{ $member->term?->name ?? 'Standard' }}
                                </div>
                                <div class="text-slate-400 text-xs">
                                    {{ $member->term?->duration_months ?? 12 }} Months
                                </div>
                            </td>

                            <!-- Validity Dates -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs">
                                <div class="text-slate-300">
                                    <span class="font-medium text-slate-400">Start:</span> <span class="text-white font-medium">{{ $member->start_date ? $member->start_date->format('Y-m-d') : 'N/A' }}</span>
                                </div>
                                <div class="text-slate-300 mt-0.5">
                                    <span class="font-medium text-slate-400">Expires:</span> <span class="text-white font-medium">{{ $member->expiry_date ? $member->expiry_date->format('Y-m-d') : 'N/A' }}</span>
                                </div>
                            </td>

                            <!-- Action Button -->
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @php
                                    $profileUrl = \Illuminate\Support\Facades\Route::has('crm.members.show')
                                        ? route('crm.members.show', ['workspace' => $workspace, 'member' => $member->uuid])
                                        : url("/crm/{$workspace}/members/{$member->uuid}");
                                @endphp
                                <a
                                    href="{{ $profileUrl }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-xs font-medium border border-indigo-200 transition-colors"
                                >
                                    <span>Inspect</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-10 h-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <p class="text-sm font-medium text-slate-700">No member records found</p>
                                    <p class="text-xs text-slate-400 mt-1">Try adjusting your keyword search or status filter options.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($members->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">
                {{ $members->links() }}
            </div>
        @endif
    </div>
</div>
