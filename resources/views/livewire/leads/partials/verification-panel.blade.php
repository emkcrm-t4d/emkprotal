<div class="space-y-6">
    <!-- Flash Messages -->
    @if(session()->has('status'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm font-medium flex items-center justify-between" id="verification-flash-success">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-sm font-medium flex items-center justify-between" id="verification-flash-error">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Manager Approval Header & Tri-State Status Overview -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-base font-bold text-slate-900">Tri-State Review Streams</h2>
                <span class="px-2 py-0.5 rounded text-xs font-bold uppercase {{ $lead->status === 'approved' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-700' }}">
                    Lead: {{ $lead->status }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Admissions staff evaluate Payment, Documents, and Photo independently. All 3 must be verified for Manager approval (DEC-03).
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Tri-State Stream Mini Indicators -->
            <div class="flex items-center gap-2 text-xs font-semibold px-3 py-2 bg-slate-50 rounded-lg border border-slate-200">
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full {{ $lead->payment_verification_status === 'verified' ? 'bg-emerald-500' : ($lead->payment_verification_status === 'rejected' ? 'bg-rose-500' : 'bg-amber-400') }}"></span>
                    <span>Pay: {{ strtoupper($lead->payment_verification_status) }}</span>
                </span>
                <span class="text-slate-300">|</span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full {{ $lead->document_verification_status === 'verified' ? 'bg-emerald-500' : ($lead->document_verification_status === 'missing_items' ? 'bg-amber-500' : 'bg-slate-300') }}"></span>
                    <span>Doc: {{ strtoupper($lead->document_verification_status) }}</span>
                </span>
                <span class="text-slate-300">|</span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full {{ $lead->photo_verification_status === 'verified' ? 'bg-emerald-500' : ($lead->photo_verification_status === 'reupload_requested' ? 'bg-amber-500' : 'bg-slate-300') }}"></span>
                    <span>Photo: {{ strtoupper($lead->photo_verification_status) }}</span>
                </span>
            </div>

            <!-- Approve Lead for Induction Button (Managerial) -->
            @if($canApprove)
                <button
                    type="button"
                    wire:click="approveLead"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-xs transition-colors flex items-center gap-1.5"
                    id="btn-approve-lead"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Approve Lead for Induction</span>
                </button>
            @else
                <button
                    type="button"
                    disabled
                    class="px-4 py-2 bg-slate-100 text-slate-400 border border-slate-200 text-xs font-bold rounded-lg cursor-not-allowed flex items-center gap-1.5"
                    title="{{ $lead->status === 'approved' ? 'Lead is already approved' : 'Requires Payment, Documents, and Photo verified and Workspace Manager authority' }}"
                    id="btn-approve-lead-disabled"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m4-11a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span>{{ $lead->status === 'approved' ? 'Lead Approved' : 'Approve Gated (Streams Incomplete)' }}</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Tabbed Navigation for the 3 Streams -->
    <div class="border-b border-slate-200">
        <nav class="flex space-x-6 text-sm font-medium" aria-label="Streams">
            <button
                type="button"
                wire:click="setActiveTab('payment')"
                class="pb-3 border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'payment' ? 'border-pink-600 text-pink-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
                id="tab-btn-payment"
            >
                <span>1. Payment (bKash)</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold {{ $lead->payment_verification_status === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                    {{ $lead->payment_verification_status }}
                </span>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('document')"
                class="pb-3 border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'document' ? 'border-indigo-600 text-indigo-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
                id="tab-btn-document"
            >
                <span>2. Documents & Consent</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold {{ $lead->document_verification_status === 'verified' ? 'bg-emerald-100 text-emerald-800' : ($lead->document_verification_status === 'missing_items' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                    {{ $lead->document_verification_status }}
                </span>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('photo')"
                class="pb-3 border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'photo' ? 'border-purple-600 text-purple-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
                id="tab-btn-photo"
            >
                <span>3. Studio Photo</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold {{ $lead->photo_verification_status === 'verified' ? 'bg-emerald-100 text-emerald-800' : ($lead->photo_verification_status === 'reupload_requested' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                    {{ $lead->photo_verification_status }}
                </span>
            </button>
        </nav>
    </div>

    <!-- STREAM A: PAYMENT (bKash Reconciliation) -->
    @if($activeTab === 'payment')
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-6 space-y-6" id="panel-stream-payment">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Stream A: bKash Transaction Reconciliation (DEC-05)</h3>
                    <p class="text-xs text-slate-500">
                        Workspace Manager confirms fee receipt against official bKash Merchant statement. No second reviewer needed.
                    </p>
                </div>
                <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-full {{ $lead->payment_verification_status === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-pink-100 text-pink-800' }}">
                    Status: {{ strtoupper($lead->payment_verification_status) }}
                </span>
            </div>

            <!-- Duplicate TxID Alert Banner -->
            @if($duplicateDetected)
                <div class="p-4 bg-rose-50 border border-rose-300 rounded-xl text-xs text-rose-900 space-y-1" id="alert-duplicate-txid">
                    <p class="font-bold flex items-center gap-1.5 text-sm text-rose-800">
                        <svg class="w-4 h-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        DUPLICATE TRANSACTION ID DETECTED
                    </p>
                    <p>
                        bKash Transaction ID <strong class="font-mono text-rose-950">{{ $bkashTxid }}</strong> has already been used on another record:
                        @if($duplicateDetails)
                            <span class="font-semibold">{{ $duplicateDetails['source'] }} ID #{{ $duplicateDetails['id'] }}</span>
                            @if(!empty($duplicateDetails['applicant_name']))
                                ({{ $duplicateDetails['applicant_name'] }})
                            @endif
                        @endif
                    </p>
                    <p class="text-rose-700">
                        Per DEC-05 and LEAD-03, duplicate transactions must be rejected to prevent fraudulent application reuse.
                    </p>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- bKash TxID Input -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        bKash Transaction ID (Field 30)
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="bkashTxid"
                            class="w-full px-3 py-2 font-mono font-bold uppercase rounded-lg border border-slate-300 focus:ring-2 focus:ring-pink-500 text-sm tracking-wider"
                            maxlength="10"
                            id="input-bkash-txid"
                        />
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">Exact 10 uppercase alphanumeric characters (e.g. BLK7X9Q12M).</p>
                    @error('bkashTxid') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                <!-- Fee Amount Received -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Registration Fee Amount (BDT)
                    </label>
                    <input
                        type="number"
                        wire:model="amountReceived"
                        step="50"
                        class="w-full px-3 py-2 font-medium rounded-lg border border-slate-300 focus:ring-2 focus:ring-pink-500 text-sm"
                        id="input-payment-amount"
                    />
                    <p class="text-[11px] text-slate-500 mt-1">Expected term fee based on admission catalog.</p>
                    @error('amountReceived') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                <!-- Rejection Reason Selector -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Rejection Reason Code (If Declining)
                    </label>
                    <select
                        wire:model="paymentRejectionReason"
                        class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-pink-500 text-xs"
                        id="select-payment-rejection-reason"
                    >
                        <option value="INVALID_TXID">INVALID_TXID - TxID not found in bKash statement</option>
                        <option value="DUPLICATE_TXID">DUPLICATE_TXID - TxID already claimed</option>
                        <option value="AMOUNT_MISMATCH">AMOUNT_MISMATCH - Deposit amount does not match fee</option>
                        <option value="TRANSACTION_NOT_FOUND">TRANSACTION_NOT_FOUND - Record not located</option>
                    </select>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Merchant Statement Reconciliation Notes
                </label>
                <textarea
                    wire:model="paymentNotes"
                    rows="2"
                    placeholder="e.g. Verified via bKash Merchant statement dated 2026-11-04. Sender mobile +88017... "
                    class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-pink-500 text-xs"
                    id="input-payment-notes"
                ></textarea>
                @error('paymentNotes') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                <div>
                    @if(!$canVerifyPayment)
                        <p class="text-xs text-amber-700 font-medium">
                            🔒 Only Workspace Managers (leads.verify_payment) can verify or reject payments.
                        </p>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        wire:click="submitPaymentVerification('rejected')"
                        @if(!$canVerifyPayment) disabled @endif
                        class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 text-xs font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        id="btn-reject-payment"
                    >
                        Reject Payment
                    </button>

                    <button
                        type="button"
                        wire:click="submitPaymentVerification('verified')"
                        @if(!$canVerifyPayment) disabled @endif
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-xs transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                        id="btn-verify-payment"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Confirm & Verify Payment (DEC-05)</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- STREAM B: DOCUMENTS & MINOR CONSENT -->
    @if($activeTab === 'document')
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-6 space-y-6" id="panel-stream-document">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Stream B: Identity Document & Minor Consent (DEC-09)</h3>
                    <p class="text-xs text-slate-500">
                        Admissions staff evaluate National ID / Passport scan and assert parental consent for minors under 18 years.
                    </p>
                </div>
                <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-full {{ $lead->document_verification_status === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800' }}">
                    Status: {{ strtoupper($lead->document_verification_status) }}
                </span>
            </div>

            <!-- Youth Protection Minor Banner (DEC-09) -->
            @if($lead->age_at_submission < 18)
                <div class="p-4 bg-amber-50 border border-amber-300 rounded-xl text-xs text-amber-900 space-y-2" id="box-minor-compliance">
                    <div class="flex items-center justify-between">
                        <span class="font-bold flex items-center gap-1.5 text-sm text-amber-800">
                            ⚠️ YOUTH OUTREACH COMPLIANCE REQUIRED (DEC-09)
                        </span>
                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-200 text-amber-900">
                            Applicant Age: {{ $lead->age_at_submission }} yrs (< 18)
                        </span>
                    </div>
                    <p>
                        In compliance with U.S. Embassy and EMK Center youth safety guidelines, applicants under 18 years of age <strong>must submit a signed Parental/Guardian Consent Form</strong>. Document verification cannot be approved without verified guardian consent.
                    </p>

                    @if($consentAttachment)
                        <div class="pt-2 flex items-center justify-between">
                            <span class="text-xs text-slate-700">Uploaded Consent File: <strong>{{ $consentAttachment->original_filename }}</strong></span>
                            <a
                                href="{{ route('documents.download', ['workspace' => $workspace, 'attachment' => $consentAttachment->id]) }}"
                                target="_blank"
                                class="inline-flex items-center gap-1 text-xs font-bold text-indigo-700 hover:underline"
                                id="link-download-consent"
                            >
                                <span>Inspect Parental Consent Scan</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </div>
                    @else
                        <p class="text-rose-700 font-semibold pt-1">
                            No Parental Consent scan was attached to this application.
                        </p>
                    @endif

                    <div class="pt-2 border-t border-amber-200 flex items-center gap-2">
                        <input
                            type="checkbox"
                            wire:model="under18ConsentVerified"
                            id="check-minor-consent-verified"
                            class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300"
                        />
                        <label for="check-minor-consent-verified" class="font-bold text-amber-950 cursor-pointer">
                            I have personally inspected and verified the signed Parental/Guardian Consent Form (DEC-09)
                        </label>
                    </div>
                </div>
            @endif

            <!-- Document Inspection Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left: Identity Scan Inspection -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Uploaded Identity Credential (Field 28)</h4>
                    @if($identityAttachment)
                        <div class="space-y-2">
                            <p class="text-xs text-slate-700">Filename: <strong class="text-slate-900">{{ $identityAttachment->original_filename }}</strong></p>
                            <p class="text-xs text-slate-500">MIME: {{ $identityAttachment->mime_type }} &bull; Size: {{ number_format($identityAttachment->size_bytes / 1024, 1) }} KB</p>

                            <div class="pt-2">
                                <a
                                    href="{{ route('documents.download', ['workspace' => $workspace, 'attachment' => $identityAttachment->id]) }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors"
                                    id="link-inspect-id-doc"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Open Document Scan in New Tab</span>
                                </a>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-slate-500">No identity document attached to this lead.</p>
                    @endif
                </div>

                <!-- Right: Identity Checkpoints -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Verification Criteria</h4>

                    <div class="space-y-2 text-xs">
                        <label class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="nameMatches"
                                id="check-name-matches"
                                class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300"
                            />
                            <span class="text-slate-800 font-medium">
                                Applicant Name matches ID credential (<strong>{{ $lead->full_name }}</strong>)
                            </span>
                        </label>

                        <label class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="dobMatches"
                                id="check-dob-matches"
                                class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300"
                            />
                            <span class="text-slate-800 font-medium">
                                Date of Birth matches ID credential (<strong>{{ $lead->date_of_birth ? $lead->date_of_birth->format('Y-m-d') : 'N/A' }}</strong>)
                            </span>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Document Type</label>
                            <select wire:model="idDocumentType" class="w-full text-xs rounded-lg border border-slate-300 px-2 py-1.5" id="select-doc-type">
                                <option value="national_id">National ID (NID)</option>
                                <option value="passport">Passport</option>
                                <option value="birth_certificate">Birth Registration Certificate</option>
                                <option value="student_id">Institution ID</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">ID Number</label>
                            <input type="text" wire:model="idDocumentNumber" placeholder="NID / Passport #" class="w-full text-xs rounded-lg border border-slate-300 px-2 py-1.5" id="input-doc-number" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes & Rejection Reasons -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Document Reviewer Notes</label>
                    <textarea wire:model="docNotes" rows="2" placeholder="e.g. NID front & back clearly visible. Valid until 2030." class="w-full text-xs rounded-lg border border-slate-300 p-2" id="input-doc-notes"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Standardized Rejection Reason (If Applicable)</label>
                    <select wire:model="docRejectionReason" class="w-full text-xs rounded-lg border border-slate-300 px-2 py-1.5" id="select-doc-rejection-reason">
                        <option value="DOCUMENT_UNREADABLE">DOCUMENT_UNREADABLE - File blurry, cropped, or illegible</option>
                        <option value="MISSING_GUARDIAN_CONSENT">MISSING_GUARDIAN_CONSENT - Minor applicant missing signed consent</option>
                        <option value="NAME_MISMATCH">NAME_MISMATCH - Name differs from application</option>
                        <option value="EXPIRED_ID">EXPIRED_ID - Credential has expired</option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <button
                    type="button"
                    wire:click="submitDocumentVerification('missing_items')"
                    class="px-4 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-xs font-semibold rounded-lg transition-colors"
                    id="btn-doc-missing-items"
                >
                    Mark Missing Items / Request Reupload
                </button>

                <button
                    type="button"
                    wire:click="submitDocumentVerification('rejected')"
                    class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 text-xs font-semibold rounded-lg transition-colors"
                    id="btn-doc-reject"
                >
                    Reject Documents
                </button>

                <button
                    type="button"
                    wire:click="submitDocumentVerification('verified')"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-xs transition-colors flex items-center gap-1.5"
                    id="btn-doc-verify"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Verify Identity Documents</span>
                </button>
            </div>
        </div>
    @endif

    <!-- STREAM C: PHOTO REVIEW (5-Point Studio Checklist) -->
    @if($activeTab === 'photo')
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-6 space-y-6" id="panel-stream-photo">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Stream C: Studio Passport Photo 5-Point Checklist</h3>
                    <p class="text-xs text-slate-500">
                        Staff verify photo suitability for physical PVC plastic membership card badge printing.
                    </p>
                </div>
                <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-full {{ $lead->photo_verification_status === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800' }}">
                    Status: {{ strtoupper($lead->photo_verification_status) }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Photo Preview Column -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex flex-col items-center justify-center text-center">
                    @if($photoAttachment)
                        <div class="w-44 h-44 rounded-lg overflow-hidden border-2 border-slate-300 bg-white shadow-xs mb-3 flex items-center justify-center">
                            <img
                                src="{{ route('documents.download', ['workspace' => $workspace, 'attachment' => $photoAttachment->id]) }}"
                                alt="Applicant Studio Photo"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <p class="text-xs font-semibold text-slate-900 truncate max-w-full">{{ $photoAttachment->original_filename }}</p>
                        <a
                            href="{{ route('documents.download', ['workspace' => $workspace, 'attachment' => $photoAttachment->id]) }}"
                            target="_blank"
                            class="text-[11px] text-indigo-600 hover:underline mt-1 font-medium"
                            id="link-photo-full"
                        >
                            Open Full Resolution
                        </a>
                    @else
                        <div class="w-36 h-36 rounded-lg bg-slate-200 flex items-center justify-center text-slate-400 text-xs">
                            No Photo Attached
                        </div>
                    @endif
                </div>

                <!-- 5-Point Studio Checklist Column (Spans 2 columns) -->
                <div class="md:col-span-2 space-y-3">
                    <div class="pb-2">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Mandatory 5-Point Studio Compliance Checklist</h4>
                        <p class="text-[11px] text-slate-500">All 5 criteria must be satisfied to approve photo for badge printing.</p>
                    </div>

                    <div class="space-y-2 text-xs">
                        <!-- Checkpoint 1 -->
                        <label class="flex items-center gap-3 p-2.5 rounded-lg border {{ $checkWhiteBackground ? 'bg-emerald-50 border-emerald-300' : 'bg-slate-50 border-slate-200' }} cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:model.live="checkWhiteBackground"
                                id="check-white-background"
                                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
                            />
                            <div>
                                <p class="font-bold text-slate-800">1. Solid White Background</p>
                                <p class="text-[11px] text-slate-500">Plain solid white background. No patterns, colors, outdoor scenes, shadows, or textured walls.</p>
                            </div>
                        </label>

                        <!-- Checkpoint 2 -->
                        <label class="flex items-center gap-3 p-2.5 rounded-lg border {{ $checkNoGlasses ? 'bg-emerald-50 border-emerald-300' : 'bg-slate-50 border-slate-200' }} cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:model.live="checkNoGlasses"
                                id="check-no-glasses"
                                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
                            />
                            <div>
                                <p class="font-bold text-slate-800">2. No Eyeglasses or Sunglasses</p>
                                <p class="text-[11px] text-slate-500">Spectacles, sunglasses, tinted lenses, or glare obstructing eyes are strictly prohibited.</p>
                            </div>
                        </label>

                        <!-- Checkpoint 3 -->
                        <label class="flex items-center gap-3 p-2.5 rounded-lg border {{ $checkEarsVisible ? 'bg-emerald-50 border-emerald-300' : 'bg-slate-50 border-slate-200' }} cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:model.live="checkEarsVisible"
                                id="check-ears-visible"
                                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
                            />
                            <div>
                                <p class="font-bold text-slate-800">3. Both Ears Clearly Visible</p>
                                <p class="text-[11px] text-slate-500">Hair tucked behind ears. Religious headwear permitted if entire facial oval is clear.</p>
                            </div>
                        </label>

                        <!-- Checkpoint 4 -->
                        <label class="flex items-center gap-3 p-2.5 rounded-lg border {{ $checkRegularAttire ? 'bg-emerald-50 border-emerald-300' : 'bg-slate-50 border-slate-200' }} cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:model.live="checkRegularAttire"
                                id="check-regular-attire"
                                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
                            />
                            <div>
                                <p class="font-bold text-slate-800">4. Standard Everyday / Professional Attire</p>
                                <p class="text-[11px] text-slate-500">Formal, semi-formal, or standard everyday clothes. No military uniforms or costumes.</p>
                            </div>
                        </label>

                        <!-- Checkpoint 5 -->
                        <label class="flex items-center gap-3 p-2.5 rounded-lg border {{ $checkNoSelfie ? 'bg-emerald-50 border-emerald-300' : 'bg-slate-50 border-slate-200' }} cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                wire:model.live="checkNoSelfie"
                                id="check-no-selfie"
                                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
                            />
                            <div>
                                <p class="font-bold text-slate-800">5. Formal Studio Portrait (No Selfie / Filters)</p>
                                <p class="text-[11px] text-slate-500">Direct frontal angle shot from appropriate distance. No tilted handheld selfies or social filters.</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Notes & Rejection Reasons -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Photo Evaluation Notes</label>
                    <textarea wire:model="photoNotes" rows="2" placeholder="e.g. Studio lighting verified, compliant for plastic card thermal printer." class="w-full text-xs rounded-lg border border-slate-300 p-2" id="input-photo-notes"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Re-upload / Rejection Reason Code</label>
                    <select wire:model="photoRejectionReason" class="w-full text-xs rounded-lg border border-slate-300 px-2 py-1.5" id="select-photo-rejection-reason">
                        <option value="NON_COMPLIANT_BACKGROUND">NON_COMPLIANT_BACKGROUND - Coloured or textured background</option>
                        <option value="GLASSES_OBSCURING_FACE">GLASSES_OBSCURING_FACE - Eyeglasses/sunglasses present</option>
                        <option value="SELFIE_DETECTED">SELFIE_DETECTED - Handheld selfie or tilted angle</option>
                        <option value="EARS_COVERED">EARS_COVERED - Ears obscured</option>
                        <option value="LOW_RESOLUTION">LOW_RESOLUTION - Pixelated or low resolution</option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                <div>
                    @if(!$allPhotoCriteriaMet)
                        <p class="text-xs text-amber-700 font-medium">
                            ⚠️ All 5 checklist items must be checked before photo can be verified.
                        </p>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        wire:click="submitPhotoVerification('reupload_requested')"
                        class="px-4 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-xs font-semibold rounded-lg transition-colors"
                        id="btn-photo-reupload"
                    >
                        Request Studio Photo Re-upload
                    </button>

                    <button
                        type="button"
                        wire:click="submitPhotoVerification('rejected')"
                        class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 text-xs font-semibold rounded-lg transition-colors"
                        id="btn-photo-reject"
                    >
                        Reject Photo
                    </button>

                    <button
                        type="button"
                        wire:click="submitPhotoVerification('verified')"
                        @if(!$allPhotoCriteriaMet) disabled @endif
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-lg shadow-xs transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                        id="btn-photo-verify"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Verify Studio Photo (All 5 Met)</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
