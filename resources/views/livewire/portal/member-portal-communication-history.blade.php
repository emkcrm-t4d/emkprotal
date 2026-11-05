<div class="bg-slate-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.35)] overflow-hidden">
    <div class="px-6 py-4 border-b border-white/10 bg-slate-950/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-white tracking-tight">Official Correspondence</h3>
            <p class="text-xs text-slate-400 mt-0.5">Threaded official communications exchanged with the EMK Center Information Desk.</p>
        </div>
        <div class="w-full sm:w-64">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search correspondence..."
                class="w-full px-3.5 py-2 text-xs rounded-xl border border-white/15 bg-white/5 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition"
            >
        </div>
    </div>

    @if ($threads->isEmpty())
        <div class="p-12 text-center">
            <div class="w-12 h-12 rounded-full bg-white/5 border border-white/10 text-slate-400 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h4 class="text-sm font-semibold text-slate-200">No official email correspondence found.</h4>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">All system notifications, tri-state verification updates, and help desk messages will appear here.</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[460px]">
            {{-- Thread list panel --}}
            <div class="lg:col-span-4 border-r border-white/10 divide-y divide-white/5 max-h-[560px] overflow-y-auto">
                @foreach ($threads as $thread)
                    @php
                        $isSelected = $selectedThread && $selectedThread->id === $thread->id;
                    @endphp
                    <button
                        type="button"
                        wire:click="selectThread({{ $thread->id }})"
                        class="w-full text-left px-4 py-3.5 transition hover:bg-white/5 cursor-pointer block {{ $isSelected ? 'bg-amber-500/10 border-l-4 border-amber-400' : '' }}"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs sm:text-sm font-semibold text-white truncate">
                                {{ $thread->subject }}
                            </p>
                            <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-white/10 text-amber-300">
                                {{ $thread->messages->count() }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400 truncate">
                            {{ $thread->participant_email }}
                        </p>
                        @if ($thread->last_message_at)
                            <p class="mt-1 text-[11px] text-slate-500">
                                {{ $thread->last_message_at->format('M d, Y h:i A') }}
                            </p>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Message viewer panel --}}
            <div class="lg:col-span-8 flex flex-col justify-between max-h-[560px] bg-slate-950/30">
                @if ($selectedThread)
                    <div class="p-6 space-y-4 overflow-y-auto flex-1">
                        <div class="pb-3 border-b border-white/10 flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-bold text-white">{{ $selectedThread->subject }}</h4>
                                <p class="text-xs text-slate-400 mt-0.5">Participant: {{ $selectedThread->participant_email }}</p>
                            </div>
                            @if ($selectedThread->thread_reference)
                                <span class="text-[11px] font-mono text-amber-300 bg-amber-500/10 border border-amber-400/20 px-2 py-0.5 rounded">
                                    Ref: {{ $selectedThread->thread_reference }}
                                </span>
                            @endif
                        </div>

                        <div class="space-y-4">
                            @foreach ($selectedThread->messages as $msg)
                                <div class="rounded-2xl border p-4 {{ $msg->direction === 'inbound' ? 'bg-white/5 border-white/10' : 'bg-indigo-950/30 border-indigo-500/30' }}">
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2 pb-2 border-b border-white/10">
                                        <div class="flex items-center gap-2">
                                            @if ($msg->direction === 'inbound')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                                    From: You
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                                    From: EMK Information Desk
                                                </span>
                                            @endif

                                            <span class="text-xs font-medium text-slate-300">
                                                {{ $msg->from_email }} &rarr; {{ $msg->to_email }}
                                            </span>
                                        </div>

                                        <span class="text-[11px] text-slate-400">
                                            {{ ($msg->sent_at ?? $msg->received_at ?? $msg->created_at)?->format('M d, Y h:i A') }}
                                        </span>
                                    </div>

                                    @if ($msg->subject && $msg->subject !== $selectedThread->subject)
                                        <p class="text-xs font-semibold text-slate-200 mb-2">Subject: {{ $msg->subject }}</p>
                                    @endif

                                    <div class="text-xs sm:text-sm text-slate-200 leading-relaxed break-words prose prose-invert prose-sm max-w-none">
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
                    <div class="p-4 bg-slate-950/70 border-t border-white/10 text-center text-xs text-slate-400">
                        <span>This correspondence record is read-only. To reply or submit questions, email </span>
                        <a href="mailto:membership@emk.com.bd" class="text-amber-400 hover:underline font-medium">membership@emk.com.bd</a>
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
