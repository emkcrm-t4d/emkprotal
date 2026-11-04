<div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/75 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-slate-900">Official Correspondence</h3>
            <p class="text-xs text-slate-500 mt-0.5">Threaded official communications exchanged with the EMK Center Information Desk.</p>
        </div>
        <div class="w-full sm:w-64">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search correspondence..."
                class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
            >
        </div>
    </div>

    @if ($threads->isEmpty())
        <div class="p-12 text-center">
            <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h4 class="text-sm font-semibold text-slate-800">No official email correspondence found.</h4>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">All system notifications, tri-state verification updates, and help desk messages will appear here.</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[460px]">
            {{-- Thread list panel --}}
            <div class="lg:col-span-4 border-r border-slate-200 divide-y divide-slate-100 max-h-[560px] overflow-y-auto">
                @foreach ($threads as $thread)
                    @php
                        $isSelected = $selectedThread && $selectedThread->id === $thread->id;
                    @endphp
                    <button
                        type="button"
                        wire:click="selectThread({{ $thread->id }})"
                        class="w-full text-left px-4 py-3.5 transition hover:bg-slate-50 cursor-pointer block {{ $isSelected ? 'bg-indigo-50/70 border-l-4 border-indigo-600' : '' }}"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs sm:text-sm font-semibold text-slate-900 truncate">
                                {{ $thread->subject }}
                            </p>
                            <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700">
                                {{ $thread->messages->count() }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 truncate">
                            {{ $thread->participant_email }}
                        </p>
                        @if ($thread->last_message_at)
                            <p class="mt-1 text-[11px] text-slate-400">
                                {{ $thread->last_message_at->format('M d, Y h:i A') }}
                            </p>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Message viewer panel --}}
            <div class="lg:col-span-8 flex flex-col justify-between max-h-[560px]">
                @if ($selectedThread)
                    <div class="p-6 space-y-4 overflow-y-auto flex-1">
                        <div class="pb-3 border-b border-slate-200 flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">{{ $selectedThread->subject }}</h4>
                                <p class="text-xs text-slate-500 mt-0.5">Participant: {{ $selectedThread->participant_email }}</p>
                            </div>
                            @if ($selectedThread->thread_reference)
                                <span class="text-[11px] font-mono text-slate-400 bg-slate-100 px-2 py-0.5 rounded">
                                    Ref: {{ $selectedThread->thread_reference }}
                                </span>
                            @endif
                        </div>

                        <div class="space-y-4">
                            @foreach ($selectedThread->messages as $msg)
                                <div class="rounded-xl border p-4 {{ $msg->direction === 'inbound' ? 'bg-slate-50 border-slate-200' : 'bg-indigo-50/40 border-indigo-200' }}">
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2 pb-2 border-b border-slate-200/50">
                                        <div class="flex items-center gap-2">
                                            @if ($msg->direction === 'inbound')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800">
                                                    From: You
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-100 text-indigo-800">
                                                    From: EMK Information Desk
                                                </span>
                                            @endif

                                            <span class="text-xs font-medium text-slate-600">
                                                {{ $msg->from_email }} &rarr; {{ $msg->to_email }}
                                            </span>
                                        </div>

                                        <span class="text-[11px] text-slate-400">
                                            {{ ($msg->sent_at ?? $msg->received_at ?? $msg->created_at)?->format('M d, Y h:i A') }}
                                        </span>
                                    </div>

                                    @if ($msg->subject && $msg->subject !== $selectedThread->subject)
                                        <p class="text-xs font-semibold text-slate-800 mb-2">Subject: {{ $msg->subject }}</p>
                                    @endif

                                    <div class="text-xs sm:text-sm text-slate-800 leading-relaxed break-words prose prose-sm max-w-none">
                                        @if ($msg->body_html)
                                            {!! $this->sanitizeHtml($msg->body_html) !!}
                                        @else
                                            <div class="whitespace-pre-line">{{ $msg->body_text }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Read-only notice bar --}}
                    <div class="p-4 bg-slate-50 border-t border-slate-200 text-center text-xs text-slate-500">
                        <span>This correspondence record is read-only. To reply or submit questions, email </span>
                        <a href="mailto:membership@emk.com.bd" class="text-indigo-600 hover:underline font-medium">membership@emk.com.bd</a>
                        <span> or contact the Information Desk.</span>
                    </div>
                @else
                    <div class="p-12 text-center text-xs text-slate-400">
                        Select an email thread from the list on the left to read correspondence.
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
