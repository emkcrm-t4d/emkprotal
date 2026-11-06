<div class="space-y-6">
    <!-- Header & Breadcrumbs Card (3D Frosted Glass) -->
    <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 sm:p-7 shadow-2xl">
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <nav class="flex items-center gap-2 text-xs text-amber-400/90 font-medium mb-2.5">
                    <a href="/crm/{{ $workspaceId }}/dashboard" class="hover:text-amber-300 transition-colors">CRM</a>
                    <span class="text-white/30">/</span>
                    <a href="/crm/{{ $workspaceId }}/settings" class="hover:text-amber-300 transition-colors">Settings</a>
                    <span class="text-white/30">/</span>
                    <span class="text-white font-semibold">Email SMTP Module</span>
                </nav>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/20 border border-amber-400/40 flex items-center justify-center text-amber-400 shadow-md">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white flex items-center gap-3">
                            Email SMTP Module
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-300 mt-0.5">
                            Configure SMTP mail servers, manage token credentials, and monitor outbound connection health.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-4 sm:mt-0 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    wire:click="checkAllRelays"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white text-xs font-semibold border border-white/20 shadow-md transition-all cursor-pointer disabled:opacity-50"
                    title="Ping and test all active relays"
                >
                    <svg wire:loading.remove wire:target="checkAllRelays" class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <svg wire:loading wire:target="checkAllRelays" class="w-4 h-4 animate-spin text-amber-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Check Relays Health</span>
                </button>

                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs sm:text-sm rounded-xl shadow-lg shadow-amber-500/25 transition-all cursor-pointer transform hover:-translate-y-0.5"
                    id="btn-add-smtp-relay"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add New SMTP Relay</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Red Alert Warning Banner for Broken Connection -->
    @if ($brokenRelay)
        <div 
            class="p-5 sm:p-6 rounded-3xl bg-gradient-to-r from-rose-950/95 via-red-900/90 to-rose-950/95 border-2 border-rose-500/70 shadow-2xl shadow-rose-950/60 text-white backdrop-blur-2xl animate-fadeIn" 
            role="alert" 
            id="banner-smtp-broken-module"
        >
            <div class="sm:flex sm:items-start sm:justify-between gap-5">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-rose-500/30 border border-rose-400/50 flex items-center justify-center shrink-0 text-rose-300 shadow-lg">
                        <svg class="w-6 h-6 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base sm:text-lg font-extrabold text-white">
                                Critical Warning: Email Server Connection Broken
                            </h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-rose-500/40 text-rose-100 border border-rose-400/50">
                                Outbound Delivery Alert
                            </span>
                        </div>
                        <p class="text-sm text-rose-100 font-medium mt-1">
                            The SMTP server <strong>'{{ $brokenRelay->name }}'</strong> ({{ $brokenRelay->host }}:{{ $brokenRelay->port }}) is not responding or rejected authentication. Official member lifecycle emails cannot be dispatched until resolved.
                        </p>
                        @if ($brokenRelay->last_error_message)
                            <div class="mt-2.5 p-3 rounded-xl bg-black/40 border border-rose-500/40 font-mono text-xs text-rose-200 select-all">
                                <span class="text-rose-400 font-bold">Error Diagnostic:</span> {{ $brokenRelay->last_error_message }}
                            </div>
                        @endif
                        <p class="text-xs text-rose-200/80 mt-2">
                            Last connection attempt: {{ $brokenRelay->last_tested_at?->diffForHumans() ?? 'Recently' }} ({{ $brokenRelay->last_tested_at?->format('d M Y, h:i A') ?? 'N/A' }} BST)
                        </p>
                    </div>
                </div>

                <div class="mt-4 sm:mt-0 flex sm:flex-col gap-2 shrink-0">
                    <button
                        type="button"
                        wire:click="openTestModal({{ $brokenRelay->id }})"
                        class="px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-white text-xs font-bold shadow-md transition-all cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>Test & Diagnose</span>
                    </button>
                    <button
                        type="button"
                        wire:click="editRelay({{ $brokenRelay->id }})"
                        class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold border border-white/20 transition-all cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Update Credentials</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Flash Status / Warning Messages -->
    @if (session()->has('status'))
        <div class="p-4 rounded-2xl bg-emerald-500/20 border border-emerald-400/40 text-emerald-100 text-sm flex items-center justify-between shadow-lg backdrop-blur-md" role="alert">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium text-emerald-100">{{ session('status') }}</span>
            </div>
        </div>
    @endif

    @if (session()->has('warning'))
        <div class="p-4 rounded-2xl bg-amber-500/20 border border-amber-400/40 text-amber-100 text-sm flex items-center justify-between shadow-lg backdrop-blur-md" role="alert">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="font-medium text-amber-100">{{ session('warning') }}</span>
            </div>
        </div>
    @endif

    <!-- Metric Pods (Frosted 3D Glass) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <!-- Active Gateway -->
        <div class="p-5 rounded-3xl bg-slate-900/70 backdrop-blur-xl border border-white/10 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/20 border border-amber-400/30 flex items-center justify-center text-amber-300 shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-wider font-bold text-slate-400">Primary Relay</p>
                <p class="text-lg font-black text-white truncate mt-0.5">
                    {{ $relays->where('is_active', true)->first()?->name ?? 'None Configured' }}
                </p>
            </div>
        </div>

        <!-- Connection Health -->
        <div class="p-5 rounded-3xl bg-slate-900/70 backdrop-blur-xl border border-white/10 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl {{ $brokenRelay ? 'bg-rose-500/20 border-rose-400/40 text-rose-300' : ($activeRelay ? 'bg-emerald-500/20 border-emerald-400/40 text-emerald-300' : 'bg-slate-700/30 border-slate-600/40 text-slate-400') }} border flex items-center justify-center shrink-0">
                @if ($brokenRelay)
                    <svg class="w-6 h-6 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                @elseif ($activeRelay)
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                @else
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01" />
                    </svg>
                @endif
            </div>
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-wider font-bold text-slate-400">Connection Health</p>
                <p class="text-lg font-black {{ $brokenRelay ? 'text-rose-400' : ($activeRelay ? 'text-emerald-400' : 'text-slate-300') }} truncate mt-0.5">
                    {{ $brokenRelay ? 'Broken' : ($activeRelay ? 'Connected' : 'Untested') }}
                </p>
            </div>
        </div>

        <!-- Latency -->
        <div class="p-5 rounded-3xl bg-slate-900/70 backdrop-blur-xl border border-white/10 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-300 shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-wider font-bold text-slate-400">Handshake Latency</p>
                <p class="text-lg font-black text-white truncate mt-0.5">
                    {{ $relays->where('latency_ms', '>', 0)->first()?->latency_ms ? $relays->where('latency_ms', '>', 0)->first()?->latency_ms . ' ms' : 'N/A' }}
                </p>
            </div>
        </div>

        <!-- Relays in Pool -->
        <div class="p-5 rounded-3xl bg-slate-900/70 backdrop-blur-xl border border-white/10 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-300 shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-wider font-bold text-slate-400">Configured Relays</p>
                <p class="text-lg font-black text-white truncate mt-0.5">
                    {{ $relays->count() }} <span class="text-xs font-normal text-slate-400">({{ $relays->where('is_active', true)->count() }} active)</span>
                </p>
            </div>
        </div>
    </div>

    <!-- SMTP Relays List Container (3D Frosted Glass Card) -->
    <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl overflow-hidden shadow-2xl">
        <div class="border-b border-white/10 bg-slate-950/70 px-6 py-4.5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                    <span>Configured SMTP Relays</span>
                    <span class="text-xs text-slate-400 font-normal">Sorted by Priority Failover Order (1 = Highest)</span>
                </h2>
            </div>
            <div class="text-xs text-amber-400 font-semibold flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span>Dynamic Failover Active</span>
            </div>
        </div>

        @if ($relays->isEmpty())
            <!-- Empty State -->
            <div class="p-12 text-center">
                <div class="w-16 h-16 rounded-3xl bg-amber-500/10 border border-amber-400/20 text-amber-400 flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-white">No SMTP Relays Configured</h3>
                <p class="text-sm text-slate-300 max-w-md mx-auto mt-1 mb-6">
                    Configure your first SMTP email server (e.g. Brevo, Amazon SES, or custom SMTP) to enable automated outbound lifecycle email transmission with health monitoring.
                </p>
                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-sm rounded-xl shadow-lg shadow-amber-500/25 transition-all cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add New SMTP Relay</span>
                </button>
            </div>
        @else
            <!-- Relays Table / Cards -->
            <div class="divide-y divide-white/5">
                @foreach ($relays as $relay)
                    <div class="p-6 transition-all hover:bg-white/[0.02] {{ $relay->isBroken() ? 'bg-rose-950/10 border-l-4 border-l-rose-500' : '' }}">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                            <!-- Left: Server Details -->
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 font-black text-sm {{ $relay->priority === 1 ? 'bg-gradient-to-tr from-amber-500 to-amber-600 text-slate-950 shadow-lg shadow-amber-500/20' : 'bg-slate-800 text-slate-300 border border-white/10' }}" title="Priority (1 = Highest)">
                                    #{{ $relay->priority }}
                                </div>

                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <h3 class="text-base font-bold text-white tracking-tight">{{ $relay->name }}</h3>

                                        <!-- Status Badge -->
                                        @if ($relay->isBroken())
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-rose-500/20 text-rose-200 border border-rose-500/50 shadow-xs animate-pulse">
                                                <svg class="w-3 h-3 text-rose-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                Connection Broken
                                            </span>
                                        @elseif ($relay->isConnected())
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shadow-xs">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                Connected ({{ $relay->latency_ms ?? 0 }}ms)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700/30 text-slate-400 border border-slate-600/40">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                                Untested
                                            </span>
                                        @endif

                                        <!-- Active / Inactive Pill -->
                                        @if ($relay->is_active)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-400/10 text-amber-300 border border-amber-400/30">
                                                Active Outbound
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-400 border border-white/10">
                                                Disabled
                                            </span>
                                        @endif

                                        <!-- Scope Badge -->
                                        @if (empty($relay->workspace_id))
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                                Global Fallback
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Server Connection Spec -->
                                    <div class="flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-slate-300">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-slate-400">Host:</span>
                                            <span class="font-mono text-white font-medium">{{ $relay->host }}:{{ $relay->port }}</span>
                                            <span class="text-[10px] px-1.5 py-0.2 rounded bg-white/10 text-slate-300 uppercase">{{ $relay->encryption }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-slate-400">Sender:</span>
                                            <span class="text-white">{{ $relay->sender_name }} &lt;{{ $relay->sender_email }}&gt;</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-slate-400">Username:</span>
                                            <span class="font-mono text-white">{{ $relay->username }}</span>
                                        </div>
                                        @if ($relay->reply_to)
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-slate-400">Reply-To:</span>
                                                <span class="text-slate-200">{{ $relay->reply_to }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($relay->last_tested_at)
                                        <p class="text-[11px] text-slate-400">
                                            Last checked: {{ $relay->last_tested_at->diffForHumans() }} ({{ $relay->last_tested_at->format('d M Y, h:i A') }} BST)
                                        </p>
                                    @endif

                                    @if ($relay->isBroken() && $relay->last_error_message)
                                        <p class="text-xs text-rose-300 font-mono bg-rose-950/40 p-2 rounded-lg border border-rose-500/30 mt-1">
                                            {{ $relay->last_error_message }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <!-- Right: Action Buttons -->
                            <div class="flex items-center gap-2 self-end lg:self-center shrink-0">
                                <!-- Test Connection / Send Test Email Button -->
                                <button
                                    type="button"
                                    wire:click="openTestModal({{ $relay->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 hover:text-white border border-amber-400/30 text-xs font-semibold shadow-xs transition-all cursor-pointer"
                                    title="Test connection handshake & send verification email"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span>Test Email Send</span>
                                </button>

                                <!-- Toggle Active Switch -->
                                <button
                                    type="button"
                                    wire:click="toggleActive({{ $relay->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl {{ $relay->is_active ? 'bg-white/10 hover:bg-white/15 text-slate-300' : 'bg-slate-800 text-slate-500 hover:text-slate-300' }} border border-white/15 text-xs font-semibold transition-all cursor-pointer"
                                    title="{{ $relay->is_active ? 'Click to disable' : 'Click to enable' }}"
                                >
                                    <span>{{ $relay->is_active ? 'Deactivate' : 'Activate' }}</span>
                                </button>

                                <!-- Edit Button -->
                                <button
                                    type="button"
                                    wire:click="editRelay({{ $relay->id }})"
                                    class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/15 transition-all cursor-pointer"
                                    title="Edit configuration"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>

                                <!-- Delete Button -->
                                <button
                                    type="button"
                                    wire:confirm="Are you sure you want to delete SMTP relay '{{ $relay->name }}'?"
                                    wire:click="deleteRelay({{ $relay->id }})"
                                    class="p-2 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 hover:text-white border border-rose-500/30 transition-all cursor-pointer"
                                    title="Delete relay"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Configuration Modal (Add / Edit SMTP Relay - Exact Match to User Sample Specification) -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-white/20 rounded-3xl max-w-2xl w-full shadow-2xl overflow-hidden animate-fadeIn text-slate-100">
                <!-- Modal Header -->
                <div class="border-b border-white/10 px-6 py-4.5 flex items-center justify-between bg-slate-950/70">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/20 border border-amber-400/40 flex items-center justify-center text-amber-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-white tracking-tight">
                            {{ $editingRelayId ? 'Edit SMTP Relay' : 'Add New SMTP Relay' }}
                        </h2>
                    </div>
                    <button 
                        type="button" 
                        wire:click="closeModal" 
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form wire:submit.prevent="saveRelay" class="p-6 space-y-4.5">
                    <!-- Row 1: Server Name & Priority -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2 space-y-1.5">
                            <label class="block text-xs font-bold text-slate-200">Server Name *</label>
                            <input 
                                type="text" 
                                wire:model="name" 
                                placeholder="e.g. Brevo Primary" 
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm font-medium"
                                required
                            >
                            @error('name') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-200">Priority (1 = Highest) *</label>
                            <input 
                                type="number" 
                                wire:model="priority" 
                                min="1" 
                                max="999" 
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm font-medium"
                                required
                            >
                            @error('priority') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Row 2: Sender Email & Sender Display Name -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-200">Sender Email Address *</label>
                            <input 
                                type="email" 
                                wire:model="sender_email" 
                                placeholder="noreply@jaago.com.bd" 
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm font-medium"
                                required
                            >
                            @error('sender_email') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-200">Sender Display Name *</label>
                            <input 
                                type="text" 
                                wire:model="sender_name" 
                                placeholder="JAAGO HUB" 
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm font-medium"
                                required
                            >
                            @error('sender_name') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Row 3: SMTP Host & Port -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2 space-y-1.5">
                            <label class="block text-xs font-bold text-slate-200">SMTP Host *</label>
                            <input 
                                type="text" 
                                wire:model="host" 
                                placeholder="smtp-relay.brevo.com" 
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white font-mono placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm"
                                required
                            >
                            @error('host') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-200">Port *</label>
                            <input 
                                type="number" 
                                wire:model="port" 
                                placeholder="587" 
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white font-mono focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm"
                                required
                            >
                            @error('port') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Row 4: Encryption Protocol -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-200">Encryption Protocol</label>
                        <select 
                            wire:model="encryption" 
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm font-medium"
                        >
                            <option value="starttls">STARTTLS (Port 587 — Recommended)</option>
                            <option value="ssl">SSL/TLS (Port 465)</option>
                            <option value="none">None / Plain (Port 25/587)</option>
                        </select>
                        @error('encryption') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Row 5: SMTP Username -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-200">SMTP Username *</label>
                        <input 
                            type="text" 
                            wire:model="username" 
                            placeholder="nasif.kamal@jaago.com.bd" 
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm font-mono"
                            required
                        >
                        @error('username') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Row 6: SMTP Password with Reveal Toggle -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-200">SMTP Password *</label>
                            <button 
                                type="button" 
                                wire:click="$toggle('revealPassword')" 
                                class="text-xs font-semibold text-amber-400 hover:text-amber-300 inline-flex items-center gap-1 cursor-pointer transition-colors"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    @if ($revealPassword)
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    @endif
                                </svg>
                                <span>{{ $revealPassword ? 'Hide Password' : 'Reveal Saved Password' }}</span>
                            </button>
                        </div>
                        <input 
                            type="{{ $revealPassword ? 'text' : 'password' }}" 
                            wire:model="password" 
                            placeholder="{{ $editingRelayId ? 'Leave blank to keep current encrypted password' : 'Enter SMTP password or API master key' }}" 
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white font-mono focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm"
                            {{ $editingRelayId ? '' : 'required' }}
                        >
                        @error('password') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Brevo SMTP Credentials Guide Banner (Exact Match to User Attachment) -->
                    <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-400/30 text-xs text-amber-200/95 space-y-1">
                        <div class="flex items-center gap-1.5 font-bold text-amber-300">
                            <span>💡 Brevo SMTP Credentials Guide:</span>
                        </div>
                        <ul class="list-disc list-inside space-y-0.5 text-[11px] text-amber-100 pl-1">
                            <li><span class="font-semibold text-white">SMTP Username:</span> Your registered Brevo account email.</li>
                            <li><span class="font-semibold text-white">SMTP Password:</span> Your Brevo SMTP Master Key (found in your <em>Brevo Dashboard &gt; SMTP &amp; API &gt; SMTP Keys</em>, usually starting with <code class="px-1.5 py-0.2 rounded bg-slate-950 text-amber-300 font-mono text-[10px]">xsmtpsib-...</code>).</li>
                        </ul>
                    </div>

                    <!-- Row 7: Throttle & Reply-To -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-200">Throttle Interval (Seconds / Recipient)</label>
                            <input 
                                type="number" 
                                wire:model="throttle_seconds" 
                                min="0" 
                                max="3600" 
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm font-medium"
                            >
                            @error('throttle_seconds') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-200">Reply-To Address (Optional)</label>
                            <input 
                                type="email" 
                                wire:model="reply_to" 
                                placeholder="pnc@jaago.com.bd" 
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm font-medium"
                            >
                            @error('reply_to') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Checkbox: Enable this SMTP relay -->
                    <div class="pt-1 flex items-center gap-2.5">
                        <input 
                            type="checkbox" 
                            id="enable-smtp" 
                            wire:model="is_active" 
                            class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400 border-white/20 bg-slate-950/60 cursor-pointer"
                        >
                        <label for="enable-smtp" class="text-xs font-medium text-slate-200 select-none cursor-pointer">
                            Enable this SMTP relay for active outbound transmission
                        </label>
                    </div>

                    <!-- Modal Footer Actions -->
                    <div class="pt-4 border-t border-white/10 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-slate-300 hover:text-white text-xs sm:text-sm font-semibold transition-colors cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs sm:text-sm rounded-xl shadow-lg shadow-amber-500/25 transition-all cursor-pointer disabled:opacity-50"
                        >
                            <svg wire:loading.remove wire:target="saveRelay" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg wire:loading wire:target="saveRelay" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Save Configuration</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Diagnostic Test Email Modal -->
    @if ($showTestModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-white/20 rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden animate-fadeIn text-slate-100">
                <div class="border-b border-white/10 px-6 py-4.5 flex items-center justify-between bg-slate-950/70">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/20 border border-amber-400/40 flex items-center justify-center text-amber-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h2 class="text-base font-bold text-white">Test SMTP Connection &amp; Dispatch</h2>
                    </div>
                    <button 
                        type="button" 
                        wire:click="closeTestModal" 
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <p class="text-xs text-slate-300">
                        This test performs an immediate TCP socket connection, EHLO greeting, TLS cryptographic handshake, and SMTP credential authentication. Optionally transmits an official diagnostic test email.
                    </p>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-200">Recipient Email Address (Optional for test email send)</label>
                        <input 
                            type="email" 
                            wire:model="testRecipientEmail" 
                            placeholder="admin@emkcenter.org" 
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400 text-sm font-medium"
                        >
                        @error('testRecipientEmail') <p class="text-xs text-rose-300 font-medium">{{ $message }}</p> @enderror
                    </div>

                    @if ($testResult)
                        @if ($testResult['success'])
                            <div class="p-4 rounded-2xl bg-emerald-500/20 border border-emerald-400/50 text-emerald-200 space-y-1">
                                <div class="flex items-center gap-2 font-bold text-emerald-300 text-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Connection &amp; Dispatch Succeeded</span>
                                </div>
                                <p class="text-xs text-emerald-100">{{ $testResult['message'] }}</p>
                                <p class="text-[11px] text-emerald-300/80">Round-trip latency: <strong>{{ $testResult['latency_ms'] }} ms</strong></p>
                            </div>
                        @else
                            <div class="p-4 rounded-2xl bg-rose-500/20 border border-rose-400/50 text-rose-200 space-y-2">
                                <div class="flex items-center gap-2 font-bold text-rose-300 text-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span>Connection Handshake Failed</span>
                                </div>
                                <div class="p-2.5 rounded-lg bg-black/40 border border-rose-500/30 font-mono text-xs text-rose-200">
                                    {{ $testResult['message'] }}
                                </div>
                                <p class="text-[11px] text-rose-300/80">
                                    Please verify your SMTP Host, Port, Username, and API Master Key. Ensure your provider allows outbound SMTP traffic.
                                </p>
                            </div>
                        @endif
                    @endif

                    <div class="pt-3 border-t border-white/10 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            wire:click="closeTestModal"
                            class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-slate-300 hover:text-white text-xs font-semibold transition-colors cursor-pointer"
                        >
                            Close
                        </button>
                        <button
                            type="button"
                            wire:click="runTestConnection"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-5 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs sm:text-sm rounded-xl shadow-md transition-all cursor-pointer disabled:opacity-50"
                        >
                            <svg wire:loading.remove wire:target="runTestConnection" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <svg wire:loading wire:target="runTestConnection" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Run Connection Test</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

