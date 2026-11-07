<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" id="card-replacement-modal">
            <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
            <div class="flex min-h-full items-center justify-center p-4 sm:p-0">
                <div class="relative overflow-hidden rounded-2xl bg-white text-left shadow-xl sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
                    <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                        <h3 class="text-base font-bold text-slate-900">Replace ID Card</h3>
                        <p class="text-xs text-slate-500 mt-0.5 font-mono">{{ $member->full_name }} • {{ $member->member_id }}</p>
                    </div>

                    <form wire:submit="submit" class="px-6 py-4 space-y-4">
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                            Replacing this card will immediately supersede the existing card and enqueue a new badge for PVC printing.
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700" for="replacementReason">Reason</label>
                            <select id="replacementReason" wire:model="replacementReason" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                <option value="">Select a reason…</option>
                                <option value="LOST_CARD">Lost Card</option>
                                <option value="DAMAGED_CARD">Damaged Card</option>
                                <option value="NAME_CHANGE">Information Update</option>
                                <option value="OTHER">Other</option>
                            </select>
                            @error('replacementReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox" wire:model.live="requiresFee" class="rounded border-slate-300" />
                            Charge replacement fee
                        </label>

                        @if ($requiresFee)
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1" for="bkashTxId">bKash Transaction ID (TrxID)</label>
                                <div class="flex rounded-lg shadow-xs border border-slate-300 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 overflow-hidden bg-white">
                                    <span class="inline-flex items-center px-3 bg-pink-50 border-r border-slate-200 text-pink-900 font-mono font-bold text-xs select-none">
                                        TrxID
                                    </span>
                                    <input 
                                        id="bkashTxId" 
                                        type="text" 
                                        wire:model="bkashTxId" 
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
                                                $wire.set('bkashTxId', v);
                                            }
                                        "
                                        maxlength="100" 
                                        class="block w-full border-0 text-sm font-mono uppercase focus:ring-0 px-3 py-2 bg-transparent" 
                                        placeholder="e.g. BLK7X9Q12M"
                                    />
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">TrxID is shown by default. TrxID prefix is automatically cleaned if pasted.</p>
                                @error('bkashTxId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" wire:click="closeModal" class="px-3.5 py-2 text-xs font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-3.5 py-2 text-xs font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Replace Card</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
