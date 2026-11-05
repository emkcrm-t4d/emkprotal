<div class="space-y-6">
    <!-- Breadcrumbs & Header -->
    <div class="sm:flex sm:items-center sm:justify-between pb-4 border-b border-slate-200">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 mb-2">
                <a href="/crm/{{ $workspaceId }}/dashboard" class="hover:text-indigo-600 transition-colors">CRM</a>
                <span>/</span>
                <a href="{{ route('crm.settings.index', $workspaceId) }}" class="hover:text-indigo-600 transition-colors">Platform Services</a>
                <span>/</span>
                <span class="text-slate-900 font-semibold">Backups & Disaster Recovery</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">System Backups & Recovery Health</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-mono font-medium bg-indigo-50 text-indigo-700 border border-indigo-200" id="badge-workspace">
                    {{ $workspaceId }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                Automated snapshot archives, AES-256 encryption, cryptographic checksum verification, and non-destructive recovery health monitoring (DASH-05, GAP-P1-06).
            </p>
        </div>

        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <button
                type="button"
                wire:click="triggerBackup"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-xs transition-colors disabled:opacity-50"
                id="btn-create-backup"
            >
                <svg wire:loading.remove wire:target="triggerBackup" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <svg wire:loading wire:target="triggerBackup" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="triggerBackup">Create On-Demand Backup</span>
                <span wire:loading wire:target="triggerBackup">Archiving & Encrypting...</span>
            </button>
        </div>
    </div>

    <!-- Flash Status Messages -->
    @if (session()->has('status'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between" id="alert-backup-status">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">{{ session('status') }}</span>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between" id="alert-backup-error">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span class="font-medium">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Verification Drill Banner -->
    @if ($verificationStatus === 'success')
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm flex items-start gap-3 shadow-xs" id="banner-verification-success">
            <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <div class="flex-1">
                <h4 class="font-semibold text-emerald-900">Recovery Integrity Drill: PASS</h4>
                <p class="mt-1 text-emerald-800 text-xs">{{ $verificationMessage }}</p>
            </div>
            <button type="button" wire:click="$set('verificationStatus', null)" class="text-emerald-500 hover:text-emerald-700">
                <span class="sr-only">Dismiss</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @elseif ($verificationStatus === 'failed')
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-sm flex items-start gap-3 shadow-xs" id="banner-verification-failed">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <h4 class="font-semibold text-rose-900">Recovery Integrity Drill: FAILED</h4>
                <p class="mt-1 text-rose-800 text-xs">{{ $verificationMessage }}</p>
            </div>
            <button type="button" wire:click="$set('verificationStatus', null)" class="text-rose-500 hover:text-rose-700">
                <span class="sr-only">Dismiss</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    <!-- Operational Metrics Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Archives</span>
                <span class="p-1.5 rounded-lg bg-indigo-50 text-indigo-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900" id="metric-total-count">{{ $metrics['total_count'] }}</span>
                <span class="text-xs text-slate-500">({{ $metrics['storage_used'] }})</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">Rolling {{ $metrics['retention_days'] }}-day retention policy</p>
        </div>

        <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Latest Backup</span>
                <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2">
                <span class="text-sm font-semibold text-slate-900 block truncate" id="metric-latest-timestamp">{{ $metrics['latest_backup'] }}</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">Scheduled daily at 02:00 BST</p>
        </div>

        <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Encryption Standard</span>
                <span class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-center gap-2">
                <span class="text-base font-bold text-slate-900">{{ $metrics['cipher'] }}</span>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                    ACTIVE
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">At-rest envelope encryption (T-10-10)</p>
        </div>

        <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Disaster Recovery SLO</span>
                <span class="p-1.5 rounded-lg bg-amber-50 text-amber-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-sm font-semibold text-slate-900">RPO &le; {{ $metrics['rpo_target'] }}h</span>
                <span class="text-xs text-slate-400">|</span>
                <span class="text-sm font-semibold text-slate-900">RTO &le; {{ $metrics['rto_target'] }}h</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">GAP-P1-06 disaster recovery SLA</p>
        </div>
    </div>

    <!-- Backups Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Available Platform Archives</h3>
                <p class="text-xs text-slate-500 mt-0.5">Encrypted backups with cryptographic SHA-256 manifests</p>
            </div>
            <span class="text-xs font-medium text-slate-500">
                Showing {{ count($backups) }} {{ Str::plural('archive', count($backups)) }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th scope="col" class="px-6 py-3">Archive Filename</th>
                        <th scope="col" class="px-6 py-3">Created Timestamp</th>
                        <th scope="col" class="px-6 py-3">Size</th>
                        <th scope="col" class="px-6 py-3">Security</th>
                        <th scope="col" class="px-6 py-3">SHA-256 Manifest</th>
                        <th scope="col" class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700 bg-white">
                    @forelse ($backups as $b)
                        <tr class="hover:bg-slate-50 transition-colors" id="row-backup-{{ $loop->index }}">
                            <td class="px-6 py-4 font-mono text-xs font-semibold text-slate-900">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    <span class="break-all">{{ $b['filename'] }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">
                                {{ $b['formatted_date'] }}
                            </td>
                            <td class="px-6 py-4 text-xs font-mono text-slate-700 whitespace-nowrap">
                                {{ $b['size_formatted'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($b['encrypted'])
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <svg class="w-3 h-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        AES-256
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                                        Unencrypted
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs font-mono text-slate-500">
                                @if ($b['sha256'])
                                    <span class="inline-block max-w-[140px] truncate align-middle text-slate-600 font-mono" title="{{ $b['sha256'] }}">
                                        {{ substr($b['sha256'], 0, 16) }}...
                                    </span>
                                @else
                                    <span class="text-slate-400 italic">No manifest</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Verify Button -->
                                    <button
                                        type="button"
                                        wire:click="runVerification('{{ $b['filename'] }}')"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors"
                                        title="Run non-destructive integrity drill"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        <span>Verify</span>
                                    </button>

                                    <!-- Download Button -->
                                    <a
                                        href="{{ route('crm.backups.download', [$workspaceId, $b['filename']]) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition-colors"
                                        title="Download encrypted archive"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        <span>Download</span>
                                    </a>

                                    <!-- Delete Button -->
                                    <button
                                        type="button"
                                        wire:click="deleteBackup('{{ $b['filename'] }}')"
                                        wire:confirm="Are you sure you want to delete this backup archive? This action cannot be undone."
                                        class="inline-flex items-center p-1.5 text-xs font-medium rounded-lg text-rose-600 hover:bg-rose-50 transition-colors"
                                        title="Delete archive"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                </svg>
                                <h3 class="mt-2 text-sm font-semibold text-slate-900">No backup archives found</h3>
                                <p class="mt-1 text-xs text-slate-500">Click "Create On-Demand Backup" above to generate an initial encrypted snapshot.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Disaster Recovery Quick Guide Panel -->
    <div class="p-6 bg-slate-900 text-slate-100 rounded-xl shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h4 class="font-semibold text-sm text-white">Disaster Recovery & Non-Destructive Restore Drill</h4>
            </div>
            <span class="text-xs font-mono text-slate-400">RPO &le; 1h | RTO &le; 4h</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-300">
            <div class="p-3 bg-slate-800/60 rounded-lg border border-slate-700/50 space-y-1">
                <span class="font-semibold text-indigo-300 block">1. CLI Verification</span>
                <p class="font-mono text-[11px] text-slate-300">php artisan crm:backup:verify {archive}</p>
                <p class="text-[11px] text-slate-400">Validates SHA-256 hash match, AES-256 decryption, and SQL dump existence.</p>
            </div>
            <div class="p-3 bg-slate-800/60 rounded-lg border border-slate-700/50 space-y-1">
                <span class="font-semibold text-indigo-300 block">2. Sandbox Restore Drill</span>
                <p class="font-mono text-[11px] text-slate-300">DB_DATABASE=emk_recovery_sandbox</p>
                <p class="text-[11px] text-slate-400">Test recovery in an isolated sandbox without affecting live production data.</p>
            </div>
            <div class="p-3 bg-slate-800/60 rounded-lg border border-slate-700/50 space-y-1">
                <span class="font-semibold text-indigo-300 block">3. Full Runbook Reference</span>
                <p class="text-[11px] text-slate-300">See <span class="font-mono text-indigo-300">docs/runbooks/BACKUP-AND-DISASTER-RECOVERY-RUNBOOK.md</span> for end-to-end failover protocol.</p>
            </div>
        </div>
    </div>
</div>
