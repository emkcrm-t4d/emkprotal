<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Bilingual Lifecycle Email Templates</h1>
            <p class="mt-1 text-sm text-slate-600">
                Manage standardized English and Bengali notification copy for all 9 applicant and member lifecycle milestones in workspace <span class="font-mono font-semibold text-indigo-600">{{ $workspace }}</span>.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                9 Canonical Milestones
            </span>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                Zero-Eval Safe Renderer (DEC-02)
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 font-medium">
            {{ session('status') }}
        </div>
    @endif

    @if ($editingTemplateId)
        <div class="bg-white rounded-xl shadow-sm border border-indigo-200 p-6 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Edit Bilingual Email Template</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Only allowlisted placeholders are interpolated. All variable values are automatically HTML-escaped.
                    </p>
                </div>
                <button type="button" wire:click="cancelEdit" class="text-sm text-slate-500 hover:text-slate-700 font-medium">
                    Cancel
                </button>
            </div>

            <div class="bg-slate-50 rounded-lg p-3 border border-slate-200">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">Allowlisted Placeholders for this Milestone:</span>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($allowed_variables as $placeholder)
                        <span class="inline-flex items-center rounded bg-white px-2.5 py-1 text-xs font-mono font-medium text-indigo-700 border border-indigo-200 shadow-2xs">
                            &#123;&#123;{{ $placeholder }}&#125;&#125;
                        </span>
                    @endforeach
                </div>
            </div>

            <form wire:submit="saveTemplate" class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Template Display Name</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-lg border-slate-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('name') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm font-medium text-slate-700">Template Active for Automated & Manual Dispatch</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4 bg-slate-50/60 p-4 rounded-lg border border-slate-200">
                        <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider">English Copy (Primary)</h3>
                        <div>
                            <label class="block text-xs font-medium text-slate-700">English Subject Line</label>
                            <input type="text" wire:model="subject_en" class="mt-1 block w-full rounded-lg border-slate-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('subject_en') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700">English HTML Body</label>
                            <textarea rows="7" wire:model="body_en" class="mt-1 block w-full font-mono text-xs rounded-lg border-slate-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            @error('body_en') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="space-y-4 bg-slate-50/60 p-4 rounded-lg border border-slate-200">
                        <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider">বাংলা কপি / Bengali Copy</h3>
                        <div>
                            <label class="block text-xs font-medium text-slate-700">Bengali Subject Line (বাংলা বিষয়)</label>
                            <input type="text" wire:model="subject_bn" class="mt-1 block w-full rounded-lg border-slate-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('subject_bn') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700">Bengali HTML Body (বাংলা মূল বার্তা)</label>
                            <textarea rows="7" wire:model="body_bn" class="mt-1 block w-full font-mono text-xs rounded-lg border-slate-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            @error('body_bn') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" wire:click="cancelEdit" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 border border-slate-300 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-indigo-500">
                        Save Template & Log Audit Event
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-2xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Milestone Template</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Key / Milestone Type</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Allowed Variables</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach ($templates as $template)
                        <tr wire:key="tpl-row-{{ $template->id }}" class="hover:bg-slate-50/75">
                            <td class="px-4 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ $template->name }}</div>
                                <div class="text-xs text-slate-500 mt-0.5 truncate max-w-md">EN: {{ $template->subject_en }}</div>
                                @if ($template->subject_bn)
                                    <div class="text-xs text-slate-500 truncate max-w-md">BN: {{ $template->subject_bn }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-mono font-medium text-slate-700">
                                    {{ $template->template_key }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1 max-w-xs">
                                    @foreach (($template->allowed_variables ?? []) as $varName)
                                        <span class="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] font-mono text-indigo-700">
                                            &#123;&#123;{{ $varName }}&#125;&#125;
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if ($template->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <button type="button" wire:click="openPreview({{ $template->id }}, 'en')" class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                                    Preview (EN/BN)
                                </button>
                                <button type="button" wire:click="editTemplate({{ $template->id }})" class="inline-flex items-center rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-3xl w-full overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Live Sample Variable Preview</h3>
                        <p class="text-xs text-slate-500">Interpolated using safe allowlisted placeholders for EMK Center Dhaka</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="openPreview({{ $previewTemplateId }}, 'en')" class="px-3 py-1 rounded text-xs font-semibold {{ $previewLocale === 'en' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 border border-slate-300' }}">
                            English
                        </button>
                        <button type="button" wire:click="openPreview({{ $previewTemplateId }}, 'bn')" class="px-3 py-1 rounded text-xs font-semibold {{ $previewLocale === 'bn' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 border border-slate-300' }}">
                            বাংলা (Bengali)
                        </button>
                        <button type="button" wire:click="openPreview({{ $previewTemplateId }}, 'bilingual')" class="px-3 py-1 rounded text-xs font-semibold {{ $previewLocale === 'bilingual' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 border border-slate-300' }}">
                            Bilingual
                        </button>
                        <button type="button" wire:click="closePreview" class="ml-2 text-slate-400 hover:text-slate-600 text-sm font-bold">
                            ✕
                        </button>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="rounded-lg bg-slate-50 p-3 border border-slate-200">
                        <span class="text-xs font-semibold uppercase text-slate-500 block">Rendered Subject:</span>
                        <div class="text-sm font-bold text-slate-900 mt-1">{{ $previewOutput['subject'] ?? '' }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-5 border border-slate-200 prose prose-sm max-w-none text-slate-800">
                        {!! $previewOutput['body_html'] ?? '' !!}
                    </div>
                </div>
                <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                    <button type="button" wire:click="closePreview" class="rounded-lg bg-slate-800 px-4 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                        Close Preview
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
