<div>
    @if ($isOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <!-- Background Backdrop -->
            <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-200">
                    <!-- Modal Header -->
                    <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900" id="modal-title">
                                Edit Member Contact & Background Details
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5 font-mono">
                                {{ $member->full_name }} • {{ $member->member_id }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Immutability Notice Banner per T-07-02 -->
                    <div class="mx-6 mt-4 p-3.5 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div class="text-xs text-amber-800">
                            <span class="font-semibold">Core Identity Immutability Guard:</span>
                            Name, Date of Birth, Email, and Member ID are fixed identity attributes and cannot be altered here.
                        </div>
                    </div>

                    <!-- Modal Body / Form Inputs -->
                    <form wire:submit.prevent="save" class="px-6 py-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Contact Number -->
                            <div class="sm:col-span-2">
                                <label for="contact_number" class="block text-xs font-medium text-slate-700 mb-1">
                                    Contact Number (+8801XXXXXXXXX) <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="contact_number"
                                    wire:model="contact_number"
                                    placeholder="+8801711111111"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('contact_number') border-rose-500 @enderror"
                                />
                                @error('contact_number')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- House / Flat No -->
                            <div>
                                <label for="address_house_no" class="block text-xs font-medium text-slate-700 mb-1">
                                    House / Flat No. <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="address_house_no"
                                    wire:model="address_house_no"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('address_house_no') border-rose-500 @enderror"
                                />
                                @error('address_house_no')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Road No -->
                            <div>
                                <label for="address_road_no" class="block text-xs font-medium text-slate-700 mb-1">
                                    Road No. (Optional)
                                </label>
                                <input
                                    type="text"
                                    id="address_road_no"
                                    wire:model="address_road_no"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('address_road_no') border-rose-500 @enderror"
                                />
                                @error('address_road_no')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Area / Neighborhood -->
                            <div>
                                <label for="address_area" class="block text-xs font-medium text-slate-700 mb-1">
                                    Area / Neighborhood <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="address_area"
                                    wire:model="address_area"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('address_area') border-rose-500 @enderror"
                                />
                                @error('address_area')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Postal / ZIP Code -->
                            <div>
                                <label for="address_zip_code" class="block text-xs font-medium text-slate-700 mb-1">
                                    Postal / ZIP Code <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="address_zip_code"
                                    wire:model="address_zip_code"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('address_zip_code') border-rose-500 @enderror"
                                />
                                @error('address_zip_code')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Division -->
                            <div>
                                <label for="address_division" class="block text-xs font-medium text-slate-700 mb-1">
                                    Division <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="address_division"
                                    wire:model="address_division"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('address_division') border-rose-500 @enderror"
                                >
                                    <option value="">Select Division</option>
                                    <option value="barishal">Barishal</option>
                                    <option value="chattogram">Chattogram</option>
                                    <option value="dhaka">Dhaka</option>
                                    <option value="khulna">Khulna</option>
                                    <option value="mymensingh">Mymensingh</option>
                                    <option value="rajshahi">Rajshahi</option>
                                    <option value="rangpur">Rangpur</option>
                                    <option value="sylhet">Sylhet</option>
                                </select>
                                @error('address_division')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- District -->
                            <div>
                                <label for="address_district" class="block text-xs font-medium text-slate-700 mb-1">
                                    District <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="address_district"
                                    wire:model="address_district"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('address_district') border-rose-500 @enderror"
                                />
                                @error('address_district')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Occupation -->
                            <div>
                                <label for="occupation" class="block text-xs font-medium text-slate-700 mb-1">
                                    Occupation <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="occupation"
                                    wire:model="occupation"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('occupation') border-rose-500 @enderror"
                                >
                                    <option value="">Select Occupation</option>
                                    <option value="artist">Artist</option>
                                    <option value="community_worker">Community Worker</option>
                                    <option value="educator">Educator</option>
                                    <option value="entrepreneur">Entrepreneur</option>
                                    <option value="media_professional">Media Professional</option>
                                    <option value="performer">Performer</option>
                                    <option value="student">Student</option>
                                    <option value="other">Other</option>
                                </select>
                                @error('occupation')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Institution of Study -->
                            <div>
                                <label for="institution_study" class="block text-xs font-medium text-slate-700 mb-1">
                                    Institution of Study <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="institution_study"
                                    wire:model="institution_study"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('institution_study') border-rose-500 @enderror"
                                />
                                @error('institution_study')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Highest Certification -->
                            <div>
                                <label for="highest_certification" class="block text-xs font-medium text-slate-700 mb-1">
                                    Highest Certification <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="highest_certification"
                                    wire:model="highest_certification"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('highest_certification') border-rose-500 @enderror"
                                >
                                    <option value="">Select Certification</option>
                                    <option value="ssc">SSC</option>
                                    <option value="o_level">O-level</option>
                                    <option value="hsc">HSC</option>
                                    <option value="a_level">A-level</option>
                                    <option value="undergraduate_degree">Undergraduate Degree</option>
                                    <option value="undergraduate_honors">Undergraduate Honors</option>
                                    <option value="graduate">Graduate</option>
                                    <option value="postgraduate">Postgraduate</option>
                                    <option value="other">Other</option>
                                </select>
                                @error('highest_certification')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Area of Subject -->
                            <div>
                                <label for="area_of_subject" class="block text-xs font-medium text-slate-700 mb-1">
                                    Area of Subject / Discipline <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="area_of_subject"
                                    wire:model="area_of_subject"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('area_of_subject') border-rose-500 @enderror"
                                />
                                @error('area_of_subject')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Modal Actions -->
                        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50"
                                id="btn-save-member-contact"
                            >
                                <span wire:loading.remove>Save Changes</span>
                                <span wire:loading class="flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    Saving...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
