<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <!-- Background Backdrop -->
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div
                    class="fixed inset-0 bg-slate-900/75 transition-opacity"
                    wire:click="closeModal"
                    aria-hidden="true"
                ></div>

                <!-- Centering element -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal Panel -->
                <div class="relative inline-block align-bottom bg-slate-900/95 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-rose-500/40 backdrop-blur-xl">
                    <!-- Modal Header -->
                    <div class="bg-rose-600 px-6 py-4 flex items-center justify-between text-white">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-rose-700/60 flex items-center justify-center text-white">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-white" id="modal-title">
                                    Reverse Member Conversion
                                </h3>
                                <p class="text-xs text-rose-100 font-medium">
                                    Statutory 30-Day Rollback Protocol (DEC-04)
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
                    <div class="px-6 py-5 space-y-4">
                        <!-- Warning Alert Box -->
                        <div class="rounded-xl bg-rose-950/50 border border-rose-500/50 p-3.5 shadow-md">
                            <div class="flex items-start gap-2.5">
                                <svg class="w-4 h-4 text-rose-400 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <div class="text-xs text-rose-100 leading-relaxed font-medium">
                                    <strong class="text-rose-200">WARNING:</strong> This action will revoke member portal access, void invoices, cancel ID card issuance, and restore the applicant to Under Review status. This action is permanently logged to immutable audit records.
                                </div>
                            </div>
                        </div>

                        @if($member)
                            <!-- Member Summary Card -->
                            <div class="bg-slate-900/80 border border-slate-700/80 rounded-xl p-3.5 text-xs space-y-2 shadow-inner">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Member:</span>
                                    <span class="font-semibold text-white">{{ $member->full_name }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400">Member ID:</span>
                                    <span class="font-mono font-bold text-purple-300 bg-purple-950/80 border border-purple-500/40 px-2 py-0.5 rounded-md">{{ $member->member_id }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Converted On:</span>
                                    <span class="text-slate-200">{{ $member->created_at->format('M d, Y H:i') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Statutory Window:</span>
                                    @php
                                        $daysSince = $member->created_at->diffInDays(now());
                                        $daysRemaining = max(0, 30 - $daysSince);
                                    @endphp
                                    <span class="font-medium {{ $daysSince <= 30 ? 'text-emerald-400' : 'text-rose-400' }}">
                                        Day {{ $daysSince }} of 30 ({{ $daysRemaining }} days remaining)
                                    </span>
                                </div>
                            </div>
                        @endif

                        @error('general')
                            <div class="p-2.5 rounded bg-rose-50 border border-rose-200 text-xs text-rose-700">
                                {{ $message }}
                            </div>
                        @enderror

                        <!-- Mandatory Justification Textarea -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label for="reversal-justification" class="block text-xs font-semibold text-slate-700">
                                    Mandatory Justification <span class="text-rose-600">*</span>
                                </label>
                                <span class="text-[11px] font-mono {{ mb_strlen(trim($justification)) >= 20 ? 'text-emerald-600 font-semibold' : 'text-slate-400' }}" id="char-counter">
                                    Characters: {{ mb_strlen(trim($justification)) }}/20 (min 20)
                                </span>
                            </div>
                            <textarea
                                id="reversal-justification"
                                wire:model.live="justification"
                                rows="3"
                                class="w-full text-xs rounded-lg border-slate-300 shadow-xs focus:border-rose-500 focus:ring-rose-500 @error('justification') border-rose-300 ring-rose-200 @enderror"
                                placeholder="Explain the operational reason for reversing this conversion (minimum 20 characters)..."
                            ></textarea>
                            @error('justification')
                                <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Administrative Password Re-authentication -->
                        <div>
                            <label for="admin-password" class="block text-xs font-semibold text-slate-700 mb-1">
                                Administrative Password Confirmation <span class="text-rose-600">*</span>
                            </label>
                            <input
                                type="password"
                                id="admin-password"
                                wire:model="password"
                                autocomplete="current-password"
                                class="w-full text-xs rounded-lg border-slate-300 shadow-xs focus:border-rose-500 focus:ring-rose-500 @error('password') border-rose-300 ring-rose-200 @enderror"
                                placeholder="Re-enter your manager account password..."
                            />
                            @error('password')
                                <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
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
                            wire:click="executeReversal"
                            wire:loading.attr="disabled"
                            id="btn-confirm-reversal"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 rounded-lg shadow-md transition-colors disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="executeReversal">
                                Confirm & Reverse Conversion
                            </span>
                            <span wire:loading wire:target="executeReversal" class="inline-flex items-center gap-1">
                                <svg class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Rolling back...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
