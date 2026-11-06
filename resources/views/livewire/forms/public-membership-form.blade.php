<div x-data="{
    division: @entangle('address_division').live,
    district: @entangle('address_district').live,
    dob: @entangle('date_of_birth').live,
    occupation: @entangle('occupation').live,
    certification: @entangle('highest_certification').live,
    hasUsExp: @entangle('has_us_experience').live,
    isAlumni: @entangle('is_american_alumni').live,
    calculateAge() {
        if (!this.dob) return null;
        const birthDate = new Date(this.dob);
        if (isNaN(birthDate.getTime())) return null;
        const diffMs = Date.now() - birthDate.getTime();
        const ageDt = new Date(diffMs);
        return Math.abs(ageDt.getUTCFullYear() - 1970);
    },
    isUnder18() {
        const age = this.calculateAge();
        return age !== null && age < 18;
    }
}" class="space-y-8">

    <!-- Form Header Banner -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="h-3 bg-gradient-to-r from-blue-700 via-indigo-600 to-sky-500"></div>
        <div class="p-6 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-6 mb-6">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 mb-2">
                        <svg class="w-3.5 h-3.5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                        Official Membership Application
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        {{ $form->title ?? 'EMK Center Membership Application Form' }}
                    </h1>
                    <p class="text-slate-600 mt-2 text-sm sm:text-base leading-relaxed max-w-3xl">
                        {{ $form->description ?? 'Welcome to American Spaces Bangladesh. Please complete all 7 sections below to register for membership at EMK Center or an American Corner.' }}
                    </p>
                </div>
            </div>

            <!-- Steps Breadcrumbs / Summary Bar -->
            <nav aria-label="Form Sections Overview" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2 text-center text-xs font-medium text-slate-500">
                <div class="p-2 rounded-lg bg-slate-50 border border-slate-200 text-indigo-700 font-semibold">1. Personal</div>
                <div class="p-2 rounded-lg bg-slate-50 border border-slate-200">2. Minor Consent</div>
                <div class="p-2 rounded-lg bg-slate-50 border border-slate-200">3. Address</div>
                <div class="p-2 rounded-lg bg-slate-50 border border-slate-200">4. Contact</div>
                <div class="p-2 rounded-lg bg-slate-50 border border-slate-200">5. Education</div>
                <div class="p-2 rounded-lg bg-slate-50 border border-slate-200">6. Documents</div>
                <div class="p-2 rounded-lg bg-slate-50 border border-slate-200">7. Payment</div>
            </nav>
        </div>
    </div>

    @if ($submitted || $is_submitted)
        <!-- Submission Success Confirmation Panel -->
        <div class="bg-white rounded-2xl shadow-lg border border-emerald-200 p-8 text-center space-y-6" role="status" aria-label="Application Submitted Successfully">
            <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto text-emerald-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div class="space-y-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                    Application Submitted Successfully!
                </span>
                <h2 class="text-2xl font-bold text-slate-900">Application Submitted Successfully!</h2>
                <p class="text-slate-600 max-w-xl mx-auto text-sm">
                    Thank you for applying for membership. Your application has been logged into our secure verification pipeline.
                </p>
            </div>
            <div class="inline-block bg-slate-50 border border-slate-200 rounded-xl px-6 py-4 text-left max-w-md w-full">
                <div class="text-xs text-slate-500 font-medium uppercase tracking-wider">Application Reference ID</div>
                <div class="flex items-center justify-between gap-2 mt-1">
                    <span class="text-lg font-mono font-bold text-indigo-700 select-all" id="app-reference-code">{{ $reference_uuid ?? $submission_uuid }}</span>
                    <button
                        type="button"
                        x-on:click="navigator.clipboard.writeText('{{ $reference_uuid ?? $submission_uuid }}')"
                        class="px-2.5 py-1 text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-200 transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        title="Copy to clipboard"
                        aria-label="Copy application reference code to clipboard"
                    >
                        Copy
                    </button>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-200 text-xs text-slate-600 space-y-1">
                    <div>Applicant: <strong class="text-slate-800">{{ $first_name }} {{ $last_name }}</strong></div>
                    <div>Email: <strong class="text-slate-800">{{ $email }}</strong></div>
                    <div>Selected Space: <strong class="text-slate-800">{{ ucwords(str_replace('_', ' ', $american_space)) }}</strong></div>
                    <div>bKash Transaction ID: <strong class="text-slate-800 font-mono">{{ $bkash_txid }}</strong></div>
                </div>
            </div>
            <div class="text-xs text-slate-600 max-w-lg mx-auto text-left space-y-2 bg-emerald-50 p-4 rounded-xl border border-emerald-200">
                <p class="font-bold text-emerald-900">What Happens Next:</p>
                <p class="text-emerald-800 font-medium">Our verification team will review your payment, documents, and photo within 2-3 business days.</p>
                <ul class="list-disc list-inside space-y-1 text-emerald-700">
                    <li>Payment verification against bKash merchant statement.</li>
                    <li>Identity document and eligibility compliance check.</li>
                    <li>Studio passport photo check for digital ID card generation.</li>
                </ul>
            </div>
        </div>
    @else
        <!-- Application Form -->
        <form wire:submit.prevent="submit" class="space-y-8" enctype="multipart/form-data">

            <!-- Global Error Banner -->
            @if ($errors->any())
                <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-xl shadow-xs" role="alert" aria-live="polite">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div class="ml-3">
                            <h3 class="text-sm font-bold text-rose-800">Please review the required fields</h3>
                            <p class="text-xs text-rose-700 mt-1">There are {{ $errors->count() }} validation error(s) preventing form submission. See highlighted sections below.</p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($errorMessage)
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-sm" role="alert">
                    {{ $errorMessage }}
                </div>
            @endif

            <!-- ==========================================
                 SECTION 1: Personal Information (Fields 1-6)
                 ========================================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 inline-flex items-center justify-center text-xs font-bold" aria-hidden="true">1</span>
                        Personal Information
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">Full legal name, American Space selection, sex, and date of birth.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Field 1: First Name -->
                    <div>
                        <label for="first_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            First Name <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            wire:model.live.debounce.300ms="first_name"
                            placeholder="e.g. Rahim"
                            aria-required="true"
                            @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('first_name') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        <p class="text-xs text-slate-500 mt-1">2-50 English letters, spaces, hyphens, apostrophes.</p>
                        @error('first_name') <span id="first_name-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 2: Middle Name -->
                    <div>
                        <label for="middle_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Middle Name <span class="text-slate-500 font-normal">(Optional)</span>
                        </label>
                        <input
                            type="text"
                            id="middle_name"
                            name="middle_name"
                            wire:model.live.debounce.300ms="middle_name"
                            placeholder="e.g. Uddin"
                            @error('middle_name') aria-invalid="true" aria-describedby="middle_name-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('middle_name') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        <p class="text-xs text-slate-500 mt-1">Middle name or initial (up to 50 chars).</p>
                        @error('middle_name') <span id="middle_name-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 3: Last Name -->
                    <div>
                        <label for="last_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Last Name <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            wire:model.live.debounce.300ms="last_name"
                            placeholder="e.g. Ahmed"
                            aria-required="true"
                            @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('last_name') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        <p class="text-xs text-slate-500 mt-1">Surname or family name.</p>
                        @error('last_name') <span id="last_name-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Field 4: American Spaces -->
                    <div>
                        <label for="american_space" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            American Space <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="american_space"
                            name="american_space"
                            wire:model.live="american_space"
                            aria-required="true"
                            @error('american_space') aria-invalid="true" aria-describedby="american_space-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('american_space') border-rose-400 bg-rose-50/30 @enderror"
                        >
                            <option value="">-- Select American Space --</option>
                            <option value="emk_center">EMK Center (Dhaka)</option>
                            <option value="american_center">American Center (Dhaka)</option>
                            <option value="barishal_corner">Barishal Corner</option>
                            <option value="chattogram_corner">Chattogram Corner</option>
                            <option value="rajshahi_corner">Rajshahi Corner</option>
                            <option value="sylhet_corner">Sylhet Corner</option>
                        </select>
                        @error('american_space') <span id="american_space-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 5: Sex -->
                    <div>
                        <label for="sex" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Sex <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="sex"
                            name="sex"
                            wire:model.live="sex"
                            aria-required="true"
                            @error('sex') aria-invalid="true" aria-describedby="sex-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('sex') border-rose-400 bg-rose-50/30 @enderror"
                        >
                            <option value="">-- Select Sex --</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="na">N/A</option>
                        </select>
                        @error('sex') <span id="sex-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 6: Date of Birth -->
                    <div>
                        <label for="date_of_birth" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Date of Birth <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            id="date_of_birth"
                            name="date_of_birth"
                            wire:model.live.debounce.300ms="date_of_birth"
                            max="{{ now()->format('Y-m-d') }}"
                            aria-required="true"
                            @error('date_of_birth') aria-invalid="true" aria-describedby="date_of_birth-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('date_of_birth') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        <div id="age-display" class="flex items-center justify-between text-xs text-slate-500 mt-1" aria-live="polite">
                            <span>Format: YYYY-MM-DD</span>
                            @if ($age_years !== null)
                                <span class="font-semibold text-slate-700">Age: {{ $age_years }} years</span>
                            @endif
                            <span x-show="dob && calculateAge() !== null" class="font-semibold text-slate-700" x-text="'Age: ' + calculateAge() + ' years'"></span>
                        </div>
                        @error('date_of_birth') <span id="date_of_birth-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 SECTION 2: Minor Applicant Consent (Field 7 - DEC-09)
                 ========================================== -->
            <div
                x-show="isUnder18()"
                x-cloak
                role="region"
                aria-live="polite"
                aria-label="Age and consent notice"
                class="bg-amber-50/60 rounded-2xl shadow-sm border border-amber-300 p-6 sm:p-8 space-y-4"
                id="minor-consent-section"
            >
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-amber-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-base font-bold text-amber-900">
                            Minor Applicant Consent Notice (Under 18 Years)
                        </h2>
                        <p class="text-xs sm:text-sm text-amber-800 mt-1 leading-relaxed">
                            Because you are under 18 years old, a signed Parental/Guardian Consent Form is required.
                            Please download the official template, have your parent or legal guardian complete and sign it, and upload the signed document below.
                        </p>
                    </div>
                </div>

                <!-- Template Download Card -->
                <div class="bg-white rounded-xl border border-amber-200 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-xs uppercase border border-rose-100" aria-hidden="true">
                            PDF
                        </div>
                        <div>
                            <div class="text-xs font-bold text-slate-800">EMK-Parental-Consent-Form.pdf</div>
                            <div class="text-xs text-slate-500">Official Parental/Guardian Consent Template (U.S. Embassy / EMK Center)</div>
                        </div>
                    </div>
                    <a
                        href="/forms/templates/EMK-Parental-Consent-Form.pdf"
                        target="_blank"
                        download
                        aria-label="Download Parental Consent Form Template (PDF)"
                        class="inline-flex items-center gap-2 px-4 py-2 min-h-[44px] rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-xs transition focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download Consent Form Template
                    </a>
                </div>

                <!-- Field 7: File Upload -->
                <div>
                    <label for="parental_consent_file" class="block text-xs font-bold text-amber-900 uppercase tracking-wider mb-1">
                        Upload Signed Parental/Guardian Consent Form <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="file"
                        id="parental_consent_file"
                        name="parental_consent_file"
                        wire:model="parental_consent_file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        aria-required="true"
                        @error('parental_consent_file') aria-invalid="true" aria-describedby="parental_consent_file-error" @enderror
                        class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 cursor-pointer border border-amber-300 rounded-xl bg-white p-1 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                    <div class="flex items-center justify-between text-xs text-slate-500 mt-1">
                        <span>Accepted formats: PDF, JPG, JPEG, PNG (Max 4MB)</span>
                        <div wire:loading wire:target="parental_consent_file" class="text-indigo-600 font-medium">Uploading...</div>
                    </div>
                    @error('parental_consent_file') <span id="parental_consent_file-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                </div>
            </div>

            <!-- ==========================================
                 SECTION 3: Address in Bangladesh (Fields 8-13)
                 ========================================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 inline-flex items-center justify-center text-xs font-bold" aria-hidden="true">3</span>
                        Address in Bangladesh
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">Residential street address, administrative division, and cascading district.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Field 8: House No -->
                    <div>
                        <label for="address_house_no" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            House / Building No. <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="address_house_no"
                            name="address_house_no"
                            wire:model.live.debounce.300ms="address_house_no"
                            placeholder="e.g. House 12, Apt 4B"
                            aria-required="true"
                            @error('address_house_no') aria-invalid="true" aria-describedby="address_house_no-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('address_house_no') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('address_house_no') <span id="address_house_no-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 9: Road No -->
                    <div>
                        <label for="address_road_no" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Road No. / Street <span class="text-slate-500 font-normal">(Optional)</span>
                        </label>
                        <input
                            type="text"
                            id="address_road_no"
                            name="address_road_no"
                            wire:model.live.debounce.300ms="address_road_no"
                            placeholder="e.g. Road 5"
                            @error('address_road_no') aria-invalid="true" aria-describedby="address_road_no-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('address_road_no') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('address_road_no') <span id="address_road_no-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Field 10: Area -->
                    <div>
                        <label for="address_area" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Area / Neighborhood <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="address_area"
                            name="address_area"
                            wire:model.live.debounce.300ms="address_area"
                            placeholder="e.g. Dhanmondi / Gulshan"
                            aria-required="true"
                            @error('address_area') aria-invalid="true" aria-describedby="address_area-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('address_area') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('address_area') <span id="address_area-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 11: ZIP Code -->
                    <div>
                        <label for="address_zip_code" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            ZIP / Postal Code <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="address_zip_code"
                            name="address_zip_code"
                            wire:model.live.debounce.300ms="address_zip_code"
                            placeholder="e.g. 1209"
                            aria-required="true"
                            @error('address_zip_code') aria-invalid="true" aria-describedby="address_zip_code-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('address_zip_code') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('address_zip_code') <span id="address_zip_code-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Fields 12 & 13: Division to District Cascading Hierarchy -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <!-- Field 12: Administrative Division -->
                    <div>
                        <label for="address_division" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Administrative Division <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="address_division"
                            name="address_division"
                            wire:model.live="address_division"
                            aria-required="true"
                            @error('address_division') aria-invalid="true" aria-describedby="address_division-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 bg-white @error('address_division') border-rose-400 bg-rose-50/30 @enderror"
                        >
                            <option value="">-- Select Division (8 Divisions) --</option>
                            @foreach ($divisions as $divKey => $divLabel)
                                <option value="{{ $divKey }}">{{ $divLabel }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Filters available districts.</p>
                        @error('address_division') <span id="address_division-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 13: District (Cascading) -->
                    <div>
                        <label for="address_district" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            District <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="address_district"
                            name="address_district"
                            wire:model="address_district"
                            @disabled(empty($available_districts))
                            aria-required="true"
                            @error('address_district') aria-invalid="true" aria-describedby="address_district-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 bg-white disabled:bg-slate-100 disabled:text-slate-400 @error('address_district') border-rose-400 bg-rose-50/30 @enderror"
                        >
                            <option value="">
                                {{ empty($available_districts) ? '-- Select Division First --' : '-- Select District (' . count($available_districts) . ' available) --' }}
                            </option>
                            @foreach ($available_districts as $districtOption)
                                <option value="{{ $districtOption }}">{{ ucwords(str_replace('_', ' ', $districtOption)) }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Strictly mapped to selected division.</p>
                        @error('address_district') <span id="address_district-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 SECTION 4: Contact & Online Presence (Fields 14-16)
                 ========================================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 inline-flex items-center justify-center text-xs font-bold" aria-hidden="true">4</span>
                        Contact & Online Presence
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">Direct communication channels for verification, badge notification, and account access.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Field 14: Contact Number -->
                    <div>
                        <label for="contact_number" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Mobile Contact Number <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="tel"
                            id="contact_number"
                            name="contact_number"
                            wire:model.live.debounce.300ms="contact_number"
                            placeholder="+8801XXXXXXXXX"
                            aria-required="true"
                            @error('contact_number') aria-invalid="true" aria-describedby="contact_number-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('contact_number') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        <p class="text-xs text-slate-500 mt-1">Must start with +8801 followed by 9 digits.</p>
                        @error('contact_number') <span id="contact_number-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 15: Email -->
                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Email Address <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            wire:model.live.debounce.300ms="email"
                            placeholder="applicant@example.com"
                            aria-required="true"
                            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('email') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        <p class="text-xs text-slate-500 mt-1">Core account identifier.</p>
                        @error('email') <span id="email-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 16: Website -->
                    <div>
                        <label for="website" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Website / Portfolio <span class="text-slate-500 font-normal">(Optional)</span>
                        </label>
                        <input
                            type="url"
                            id="website"
                            name="website"
                            wire:model.live.debounce.300ms="website"
                            placeholder="https://example.com"
                            @error('website') aria-invalid="true" aria-describedby="website-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('website') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        <p class="text-xs text-slate-500 mt-1">Personal portfolio or LinkedIn URL.</p>
                        @error('website') <span id="website-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 SECTION 5: Education & Professional Background (Fields 17-27)
                 ========================================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 inline-flex items-center justify-center text-xs font-bold" aria-hidden="true">5</span>
                        Education & Professional Background
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">Academic credentials, subject focus, and international exchange experience.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Field 17: Occupation & Conditional Child Input -->
                    <div>
                        <label for="occupation" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Primary Occupation <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="occupation"
                            name="occupation"
                            wire:model.live="occupation"
                            aria-required="true"
                            @error('occupation') aria-invalid="true" aria-describedby="occupation-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('occupation') border-rose-400 bg-rose-50/30 @enderror"
                        >
                            <option value="">-- Select Occupation --</option>
                            <option value="artist">Artist</option>
                            <option value="community_worker">Community Worker</option>
                            <option value="educator">Educator</option>
                            <option value="entrepreneur">Entrepreneur</option>
                            <option value="media_professional">Media Professional</option>
                            <option value="performer">Performer</option>
                            <option value="student">Student</option>
                            <option value="other">Other</option>
                        </select>
                        @error('occupation') <span id="occupation-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror

                        <!-- Conditional: Occupation Other -->
                        <div x-show="occupation === 'other'" x-cloak class="mt-3">
                            <label for="occupation_other" class="block text-xs font-bold text-indigo-700 uppercase tracking-wider mb-1">
                                Please Specify Occupation <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="occupation_other"
                                name="occupation_other"
                                wire:model.live.debounce.300ms="occupation_other"
                                placeholder="Specify your profession"
                                @error('occupation_other') aria-invalid="true" aria-describedby="occupation_other-error" @enderror
                                class="w-full rounded-xl border-indigo-300 bg-indigo-50/20 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('occupation_other') border-rose-400 bg-rose-50/30 @enderror"
                            >
                            @error('occupation_other') <span id="occupation_other-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Field 18: Institution Study -->
                    <div>
                        <label for="institution_study" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Last / Current Place of Study <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="institution_study"
                            name="institution_study"
                            wire:model.live.debounce.300ms="institution_study"
                            placeholder="e.g. University of Dhaka"
                            aria-required="true"
                            @error('institution_study') aria-invalid="true" aria-describedby="institution_study-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('institution_study') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('institution_study') <span id="institution_study-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Field 19: Highest Academic Certification & Conditional Child Input -->
                    <div>
                        <label for="highest_certification" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Highest Academic Certification <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="highest_certification"
                            name="highest_certification"
                            wire:model.live="highest_certification"
                            aria-required="true"
                            @error('highest_certification') aria-invalid="true" aria-describedby="highest_certification-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('highest_certification') border-rose-400 bg-rose-50/30 @enderror"
                        >
                            <option value="">-- Select Highest Certification --</option>
                            <option value="ssc">SSC</option>
                            <option value="o_level">O-level</option>
                            <option value="hsc">HSC</option>
                            <option value="a_level">A-level</option>
                            <option value="undergraduate_degree">Undergraduate Degree</option>
                            <option value="undergraduate_honors">Undergraduate Honors</option>
                            <option value="graduate">Graduate</option>
                            <option value="postgraduate">Postgraduate</option>
                            <option value="other">Other</option>
                        </select>
                        @error('highest_certification') <span id="highest_certification-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror

                        <!-- Conditional: Highest Certification Other -->
                        <div x-show="certification === 'other'" x-cloak class="mt-3">
                            <label for="highest_certification_other" class="block text-xs font-bold text-indigo-700 uppercase tracking-wider mb-1">
                                Please Specify Certification <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="highest_certification_other"
                                name="highest_certification_other"
                                wire:model.live.debounce.300ms="highest_certification_other"
                                placeholder="Specify your diploma/degree"
                                @error('highest_certification_other') aria-invalid="true" aria-describedby="highest_certification_other-error" @enderror
                                class="w-full rounded-xl border-indigo-300 bg-indigo-50/20 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('highest_certification_other') border-rose-400 bg-rose-50/30 @enderror"
                            >
                            @error('highest_certification_other') <span id="highest_certification_other-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Field 20: Area of Subject -->
                    <div>
                        <label for="area_of_subject" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Area of Subject / Major <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="area_of_subject"
                            name="area_of_subject"
                            wire:model.live.debounce.300ms="area_of_subject"
                            placeholder="e.g. Economics / Computer Science"
                            aria-required="true"
                            @error('area_of_subject') aria-invalid="true" aria-describedby="area_of_subject-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('area_of_subject') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('area_of_subject') <span id="area_of_subject-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Fields 21 & 22: U.S. Work/Study Experience & Conditional Location -->
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-4">
                    <fieldset class="space-y-2">
                        <legend class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                            Field 21: U.S. Work or Study Experience <span class="text-rose-500">*</span>
                        </legend>
                        <div class="flex items-center gap-6">
                            <div class="inline-flex items-center gap-2">
                                <input
                                    type="radio"
                                    id="has_us_experience_yes"
                                    name="has_us_experience"
                                    value="1"
                                    wire:model.live="has_us_experience"
                                    aria-required="true"
                                    class="text-indigo-600 focus:ring-2 focus:ring-indigo-500 min-h-[20px] min-w-[20px]"
                                >
                                <label for="has_us_experience_yes" class="text-slate-800 font-medium text-sm cursor-pointer min-h-[44px] inline-flex items-center">
                                    Yes
                                </label>
                            </div>
                            <div class="inline-flex items-center gap-2">
                                <input
                                    type="radio"
                                    id="has_us_experience_no"
                                    name="has_us_experience"
                                    value="0"
                                    wire:model.live="has_us_experience"
                                    aria-required="true"
                                    class="text-indigo-600 focus:ring-2 focus:ring-indigo-500 min-h-[20px] min-w-[20px]"
                                >
                                <label for="has_us_experience_no" class="text-slate-800 font-medium text-sm cursor-pointer min-h-[44px] inline-flex items-center">
                                    No
                                </label>
                            </div>
                        </div>
                        @error('has_us_experience') <span id="has_us_experience-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </fieldset>

                    <!-- Field 22: US Experience Location (Conditional) -->
                    <div x-show="hasUsExp == '1'" x-cloak class="pt-2">
                        <label for="us_experience_location" class="block text-xs font-bold text-indigo-700 uppercase tracking-wider mb-1">
                            Field 22: U.S. Location / Institution <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="us_experience_location"
                            name="us_experience_location"
                            wire:model.live.debounce.300ms="us_experience_location"
                            placeholder="e.g. Boston, MA or Harvard University"
                            @error('us_experience_location') aria-invalid="true" aria-describedby="us_experience_location-error" @enderror
                            class="w-full rounded-xl border-indigo-300 bg-white shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('us_experience_location') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('us_experience_location') <span id="us_experience_location-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Fields 23 & 24: American Institution Alumni & Conditional Name -->
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-4">
                    <fieldset class="space-y-2">
                        <legend class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                            Field 23: American Institution Alumni <span class="text-rose-500">*</span>
                        </legend>
                        <div class="flex items-center gap-6">
                            <div class="inline-flex items-center gap-2">
                                <input
                                    type="radio"
                                    id="is_american_alumni_yes"
                                    name="is_american_alumni"
                                    value="1"
                                    wire:model.live="is_american_alumni"
                                    aria-required="true"
                                    class="text-indigo-600 focus:ring-2 focus:ring-indigo-500 min-h-[20px] min-w-[20px]"
                                >
                                <label for="is_american_alumni_yes" class="text-slate-800 font-medium text-sm cursor-pointer min-h-[44px] inline-flex items-center">
                                    Yes
                                </label>
                            </div>
                            <div class="inline-flex items-center gap-2">
                                <input
                                    type="radio"
                                    id="is_american_alumni_no"
                                    name="is_american_alumni"
                                    value="0"
                                    wire:model.live="is_american_alumni"
                                    aria-required="true"
                                    class="text-indigo-600 focus:ring-2 focus:ring-indigo-500 min-h-[20px] min-w-[20px]"
                                >
                                <label for="is_american_alumni_no" class="text-slate-800 font-medium text-sm cursor-pointer min-h-[44px] inline-flex items-center">
                                    No
                                </label>
                            </div>
                        </div>
                        @error('is_american_alumni') <span id="is_american_alumni-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </fieldset>

                    <!-- Field 24: American Institution Name (Conditional) -->
                    <div x-show="isAlumni == '1'" x-cloak class="pt-2">
                        <label for="american_institution_name" class="block text-xs font-bold text-indigo-700 uppercase tracking-wider mb-1">
                            Field 24: American School / Institution Name <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="american_institution_name"
                            name="american_institution_name"
                            wire:model.live.debounce.300ms="american_institution_name"
                            placeholder="e.g. University of California, Berkeley"
                            @error('american_institution_name') aria-invalid="true" aria-describedby="american_institution_name-error" @enderror
                            class="w-full rounded-xl border-indigo-300 bg-white shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('american_institution_name') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('american_institution_name') <span id="american_institution_name-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Professional Details (Fields 25-27) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Field 25: Employer Name -->
                    <div>
                        <label for="employer_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Current Employer <span class="text-slate-500 font-normal">(Optional)</span>
                        </label>
                        <input
                            type="text"
                            id="employer_name"
                            name="employer_name"
                            wire:model.live.debounce.300ms="employer_name"
                            placeholder="e.g. ABC Enterprises"
                            @error('employer_name') aria-invalid="true" aria-describedby="employer_name-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('employer_name') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('employer_name') <span id="employer_name-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 26: Job Title -->
                    <div>
                        <label for="job_title" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Job Title / Role <span class="text-slate-500 font-normal">(Optional)</span>
                        </label>
                        <input
                            type="text"
                            id="job_title"
                            name="job_title"
                            wire:model.live.debounce.300ms="job_title"
                            placeholder="e.g. Project Officer"
                            @error('job_title') aria-invalid="true" aria-describedby="job_title-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('job_title') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('job_title') <span id="job_title-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 27: Work Phone -->
                    <div>
                        <label for="work_phone" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Work Phone <span class="text-slate-500 font-normal">(Optional)</span>
                        </label>
                        <input
                            type="tel"
                            id="work_phone"
                            name="work_phone"
                            wire:model.live.debounce.300ms="work_phone"
                            placeholder="+8801XXXXXXXXX"
                            @error('work_phone') aria-invalid="true" aria-describedby="work_phone-error" @enderror
                            class="w-full rounded-xl border-slate-300 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 text-sm px-3.5 py-2.5 @error('work_phone') border-rose-400 bg-rose-50/30 @enderror"
                        >
                        @error('work_phone') <span id="work_phone-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>


            <!-- ==========================================
                 SECTION 6: Identity Documents & Studio Photograph (Fields 28-29)
                 ========================================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 inline-flex items-center justify-center text-xs font-bold" aria-hidden="true">6</span>
                        Identity Documents & Studio Photograph
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">Official identity verification and member badge photograph upload.</p>
                </div>

                <!-- Guidance Info Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Identity Documents Guidance -->
                    <div class="bg-blue-50/70 border border-blue-200 rounded-xl p-4 text-xs text-blue-950 space-y-2">
                        <div class="font-bold flex items-center gap-1.5 text-blue-900">
                            <svg class="w-4 h-4 text-blue-700 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                            Accepted Identity Documents
                        </div>
                        <ul class="list-disc list-inside space-y-1 text-blue-800">
                            <li>National ID (NID) card (front and back)</li>
                            <li>Valid Bangladesh Passport</li>
                            <li>Official Birth Certificate</li>
                            <li>Current School / College / University Student ID</li>
                        </ul>
                        <p class="text-slate-600 pt-1">Upload 1 or 2 files (PDF, JPG, JPEG, DOC, DOCX, PNG; max 4MB per file).</p>
                    </div>

                    <!-- Studio Passport Photo Guidance -->
                    <div class="bg-indigo-50/70 border border-indigo-200 rounded-xl p-4 text-xs text-indigo-950 space-y-2">
                        <div class="font-bold flex items-center gap-1.5 text-indigo-900">
                            <svg class="w-4 h-4 text-indigo-700 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                            Studio Passport Photo Specifications
                        </div>
                        <ul class="list-disc list-inside space-y-1 text-indigo-800">
                            <li>Plain solid white background (no patterns or outdoor shots)</li>
                            <li>No glasses or sunglasses permitted (eyes must be clearly visible)</li>
                            <li>Both ears visible; hair tucked behind ears</li>
                            <li>Regular professional or casual clothes (no uniforms)</li>
                            <li>Strictly no selfies, angled shots, or beauty filters</li>
                            <li>Minimum 300x300 pixels, maximum 4MB (JPG, JPEG, PNG)</li>
                        </ul>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Field 28: Identity Documents Upload -->
                    <div>
                        <label for="identity_document_files" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Field 28: Upload Identity Documents <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="file"
                            id="identity_document_files"
                            name="identity_document_files"
                            wire:model="identity_document_files"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            aria-required="true"
                            @error('identity_document_files') aria-invalid="true" aria-describedby="identity_document_files-error" @enderror
                            @error('identity_document_files.*') aria-invalid="true" aria-describedby="identity_document_files-error" @enderror
                            class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer border border-slate-300 rounded-xl bg-white p-1 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        <div class="flex items-center justify-between text-xs text-slate-500 mt-1">
                            <span>1 or 2 files (NID, Passport, Birth Certificate, Student ID)</span>
                            <div wire:loading wire:target="identity_document_files" class="text-indigo-600 font-medium">Uploading...</div>
                        </div>
                        @error('identity_document_files') <span id="identity_document_files-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                        @error('identity_document_files.*') <span id="identity_document_files-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 29: Studio Passport Photo Upload -->
                    <div>
                        <label for="photo_file" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Field 29: Upload Studio Passport Photo <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="file"
                            id="photo_file"
                            name="photo_file"
                            wire:model="photo_file"
                            accept=".jpg,.jpeg,.png"
                            aria-required="true"
                            @error('photo_file') aria-invalid="true" aria-describedby="photo_file-error" @enderror
                            class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer border border-slate-300 rounded-xl bg-white p-1 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        <div class="flex items-center justify-between text-xs text-slate-500 mt-1">
                            <span>White background, no glasses, ears visible, min 300x300</span>
                            <div wire:loading wire:target="photo_file" class="text-indigo-600 font-medium">Uploading...</div>
                        </div>
                        @error('photo_file') <span id="photo_file-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 SECTION 7: Payment Verification & Terms Acceptance (Fields 30-31 per DEC-05)
                 ========================================== -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 inline-flex items-center justify-center text-xs font-bold" aria-hidden="true">7</span>
                        Payment Verification & Terms Acceptance
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">bKash Transaction ID verification and legal agreement lock.</p>
                </div>

                <!-- bKash Instructions Card -->
                <div role="region" aria-label="bKash payment instructions" aria-live="polite" class="bg-pink-50/70 border border-pink-200 rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-md bg-pink-600 text-white font-bold flex items-center justify-center text-xs" aria-hidden="true">৳</span>
                            <h3 class="text-xs font-bold text-pink-900 uppercase tracking-wider">bKash Merchant Payment</h3>
                        </div>
                        <p class="text-xs text-pink-800 leading-relaxed">
                            Please make payment of the non-refundable membership fee to bKash Merchant Account <strong class="font-mono text-pink-950">01977109012</strong>.
                            Enter the 10-character alphanumeric Transaction ID (TxID) below.
                        </p>
                    </div>
                    <div class="text-xs bg-white text-pink-900 font-mono px-3 py-1.5 rounded-lg border border-pink-200 shrink-0 font-bold">
                        Format: 10 Alphanumeric Chars
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-start">
                    <!-- Field 30: bKash Transaction ID -->
                    <div>
                        <label for="bkash_txid" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Field 30: bKash Transaction ID <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                type="text"
                                id="bkash_txid"
                                name="bkash_txid"
                                wire:model.live.debounce.300ms="bkash_txid"
                                x-on:input="$el.value = $el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10)"
                                placeholder="e.g. BLK7X9Q12M"
                                maxlength="10"
                                aria-required="true"
                                @error('bkash_txid') aria-invalid="true" aria-describedby="bkash_txid-error" @enderror
                                class="w-full rounded-xl border-slate-300 font-mono uppercase tracking-widest text-base shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 px-3.5 py-2.5 @error('bkash_txid') border-rose-400 bg-rose-50/30 @enderror"
                            >
                            <span class="absolute right-3 top-3 text-xs font-mono text-slate-500" aria-hidden="true">
                                {{ strlen($bkash_txid) }}/10
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Exactly 10 uppercase characters (0-9, A-Z). Auto-capitalized.</p>
                        @error('bkash_txid') <span id="bkash_txid-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field 31: Terms & Conditions Agreement -->
                    <div class="pt-2">
                        <label for="terms_agreed" class="flex items-start gap-3 cursor-pointer min-h-[44px]">
                            <input
                                type="checkbox"
                                id="terms_agreed"
                                name="terms_agreed"
                                wire:model="terms_agreed"
                                aria-required="true"
                                @error('terms_agreed') aria-invalid="true" aria-describedby="terms_agreed-error" @enderror
                                class="rounded border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500 h-5 w-5 mt-0.5"
                            >
                            <span class="text-xs text-slate-700 leading-relaxed">
                                <strong class="text-slate-900 block font-semibold">Field 31: Terms and Conditions Agreement *</strong>
                                I hereby confirm that all information provided in this application is accurate and complete. I agree to abide by the rules and regulations of the EMK Center and American Spaces Bangladesh.
                            </span>
                        </label>
                        @error('terms_agreed') <span id="terms_agreed-error" class="text-xs text-rose-600 block mt-1" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="border-t border-slate-100 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                        Your data is encrypted and transferred via 256-bit TLS connection.
                    </div>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        aria-label="Submit Membership Application"
                        class="group relative w-full sm:w-auto min-h-[44px] overflow-hidden inline-flex items-center justify-center gap-2.5 px-8 py-3.5 rounded-full bg-gradient-to-r from-amber-400 via-amber-300 to-amber-500 hover:from-amber-300 hover:to-amber-400 active:scale-[0.99] text-slate-950 font-black text-sm uppercase tracking-wider shadow-lg shadow-amber-500/25 hover:shadow-amber-500/40 transition-all duration-300 transform hover:-translate-y-0.5 active:translate-y-0 cursor-pointer disabled:cursor-wait disabled:hover:translate-y-0 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2"
                    >
                        <!-- Liquid Light Shimmer Sweep across the button -->
                        <div wire:loading wire:target="submit" class="absolute inset-0 bg-gradient-to-r from-transparent via-white/45 to-transparent -translate-x-full animate-shimmer pointer-events-none"></div>

                        <!-- Default Ready State -->
                        <span wire:loading.remove wire:target="submit" class="relative z-10 flex items-center justify-center gap-2.5 font-black tracking-wider">
                            <span>Submit Membership Application</span>
                            <svg class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </span>

                        <!-- Authenticating Loading State -->
                        <span wire:loading wire:target="submit" class="relative z-10 inline-flex items-center justify-center gap-3">
                            <svg class="animate-spin w-5 h-5 text-slate-950 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3.5"></circle>
                                <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="tracking-widest uppercase font-black text-xs sm:text-sm text-slate-950 select-none">Authenticating &amp; Submitting</span>
                            <span class="inline-flex items-center gap-1 ml-0.5" aria-hidden="true">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-950 animate-bounce" style="animation-duration: 0.8s; animation-delay: 0ms;"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-950 animate-bounce" style="animation-duration: 0.8s; animation-delay: 150ms;"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-950 animate-bounce" style="animation-duration: 0.8s; animation-delay: 300ms;"></span>
                            </span>
                        </span>
                    </button>
                </div>
            </div>

        </form>
    @endif

</div>
