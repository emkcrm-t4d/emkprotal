<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Forms\Services\FormSchemaEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dob = $this->input('date_of_birth');
        $isMinor = false;
        if (!empty($dob)) {
            try {
                $isMinor = Carbon::parse($dob)->age < 18;
            } catch (\Throwable) {
                $isMinor = false;
            }
        }

        $rules = [
            // 1. Personal Information (Fields 1-6)
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s\'\-]{2,50}$/'],
            'middle_name' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z\s\'\-]{1,50}$/'],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s\'\-]{2,50}$/'],
            'american_space' => ['required', 'string', 'in:american_center,emk_center,barishal_corner,chattogram_corner,rajshahi_corner,sylhet_corner'],
            'sex' => ['required', 'string', 'in:male,female,na'],
            'date_of_birth' => ['required', 'date', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],

            // 2. Youth Consent (Field 7 per DEC-09)
            'parental_consent_file' => $isMinor
                ? ['required_without:parental_consent', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096']
                : ['nullable'],
            'parental_consent' => $isMinor
                ? ['required_without:parental_consent_file', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096']
                : ['nullable'],

            // 3. Address in Bangladesh (Fields 8-13)
            'address_house_no' => ['required', 'string', 'min:1', 'max:100', 'regex:/^[A-Za-z0-9\s,\-\/\.#]{1,100}$/'],
            'address_road_no' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\s,\-\/\.#]{1,100}$/'],
            'address_area' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[A-Za-z0-9\s,\-\.]{2,100}$/'],
            'address_zip_code' => ['required', 'string', 'min:3', 'max:10', 'regex:/^[A-Za-z0-9\s\-]{3,10}$/'],
            'address_division' => ['required', 'string', 'in:barishal,chattogram,dhaka,khulna,mymensingh,rajshahi,rangpur,sylhet'],
            'address_district' => ['required', 'string', 'max:50'],

            // 4. Contact & Online Presence (Fields 14-16)
            'contact_number' => ['required', 'string', 'regex:/^\+8801[3-9]\d{8}$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:150'],
            'website' => ['nullable', 'url', 'max:200'],

            // 5. Education & Professional Background (Fields 17-27)
            'occupation' => ['required', 'string', 'in:artist,community_worker,educator,entrepreneur,media_professional,performer,student,other'],
            'occupation_other' => ['nullable', 'string', 'max:100', 'required_if:occupation,other'],
            'institution_study' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z0-9\s,\-\.\'&]{2,150}$/'],
            'highest_certification' => ['required', 'string', 'in:ssc,o_level,hsc,a_level,undergraduate_degree,undergraduate_honors,graduate,postgraduate,other'],
            'highest_certification_other' => ['nullable', 'string', 'max:100', 'required_if:highest_certification,other'],
            'area_of_subject' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z0-9\s,\-\.\'&]{2,150}$/'],
            'has_us_experience' => ['required', 'in:0,1,true,false'],
            'us_experience_location' => ['nullable', 'string', 'max:150', 'required_if:has_us_experience,1,true', 'regex:/^[A-Za-z0-9\s,\-\.]{2,150}$/'],
            'is_american_alumni' => ['required', 'in:0,1,true,false'],
            'american_institution_name' => ['nullable', 'string', 'max:150', 'required_if:is_american_alumni,1,true', 'regex:/^[A-Za-z0-9\s,\-\.\'&]{2,150}$/'],
            'employer_name' => ['nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9\s,\-\.\'&]{2,150}$/'],
            'job_title' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\s,\-\.\'&]{2,100}$/'],
            'work_phone' => ['nullable', 'string', 'regex:/^\+8801[3-9]\d{8}$/'],

            // 6. Identity Documents & Studio Photograph (Fields 28-29)
            'identity_document_files' => ['required_without:identity_documents', 'array', 'min:1', 'max:2'],
            'identity_document_files.*' => ['file', 'mimes:pdf,jpg,jpeg,doc,docx,png', 'max:4096'],
            'identity_documents' => ['required_without:identity_document_files', 'array', 'min:1', 'max:2'],
            'identity_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,doc,docx,png', 'max:4096'],
            'photo_file' => ['required_without:photo', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'photo' => ['required_without:photo_file', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],

            // 7. Payment Verification & Terms Acceptance (Fields 30-31 per DEC-05)
            'bkash_txid' => ['required', 'string', 'size:10', 'regex:/^[A-Z0-9]{10}$/'],
            'terms_agreed' => ['accepted'],
        ];

        $division = $this->input('address_division');
        if (!empty($division)) {
            $schemaEngine = app(FormSchemaEngine::class);
            $districts = $schemaEngine->getDataset('districts', strtolower((string) $division));
            if (!empty($districts)) {
                $rules['address_district'][] = 'in:' . implode(',', $districts);
            }
        }

        return $rules;
    }

    /**
     * Custom validation error messages per FIELD-DICTIONARY.md.
     */
    public function messages(): array
    {
        return [
            'first_name.regex' => 'Please enter a valid first name (2-50 letters).',
            'last_name.regex' => 'Please enter a valid last name (2-50 letters).',
            'american_space.in' => 'Please select a valid American Space.',
            'sex.in' => 'Please select a valid option for sex.',
            'date_of_birth.date_format' => 'Please enter a valid date of birth.',
            'parental_consent_file.required' => 'Parental/Guardian consent form is mandatory for applicants under 18 years of age.',
            'parental_consent_file.required_without' => 'Parental/Guardian consent form is mandatory for applicants under 18 years of age.',
            'parental_consent.required' => 'Parental/Guardian consent form is mandatory for applicants under 18 years of age.',
            'parental_consent.required_without' => 'Parental/Guardian consent form is mandatory for applicants under 18 years of age.',
            'address_division.in' => 'Please select your administrative division.',
            'address_district.in' => 'Please select a valid district for the chosen division.',
            'contact_number.regex' => 'Please enter a valid Bangladeshi mobile number in format +8801XXXXXXXXX.',
            'occupation_other.required_if' => 'Please specify your occupation.',
            'highest_certification_other.required_if' => 'Please specify your highest certification.',
            'us_experience_location.required_if' => 'Please specify your U.S. work or study location.',
            'american_institution_name.required_if' => 'Please enter the name of the American institution attended.',
            'identity_document_files.required_without' => 'Please upload 1 or 2 valid identity documents (NID, Passport, Birth Certificate, or Student ID), up to 4MB each.',
            'identity_documents.required_without' => 'Please upload 1 or 2 valid identity documents (NID, Passport, Birth Certificate, or Student ID), up to 4MB each.',
            'photo_file.required_without' => 'Please upload a studio passport photo: white background, no glasses, ears visible, regular clothes, no selfies (Max 4MB).',
            'photo.required_without' => 'Please upload a studio passport photo: white background, no glasses, ears visible, regular clothes, no selfies (Max 4MB).',
            'bkash_txid.required' => 'Please enter a valid 10-character uppercase bKash Transaction ID (e.g., BLK7X9Q12M).',
            'bkash_txid.size' => 'Please enter a valid 10-character uppercase bKash Transaction ID (e.g., BLK7X9Q12M).',
            'bkash_txid.regex' => 'Please enter a valid 10-character uppercase bKash Transaction ID (e.g., BLK7X9Q12M).',
            'terms_agreed.accepted' => 'You must read and agree to the Membership Terms and Conditions to submit your application.',
        ];
    }
}
