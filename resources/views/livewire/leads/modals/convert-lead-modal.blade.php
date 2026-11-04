<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                wire:click="closeModal"
            ></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-200">
                    <!-- Modal Header (Emerald Theme) -->
                    <div class="bg-emerald-600 px-6 py-4 flex items-center justify-between text-white">
                        <div class="flex items-center gap-2.5">
                            <div class="p-1.5 bg-emerald-700/50 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold leading-6" id="modal-title">
                                    Convert Lead to Active Member
                                </h3>
                                <p class="text-xs text-emerald-100">
                                    Applicant: {{ $lead->full_name }} &bull; Ref: {{ $lead->uuid }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="text-emerald-100 hover:text-white transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="px-6 py-5 space-y-5 max-h-[75vh] overflow-y-auto">
                        <!-- Conversion Invariant Error Banner -->
                        @error('conversion')
                            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-3.5 py-2.5 rounded-lg text-xs font-semibold flex items-center gap-2">
                                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ $message }}</span>
                            </div>
                        @enderror

                        <!-- Tri-State Verification Summary Badge Checklist -->
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2.5 flex items-center justify-between">
                                <span>Tri-State Invariant Gate Verification</span>
                                <span class="text-[10px] text-emerald-700 font-bold bg-emerald-100 px-2 py-0.5 rounded">All Checks Passed</span>
                            </h4>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                <!-- Payment Stream -->
                                <div class="bg-white p-2.5 rounded-lg border border-slate-200 flex flex-col">
                                    <span class="text-slate-500 text-[10px]">Payment Stream</span>
                                    <span class="font-bold text-emerald-700 flex items-center gap-1 mt-0.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        {{ ucfirst($lead->payment_verification_status) }}
                                    </span>
                                    <span class="text-[10px] font-mono text-pink-600 truncate mt-1">TxID: {{ $lead->bkash_txid }}</span>
                                </div>

                                <!-- Document Stream -->
                                <div class="bg-white p-2.5 rounded-lg border border-slate-200 flex flex-col">
                                    <span class="text-slate-500 text-[10px]">Document Stream</span>
                                    <span class="font-bold text-emerald-700 flex items-center gap-1 mt-0.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        {{ ucfirst($lead->document_verification_status) }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 mt-1">ID Verified</span>
                                </div>

                                <!-- Photo Stream -->
                                <div class="bg-white p-2.5 rounded-lg border border-slate-200 flex flex-col">
                                    <span class="text-slate-500 text-[10px]">Photo Stream</span>
                                    <span class="font-bold text-emerald-700 flex items-center gap-1 mt-0.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        {{ ucfirst($lead->photo_verification_status) }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 mt-1">Portrait Verified</span>
                                </div>

                                <!-- Lead Approval Status -->
                                <div class="bg-white p-2.5 rounded-lg border border-slate-200 flex flex-col">
                                    <span class="text-slate-500 text-[10px]">Manager Approval</span>
                                    <span class="font-bold text-emerald-700 flex items-center gap-1 mt-0.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        {{ ucfirst($lead->status) }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 mt-1">Approved</span>
                                </div>
                            </div>
                        </div>

                        <!-- Applicant Summary -->
                        <div class="bg-white border border-slate-200 rounded-xl p-4">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">Applicant Verification Summary</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-slate-500">Full Name:</span>
                                    <span class="font-semibold text-slate-900 ml-1">{{ $lead->full_name }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Email:</span>
                                    <span class="font-mono text-slate-900 ml-1">{{ $lead->email }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Contact Number:</span>
                                    <span class="font-mono text-slate-900 ml-1">{{ $lead->contact_number }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Target Space:</span>
                                    <span class="font-semibold text-slate-900 ml-1">{{ $lead->target_space_code }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Membership Term Selector Dropdown -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5" for="term-selector">
                                Select Membership Term *
                            </label>
                            <select
                                id="term-selector"
                                wire:model.live="selectedTermId"
                                class="w-full text-sm rounded-lg border-slate-300 shadow-xs focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                @foreach($terms as $term)
                                    <option value="{{ $term->id }}">
                                        {{ $term->name }} &bull; {{ $term->duration_months }} Months &bull; BDT {{ number_format($term->fee_bdt, 2) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('selectedTermId')
                                <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span>
                            @enderror

                            <!-- Selected Term Dynamic Detail Card -->
                            @if($selectedTerm)
                                <div class="mt-3 bg-emerald-50/70 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-900">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-bold text-emerald-950">{{ $selectedTerm->name }}</span>
                                            <span class="text-[11px] text-emerald-800 ml-2">({{ $selectedTerm->code }})</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-mono font-bold text-sm text-emerald-900">BDT {{ number_format($selectedTerm->fee_bdt, 2) }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-t border-emerald-200/60 flex items-center justify-between text-[11px] text-emerald-800">
                                        <span>Duration: <strong>{{ $selectedTerm->duration_months }} Months</strong></span>
                                        <span>Valid From: <strong>{{ now()->format('Y-m-d') }}</strong> to <strong>{{ now()->addMonths((int)$selectedTerm->duration_months)->format('Y-m-d') }}</strong></span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Warning Text -->
                        <div class="bg-amber-50 border border-amber-300 rounded-xl p-3 text-xs text-amber-900 flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="leading-relaxed">
                                <strong>Warning:</strong> This action will atomically provision Member ID, generate a paid invoice, queue physical badge printing, and provision portal credentials.
                            </p>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-slate-50 px-6 py-3.5 border-t border-slate-200 flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-200 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            wire:click="executeConversion"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition-colors disabled:opacity-50"
                            id="btn-confirm-conversion"
                        >
                            <svg wire:loading wire:target="executeConversion" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg wire:loading.remove wire:target="executeConversion" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Confirm & Convert to Member</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
