<div class="space-y-6">
    <!-- Breadcrumbs & Form Header -->
    <div class="sm:flex sm:items-center sm:justify-between pb-4 border-b border-slate-200">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 mb-2">
                <a href="{{ route('crm.forms.index', ['workspace' => $workspace]) }}" class="hover:text-indigo-600 transition-colors">Forms</a>
                <span>/</span>
                <span class="text-slate-800 font-medium">{{ $form->title }}</span>
                <span>/</span>
                <span class="text-slate-900 font-semibold">Submissions</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Form Submissions</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $form->is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                    {{ $form->is_published ? 'Published' : 'Draft' }}
                </span>
                <span class="text-xs font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                    v{{ $form->version }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                Workspace: <span class="font-semibold text-slate-700">{{ $workspace }}</span>
                &bull; Form: <span class="font-mono text-xs text-slate-700">{{ $form->slug }}</span>
            </p>
        </div>

        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <a
                href="{{ \Illuminate\Support\Facades\Route::has('crm.forms.submissions.export') ? route('crm.forms.submissions.export', ['workspace' => $workspace, 'form' => $form->id, 'search' => $search, 'status' => $statusFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo]) : '#' }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors"
                id="btn-export-csv"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export CSV (Audited)
            </a>
            <a
                href="{{ route('crm.forms.index', ['workspace' => $workspace]) }}"
                class="px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
            >
                Back to Forms
            </a>
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Submissions</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($metrics['total']) }}</p>
                </div>
                <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-lg">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Pending Triage</p>
                    <p class="text-2xl font-bold text-amber-700 mt-1">{{ number_format($metrics['pending']) }}</p>
                </div>
                <div class="p-2.5 bg-amber-50 text-amber-600 rounded-lg">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-emerald-600 uppercase tracking-wider">Processed Leads</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($metrics['processed']) }}</p>
                </div>
                <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-lg">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-rose-600 uppercase tracking-wider">Failed / Incomplete</p>
                    <p class="text-2xl font-bold text-rose-700 mt-1">{{ number_format($metrics['failed']) }}</p>
                </div>
                <div class="p-2.5 bg-rose-50 text-rose-600 rounded-lg">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <!-- Search Input -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-medium text-slate-600 mb-1">Search Applications</label>
                <div class="relative">
                    <input
                        type="text"
                        id="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search name, email, phone, bKash TxID..."
                        class="w-full text-sm rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pl-9"
                    />
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label for="statusFilter" class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <select
                    id="statusFilter"
                    wire:model.live="statusFilter"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">All Statuses</option>
                    <option value="submitted">Submitted (Pending)</option>
                    <option value="processed">Processed (Lead Created)</option>
                    <option value="failed">Failed</option>
                </select>
            </div>

            <!-- Date Range From -->
            <div>
                <label for="dateFrom" class="block text-xs font-medium text-slate-600 mb-1">Date From</label>
                <input
                    type="date"
                    id="dateFrom"
                    wire:model.live="dateFrom"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <!-- Date Range To -->
            <div>
                <label for="dateTo" class="block text-xs font-medium text-slate-600 mb-1">Date To</label>
                <input
                    type="date"
                    id="dateTo"
                    wire:model.live="dateTo"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
        </div>

        @if($isSuperadmin)
            <!-- Superadmin Workspace Scope Filter -->
            <div class="pt-3 border-t border-slate-100 flex items-center gap-3">
                <span class="text-xs font-medium text-purple-700 bg-purple-50 px-2 py-1 rounded border border-purple-200">
                    Superadmin Authority
                </span>
                <label for="selectedWorkspace" class="text-xs font-medium text-slate-600">Filter Workspace:</label>
                <select
                    id="selectedWorkspace"
                    wire:model.live="selectedWorkspace"
                    class="text-sm rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 py-1"
                >
                    <option value="">All Workspaces</option>
                    @foreach($workspaces as $ws)
                        <option value="{{ $ws->id }}">{{ $ws->name }} ({{ $ws->id }})</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <!-- Submissions Table Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Submitted At</th>
                        <th class="px-4 py-3 text-left">Applicant Name</th>
                        <th class="px-4 py-3 text-left">Email & Phone</th>
                        <th class="px-4 py-3 text-left">Target Space</th>
                        <th class="px-4 py-3 text-left">bKash TxID</th>
                        <th class="px-4 py-3 text-center">Attached Files</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 divide-opacity-60">
                    @forelse($submissions as $sub)
                        @php
                            $payload = $sub->payload ?? [];
                            $lead = $sub->lead;
                            $firstName = $lead?->first_name ?? ($payload['first_name'] ?? '');
                            $lastName = $lead?->last_name ?? ($payload['last_name'] ?? '');
                            $fullName = trim($firstName . ' ' . $lastName) ?: 'N/A';
                            $email = $lead?->email ?? ($payload['email'] ?? 'N/A');
                            $phone = $lead?->contact_number ?? ($payload['contact_number'] ?? '—');
                            $space = $lead?->target_space_code ?? ($payload['american_space'] ?? '—');
                            $txid = $lead?->bkash_txid ?? ($payload['bkash_txid'] ?? '—');
                            $filesCount = $lead ? $lead->attachments->count() : 0;
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors" wire:key="sub-row-{{ $sub->id }}">
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap font-mono text-xs">
                                {{ $sub->submitted_at ? $sub->submitted_at->format('Y-m-d H:i') : $sub->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-900 whitespace-nowrap">
                                {{ $fullName }}
                            </td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                                <div class="text-xs font-mono text-slate-800">{{ $email }}</div>
                                <div class="text-xs text-slate-500">{{ $phone }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 border border-slate-200">
                                    {{ $space }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono text-xs font-semibold text-pink-700">
                                {{ $txid }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if($filesCount > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        {{ $filesCount }} file{{ $filesCount > 1 ? 's' : '' }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if($sub->status === 'processed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Processed
                                    </span>
                                @elseif($sub->status === 'submitted')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        Submitted
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                        Failed
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    wire:click="inspectSubmission({{ $sub->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-medium transition-colors"
                                    id="btn-inspect-{{ $sub->id }}"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Inspect 31 Fields
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-sm font-medium text-slate-700">No submissions found</p>
                                    <p class="text-xs text-slate-400">No records match the current filter criteria or workspace boundary.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($submissions->hasPages())
            <div class="px-4 py-3 border-t border-slate-200 bg-slate-50">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>

    <!-- 31-Field Inspector Modal -->
    @if($showModal && $selectedSubmission)
        @php
            $payload = $selectedSubmission->payload ?? [];
            $lead = $selectedSubmission->lead;
            $getVal = fn($key, $fallback = '—') => $lead?->{$key} ?? ($payload[$key] ?? $fallback);
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-slate-200">
                    <!-- Modal Header -->
                    <div class="bg-slate-900 px-6 py-4 flex items-center justify-between text-white">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold" id="modal-title">Application Inspector (All 31 Fields)</h3>
                                <span class="px-2 py-0.5 rounded text-xs font-mono bg-slate-800 text-indigo-300 border border-slate-700">
                                    {{ $selectedSubmission->uuid }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Submitted: {{ $selectedSubmission->submitted_at ? $selectedSubmission->submitted_at->format('Y-m-d H:i:s') : $selectedSubmission->created_at }}
                                &bull; Workspace: {{ $selectedSubmission->workspace_id ?? 'Staging Pool' }}
                                &bull; Status: {{ strtoupper($selectedSubmission->status) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="text-slate-400 hover:text-white transition-colors p-1 rounded-lg hover:bg-slate-800"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body (Categorized 31 Fields) -->
                    <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                        <!-- Category 1: Personal Details -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                1. Personal Identity Details
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <span class="text-slate-500 block">First Name (1)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('first_name') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Middle Name (2)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('middle_name') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Last Name (3)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('last_name') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Date of Birth (7)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('date_of_birth') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Calculated Age</span>
                                    <span class="font-semibold text-slate-900">
                                        {{ $lead?->age_at_submission ?? (\Carbon\Carbon::hasFormat((string)$getVal('date_of_birth'), 'Y-m-d') ? \Carbon\Carbon::parse((string)$getVal('date_of_birth'))->age : '—') }} years
                                    </span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Sex (8)</span>
                                    <span class="font-semibold text-slate-900">{{ ucfirst((string) $getVal('sex')) }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Youth Consent (10)</span>
                                    @php
                                        $hasConsent = $lead?->has_parental_consent ?? (!empty($payload['has_parental_consent']) || !empty($payload['parental_consent_file']));
                                        $ageVal = $lead?->age_at_submission ?? (\Carbon\Carbon::hasFormat((string)$getVal('date_of_birth'), 'Y-m-d') ? \Carbon\Carbon::parse((string)$getVal('date_of_birth'))->age : 25);
                                    @endphp
                                    @if($ageVal < 18)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $hasConsent ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                            {{ $hasConsent ? 'Minor - Consent Attached' : 'Minor - Missing Consent' }}
                                        </span>
                                    @else
                                        <span class="text-slate-600 font-medium">Adult (Not Required)</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Category 2: Address & Location -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                2. Residential Address & Geographic Division
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <span class="text-slate-500 block">House No / Street (11)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('address_house_no') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Road / Block / Sector (12)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('address_road_no') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Area / Thana (13)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('address_area') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Postal / Zip Code (14)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('address_zip_code') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Division (15)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('address_division') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">District (16 - Cascaded)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('address_district') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Category 3: Contact & Professional Details -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                3. Contact & Professional Background
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <span class="text-slate-500 block">Email Address (5)</span>
                                    <span class="font-semibold font-mono text-slate-900">{{ $getVal('email') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Primary Contact Number (6)</span>
                                    <span class="font-semibold font-mono text-slate-900">{{ $getVal('contact_number') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Personal Website (17)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('website') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Occupation (18)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('occupation') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Occupation (Other) (19)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('occupation_other') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Employer Name (27)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('employer_name') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Job Title (28)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('job_title') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Work Phone (29)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('work_phone') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Category 4: Education & US Experience -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                </svg>
                                4. Education & US Department of State / Academic Experience
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <span class="text-slate-500 block">Institution of Study (20)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('institution_study') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Highest Certification (21)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('highest_certification') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Highest Certification (Other) (22)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('highest_certification_other') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Area of Subject (23)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('area_of_subject') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Has US Experience (24)</span>
                                    <span class="font-semibold text-slate-900">{{ !empty($getVal('has_us_experience')) && $getVal('has_us_experience') !== '0' ? 'Yes' : 'No' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">US Experience Location (25)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('us_experience_location') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Is American Alumni (26)</span>
                                    <span class="font-semibold text-slate-900">{{ !empty($getVal('is_american_alumni')) && $getVal('is_american_alumni') !== '0' ? 'Yes' : 'No' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">American Institution Name</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('american_institution_name') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Category 5: Payment & Terms -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                5. Payment & Legal Terms
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <span class="text-slate-500 block">American Space (4)</span>
                                    <span class="font-semibold text-slate-900">{{ $getVal('american_space') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">bKash Transaction ID (30 - DEC-05)</span>
                                    <span class="font-mono font-bold text-pink-700 text-sm">{{ $getVal('bkash_txid') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block">Terms & Conditions (31)</span>
                                    <span class="font-semibold text-emerald-700">
                                        {{ !empty($getVal('terms_agreed')) ? 'Agreed & Accepted' : 'Pending' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Category 6: Attached Documents -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                6. Attached Files & Verification Evidence
                            </h4>
                            @if($lead && $lead->attachments->isNotEmpty())
                                <div class="divide-y divide-slate-200 border border-slate-200 rounded-lg bg-white overflow-hidden text-xs">
                                    @foreach($lead->attachments as $att)
                                        <div class="p-3 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded text-[11px] font-medium {{ $att->document_type === 'parental_consent' ? 'bg-amber-100 text-amber-800' : ($att->document_type === 'studio_photo' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800') }}">
                                                    {{ strtoupper(str_replace('_', ' ', $att->document_type)) }}
                                                </span>
                                                <span class="font-mono text-slate-800">{{ $att->original_filename }}</span>
                                                <span class="text-slate-400">({{ number_format($att->size_bytes / 1024, 1) }} KB)</span>
                                                @if($att->youth_protection)
                                                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-red-100 text-red-700 font-semibold">
                                                        Youth Protected
                                                    </span>
                                                @endif
                                            </div>
                                            <a
                                                href="{{ route('documents.download', ['workspace' => $workspace, 'attachment' => $att->id]) }}"
                                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-medium transition-colors"
                                                target="_blank"
                                            >
                                                Download / Inspect
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-xs text-slate-500 italic p-3 bg-white rounded border border-slate-200">
                                    No physical attachments stored or linked for this record.
                                </div>
                            @endif
                        </div>

                        <!-- Raw Submission JSON Payload -->
                        <div class="space-y-1">
                            <span class="text-xs font-semibold text-slate-700 block">Raw Submission Payload (JSON)</span>
                            <pre class="bg-slate-900 text-slate-100 text-[11px] p-3 rounded-lg overflow-x-auto max-h-48 font-mono"><code>{{ json_encode($selectedSubmission->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-slate-50 px-6 py-3 border-t border-slate-200 flex justify-end">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-100 transition-colors"
                        >
                            Close Inspector
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
