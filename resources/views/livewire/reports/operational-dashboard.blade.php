<div class="space-y-6">
    <!-- Top Bar: Tenancy Context & Superadmin Switcher -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $isGlobalView ? 'bg-purple-100 text-purple-800 border border-purple-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300' }}">
                        {{ $isGlobalView ? 'Global Aggregation' : 'Workspace Scoped' }}
                    </span>
                    <span class="text-xs font-mono text-slate-500">
                        {{ $isGlobalView ? 'all-american-spaces' : ($selectedWorkspaceScope ?: $workspaceId) }}
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">
                    {{ $isGlobalView ? 'All American Spaces — Consolidated Analytics' : 'Operational Metrics & Analytics' }}
                </h1>
                <p class="text-sm text-slate-500 mt-0.5">
                    Real-time operational KPIs, conversion pipeline, card issuance, and financial ledgers.
                </p>
            </div>

            <!-- Superadmin Multi-Workspace Switcher per DASH-02 -->
            @if ($canSwitchScope)
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-slate-50 p-2 rounded-lg border border-slate-200">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            wire:click="toggleGlobalView(false)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors {{ !$isGlobalView ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200' }}"
                        >
                            Single Workspace
                        </button>
                        <button
                            type="button"
                            wire:click="toggleGlobalView(true)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors {{ $isGlobalView ? 'bg-purple-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200' }}"
                        >
                            All Spaces (National)
                        </button>
                    </div>

                    @if (!$isGlobalView && count($availableWorkspaces) > 0)
                        <div class="sm:border-l sm:border-slate-300 sm:pl-2">
                            <select
                                wire:change="switchWorkspaceScope($event.target.value)"
                                class="w-full sm:w-auto text-xs rounded-md border-slate-300 bg-white py-1.5 pl-2.5 pr-8 font-medium text-slate-700 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                @foreach ($availableWorkspaces as $ws)
                                    <option value="{{ $ws['id'] }}" @selected(($selectedWorkspaceScope ?: $workspaceId) === $ws['id'])>
                                        {{ $ws['name'] }} ({{ $ws['code'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            @else
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-mono font-medium bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs">
                        {{ $workspaceId }}
                    </span>
                </div>
            @endif
        </div>

        <!-- Date Range Filter Bar -->
        <div class="mt-5 pt-4 border-t border-slate-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <!-- Preset Buttons -->
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs font-medium text-slate-500 mr-1">Timeframe:</span>
                @foreach (['7_days' => '7 Days', '30_days' => '30 Days', '90_days' => '90 Days', 'year' => 'This Year', 'all' => 'All Time'] as $key => $label)
                    <button
                        type="button"
                        wire:click="applyDatePreset('{{ $key }}')"
                        class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors {{ $datePreset === $key ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <!-- Custom Date Inputs & Refresh -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5 text-xs text-slate-600">
                    <label for="date-from" class="font-medium">From:</label>
                    <input
                        type="date"
                        id="date-from"
                        wire:model.live="dateFrom"
                        class="text-xs rounded-md border-slate-300 py-1 px-2 text-slate-700 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div class="flex items-center gap-1.5 text-xs text-slate-600">
                    <label for="date-to" class="font-medium">To:</label>
                    <input
                        type="date"
                        id="date-to"
                        wire:model.live="dateTo"
                        class="text-xs rounded-md border-slate-300 py-1 px-2 text-slate-700 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <button
                    type="button"
                    wire:click="refreshMetrics"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    title="Refresh and bypass cache"
                >
                    <svg wire:loading.class="animate-spin" class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
        <!-- 4-Column Quick KPI Cards per DASH-01 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Active Members Card -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Active Members</span>
                    <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900">{{ number_format($metrics->memberStatusBreakdown['active'] ?? 0) }}</span>
                    <span class="text-xs text-slate-500">of {{ number_format($metrics->totalMembers) }} total</span>
                </div>
                <div class="mt-2 text-xs text-slate-500 flex items-center gap-2">
                    <span class="text-amber-600 font-medium">{{ $metrics->memberStatusBreakdown['inactive'] ?? 0 }} inactive</span>
                    <span>•</span>
                    <span class="text-rose-600 font-medium">{{ ($metrics->memberStatusBreakdown['restricted'] ?? 0) + ($metrics->memberStatusBreakdown['revoked'] ?? 0) }} sanctioned</span>
                </div>
            </div>

            <!-- 2. Pipeline Leads Under Review Card -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Pipeline Leads</span>
                    <span class="p-2 rounded-lg bg-indigo-50 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900">{{ number_format(($metrics->leadPipelineBreakdown['under_review'] ?? 0) + ($metrics->leadPipelineBreakdown['submitted'] ?? 0)) }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                        {{ $metrics->conversionRate }}% Conv. Rate
                    </span>
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    <span>{{ $metrics->leadPipelineBreakdown['converted'] ?? 0 }} converted of {{ number_format($metrics->totalLeads) }} total leads</span>
                </div>
            </div>

            <!-- 3. ID Cards Ready for Pickup Card -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Cards Ready for Pickup</span>
                    <span class="p-2 rounded-lg bg-amber-50 text-amber-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-amber-600">{{ number_format($metrics->idCardStatusBreakdown['card_printed_ready_for_pickup'] ?? 0) }}</span>
                    <span class="text-xs text-slate-500">ready at desk</span>
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    <span>{{ $metrics->idCardStatusBreakdown['provided_for_printing'] ?? 0 }} queued for print • {{ $metrics->idCardStatusBreakdown['delivered'] ?? 0 }} delivered</span>
                </div>
            </div>

            <!-- 4. Total Collected Revenue Card -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Collected Revenue</span>
                    <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-emerald-600">৳ {{ number_format($metrics->totalRevenueBdt, 2) }}</span>
                    <span class="text-xs text-slate-500 font-medium">BDT</span>
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    <span>{{ number_format($metrics->paidInvoicesCount) }} paid invoice records</span>
                </div>
            </div>
        </div>

        <!-- Middle Section: Lead Pipeline Funnel & Member Distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Lead Pipeline Funnel Visualizer (2 Columns) -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-xs border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Lead Pipeline Funnel</h2>
                        <p class="text-xs text-slate-500">Distribution across application, verification, conversion, and rejection stages.</p>
                    </div>
                    <span class="text-xs font-mono bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md">
                        Total: {{ number_format($metrics->totalLeads) }}
                    </span>
                </div>

                <div class="space-y-3">
                    @php
                        $leadStages = [
                            ['key' => 'submitted', 'name' => 'Submitted (New)', 'color' => 'bg-blue-500'],
                            ['key' => 'under_review', 'name' => 'Under Review', 'color' => 'bg-indigo-500'],
                            ['key' => 'missing_items', 'name' => 'Missing Items', 'color' => 'bg-amber-500'],
                            ['key' => 'approved', 'name' => 'Approved (Ready to Convert)', 'color' => 'bg-teal-500'],
                            ['key' => 'converted', 'name' => 'Converted to Member', 'color' => 'bg-emerald-600'],
                            ['key' => 'rejected', 'name' => 'Rejected', 'color' => 'bg-rose-500'],
                            ['key' => 'blocked', 'name' => 'Blocked (Sanctioned)', 'color' => 'bg-slate-700'],
                            ['key' => 'draft', 'name' => 'Draft', 'color' => 'bg-slate-400'],
                        ];
                    @endphp

                    @foreach ($leadStages as $stage)
                        @php
                            $count = $metrics->leadPipelineBreakdown[$stage['key']] ?? 0;
                            $pct = $metrics->totalLeads > 0 ? round(($count / $metrics->totalLeads) * 100, 1) : 0;
                        @endphp
                        <div>
                            <div class="flex justify-between items-center text-xs mb-1">
                                <span class="font-medium text-slate-700">{{ $stage['name'] }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-900">{{ number_format($count) }}</span>
                                    <span class="text-slate-400 font-mono w-12 text-right">({{ $pct }}%)</span>
                                </div>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="{{ $stage['color'] }} h-2.5 rounded-full transition-all duration-300"
                                    style="width: {{ max($pct, ($count > 0 ? 2 : 0)) }}%"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Member Status Distribution (1 Column) -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-6 flex flex-col justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 mb-1">Member Statuses</h2>
                    <p class="text-xs text-slate-500 mb-4">Current membership health & status breakdown.</p>

                    <div class="space-y-3">
                        @php
                            $memberStatuses = [
                                ['key' => 'active', 'name' => 'Active', 'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
                                ['key' => 'inactive', 'name' => 'Inactive (Expired)', 'badge' => 'bg-amber-100 text-amber-800 border-amber-200'],
                                ['key' => 'restricted', 'name' => 'Restricted (Hold)', 'badge' => 'bg-rose-100 text-rose-800 border-rose-200'],
                                ['key' => 'revoked', 'name' => 'Revoked', 'badge' => 'bg-slate-200 text-slate-800 border-slate-300'],
                                ['key' => 'archived_reversed', 'name' => 'Archived (Reversed)', 'badge' => 'bg-slate-100 text-slate-600 border-slate-200'],
                            ];
                        @endphp

                        @foreach ($memberStatuses as $status)
                            @php
                                $mCount = $metrics->memberStatusBreakdown[$status['key']] ?? 0;
                            @endphp
                            <div class="flex items-center justify-between p-2.5 rounded-lg border border-slate-100 bg-slate-50/50">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium border {{ $status['badge'] }}">
                                    {{ $status['name'] }}
                                </span>
                                <span class="text-sm font-bold text-slate-900">{{ number_format($mCount) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100">
                    <div class="flex justify-between items-center text-xs">
                        <span class="font-medium text-slate-500">Total Enrolled Members</span>
                        <span class="font-extrabold text-slate-900 text-base">{{ number_format($metrics->totalMembers) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lower Section: ID Card Issuance Lifecycle Breakdown -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Physical ID Card Issuance Lifecycle</h2>
                    <p class="text-xs text-slate-500">PVC plastic badges tracking across printing batch, front desk pickup, and delivery per DEC-08.</p>
                </div>
                <span class="text-xs font-mono bg-amber-50 text-amber-800 border border-amber-200 px-2.5 py-1 rounded-md">
                    Total Badges: {{ number_format($metrics->totalIdCards) }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                @php
                    $idCardStates = [
                        ['key' => 'provided_for_printing', 'title' => 'Queued for Printing', 'icon_bg' => 'bg-blue-50 text-blue-600'],
                        ['key' => 'card_printed_ready_for_pickup', 'title' => 'Ready for Pickup', 'icon_bg' => 'bg-amber-50 text-amber-600'],
                        ['key' => 'delivered', 'title' => 'Delivered to Member', 'icon_bg' => 'bg-emerald-50 text-emerald-600'],
                        ['key' => 'superseded_replaced', 'title' => 'Superseded / Replaced', 'icon_bg' => 'bg-slate-100 text-slate-600'],
                        ['key' => 'voided_reversal', 'title' => 'Voided Reversal', 'icon_bg' => 'bg-rose-50 text-rose-600'],
                    ];
                @endphp

                @foreach ($idCardStates as $cardState)
                    @php
                        $cCount = $metrics->idCardStatusBreakdown[$cardState['key']] ?? 0;
                    @endphp
                    <div class="p-4 rounded-lg border border-slate-200 bg-slate-50/40">
                        <span class="text-xs font-medium text-slate-600 block">{{ $cardState['title'] }}</span>
                        <div class="mt-2 flex items-baseline gap-1">
                            <span class="text-2xl font-bold text-slate-900">{{ number_format($cCount) }}</span>
                            <span class="text-xs text-slate-400">cards</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
