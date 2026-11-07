<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Domain\Forms\Mail\ApplicationConfirmationMail;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Services\DuplicateSubmissionGuard;
use App\Domain\Forms\Services\FormSchemaEngine;
use App\Domain\Forms\Services\LeadIngestionService;
use App\Domain\Payments\Adapters\ManualBkashPaymentAdapter;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('forms.public-layout')]
class PublicMembershipForm extends Component
{
    use WithFileUploads;

    public ?Form $form = null;
    public string $slug = 'membership-application';

    // 1. Personal Information (Fields 1-6)
    public string $first_name = '';
    public string $middle_name = '';
    public string $last_name = '';
    public string $american_space = 'emk_center';
    public string $sex = '';
    public string $date_of_birth = '';

    // 2. Youth Consent (Field 7 - DEC-09)
    public mixed $parental_consent_file = null;

    // 3. Address in Bangladesh (Fields 8-13)
    public string $address_house_no = '';
    public string $address_road_no = '';
    public string $address_area = '';
    public string $address_zip_code = '';
    public string $address_division = '';
    public ?string $address_district = null;

    // 4. Contact & Online Presence (Fields 14-16)
    public string $contact_number = '';
    public string $email = '';
    public string $website = '';

    // 5. Education & Professional Background (Fields 17-27)
    public string $occupation = '';
    public string $occupation_other = '';
    public string $institution_study = '';
    public string $highest_certification = '';
    public string $highest_certification_other = '';
    public string $area_of_subject = '';
    public string|int|bool $has_us_experience = '0';
    public string $us_experience_location = '';
    public string|int|bool $is_american_alumni = '0';
    public string $american_institution_name = '';
    public string $employer_name = '';
    public string $job_title = '';
    public string $work_phone = '';

    // 6. Identity Documents & Studio Photograph (Fields 28-29)
    public array $identity_document_files = [];
    public mixed $photo_file = null;

    // 7. Payment Verification & Terms Acceptance (Fields 30-31)
    public string $bkash_txid = '';
    public bool $terms_agreed = false;

    // Dynamic reactive state
    public ?int $age_years = null;
    public bool $requires_consent = false;
    public array $available_districts = [];

    // Submission lifecycle state
    public bool $submitted = false;
    public bool $is_submitted = false;
    public ?string $submission_uuid = null;
    public ?string $reference_uuid = null;
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(string $slug = 'membership-application'): void
    {
        $this->slug = $slug;

        $this->form = Form::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (!$this->form) {
            abort(404, 'Form not found or unpublished.');
        }

        if (request()->wantsJson()) {
            abort(response()->json([
                'status' => 'ok',
                'form' => [
                    'uuid' => $this->form->uuid,
                    'slug' => $this->form->slug,
                    'title' => $this->form->title,
                    'description' => $this->form->description,
                    'schema' => $this->form->schema,
                    'settings' => $this->form->settings,
                ],
            ], 200));
        }

        if (!empty($this->address_division)) {
            $this->updateDistrictsForDivision();
        }

        if (!empty($this->date_of_birth)) {
            $this->recalculateAge();
        }
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'date_of_birth') {
            $this->recalculateAge();
        }

        if ($propertyName === 'address_division') {
            $this->updateDistrictsForDivision();
        }

        if ($propertyName === 'occupation') {
            if ($this->occupation !== 'other') {
                $this->occupation_other = '';
                $this->resetErrorBag('occupation_other');
            } else {
                $this->validateOnly('occupation_other');
            }
        }

        if ($propertyName === 'highest_certification') {
            if ($this->highest_certification !== 'other') {
                $this->highest_certification_other = '';
                $this->resetErrorBag('highest_certification_other');
            } else {
                $this->validateOnly('highest_certification_other');
            }
        }

        if ($propertyName === 'has_us_experience') {
            if (!in_array((string) $this->has_us_experience, ['1', 'true'], true)) {
                $this->us_experience_location = '';
                $this->resetErrorBag('us_experience_location');
            } else {
                $this->validateOnly('us_experience_location');
            }
        }

        if ($propertyName === 'is_american_alumni') {
            if (!in_array((string) $this->is_american_alumni, ['1', 'true'], true)) {
                $this->american_institution_name = '';
                $this->resetErrorBag('american_institution_name');
            } else {
                $this->validateOnly('american_institution_name');
            }
        }

        $rules = $this->rules();
        if (array_key_exists($propertyName, $rules)) {
            $this->validateOnly($propertyName);
        }
    }

    public function updatedOccupation($value): void
    {
        if ($this->occupation !== 'other') {
            $this->occupation_other = '';
            $this->resetErrorBag('occupation_other');
        } else {
            $this->validateOnly('occupation_other');
        }
    }

    public function updatedHighestCertification($value): void
    {
        if ($this->highest_certification !== 'other') {
            $this->highest_certification_other = '';
            $this->resetErrorBag('highest_certification_other');
        } else {
            $this->validateOnly('highest_certification_other');
        }
    }

    public function updatedHasUsExperience($value): void
    {
        if (!in_array((string) $this->has_us_experience, ['1', 'true'], true)) {
            $this->us_experience_location = '';
            $this->resetErrorBag('us_experience_location');
        } else {
            $this->validateOnly('us_experience_location');
        }
    }

    public function updatedIsAmericanAlumni($value): void
    {
        if (!in_array((string) $this->is_american_alumni, ['1', 'true'], true)) {
            $this->american_institution_name = '';
            $this->resetErrorBag('american_institution_name');
        } else {
            $this->validateOnly('american_institution_name');
        }
    }

    public function updatedAddressDivision($value): void
    {
        $this->updateDistrictsForDivision();
        $this->validateOnly('address_division');
    }

    public function updatedDateOfBirth($value): void
    {
        $this->recalculateAge();
        $this->validateOnly('date_of_birth');
    }

    public function updatedBkashTxid($value): void
    {
        $this->bkash_txid = ManualBkashPaymentAdapter::extractTxid((string) $value);
        if (strlen($this->bkash_txid) === 10) {
            $this->validateOnly('bkash_txid');
        } elseif (strlen($this->bkash_txid) === 0) {
            $this->resetErrorBag('bkash_txid');
        }
    }

    public function updateDistrictsForDivision(): void
    {
        $schemaEngine = app(FormSchemaEngine::class);
        $normalized = strtolower(trim((string) $this->address_division));
        $this->available_districts = $schemaEngine->getDataset('districts', $normalized);

        if (!empty($this->address_district) && !in_array($this->address_district, $this->available_districts, true)) {
            $this->address_district = null;
        }
    }

    public function validateProperty(?string $name = null): void
    {
        if ($name) {
            $this->validateOnly($name);
        } else {
            $this->validate();
        }
    }

    public function recalculateAge(): void
    {
        if (empty($this->date_of_birth)) {
            $this->age_years = null;
            $this->requires_consent = false;
            $this->parental_consent_file = null;
            return;
        }

        try {
            $dobCarbon = Carbon::parse($this->date_of_birth);
            $this->age_years = $dobCarbon->age;
            $this->requires_consent = ($this->age_years < 18);

            if (!$this->requires_consent) {
                $this->parental_consent_file = null;
            }
        } catch (\Throwable) {
            $this->age_years = null;
            $this->requires_consent = false;
        }
    }

    public function rules(): array
    {
        $rules = [
            // 1. Personal Information (Fields 1-6)
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s\'\-]{2,50}$/'],
            'middle_name' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z\s\'\-]{1,50}$/'],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s\'\-]{2,50}$/'],
            'american_space' => ['required', 'string', 'in:american_center,emk_center,barishal_corner,chattogram_corner,rajshahi_corner,sylhet_corner'],
            'sex' => ['required', 'string', 'in:male,female,na'],
            'date_of_birth' => ['required', 'date', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],

            // 2. Youth Consent (Field 7 per DEC-09)
            'parental_consent_file' => $this->requires_consent
                ? ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096']
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
            'occupation_other' => ($this->occupation === 'other')
                ? ['required', 'string', 'min:2', 'max:100']
                : ['nullable', 'string', 'max:100'],
            'institution_study' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z0-9\s,\-\.\'&]{2,150}$/'],
            'highest_certification' => ['required', 'string', 'in:ssc,o_level,hsc,a_level,undergraduate_degree,undergraduate_honors,graduate,postgraduate,other'],
            'highest_certification_other' => ($this->highest_certification === 'other')
                ? ['required', 'string', 'min:2', 'max:100']
                : ['nullable', 'string', 'max:100'],
            'area_of_subject' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z0-9\s,\-\.\'&]{2,150}$/'],
            'has_us_experience' => ['required', 'in:0,1,true,false'],
            'us_experience_location' => (in_array((string) $this->has_us_experience, ['1', 'true'], true))
                ? ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z0-9\s,\-\.]{2,150}$/']
                : ['nullable', 'string', 'max:150'],
            'is_american_alumni' => ['required', 'in:0,1,true,false'],
            'american_institution_name' => (in_array((string) $this->is_american_alumni, ['1', 'true'], true))
                ? ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z0-9\s,\-\.\'&]{2,150}$/']
                : ['nullable', 'string', 'max:150'],
            'employer_name' => ['nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9\s,\-\.\'&]{2,150}$/'],
            'job_title' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\s,\-\.\'&]{2,100}$/'],
            'work_phone' => ['nullable', 'string', 'regex:/^\+8801[3-9]\d{8}$/'],

            // 6. Identity Documents & Studio Photograph (Fields 28-29)
            'identity_document_files' => ['required', 'array', 'min:1', 'max:2'],
            'identity_document_files.*' => ['file', 'mimes:pdf,jpg,jpeg,doc,docx,png', 'max:4096'],
            'photo_file' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],

            // 7. Payment Verification & Terms Acceptance (Fields 30-31 per DEC-05)
            'bkash_txid' => ['required', 'string', 'size:10', 'regex:/^[A-Z0-9]{10}$/'],
            'terms_agreed' => ['accepted'],
        ];

        if (!empty($this->address_division)) {
            $schemaEngine = app(FormSchemaEngine::class);
            $districts = $schemaEngine->getDataset('districts', strtolower($this->address_division));
            if (!empty($districts)) {
                $rules['address_district'][] = 'in:' . implode(',', $districts);
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => 'Please enter a valid first name (2-50 letters).',
            'last_name.regex' => 'Please enter a valid last name (2-50 letters).',
            'american_space.in' => 'Please select a valid American Space.',
            'sex.in' => 'Please select a valid option for sex.',
            'date_of_birth.date_format' => 'Please enter a valid date of birth.',
            'parental_consent_file.required' => 'Parental/Guardian consent form is mandatory for applicants under 18 years of age.',
            'address_division.in' => 'Please select your administrative division.',
            'address_district.in' => 'Please select a valid district for the chosen division.',
            'contact_number.regex' => 'Please enter a valid Bangladeshi mobile number in format +8801XXXXXXXXX.',
            'occupation_other.required' => 'Please specify your occupation.',
            'highest_certification_other.required' => 'Please specify your highest certification.',
            'us_experience_location.required' => 'Please specify your U.S. work or study location.',
            'american_institution_name.required' => 'Please enter the name of the American institution attended.',
            'identity_document_files.required' => 'Please upload 1 or 2 valid identity documents (NID, Passport, Birth Certificate, or Student ID), up to 4MB each.',
            'photo_file.required' => 'Please upload a studio passport photo: white background, no glasses, ears visible, regular clothes, no selfies (Max 4MB).',
            'bkash_txid.required' => 'Please enter a valid 10-character uppercase bKash Transaction ID (e.g., BLK7X9Q12M).',
            'bkash_txid.size' => 'Please enter a valid 10-character uppercase bKash Transaction ID (e.g., BLK7X9Q12M).',
            'bkash_txid.regex' => 'Please enter a valid 10-character uppercase bKash Transaction ID (e.g., BLK7X9Q12M).',
            'terms_agreed.accepted' => 'You must read and agree to the Membership Terms and Conditions to submit your application.',
        ];
    }

    public function submit(): void
    {
        $this->errorMessage = null;

        $this->bkash_txid = ManualBkashPaymentAdapter::extractTxid($this->bkash_txid);

        $this->validate();

        try {
            $clientIp = request()->ip() ?: '127.0.0.1';
            DuplicateSubmissionGuard::checkRateLimit($clientIp);

            $ingestionService = app(LeadIngestionService::class);
            $lead = $ingestionService->ingest(
                form: $this->form,
                validatedData: [
                    'first_name' => $this->first_name,
                    'middle_name' => $this->middle_name,
                    'last_name' => $this->last_name,
                    'american_space' => $this->american_space,
                    'sex' => $this->sex,
                    'date_of_birth' => $this->date_of_birth,
                    'address_house_no' => $this->address_house_no,
                    'address_road_no' => $this->address_road_no,
                    'address_area' => $this->address_area,
                    'address_zip_code' => $this->address_zip_code,
                    'address_division' => $this->address_division,
                    'address_district' => $this->address_district,
                    'contact_number' => $this->contact_number,
                    'email' => $this->email,
                    'website' => $this->website,
                    'occupation' => $this->occupation,
                    'occupation_other' => $this->occupation_other,
                    'institution_study' => $this->institution_study,
                    'highest_certification' => $this->highest_certification,
                    'highest_certification_other' => $this->highest_certification_other,
                    'area_of_subject' => $this->area_of_subject,
                    'has_us_experience' => $this->has_us_experience,
                    'us_experience_location' => $this->us_experience_location,
                    'is_american_alumni' => $this->is_american_alumni,
                    'american_institution_name' => $this->american_institution_name,
                    'employer_name' => $this->employer_name,
                    'job_title' => $this->job_title,
                    'work_phone' => $this->work_phone,
                    'bkash_txid' => $this->bkash_txid,
                    'terms_agreed' => $this->terms_agreed,
                ],
                uploadedFiles: [
                    'identity_document_files' => $this->identity_document_files,
                    'photo_file' => $this->photo_file,
                    'parental_consent_file' => $this->parental_consent_file,
                ],
                ipAddress: $clientIp,
                userAgent: request()->userAgent()
            );

            // Queue automated applicant confirmation email
            Mail::to($lead->email)->queue(new ApplicationConfirmationMail($lead));

            // Dispatch official notification to Workspace Admin per user requirement
            try {
                $emailDispatcher = app(\App\Domain\Mail\Services\QueuedEmailDispatcher::class);
                $workspaceId = $lead->workspace_id ?: 'ws-emk-dhaka';
                $adminEmail = \App\Models\User::withoutGlobalScopes()
                    ->where(function ($q) use ($workspaceId) {
                        $q->where('workspace_id', $workspaceId)->orWhereNull('workspace_id');
                    })
                    ->where('is_active', true)
                    ->value('email') ?? (string) config('mail.from.address', 'admin@emk.test');

                $applicantFullName = trim(
                    $lead->first_name . ' ' .
                    ($lead->middle_name ? $lead->middle_name . ' ' : '') .
                    $lead->last_name
                );

                $emailDispatcher->dispatchTemplate(
                    workspaceId: $workspaceId,
                    templateKey: \App\Domain\Mail\Models\EmailTemplate::MILESTONE_ADMIN_NEW_USER,
                    recipientEmail: $adminEmail,
                    variables: [
                        'applicant_name' => $applicantFullName,
                        'workspace_name' => 'EMK Center Dhaka',
                        'amount_bdt' => $lead->bkash_txid ? '500.00' : '0.00',
                        'action_url' => url('/crm/' . $workspaceId . '/leads'),
                    ],
                    locale: 'en',
                    leadId: $lead->id
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Admin notification dispatch skipped: ' . $e->getMessage());
            }

            $this->submission_uuid = $lead->uuid;
            $this->reference_uuid = $lead->uuid;
            $this->submitted = true;
            $this->is_submitted = true;
            $this->successMessage = 'Your application has been received successfully! Reference ID: ' . $lead->uuid;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $this->addError($field, $msg);
                }
            }
            $this->errorMessage = 'Please review the highlighted errors before submitting.';
        } catch (ThrottleRequestsException $e) {
            $this->addError('bkash_txid', $e->getMessage());
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = 'An error occurred while submitting your application: ' . $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.forms.public-membership-form', [
            'divisions' => FormSchemaEngine::BANGLADESH_DIVISIONS,
            'americanSpaces' => FormSchemaEngine::AMERICAN_SPACES,
        ]);
    }
}
