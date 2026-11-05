<div class="space-y-6">
    <!-- Top Bar: Glass Blur View Tenancy Context & Superadmin Switcher -->
    <div class="bg-slate-900/70 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white/10 p-6 sm:p-7 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $isGlobalView ? 'bg-purple-500/20 text-purple-300 border border-purple-400/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/30' }} shadow-xs">
                        {{ $isGlobalView ? 'Global Aggregation' : 'Workspace Scoped' }}
                    </span>
                    <span class="sr-only">
                        {{ $isGlobalView ? 'all-american-spaces' : ($selectedWorkspaceScope ?: $workspaceId) }}
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight mt-2">
                    {{ $isGlobalView ? 'All American Spaces — Consolidated Analytics' : 'Operational Metrics & Analytics' }}
                </h1>
            </div>

            <!-- Superadmin Multi-Workspace Switcher per DASH-02 -->
            @if ($canSwitchScope)
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-slate-950/60 p-2.5 rounded-2xl border border-white/10">
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            wire:click="toggleGlobalView(false)"
                            class="px-3.5 py-2 text-xs font-semibold rounded-xl transition-all cursor-pointer {{ !$isGlobalView ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-bold shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10' }}"
                        >
                            Single Workspace
                        </button>
                        <button
                            type="button"
                            wire:click="toggleGlobalView(true)"
                            class="px-3.5 py-2 text-xs font-semibold rounded-xl transition-all cursor-pointer {{ $isGlobalView ? 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10' }}"
                        >
                            All Spaces (National)
                        </button>
                    </div>

                    @if (!$isGlobalView && count($availableWorkspaces) > 0)
                        <div class="sm:border-l sm:border-white/10 sm:pl-2">
                            <select
                                wire:change="switchWorkspaceScope($event.target.value)"
                                class="w-full sm:w-auto text-xs rounded-xl border-white/15 bg-white/10 py-2 pl-3 pr-8 font-medium text-white shadow-inner focus:border-amber-400 focus:ring-amber-400 cursor-pointer"
                            >
                                @foreach ($availableWorkspaces as $ws)
                                    <option value="{{ $ws['id'] }}" class="bg-slate-900 text-white" @selected(($selectedWorkspaceScope ?: $workspaceId) === $ws['id'])>
                                        {{ $ws['name'] }} ({{ $ws['code'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            @else
                <span class="sr-only">{{ $workspaceId }}</span>
            @endif
        </div>

        <!-- Date Range Filter Bar -->
        <div class="mt-6 pt-5 border-t border-white/10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <!-- Preset Buttons -->
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs font-semibold text-slate-300 mr-1">Timeframe:</span>
                @foreach (['7_days' => '7 Days', '30_days' => '30 Days', '90_days' => '90 Days', 'year' => 'This Year', 'all' => 'All Time'] as $key => $label)
                    <button
                        type="button"
                        wire:click="applyDatePreset('{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-xl transition-all cursor-pointer {{ $datePreset === $key ? 'bg-amber-400 text-slate-950 font-bold shadow-md' : 'bg-white/10 text-slate-300 hover:text-white hover:bg-white/15 border border-white/5' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <!-- Custom Date Inputs & Refresh -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5 text-xs text-slate-300">
                    <label for="date-from" class="font-medium">From:</label>
                    <input
                        type="date"
                        id="date-from"
                        wire:model.live="dateFrom"
                        class="text-xs rounded-xl border-white/15 bg-white/10 py-1.5 px-3 text-white shadow-inner focus:border-amber-400 focus:ring-amber-400"
                    />
                </div>
                <div class="flex items-center gap-1.5 text-xs text-slate-300">
                    <label for="date-to" class="font-medium">To:</label>
                    <input
                        type="date"
                        id="date-to"
                        wire:model.live="dateTo"
                        class="text-xs rounded-xl border-white/15 bg-white/10 py-1.5 px-3 text-white shadow-inner focus:border-amber-400 focus:ring-amber-400"
                    />
                </div>
                <button
                    type="button"
                    wire:click="refreshMetrics"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-white/10 hover:bg-white/20 border border-white/10 rounded-xl transition cursor-pointer focus:outline-none focus:ring-2 focus:ring-amber-400"
                    title="Refresh and bypass cache"
                >
                    <svg wire:loading.class="animate-spin" class="w-3.5 h-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    @if ($isGlobalView && $globalMetrics)
        <!-- ========================================== -->
        <!-- GLOBAL MULTI-WORKSPACE CONSOLIDATED VIEW -->
        <!-- ========================================== -->
        <!-- Global KPI Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Members Across Spaces -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Total Members (National)</span>
                    <span class="p-2 rounded-lg bg-indigo-50 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900">{{ number_format($globalMetrics['total_members']) }}</span>
                    <span class="text-xs text-slate-500">all spaces</span>
                </div>
            </div>

            <!-- Total Leads Across Spaces -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Total Pipeline Leads</span>
                    <span class="p-2 rounded-lg bg-blue-50 text-blue-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900">{{ number_format($globalMetrics['total_leads']) }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                        {{ $globalMetrics['conversion_rate'] }}% conv.
                    </span>
                </div>
            </div>

            <!-- Total ID Cards Issued -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Total ID Cards</span>
                    <span class="p-2 rounded-lg bg-amber-50 text-amber-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900">{{ number_format($globalMetrics['total_id_cards']) }}</span>
                    <span class="text-xs text-slate-500">badges tracked</span>
                </div>
            </div>

            <!-- Total National Revenue -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Total National Revenue</span>
                    <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-emerald-600">৳ {{ number_format($globalMetrics['total_revenue_bdt'], 2) }}</span>
                    <span class="text-xs text-slate-500">({{ $globalMetrics['paid_invoices_count'] }} paid)</span>
                </div>
            </div>
        </div>

        <!-- National American Spaces Overview Comparison Table -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">National American Spaces Overview</h3>
                    <p class="text-xs text-slate-500">Comparative breakdown across EMK Center Dhaka, American Center, Chattogram, Rajshahi, Sylhet, and Barishal.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-700 text-xs font-semibold uppercase tracking-wider text-left">
                        <tr>
                            <th scope="col" class="py-3 px-4">Space Code</th>
                            <th scope="col" class="py-3 px-4">American Space</th>
                            <th scope="col" class="py-3 px-4 text-right">Total Members</th>
                            <th scope="col" class="py-3 px-4 text-right">Active</th>
                            <th scope="col" class="py-3 px-4 text-right">Leads</th>
                            <th scope="col" class="py-3 px-4 text-right">Converted</th>
                            <th scope="col" class="py-3 px-4 text-right">Conv. Rate</th>
                            <th scope="col" class="py-3 px-4 text-right">Revenue (BDT)</th>
                            <th scope="col" class="py-3 px-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($globalMetrics['spaces_breakdown'] as $code => $data)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-4 font-mono font-medium text-slate-900">{{ $code }}</td>
                                <td class="py-3 px-4 text-slate-800">{{ $data['space_name'] }}</td>
                                <td class="py-3 px-4 text-right font-medium text-slate-900">{{ number_format($data['total_members']) }}</td>
                                <td class="py-3 px-4 text-right text-emerald-600 font-medium">{{ number_format($data['active_members']) }}</td>
                                <td class="py-3 px-4 text-right text-slate-600">{{ number_format($data['total_leads']) }}</td>
                                <td class="py-3 px-4 text-right text-indigo-600 font-medium">{{ number_format($data['converted_leads']) }}</td>
                                <td class="py-3 px-4 text-right font-semibold text-slate-800">{{ $data['conversion_rate'] }}%</td>
                                <td class="py-3 px-4 text-right font-bold text-emerald-700">৳ {{ number_format($data['total_revenue_bdt'], 2) }}</td>
                                <td class="py-3 px-4 text-center">
                                    <button
                                        type="button"
                                        wire:click="switchWorkspaceScope('{{ $data['workspace_id'] }}')"
                                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-900 hover:underline"
                                    >
                                        Inspect
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-6 text-center text-slate-400">No American Spaces found in directory.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @elseif ($metrics)
        <!-- ========================================== -->
        <!-- SINGLE WORKSPACE OPERATIONAL DASHBOARD -->
        <!-- ========================================== -->
        <!-- 4-Column Quick KPI Cards in 3D Frosted Glass -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Active Members Card -->
            <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Active Members</span>
                    <span class="p-2 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black text-white">{{ number_format($metrics->memberStatusBreakdown['active'] ?? 0) }}</span>
                    <span class="text-xs text-slate-400">of {{ number_format($metrics->totalMembers) }} total</span>
                </div>
                <div class="mt-2 text-xs text-slate-400 flex items-center gap-2">
                    <span class="text-amber-400 font-medium">{{ $metrics->memberStatusBreakdown['inactive'] ?? 0 }} inactive</span>
                    <span>•</span>
                    <span class="text-rose-400 font-medium">{{ ($metrics->memberStatusBreakdown['restricted'] ?? 0) + ($metrics->memberStatusBreakdown['revoked'] ?? 0) }} sanctioned</span>
                </div>
            </div>

            <!-- 2. Pipeline Leads Under Review Card -->
            <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Pipeline Leads</span>
                    <span class="p-2 rounded-xl bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black text-white">{{ number_format(($metrics->leadPipelineBreakdown['under_review'] ?? 0) + ($metrics->leadPipelineBreakdown['submitted'] ?? 0)) }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                        {{ $metrics->conversionRate }}% Conv. Rate
                    </span>
                </div>
                <div class="mt-2 text-xs text-slate-400">
                    <span>{{ $metrics->leadPipelineBreakdown['converted'] ?? 0 }} converted of {{ number_format($metrics->totalLeads) }} total leads</span>
                </div>
            </div>

            <!-- 3. ID Cards Ready for Pickup Card -->
            <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Cards Ready for Pickup</span>
                    <span class="p-2 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black text-amber-300">{{ number_format($metrics->idCardStatusBreakdown['card_printed_ready_for_pickup'] ?? 0) }}</span>
                    <span class="text-xs text-slate-400">ready at desk</span>
                </div>
                <div class="mt-2 text-xs text-slate-400">
                    <span>{{ $metrics->idCardStatusBreakdown['provided_for_printing'] ?? 0 }} queued for print • {{ $metrics->idCardStatusBreakdown['delivered'] ?? 0 }} delivered</span>
                </div>
            </div>

            <!-- 4. Total Collected Revenue Card -->
            <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Collected Revenue</span>
                    <span class="p-2 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black text-emerald-300">৳ {{ number_format($metrics->totalRevenueBdt, 2) }}</span>
                    <span class="text-xs text-slate-400 font-medium">BDT</span>
                </div>
                <div class="mt-2 text-xs text-slate-400">
                    <span>{{ number_format($metrics->paidInvoicesCount) }} paid invoice records</span>
                </div>
            </div>
        </div>

        <!-- Middle Section: Lead Pipeline Funnel & Member Distribution in 3D Frosted Glass -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Lead Pipeline Funnel Visualizer (2 Columns) -->
            <div class="lg:col-span-2 bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-base font-bold text-white tracking-tight">Lead Pipeline Funnel</h2>
                    </div>
                    <span class="text-xs font-mono bg-white/10 text-amber-300 border border-white/10 px-3 py-1 rounded-full font-bold">
                        Total: {{ number_format($metrics->totalLeads) }}
                    </span>
                </div>

                <div class="space-y-3.5">
                    @php
                        $leadStages = [
                            ['key' => 'submitted', 'name' => 'Submitted (New)', 'color' => 'bg-blue-500'],
                            ['key' => 'under_review', 'name' => 'Under Review', 'color' => 'bg-indigo-500'],
                            ['key' => 'missing_items', 'name' => 'Missing Items', 'color' => 'bg-amber-500'],
                            ['key' => 'approved', 'name' => 'Approved (Ready to Convert)', 'color' => 'bg-teal-500'],
                            ['key' => 'converted', 'name' => 'Converted to Member', 'color' => 'bg-emerald-500'],
                            ['key' => 'rejected', 'name' => 'Rejected', 'color' => 'bg-rose-500'],
                            ['key' => 'blocked', 'name' => 'Blocked (Sanctioned)', 'color' => 'bg-slate-600'],
                            ['key' => 'draft', 'name' => 'Draft', 'color' => 'bg-slate-500'],
                        ];
                    @endphp

                    @foreach ($leadStages as $stage)
                        @php
                            $count = $metrics->leadPipelineBreakdown[$stage['key']] ?? 0;
                            $pct = $metrics->totalLeads > 0 ? round(($count / $metrics->totalLeads) * 100, 1) : 0;
                        @endphp
                        <div>
                            <div class="flex justify-between items-center text-xs mb-1.5">
                                <span class="font-semibold text-slate-300">{{ $stage['name'] }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-white">{{ number_format($count) }}</span>
                                    <span class="text-slate-400 font-mono w-12 text-right">({{ $pct }}%)</span>
                                </div>
                            </div>
                            <div class="w-full bg-slate-950/80 rounded-full h-2.5 overflow-hidden border border-white/5">
                                <div
                                    class="{{ $stage['color'] }} h-2.5 rounded-full transition-all duration-300"
                                    style="width: {{ max($pct, ($count > 0 ? 2 : 0)) }}%"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Member Status Distribution (1 Column - Redesigned High-Contrast Pod) -->
            <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white flex flex-col justify-between">
                <div>
                    <h2 class="text-base font-bold text-white tracking-tight mb-4">Member Statuses</h2>

                    <div class="space-y-2.5">
                        @php
                            $memberStatuses = [
                                ['key' => 'active', 'name' => 'Active', 'badge' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'],
                                ['key' => 'inactive', 'name' => 'Inactive (Expired)', 'badge' => 'bg-amber-500/20 text-amber-300 border-amber-500/30'],
                                ['key' => 'restricted', 'name' => 'Restricted (Hold)', 'badge' => 'bg-rose-500/20 text-rose-300 border-rose-500/30'],
                                ['key' => 'revoked', 'name' => 'Revoked', 'badge' => 'bg-slate-800 text-slate-300 border-white/10'],
                                ['key' => 'archived_reversed', 'name' => 'Archived (Reversed)', 'badge' => 'bg-slate-800/60 text-slate-400 border-white/10'],
                            ];
                        @endphp

                        @foreach ($memberStatuses as $status)
                            @php
                                $mCount = $metrics->memberStatusBreakdown[$status['key']] ?? 0;
                            @endphp
                            <div class="flex items-center justify-between p-3 rounded-2xl border border-white/10 bg-slate-950/60 shadow-inner">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $status['badge'] }}">
                                    {{ $status['name'] }}
                                </span>
                                <span class="text-base font-black text-white">{{ number_format($mCount) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-white/10">
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-medium text-slate-400">Total Enrolled Members</span>
                        <span class="font-black text-white text-base">{{ number_format($metrics->totalMembers) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lower Section: ID Card Issuance Lifecycle Breakdown -->
        <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-white tracking-tight">Physical ID Card Issuance Lifecycle</h2>
                </div>
                <span class="text-xs font-mono bg-white/10 text-amber-300 border border-white/10 px-3 py-1 rounded-full font-bold">
                    Total Badges: {{ number_format($metrics->totalIdCards) }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                @php
                    $idCardStates = [
                        ['key' => 'provided_for_printing', 'title' => 'Queued for Printing'],
                        ['key' => 'card_printed_ready_for_pickup', 'title' => 'Ready for Pickup'],
                        ['key' => 'delivered', 'title' => 'Delivered to Member'],
                        ['key' => 'superseded_replaced', 'title' => 'Superseded / Replaced'],
                        ['key' => 'voided_reversal', 'title' => 'Voided Reversal'],
                    ];
                @endphp

                @foreach ($idCardStates as $cardState)
                    @php
                        $cCount = $metrics->idCardStatusBreakdown[$cardState['key']] ?? 0;
                    @endphp
                    <div class="p-4 rounded-2xl border border-white/10 bg-slate-950/60 shadow-inner">
                        <span class="text-xs font-semibold text-slate-400 block">{{ $cardState['title'] }}</span>
                        <div class="mt-2 flex items-baseline gap-1.5">
                            <span class="text-2xl font-black text-white">{{ number_format($cCount) }}</span>
                            <span class="text-xs text-slate-500">cards</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
