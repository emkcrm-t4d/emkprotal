<div class="space-y-6">
    @if (session('status'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- Membership Summary Header --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-6 sm:p-8 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                {{-- Member Identity & Badges --}}
                <div class="flex items-start gap-4">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-indigo-600 via-blue-500 to-sky-400 p-0.5 shadow-md shrink-0">
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
                            <div class="w-full h-full rounded-[14px] bg-slate-800 flex items-center justify-center text-white font-bold text-xl sm:text-2xl">
                                {{ strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)) }}
                            </div>
                        @endif
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <span class="font-mono text-xs sm:text-sm font-bold bg-white/10 text-white px-2.5 py-0.5 rounded-lg border border-white/20 tracking-wider">
                                {{ $member->member_id }}
                            </span>

                            @if ($member->status === 'active')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    Active Member
                                </span>
                            @elseif ($member->status === 'inactive')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                    Inactive / Expired
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                    {{ ucfirst($member->status) }}
                                </span>
                            @endif
                        </div>

                        <p class="text-xs text-indigo-300 font-medium">
                            Welcome, {{ $member->full_name }}
                        </p>
                        <h2 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                            {{ $member->full_name }}
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-300 mt-0.5 flex items-center gap-2">
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
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-sm transition-colors cursor-pointer"
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
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-slate-100 bg-slate-50 border-t border-slate-200">
            {{-- Term Specification --}}
            <div class="p-4 sm:p-5">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 block">Membership Term</span>
                <span class="text-sm font-bold text-slate-900 mt-1 block">
                    {{ $member->term?->name ?? 'Standard Membership' }}
                </span>
                <span class="text-xs text-slate-500 mt-0.5 block">
                    Duration: {{ $member->term?->duration_months ?? 12 }} Months
                </span>
            </div>

            {{-- Validity Window --}}
            <div class="p-4 sm:p-5">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 block">Validity Period</span>
                <div class="text-sm font-bold text-slate-900 mt-1 flex items-center gap-1.5">
                    <span>{{ $member->start_date?->format('M d, Y') ?? '—' }}</span>
                    <span class="text-slate-400">&rarr;</span>
                    <span>{{ $member->expiry_date?->format('M d, Y') ?? '—' }}</span>
                </div>
                @php
                    $isExpired = $member->expiry_date && $member->expiry_date->isPast();
                    $daysRemaining = $member->expiry_date ? (int) now()->diffInDays($member->expiry_date, false) : 0;
                @endphp
                <span class="text-xs {{ $isExpired ? 'text-rose-600 font-medium' : ($daysRemaining < 30 ? 'text-amber-600 font-medium' : 'text-slate-500') }} mt-0.5 block">
                    @if ($isExpired)
                        Expired {{ abs($daysRemaining) }} days ago
                    @else
                        {{ $daysRemaining }} days remaining
                    @endif
                </span>
            </div>

            {{-- Physical ID Card Indicator --}}
            <div class="p-4 sm:p-5 sm:col-span-2 md:col-span-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 block">Physical ID Card</span>
                <div class="mt-1 flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $this->idCardStatusColor }}">
                        {{ $this->idCardStatusLabel }}
                    </span>
                </div>
                <span class="text-xs font-mono text-slate-500 mt-1 block">
                    Card No: {{ $this->latestIdCard?->card_number ?? 'In Queue' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="border-b border-slate-200">
        <nav class="flex space-x-8" aria-label="Tabs">
            <button
                type="button"
                wire:click="setTab('profile')"
                class="py-4 px-1 inline-flex items-center gap-2 border-b-2 text-sm font-semibold transition cursor-pointer {{ $activeTab === 'profile' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Personal Profile</span>
            </button>

            <button
                type="button"
                wire:click="setTab('documents')"
                class="py-4 px-1 inline-flex items-center gap-2 border-b-2 text-sm font-semibold transition cursor-pointer {{ $activeTab === 'documents' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Uploaded Documents</span>
                @if ($member->lead?->attachments && $member->lead->attachments->count() > 0)
                    <span class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                        {{ $member->lead->attachments->count() }}
                    </span>
                @endif
            </button>

            <button
                type="button"
                wire:click="setTab('communications')"
                class="py-4 px-1 inline-flex items-center gap-2 border-b-2 text-sm font-semibold transition cursor-pointer {{ $activeTab === 'communications' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <span>Official Correspondence</span>
            </button>
        </nav>
    </div>

    {{-- Tab 1: Personal & Contact Information (Strictly Read-Only) --}}
    @if ($activeTab === 'profile')
        <div class="space-y-6">
            {{-- Personal & Demographic Details --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-8">
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Personal & Demographic Details</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Verified applicant identity and personal specifications.</p>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200">
                        Read-Only View
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <span class="text-xs font-medium text-slate-500 block">First Name</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->first_name }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Middle Name</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->middle_name ?: '—' }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Last Name</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->last_name }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Date of Birth</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">
                            {{ $member->date_of_birth?->format('F d, Y') ?? '—' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Sex</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block capitalize">
                            {{ $member->sex === 'na' ? 'Prefer not to say' : ucfirst($member->sex) }}
                        </span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Member Since</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">
                            {{ $member->created_at?->format('F d, Y') ?? '—' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Contact Information --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-8">
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Contact & Address Information</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Primary communication details and residential address.</p>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200">
                        Read-Only View
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-500 block">Email Address</span>
                            <button
                                type="button"
                                wire:click="$dispatch('openSubmitEmailChangeModal')"
                                class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 transition cursor-pointer flex items-center gap-1"
                                title="Request Email Address Change"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                <span>Change Email</span>
                            </button>
                        </div>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->email }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Contact Phone Number</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->contact_number }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">House / Building</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->address_house_no }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Road / Street</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->address_road_no ?: '—' }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Area / Locality</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->address_area }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">ZIP / Postal Code</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->address_zip_code }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">District</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block capitalize">{{ ucfirst($member->address_district) }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Division</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block capitalize">{{ ucfirst($member->address_division) }}</span>
                    </div>
                </div>
            </div>

            {{-- Education & Occupation Details --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-8">
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Education & Background</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Academic background and current occupational profile.</p>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200">
                        Read-Only View
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Current Occupation</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block capitalize">{{ ucfirst($member->occupation) }}</span>
                    </div>

                    <div class="sm:col-span-2">
                        <span class="text-xs font-medium text-slate-500 block">Institution of Study / Employer</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->institution_study }}</span>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 block">Highest Certification</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block capitalize">
                            {{ ucwords(str_replace('_', ' ', (string) $member->highest_certification)) }}
                        </span>
                    </div>

                    <div class="sm:col-span-2">
                        <span class="text-xs font-medium text-slate-500 block">Field / Subject Area</span>
                        <span class="text-sm font-semibold text-slate-900 mt-1 block">{{ $member->area_of_subject }}</span>
                    </div>
                </div>

                {{-- Notice Box --}}
                <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 -mx-6 sm:-mx-8 -mb-6 sm:-mb-8 p-6 rounded-b-2xl">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-xs font-semibold text-slate-900">Need to update your personal details?</p>
                            <p class="text-xs text-slate-500 mt-0.5">To update your contact or address details, please submit a Change Request for staff review.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="px-4 py-2 text-xs font-semibold rounded-xl text-indigo-700 bg-white border border-indigo-200 hover:bg-indigo-50 shadow-2xs transition-colors shrink-0 cursor-pointer"
                    >
                        Submit Change Request
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Tab 2: Uploaded Documents --}}
    @if ($activeTab === 'documents')
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-8">
            <div class="pb-4 mb-6 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Uploaded Credentials & Identity Documents</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Encrypted private documents provided during membership registration.</p>
                </div>
                <span class="text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
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
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold text-slate-800">No uploaded documents on record.</h4>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Uploaded identity cards and photographs will appear here.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50/50 flex flex-col justify-between hover:border-indigo-300 transition-colors">
                            <div>
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                                            @if ($isPhoto)
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            @else
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900">{{ $label }}</p>
                                            <p class="text-[11px] text-slate-500 truncate max-w-[200px]" title="{{ $doc->original_filename }}">
                                                {{ $doc->original_filename }}
                                            </p>
                                        </div>
                                    </div>

                                    @if ($doc->youth_protection)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                            Minor Protected
                                        </span>
                                    @endif
                                </div>

                                <div class="text-[11px] text-slate-400 mt-2 flex items-center gap-3">
                                    <span>Size: {{ number_format($doc->size_bytes / 1024, 1) }} KB</span>
                                    <span>•</span>
                                    <span>Type: {{ $doc->mime_type }}</span>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-200/60 flex items-center justify-between">
                                <span class="text-[11px] text-slate-500">Verified</span>
                                <a
                                    href="{{ route('portal.documents.download', ['attachment' => $doc->id]) }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-700 hover:text-indigo-900 bg-white hover:bg-indigo-50 border border-indigo-200 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>Download</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
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
</div>
