<div>
    @if ($isOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="email-change-modal-title"
            role="dialog"
            aria-modal="true"
        >
            {{-- Backdrop --}}
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                wire:click="closeModal"
            ></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div
                    class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-xl border border-slate-200"
                    @click.stop
                >
                    {{-- Header --}}
                    <div class="px-6 py-5 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-400/30 flex items-center justify-center text-amber-300">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white tracking-tight" id="email-change-modal-title">
                                    Request Email Address Change
                                </h3>
                                <p class="text-xs text-slate-300">
                                    Member ID: <span class="font-mono font-semibold text-indigo-300">{{ $member->member_id }}</span>
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="closeModal"
                            class="rounded-lg p-1.5 text-slate-400 hover:text-white hover:bg-white/10 transition"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Form Body --}}
                    <div class="p-6 space-y-5 max-h-[calc(85vh-160px)] overflow-y-auto">
                        {{-- Security Policy Banner --}}
                        <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1">
                            <p class="font-bold flex items-center gap-1.5 text-amber-950">
                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Security Verification & Administrative Gate (PORT-04)
                            </p>
                            <p class="text-amber-800">
                                Your email address is your primary login identifier and destination for official membership correspondence.
                                Any modification requires security review and authorization by the Workspace Manager. Once approved, your login credentials and profile will be updated atomically.
                            </p>
                        </div>

                        {{-- Pending Duplication Guard Warning --}}
                        @if ($hasPendingRequest)
                            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-start gap-3">
                                <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p class="font-bold text-rose-950">Email Change Request Already Pending</p>
                                    <p class="mt-0.5">
                                        An email change request is currently pending administrative review by your Workspace Manager. Additional submissions are restricted until the active request is resolved.
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- Submission Error Banner --}}
                        @if ($submissionError)
                            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-start gap-3">
                                <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p class="font-bold text-rose-950">Submission Error</p>
                                    <p class="mt-0.5">{{ $submissionError }}</p>
                                </div>
                            </div>
                        @endif

                        <form wire:submit.prevent="submit" class="space-y-4">
                            {{-- Current Email --}}
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Current Registered Email
                                </label>
                                <input
                                    type="email"
                                    value="{{ $member->email }}"
                                    disabled
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-100 text-slate-600 font-mono"
                                >
                                <p class="text-[11px] text-slate-400 mt-1">This email remains active until your request is approved by a manager.</p>
                            </div>

                            {{-- Proposed New Email --}}
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Proposed New Email Address <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="email"
                                    wire:model.live.debounce.300ms="new_email"
                                    @disabled($hasPendingRequest)
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                    placeholder="new.email@example.com"
                                >
                                @error('new_email') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            {{-- Confirm New Email --}}
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Confirm New Email Address <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="email"
                                    wire:model="new_email_confirmation"
                                    @disabled($hasPendingRequest)
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                    placeholder="Re-enter proposed new email"
                                >
                                @error('new_email_confirmation') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            {{-- Justification --}}
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Reason for Email Change <span class="text-rose-500">*</span>
                                </label>
                                <textarea
                                    wire:model="justification"
                                    rows="3"
                                    @disabled($hasPendingRequest)
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                    placeholder="Please provide a clear justification for changing your email address (minimum 10 characters)..."
                                ></textarea>
                                <p class="text-[11px] text-slate-400 mt-1">Minimum 10 characters required for security audit trail.</p>
                                @error('justification') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </form>
                    </div>

                    {{-- Actions --}}
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 text-xs font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            wire:click="submit"
                            wire:loading.attr="disabled"
                            @disabled($hasPendingRequest)
                            class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="submit">Submit Email Change Request</span>
                            <span wire:loading wire:target="submit">Submitting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
