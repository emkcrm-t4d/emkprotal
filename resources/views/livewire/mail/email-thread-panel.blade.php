<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    {{-- Header Bar --}}
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <h3 class="text-base font-semibold text-slate-900">
                Official Information Desk Correspondence
            </h3>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                {{ $this->threads->count() }} {{ \Illuminate\Support\Str::plural('Thread', $this->threads->count()) }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                id="btn-sync-mailbox"
                wire:click="syncNow"
                class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
            >
                Sync Mailbox
            </button>

            <button
                type="button"
                id="btn-new-email-thread"
                wire:click="toggleNewThreadForm"
                class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700"
            >
                New Official Email
            </button>
        </div>
    </div>

    @if (session()->has('status'))
        <div class="px-6 py-3 bg-emerald-50 border-b border-emerald-200 text-xs font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Optional New Thread Form --}}
    @if ($showNewThreadForm)
        <div class="p-6 bg-indigo-50/40 border-b border-slate-200">
            <form wire:submit="startNewThread" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-800">
                        Start New Official Email Thread ({{ $counterpartyEmail }})
                    </h4>
                </div>

                <div>
                    <label for="new-thread-subject" class="block text-xs font-medium text-slate-700 mb-1">Subject</label>
                    <input
                        type="text"
                        id="new-thread-subject"
                        wire:model="newThreadSubject"
                        placeholder="Official subject line..."
                        class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    @error('newThreadSubject')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="new-thread-body" class="block text-xs font-medium text-slate-700 mb-1">Message Body</label>
                    <textarea
                        id="new-thread-body"
                        rows="4"
                        wire:model="newThreadBody"
                        placeholder="Write official correspondence..."
                        class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
                    @error('newThreadBody')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="toggleNewThreadForm"
                        class="px-3 py-1.5 text-xs font-medium text-slate-600 hover:text-slate-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        id="btn-submit-new-thread"
                        class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        Send Official Email
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[420px]">
        {{-- Thread List Sidebar --}}
        <div class="lg:col-span-4 border-r border-slate-200 divide-y divide-slate-100">
            @forelse ($this->threads as $thread)
                @php
                    $isSelected = $this->selectedThread && $this->selectedThread->id === $thread->id;
                @endphp
                <button
                    type="button"
                    wire:click="selectThread({{ $thread->id }})"
                    class="w-full text-left px-4 py-3.5 transition hover:bg-slate-50 {{ $isSelected ? 'bg-indigo-50/60 border-l-4 border-indigo-600' : '' }}"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-900 truncate">
                            {{ $thread->subject }}
                        </p>
                        <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                            {{ $thread->message_count }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 truncate">
                        {{ $thread->participant_email }}
                    </p>
                    @if ($thread->last_message_at)
                        <p class="mt-1 text-[11px] text-slate-400">
                            {{ $thread->last_message_at->format('d M Y, H:i') }}
                        </p>
                    @endif
                </button>
            @empty
                <div class="p-8 text-center text-sm text-slate-500">
                    No official email correspondence recorded yet.
                </div>
            @endforelse
        </div>

        {{-- Thread Messages & Reply Composer --}}
        <div class="lg:col-span-8 flex flex-col justify-between">
            @if ($this->selectedThread)
                <div class="p-6 space-y-4 overflow-y-auto max-h-[500px]">
                    <div class="pb-3 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">{{ $this->selectedThread->subject }}</h4>
                            <p class="text-xs text-slate-500">Participant: {{ $this->selectedThread->participant_email }}</p>
                        </div>
                        @if ($this->selectedThread->thread_reference)
                            <span class="text-[11px] font-mono text-slate-400">{{ $this->selectedThread->thread_reference }}</span>
                        @endif
                    </div>

                    @foreach ($this->selectedThread->messages as $msg)
                        <div class="rounded-xl border p-4 {{ $msg->direction === 'inbound' ? 'bg-slate-50 border-slate-200' : 'bg-indigo-50/40 border-indigo-200' }}">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    @if ($msg->direction === 'inbound')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800">
                                            Inbound
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-100 text-indigo-800">
                                            Outbound
                                        </span>
                                    @endif

                                    <span class="text-xs font-medium text-slate-700">
                                        {{ $msg->from_email }} &rarr; {{ $msg->to_email }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-800">
                                        {{ $msg->status }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">
                                        {{ ($msg->sent_at ?? $msg->received_at ?? $msg->created_at)?->format('d M Y H:i') }}
                                    </span>
                                </div>
                            </div>

                            <div class="text-sm text-slate-800 whitespace-pre-line">
                                {{ $msg->body_text ?: strip_tags((string) $msg->body_html) }}
                            </div>

                            @if ($msg->message_id_header)
                                <div class="mt-2 pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                                    <span>Msg-ID: {{ $msg->message_id_header }}</span>
                                    @if ($msg->in_reply_to_header)
                                        <span>In-Reply-To: {{ $msg->in_reply_to_header }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Inline Reply Composer --}}
                <div class="p-6 bg-slate-50 border-t border-slate-200">
                    <form wire:submit="sendReply" class="space-y-3">
                        <div>
                            <label for="reply-body" class="block text-xs font-semibold text-slate-700 mb-1">
                                Official Information Desk Reply
                            </label>
                            <textarea
                                id="reply-body"
                                rows="3"
                                wire:model="replyBody"
                                placeholder="Type your official reply to {{ $this->selectedThread->participant_email }}..."
                                class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            @error('replyBody')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end">
                            <button
                                type="submit"
                                id="btn-send-thread-reply"
                                class="inline-flex items-center px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700"
                            >
                                Send Official Reply
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="p-12 text-center text-sm text-slate-500">
                    Select a thread or start a new official email conversation.
                </div>
            @endif
        </div>
    </div>
</div>
