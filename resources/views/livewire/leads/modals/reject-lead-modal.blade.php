<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-reject-title" role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                wire:click="closeModal"
            ></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-slate-900/95 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-rose-500/40 backdrop-blur-xl">
                    <!-- Modal Header -->
                    <div class="bg-rose-600 px-6 py-4 flex items-center justify-between text-white">
                        <div class="flex items-center gap-2.5">
                            <div class="p-1.5 bg-rose-700/50 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold leading-6" id="modal-reject-title">
                                    Reject Membership Application (Standardized Taxonomy)
                                </h3>
                                <p class="text-xs text-rose-100">
                                    Applicant: {{ $lead->full_name }} ({{ $lead->email }}) &bull; Ref: {{ $lead->uuid }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="text-rose-100 hover:text-white transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="bg-rose-50 border border-rose-200 rounded-xl p-3.5 text-xs text-rose-900">
                            <p class="font-bold">Managerial Action Warning (VERIFICATION-DICTIONARY.md Section 7)</p>
                            <p class="mt-0.5 text-rose-800">
                                Formally declining this application transitions the lead to <span class="font-semibold">Rejected</span> and dispatches an official explanation email. Legitimate applicants remain eligible to reapply with fresh documentation.
                            </p>
                        </div>

                        <!-- Standardized Rejection Reason Dropdown -->
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1.5">
                                Select Standardized Rejection Reason *
                            </label>
                            <select
                                wire:model.live="rejectionReasonCode"
                                class="w-full text-xs rounded-lg border-slate-300 shadow-xs focus:border-rose-500 focus:ring-rose-500"
                                id="select-rejection-reason"
                            >
                                <option value="">-- Choose Standardized Rejection Reason --</option>
                                
                                <optgroup label="Stream A: Payment Verification Reasons">
                                    @foreach($taxonomy as $code => $info)
                                        @if($info['stream'] === 'payment')
                                            <option value="{{ $code }}">{{ $code }} &bull; {{ $info['description'] }}</option>
                                        @endif
                                    @endforeach
                                </optgroup>

                                <optgroup label="Stream B: Document Compliance Reasons">
                                    @foreach($taxonomy as $code => $info)
                                        @if($info['stream'] === 'document')
                                            <option value="{{ $code }}">{{ $code }} &bull; {{ $info['description'] }}</option>
                                        @endif
                                    @endforeach
                                </optgroup>

                                <optgroup label="Stream C: Studio Photo Compliance Reasons">
                                    @foreach($taxonomy as $code => $info)
                                        @if($info['stream'] === 'photo')
                                            <option value="{{ $code }}">{{ $code }} &bull; {{ $info['description'] }}</option>
                                        @endif
                                    @endforeach
                                </optgroup>
                            </select>
                            @error('rejectionReasonCode')
                                <span class="text-xs text-rose-600 mt-1 block font-semibold">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Dynamic Applicant-Facing Text Preview -->
                        @if($selectedTaxonomy)
                            <div class="bg-slate-900/90 border border-rose-500/40 rounded-xl p-3.5 text-xs shadow-md">
                                <h4 class="font-bold text-rose-300 uppercase tracking-wider text-[11px] mb-1.5">
                                    Applicant-Facing Email Explanation Preview:
                                </h4>
                                <blockquote class="border-l-4 border-rose-500 pl-3 italic text-slate-200 bg-black/40 py-2 rounded-r">
                                    "{{ $selectedTaxonomy['applicant_message'] }}"
                                </blockquote>
                                <p class="text-[11px] text-slate-400 mt-2">
                                    Target Stream Affected: <span class="font-mono font-semibold uppercase text-amber-300">{{ $selectedTaxonomy['stream'] }}</span>
                                </p>
                            </div>
                        @endif

                        <!-- Internal Staff Notes (Confidential) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1.5">
                                Internal Staff / Manager Notes (Confidential - Not Sent to Applicant):
                            </label>
                            <textarea
                                wire:model="internalNotes"
                                rows="3"
                                placeholder="E.g., bKash merchant ledger checked; transaction ID not found in 72-hour batch statement."
                                class="w-full text-xs rounded-lg border-slate-700 bg-slate-950/80 text-white shadow-xs focus:border-rose-500 focus:ring-rose-500"
                            ></textarea>
                            @error('internalNotes')
                                <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Optional Manager Checkbox: Block Email per LEAD-07 -->
                        <div class="bg-slate-900/80 border border-slate-700/80 rounded-xl p-3.5">
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="blockEmail"
                                    class="rounded border-slate-600 text-rose-600 shadow-xs focus:ring-rose-500 mt-0.5"
                                    id="checkbox-block-email"
                                />
                                <div>
                                    <span class="text-xs font-bold text-white block">
                                        Block this applicant email from future submissions (LEAD-07)
                                    </span>
                                    <span class="text-[11px] text-slate-400 block mt-0.5">
                                        Flags this email (<strong>{{ $lead->email }}</strong>) as fraudulent or abusive. All future submissions using this email address will be rejected immediately at the public submission perimeter.
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-slate-900/90 px-6 py-4 border-t border-slate-700/80 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 rounded-lg text-xs font-semibold text-slate-300 hover:text-white hover:bg-white/10 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            wire:click="confirmRejection"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-bold shadow-md transition-colors"
                            id="btn-confirm-rejection"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span>Confirm Application Rejection</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
