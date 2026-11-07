<div>
    @if ($isOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="renewal-modal-title"
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
                            <div class="w-10 h-10 rounded-xl bg-indigo-600/30 border border-indigo-400/30 flex items-center justify-center text-indigo-300">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white tracking-tight" id="renewal-modal-title">
                                    Membership Renewal
                                </h3>
                                <p class="text-xs text-slate-300">
                                    Member: <span class="font-semibold text-white">{{ $member->full_name }}</span>
                                    <span class="mx-1">•</span>
                                    ID: <span class="font-mono text-indigo-300">{{ $member->member_id }}</span>
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

                    {{-- Body --}}
                    <div class="p-6 space-y-5 max-h-[calc(85vh-140px)] overflow-y-auto">
                        {{-- Validity Context Card --}}
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs flex items-center justify-between">
                            <div>
                                <span class="text-slate-400 uppercase text-[10px] font-semibold block">Current Validity</span>
                                <span class="font-bold text-slate-800 mt-0.5 block">
                                    {{ $member->expiry_date?->format('M d, Y') ?? '—' }}
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400 uppercase text-[10px] font-semibold block">Membership Status</span>
                                <span class="font-semibold mt-0.5 inline-flex items-center px-2 py-0.5 rounded-full text-[11px]
                                    {{ $member->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                    {{ ucfirst($member->status) }}
                                </span>
                            </div>
                        </div>

                        {{-- Window Closed Alert --}}
                        @if (! $isRenewalOpen)
                            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs space-y-1">
                                <div class="flex items-center gap-2 font-bold text-amber-950">
                                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Renewal Window Is Not Open Yet</span>
                                </div>
                                <p class="text-amber-800">
                                    Per membership policy, renewals open automatically 30 calendar days prior to your expiration date
                                    (starting <strong>{{ $member->expiry_date?->copy()->subDays(30)->format('M d, Y') ?? '—' }}</strong>).
                                </p>
                            </div>
                        @endif

                        {{-- Pending Request Alert --}}
                        @if ($hasPendingRequest)
                            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs space-y-1">
                                <div class="flex items-center gap-2 font-bold text-amber-950">
                                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Renewal Request Pending Review</span>
                                </div>
                                <p class="text-amber-800">
                                    A renewal request has already been submitted and is currently undergoing payment verification by workspace staff.
                                </p>
                            </div>
                        @endif

                        {{-- Error Alert --}}
                        @if ($submissionError)
                            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-start gap-2">
                                <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>{{ $submissionError }}</span>
                            </div>
                        @endif

                        {{-- Form --}}
                        <form wire:submit.prevent="submit" class="space-y-4">
                            {{-- Step 1: Membership Term Selection --}}
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                                    1. Select Renewal Term
                                </label>
                                <div class="space-y-2">
                                    @foreach ($terms as $term)
                                        <label
                                            class="flex items-center justify-between p-3.5 rounded-xl border transition cursor-pointer {{ (int) $selected_term_id === (int) $term->id ? 'border-indigo-600 bg-indigo-50/40 ring-1 ring-indigo-600' : 'border-slate-200 bg-white hover:bg-slate-50' }}"
                                        >
                                            <div class="flex items-center gap-3">
                                                <input
                                                    type="radio"
                                                    name="renewal_term"
                                                    value="{{ $term->id }}"
                                                    wire:model.live="selected_term_id"
                                                    class="text-indigo-600 focus:ring-indigo-500"
                                                    @disabled(! $isRenewalOpen || $hasPendingRequest)
                                                >
                                                <div>
                                                    <span class="text-xs font-bold text-slate-900 block">{{ $term->name }}</span>
                                                    <span class="text-[11px] text-slate-500 block">Validity: {{ $term->duration_months }} Months</span>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-sm font-bold text-indigo-700 block font-mono">
                                                    ৳{{ number_format((float) $term->fee_bdt, 2) }}
                                                </span>
                                                <span class="text-[10px] uppercase font-semibold text-slate-400 block">BDT</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                @error('selected_term_id') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            {{-- Step 2: Payment Instructions --}}
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2 font-bold text-slate-900">
                                        <div class="w-7 h-7 rounded-full bg-white p-0.5 ring-2 ring-[#E2136E] shadow-xs flex items-center justify-center shrink-0 overflow-hidden" aria-hidden="true">
                                            <img src="{{ asset('images/bkash-logo.png') }}" alt="bKash" class="w-full h-full object-cover rounded-full">
                                        </div>
                                        <span>2. bKash Merchant Payment Instructions</span>
                                    </div>
                                    <div class="shrink-0 flex flex-col items-center bg-white p-1.5 rounded-lg border border-slate-200 shadow-2xs">
                                        <img src="{{ asset('images/bkash-qr.png') }}" alt="bKash QR" class="w-14 h-14 object-contain">
                                        <span class="text-[9px] font-bold text-pink-700 tracking-tight uppercase">Scan QR</span>
                                    </div>
                                </div>
                                <ol class="list-decimal list-inside space-y-1 text-slate-600 text-[11px] leading-relaxed">
                                    <li>Open your bKash app and select <strong>Make Payment</strong> (or scan the QR code).</li>
                                    <li>Enter Merchant bKash Number: <strong class="font-mono text-slate-900">017 7774 2140</strong>.</li>
                                    <li>Enter Amount: <strong class="font-mono text-slate-900">৳{{ number_format((float) ($selectedTerm?->fee_bdt ?? 0), 2) }}</strong>.</li>
                                    <li>Use Reference: <strong class="font-mono text-indigo-600">{{ $member->member_id }}</strong>.</li>
                                    <li>Complete payment and copy the <strong>10-character Transaction ID (TxID)</strong>.</li>
                                </ol>
                            </div>

                            {{-- Step 3: Transaction ID Input (TrxID) --}}
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1 flex items-center justify-between">
                                    <span>3. bKash Transaction ID (TrxID) <span class="text-rose-500">*</span></span>
                                    <span class="text-[11px] font-normal text-slate-400 lowercase">auto-extracts from SMS</span>
                                </label>
                                <div class="flex rounded-xl shadow-xs border border-slate-300 focus-within:border-pink-500 focus-within:ring-2 focus-within:ring-pink-500/20 overflow-hidden bg-white transition-all">
                                    <span class="inline-flex items-center px-3 bg-pink-50 border-r border-slate-200 text-pink-900 font-mono font-bold text-xs tracking-wider select-none shrink-0" title="bKash Transaction ID Prefix">
                                        TrxID
                                    </span>
                                    <div class="relative flex-1">
                                        <input
                                            type="text"
                                            wire:model.live.debounce.300ms="bkash_txid"
                                            x-on:input="
                                                let v = $el.value;
                                                let m = v.match(/(?:trx\s*id|tx\s*id|transaction\s*id|txn\s*id)[\s:\-#=]*([A-Za-z0-9]{10})/i);
                                                if (m && m[1]) {
                                                    v = m[1].toUpperCase();
                                                } else {
                                                    v = v.replace(/^(?:trx\s*id|tx\s*id|transaction\s*id|txn\s*id)[\s:\-#=]*/i, '');
                                                    v = v.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);
                                                }
                                                $el.value = v;
                                            "
                                            x-on:paste="
                                                let text = ($event.clipboardData || window.clipboardData)?.getData('text') || '';
                                                if (text) {
                                                    $event.preventDefault();
                                                    let m = text.match(/(?:trx\s*id|tx\s*id|transaction\s*id|txn\s*id)[\s:\-#=]*([A-Za-z0-9]{10})/i);
                                                    let v = m && m[1] ? m[1].toUpperCase() : text.replace(/^(?:trx\s*id|tx\s*id|transaction\s*id|txn\s*id)[\s:\-#=]*/i, '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);
                                                    $el.value = v;
                                                    $wire.set('bkash_txid', v);
                                                }
                                            "
                                            maxlength="100"
                                            class="w-full px-3.5 py-2.5 text-xs font-mono font-bold uppercase tracking-wider border-0 focus:ring-0 bg-transparent text-slate-900"
                                            placeholder="e.g. BL9X4K2P9Q"
                                            @disabled(! $isRenewalOpen || $hasPendingRequest)
                                        >
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs text-slate-400 font-mono">
                                            {{ strlen($bkash_txid) }}/10
                                        </div>
                                    </div>
                                </div>
                                <span class="text-[11px] text-slate-500 mt-1 block">
                                    TrxID is shown by default. If you paste full SMS or type with "TrxID", the ID is automatically extracted.
                                </span>
                                @error('bkash_txid') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </form>
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between gap-3">
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
                            @disabled(! $isRenewalOpen || $hasPendingRequest)
                            class="px-5 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 disabled:bg-slate-300 disabled:cursor-not-allowed rounded-xl shadow-xs transition cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="submit">Submit Renewal Request</span>
                            <span wire:loading wire:target="submit">Submitting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
