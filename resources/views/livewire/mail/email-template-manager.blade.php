<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Top Bar: Glass Blur View per User Specification -->
    <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl p-6 sm:p-7 shadow-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">Bilingual Lifecycle Email Templates</h1>
            <span class="sr-only">{{ $workspace }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="inline-flex items-center rounded-full bg-indigo-500/20 px-3.5 py-1.5 text-xs font-semibold text-indigo-300 border border-indigo-400/30 shadow-xs">
                9 Canonical Milestones
            </span>
            <span class="inline-flex items-center rounded-full bg-emerald-500/20 px-3.5 py-1.5 text-xs font-semibold text-emerald-300 border border-emerald-400/30 shadow-xs">
                Zero-Eval Safe Renderer (DEC-02)
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-2xl bg-emerald-500/20 p-4 border border-emerald-400/30 text-sm text-emerald-300 font-medium backdrop-blur-md shadow-lg flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($editingTemplateId)
        <div class="bg-slate-900/85 backdrop-blur-2xl rounded-3xl shadow-2xl border border-amber-400/30 p-6 sm:p-8 space-y-6 text-white">
            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                <div>
                    <h2 class="text-lg font-bold text-white tracking-tight">Edit Bilingual Email Template</h2>
                    <p class="text-xs text-slate-300 mt-0.5">
                        Only allowlisted placeholders are interpolated. All variable values are automatically HTML-escaped.
                    </p>
                </div>
                <button type="button" wire:click="cancelEdit" class="text-xs font-semibold text-slate-400 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer">
                    Cancel
                </button>
            </div>

            <div class="bg-slate-950/60 rounded-2xl p-4 border border-white/10">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-400 block mb-2">Allowlisted Placeholders for this Milestone:</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($allowed_variables as $placeholder)
                        <span class="inline-flex items-center rounded-lg bg-amber-400/10 px-2.5 py-1 text-xs font-mono font-medium text-amber-300 border border-amber-400/30 shadow-xs">
                            &#123;&#123;{{ $placeholder }}&#125;&#125;
                        </span>
                    @endforeach
                </div>
            </div>

            <form wire:submit="saveTemplate" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 items-center">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Template Display Name</label>
                        <input type="text" wire:model="name" class="mt-1.5 block w-full rounded-xl border-white/15 bg-white/5 text-white placeholder-slate-400 shadow-inner focus:border-amber-400 focus:ring-amber-400 sm:text-sm px-3.5 py-2.5" />
                        @error('name') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="w-4 h-4 rounded border-white/20 bg-white/10 text-amber-500 focus:ring-amber-400" />
                            <span class="text-sm font-medium text-slate-200">Template Active for Automated & Manual Dispatch</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4 bg-slate-950/50 p-5 rounded-2xl border border-white/10">
                        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider">English Copy (Primary)</h3>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">English Subject Line</label>
                            <input type="text" wire:model="subject_en" class="mt-1.5 block w-full rounded-xl border-white/15 bg-white/5 text-white placeholder-slate-400 shadow-inner focus:border-amber-400 focus:ring-amber-400 sm:text-sm px-3.5 py-2" />
                            @error('subject_en') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">English HTML Body</label>
                            <textarea rows="7" wire:model="body_en" class="mt-1.5 block w-full font-mono text-xs rounded-xl border-white/15 bg-white/5 text-slate-100 shadow-inner focus:border-amber-400 focus:ring-amber-400 p-3"></textarea>
                            @error('body_en') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="space-y-4 bg-slate-950/50 p-5 rounded-2xl border border-white/10">
                        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider">বাংলা কপি / Bengali Copy</h3>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Bengali Subject Line (বাংলা বিষয়)</label>
                            <input type="text" wire:model="subject_bn" class="mt-1.5 block w-full rounded-xl border-white/15 bg-white/5 text-white placeholder-slate-400 shadow-inner focus:border-amber-400 focus:ring-amber-400 sm:text-sm px-3.5 py-2" />
                            @error('subject_bn') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Bengali HTML Body (বাংলা মূল বার্তা)</label>
                            <textarea rows="7" wire:model="body_bn" class="mt-1.5 block w-full font-mono text-xs rounded-xl border-white/15 bg-white/5 text-slate-100 shadow-inner focus:border-amber-400 focus:ring-amber-400 p-3"></textarea>
                            @error('body_bn') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                    <button type="button" wire:click="cancelEdit" class="rounded-xl bg-white/10 px-4 py-2.5 text-xs font-semibold text-slate-300 hover:text-white hover:bg-white/15 border border-white/10 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 px-5 py-2.5 text-xs font-bold text-slate-950 shadow-lg shadow-amber-500/25 transition cursor-pointer">
                        Save Template & Log Audit Event
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Main Table Container: 3D Frosted Glass Block Automatically Adjusted to Background Color -->
    <div class="bg-slate-900/70 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-white/10 text-left text-xs">
                <thead class="bg-slate-950/80 text-slate-300 font-bold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th scope="col" class="px-5 py-4 text-left font-bold uppercase tracking-wider text-slate-300">Milestone Template</th>
                        <th scope="col" class="px-5 py-4 text-left font-bold uppercase tracking-wider text-slate-300">Key / Milestone Type</th>
                        <th scope="col" class="px-5 py-4 text-left font-bold uppercase tracking-wider text-slate-300">Allowed Variables</th>
                        <th scope="col" class="px-5 py-4 text-left font-bold uppercase tracking-wider text-slate-300">Status</th>
                        <th scope="col" class="px-5 py-4 text-right font-bold uppercase tracking-wider text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 bg-transparent text-slate-200">
                    @foreach ($templates as $template)
                        <tr wire:key="tpl-row-{{ $template->id }}" class="hover:bg-white/5 transition-colors">
                            <td class="px-5 py-4">
                                <div class="text-sm font-bold text-white">{{ $template->name }}</div>
                                <div class="text-xs text-slate-300 mt-0.5 truncate max-w-md">EN: {{ $template->subject_en }}</div>
                                @if ($template->subject_bn)
                                    <div class="text-xs text-amber-300/80 truncate max-w-md mt-0.5">BN: {{ $template->subject_bn }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-lg bg-white/10 border border-white/10 px-2.5 py-1 text-xs font-mono font-medium text-slate-200">
                                    {{ $template->template_key }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1.5 max-w-xs">
                                    @foreach (($template->allowed_variables ?? []) as $varName)
                                        <span class="inline-flex items-center rounded-md bg-amber-400/10 border border-amber-400/25 px-2 py-0.5 text-[11px] font-mono text-amber-300">
                                            &#123;&#123;{{ $varName }}&#125;&#125;
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                @if ($template->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-300 border border-emerald-500/30 shadow-xs">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-slate-400 border border-white/10">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-right text-xs font-semibold space-x-2">
                                <button type="button" wire:click="openPreview({{ $template->id }}, 'en')" class="inline-flex items-center rounded-xl bg-white/10 hover:bg-white/20 px-3.5 py-2 text-slate-200 hover:text-white border border-white/10 transition cursor-pointer">
                                    Preview (EN/BN)
                                </button>
                                <button type="button" wire:click="editTemplate({{ $template->id }})" class="inline-flex items-center rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-400/30 px-3.5 py-2 transition cursor-pointer shadow-xs">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($previewTemplateId && !empty($previewOutput))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md p-4">
            <div class="bg-slate-900/95 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white/15 max-w-3xl w-full overflow-hidden text-white">
                <div class="flex items-center justify-between px-6 py-4 border-b border-white/10 bg-slate-950/60">
                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight">Live Sample Variable Preview</h3>
                        <p class="text-xs text-slate-300 mt-0.5">Interpolated using safe allowlisted placeholders for EMK Center Dhaka</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="openPreview({{ $previewTemplateId }}, 'en')" class="px-3 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer {{ $previewLocale === 'en' ? 'bg-amber-400 text-slate-950 shadow-md font-bold' : 'bg-white/10 text-slate-300 hover:text-white' }}">
                            English
                        </button>
                        <button type="button" wire:click="openPreview({{ $previewTemplateId }}, 'bn')" class="px-3 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer {{ $previewLocale === 'bn' ? 'bg-amber-400 text-slate-950 shadow-md font-bold' : 'bg-white/10 text-slate-300 hover:text-white' }}">
                            বাংলা (Bengali)
                        </button>
                        <button type="button" wire:click="openPreview({{ $previewTemplateId }}, 'bilingual')" class="px-3 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer {{ $previewLocale === 'bilingual' ? 'bg-amber-400 text-slate-950 shadow-md font-bold' : 'bg-white/10 text-slate-300 hover:text-white' }}">
                            Bilingual
                        </button>
                        <button type="button" wire:click="closePreview" class="ml-2 text-slate-400 hover:text-white text-base font-bold p-1">
                            ✕
                        </button>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="rounded-2xl bg-slate-950/60 p-4 border border-white/10">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-400 block">Rendered Subject:</span>
                        <div class="text-sm font-semibold text-white mt-1">{{ $previewOutput['subject'] ?? '' }}</div>
                    </div>
                    <div class="rounded-2xl bg-white/5 p-5 border border-white/10 prose prose-invert prose-sm max-w-none text-slate-200">
                        {!! $previewOutput['body_html'] ?? '' !!}
                    </div>
                </div>
                <div class="px-6 py-4 bg-slate-950/60 border-t border-white/10 flex justify-end">
                    <button type="button" wire:click="closePreview" class="rounded-xl bg-white/10 hover:bg-white/20 px-5 py-2 text-xs font-semibold text-white border border-white/10 transition cursor-pointer">
                        Close Preview
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
