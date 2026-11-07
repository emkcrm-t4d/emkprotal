<div class="space-y-6">
    <!-- Breadcrumbs & Profile Header -->
    <div class="pb-4 border-b border-white/10">
        <nav class="flex items-center gap-2 text-xs text-slate-400 mb-2">
            <a href="{{ route('crm.leads', ['workspace' => $workspace]) }}" class="hover:text-amber-400 transition-colors">Leads</a>
            <span class="text-slate-600">/</span>
            <span class="text-white font-semibold">{{ $lead->full_name }}</span>
        </nav>

        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $lead->full_name }}</h1>

                    @php
                        $statusBadgeClass = match($lead->status) {
                            'submitted' => 'bg-blue-50 text-blue-700 border-blue-200',
                            'under_review' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                            'missing_items' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'converted' => 'bg-purple-50 text-purple-700 border-purple-200',
                            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                            'blocked' => 'bg-zinc-100 text-zinc-700 border-zinc-300',
                            default => 'bg-slate-100 text-slate-700 border-slate-200',
                        };
                    @endphp
                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadgeClass }}">
                        {{ ucwords(str_replace('_', ' ', $lead->status)) }}
                    </span>

                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-mono font-bold bg-pink-50 text-pink-700 border border-pink-200" title="bKash Transaction ID">
                        TxID: {{ $lead->bkash_txid }}
                    </span>

                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                        Space: {{ $lead->target_space_code }}
                    </span>

                    @if($lead->age_at_submission < 18)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300">
                            Minor Applicant (Age {{ $lead->age_at_submission }}) - DEC-09 Protected
                        </span>
                    @endif
                </div>
            </div>

            <div class="mt-4 sm:mt-0 flex items-center gap-2">
                @php
                    $canConvertLead = auth()->user()?->can('leads.convert') || auth()->user()?->hasRole(['superadmin', 'global_admin', 'workspace_manager']);
                    $conversionAllowed = $this->isConversionAllowed;
                @endphp

                @if($lead->status === 'converted')
                    <a
                        href="{{ route('crm.members', ['workspace' => $workspace]) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-purple-100 hover:bg-purple-200 text-purple-800 border border-purple-300 transition-colors shadow-xs"
                        id="badge-converted-member"
                    >
                        <svg class="w-3.5 h-3.5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span>Converted to Member: {{ $lead->convertedMember?->member_id ?? $lead->converted_member_id }}</span>
                    </a>

                    @php
                        $canReverseConversion = auth()->user()?->can('leads.reverse_conversion') || auth()->user()?->hasRole(['superadmin', 'global_admin', 'workspace_manager']);
                        $isReversalEligible = $this->isReversalAllowed;
                    @endphp

                    @if($canReverseConversion && $isReversalEligible)
                        <button
                            type="button"
                            wire:click="openReversalModal"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 transition-colors shadow-xs"
                            id="btn-reverse-conversion"
                        >
                            <svg class="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Reverse Conversion</span>
                        </button>
                    @endif
                @elseif(!$canConvertLead)
                    <button
                        type="button"
                        disabled
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-slate-800 text-slate-500 border border-white/10 cursor-not-allowed shadow-xs"
                        title="Conversion requires Workspace Manager authority."
                        id="btn-convert-lead-disabled-auth"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Convert to Member</span>
                    </button>
                @elseif($conversionAllowed)
                    <button
                        type="button"
                        wire:click="openConversionModal"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition-colors"
                        id="btn-convert-lead"
                    >
                        <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Convert to Member</span>
                    </button>
                @else
                    @if($this->canApproveLead)
                        <button
                            type="button"
                            wire:click="approveLead"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg transition-all animate-pulse"
                            id="btn-header-approve-lead"
                            title="Payment, Document, and Photo streams are verified. Click to approve lead for induction!"
                        >
                            <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Approve for Induction</span>
                        </button>
                    @endif

                    <button
                        type="button"
                        disabled
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-slate-800 text-slate-500 border border-white/10 cursor-not-allowed shadow-xs"
                        title="All 3 verifications (Payment, Document, Photo) and Manager Approval must be verified to convert."
                        id="btn-convert-lead-disabled-invariant"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Convert to Member</span>
                    </button>
                @endif

                @if(auth()->user()?->can('leads.communicate') || auth()->user()?->hasRole(['superadmin', 'global_admin', 'workspace_manager', 'delegated_staff']))
                    <button
                        type="button"
                        wire:click="openMissingItemsModal"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 transition-colors shadow-xs"
                        id="btn-open-missing-items"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Request Missing Items</span>
                    </button>
                @endif

                @if(auth()->user()?->can('leads.reject') || auth()->user()?->hasRole(['superadmin', 'global_admin', 'workspace_manager']))
                    <button
                        type="button"
                        wire:click="openRejectModal"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 transition-colors shadow-xs"
                        id="btn-open-reject-lead"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        <span>Reject Application</span>
                    </button>
                @endif

                @if(auth()->user()?->can('leads.edit') || auth()->user()?->hasRole(['superadmin', 'global_admin', 'workspace_manager']))
                    <button
                        type="button"
                        wire:click="toggleEditMode"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium border transition-colors {{ $editMode ? 'bg-slate-100 text-slate-700 border-slate-300' : 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border-indigo-200' }}"
                        id="btn-toggle-edit"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span>{{ $editMode ? 'Cancel Edit' : 'Edit Contact Details' }}</span>
                    </button>
                @endif

                <a
                    href="{{ route('crm.leads', ['workspace' => $workspace]) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg text-xs font-semibold text-white transition-colors shadow-sm"
                >
                    <svg class="w-3.5 h-3.5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Back to Directory</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Flash message status notification -->
    @if (session('status'))
        <div class="bg-emerald-500/20 border border-emerald-400/40 text-emerald-100 px-4 py-3 rounded-xl text-sm flex items-center gap-3 shadow-lg backdrop-blur-md" role="alert">
            <svg class="w-5 h-5 text-emerald-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium text-emerald-100">{{ session('status') }}</span>
        </div>
    @endif

    <!-- All 3 Streams Verified — Manager Approval Alert Banner -->
    @if($this->canApproveLead)
        <div class="bg-gradient-to-r from-emerald-950/70 via-slate-900/80 to-emerald-950/70 border border-emerald-500/40 rounded-2xl p-4 sm:p-5 text-white shadow-xl backdrop-blur-md flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4" role="alert" id="banner-ready-for-approval">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="p-2.5 bg-emerald-500/20 text-emerald-400 rounded-xl border border-emerald-500/30 shrink-0 mt-0.5 sm:mt-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-bold text-white flex flex-wrap items-center gap-2">
                        <span>All 3 Streams Verified (Payment, Documents, Photo)</span>
                        <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-400/20 text-emerald-300 font-bold border border-emerald-400/30 uppercase tracking-wide">Next Step: Induction Approval</span>
                    </h3>
                    <p class="text-xs text-slate-300 mt-1 leading-relaxed">
                        To convert this applicant into an active member, click <strong>Approve Lead for Induction</strong> below. This updates the status from <em>Under Review</em> to <em>Approved</em> and unlocks the <strong>Convert to Member</strong> button.
                    </p>
                </div>
            </div>
            <button
                type="button"
                wire:click="approveLead"
                class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg transition-all flex items-center justify-center gap-2 shrink-0 cursor-pointer"
                id="btn-banner-approve-lead"
            >
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Approve Lead for Induction</span>
            </button>
        </div>
    @endif

    <!-- Previous Application History Banner per LEAD-07 -->
    @if($hasReapplicationHistory)
        <div class="bg-amber-500/20 border border-amber-400/40 rounded-2xl p-4 text-amber-100 shadow-xl backdrop-blur-md" id="reapplicant-history-banner" role="alert">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-amber-400/20 rounded-xl shrink-0 mt-0.5 text-amber-300 border border-amber-400/30">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm sm:text-base font-bold text-white flex items-center gap-2">
                        <span>Applicant Reapplication Detected (LEAD-07)</span>
                        <span class="px-2 py-0.5 rounded text-xs bg-amber-400/20 text-amber-300 border border-amber-400/30 font-semibold font-mono">
                            {{ count($historicalLeads) }} Previous Application{{ count($historicalLeads) > 1 ? 's' : '' }}
                        </span>
                    </h3>
                    <p class="text-xs text-amber-200 mt-1">
                        This applicant has prior submission history under email <strong class="text-white">{{ $lead->email }}</strong>. Review previous rejection causes and staff notes below before proceeding with review.
                    </p>

                    @php
                        $priorRejected = collect($historicalLeads)->firstWhere('status', 'rejected');
                        $rejectionReason = null;
                        if ($priorRejected && !empty($priorRejected['verifications'])) {
                            foreach ($priorRejected['verifications'] as $v) {
                                if (!empty($v['rejection_reason_code'])) {
                                    $rejectionReason = $v['rejection_reason_code'];
                                    break;
                                }
                            }
                        }
                    @endphp

                    @if($priorRejected)
                        <div class="mt-2 text-xs font-semibold text-amber-200 bg-amber-950/60 p-2.5 rounded-lg border border-amber-500/40">
                            Applicant Reapplication Detected: Previous application on {{ \Carbon\Carbon::parse($priorRejected['submitted_at'] ?? $priorRejected['created_at'])->format('Y-m-d') }} was Rejected (Reason: {{ $rejectionReason ?? 'Standard Rejection' }}).
                        </div>
                    @endif

                    <!-- Historical applications listing -->
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-xs text-left divide-y divide-slate-700 bg-slate-900/80 rounded-lg overflow-hidden border border-white/10">
                            <thead class="bg-slate-800/80 text-slate-200 font-semibold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th class="px-3 py-2">Submitted</th>
                                    <th class="px-3 py-2">Prior Status</th>
                                    <th class="px-3 py-2">bKash TxID</th>
                                    <th class="px-3 py-2">Target Space</th>
                                    <th class="px-3 py-2">Action / Link</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 text-slate-300">
                                @foreach($historicalLeads as $hist)
                                    <tr>
                                        <td class="px-3 py-2 font-mono whitespace-nowrap">
                                            {{ \Carbon\Carbon::parse($hist['submitted_at'] ?? $hist['created_at'])->format('Y-m-d H:i') }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold {{ $hist['status'] === 'rejected' ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-slate-200 text-slate-800' }}">
                                                {{ strtoupper($hist['status']) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 font-mono whitespace-nowrap">
                                            {{ $hist['bkash_txid'] }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap font-medium">
                                            {{ $hist['target_space_code'] }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <a
                                                href="{{ route('crm.leads.show', ['workspace' => $workspace, 'lead' => $hist['id']]) }}"
                                                class="text-indigo-700 hover:text-indigo-900 font-semibold underline"
                                                target="_blank"
                                            >
                                                View Historical Lead #{{ $hist['id'] }} &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Profile Tab Navigation (3D Frosted Capsule Bar) -->
    <div class="p-1.5 bg-slate-900/60 backdrop-blur-xl border border-white/10 rounded-2xl shadow-lg flex flex-wrap gap-2">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
            <button
                type="button"
                wire:click="setTab('details')"
                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all cursor-pointer {{ $activeTab === 'details' ? 'bg-white text-slate-950 shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10 border border-white/10' }}"
            >
                Applicant Details (31 Fields)
            </button>
            <button
                type="button"
                wire:click="setTab('attachments')"
                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'attachments' ? 'bg-white text-slate-950 shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10 border border-white/10' }}"
            >
                <span>Attachments & Photos</span>
                <span class="px-1.5 py-0.5 rounded-full text-xs font-mono {{ $lead->attachments->count() > 0 ? 'bg-indigo-400/20 text-indigo-300 border border-indigo-400/30' : 'bg-slate-800 text-slate-400' }}">
                    {{ $lead->attachments->count() }}
                </span>
            </button>
            <button
                type="button"
                wire:click="setTab('streams')"
                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all cursor-pointer flex items-center gap-1.5 {{ $activeTab === 'streams' ? 'bg-white text-slate-950 shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10 border border-white/10' }}"
            >
                <span>Verification Streams</span>
                <span class="px-1.5 py-0.2 rounded text-[10px] uppercase font-bold bg-amber-400/20 text-amber-300 border border-amber-400/30">
                    Tri-State
                </span>
            </button>
            <button
                type="button"
                wire:click="setTab('emails')"
                id="tab-btn-emails"
                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all cursor-pointer {{ $activeTab === 'emails' ? 'bg-white text-slate-950 shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10 border border-white/10' }}"
            >
                Official Emails
            </button>
            <button
                type="button"
                wire:click="setTab('timeline')"
                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all cursor-pointer {{ $activeTab === 'timeline' ? 'bg-white text-slate-950 shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/10 border border-white/10' }}"
            >
                Audit & Verification Timeline
            </button>
        </nav>
    </div>

    <!-- Tab 1: Applicant Details (All 31 Fields) -->
    @if($activeTab === 'details')
        <div class="space-y-6">
            @if($editMode)
                <!-- Inline Edit Form (leads.edit permission) -->
                <form wire:submit.prevent="saveContactDetails" class="bg-white rounded-xl shadow-xs border border-indigo-200 p-6 space-y-6">
                    <div class="pb-3 border-b border-indigo-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Edit Applicant Contact & Address Details</h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Identity fields (Name, Email, Date of Birth, Sex, bKash TxID) are immutable per T-06-02.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="toggleEditMode"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-medium transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors"
                                id="btn-save-contact"
                            >
                                Save Changes (Audited)
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Contact Number -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Contact Number (Format: +8801XXXXXXXXX) *</label>
                            <input
                                type="text"
                                wire:model="contact_number"
                                class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            @error('contact_number') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- House No -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">House No. *</label>
                            <input
                                type="text"
                                wire:model="address_house_no"
                                class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            @error('address_house_no') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Road No -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Road No. (Optional)</label>
                            <input
                                type="text"
                                wire:model="address_road_no"
                                class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            @error('address_road_no') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Area -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Area / Neighborhood *</label>
                            <input
                                type="text"
                                wire:model="address_area"
                                class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            @error('address_area') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- ZIP Code -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">ZIP / Postal Code *</label>
                            <input
                                type="text"
                                wire:model="address_zip_code"
                                class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            @error('address_zip_code') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Division -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Administrative Division *</label>
                            <select
                                wire:model="address_division"
                                class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="barishal">Barishal</option>
                                <option value="chattogram">Chattogram</option>
                                <option value="dhaka">Dhaka</option>
                                <option value="khulna">Khulna</option>
                                <option value="mymensingh">Mymensingh</option>
                                <option value="rajshahi">Rajshahi</option>
                                <option value="rangpur">Rangpur</option>
                                <option value="sylhet">Sylhet</option>
                            </select>
                            @error('address_division') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- District -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Administrative District *</label>
                            <input
                                type="text"
                                wire:model="address_district"
                                class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            @error('address_district') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Occupation -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Primary Occupation *</label>
                            <select
                                wire:model="occupation"
                                class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="artist">Artist</option>
                                <option value="community_worker">Community Worker</option>
                                <option value="educator">Educator</option>
                                <option value="entrepreneur">Entrepreneur</option>
                                <option value="media_professional">Media Professional</option>
                                <option value="performer">Performer</option>
                                <option value="student">Student</option>
                                <option value="other">Other</option>
                            </select>
                            @error('occupation') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        @if($occupation === 'other')
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Occupation (Other Specification)</label>
                                <input
                                    type="text"
                                    wire:model="occupation_other"
                                    class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                @error('occupation_other') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <!-- Institution of Study -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Institution of Study *</label>
                            <input
                                type="text"
                                wire:model="institution_study"
                                class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            @error('institution_study') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </form>
            @endif

            <!-- 31 Fields Specification Inspector Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Group 1: Identity & Demographics (Fields 1-6, 31) -->
                <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-xl overflow-hidden text-white">
                    <div class="px-5 py-3.5 bg-white/5 border-b border-white/10 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white tracking-wide">1. Identity & Demographics</h2>
                        <span class="text-xs font-mono text-slate-400">Fields 1-6, 31</span>
                    </div>
                    <div class="p-5 divide-y divide-white/5 text-sm">
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">First Name (Field 1):</span>
                            <span class="font-semibold text-white">{{ $lead->first_name }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Middle Name (Field 2):</span>
                            <span class="font-semibold text-white">{{ $lead->middle_name ?? '—' }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Last Name (Field 3):</span>
                            <span class="font-semibold text-white">{{ $lead->last_name }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">American Space Target (Field 4):</span>
                            <span class="font-semibold text-white">{{ $lead->target_space_code }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Sex (Field 5):</span>
                            <span class="font-semibold text-white uppercase text-xs">{{ strtoupper($lead->sex) }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Date of Birth (Field 6):</span>
                            <span class="font-semibold text-white">{{ $lead->date_of_birth ? $lead->date_of_birth->format('Y-m-d') : 'N/A' }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Age at Submission:</span>
                            <span class="font-semibold {{ $lead->age_at_submission < 18 ? 'text-amber-400' : 'text-white' }}">
                                {{ $lead->age_at_submission }} years
                            </span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Terms & Conditions Agreed (Field 31):</span>
                            <span class="inline-flex items-center gap-1 font-semibold text-emerald-400">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Agreed (Yes)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Group 2: Contact Details (Fields 14-16, 27) -->
                <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-xl overflow-hidden text-white">
                    <div class="px-5 py-3.5 bg-white/5 border-b border-white/10 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white tracking-wide">2. Contact Information</h2>
                        <span class="text-xs font-mono text-slate-400">Fields 14-16, 27</span>
                    </div>
                    <div class="p-5 divide-y divide-white/5 text-sm">
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Contact Number (Field 14):</span>
                            <span class="font-mono font-semibold text-white">{{ $lead->contact_number }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Email Address (Field 15):</span>
                            <span class="font-mono font-semibold text-white">{{ $lead->email }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Website / Portfolio (Field 16):</span>
                            @if($lead->website)
                                <a href="{{ $lead->website }}" target="_blank" class="font-semibold text-amber-400 hover:underline">
                                    {{ $lead->website }}
                                </a>
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Work Phone (Field 27):</span>
                            <span class="font-mono font-semibold text-white">{{ $lead->work_phone ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Group 3: Residential Address (Fields 8-13) -->
                <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-xl overflow-hidden text-white">
                    <div class="px-5 py-3.5 bg-white/5 border-b border-white/10 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white tracking-wide">3. Residential Address</h2>
                        <span class="text-xs font-mono text-slate-400">Fields 8-13</span>
                    </div>
                    <div class="p-5 divide-y divide-white/5 text-sm">
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">House No. (Field 8):</span>
                            <span class="font-semibold text-white">{{ $lead->address_house_no }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Road No. (Field 9):</span>
                            <span class="font-semibold text-white">{{ $lead->address_road_no ?? '—' }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Area / Neighborhood (Field 10):</span>
                            <span class="font-semibold text-white">{{ $lead->address_area }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Postal / ZIP Code (Field 11):</span>
                            <span class="font-mono font-semibold text-white">{{ $lead->address_zip_code }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Administrative Division (Field 12):</span>
                            <span class="font-semibold text-white capitalize">{{ $lead->address_division }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Administrative District (Field 13):</span>
                            <span class="font-semibold text-white capitalize">{{ $lead->address_district }}</span>
                        </div>
                    </div>
                </div>

                <!-- Group 4: Education & Profession (Fields 17-20, 25-26) -->
                <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-xl overflow-hidden text-white">
                    <div class="px-5 py-3.5 bg-white/5 border-b border-white/10 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white tracking-wide">4. Education & Profession</h2>
                        <span class="text-xs font-mono text-slate-400">Fields 17-20, 25-26</span>
                    </div>
                    <div class="p-5 divide-y divide-white/5 text-sm">
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Primary Occupation (Field 17):</span>
                            <span class="font-semibold text-white capitalize">
                                {{ str_replace('_', ' ', $lead->occupation) }}
                                @if($lead->occupation_other)
                                    ({{ $lead->occupation_other }})
                                @endif
                            </span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Place of Study (Field 18):</span>
                            <span class="font-semibold text-white">{{ $lead->institution_study }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Highest Certification (Field 19):</span>
                            <span class="font-semibold text-white uppercase">
                                {{ strtoupper(str_replace('_', ' ', $lead->highest_certification)) }}
                                @if($lead->highest_certification_other)
                                    ({{ $lead->highest_certification_other }})
                                @endif
                            </span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Area of Subject / Major (Field 20):</span>
                            <span class="font-semibold text-white">{{ $lead->area_of_subject }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Current Employer (Field 25):</span>
                            <span class="font-semibold text-white">{{ $lead->employer_name ?? '—' }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Job Title / Designation (Field 26):</span>
                            <span class="font-semibold text-white">{{ $lead->job_title ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Group 5: U.S. Experience & Alumni (Fields 21-24) -->
                <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-xl overflow-hidden text-white">
                    <div class="px-5 py-3.5 bg-white/5 border-b border-white/10 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white tracking-wide">5. U.S. Experience & Alumni</h2>
                        <span class="text-xs font-mono text-slate-400">Fields 21-24</span>
                    </div>
                    <div class="p-5 divide-y divide-white/5 text-sm">
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">U.S. Work / Study Experience (Field 21):</span>
                            <span class="font-semibold {{ $lead->has_us_experience ? 'text-amber-400' : 'text-slate-300' }}">
                                {{ $lead->has_us_experience ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        @if($lead->has_us_experience)
                            <div class="py-2.5 flex justify-between items-center">
                                <span class="text-slate-400 font-medium">U.S. Location (Field 22):</span>
                                <span class="font-semibold text-white">{{ $lead->us_experience_location ?? '—' }}</span>
                            </div>
                        @endif
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">American Institution Alumni (Field 23):</span>
                            <span class="font-semibold {{ $lead->is_american_alumni ? 'text-amber-400' : 'text-slate-300' }}">
                                {{ $lead->is_american_alumni ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        @if($lead->is_american_alumni)
                            <div class="py-2.5 flex justify-between items-center">
                                <span class="text-slate-400 font-medium">Institution Name (Field 24):</span>
                                <span class="font-semibold text-white">{{ $lead->american_institution_name ?? '—' }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Group 6: Minor Youth Protection & bKash Payment (Fields 7, 30) -->
                <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-xl overflow-hidden text-white">
                    <div class="px-5 py-3.5 bg-white/5 border-b border-white/10 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white tracking-wide">6. Payment & Minor Consent Compliance</h2>
                        <span class="text-xs font-mono text-slate-400">Fields 7, 30</span>
                    </div>
                    <div class="p-5 divide-y divide-white/5 text-sm">
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">bKash Transaction ID (Field 30):</span>
                            <span class="font-mono font-bold text-pink-400 text-base">{{ $lead->bkash_txid }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Payment Status:</span>
                            <span class="font-semibold uppercase text-xs text-white">{{ $lead->payment_verification_status }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Minor Applicant (< 18 yrs):</span>
                            <span class="font-semibold {{ $lead->age_at_submission < 18 ? 'text-amber-400' : 'text-slate-300' }}">
                                {{ $lead->age_at_submission < 18 ? 'Yes (Mandatory Consent Required)' : 'No (Adult)' }}
                            </span>
                        </div>
                        <div class="py-2.5 flex justify-between items-center">
                            <span class="text-slate-400 font-medium">Parental Consent Uploaded (Field 7):</span>
                            <span class="font-semibold {{ $lead->has_parental_consent ? 'text-emerald-400' : ($lead->age_at_submission < 18 ? 'text-rose-400' : 'text-slate-500') }}">
                                {{ $lead->has_parental_consent ? 'Yes (Verified Attached)' : ($lead->age_at_submission < 18 ? 'Missing / Incomplete' : 'Not Required') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attached Documents & Media Summary (Fields 7, 28, 29) -->
            <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-xl overflow-hidden text-white">
                <div class="px-5 py-3.5 bg-white/5 border-b border-white/10 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-white tracking-wide">7. Attached Documents & Verification Media</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Secure private attachments with encrypted streaming (Fields 7, 28, 29)</p>
                    </div>
                    <span class="text-xs font-mono text-slate-400">{{ $lead->attachments->count() }} Attached File(s)</span>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @forelse($lead->attachments as $attachment)
                            @php
                                $typeLabel = match($attachment->document_type) {
                                    'identity_document' => 'Identity Document (Field 28)',
                                    'studio_photo' => 'Studio Passport Photo (Field 29)',
                                    'parental_consent' => 'Parental Consent Form (Field 7)',
                                    default => ucwords(str_replace('_', ' ', $attachment->document_type)),
                                };

                                $typeBadge = match($attachment->document_type) {
                                    'identity_document' => 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40',
                                    'studio_photo' => 'bg-purple-500/20 text-purple-300 border-purple-500/40',
                                    'parental_consent' => 'bg-amber-500/20 text-amber-300 border-amber-500/40',
                                    default => 'bg-slate-800 text-slate-300 border-white/10',
                                };
                            @endphp
                            <div class="bg-slate-800/80 backdrop-blur-md rounded-xl border border-white/15 p-4 flex flex-col justify-between shadow-lg" id="summary-attachment-card-{{ $attachment->id }}">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold border {{ $typeBadge }}">
                                            {{ $typeLabel }}
                                        </span>
                                        @if($attachment->youth_protection)
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                                Youth
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs font-semibold text-white truncate" title="{{ $attachment->original_filename }}">
                                        {{ $attachment->original_filename }}
                                    </p>
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        {{ number_format($attachment->size_bytes / 1024, 1) }} KB &bull; {{ $attachment->mime_type }}
                                    </p>
                                </div>
                                <div class="mt-3 pt-2.5 border-t border-white/10">
                                    <a
                                        href="{{ route('documents.download', ['workspace' => $workspace, 'attachment' => $attachment->id]) }}"
                                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-400 hover:text-amber-300 transition-colors"
                                        target="_blank"
                                        id="summary-download-attachment-{{ $attachment->id }}"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        <span>Download Document</span>
                                    </a>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 col-span-3">No attachments uploaded with this lead.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Tab 2: Secure Attachments & Photos (Fields 7, 28, 29) -->
    @if($activeTab === 'attachments')
        <div class="space-y-6">
            <div class="bg-blue-500/20 border border-blue-400/30 rounded-2xl p-4 text-xs text-blue-100 shadow-xl backdrop-blur-md">
                <p class="font-bold text-white">Private Storage Encryption & Access Control (DEC-09 / ARCH-06)</p>
                <p class="mt-1 text-blue-200">
                    All identity documents and photographs are AES-256 encrypted at rest on private disks. Physical filenames use UUID v4. Downloading streams files through the <code>documents.download</code> controller enforcing tenancy and youth consent permissions.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($lead->attachments as $attachment)
                    @php
                        $typeLabel = match($attachment->document_type) {
                            'identity_document' => 'Identity Document (Field 28)',
                            'studio_photo' => 'Studio Passport Photo (Field 29)',
                            'parental_consent' => 'Parental Consent Form (Field 7)',
                            default => ucwords(str_replace('_', ' ', $attachment->document_type)),
                        };

                        $typeBadge = match($attachment->document_type) {
                            'identity_document' => 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40',
                            'studio_photo' => 'bg-purple-500/20 text-purple-300 border-purple-500/40',
                            'parental_consent' => 'bg-amber-500/20 text-amber-300 border-amber-500/40',
                            default => 'bg-slate-800 text-slate-300 border-white/10',
                        };
                    @endphp
                    <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-xl overflow-hidden flex flex-col justify-between text-white" id="attachment-card-{{ $attachment->id }}">
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border {{ $typeBadge }}">
                                    {{ $typeLabel }}
                                </span>
                                @if($attachment->youth_protection)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        Youth Protection
                                    </span>
                                @endif
                            </div>

                            <p class="text-sm font-semibold text-white truncate" title="{{ $attachment->original_filename }}">
                                {{ $attachment->original_filename }}
                            </p>

                            <div class="mt-2 space-y-1 text-xs text-slate-400">
                                <p>Size: <span class="font-mono text-slate-200">{{ number_format($attachment->size_bytes / 1024, 1) }} KB</span></p>
                                <p>MIME: <span class="font-mono text-slate-200">{{ $attachment->mime_type }}</span></p>
                                <p class="truncate" title="{{ $attachment->file_hash }}">SHA-256: <span class="font-mono text-slate-400 text-[10px]">{{ substr($attachment->file_hash, 0, 16) }}...</span></p>
                            </div>
                        </div>

                        <div class="p-3 bg-white/5 border-t border-white/10">
                            <a
                                href="{{ route('documents.download', ['workspace' => $workspace, 'attachment' => $attachment->id]) }}"
                                class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-md transition-colors"
                                target="_blank"
                                id="download-attachment-{{ $attachment->id }}"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Secure Download & Stream</span>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 bg-slate-900/60 backdrop-blur-xl border border-white/10 p-8 rounded-2xl text-center text-slate-400">
                        <p class="text-sm font-medium">No files attached to this lead.</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Tab 3: Verification Streams (Tri-State) -->
    @if($activeTab === 'streams')
        @livewire('leads.partials.verification-panel', ['lead' => $lead, 'workspace' => $workspace], key('verification-panel-'.$lead->id))
    @endif

    <!-- Tab 4: Official Emails & Threaded Correspondence -->
    @if($activeTab === 'emails' || $activeTab === 'details')
        <div id="tab-emails" class="{{ $activeTab === 'emails' ? '' : 'mt-6' }}">
            <livewire:mail.email-thread-panel :workspace="$workspace" :leadId="$lead->id" :key="'lead-emails-'.$lead->id" />
        </div>
    @endif

    <!-- Tab 5: Audit & Verification Timeline -->
    @if($activeTab === 'timeline')
        <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-xl p-6 space-y-4 text-white">
            <h2 class="text-sm font-bold text-white tracking-wide pb-3 border-b border-white/10">Application Lifecycle Timeline</h2>

            <div class="flow-root">
                <ul class="-mb-8">
                    <!-- Event 1: Lead Submitted -->
                    <li>
                        <div class="relative pb-8">
                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true"></span>
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white text-white text-xs">
                                        ✓
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                    <div>
                                        <p class="text-sm text-slate-800 font-semibold">Application Received & Ingested</p>
                                        <p class="text-xs text-slate-500">Applicant submitted 31-field application via public form.</p>
                                    </div>
                                    <div class="text-right text-xs whitespace-nowrap text-slate-500 font-mono">
                                        {{ $lead->submitted_at ? $lead->submitted_at->format('Y-m-d H:i') : $lead->created_at->format('Y-m-d H:i') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    <!-- Event 2: Verification records if any -->
                    @forelse($lead->verifications as $v)
                        <li>
                            <div class="relative pb-8">
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true"></span>
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full {{ $v->status === 'verified' ? 'bg-emerald-500' : 'bg-rose-500' }} flex items-center justify-center ring-8 ring-white text-white text-xs">
                                            {{ strtoupper(substr($v->stream, 0, 1)) }}
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-slate-800 font-semibold">
                                                {{ ucfirst($v->stream) }} Stream: {{ ucfirst($v->status) }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                Verified by: {{ $v->verifiedBy?->name ?? 'Staff User' }}
                                                @if($v->notes)
                                                    &bull; Note: "{{ $v->notes }}"
                                                @endif
                                                @if($v->rejection_reason_code)
                                                    &bull; Reason Code: <span class="font-mono">{{ $v->rejection_reason_code }}</span>
                                                @endif
                                            </p>
                                        </div>
                                        <div class="text-right text-xs whitespace-nowrap text-slate-500 font-mono">
                                            {{ $v->verified_at ? $v->verified_at->format('Y-m-d H:i') : $v->created_at->format('Y-m-d H:i') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @empty
                        <!-- No verification events yet -->
                    @endforelse

                    <!-- Event 3: Current Status -->
                    <li>
                        <div class="relative">
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full bg-slate-400 flex items-center justify-center ring-8 ring-white text-white text-xs">
                                        ●
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                    <div>
                                        <p class="text-sm text-slate-800 font-semibold">Current State: {{ ucwords(str_replace('_', ' ', $lead->status)) }}</p>
                                        <p class="text-xs text-slate-500">Record awaits further verification action or manager review.</p>
                                    </div>
                                    <div class="text-right text-xs whitespace-nowrap text-slate-500 font-mono">
                                        {{ $lead->updated_at->format('Y-m-d H:i') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    @endif

    <!-- Missing Items, Rejection, Conversion & Reversal Modals -->
    @livewire('leads.modals.missing-items-modal', ['lead' => $lead, 'workspace' => $workspace], key('missing-items-modal-'.$lead->id))
    @livewire('leads.modals.reject-lead-modal', ['lead' => $lead, 'workspace' => $workspace], key('reject-lead-modal-'.$lead->id))
    @livewire('leads.modals.convert-lead-modal', ['lead' => $lead, 'workspace' => $workspace], key('convert-lead-modal-'.$lead->id))
    @livewire('members.modals.conversion-reversal-modal', ['memberId' => $lead->converted_member_id, 'workspace' => $workspace, 'lead' => $lead], key('conversion-reversal-modal-'.$lead->id))
</div>
