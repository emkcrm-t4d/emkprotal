<div x-data="{ idCardModalOpen: false }" @open-member-id-card-modal.window="idCardModalOpen = true" class="space-y-6">
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-slate-900/80 border border-emerald-500/40 text-emerald-300 text-xs flex items-center gap-2.5 shadow-xl backdrop-blur-md">
            <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span class="font-medium">{{ session('status') }}</span>
        </div>
    @endif

    {{-- Membership Summary 3D Frosted Glass Block --}}
    <div class="relative rounded-3xl bg-slate-900/60 backdrop-blur-2xl border border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.4)] overflow-hidden transition-all duration-300 hover:border-white/20">
        <!-- Ambient Glass Accent Reflection -->
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

        <div class="relative p-6 sm:p-8 bg-gradient-to-r from-slate-900/90 via-indigo-950/60 to-slate-900/90 text-white border-b border-white/10">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                {{-- Member Identity & Badges --}}
                <div class="flex items-start gap-4 sm:gap-5">
                    {{-- Circular Avatar with 3D Radiant Glow Ring --}}
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl p-0.5 bg-gradient-to-tr from-amber-400 via-rose-500 to-indigo-500 shadow-lg shadow-indigo-500/25 shrink-0">
                        @php
                            $photoAttachment = $member->lead?->attachments->firstWhere('document_type', 'studio_photo');
                        @endphp
                        @if ($photoAttachment)
                            <img
                                src="{{ route('portal.documents.download', ['attachment' => $photoAttachment->id]) }}"
                                alt="{{ $member->full_name }}"
                                class="w-full h-full object-cover rounded-[14px]"
                            >
                        @else
                            <div class="w-full h-full rounded-[14px] bg-slate-900 flex items-center justify-center text-white font-bold text-xl sm:text-2xl">
                                {{ strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)) }}
                            </div>
                        @endif
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            {{-- Clickable Member ID Button triggering 3D Holographic Card Modal --}}
                            <button
                                type="button"
                                @click="idCardModalOpen = true"
                                class="font-mono text-xs sm:text-sm font-bold bg-amber-400/15 hover:bg-amber-400/25 text-amber-300 px-3 py-1 rounded-xl border border-amber-400/30 tracking-wider flex items-center gap-2 cursor-pointer transition shadow-xs group"
                                title="Click to view 3D Digital Member ID Card"
                            >
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                                <span>{{ $member->member_id }}</span>
                                <span class="text-[10px] text-amber-300/80 font-sans font-medium px-1.5 py-0.2 bg-amber-400/10 rounded group-hover:bg-amber-400/20 transition">View 3D Pass</span>
                            </button>

                            @if ($member->status === 'active')
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 shadow-xs" role="status" aria-label="Membership Status: Active Member">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                                    Active Member
                                </span>
                            @elseif ($member->status === 'inactive')
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30 shadow-xs" role="status" aria-label="Membership Status: Inactive or Expired">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400" aria-hidden="true"></span>
                                    Inactive / Expired
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30 shadow-xs" role="status" aria-label="Membership Status: {{ ucfirst($member->status) }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400" aria-hidden="true"></span>
                                    {{ ucfirst($member->status) }}
                                </span>
                            @endif
                        </div>

                        <p class="text-xs text-amber-300/90 font-medium">
                            Welcome, {{ $member->full_name }}
                        </p>
                        <h2 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                            {{ $member->full_name }}
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-300 mt-0.5 flex flex-wrap items-center gap-2">
                            <span>{{ $member->email }}</span>
                            <span class="text-slate-500">•</span>
                            <span>{{ $member->contact_number }}</span>
                        </p>
                    </div>
                </div>

                {{-- Action / Quick Link --}}
                <div class="flex flex-col sm:flex-row md:flex-col items-start md:items-end justify-center gap-2">
                    <button
                        type="button"
                        wire:click="$dispatch('openSubmitProfileChangeRequestModal')"
                        class="inline-flex items-center gap-2 min-h-[44px] px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-gradient-to-r from-indigo-600 to-indigo-500 hover:from-indigo-500 hover:to-indigo-400 border border-indigo-400/30 shadow-lg shadow-indigo-600/30 transition-all cursor-pointer transform hover:scale-[1.02] active:scale-[0.98]"
                        title="Submit a Change Request for staff review"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span>Request Info Update</span>
                    </button>
                    <span class="text-[11px] text-slate-400">Changes subject to staff review</span>
                </div>
            </div>
        </div>

        {{-- Validity & ID Card Summary Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-white/5 bg-slate-950/50 text-slate-200">
            {{-- Term Specification --}}
            <div class="p-4 sm:p-5">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block">Membership Term</span>
                <span class="text-sm font-bold text-white mt-1 block">
                    {{ $member->term?->name ?? 'Standard Membership' }}
                </span>
                <span class="text-xs text-slate-400 mt-0.5 block">
                    Duration: {{ $member->term?->duration_months ?? 12 }} Months
                </span>
            </div>

            {{-- Validity Window --}}
            <div class="p-4 sm:p-5 flex flex-col justify-between">
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block">Validity Period</span>
                    <div class="text-sm font-bold text-white mt-1 flex items-center gap-1.5">
                        <span>{{ $member->start_date?->format('M d, Y') ?? '—' }}</span>
                        <span class="text-slate-500">&rarr;</span>
                        <span>{{ $member->expiry_date?->format('M d, Y') ?? '—' }}</span>
                    </div>
                    @php
                        $isExpired = $member->expiry_date && $member->expiry_date->isPast();
                        $daysRemaining = $member->expiry_date ? (int) now()->diffInDays($member->expiry_date, false) : 0;
                        $renewalService = app(\App\Domain\Members\Services\MemberRenewalService::class);
                        $canRenew = $renewalService->isRenewalWindowOpen($member);
                        $renewalOpensAt = $member->expiry_date?->copy()->subDays(30);
                    @endphp
                    <span class="text-xs {{ $isExpired ? 'text-rose-400 font-medium' : ($daysRemaining < 30 ? 'text-amber-400 font-medium' : 'text-slate-400') }} mt-0.5 block">
                        @if ($isExpired)
                            Expired {{ abs($daysRemaining) }} days ago
                        @else
                            {{ $daysRemaining }} days remaining
                        @endif
                    </span>
                </div>

                <div class="mt-3">
                    @if ($canRenew)
                        <button
                            type="button"
                            @click="$dispatch('openSubmitRenewalModal')"
                            class="inline-flex items-center gap-1.5 min-h-[44px] px-3.5 py-2 rounded-xl text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-md transition cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Renew Membership</span>
                        </button>
                    @else
                        <div
                            class="inline-flex items-center gap-1.5 text-[11px] text-slate-400 cursor-not-allowed"
                            title="Renewal opens 30 days before expiration ({{ $renewalOpensAt?->format('M d, Y') ?? '—' }})"
                        >
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                            <span>Renewal opens {{ $renewalOpensAt?->format('M d, Y') ?? '30 days prior' }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Physical ID Card Indicator & 3D Digital Card Launcher --}}
            <div class="p-4 sm:p-5 sm:col-span-2 md:col-span-1 flex flex-col justify-between">
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block">Physical ID Card</span>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $this->idCardStatusColor }}" role="status" aria-label="Physical ID Card Status: {{ $this->idCardStatusLabel }}">
                            {{ $this->idCardStatusLabel }}
                        </span>
                    </div>
                    <span class="text-xs font-mono text-slate-300 mt-1 block">
                        Card No: {{ $this->latestIdCard?->card_number ?? 'In Queue' }}
                    </span>
                </div>

                <div class="mt-3">
                    <button
                        type="button"
                        @click="idCardModalOpen = true"
                        class="inline-flex items-center gap-2 min-h-[44px] px-3.5 py-2 rounded-xl text-xs font-semibold text-cyan-300 hover:text-white bg-cyan-500/15 hover:bg-cyan-500/25 border border-cyan-400/30 transition shadow-xs cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>View 3D Digital Pass</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Navigation in 3D Glass Pill Design --}}
    <div class="p-1.5 bg-slate-900/60 backdrop-blur-xl border border-white/10 rounded-2xl shadow-lg flex flex-wrap gap-2">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
            <button
                type="button"
                wire:click="setTab('profile')"
                class="py-2.5 px-4 min-h-[44px] inline-flex items-center gap-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer {{ $activeTab === 'profile' ? 'bg-gradient-to-r from-amber-500/20 to-indigo-500/20 border border-amber-400/40 text-amber-300 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-white/5 border border-transparent' }}"
            >
                <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Personal Profile</span>
            </button>

            <button
                type="button"
                wire:click="setTab('documents')"
                class="py-2.5 px-4 min-h-[44px] inline-flex items-center gap-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer {{ $activeTab === 'documents' ? 'bg-gradient-to-r from-amber-500/20 to-indigo-500/20 border border-amber-400/40 text-amber-300 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-white/5 border border-transparent' }}"
            >
                <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Uploaded Documents</span>
                @if ($member->lead?->attachments && $member->lead->attachments->count() > 0)
                    <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-400/20 text-amber-300 border border-amber-400/30">
                        {{ $member->lead->attachments->count() }}
                    </span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('communications')"
                class="py-2.5 px-4 min-h-[44px] inline-flex items-center gap-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer {{ $activeTab === 'communications' ? 'bg-gradient-to-r from-amber-500/20 to-indigo-500/20 border border-amber-400/40 text-amber-300 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-white/5 border border-transparent' }}"
            >
                <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <span>Official Correspondence</span>
            </button>
        </nav>
    </div>

    {{-- Tab 1: Personal & Contact Information (Strictly Read-Only 3D Blocks) --}}
    @if ($activeTab === 'profile')
        <div class="space-y-6">
            {{-- Personal & Demographic Details 3D Frosted Glass Block --}}
            <div class="bg-slate-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.35)] p-6 sm:p-8 hover:border-white/20 transition-all">
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight">Personal & Demographic Details</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Verified applicant identity and personal specifications.</p>
                    </div>
                    <span class="text-[11px] font-medium text-slate-300 bg-white/5 px-2.5 py-1 rounded-lg border border-white/10">
                        Read-Only View
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">First Name</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->first_name }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Middle Name</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->middle_name ?: '—' }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Last Name</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->last_name }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Date of Birth</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">
                            {{ $member->date_of_birth?->format('F d, Y') ?? '—' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Sex</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block capitalize">
                            {{ $member->sex === 'na' ? 'Prefer not to say' : ucfirst($member->sex) }}
                        </span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Member Since</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">
                            {{ $member->created_at?->format('F d, Y') ?? '—' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Contact Information 3D Frosted Glass Block --}}
            <div class="bg-slate-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.35)] p-6 sm:p-8 hover:border-white/20 transition-all">
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight">Contact & Address Information</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Primary communication details and residential address.</p>
                    </div>
                    <span class="text-[11px] font-medium text-slate-300 bg-white/5 px-2.5 py-1 rounded-lg border border-white/10">
                        Read-Only View
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-400 block">Email Address</span>
                            <button
                                type="button"
                                wire:click="$dispatch('openSubmitEmailChangeModal')"
                                class="text-[11px] font-semibold text-amber-400 hover:text-amber-300 transition cursor-pointer flex items-center gap-1"
                                title="Request Email Address Change"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                <span>Change Email</span>
                            </button>
                        </div>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->email }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Contact Phone Number</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->contact_number }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">House / Building</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->address_house_no }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Road / Street</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->address_road_no ?: '—' }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Area / Locality</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->address_area }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">ZIP / Postal Code</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->address_zip_code }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">District</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block capitalize">{{ ucfirst($member->address_district) }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Division</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block capitalize">{{ ucfirst($member->address_division) }}</span>
                    </div>
                </div>
            </div>

            {{-- Education & Occupation Details 3D Frosted Glass Block --}}
            <div class="bg-slate-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.35)] p-6 sm:p-8 hover:border-white/20 transition-all">
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight">Education & Background</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Academic background and current occupational profile.</p>
                    </div>
                    <span class="text-[11px] font-medium text-slate-300 bg-white/5 px-2.5 py-1 rounded-lg border border-white/10">
                        Read-Only View
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Current Occupation</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block capitalize">{{ ucfirst($member->occupation) }}</span>
                    </div>

                    <div class="sm:col-span-2">
                        <span class="text-xs font-medium text-slate-400 block">Institution of Study / Employer</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->institution_study }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-400 block">Highest Certification</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block capitalize">
                            {{ ucwords(str_replace('_', ' ', (string) $member->highest_certification)) }}
                        </span>
                    </div>

                    <div class="sm:col-span-2">
                        <span class="text-xs font-medium text-slate-400 block">Field / Subject Area</span>
                        <span class="text-sm font-semibold text-slate-100 mt-1 block">{{ $member->area_of_subject }}</span>
                    </div>
                </div>

                {{-- Notice Box in Dark Frosted Glass --}}
                <div class="mt-8 pt-6 border-t border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-950/60 -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 p-6 rounded-b-3xl">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-xs font-semibold text-white">Need to update your personal details?</p>
                            <p class="text-xs text-slate-400 mt-0.5">To update your contact or address details, please submit a Change Request for staff review.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        wire:click="$dispatch('openSubmitProfileChangeRequestModal')"
                        class="px-4 py-2.5 min-h-[44px] text-xs font-semibold rounded-xl text-amber-300 bg-amber-500/15 border border-amber-400/30 hover:bg-amber-500/25 shadow-xs transition-colors shrink-0 cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span>Submit Change Request</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Tab 2: Uploaded Documents in 3D Frosted Glass --}}
    @if ($activeTab === 'documents')
        <div class="bg-slate-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.35)] p-6 sm:p-8">
            <div class="pb-4 mb-6 border-b border-white/10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-white tracking-tight">Uploaded Credentials & Identity Documents</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Encrypted private documents provided during membership registration.</p>
                </div>
                <span class="text-[11px] font-medium text-emerald-300 bg-emerald-500/15 px-3 py-1 rounded-xl border border-emerald-400/30 flex items-center gap-1.5 self-start sm:self-auto">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                    </svg>
                    AES-256 Encrypted
                </span>
            </div>

            @php
                $attachments = $member->lead?->attachments ?? collect();
            @endphp

            @if ($attachments->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-white/5 border border-white/10 text-slate-400 flex items-center justify-center mx-auto mb-3" aria-hidden="true">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold text-slate-200">No uploaded documents on record.</h4>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Uploaded identity cards and photographs will appear here.</p>
                </div>
            @else
                <div class="overflow-x-auto -mx-6 sm:mx-0">
                    <table class="min-w-full divide-y divide-white/10 text-left text-xs" aria-label="Member Uploaded Documents Table">
                        <thead class="bg-slate-950/70 text-slate-300 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th scope="col" class="py-3 px-4">Document Type</th>
                                <th scope="col" class="py-3 px-4">File Name</th>
                                <th scope="col" class="py-3 px-4">Size & Format</th>
                                <th scope="col" class="py-3 px-4">Verification</th>
                                <th scope="col" class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 bg-transparent text-slate-200">
                            @foreach ($attachments as $doc)
                                @php
                                    $label = match ($doc->document_type) {
                                        'identity_document' => 'Identity Document (NID / Passport / Birth Certificate)',
                                        'studio_photo' => 'Studio Identification Photograph',
                                        'parental_consent' => 'Youth / Parental Consent Form',
                                        default => ucfirst(str_replace('_', ' ', (string) $doc->document_type)),
                                    };
                                    $isPhoto = $doc->document_type === 'studio_photo';
                                @endphp
                                <tr class="hover:bg-white/5 transition-colors">
                                    <td class="py-3.5 px-4 font-semibold text-white">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-lg bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 flex items-center justify-center shrink-0" aria-hidden="true">
                                                @if ($isPhoto)
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                @else
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                @endif
                                            </div>
                                            <span>{{ $label }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-300 font-mono text-[11px] truncate max-w-[200px]" title="{{ $doc->original_filename }}">
                                        {{ $doc->original_filename }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-400 text-[11px]">
                                        {{ number_format($doc->size_bytes / 1024, 1) }} KB ({{ $doc->mime_type }})
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if ($doc->youth_protection)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-500/20 text-blue-300 border border-blue-500/30">
                                                Minor Protected
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                                Verified
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <a
                                            href="{{ route('portal.documents.download', ['attachment' => $doc->id]) }}"
                                            target="_blank"
                                            aria-label="Download {{ $label }}: {{ $doc->original_filename }}"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 min-h-[44px] rounded-xl text-xs font-semibold text-amber-300 hover:text-amber-200 bg-amber-500/15 hover:bg-amber-500/25 border border-amber-400/30 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400 shadow-xs"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            <span>Download</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- Tab 3: Official Correspondence --}}
    @if ($activeTab === 'communications')
        <div>
            <livewire:portal.member-portal-communication-history />
        </div>
    @endif

    {{-- Change Request Modals --}}
    <livewire:portal.modals.submit-profile-change-request-modal :member="$member" />
    <livewire:portal.modals.submit-email-change-modal :member="$member" />
    <livewire:portal.modals.submit-renewal-modal :member="$member" />

    {{-- =========================================================================
         INTERACTIVE 3D HOLOGRAPHIC DIGITAL MEMBER ID CARD POP-UP MODAL
         Inspired by translucent holographic pass UI (media_1793890922310_d72c2a77.jpg)
         Pure CSS GPU-accelerated transforms, zero heavy WebGL dependencies.
         ========================================================================= --}}
    <div
        x-show="idCardModalOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-label="3D Holographic Digital Member ID Card Pass"
        @keydown.escape.window="idCardModalOpen = false"
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6"
    >
        <!-- Modal Backdrop with deep dark blur -->
        <div
            x-show="idCardModalOpen"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="idCardModalOpen = false"
            class="fixed inset-0 bg-slate-950/85 backdrop-blur-md"
            aria-hidden="true"
        ></div>

        <!-- 3D Card Modal Container -->
        <div
            x-show="idCardModalOpen"
            x-transition:enter="transition ease-out duration-400 transform"
            x-transition:enter-start="opacity-0 scale-90 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-90 translate-y-4"
            class="relative w-full max-w-md mx-auto z-10"
        >
            <!-- 3D Card Shell with Translucent Frosted Glass & Neon Edge Glow -->
            <div class="relative rounded-3xl p-6 sm:p-7 bg-slate-900/85 backdrop-blur-2xl border-2 border-cyan-400/50 shadow-[0_0_60px_rgba(6,182,212,0.3)] ring-1 ring-cyan-300/30 overflow-hidden transform transition-all duration-300 hover:scale-[1.01]">
                <!-- Holographic Ambient Color Orbs -->
                <div class="absolute -top-24 -left-24 w-60 h-60 bg-cyan-500/20 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
                <div class="absolute -bottom-24 -right-24 w-60 h-60 bg-amber-500/20 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
                <div class="absolute inset-0 bg-gradient-to-tr from-white/10 via-transparent to-white/5 pointer-events-none" aria-hidden="true"></div>

                <!-- Top Card Branding Row with Smart Chip -->
                <div class="relative flex items-center justify-between pb-4 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-full border-2 border-amber-400/90 ring-2 ring-amber-400/30 bg-white p-0.5 shadow-md flex items-center justify-center shrink-0">
                            <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center" class="w-full h-full object-contain rounded-full">
                        </div>
                        <div>
                            <span class="text-[10px] font-mono uppercase tracking-widest text-cyan-300 font-semibold block leading-tight">Digital Membership Pass</span>
                            <span class="text-sm font-bold text-white tracking-tight block">EMK CENTER</span>
                            <span class="text-[10px] text-slate-300 font-medium block">American Spaces Bangladesh</span>
                        </div>
                    </div>

                    <!-- Realistic Smart Contactless Chip Graphic -->
                    <div class="flex flex-col items-end">
                        <div class="w-10 h-7 rounded-md bg-gradient-to-br from-amber-300 via-amber-400 to-amber-600 border border-amber-200/80 shadow-inner p-1 relative flex items-center justify-center overflow-hidden" title="Smart Contactless Pass Chip">
                            <div class="w-full h-full border border-amber-800/40 rounded flex flex-col justify-around py-0.5">
                                <div class="w-full h-px bg-amber-800/50"></div>
                                <div class="w-full h-px bg-amber-800/50"></div>
                            </div>
                            <div class="absolute inset-y-0 w-2.5 border-x border-amber-800/40"></div>
                        </div>
                        <span class="text-[9px] font-mono text-cyan-300/80 mt-1 uppercase tracking-wider">NFC VERIFIED</span>
                    </div>
                </div>

                <!-- Central Holographic Avatar Ring -->
                <div class="relative py-5 flex flex-col items-center text-center">
                    <div class="relative">
                        <!-- Outer Glowing Ring Gradient -->
                        <div class="w-24 h-24 rounded-full p-1 bg-gradient-to-tr from-cyan-400 via-amber-400 to-rose-500 shadow-[0_0_30px_rgba(6,182,212,0.4)] flex items-center justify-center">
                            @if ($photoAttachment)
                                <img
                                    src="{{ route('portal.documents.download', ['attachment' => $photoAttachment->id]) }}"
                                    alt="{{ $member->full_name }}"
                                    class="w-full h-full object-cover rounded-full bg-slate-900"
                                >
                            @else
                                <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center text-white font-black text-2xl tracking-wider">
                                    {{ strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)) }}
                                </div>
                            @endif
                        </div>

                        <!-- Verified Checkmark Badge -->
                        <div class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center border-2 border-slate-900 shadow-md" title="Verified Active Member">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>

                    <!-- Member Name & ID Badge -->
                    <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight mt-3">
                        {{ $member->full_name }}
                    </h3>

                    <div class="mt-2 inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-cyan-950/70 border border-cyan-400/40 text-cyan-300 font-mono text-xs sm:text-sm font-bold shadow-inner">
                        <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                        <span>{{ $member->member_id }}</span>
                    </div>

                    <!-- Card Credentials Grid -->
                    <div class="w-full grid grid-cols-2 gap-2.5 mt-5 text-left text-xs bg-slate-950/50 p-3.5 rounded-2xl border border-white/10">
                        <div>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 block">Plan / Type</span>
                            <span class="text-white font-bold text-xs mt-0.5 block truncate">{{ $member->term?->name ?? 'Standard Membership' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 block">Status</span>
                            <span class="inline-flex items-center gap-1 text-emerald-400 font-bold text-xs mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                {{ ucfirst($member->status) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 block">Valid Until</span>
                            <span class="text-white font-bold text-xs mt-0.5 block">{{ $member->expiry_date?->format('M d, Y') ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 block">Physical Card No</span>
                            <span class="text-amber-300 font-mono font-bold text-xs mt-0.5 block truncate">{{ $this->latestIdCard?->card_number ?? 'In Queue' }}</span>
                        </div>
                    </div>

                    <!-- High-Tech Security Barcode Strip -->
                    <div class="w-full mt-4 p-3 bg-white/5 rounded-2xl border border-white/10 flex flex-col items-center">
                        <div class="w-full flex items-center justify-center gap-1 h-8 opacity-80" aria-hidden="true">
                            <span class="w-1 h-8 bg-cyan-300"></span>
                            <span class="w-0.5 h-8 bg-cyan-300"></span>
                            <span class="w-2 h-8 bg-cyan-300"></span>
                            <span class="w-0.5 h-8 bg-cyan-300"></span>
                            <span class="w-1.5 h-8 bg-cyan-300"></span>
                            <span class="w-0.5 h-8 bg-cyan-300"></span>
                            <span class="w-2.5 h-8 bg-cyan-300"></span>
                            <span class="w-1 h-8 bg-cyan-300"></span>
                            <span class="w-0.5 h-8 bg-cyan-300"></span>
                            <span class="w-2 h-8 bg-cyan-300"></span>
                            <span class="w-1.5 h-8 bg-cyan-300"></span>
                            <span class="w-0.5 h-8 bg-cyan-300"></span>
                            <span class="w-1 h-8 bg-cyan-300"></span>
                            <span class="w-2 h-8 bg-cyan-300"></span>
                            <span class="w-0.5 h-8 bg-cyan-300"></span>
                            <span class="w-1.5 h-8 bg-cyan-300"></span>
                            <span class="w-2.5 h-8 bg-cyan-300"></span>
                            <span class="w-1 h-8 bg-cyan-300"></span>
                        </div>
                        <span class="text-[10px] font-mono tracking-widest text-slate-400 mt-1 uppercase">{{ $member->member_id }} • OFFICIAL EMK DIGITAL PASS</span>
                    </div>
                </div>

                <!-- Action Buttons: Finger-Friendly Touch Targets (Min 48px) -->
                <div class="relative pt-4 border-t border-white/10 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        onclick="window.print()"
                        class="min-h-[48px] px-4 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-bold text-xs sm:text-sm shadow-lg shadow-cyan-500/25 flex items-center justify-center gap-2 cursor-pointer transition transform hover:scale-[1.02] active:scale-[0.98]"
                        title="Print or Save Digital Pass"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Print Pass</span>
                    </button>

                    <button
                        type="button"
                        @click="idCardModalOpen = false"
                        class="min-h-[48px] px-4 py-3 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 text-slate-200 border border-white/10 font-semibold text-xs sm:text-sm flex items-center justify-center gap-2 cursor-pointer transition"
                    >
                        <span>Close</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
