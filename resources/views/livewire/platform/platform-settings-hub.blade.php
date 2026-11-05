<div class="space-y-6">
    <!-- Breadcrumbs & Header -->
    <div class="sm:flex sm:items-center sm:justify-between pb-4 border-b border-slate-200">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 mb-2">
                <a href="/crm/{{ $workspaceId }}/dashboard" class="hover:text-indigo-600 transition-colors">CRM</a>
                <span>/</span>
                <span class="text-slate-500">Platform Services</span>
                <span>/</span>
                <span class="text-slate-900 font-semibold">Settings Hub</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Platform Settings Hub</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-mono font-medium bg-indigo-50 text-indigo-700 border border-indigo-200" id="badge-workspace">
                    {{ $workspaceId }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                Unified operational parameter configuration, credential management, and platform service administration per DASH-04 and ASVS V8.2.
            </p>
        </div>

        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <button
                type="button"
                wire:click="saveSettings"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-xs transition-colors disabled:opacity-50"
                id="btn-save-settings-top"
            >
                <svg wire:loading.remove wire:target="saveSettings" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg wire:loading wire:target="saveSettings" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Save Changes</span>
            </button>
        </div>
    </div>

    <!-- Flash Status Messages -->
    @if (session()->has('status'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between" id="alert-settings-status">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">{{ session('status') }}</span>
            </div>
        </div>
    @endif

    <!-- Sub-Navigation Modules Bar -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Administration & Platform Modules</h2>
        </div>
        <div class="p-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-8 gap-2">
            <a
                href="{{ route('crm.settings.index', $workspaceId) }}"
                class="flex flex-col items-center justify-center p-3 rounded-lg border text-center transition-colors bg-indigo-50 border-indigo-200 text-indigo-700 shadow-xs"
                id="subnav-parameters"
            >
                <svg class="w-5 h-5 mb-1.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
                <span class="text-xs font-semibold">System Parameters</span>
                <span class="text-[10px] text-indigo-500 font-mono mt-0.5">Active</span>
            </a>

            <a
                href="{{ route('crm.terms.index', $workspaceId) }}"
                class="flex flex-col items-center justify-center p-3 rounded-lg border text-center transition-colors bg-white border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300"
                id="subnav-terms"
            >
                <svg class="w-5 h-5 mb-1.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="text-xs font-semibold">Membership Terms</span>
                <span class="text-[10px] text-slate-400 font-mono mt-0.5">Terms & Fees</span>
            </a>

            <a
                href="{{ route('crm.staff.index', $workspaceId) }}"
                class="flex flex-col items-center justify-center p-3 rounded-lg border text-center transition-colors bg-white border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300"
                id="subnav-staff"
            >
                <svg class="w-5 h-5 mb-1.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span class="text-xs font-semibold">Staff & Delegations</span>
                <span class="text-[10px] text-slate-400 font-mono mt-0.5">Accounts</span>
            </a>

            <a
                href="{{ route('crm.email_templates.index', $workspaceId) }}"
                class="flex flex-col items-center justify-center p-3 rounded-lg border text-center transition-colors bg-white border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300"
                id="subnav-templates"
            >
                <svg class="w-5 h-5 mb-1.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <span class="text-xs font-semibold">Email Templates</span>
                <span class="text-[10px] text-slate-400 font-mono mt-0.5">Notifications</span>
            </a>

            <a
                href="{{ route('crm.email_delivery.index', $workspaceId) }}"
                class="flex flex-col items-center justify-center p-3 rounded-lg border text-center transition-colors bg-white border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300"
                id="subnav-delivery"
            >
                <svg class="w-5 h-5 mb-1.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span class="text-xs font-semibold">Delivery Monitor</span>
                <span class="text-[10px] text-slate-400 font-mono mt-0.5">Logs & Queues</span>
            </a>

            <a
                href="{{ route('crm.workflow_rules.index', $workspaceId) }}"
                class="flex flex-col items-center justify-center p-3 rounded-lg border text-center transition-colors bg-white border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300"
                id="subnav-workflow"
            >
                <svg class="w-5 h-5 mb-1.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span class="text-xs font-semibold">Workflow Rules</span>
                <span class="text-[10px] text-slate-400 font-mono mt-0.5">Automation</span>
            </a>

            <a
                href="{{ route('crm.backups.index', $workspaceId) }}"
                class="flex flex-col items-center justify-center p-3 rounded-lg border text-center transition-colors bg-white border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300"
                id="subnav-backups"
            >
                <svg class="w-5 h-5 mb-1.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <span class="text-xs font-semibold">Backups & Health</span>
                <span class="text-[10px] text-slate-400 font-mono mt-0.5">Recovery</span>
            </a>

            <a
                href="{{ route('crm.audit_logs.index', $workspaceId) }}"
                class="flex flex-col items-center justify-center p-3 rounded-lg border text-center transition-colors bg-white border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300"
                id="subnav-audit"
            >
                <svg class="w-5 h-5 mb-1.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <span class="text-xs font-semibold">Audit Logs</span>
                <span class="text-[10px] text-slate-400 font-mono mt-0.5">Trail & ASVS</span>
            </a>
        </div>
    </div>

    <!-- Parameter Tabs & Editor Card -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
        <!-- Tab Navigation Bar -->
        <div class="border-b border-slate-200 bg-slate-50 px-4 sm:px-6">
            <nav class="-mb-px flex space-x-6" aria-label="Settings Tabs">
                <button
                    type="button"
                    wire:click="switchTab('general')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'general' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
                    id="tab-general"
                >
                    General Parameters
                </button>

                <button
                    type="button"
                    wire:click="switchTab('membership')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'membership' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
                    id="tab-membership"
                >
                    Membership Rules
                </button>

                <button
                    type="button"
                    wire:click="switchTab('payments')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'payments' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
                    id="tab-payments"
                >
                    Payment Credentials
                </button>

                <button
                    type="button"
                    wire:click="switchTab('security')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'security' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
                    id="tab-security"
                >
                    Security & Sessions
                </button>
            </nav>
        </div>

        <!-- Form Body -->
        <form wire:submit.prevent="saveSettings" class="p-6 sm:p-8 space-y-6">
            <!-- GENERAL TAB -->
            @if ($activeTab === 'general')
                <div class="space-y-6" id="panel-general">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-base font-semibold text-slate-900">Organization & Branding</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Primary identification settings applied to portals, invoices, and communication templates.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div class="sm:col-span-4">
                            <label for="org_name" class="block text-sm font-medium text-slate-700">
                                Organization Display Name <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="org_name"
                                wire:model.defer="settings.general.organization_name"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="EMK Center"
                            />
                            <p class="mt-1 text-xs text-slate-500">Official legal and public-facing name for this center.</p>
                            @error('settings.general.organization_name')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-4">
                            <label for="contact_email" class="block text-sm font-medium text-slate-700">
                                Primary Contact Email <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="email"
                                id="contact_email"
                                wire:model.defer="settings.general.contact_email"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="info@emkcenter.org"
                            />
                            <p class="mt-1 text-xs text-slate-500">Official contact email included in outgoing member notifications and reply-to headers.</p>
                            @error('settings.general.contact_email')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-3">
                            <label for="default_locale" class="block text-sm font-medium text-slate-700">
                                Default System Locale <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="default_locale"
                                wire:model.defer="settings.general.default_locale"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                <option value="en">English (en)</option>
                                <option value="bn">Bengali (bn)</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Default fallback language for bilingual portal views and email dispatches.</p>
                            @error('settings.general.default_locale')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endif

            <!-- MEMBERSHIP TAB -->
            @if ($activeTab === 'membership')
                <div class="space-y-6" id="panel-membership">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-base font-semibold text-slate-900">Membership Lifecycle Rules</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Parameters controlling renewal eligibility windows, conversion reversals, and standard terms.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <label for="renewal_window_days" class="block text-sm font-medium text-slate-700">
                                Renewal Eligibility Window (Days) <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                id="renewal_window_days"
                                min="1"
                                max="365"
                                wire:model.defer="settings.membership.renewal_window_days"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                            <p class="mt-1 text-xs text-slate-500">Number of days prior to membership expiry when members may submit renewal applications (e.g. 30 days).</p>
                            @error('settings.membership.renewal_window_days')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-3">
                            <label for="max_reversals_allowed_days" class="block text-sm font-medium text-slate-700">
                                Max Conversion Reversal Window (Days) <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                id="max_reversals_allowed_days"
                                min="1"
                                max="365"
                                wire:model.defer="settings.membership.max_reversals_allowed_days"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                            <p class="mt-1 text-xs text-slate-500">Maximum allowed window to reverse an accidental lead-to-member conversion per DEC-08.</p>
                            @error('settings.membership.max_reversals_allowed_days')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-4">
                            <label for="default_term_code" class="block text-sm font-medium text-slate-700">
                                Default Membership Term Code <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="default_term_code"
                                wire:model.defer="settings.membership.default_term_code"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"
                                placeholder="STANDARD_1YR"
                            />
                            <p class="mt-1 text-xs text-slate-500">Initial membership term code assigned to approved public form submissions.</p>
                            @error('settings.membership.default_term_code')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endif

            <!-- PAYMENTS TAB -->
            @if ($activeTab === 'payments')
                <div class="space-y-6" id="panel-payments">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-base font-semibold text-slate-900">Payment Gateways & Manual bKash Configuration</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Parameters for manual mobile financial services, standard fee amounts, and encrypted gateway secrets per T-10-08.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <label for="bkash_merchant_number" class="block text-sm font-medium text-slate-700">
                                Manual bKash Merchant / Agent Number <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="bkash_merchant_number"
                                wire:model.defer="settings.payments.manual_bkash_merchant_number"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"
                                placeholder="01700000000"
                            />
                            <p class="mt-1 text-xs text-slate-500">Phone number displayed to applicants for manual offline payment reconciliation.</p>
                            @error('settings.payments.manual_bkash_merchant_number')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-3">
                            <label for="default_fee_bdt" class="block text-sm font-medium text-slate-700">
                                Default Application Fee (BDT) <span class="text-rose-500">*</span>
                            </label>
                            <div class="mt-1 relative rounded-lg shadow-xs">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-slate-500 sm:text-sm font-bold">৳</span>
                                </div>
                                <input
                                    type="text"
                                    id="default_fee_bdt"
                                    wire:model.defer="settings.payments.default_application_fee_bdt"
                                    class="block w-full pl-8 rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    placeholder="500.00"
                                />
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Standard application processing fee in Bangladeshi Taka.</p>
                            @error('settings.payments.default_application_fee_bdt')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-6">
                            <label for="instruction_text" class="block text-sm font-medium text-slate-700">
                                Payment Instruction Guide
                            </label>
                            <textarea
                                id="instruction_text"
                                rows="3"
                                wire:model.defer="settings.payments.instruction_text"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="Please send application fee via bKash Merchant Pay and retain Transaction ID..."
                            ></textarea>
                            <p class="mt-1 text-xs text-slate-500">Public instructions rendered on the submission checkout step for offline verification.</p>
                            @error('settings.payments.instruction_text')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-6 border-t border-slate-100 pt-4">
                            <div class="flex items-center justify-between mb-2">
                                <label for="bkash_secret" class="block text-sm font-medium text-slate-700">
                                    bKash Merchant API Secret Key (AES-256 Encrypted)
                                </label>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-800 border border-amber-200">
                                    Encrypted at Rest & Masked
                                </span>
                            </div>
                            <input
                                type="password"
                                id="bkash_secret"
                                wire:model.defer="settings.payments.bkash_secret"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono tracking-wider"
                                placeholder="••••••••"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                Protected credential. Leave as <code class="text-xs bg-slate-100 px-1 py-0.5 rounded font-mono">••••••••</code> to keep existing secret unchanged.
                            </p>
                            @error('settings.payments.bkash_secret')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endif

            <!-- SECURITY TAB -->
            @if ($activeTab === 'security')
                <div class="space-y-6" id="panel-security">
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-base font-semibold text-slate-900">Session Controls & Account Policies</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Parameters safeguarding administrative logins, session timeouts, and credential rotation per OWASP ASVS.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <label for="session_timeout_minutes" class="block text-sm font-medium text-slate-700">
                                Session Inactivity Timeout (Minutes) <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                id="session_timeout_minutes"
                                min="5"
                                max="1440"
                                wire:model.defer="settings.security.session_timeout_minutes"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                            <p class="mt-1 text-xs text-slate-500">Idle duration before active web sessions are automatically invalidated (e.g. 120 minutes).</p>
                            @error('settings.security.session_timeout_minutes')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-3">
                            <label for="require_password_reset_days" class="block text-sm font-medium text-slate-700">
                                Staff Password Rotation Interval (Days) <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                id="require_password_reset_days"
                                min="1"
                                max="365"
                                wire:model.defer="settings.security.require_password_reset_days"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                            <p class="mt-1 text-xs text-slate-500">Periodic password expiration interval requiring staff to set a new password (e.g. 90 days).</p>
                            @error('settings.security.require_password_reset_days')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endif

            <!-- Bottom Save Bar -->
            <div class="pt-6 border-t border-slate-200 flex items-center justify-between">
                <span class="text-xs text-slate-500">
                    Modifications will be immutably recorded in workspace audit logs per ASVS V8.2.
                </span>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-xs transition-colors disabled:opacity-50"
                    id="btn-save-settings"
                >
                    <svg wire:loading.remove wire:target="saveSettings" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg wire:loading wire:target="saveSettings" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Save Platform Settings</span>
                </button>
            </div>
        </form>
    </div>
</div>
