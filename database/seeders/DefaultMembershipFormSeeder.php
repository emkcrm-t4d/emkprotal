<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Services\FormSchemaEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultMembershipFormSeeder extends Seeder
{
    /**
     * Seed the official 31-field EMK Center Membership Application Form.
     */
    public function run(): void
    {
        $schemaFields = [
            // Field 1: First Name
            [
                'name' => 'first_name',
                'label' => 'First Name',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => true,
                'min_length' => 2,
                'max_length' => 50,
                'regex' => '^[A-Za-z\s\'\-]{2,50}$',
                'placeholder' => 'e.g. Rahim',
                'help_text' => 'English letters, spaces, hyphens, and apostrophes permitted.',
            ],

            // Field 2: Middle Name
            [
                'name' => 'middle_name',
                'label' => 'Middle Name',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => false,
                'max_length' => 50,
                'regex' => '^[A-Za-z\s\'\-]{1,50}$',
                'placeholder' => 'e.g. Uddin (Optional)',
                'help_text' => 'Middle name or initial (optional).',
            ],

            // Field 3: Last Name
            [
                'name' => 'last_name',
                'label' => 'Last Name',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => true,
                'min_length' => 2,
                'max_length' => 50,
                'regex' => '^[A-Za-z\s\'\-]{2,50}$',
                'placeholder' => 'e.g. Ahmed',
                'help_text' => 'Family or surname.',
            ],

            // Field 4: American Spaces
            [
                'name' => 'american_space',
                'label' => 'American Spaces',
                'type' => FormSchemaEngine::TYPE_SELECT,
                'required' => true,
                'dataset' => 'american_spaces',
                'options' => FormSchemaEngine::AMERICAN_SPACES,
                'help_text' => 'Select the American Space or Corner you are applying to.',
            ],

            // Field 5: Sex
            [
                'name' => 'sex',
                'label' => 'Sex',
                'type' => FormSchemaEngine::TYPE_SELECT,
                'required' => true,
                'options' => [
                    'male' => 'Male',
                    'female' => 'Female',
                    'na' => 'N/A',
                ],
                'help_text' => 'Please select an option for sex.',
            ],

            // Field 6: Date of Birth
            [
                'name' => 'date_of_birth',
                'label' => 'Date of Birth',
                'type' => FormSchemaEngine::TYPE_DATE,
                'required' => true,
                'validation_rules' => ['before:today', 'after:1900-01-01'],
                'help_text' => 'Format: YYYY-MM-DD. Applicants under 18 require parental consent.',
            ],

            // Field 7: Parental Consent Upload (Conditional for under 18 per DEC-09)
            [
                'name' => 'parental_consent_path',
                'label' => 'Parental/Guardian Consent Form',
                'type' => FormSchemaEngine::TYPE_FILE,
                'required' => false, // Conditional required if age_years < 18
                'mimes' => 'pdf,jpg,jpeg,png',
                'max_kb' => 4096,
                'conditional_rules' => [
                    'field' => 'age_years',
                    'operator' => 'less_than',
                    'value' => 18,
                ],
                'help_text' => 'Mandatory for applicants under 18 years of age. Download official consent form template.',
            ],

            // Field 8: House No
            [
                'name' => 'address_house_no',
                'label' => 'House / Building No.',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => true,
                'min_length' => 1,
                'max_length' => 100,
                'regex' => '^[A-Za-z0-9\s,\-\/\.#]{1,100}$',
                'placeholder' => 'e.g. House 12, Apt 4B',
            ],

            // Field 9: Road No
            [
                'name' => 'address_road_no',
                'label' => 'Road No. / Street',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => false,
                'max_length' => 100,
                'regex' => '^[A-Za-z0-9\s,\-\/\.#]{1,100}$',
                'placeholder' => 'e.g. Road 5 (Optional)',
            ],

            // Field 10: Area
            [
                'name' => 'address_area',
                'label' => 'Neighborhood / Area',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => true,
                'min_length' => 2,
                'max_length' => 100,
                'regex' => '^[A-Za-z0-9\s,\-\.]{2,100}$',
                'placeholder' => 'e.g. Dhanmondi',
            ],

            // Field 11: ZIP Code
            [
                'name' => 'address_zip_code',
                'label' => 'ZIP / Postal Code',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => true,
                'min_length' => 3,
                'max_length' => 10,
                'regex' => '^[A-Za-z0-9\s\-]{3,10}$',
                'placeholder' => 'e.g. 1209',
            ],

            // Field 12: Division
            [
                'name' => 'address_division',
                'label' => 'Administrative Division',
                'type' => FormSchemaEngine::TYPE_SELECT,
                'required' => true,
                'dataset' => 'bangladesh_divisions',
                'options' => FormSchemaEngine::BANGLADESH_DIVISIONS,
                'help_text' => 'Select your administrative division in Bangladesh.',
            ],

            // Field 13: District (Cascading dropdown partitioned across 8 divisions)
            [
                'name' => 'address_district',
                'label' => 'District',
                'type' => FormSchemaEngine::TYPE_CASCADING_SELECT,
                'required' => true,
                'parent_field' => 'address_division',
                'dataset' => 'districts',
                'help_text' => 'Select a district strictly belonging to the chosen division.',
            ],

            // Field 14: Contact Number
            [
                'name' => 'contact_number',
                'label' => 'Contact Number (Mobile)',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => true,
                'regex' => '^\+8801[3-9]\d{8}$',
                'placeholder' => '+8801712345678',
                'help_text' => 'Bangladeshi mobile number format +8801XXXXXXXXX.',
            ],

            // Field 15: Email
            [
                'name' => 'email',
                'label' => 'Email Address',
                'type' => FormSchemaEngine::TYPE_EMAIL,
                'required' => true,
                'max_length' => 150,
                'placeholder' => 'applicant@example.com',
                'help_text' => 'Active email address for verification and account access.',
            ],

            // Field 16: Website
            [
                'name' => 'website',
                'label' => 'Website / Portfolio URL',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => false,
                'max_length' => 200,
                'placeholder' => 'https://example.com (Optional)',
            ],

            // Field 17: Occupation
            [
                'name' => 'occupation',
                'label' => 'Primary Occupation',
                'type' => FormSchemaEngine::TYPE_SELECT,
                'required' => true,
                'options' => [
                    'artist' => 'Artist',
                    'community_worker' => 'Community Worker',
                    'educator' => 'Educator',
                    'entrepreneur' => 'Entrepreneur',
                    'media_professional' => 'Media Professional',
                    'performer' => 'Performer',
                    'student' => 'Student',
                    'other' => 'Other',
                ],
                'help_text' => 'Select your primary occupation or professional domain.',
            ],

            // Field 18: Institution Study
            [
                'name' => 'institution_study',
                'label' => 'Last / Current Place of Study',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => true,
                'min_length' => 2,
                'max_length' => 150,
                'regex' => '^[A-Za-z0-9\s,\-\.\'&]{2,150}$',
                'placeholder' => 'e.g. University of Dhaka',
            ],

            // Field 19: Highest Certification
            [
                'name' => 'highest_certification',
                'label' => 'Highest Academic Certification',
                'type' => FormSchemaEngine::TYPE_SELECT,
                'required' => true,
                'options' => [
                    'ssc' => 'SSC',
                    'o_level' => 'O-level',
                    'hsc' => 'HSC',
                    'a_level' => 'A-level',
                    'undergraduate_degree' => 'Undergraduate Degree',
                    'undergraduate_honors' => 'Undergraduate Honors',
                    'graduate' => 'Graduate',
                    'postgraduate' => 'Postgraduate',
                    'other' => 'Other',
                ],
            ],

            // Field 20: Area of Subject
            [
                'name' => 'area_of_subject',
                'label' => 'Area of Subject / Major',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => true,
                'min_length' => 2,
                'max_length' => 150,
                'regex' => '^[A-Za-z0-9\s,\-\.\'&]{2,150}$',
                'placeholder' => 'e.g. Economics / Computer Science',
            ],

            // Field 21: Has US Experience
            [
                'name' => 'has_us_experience',
                'label' => 'U.S. Work or Study Experience',
                'type' => FormSchemaEngine::TYPE_RADIO,
                'required' => true,
                'options' => [
                    '1' => 'Yes',
                    '0' => 'No',
                ],
                'help_text' => 'Indicate whether you have studied or worked in the United States.',
            ],

            // Field 22: US Experience Location (Conditional on has_us_experience = 1)
            [
                'name' => 'us_experience_location',
                'label' => 'U.S. Location / Institution',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => false,
                'max_length' => 150,
                'regex' => '^[A-Za-z0-9\s,\-\.]{2,150}$',
                'conditional_rules' => [
                    'field' => 'has_us_experience',
                    'operator' => 'equals',
                    'value' => '1',
                ],
                'placeholder' => 'e.g. Boston, MA or Harvard University',
            ],

            // Field 23: Is American Alumni
            [
                'name' => 'is_american_alumni',
                'label' => 'American Institution Alumni',
                'type' => FormSchemaEngine::TYPE_RADIO,
                'required' => true,
                'options' => [
                    '1' => 'Yes',
                    '0' => 'No',
                ],
                'help_text' => 'Indicate whether you are an alumnus/alumna of an American educational program or institution.',
            ],

            // Field 24: American Institution Name (Conditional on is_american_alumni = 1)
            [
                'name' => 'american_institution_name',
                'label' => 'American School / Institution Name',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => false,
                'max_length' => 150,
                'regex' => '^[A-Za-z0-9\s,\-\.\'&]{2,150}$',
                'conditional_rules' => [
                    'field' => 'is_american_alumni',
                    'operator' => 'equals',
                    'value' => '1',
                ],
                'placeholder' => 'e.g. University of California, Berkeley',
            ],

            // Field 25: Employer Name
            [
                'name' => 'employer_name',
                'label' => 'Current Employer',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => false,
                'max_length' => 150,
                'regex' => '^[A-Za-z0-9\s,\-\.\'&]{2,150}$',
                'placeholder' => 'e.g. ABC Enterprises (Optional)',
            ],

            // Field 26: Job Title
            [
                'name' => 'job_title',
                'label' => 'Job Title / Designation',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => false,
                'max_length' => 100,
                'regex' => '^[A-Za-z0-9\s,\-\.\'&]{2,100}$',
                'placeholder' => 'e.g. Project Officer (Optional)',
            ],

            // Field 27: Work Phone
            [
                'name' => 'work_phone',
                'label' => 'Work Phone',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => false,
                'regex' => '^\+8801[3-9]\d{8}$',
                'placeholder' => '+8801XXXXXXXXX (Optional)',
            ],

            // Field 28: Identity Documents Upload
            [
                'name' => 'identity_document_paths',
                'label' => 'Identity Document(s) Upload',
                'type' => FormSchemaEngine::TYPE_MULTI_FILE,
                'required' => true,
                'min_files' => 1,
                'max_files' => 2,
                'mimes' => 'pdf,jpg,jpeg,doc,docx,png',
                'max_kb' => 4096,
                'help_text' => 'Upload 1 or 2 valid identity documents (NID, Passport, Birth Certificate, or Student ID), up to 4MB each.',
            ],

            // Field 29: Photo Upload (Studio Passport specifications)
            [
                'name' => 'photo_path',
                'label' => 'Studio Passport Photo Upload',
                'type' => FormSchemaEngine::TYPE_FILE,
                'required' => true,
                'mimes' => 'jpg,jpeg,png',
                'max_kb' => 4096,
                'help_text' => 'Studio passport specifications: solid white background, no glasses, ears visible, regular attire, strictly no selfies, min 300x300px, max 4MB.',
            ],

            // Field 30: bKash Transaction ID (10-char uppercase per DEC-05)
            [
                'name' => 'bkash_txid',
                'label' => 'bKash Transaction ID',
                'type' => FormSchemaEngine::TYPE_TEXT,
                'required' => true,
                'min_length' => 10,
                'max_length' => 10,
                'casing' => 'uppercase',
                'regex' => '^[A-Z0-9]{10}$',
                'placeholder' => 'e.g. BLK7X9Q12M',
                'help_text' => 'Enter the 10-character uppercase bKash Transaction ID from your merchant payment.',
            ],

            // Field 31: Terms and Conditions Agreement
            [
                'name' => 'terms_agreed',
                'label' => 'Membership Terms and Conditions Agreement',
                'type' => FormSchemaEngine::TYPE_CHECKBOX,
                'required' => true,
                'help_text' => 'I have read, understood, and agree to the EMK Center Membership Terms and Conditions.',
            ],
        ];

        $settings = [
            'confirmation_email' => true,
            'allowed_spaces' => [
                'american_center',
                'emk_center',
                'barishal_corner',
                'chattogram_corner',
                'rajshahi_corner',
                'sylhet_corner',
            ],
            'applicant_instructions' => [
                'identity_documents' => 'Accepted identity documents: National ID (NID), Passport, Birth Certificate, or Student ID.',
                'studio_photo_guidelines' => 'Solid plain white background, no eyeglasses, ears visible, regular professional or casual attire, strictly no selfies, min 300x300 pixels, max 4MB.',
                'payment_instructions' => 'Non-refundable fee via bKash merchant account; input 10-character uppercase bKash Transaction ID.',
                'under_18_consent_guidance' => 'Signed parental/guardian consent form required for applicants under 18; download template link.',
            ],
        ];

        $existing = Form::withoutGlobalScopes()
            ->where('slug', 'membership-application')
            ->where('workspace_id', 'ws-emk-dhaka')
            ->first();

        $uuid = $existing ? $existing->uuid : (string) Str::uuid();

        Form::withoutGlobalScopes()->updateOrCreate(
            [
                'slug' => 'membership-application',
                'workspace_id' => 'ws-emk-dhaka',
            ],
            [
                'uuid' => $uuid,
                'title' => 'EMK Center Membership Application Form',
                'description' => 'Official application for annual and multi-year membership at EMK Center (American Center Dhaka and American Spaces Bangladesh).',
                'schema' => [
                    'fields' => $schemaFields,
                ],
                'settings' => $settings,
                'is_published' => true,
                'is_default' => true,
                'version' => '1.0-31f',
            ]
        );
    }
}
