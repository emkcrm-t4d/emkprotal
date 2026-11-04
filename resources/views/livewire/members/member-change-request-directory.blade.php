<div class="space-y-6">
    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('crm.members', ['workspace' => $workspace]) }}" class="hover:text-indigo-600 transition">Members</a>
                <span>&rsaquo;</span>
                <span class="font-semibold text-slate-700">Change Requests</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Member Profile Change Requests</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Review submitted contact and demographic updates, inspect field diffs, and transition approval states.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a
                href="{{ route('crm.members', ['workspace' => $workspace]) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 shadow-xs transition"
            >
                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Back to Members</span>
            </a>
        </div>
    </div>

    {{-- Flash Status Alert --}}
    @if (session('status'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- Metrics Strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 block">Total Requests</span>
            <span class="text-2xl font-bold text-slate-900 mt-1 block">{{ number_format($metrics['total']) }}</span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-amber-600 block">Pending Review</span>
            <span class="text-2xl font-bold text-amber-600 mt-1 block">{{ number_format($metrics['pending']) }}</span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600 block">Approved</span>
            <span class="text-2xl font-bold text-emerald-600 mt-1 block">{{ number_format($metrics['approved']) }}</span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-rose-600 block">Rejected</span>
            <span class="text-2xl font-bold text-rose-600 mt-1 block">{{ number_format($metrics['rejected']) }}</span>
        </div>
    </div>

    {{-- Filters & Search Bar --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            {{-- Keyword Search --}}
            <div class="relative w-full sm:w-64">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="Search member, ID, email..."
                >
            </div>

            {{-- Status Filter --}}
            <div class="w-full sm:w-40">
                <select
                    wire:model.live="statusFilter"
                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="pending">Pending Review</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="all">All Statuses</option>
                </select>
            </div>

            {{-- Type Filter --}}
            <div class="w-full sm:w-44">
                <select
                    wire:model.live="typeFilter"
                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="all">All Request Types</option>
                    <option value="general_update">General Update</option>
                    <option value="email_change">Email Change</option>
                    <option value="renewal">Membership Renewal</option>
                </select>
            </div>
        </div>

        <div>
            <button
                type="button"
                wire:click="resetFilters"
                class="text-xs font-semibold text-slate-500 hover:text-slate-800 transition px-2 py-1"
            >
                Reset Filters
            </button>
        </div>
    </div>

    {{-- Requests Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5">Submitted</th>
                        <th class="px-5 py-3.5">Member</th>
                        <th class="px-5 py-3.5">Type</th>
                        <th class="px-5 py-3.5">Justification</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5">Reviewer</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($requests as $requestItem)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-5 py-4 whitespace-nowrap text-slate-500 font-mono text-[11px]">
                                {{ $requestItem->created_at->format('M d, Y H:i') }}
                            </td>

                            <td class="px-5 py-4">
                                <div class="font-bold text-slate-900">
                                    {{ $requestItem->member?->full_name ?? '—' }}
                                </div>
                                <div class="text-[11px] font-mono text-indigo-600">
                                    {{ $requestItem->member?->member_id ?? '—' }}
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    {{ $requestItem->member?->email ?? '—' }}
                                </div>
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                @if ($requestItem->request_type === 'general_update')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                        General Profile
                                    </span>
                                @elseif ($requestItem->request_type === 'email_change')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-300">
                                        Email Address Change (Requires Manager Approval)
                                    </span>
                                @elseif ($requestItem->request_type === 'renewal')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        Membership Renewal
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700">
                                        {{ ucfirst($requestItem->request_type) }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-4 max-w-xs">
                                <p class="truncate text-slate-600" title="{{ $requestItem->justification }}">
                                    {{ $requestItem->justification }}
                                </p>
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                @if ($requestItem->status === 'pending')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        Pending Review
                                    </span>
                                @elseif ($requestItem->status === 'approved')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Approved
                                    </span>
                                @elseif ($requestItem->status === 'rejected')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Rejected
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-[11px]">
                                @if ($requestItem->reviewer)
                                    <div class="font-semibold text-slate-800">{{ $requestItem->reviewer->name }}</div>
                                    <div class="text-slate-400">{{ $requestItem->reviewed_at?->format('M d, Y H:i') }}</div>
                                @else
                                    <span class="text-slate-400 italic">Unassigned</span>
                                @endif
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-right">
                                <button
                                    type="button"
                                    wire:click="openDiffModal({{ $requestItem->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Review Diff</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="font-bold text-slate-700">No Change Requests Found</p>
                                    <p class="text-xs text-slate-400">
                                        No member change requests match the selected filters or search query.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($requests->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $requests->links() }}
            </div>
        @endif
    </div>

    {{-- Side-by-Side Diff Inspector Modal --}}
    @if ($isDiffModalOpen && $selectedRequest)
        <div
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="diff-modal-title"
            role="dialog"
            aria-modal="true"
        >
            {{-- Backdrop --}}
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                wire:click="closeDiffModal"
            ></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div
                    class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-3xl border border-slate-200"
                    @click.stop
                >
                    {{-- Header --}}
                    <div class="px-6 py-5 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-600/30 border border-indigo-400/30 flex items-center justify-center text-indigo-300">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white tracking-tight" id="diff-modal-title">
                                    Change Request Diff Inspector
                                </h3>
                                <p class="text-xs text-slate-300">
                                    Member: <span class="font-semibold text-white">{{ $selectedRequest->member?->full_name }}</span>
                                    <span class="mx-1">•</span>
                                    ID: <span class="font-mono text-indigo-300">{{ $selectedRequest->member?->member_id }}</span>
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="closeDiffModal"
                            class="rounded-lg p-1.5 text-slate-400 hover:text-white hover:bg-white/10 transition"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Modal Body --}}
                    <div class="p-6 space-y-5 max-h-[calc(85vh-160px)] overflow-y-auto">
                        {{-- Request Meta Summary --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                            <div>
                                <span class="text-slate-400 uppercase text-[10px] font-semibold block">Request Type</span>
                                <span class="font-bold text-slate-800 mt-0.5 block">{{ ucfirst(str_replace('_', ' ', $selectedRequest->request_type)) }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 uppercase text-[10px] font-semibold block">Status</span>
                                <span class="font-bold mt-0.5 block
                                    {{ $selectedRequest->status === 'approved' ? 'text-emerald-600' : ($selectedRequest->status === 'rejected' ? 'text-rose-600' : 'text-amber-600') }}">
                                    {{ ucfirst($selectedRequest->status) }}
                                </span>
                            </div>
                            <div>
                                <span class="text-slate-400 uppercase text-[10px] font-semibold block">Submitted Date</span>
                                <span class="font-bold text-slate-800 mt-0.5 block">{{ $selectedRequest->created_at->format('M d, Y H:i') }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 uppercase text-[10px] font-semibold block">Workspace</span>
                                <span class="font-mono text-slate-800 mt-0.5 block">{{ $selectedRequest->workspace_id }}</span>
                            </div>
                        </div>

                        {{-- Member Justification --}}
                        <div class="p-4 rounded-xl bg-indigo-50/50 border border-indigo-100 text-xs space-y-1">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 block">
                                Member's Submitted Justification
                            </span>
                            <p class="text-slate-800 italic">
                                &ldquo;{{ $selectedRequest->justification }}&rdquo;
                            </p>
                        </div>

                        {{-- Error Alert in Modal --}}
                        @if ($errorMessage)
                            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-start gap-2">
                                <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>{{ $errorMessage }}</span>
                            </div>
                        @endif

                        {{-- Email Change Security Alert Banner --}}
                        @if ($selectedRequest->request_type === 'email_change')
                            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1">
                                <div class="flex items-center gap-1.5 font-bold text-amber-950">
                                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Email Address Change (Requires Manager Approval)</span>
                                </div>
                                <p class="text-amber-800">
                                    This change request updates primary portal authentication credentials and contact email. Approving will atomically update both the Member and User tables. Authority is restricted to Workspace Managers and Superadmins per DEC-07.
                                </p>
                            </div>
                        @endif

                        {{-- Membership Renewal Verification Card --}}
                        @if ($selectedRequest->request_type === 'renewal')
                            @php
                                $today = \Carbon\Carbon::today();
                                $currExp = $selectedRequest->member?->expiry_date ? \Carbon\Carbon::parse($selectedRequest->member->expiry_date)->startOfDay() : null;
                                $durMonths = (int) ($selectedRequest->new_payload['duration_months'] ?? 12);
                                $isEarly = $currExp && $currExp->greaterThanOrEqualTo($today) && $selectedRequest->member?->status === 'active';
                                if ($isEarly) {
                                    $projectedNewExpiry = $currExp->copy()->addMonths($durMonths);
                                    $mathDesc = 'Early Renewal: Current expiry (' . $currExp->format('M d, Y') . ') + ' . $durMonths . ' months = ' . $projectedNewExpiry->format('M d, Y') . ' (0 days lost)';
                                } else {
                                    $projectedNewExpiry = $today->copy()->addMonths($durMonths);
                                    $mathDesc = 'Late Renewal: Today (' . $today->format('M d, Y') . ') + ' . $durMonths . ' months = ' . $projectedNewExpiry->format('M d, Y');
                                }
                            @endphp
                            <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-xs text-blue-900 space-y-2">
                                <div class="flex items-center gap-1.5 font-bold text-blue-950">
                                    <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <span>Membership Renewal Payment Verification</span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
                                    <div>
                                        <span class="text-slate-500 text-[10px] uppercase font-semibold block">Selected Term</span>
                                        <span class="font-bold text-slate-800">{{ $selectedRequest->new_payload['term_name'] ?? '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 text-[10px] uppercase font-semibold block">Renewal Fee</span>
                                        <span class="font-bold text-slate-800 font-mono">৳{{ number_format((float) ($selectedRequest->new_payload['fee_bdt'] ?? 0), 2) }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 text-[10px] uppercase font-semibold block">bKash TxID</span>
                                        <span class="font-bold text-indigo-700 font-mono">{{ $selectedRequest->new_payload['bkash_txid'] ?? '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-700 text-[10px] uppercase font-semibold block">Projected Expiry</span>
                                        <span class="font-bold text-blue-950 font-mono">{{ $projectedNewExpiry->format('M d, Y') }}</span>
                                    </div>
                                </div>
                                <p class="text-[11px] text-blue-700 font-medium">
                                    {{ $mathDesc }}. Approving will update validity, reset status to Active, and issue a paid Invoice.
                                </p>
                            </div>
                        @endif

                        {{-- Side-by-Side Diff Table --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Attribute Diff Inspection
                                </h4>
                                <span class="text-[11px] text-slate-400">Green highlights proposed modifications</span>
                            </div>

                            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3 w-1/3">Field Name</th>
                                            <th class="px-4 py-3 w-1/3">Current Value (DB)</th>
                                            <th class="px-4 py-3 w-1/3 bg-emerald-50/50 text-emerald-800">Proposed New Value</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @php
                                            $newPayload = $selectedRequest->new_payload ?? [];
                                            $oldPayload = $selectedRequest->old_payload ?? [];
                                            $fieldLabels = [
                                                'email' => 'Email Address (Portal Username & Notifications)',
                                                'contact_number' => 'Contact Number',
                                                'address_house_no' => 'House / Flat No.',
                                                'address_road_no' => 'Road / Street No.',
                                                'address_area' => 'Area / Neighborhood',
                                                'address_zip_code' => 'Postal / Zip Code',
                                                'address_division' => 'Division',
                                                'address_district' => 'District',
                                                'occupation' => 'Occupation',
                                                'institution_study' => 'Institution / Organization',
                                                'highest_certification' => 'Highest Certification',
                                                'area_of_subject' => 'Area of Subject',
                                                'term_id' => 'Membership Term ID',
                                                'term_name' => 'Selected Membership Term',
                                                'duration_months' => 'Term Duration (Months)',
                                                'fee_bdt' => 'Membership Fee (BDT)',
                                                'bkash_txid' => 'bKash Transaction ID',
                                            ];
                                        @endphp

                                        @forelse ($newPayload as $fieldKey => $proposedVal)
                                            <tr class="hover:bg-slate-50/50">
                                                <td class="px-4 py-3 font-semibold text-slate-800">
                                                    {{ $fieldLabels[$fieldKey] ?? ucwords(str_replace('_', ' ', $fieldKey)) }}
                                                </td>
                                                <td class="px-4 py-3 text-slate-600 font-mono text-[11px]">
                                                    {{ $oldPayload[$fieldKey] ?? $selectedRequest->member?->{$fieldKey} ?? '—' }}
                                                </td>
                                                <td class="px-4 py-3 bg-emerald-50 text-emerald-950 font-semibold font-mono text-[11px] border-l border-emerald-100">
                                                    {{ $proposedVal !== null && $proposedVal !== '' ? $proposedVal : '—' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="px-4 py-6 text-center text-slate-400">
                                                    No specific attribute changes recorded in payload.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Decision Details if already reviewed --}}
                        @if ($selectedRequest->status !== 'pending')
                            <div class="p-4 rounded-xl border {{ $selectedRequest->status === 'approved' ? 'bg-emerald-50/50 border-emerald-200' : 'bg-rose-50/50 border-rose-200' }} text-xs space-y-2">
                                <div class="flex items-center gap-2 font-bold {{ $selectedRequest->status === 'approved' ? 'text-emerald-900' : 'text-rose-900' }}">
                                    @if ($selectedRequest->status === 'approved')
                                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Approved by {{ $selectedRequest->reviewer?->name ?? 'Staff' }}</span>
                                    @else
                                        <svg class="w-4 h-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <span>Rejected by {{ $selectedRequest->reviewer?->name ?? 'Staff' }}</span>
                                    @endif
                                    <span class="font-normal text-slate-500">on {{ $selectedRequest->reviewed_at?->format('M d, Y H:i') }}</span>
                                </div>

                                @if ($selectedRequest->manager_notes)
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Manager Notes:</span>
                                        <p class="text-slate-600 mt-0.5">{{ $selectedRequest->manager_notes }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Reviewer Actions Input Area (when Pending) --}}
                        @if ($selectedRequest->status === 'pending' && $canApproveOrReject)
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                                        Rejection Explanation Notes <span class="text-slate-400 font-normal">(Mandatory if rejecting — min 5 characters)</span>
                                    </label>
                                    <textarea
                                        wire:model="rejectionNotes"
                                        rows="2"
                                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                                        placeholder="Reason for rejecting this change request..."
                                    ></textarea>
                                    @error('rejectionNotes') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Actions Footer --}}
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            wire:click="closeDiffModal"
                            class="px-4 py-2 text-xs font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition"
                        >
                            Close
                        </button>

                        @if ($selectedRequest->status === 'pending' && $canApproveOrReject)
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="reject"
                                    wire:loading.attr="disabled"
                                    class="px-4 py-2 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl transition cursor-pointer"
                                >
                                    <span wire:loading.remove wire:target="reject">Reject Request</span>
                                    <span wire:loading wire:target="reject">Processing...</span>
                                </button>

                                @if ($selectedRequest->request_type === 'email_change' && ! $canApproveSelected)
                                    <button
                                        type="button"
                                        disabled
                                        class="px-4 py-2 text-xs font-semibold text-slate-400 bg-slate-100 border border-slate-200 rounded-xl cursor-not-allowed"
                                        title="Email change requests require Workspace Manager or Superadmin approval."
                                    >
                                        Approve (Manager Only)
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="approve"
                                        wire:loading.attr="disabled"
                                        class="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-xl shadow-xs transition cursor-pointer"
                                    >
                                        <span wire:loading.remove wire:target="approve">Approve Changes</span>
                                        <span wire:loading wire:target="approve">Applying...</span>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
