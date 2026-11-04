<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Automated Lifecycle Workflow Rules</h1>
            <p class="text-sm text-slate-600 mt-1">
                Configure bounded, zero-eval email triggers for status transitions and membership milestones in <span class="font-semibold">{{ $workspace }}</span>.
            </p>
        </div>
    </div>

    @if (session()->has('status'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($editingRuleId)
        <div class="bg-white rounded-xl shadow-sm border border-indigo-200 p-6 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Configure Workflow Rule</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Bind a bilingual email template and adjust milestone offset parameters.</p>
                </div>
                <button type="button" wire:click="cancelEdit" class="text-sm text-slate-500 hover:text-slate-700">
                    Cancel
                </button>
            </div>

            <form wire:submit.prevent="saveRule" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Bound Email Template</label>
                        <select wire:model="email_template_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="0">-- Select Template --</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->template_key }})</option>
                            @endforeach
                        </select>
                        @error('email_template_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Days Before Expiry / Offset (0–365)</label>
                        <input type="number" min="0" max="365" wire:model="days_offset" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <p class="text-[11px] text-slate-500 mt-1">Used for renewal window advance notice (default: 30 days).</p>
                        @error('days_offset') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="is_enabled" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            <span>Rule Enabled</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" wire:click="cancelEdit" class="px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700">
                        Save Rule Configuration
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Workflow Rule</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Trigger Event</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Bound Template</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Timing / Offset</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($rules as $rule)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $rule->name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $rule->rule_key }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-mono text-slate-700">
                                    {{ $rule->trigger_event }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                @if ($rule->template)
                                    <div class="font-medium text-slate-800">{{ $rule->template->name }}</div>
                                    <div class="text-xs text-slate-500 font-mono">{{ $rule->template->template_key }}</div>
                                @else
                                    <span class="text-xs text-amber-600">Unbound</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($rule->trigger_event === \App\Domain\Mail\Models\WorkflowRule::TRIGGER_RENEWAL_WINDOW_OPEN)
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                                        {{ $rule->days_offset }} days before expiry
                                    </span>
                                @elseif ($rule->days_offset > 0)
                                    <span class="text-xs text-slate-600">{{ $rule->days_offset }} days offset</span>
                                @else
                                    <span class="text-xs text-slate-500">Immediate on transition</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($rule->is_enabled)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                        Enabled
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        Disabled
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button
                                    type="button"
                                    wire:click="toggleRule({{ $rule->id }})"
                                    class="inline-flex items-center rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    {{ $rule->is_enabled ? 'Disable' : 'Enable' }}
                                </button>
                                <button
                                    type="button"
                                    wire:click="editRule({{ $rule->id }})"
                                    class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                                >
                                    Configure
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
