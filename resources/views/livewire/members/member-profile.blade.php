<div class="space-y-6">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-slate-400">
        <a href="/crm/{{ $workspace }}/members" class="hover:text-amber-400 transition-colors">Members</a>
        <span class="text-slate-600">/</span>
        <span class="text-white font-semibold">{{ $member->full_name }}</span>
    </nav>

    <!-- Header Card (3D Frosted Glass) -->
    <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 sm:p-7 shadow-2xl text-white">
        <div class="sm:flex sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <!-- Studio Photo Thumbnail Preview if present -->
                @php
                    $photoAttachment = $member->lead?->attachments?->firstWhere('document_type', 'studio_photo');
                @endphp
                <div class="w-16 h-16 rounded-2xl bg-slate-950/80 border border-white/10 overflow-hidden flex items-center justify-center shrink-0">
                    @if ($photoAttachment)
                        <img
                            src="{{ route('documents.download', ['workspace' => $workspace, 'attachment' => $photoAttachment->id]) }}"
                            alt="{{ $member->full_name }}"
                            class="w-full h-full object-cover"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                        />
                        <div class="w-full h-full items-center justify-center text-slate-500 hidden">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                    @else
                        <svg class="w-8 h-8 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    @endif
                </div>

                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white" id="member-full-name">
                            {{ $member->full_name }}
                        </h1>
                        <span class="font-mono text-xs font-bold px-3 py-1 bg-amber-400/15 text-amber-300 rounded-xl border border-amber-400/30" id="member-id-badge">
                            {{ $member->member_id }}
                        </span>
                        <!-- Status Badge -->
                        @if ($member->status === 'active')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                Active
                            </span>
                        @elseif ($member->status === 'inactive')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                Inactive
                            </span>
                        @elseif ($member->status === 'restricted')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                Restricted
                            </span>
                        @elseif ($member->status === 'revoked')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-white/10">
                                Revoked
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-white/10">
                                {{ ucfirst($member->status) }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-300">
                        <span class="sr-only">{{ $member->workspace_id }}</span>
                        <div>
                            <span class="text-slate-400 font-medium">Term:</span>
                            <span class="font-semibold text-white ml-1">{{ $member->term?->name ?? 'Standard' }} ({{ $member->term?->duration_months ?? 12 }} Months)</span>
                        </div>
                        <div class="text-slate-600">•</div>
                        <div>
                            <span class="text-slate-400 font-medium">Validity:</span>
                            <span class="font-semibold text-white ml-1">{{ $member->start_date ? $member->start_date->format('Y-m-d') : 'N/A' }} to {{ $member->expiry_date ? $member->expiry_date->format('Y-m-d') : 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 sm:mt-0 flex items-center gap-2.5">
                <button
                    type="button"
                    wire:click="openEditContactModal"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-slate-950 rounded-full text-xs font-bold hover:bg-amber-400 shadow-md transition-colors cursor-pointer"
                    id="btn-edit-contact"
                >
                    <svg class="w-3.5 h-3.5 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Contact Details
                </button>
                @if ($this->canManageCards())
                    <button
                        type="button"
                        wire:click="openCardReplacementModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white/10 text-slate-200 border border-white/10 rounded-full text-xs font-semibold hover:bg-white/20 transition-colors cursor-pointer"
                        id="btn-replace-card"
                    >
                        Replace ID Card
                    </button>
                @endif
                @if ($this->canChangeStatus())
                    <button
                        type="button"
                        wire:click="openStatusModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-rose-500/20 text-rose-300 border border-rose-500/30 rounded-full text-xs font-semibold hover:bg-rose-500/30 transition-colors cursor-pointer"
                        id="btn-change-status"
                    >
                        Change Status
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Navigation Tabs (High Contrast Frosted Capsule Bar) -->
    <div class="p-1.5 bg-slate-900/60 backdrop-blur-xl border border-white/10 rounded-2xl shadow-lg flex flex-wrap gap-2">
        <nav class="flex flex-wrap gap-2" aria-label="Member Profile Tabs">
            <button
                type="button"
                wire:click="setTab('overview')"
                class="px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold transition-all cursor-pointer {{ $activeTab === 'overview' ? 'bg-white text-slate-950 shadow-lg shadow-white/20' : 'text-slate-300 hover:text-white hover:bg-white/10 border border-white/10' }}"
                id="tab-btn-overview"
            >
                31-Field Overview
            </button>
            <button
                type="button"
                wire:click="setTab('invoices')"
                class="px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold transition-all cursor-pointer {{ $activeTab === 'invoices' ? 'bg-white text-slate-950 shadow-lg shadow-white/20' : 'text-slate-300 hover:text-white hover:bg-white/10 border border-white/10' }}"
                id="tab-btn-invoices"
            >
                Invoices & Billing ({{ $member->invoices->count() }})
            </button>
            <button
                type="button"
                wire:click="setTab('emails')"
                class="px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold transition-all cursor-pointer {{ $activeTab === 'emails' ? 'bg-white text-slate-950 shadow-lg shadow-white/20' : 'text-slate-300 hover:text-white hover:bg-white/10 border border-white/10' }}"
                id="tab-btn-member-emails"
            >
                Official Emails
            </button>
            <button
                type="button"
                wire:click="setTab('activity_timeline')"
                class="px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold transition-all cursor-pointer {{ $activeTab === 'activity_timeline' ? 'bg-white text-slate-950 shadow-lg shadow-white/20' : 'text-slate-300 hover:text-white hover:bg-white/10 border border-white/10' }}"
                id="tab-btn-timeline"
            >
                Activity Timeline & Audit
            </button>
        </nav>
    </div>

    <!-- TAB 1: 31-FIELD OVERVIEW -->
    @if ($activeTab === 'overview')
        <div class="space-y-6" id="tab-overview">
            <!-- 1. Personal & Identity Details -->
            <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white">
                <h2 class="text-sm font-bold uppercase tracking-wider text-amber-300 pb-3 border-b border-white/10 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Personal &amp; Demographic Identity
                </h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <div>
                        <dt class="text-xs font-medium text-slate-400">First Name</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">{{ $member->first_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Middle Name</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">{{ $member->middle_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Last Name</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">{{ $member->last_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">American Space</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5 capitalize">{{ str_replace('_', ' ', $member->lead?->american_space ?? 'EMK Center') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Gender / Sex</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">{{ ucfirst($member->sex) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Date of Birth</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">{{ $member->date_of_birth ? $member->date_of_birth->format('Y-m-d') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Calculated Age</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">
                            {{ $member->date_of_birth ? $member->date_of_birth->age . ' years' : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Member ID</dt>
                        <dd class="text-sm font-mono font-bold text-amber-300 mt-0.5">{{ $member->member_id }}</dd>
                    </div>
                </dl>
            </div>

            <!-- 2. Contact & Address Details -->
            <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white">
                <h2 class="text-sm font-bold uppercase tracking-wider text-amber-300 pb-3 border-b border-white/10 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Contact &amp; Residential Address
                </h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Email Address</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">{{ $member->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Contact Number</dt>
                        <dd class="text-sm font-mono font-semibold text-white mt-0.5" id="member-contact-number">{{ $member->contact_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Work Phone</dt>
                        <dd class="text-sm font-mono font-semibold text-white mt-0.5">{{ $member->lead?->work_phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Website</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">
                            @if ($member->lead?->website)
                                <a href="{{ $member->lead->website }}" target="_blank" rel="noopener" class="text-amber-400 hover:underline truncate block">
                                    {{ $member->lead->website }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">House / Building No.</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5" id="member-house-no">{{ $member->address_house_no }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Road No.</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5" id="member-road-no">{{ $member->address_road_no ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Area / Neighborhood</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5" id="member-area">{{ $member->address_area }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Postal / ZIP Code</dt>
                        <dd class="text-sm font-mono font-semibold text-white mt-0.5" id="member-zip-code">{{ $member->address_zip_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Division</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5" id="member-division">{{ ucfirst($member->address_division) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">District</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5" id="member-district">{{ ucfirst($member->address_district) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- 3. Academic & Professional Details -->
            <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white">
                <h2 class="text-sm font-bold uppercase tracking-wider text-amber-300 pb-3 border-b border-white/10 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                    </svg>
                    Academic, Professional &amp; Exchange Credentials
                </h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Occupation</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5" id="member-occupation">{{ ucfirst($member->occupation) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Institution of Study</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5" id="member-institution">{{ $member->institution_study }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Highest Certification</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5" id="member-highest-cert">{{ ucwords(str_replace('_', ' ', $member->highest_certification)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Area of Subject / Discipline</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">{{ $member->area_of_subject }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Current Employer</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">{{ $member->lead?->employer_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Job Title</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">{{ $member->lead?->job_title ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">U.S. Work / Study Experience</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">
                            {{ $member->lead?->has_us_experience ? 'Yes (' . ($member->lead->us_experience_location ?: 'U.S.') . ')' : 'No' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">American Institution Alumni</dt>
                        <dd class="text-sm font-semibold text-white mt-0.5">
                            {{ $member->lead?->is_american_alumni ? 'Yes (' . ($member->lead->american_institution_name ?: 'American Institution') . ')' : 'No' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Originating bKash TxID</dt>
                        <dd class="text-sm font-mono font-semibold text-amber-300 mt-0.5">{{ $member->lead?->bkash_txid ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-400">Terms &amp; Conditions Agreed</dt>
                        <dd class="text-sm font-semibold text-emerald-300 mt-0.5">✓ Accepted at submission</dd>
                    </div>
                </dl>
            </div>

            <!-- 4. Verified Identity Documents & Studio Photo -->
            <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 shadow-2xl text-white">
                <h2 class="text-sm font-bold uppercase tracking-wider text-amber-300 pb-3 border-b border-white/10 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Verified Identity Documents &amp; Photographic Evidence
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4" id="section-attachments">
                    @forelse ($member->lead?->attachments ?? [] as $attachment)
                        <div class="border border-white/10 rounded-2xl p-4 bg-slate-950/60 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 truncate">
                                <svg class="w-6 h-6 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <div class="truncate">
                                    <p class="text-xs font-semibold text-white truncate">{{ $attachment->original_filename }}</p>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">
                                        {{ str_replace('_', ' ', $attachment->document_type) }} • {{ number_format($attachment->size_bytes / 1024, 1) }} KB
                                    </p>
                                </div>
                            </div>
                            <a
                                href="{{ route('documents.download', ['workspace' => $workspace, 'attachment' => $attachment->id]) }}"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white text-slate-950 hover:bg-amber-400 rounded-full text-xs font-bold transition-all shrink-0"
                            >
                                <span>Download</span>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </a>
                        </div>
                    @empty
                        <div class="col-span-full py-6 text-center text-xs text-slate-400">
                            No verified documents attached to this member record.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: INVOICES & BILLING -->
    @if ($activeTab === 'invoices')
        <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl shadow-2xl overflow-hidden text-white" id="tab-invoices">
            <div class="p-6 border-b border-white/10 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-amber-300">
                        Billing &amp; Payment History
                    </h2>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10 text-left text-sm" id="table-invoices">
                    <thead class="bg-slate-950/60 text-slate-300 font-bold text-xs uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="px-6 py-4">Invoice #</th>
                            <th scope="col" class="px-6 py-4">Amount (BDT)</th>
                            <th scope="col" class="px-6 py-4">Payment Method</th>
                            <th scope="col" class="px-6 py-4">Reference (bKash TxID)</th>
                            <th scope="col" class="px-6 py-4">Status</th>
                            <th scope="col" class="px-6 py-4">Paid Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 bg-slate-900/40 text-slate-200">
                        @forelse ($member->invoices as $invoice)
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs font-bold text-white">
                                    {{ $invoice->invoice_number }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-bold text-white">
                                    BDT {{ number_format((float) $invoice->amount_bdt, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs capitalize text-slate-300">
                                    {{ str_replace('_', ' ', $invoice->payment_method) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-amber-300 font-bold">
                                    {{ $invoice->payment_reference }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($invoice->status === 'paid')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            Paid
                                        </span>
                                    @elseif ($invoice->status === 'unpaid')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                            Unpaid
                                        </span>
                                    @elseif ($invoice->status === 'voided')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-white/10">
                                            Voided
                                        </span>
                                    @elseif ($invoice->status === 'void_reversed')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                            Void (Reversed)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-white/10">
                                            {{ ucfirst($invoice->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-300">
                                    {{ $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i') : 'N/A' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-slate-400 text-xs">
                                    No invoice records found for this member.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- TAB 2.5: OFFICIAL EMAILS & THREADED CORRESPONDENCE -->
    @if ($activeTab === 'emails' || $activeTab === 'overview')
        <div id="tab-member-emails" class="{{ $activeTab === 'emails' ? '' : 'mt-6' }}">
            <livewire:mail.email-thread-panel :workspace="$workspace" :memberId="$member->id" :leadId="$member->lead_id" :key="'member-emails-'.$member->id" />
        </div>
    @endif

    <!-- TAB 3: ACTIVITY TIMELINE & AUDIT LOGS -->
    @if ($activeTab === 'activity_timeline')
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6" id="tab-timeline">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-900 pb-4 border-b border-slate-100">
                Unified Lifecycle Events & Audit Trail
            </h2>

            <div class="mt-6 flow-root">
                <ul role="list" class="-mb-8">
                    <!-- Event 1: ID Card Credential Status -->
                    @foreach ($member->idCards as $card)
                        <li>
                            <div class="relative pb-8">
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true"></span>
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2H9.17A3.001 3.001 0 0112 14z" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                        <div>
                                            <p class="text-xs font-semibold text-slate-900">
                                                Physical ID Card [{{ $card->card_number }}]
                                            </p>
                                            <p class="text-xs text-slate-500 mt-0.5">
                                                Status: <span class="font-medium text-slate-800 capitalize">{{ str_replace('_', ' ', $card->status) }}</span>
                                                @if ($card->batch_reference)
                                                    • Batch: {{ $card->batch_reference }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="whitespace-nowrap text-right text-xs text-slate-400">
                                            {{ $card->created_at ? $card->created_at->format('Y-m-d H:i') : '' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach

                    <!-- Event 2: Membership Term Activated -->
                    <li>
                        <div class="relative pb-8">
                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true"></span>
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                    <div>
                                        <p class="text-xs font-semibold text-slate-900">
                                            Membership Induction and Term Validity Started
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            Valid from {{ $member->start_date ? $member->start_date->format('Y-m-d') : 'N/A' }} until {{ $member->expiry_date ? $member->expiry_date->format('Y-m-d') : 'N/A' }}.
                                        </p>
                                    </div>
                                    <div class="whitespace-nowrap text-right text-xs text-slate-400">
                                        {{ $member->created_at ? $member->created_at->format('Y-m-d H:i') : '' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    <!-- Event 3: Audit Log Records -->
                    @foreach ($auditLogs as $log)
                        <li>
                            <div class="relative pb-8">
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true"></span>
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                        <div>
                                            <p class="text-xs font-semibold text-slate-900 font-mono">
                                                {{ $log->event_type }}
                                            </p>
                                            <p class="text-xs text-slate-600 mt-0.5">
                                                {{ $log->reason ?? 'Audit event recorded.' }}
                                            </p>
                                            @if ($log->new_values)
                                                <details class="mt-1">
                                                    <summary class="text-[10px] text-indigo-600 cursor-pointer">View Details</summary>
                                                    <pre class="mt-1 text-[10px] bg-slate-50 p-2 rounded border border-slate-200 overflow-x-auto">{{ json_encode(json_decode($log->new_values), JSON_PRETTY_PRINT) }}</pre>
                                                </details>
                                            @endif
                                        </div>
                                        <div class="whitespace-nowrap text-right text-xs text-slate-400">
                                            {{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <!-- Inline Contact Edit Modal Component Slot (Task 3) -->
    @if (class_exists(\App\Livewire\Members\EditMemberContactModal::class))
        <livewire:members.edit-member-contact-modal :member="$member" :workspace="$workspace" wire:key="'edit-modal-'.$member->id" />
    @endif

    @if ($this->canChangeStatus())
        <livewire:members.member-status-modal :member="$member" :workspace="$workspace" wire:key="'status-modal-'.$member->id" />
    @endif

    @if ($this->canManageCards())
        <livewire:members.request-card-replacement-modal :member="$member" :workspace="$workspace" wire:key="'card-replace-modal-'.$member->id" />
    @endif
</div>
