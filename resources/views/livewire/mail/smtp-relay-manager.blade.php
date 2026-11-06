<div class="space-y-6" x-data="{ showRawTrace: false }">
    <!-- Header Block: Minimalist Glass Card -->
    <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl p-5 sm:p-6 shadow-xl">
        <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-xs text-amber-400/90 font-medium mb-2">
                    <a href="/crm/{{ $workspaceId }}/dashboard" class="hover:text-amber-300 transition-colors">CRM</a>
                    <span class="text-white/30">/</span>
                    <a href="/crm/{{ $workspaceId }}/settings" class="hover:text-amber-300 transition-colors">Settings</a>
                    <span class="text-white/30">/</span>
                    <span class="text-white font-semibold">Email SMTP Module</span>
                </nav>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500/20 to-amber-600/30 border border-amber-400/40 flex items-center justify-center text-amber-400 shadow-sm shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white flex items-center gap-2.5">
                            <span>Email SMTP Module</span>
                            @if ($brokenRelay)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse">
                                    Attention Required
                                </span>
                            @elseif ($activeRelay)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                    All Systems Operational
                                </span>
                            @endif
                        </h1>
                        <p class="text-xs text-slate-300 mt-0.5">
                            Manage live SMTP server gateways, review health diagnostics, and track outbound email deliveries.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Top Actions -->
            <div class="mt-4 sm:mt-0 flex items-center gap-2.5 shrink-0">
                <button
                    type="button"
                    wire:click="checkAllRelays"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-200 text-xs font-semibold border border-white/10 transition cursor-pointer disabled:opacity-50"
                    title="Ping and test all active relays"
                >
                    <svg wire:loading.remove wire:target="checkAllRelays" class="w-3.5 h-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <svg wire:loading wire:target="checkAllRelays" class="w-3.5 h-3.5 animate-spin text-amber-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Check Health</span>
                </button>

                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs sm:text-sm rounded-xl shadow-md transition cursor-pointer"
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

    <!-- Flash Status / Warning Alerts -->
    @if (session()->has('status'))
        <div class="p-3.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-xs sm:text-sm flex items-center justify-between shadow-md backdrop-blur-md" role="alert">
            <div class="flex items-center gap-2.5">
                <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium text-emerald-100">{{ session('status') }}</span>
            </div>
        </div>
    @endif

    @if (session()->has('warning'))
        <div class="p-3.5 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-200 text-xs sm:text-sm flex items-center justify-between shadow-md backdrop-blur-md" role="alert">
            <div class="flex items-center gap-2.5">
                <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="font-medium text-amber-100">{{ session('warning') }}</span>
            </div>
        </div>
    @endif

    <!-- BLOCK 1: Segregated Warning Alert Card (Clean, Human-Readable, Minimalist) -->
    @if ($brokenRelay)
        <div 
            class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-rose-950/70 via-slate-900/80 to-slate-900/80 border border-rose-500/40 shadow-xl text-white backdrop-blur-xl" 
            role="alert" 
            id="banner-smtp-broken-module"
        >
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-500/20 border border-rose-500/40 flex items-center justify-center shrink-0 text-rose-300">
                        <svg class="w-5 h-5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-sm text-white">SMTP Connection Alert:</span>
                            <span class="text-xs font-semibold text-rose-300">{{ $brokenRelay->name }}</span>
                            <span class="text-[10px] px-2 py-0.2 rounded-full font-bold bg-rose-500/30 text-rose-200 border border-rose-500/40">
                                Error {{ str_contains($brokenRelay->last_error_message ?? '', '535') ? '535' : 'Offline' }}
                            </span>
                        </div>
                        <p class="text-xs text-rose-100/90 leading-relaxed">
                            {{ $brokenRelay->friendly_error ?: 'The email server failed authentication. Outbound deliveries cannot be sent.' }}
                        </p>
                        <div class="flex items-center gap-3 pt-0.5">
                            <span class="text-[11px] text-slate-400">
                                Last checked: {{ $brokenRelay->last_tested_at?->diffForHumans() ?? 'Recently' }}
                            </span>
                            <button 
                                type="button" 
                                @click="showRawTrace = !showRawTrace" 
                                class="text-[11px] font-semibold text-amber-400 hover:text-amber-300 underline cursor-pointer"
                            >
                                <span x-text="showRawTrace ? 'Hide technical trace' : 'View raw diagnostic log'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Alert Action Pills -->
                <div class="flex items-center gap-2 self-start md:self-center shrink-0">
                    <button
                        type="button"
                        wire:click="openTestModal({{ $brokenRelay->id }})"
                        class="px-3.5 py-1.5 rounded-xl bg-rose-500 hover:bg-rose-400 text-white text-xs font-bold shadow transition cursor-pointer flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>Re-Test</span>
                    </button>
                    <button
                        type="button"
                        wire:click="editRelay({{ $brokenRelay->id }})"
                        class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold border border-white/15 transition cursor-pointer flex items-center gap-1"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Edit</span>
                    </button>
                </div>
            </div>

            <!-- Collapsible Raw Server Traceback -->
            <div x-show="showRawTrace" x-transition.duration.200ms class="mt-3 pt-3 border-t border-rose-500/20">
                <p class="text-[10px] font-mono uppercase tracking-wider text-rose-300 font-bold mb-1">Raw SMTP Handshake Response:</p>
                <div class="p-2.5 rounded-lg bg-black/50 border border-white/10 font-mono text-[11px] text-rose-200/90 overflow-x-auto select-all">
                    {{ $brokenRelay->last_error_message }}
                </div>
            </div>
        </div>
    @endif

    <!-- MAIN GRID: Block Design Architecture -->
    <div class="space-y-6">
        <!-- BLOCK 2: Live Server(s) / SMTP Relays Block -->
        <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl overflow-hidden shadow-xl">
            <!-- Block Header Bar -->
            <div class="border-b border-white/10 bg-slate-950/60 px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <h2 class="text-sm font-bold text-white tracking-tight">Live SMTP Gateways</h2>
                    <span class="px-2 py-0.2 rounded-full text-[10px] font-semibold bg-white/5 text-slate-300 border border-white/10">
                        {{ $relays->count() }} Configured
                    </span>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span class="inline-flex items-center gap-1.5 text-[11px] text-amber-300 font-medium">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Priority Failover Active (1 = Highest)
                    </span>
                </div>
            </div>

            @if ($relays->isEmpty())
                <!-- Minimal Empty State -->
                <div class="p-8 text-center">
                    <p class="text-sm font-semibold text-white">No SMTP relays configured</p>
                    <p class="text-xs text-slate-400 mt-1 mb-4">Add your Brevo or custom SMTP credentials to start sending verified emails.</p>
                    <button
                        type="button"
                        wire:click="openCreateModal"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow transition cursor-pointer"
                    >
                        <span>Add SMTP Relay</span>
                    </button>
                </div>
            @else
                <!-- Gateways List / Cards -->
                <div class="divide-y divide-white/5">
                    @foreach ($relays as $relay)
                        <div class="p-4 sm:p-5 transition hover:bg-white/[0.015] {{ $relay->isBroken() ? 'bg-rose-950/5' : '' }}">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                <!-- Server Info -->
                                <div class="flex items-start gap-3.5 min-w-0">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 font-black text-xs {{ $relay->priority === 1 ? 'bg-amber-400/20 text-amber-300 border border-amber-400/40' : 'bg-slate-800 text-slate-400 border border-white/10' }}" title="Priority: 1 = Highest">
                                        #{{ $relay->priority }}
                                    </div>

                                    <div class="space-y-1.5 min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-bold text-white tracking-tight">{{ $relay->name }}</h3>

                                            <!-- Live Status Badge -->
                                            @if ($relay->isBroken())
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400 animate-pulse"></span>
                                                    Connection Error
                                                </span>
                                            @elseif ($relay->isConnected())
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                    Live &amp; Connected ({{ $relay->latency_ms ?? 0 }}ms)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-800 text-slate-400 border border-white/10">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                                    Untested
                                                </span>
                                            @endif

                                            <!-- Active Toggle Pill -->
                                            <button
                                                type="button"
                                                wire:click="toggleActive({{ $relay->id }})"
                                                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider transition cursor-pointer {{ $relay->is_active ? 'bg-amber-400/10 text-amber-300 border border-amber-400/30 hover:bg-amber-400/20' : 'bg-slate-800 text-slate-500 border border-white/10 hover:text-slate-300' }}"
                                                title="Click to toggle active transmission state"
                                            >
                                                {{ $relay->is_active ? 'Active Outbound' : 'Disabled' }}
                                            </button>

                                            @if (empty($relay->workspace_id))
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                                    Global Fallback
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Server Credentials Tags -->
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-300">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-black/30 border border-white/10 font-mono text-[11px] text-slate-200">
                                                <span class="text-slate-400">Host:</span> {{ $relay->host }}:{{ $relay->port }}
                                                <span class="text-amber-400 font-bold ml-1 uppercase">{{ $relay->encryption }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-black/30 border border-white/10 text-[11px] text-slate-200">
                                                <span class="text-slate-400">Sender:</span> {{ $relay->sender_name }} &lt;{{ $relay->sender_email }}&gt;
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-black/30 border border-white/10 font-mono text-[11px] text-slate-200">
                                                <span class="text-slate-400">User:</span> {{ $relay->username }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-2 self-start lg:self-center shrink-0">
                                    <button
                                        type="button"
                                        wire:click="openTestModal({{ $relay->id }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 hover:text-white border border-amber-400/30 text-xs font-semibold transition cursor-pointer"
                                        title="Send a real-time verification test email"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        <span>Test Email Send</span>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="editRelay({{ $relay->id }})"
                                        class="p-1.5 rounded-xl bg-white/5 hover:bg-white/15 text-slate-300 hover:text-white border border-white/10 transition cursor-pointer"
                                        title="Edit Server Settings"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>

                                    <button
                                        type="button"
                                        wire:confirm="Delete SMTP relay '{{ $relay->name }}'?"
                                        wire:click="deleteRelay({{ $relay->id }})"
                                        class="p-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 hover:text-white border border-rose-500/20 transition cursor-pointer"
                                        title="Delete Relay"
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

        <!-- BLOCK 3: Email Activity & Delivery Tracker Block ("Which email is sent and which email fails") -->
        <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl overflow-hidden shadow-xl">
            <!-- Block Header & Delivery Counters -->
            <div class="border-b border-white/10 bg-slate-950/60 px-5 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                        <h2 class="text-sm sm:text-base font-bold text-white tracking-tight">Outbound Email Activity &amp; Delivery Log</h2>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">Track every lifecycle email transmission, inspect failures, and trigger safe retries.</p>
                </div>

                <!-- Telemetry Counters -->
                <div class="flex items-center gap-2 text-xs">
                    <span class="px-2.5 py-1 rounded-xl bg-white/5 border border-white/10 text-slate-300 font-medium">
                        Total: <strong class="text-white">{{ $totalLogCount }}</strong>
                    </span>
                    <span class="px-2.5 py-1 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 font-semibold flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Sent: <strong class="text-white">{{ $sentCount }}</strong>
                    </span>
                    <span class="px-2.5 py-1 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 font-semibold flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                        Failed: <strong class="text-white">{{ $failedCount }}</strong>
                    </span>
                    @if ($queuedCount > 0)
                        <span class="px-2.5 py-1 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 font-semibold flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                            Queued: <strong class="text-white">{{ $queuedCount }}</strong>
                        </span>
                    @endif
                </div>
            </div>

            <!-- Toolbar: Filter Pills & Search -->
            <div class="p-4 border-b border-white/5 bg-slate-900/40 flex flex-col sm:flex-row items-center justify-between gap-3">
                <!-- Status Segmented Pills -->
                <div class="flex items-center gap-1.5 self-start sm:self-auto bg-slate-950/60 p-1 rounded-xl border border-white/10 text-xs">
                    <button
                        type="button"
                        wire:click="$set('logFilter', 'all')"
                        class="px-3 py-1 rounded-lg font-semibold transition cursor-pointer {{ $logFilter === 'all' ? 'bg-amber-400/20 text-amber-300 border border-amber-400/40 shadow-xs' : 'text-slate-400 hover:text-white' }}"
                    >
                        All ({{ $totalLogCount }})
                    </button>
                    <button
                        type="button"
                        wire:click="$set('logFilter', 'sent')"
                        class="px-3 py-1 rounded-lg font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $logFilter === 'sent' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shadow-xs' : 'text-slate-400 hover:text-white' }}"
                    >
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Sent ({{ $sentCount }})
                    </button>
                    <button
                        type="button"
                        wire:click="$set('logFilter', 'failed')"
                        class="px-3 py-1 rounded-lg font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $logFilter === 'failed' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40 shadow-xs' : 'text-slate-400 hover:text-white' }}"
                    >
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                        Failed ({{ $failedCount }})
                    </button>
                    @if ($queuedCount > 0)
                        <button
                            type="button"
                            wire:click="$set('logFilter', 'queued')"
                            class="px-3 py-1 rounded-lg font-semibold transition cursor-pointer flex items-center gap-1.5 {{ $logFilter === 'queued' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40 shadow-xs' : 'text-slate-400 hover:text-white' }}"
                        >
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                            Queued ({{ $queuedCount }})
                        </button>
                    @endif
                </div>

                <!-- Search Input -->
                <div class="relative w-full sm:w-64">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="logSearch" 
                        placeholder="Search recipient or subject..." 
                        class="w-full pl-9 pr-3 py-1.5 rounded-xl bg-slate-950/60 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs"
                    >
                </div>
            </div>

            <!-- Email Transmission Table -->
            @if ($emailLogs->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-xs font-medium text-slate-400">No email delivery logs found matching the selected filter.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-white/5 bg-slate-950/40 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-2.5 px-4">Status</th>
                                <th class="py-2.5 px-4">Recipient</th>
                                <th class="py-2.5 px-4">Subject &amp; Milestone</th>
                                <th class="py-2.5 px-4">Delivery / Error Diagnostic</th>
                                <th class="py-2.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-slate-200">
                            @foreach ($emailLogs as $log)
                                <tr class="hover:bg-white/[0.02] transition">
                                    <!-- Status Pill -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        @if ($log->status === 'sent')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                                <svg class="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                Sent
                                            </span>
                                        @elseif ($log->status === 'failed')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse">
                                                <svg class="w-3 h-3 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                Failed
                                            </span>
                                        @elseif ($log->status === 'queued')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                                <svg class="w-3 h-3 text-amber-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                Queued
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[11px] bg-slate-800 text-slate-400">
                                                {{ ucfirst($log->status) }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Recipient -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="font-medium text-white">{{ $log->to_email }}</div>
                                        @if ($log->template_key)
                                            <div class="text-[10px] text-slate-400 font-mono">{{ $log->template_key }}</div>
                                        @endif
                                    </td>

                                    <!-- Subject -->
                                    <td class="py-3 px-4 max-w-xs truncate">
                                        <span class="text-white font-medium" title="{{ $log->subject }}">{{ $log->subject }}</span>
                                    </td>

                                    <!-- Delivery / Error Diagnostic -->
                                    <td class="py-3 px-4 text-xs">
                                        @if ($log->status === 'sent')
                                            <div class="text-emerald-300/90 font-mono text-[11px]">
                                                {{ $log->sent_at ? $log->sent_at->format('d M, h:i A') : 'Delivered' }}
                                            </div>
                                        @elseif ($log->status === 'failed')
                                            <div class="text-rose-300 font-mono text-[11px] truncate max-w-sm" title="{{ $log->failure_reason }}">
                                                {{ \Illuminate\Support\Str::limit($log->failure_reason ?: 'Connection error', 60) }}
                                            </div>
                                        @else
                                            <div class="text-slate-400 text-[11px]">Awaiting worker transmission</div>
                                        @endif
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if ($log->status === 'failed' || $log->status === 'queued')
                                                <button
                                                    type="button"
                                                    wire:click="retryEmail({{ $log->id }})"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 hover:text-white border border-amber-400/30 text-[11px] font-bold transition cursor-pointer"
                                                    title="Retry email dispatch now"
                                                >
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                    <span>Retry</span>
                                                </button>
                                            @endif

                                            <button
                                                type="button"
                                                wire:click="inspectMessage({{ $log->id }})"
                                                class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white border border-white/10 text-[11px] font-medium transition cursor-pointer"
                                                title="View Message Details"
                                            >
                                                Inspect
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Links -->
                <div class="p-3 border-t border-white/5 bg-slate-950/40">
                    {{ $emailLogs->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- MODAL 1: Add / Edit SMTP Relay (Matches Specification & Screenshot) -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-white/20 rounded-2xl max-w-2xl w-full shadow-2xl overflow-hidden animate-fadeIn text-slate-100">
                <!-- Modal Header -->
                <div class="border-b border-white/10 px-6 py-4 flex items-center justify-between bg-slate-950/70">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-amber-500/20 border border-amber-400/40 flex items-center justify-center text-amber-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <h2 class="text-base font-bold text-white tracking-tight">
                            {{ $editingRelayId ? 'Edit SMTP Relay Configuration' : 'Add New SMTP Relay' }}
                        </h2>
                    </div>
                    <button 
                        type="button" 
                        wire:click="closeModal" 
                        class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition cursor-pointer"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form wire:submit.prevent="saveRelay" class="p-5 sm:p-6 space-y-4">
                    <!-- Row 1: Server Name & Priority -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div class="sm:col-span-2 space-y-1">
                            <label class="block text-xs font-bold text-slate-200">Server Name *</label>
                            <input 
                                type="text" 
                                wire:model="name" 
                                placeholder="e.g. Brevo Primary" 
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs font-medium"
                                required
                            >
                            @error('name') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-200">Priority (1 = Highest) *</label>
                            <input 
                                type="number" 
                                wire:model="priority" 
                                min="1" 
                                max="999" 
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs font-medium"
                                required
                            >
                            @error('priority') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Row 2: Sender Email & Sender Display Name -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-200">Sender Email Address *</label>
                            <input 
                                type="email" 
                                wire:model="sender_email" 
                                placeholder="noreply@jaago.com.bd" 
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs font-medium"
                                required
                            >
                            @error('sender_email') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-200">Sender Display Name *</label>
                            <input 
                                type="text" 
                                wire:model="sender_name" 
                                placeholder="JAAGO HUB" 
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs font-medium"
                                required
                            >
                            @error('sender_name') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Row 3: SMTP Host & Port -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div class="sm:col-span-2 space-y-1">
                            <label class="block text-xs font-bold text-slate-200">SMTP Host *</label>
                            <input 
                                type="text" 
                                wire:model="host" 
                                placeholder="smtp-relay.brevo.com" 
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white font-mono placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs"
                                required
                            >
                            @error('host') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-200">Port *</label>
                            <input 
                                type="number" 
                                wire:model="port" 
                                placeholder="587" 
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white font-mono focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs"
                                required
                            >
                            @error('port') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Row 4: Encryption Protocol -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-200">Encryption Protocol</label>
                        <select 
                            wire:model="encryption" 
                            class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs font-medium"
                        >
                            <option value="starttls">STARTTLS (Port 587 — Recommended)</option>
                            <option value="ssl">SSL/TLS (Port 465)</option>
                            <option value="none">None / Plain (Port 25/587)</option>
                        </select>
                        @error('encryption') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Row 5: SMTP Username -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-200">SMTP Username *</label>
                        <input 
                            type="text" 
                            wire:model="username" 
                            placeholder="nasif.kamal@jaago.com.bd" 
                            class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs font-mono"
                            required
                        >
                        @error('username') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Row 6: SMTP Password with Reveal Toggle -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-200">SMTP Password *</label>
                            <button 
                                type="button" 
                                wire:click="$toggle('revealPassword')" 
                                class="text-[11px] font-semibold text-amber-400 hover:text-amber-300 inline-flex items-center gap-1 cursor-pointer transition"
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
                            placeholder="{{ $editingRelayId ? 'Leave blank to keep existing password' : 'Enter SMTP password or API Master Key' }}" 
                            class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white font-mono focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs"
                            {{ $editingRelayId ? '' : 'required' }}
                        >
                        @error('password') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Brevo Credentials Guide Box -->
                    <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-400/25 text-xs text-amber-200/90 space-y-1">
                        <div class="flex items-center gap-1.5 font-bold text-amber-300 text-[11px]">
                            <span>💡 Brevo SMTP Credentials Guide:</span>
                        </div>
                        <ul class="list-disc list-inside space-y-0.5 text-[11px] text-amber-100/90 pl-1">
                            <li><span class="font-semibold text-white">SMTP Username:</span> Your registered Brevo account email.</li>
                            <li><span class="font-semibold text-white">SMTP Password:</span> Your Brevo SMTP Master Key (found in Brevo Dashboard &gt; SMTP &amp; API &gt; SMTP Keys, usually starting with <code class="px-1 py-0.2 rounded bg-slate-950 text-amber-300 font-mono text-[10px]">xsmtpsib-...</code>).</li>
                        </ul>
                    </div>

                    <!-- Row 7: Throttle & Reply-To -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-200">Throttle Interval (Seconds / Recipient)</label>
                            <input 
                                type="number" 
                                wire:model="throttle_seconds" 
                                min="0" 
                                max="3600" 
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs font-medium"
                            >
                            @error('throttle_seconds') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-200">Reply-To Address (Optional)</label>
                            <input 
                                type="email" 
                                wire:model="reply_to" 
                                placeholder="pnc@jaago.com.bd" 
                                class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs font-medium"
                            >
                            @error('reply_to') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Checkbox: Enable this SMTP relay -->
                    <div class="pt-1 flex items-center gap-2">
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
                    <div class="pt-3 border-t border-white/10 flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white text-xs font-semibold transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs rounded-xl shadow transition cursor-pointer disabled:opacity-50"
                        >
                            <svg wire:loading.remove wire:target="saveRelay" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg wire:loading wire:target="saveRelay" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
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

    <!-- MODAL 2: Test SMTP Connection & Send Diagnostic Email -->
    @if ($showTestModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-white/20 rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden animate-fadeIn text-slate-100">
                <div class="border-b border-white/10 px-5 py-3.5 flex items-center justify-between bg-slate-950/70">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-amber-500/20 border border-amber-400/40 flex items-center justify-center text-amber-400">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h2 class="text-sm font-bold text-white">Test SMTP Gateway Handshake</h2>
                    </div>
                    <button 
                        type="button" 
                        wire:click="closeTestModal" 
                        class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition cursor-pointer"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-3.5">
                    <p class="text-xs text-slate-300">
                        Tests socket connectivity, TLS negotiation, and SMTP credentials. Optionally sends a real verification email.
                    </p>

                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-200">Recipient Email Address (Optional for test send)</label>
                        <input 
                            type="email" 
                            wire:model="testRecipientEmail" 
                            placeholder="admin@emkcenter.org" 
                            class="w-full px-3 py-2 rounded-xl bg-slate-950/60 border border-white/15 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400 text-xs font-medium"
                        >
                        @error('testRecipientEmail') <p class="text-[11px] text-rose-300 font-medium">{{ $message }}</p> @enderror
                    </div>

                    @if ($testResult)
                        @if ($testResult['success'])
                            <div class="p-3.5 rounded-xl bg-emerald-500/15 border border-emerald-400/40 text-emerald-200 space-y-1 text-xs">
                                <div class="flex items-center gap-1.5 font-bold text-emerald-300">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Handshake Verified Successfully</span>
                                </div>
                                <p class="text-emerald-100">{{ $testResult['message'] }}</p>
                                <p class="text-[11px] text-emerald-300/80">Round-trip latency: <strong>{{ $testResult['latency_ms'] }} ms</strong></p>
                            </div>
                        @else
                            <div class="p-3.5 rounded-xl bg-rose-500/15 border border-rose-400/40 text-rose-200 space-y-1.5 text-xs">
                                <div class="flex items-center gap-1.5 font-bold text-rose-300">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span>Connection Handshake Failed</span>
                                </div>
                                <div class="p-2 rounded-lg bg-black/40 border border-rose-500/30 font-mono text-[11px] text-rose-200">
                                    {{ $testResult['message'] }}
                                </div>
                            </div>
                        @endif
                    @endif

                    <div class="pt-2 border-t border-white/10 flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            wire:click="closeTestModal"
                            class="px-3.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white text-xs font-semibold transition cursor-pointer"
                        >
                            Close
                        </button>
                        <button
                            type="button"
                            wire:click="runTestConnection"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs rounded-xl shadow transition cursor-pointer disabled:opacity-50"
                        >
                            <svg wire:loading.remove wire:target="runTestConnection" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <svg wire:loading wire:target="runTestConnection" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Run Diagnostic Test</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 3: Inspect Email Message Details -->
    @if ($showMessageModal && $activeViewingMessage)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-white/20 rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden animate-fadeIn text-slate-100">
                <div class="border-b border-white/10 px-5 py-3.5 flex items-center justify-between bg-slate-950/70">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-indigo-500/20 border border-indigo-400/40 flex items-center justify-center text-indigo-300">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h2 class="text-sm font-bold text-white">Email Transmission Details</h2>
                    </div>
                    <button 
                        type="button" 
                        wire:click="closeMessageModal" 
                        class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition cursor-pointer"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-3.5 text-xs">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-slate-400">
                            <span>Status:</span>
                            @if ($activeViewingMessage->status === 'sent')
                                <span class="px-2 py-0.5 rounded-full font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">Delivered</span>
                            @elseif ($activeViewingMessage->status === 'failed')
                                <span class="px-2 py-0.5 rounded-full font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40">Delivery Failed</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">{{ ucfirst($activeViewingMessage->status) }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between text-slate-400">
                            <span>Recipient:</span>
                            <span class="font-mono text-white">{{ $activeViewingMessage->to_email }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-400">
                            <span>Subject:</span>
                            <span class="text-white font-medium truncate max-w-[280px]">{{ $activeViewingMessage->subject }}</span>
                        </div>
                        @if ($activeViewingMessage->template_key)
                            <div class="flex items-center justify-between text-slate-400">
                                <span>Template Key:</span>
                                <span class="font-mono text-amber-300">{{ $activeViewingMessage->template_key }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between text-slate-400">
                            <span>Timestamp:</span>
                            <span class="text-slate-200">{{ $activeViewingMessage->created_at?->format('d M Y, h:i:s A') }} BST</span>
                        </div>
                    </div>

                    @if ($activeViewingMessage->failure_reason)
                        <div class="p-3 rounded-xl bg-rose-950/40 border border-rose-500/30 text-rose-200 space-y-1">
                            <span class="text-[10px] font-mono uppercase tracking-wider font-bold text-rose-400">Failure Diagnostics:</span>
                            <p class="font-mono text-[11px] select-all leading-relaxed">{{ $activeViewingMessage->failure_reason }}</p>
                        </div>
                    @endif

                    @if ($activeViewingMessage->body_text)
                        <div class="space-y-1">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Message Content:</span>
                            <div class="p-3 rounded-xl bg-black/40 border border-white/10 text-slate-300 text-xs font-mono max-h-40 overflow-y-auto whitespace-pre-wrap">
                                {{ $activeViewingMessage->body_text }}
                            </div>
                        </div>
                    @endif

                    <div class="pt-2 border-t border-white/10 flex items-center justify-between">
                        @if ($activeViewingMessage->status === 'failed')
                            <button
                                type="button"
                                wire:click="retryEmail({{ $activeViewingMessage->id }})"
                                class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow transition cursor-pointer"
                            >
                                Retry Delivery
                            </button>
                        @else
                            <span></span>
                        @endif

                        <button
                            type="button"
                            wire:click="closeMessageModal"
                            class="px-3.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white text-xs font-semibold transition cursor-pointer"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
