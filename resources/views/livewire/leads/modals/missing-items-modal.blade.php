<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                wire:click="closeModal"
            ></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-slate-900/95 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-700/80 backdrop-blur-xl">
                    <!-- Modal Header -->
                    <div class="bg-amber-500 px-6 py-4 flex items-center justify-between text-white">
                        <div class="flex items-center gap-2.5">
                            <div class="p-1.5 bg-amber-600/40 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold leading-6" id="modal-title">
                                    Request Missing Items & Documentation
                                </h3>
                                <p class="text-xs text-amber-100">
                                    Recipient: {{ $lead->full_name }} ({{ $lead->email }}) &bull; Ref: {{ $lead->uuid }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="text-amber-100 hover:text-white transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="px-6 py-5 space-y-5 max-h-[75vh] overflow-y-auto">
                        @error('selectedItems')
                            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-3.5 py-2 rounded-lg text-xs font-semibold">
                                {{ $message }}
                            </div>
                        @enderror

                        <div>
                            <p class="text-xs text-slate-600 font-medium mb-3">
                                Select the items or compliance flaws that the applicant must rectify. This will transition the application status to <span class="font-bold text-amber-700">Missing Items</span> and queue an official email.
                            </p>

                            <!-- Categorized Checklist -->
                            <div class="space-y-4">
                                @foreach($checklistOptions as $category => $items)
                                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5">
                                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2.5 flex items-center justify-between">
                                            <span>{{ $category }}</span>
                                            <span class="text-[10px] text-slate-400 font-normal">Check all that apply</span>
                                        </h4>
                                        <div class="space-y-2">
                                            @foreach($items as $item)
                                                <label class="flex items-start gap-2.5 cursor-pointer text-xs text-slate-700 hover:text-slate-900">
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $item }}"
                                                        wire:model.live="selectedItems"
                                                        class="rounded border-slate-300 text-amber-600 shadow-xs focus:ring-amber-500 mt-0.5"
                                                    />
                                                    <span>{{ $item }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Custom Instructions -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                Additional Staff Review Notes / Instructions (Optional):
                            </label>
                            <textarea
                                wire:model="customInstructions"
                                rows="3"
                                placeholder="E.g., Please provide a clear scan of the back of your National ID showing your current residential address..."
                                class="w-full text-xs rounded-lg border-slate-300 shadow-xs focus:border-amber-500 focus:ring-amber-500"
                            ></textarea>
                            <p class="text-[11px] text-slate-400 mt-1">
                                These notes will be included verbatim in the official notification email sent to the applicant.
                            </p>
                        </div>

                        <!-- Live Selected Items Summary Preview -->
                        @if(count($selectedItems) > 0)
                            <div class="bg-amber-950/40 border border-amber-500/40 rounded-xl p-3.5 text-xs text-amber-200 shadow-md">
                                <p class="font-bold text-amber-300 mb-1">Email Preview ({{ count($selectedItems) }} item{{ count($selectedItems) > 1 ? 's' : '' }} selected):</p>
                                <ul class="list-disc list-inside space-y-0.5 text-amber-100 text-[11px]">
                                    @foreach($selectedItems as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
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
                            wire:click="dispatchMissingItemsNotification"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-xs font-bold shadow-md transition-colors"
                            id="btn-dispatch-missing-items"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span>Send Notification & Mark Missing Items</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
