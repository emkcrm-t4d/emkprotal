<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Horizon Email Delivery Monitor</h1>
            <p class="mt-1 text-sm text-slate-600">
                Inspect queued, sent, and failed official lifecycle email deliveries in workspace <span class="font-mono font-semibold text-indigo-600">{{ $workspace }}</span> and trigger safe idempotent retries.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                Deduplication Guard Active
            </span>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                3-Attempt Backoff (MAIL-04)
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 font-medium">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Queued for Horizon</div>
            <div class="mt-2 text-2xl font-bold text-amber-600">{{ $queuedCount }}</div>
            <div class="mt-1 text-xs text-slate-500">Awaiting worker dispatch</div>
        </div>
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sent Successfully</div>
            <div class="mt-2 text-2xl font-bold text-emerald-600">{{ $sentCount }}</div>
            <div class="mt-1 text-xs text-slate-500">Delivered via official mailbox</div>
        </div>
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-2xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Failed Deliveries</div>
            <div class="mt-2 text-2xl font-bold text-rose-600">{{ $failedCount }}</div>
            <div class="mt-1 text-xs text-slate-500">Sanitized diagnostics recorded</div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-2xs flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Search Recipient, Subject, or Template</label>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by email, subject, or template key..."
                class="block w-full rounded-lg border-slate-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            />
        </div>
        <div class="w-full sm:w-56">
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Filter by Status</label>
            <select
                wire:model.live="statusFilter"
                class="block w-full rounded-lg border-slate-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            >
                <option value="">All Statuses</option>
                <option value="queued">Queued</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
                <option value="received">Received</option>
            </select>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-2xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Recipient & Subject</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Milestone / Template</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Diagnostics / Timestamp</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($messages as $msg)
                        <tr wire:key="email-msg-{{ $msg->id }}" class="hover:bg-slate-50/75">
                            <td class="px-4 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ $msg->to_email }}</div>
                                <div class="text-xs text-slate-600 mt-0.5 max-w-md truncate">{{ $msg->subject }}</div>
                                @if ($msg->deduplication_key)
                                    <div class="text-[11px] font-mono text-slate-400 mt-0.5 truncate max-w-md">
                                        Dedup: {{ $msg->deduplication_key }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if ($msg->template_key)
                                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-mono font-medium text-indigo-700">
                                        {{ $msg->template_key }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-mono text-slate-600">
                                        {{ $msg->direction }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if ($msg->status === 'sent')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                        Sent
                                    </span>
                                @elseif ($msg->status === 'queued')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                        Queued
                                    </span>
                                @elseif ($msg->status === 'failed')
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                        Failed
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-500/20">
                                        {{ ucfirst($msg->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if ($msg->status === 'failed' && $msg->failure_reason)
                                    <div class="text-xs text-rose-600 font-mono max-w-sm break-words">
                                        {{ $msg->failure_reason }}
                                    </div>
                                @elseif ($msg->sent_at)
                                    <div class="text-xs text-slate-500">
                                        Sent {{ $msg->sent_at->format('d M Y, H:i') }}
                                    </div>
                                @else
                                    <div class="text-xs text-slate-500">
                                        Created {{ $msg->created_at?->format('d M Y, H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if ($msg->status === 'failed')
                                    <button
                                        type="button"
                                        wire:click="retryMessage({{ $msg->id }})"
                                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-indigo-500"
                                    >
                                        Retry Delivery
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                                No email delivery records match the current filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($messages->hasPages())
            <div class="px-4 py-3 border-t border-slate-200 bg-slate-50">
                {{ $messages->links() }}
            </div>
        @endif
    </div>
</div>
