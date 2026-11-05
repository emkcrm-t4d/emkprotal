<?php

declare(strict_types=1);

namespace App\Domain\Migration\Services;

use App\Domain\Forms\Models\MembershipTerm;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ZohoFieldMappingService
{
    /**
     * Map a raw record according to source type and workspace.
     *
     * @param  string  $sourceType  'contacts_members', 'leads', or 'form_submissions'
     * @param  array<string, mixed>  $rawRow
     * @param  string  $workspaceId
     * @return array{mapped: array<string, mixed>, validation_status: string, validation_errors: list<string>}
     */
    public function mapRecord(string $sourceType, array $rawRow, string $workspaceId): array
    {
        return match ($sourceType) {
            'contacts_members' => $this->mapContactMember($rawRow, $workspaceId),
            'leads' => $this->mapLead($rawRow, $workspaceId),
            'form_submissions' => $this->mapFormSubmission($rawRow, $workspaceId),
            default => [
                'mapped' => $rawRow,
                'validation_status' => 'invalid',
                'validation_errors' => ["Unsupported source type [{$sourceType}]."],
            ],
        };
    }

    /**
     * Map a legacy Zoho Contact/Member record into Hub members schema.
     *
     * @param  array<string, mixed>  $row
     * @param  string  $workspaceId
     * @return array{mapped: array<string, mixed>, validation_status: string, validation_errors: list<string>}
     */
    public function mapContactMember(array $row, string $workspaceId): array
    {
        $errors = [];
        $hasWarnings = false;

        // 1. Zoho ID
        $zohoId = $this->sanitizeString($this->getValue($row, [
            'CONTACTID', 'Contact Id', 'Contact ID', 'Record Id', 'Record ID', 'zoho_id', 'Zoho ID', 'ID',
        ]));

        if (empty($zohoId)) {
            $errors[] = 'Missing Zoho contact identifier.';
        }

        // 2. Names
        $firstName = $this->sanitizeString($this->getValue($row, ['First Name', 'First_Name', 'first_name', 'FirstName']));
        $lastName = $this->sanitizeString($this->getValue($row, ['Last Name', 'Last_Name', 'last_name', 'LastName', 'Surname']));
        $middleName = $this->sanitizeString($this->getValue($row, ['Middle Name', 'Middle_Name', 'middle_name', 'MiddleName']));

        if (empty($firstName) && empty($lastName)) {
            $fullName = $this->sanitizeString($this->getValue($row, ['Full Name', 'Contact Name', 'Name']));
            if (!empty($fullName)) {
                $parts = explode(' ', $fullName, 2);
                $firstName = $parts[0];
                $lastName = $parts[1] ?? 'Member';
            } else {
                $firstName = 'Unknown';
                $lastName = 'Member';
                $hasWarnings = true;
            }
        } elseif (empty($firstName)) {
            $firstName = 'Member';
            $hasWarnings = true;
        } elseif (empty($lastName)) {
            $lastName = 'Member';
            $hasWarnings = true;
        }

        // 3. Email
        $rawEmail = $this->sanitizeString($this->getValue($row, ['Email', 'Email Address', 'email', 'Primary Email']));
        $email = !empty($rawEmail) ? Str::lower(trim($rawEmail)) : null;

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid or missing email address [{$rawEmail}].";
        }

        // 4. Phone
        $rawPhone = $this->getValue($row, ['Phone', 'Mobile', 'Mobile Phone', 'Phone Number', 'Contact Number']);
        $phone = $this->sanitizeBangladeshiPhone($rawPhone);
        if (empty($phone)) {
            $phone = '+8801700000000';
            $hasWarnings = true;
        }

        // 5. Date of Birth
        $rawDob = $this->getValue($row, ['Date of Birth', 'DOB', 'Date_of_Birth', 'Birthdate']);
        $dob = $this->parseDate($rawDob);
        if (empty($dob)) {
            $dob = '1995-01-01';
            $hasWarnings = true;
        }

        // 6. Gender
        $rawSex = $this->getValue($row, ['Gender', 'Sex', 'sex', 'gender']);
        $sex = $this->normalizeGender($rawSex);

        // 7. Address
        $houseNo = $this->sanitizeString($this->getValue($row, ['House No', 'House No.', 'Mailing Street', 'Address', 'Street', 'House_No'])) ?? 'N/A';
        $roadNo = $this->sanitizeString($this->getValue($row, ['Road No', 'Road No.', 'Road', 'Road_No']));
        $area = $this->sanitizeString($this->getValue($row, ['Area', 'Mailing Area', 'Neighborhood', 'Area_Name'])) ?? 'Dhaka';
        $zipCode = $this->sanitizeString($this->getValue($row, ['Postal Code', 'Zip Code', 'Mailing Zip', 'ZIP / Postal Code', 'Zip'])) ?? '1209';
        $division = $this->normalizeDivision($this->getValue($row, ['Division', 'Mailing State', 'State', 'Division_Name']));
        $district = $this->normalizeDistrict($this->getValue($row, ['District', 'Mailing City', 'City', 'District_Name']), $division);

        // 8. Education & Occupation
        $occupation = $this->normalizeOccupation($this->getValue($row, ['Occupation', 'Designation', 'Profession']));
        $institution = $this->sanitizeString($this->getValue($row, ['Institution', 'Institution Study', 'School / College / University', 'University', 'College'])) ?? 'N/A';
        $certification = $this->normalizeHighestCertification($this->getValue($row, ['Highest Certification', 'Certification', 'Degree', 'Education']));
        $areaOfSubject = $this->sanitizeString($this->getValue($row, ['Area of Subject', 'Subject', 'Major', 'Department'])) ?? 'General';

        // 9. Membership Term & Dates
        $rawTerm = $this->getValue($row, ['Membership Type', 'Membership Term', 'Term', 'Plan', 'Membership_Type']);
        $termId = $this->resolveMembershipTermId($rawTerm, $workspaceId);

        $rawStartDate = $this->getValue($row, ['Start Date', 'Valid From', 'Membership Start Date', 'Created Time', 'Added Time', 'Start_Date']);
        $startDate = $this->parseDate($rawStartDate) ?? Carbon::now()->toDateString();

        $rawExpiryDate = $this->getValue($row, ['Expiry Date', 'Valid To', 'Membership Expiry Date', 'End Date', 'Expiry_Date']);
        $expiryDate = $this->parseDate($rawExpiryDate);
        if (empty($expiryDate)) {
            $durationMonths = $this->getTermDurationMonths($termId);
            $expiryDate = Carbon::parse($startDate)->addMonths($durationMonths)->toDateString();
        }

        // 10. Status
        $rawStatus = $this->getValue($row, ['Status', 'Membership Status', 'Member Status', 'Lead Status']);
        if (!empty($rawStatus)) {
            $status = $this->normalizeMemberStatus($rawStatus);
        } else {
            $status = Carbon::parse($expiryDate)->isPast() ? 'inactive' : 'active';
        }

        // bKash TxID if present in member export
        $rawTxid = $this->getValue($row, ['bKash Transaction ID', 'Payment Ref', 'TxID', 'Transaction ID', 'bKash TxID']);
        $bkashTxid = $this->normalizeBkashTxid($rawTxid);

        $validationStatus = !empty($errors) ? 'invalid' : ($hasWarnings ? 'warning' : 'valid');

        return [
            'mapped' => [
                'zoho_id' => $zohoId,
                'workspace_id' => $workspaceId,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'email' => $email,
                'contact_number' => $phone,
                'date_of_birth' => $dob,
                'sex' => $sex,
                'address_house_no' => $houseNo,
                'address_road_no' => $roadNo,
                'address_area' => $area,
                'address_zip_code' => $zipCode,
                'address_division' => $division,
                'address_district' => $district,
                'occupation' => $occupation,
                'institution_study' => $institution,
                'highest_certification' => $certification,
                'area_of_subject' => $areaOfSubject,
                'membership_term_id' => $termId,
                'start_date' => $startDate,
                'expiry_date' => $expiryDate,
                'status' => $status,
                'bkash_txid' => $bkashTxid,
            ],
            'validation_status' => $validationStatus,
            'validation_errors' => $errors,
        ];
    }

    /**
     * Map a legacy Zoho Lead record into Hub leads schema.
     *
     * @param  array<string, mixed>  $row
     * @param  string  $workspaceId
     * @return array{mapped: array<string, mixed>, validation_status: string, validation_errors: list<string>}
     */
    public function mapLead(array $row, string $workspaceId): array
    {
        $errors = [];
        $hasWarnings = false;

        // 1. Zoho ID
        $zohoId = $this->sanitizeString($this->getValue($row, [
            'LEADID', 'Lead ID', 'Lead Id', 'Record Id', 'Record ID', 'zoho_id', 'Zoho ID', 'ID',
        ]));

        if (empty($zohoId)) {
            $errors[] = 'Missing Zoho lead identifier.';
        }

        // 2. Names
        $firstName = $this->sanitizeString($this->getValue($row, ['First Name', 'First_Name', 'first_name', 'FirstName']));
        $lastName = $this->sanitizeString($this->getValue($row, ['Last Name', 'Last_Name', 'last_name', 'LastName', 'Surname']));
        $middleName = $this->sanitizeString($this->getValue($row, ['Middle Name', 'Middle_Name', 'middle_name', 'MiddleName']));

        if (empty($firstName) && empty($lastName)) {
            $fullName = $this->sanitizeString($this->getValue($row, ['Full Name', 'Lead Name', 'Name']));
            if (!empty($fullName)) {
                $parts = explode(' ', $fullName, 2);
                $firstName = $parts[0];
                $lastName = $parts[1] ?? 'Applicant';
            } else {
                $firstName = 'Unknown';
                $lastName = 'Applicant';
                $hasWarnings = true;
            }
        } elseif (empty($firstName)) {
            $firstName = 'Applicant';
            $hasWarnings = true;
        } elseif (empty($lastName)) {
            $lastName = 'Applicant';
            $hasWarnings = true;
        }

        // 3. Email
        $rawEmail = $this->sanitizeString($this->getValue($row, ['Email', 'Email Address', 'email', 'Primary Email']));
        $email = !empty($rawEmail) ? Str::lower(trim($rawEmail)) : null;

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid or missing email address [{$rawEmail}].";
        }

        // 4. Phone
        $rawPhone = $this->getValue($row, ['Phone', 'Mobile', 'Mobile Phone', 'Phone Number', 'Contact Number']);
        $phone = $this->sanitizeBangladeshiPhone($rawPhone);
        if (empty($phone)) {
            $phone = '+8801700000000';
            $hasWarnings = true;
        }

        // 5. Date of Birth & Age
        $rawDob = $this->getValue($row, ['Date of Birth', 'DOB', 'Date_of_Birth', 'Birthdate']);
        $dob = $this->parseDate($rawDob);
        if (empty($dob)) {
            $dob = '2000-01-01';
            $hasWarnings = true;
        }

        $age = 24;
        try {
            $age = Carbon::parse($dob)->age;
        } catch (\Throwable) {
            $age = 24;
        }

        // 6. Sex
        $rawSex = $this->getValue($row, ['Gender', 'Sex', 'sex', 'gender']);
        $sex = $this->normalizeGender($rawSex);

        // 7. bKash TxID (DEC-05)
        $rawTxid = $this->getValue($row, [
            'bKash Transaction ID', 'Payment Ref', 'TxID', 'bKash TxID', 'Transaction ID', 'bKash TrxID', 'Payment Reference',
        ]);
        $bkashTxid = $this->normalizeBkashTxid($rawTxid);
        if (empty($bkashTxid)) {
            $hasWarnings = true;
        }

        // 8. Address
        $houseNo = $this->sanitizeString($this->getValue($row, ['House No', 'House No.', 'Street', 'Address', 'House_No'])) ?? 'N/A';
        $roadNo = $this->sanitizeString($this->getValue($row, ['Road No', 'Road No.', 'Road', 'Road_No']));
        $area = $this->sanitizeString($this->getValue($row, ['Area', 'Neighborhood', 'Area_Name'])) ?? 'Dhaka';
        $zipCode = $this->sanitizeString($this->getValue($row, ['Postal Code', 'Zip Code', 'ZIP / Postal Code', 'Zip'])) ?? '1209';
        $division = $this->normalizeDivision($this->getValue($row, ['Division', 'State', 'Division_Name']));
        $district = $this->normalizeDistrict($this->getValue($row, ['District', 'City', 'District_Name']), $division);

        // 9. Education & Work
        $occupation = $this->normalizeOccupation($this->getValue($row, ['Occupation', 'Designation', 'Profession']));
        $institution = $this->sanitizeString($this->getValue($row, ['Institution', 'Institution Study', 'University', 'College', 'School'])) ?? 'N/A';
        $certification = $this->normalizeHighestCertification($this->getValue($row, ['Highest Certification', 'Certification', 'Degree', 'Education']));
        $areaOfSubject = $this->sanitizeString($this->getValue($row, ['Area of Subject', 'Subject', 'Major', 'Department'])) ?? 'General';

        // 10. Status
        $rawStatus = $this->getValue($row, ['Status', 'Lead Status', 'Lead_Status']);
        $status = $this->normalizeLeadStatus($rawStatus);

        // 11. Submitted date
        $rawSubmitted = $this->getValue($row, ['Created Time', 'Added Time', 'Created_Time', 'Submitted At', 'Date Added']);
        $submittedAt = $this->parseDate($rawSubmitted) ?? Carbon::now()->toDateTimeString();

        $validationStatus = !empty($errors) ? 'invalid' : ($hasWarnings ? 'warning' : 'valid');

        return [
            'mapped' => [
                'zoho_id' => $zohoId,
                'workspace_id' => $workspaceId,
                'target_space_code' => 'EMK-DHK',
                'status' => $status,
                'bkash_txid' => $bkashTxid,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'email' => $email,
                'contact_number' => $phone,
                'date_of_birth' => $dob,
                'age_at_submission' => $age,
                'sex' => $sex,
                'address_house_no' => $houseNo,
                'address_road_no' => $roadNo,
                'address_area' => $area,
                'address_zip_code' => $zipCode,
                'address_division' => $division,
                'address_district' => $district,
                'occupation' => $occupation,
                'institution_study' => $institution,
                'highest_certification' => $certification,
                'area_of_subject' => $areaOfSubject,
                'has_us_experience' => false,
                'is_american_alumni' => false,
                'has_parental_consent' => ($age < 18),
                'terms_agreed' => true,
                'submitted_at' => $submittedAt,
            ],
            'validation_status' => $validationStatus,
            'validation_errors' => $errors,
        ];
    }

    /**
     * Map a legacy Zoho Form submission.
     *
     * @param  array<string, mixed>  $row
     * @param  string  $workspaceId
     * @return array{mapped: array<string, mixed>, validation_status: string, validation_errors: list<string>}
     */
    public function mapFormSubmission(array $row, string $workspaceId): array
    {
        $sanitized = [];
        foreach ($row as $k => $v) {
            $sanitized[(string) $k] = is_string($v) ? $this->sanitizeString($v) : $v;
        }

        $zohoId = $this->sanitizeString($this->getValue($row, ['ID', 'Submission ID', 'Record ID', 'zoho_id']));

        return [
            'mapped' => [
                'zoho_id' => $zohoId,
                'workspace_id' => $workspaceId,
                'payload' => $sanitized,
            ],
            'validation_status' => 'valid',
            'validation_errors' => [],
        ];
    }

    /**
     * Helper to retrieve value case-insensitively with flexible delimiter matching.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $possibleKeys
     */
    public function getValue(array $row, array $possibleKeys, mixed $default = null): mixed
    {
        foreach ($possibleKeys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        // Normalized key map
        $normalizedRow = [];
        foreach ($row as $k => $v) {
            $norm = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $k));
            $normalizedRow[$norm] = $v;
        }

        foreach ($possibleKeys as $key) {
            $normKey = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $key));
            if (array_key_exists($normKey, $normalizedRow) && $normalizedRow[$normKey] !== null && $normalizedRow[$normKey] !== '') {
                return $normalizedRow[$normKey];
            }
        }

        return $default;
    }

    /**
     * Sanitize string input: strip tags, null bytes, and script injection per T-11-01.
     */
    public function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = str_replace(["\0", "\x00"], '', $value);
        $clean = strip_tags($clean);
        $clean = preg_replace('/javascript:/i', '', $clean);
        $clean = trim($clean);

        return $clean !== '' ? $clean : null;
    }

    /**
     * Sanitize phone number to standard Bangladeshi +8801XXXXXXXXX format.
     */
    public function sanitizeBangladeshiPhone(mixed $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $digits = preg_replace('/[^\d]/', '', (string) $phone);

        if (empty($digits)) {
            return null;
        }

        // Handle 8801XXXXXXXXX (13 digits)
        if (str_starts_with($digits, '8801') && strlen($digits) === 13) {
            return '+' . $digits;
        }

        // Handle 01XXXXXXXXX (11 digits)
        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            return '+88' . $digits;
        }

        // Handle 1XXXXXXXXX (10 digits)
        if (str_starts_with($digits, '1') && strlen($digits) === 10) {
            return '+880' . $digits;
        }

        // Fallback: if already starts with +, keep clean
        return '+' . $digits;
    }

    /**
     * Multi-format date parser supporting Y-m-d, d/m/Y, m/d/Y, d-M-Y, etc.
     */
    public function parseDate(mixed $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        $trimmed = trim((string) $dateStr);

        $formats = [
            'Y-m-d',
            'd/m/Y',
            'm/d/Y',
            'd-m-Y',
            'd-M-Y',
            'd M Y',
            'd F Y',
            'Y/m/d',
            'Y-m-d H:i:s',
            'd/m/Y H:i:s',
            'm/d/Y H:i:s',
            'Y-m-d\TH:i:s\Z',
            'Y-m-d\TH:i:s.u\Z',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $trimmed);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                // Continue to next format
            }
        }

        try {
            return Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalize gender to 'male', 'female', or 'na'.
     */
    public function normalizeGender(mixed $gender): string
    {
        if (empty($gender)) {
            return 'na';
        }

        $val = strtolower(trim((string) $gender));
        if (in_array($val, ['male', 'm', 'man', 'boy'], true)) {
            return 'male';
        }

        if (in_array($val, ['female', 'f', 'woman', 'girl'], true)) {
            return 'female';
        }

        return 'na';
    }

    /**
     * Normalize division to Bangladesh administrative division.
     */
    public function normalizeDivision(mixed $division): string
    {
        if (empty($division)) {
            return 'dhaka';
        }

        $val = strtolower(trim((string) $division));
        $validDivisions = [
            'barishal', 'chattogram', 'dhaka', 'khulna', 'mymensingh', 'rajshahi', 'rangpur', 'sylhet',
        ];

        if (in_array($val, $validDivisions, true)) {
            return $val;
        }

        if (str_contains($val, 'chittagong')) {
            return 'chattogram';
        }
        if (str_contains($val, 'barisal')) {
            return 'barishal';
        }

        foreach ($validDivisions as $div) {
            if (str_contains($val, $div)) {
                return $div;
            }
        }

        return 'dhaka';
    }

    /**
     * Normalize district within division boundaries.
     */
    public function normalizeDistrict(mixed $district, string $division): string
    {
        if (empty($district)) {
            return $division === 'chattogram' ? 'chattogram' : 'dhaka';
        }

        $val = strtolower(trim(preg_replace('/[^a-zA-Z]/', '', (string) $district)));

        return !empty($val) ? $val : 'dhaka';
    }

    /**
     * Normalize occupation to valid enum.
     */
    public function normalizeOccupation(mixed $occupation): string
    {
        if (empty($occupation)) {
            return 'other';
        }

        $val = strtolower(trim((string) $occupation));
        $map = [
            'student' => 'student',
            'teacher' => 'educator',
            'educator' => 'educator',
            'professor' => 'educator',
            'artist' => 'artist',
            'painter' => 'artist',
            'performer' => 'performer',
            'actor' => 'performer',
            'musician' => 'performer',
            'community' => 'community_worker',
            'social' => 'community_worker',
            'entrepreneur' => 'entrepreneur',
            'business' => 'entrepreneur',
            'media' => 'media_professional',
            'journalist' => 'media_professional',
        ];

        foreach ($map as $needle => $enum) {
            if (str_contains($val, $needle)) {
                return $enum;
            }
        }

        $valid = ['artist', 'community_worker', 'educator', 'entrepreneur', 'media_professional', 'performer', 'student', 'other'];
        if (in_array($val, $valid, true)) {
            return $val;
        }

        return 'other';
    }

    /**
     * Normalize highest certification.
     */
    public function normalizeHighestCertification(mixed $cert): string
    {
        if (empty($cert)) {
            return 'undergraduate_degree';
        }

        $val = strtolower(trim((string) $cert));
        $valid = [
            'ssc', 'o_level', 'hsc', 'a_level', 'undergraduate_degree',
            'undergraduate_honors', 'graduate', 'postgraduate', 'other',
        ];

        if (in_array($val, $valid, true)) {
            return $val;
        }

        if (str_contains($val, 'master') || str_contains($val, 'postgrad') || str_contains($val, 'msc') || str_contains($val, 'mba')) {
            return 'postgraduate';
        }
        if (str_contains($val, 'bachelor') || str_contains($val, 'bsc') || str_contains($val, 'undergrad') || str_contains($val, 'honors')) {
            return 'undergraduate_degree';
        }
        if (str_contains($val, 'hsc') || str_contains($val, 'a level') || str_contains($val, 'a-level') || str_contains($val, 'college')) {
            return 'hsc';
        }
        if (str_contains($val, 'ssc') || str_contains($val, 'o level') || str_contains($val, 'o-level') || str_contains($val, 'school')) {
            return 'ssc';
        }

        return 'undergraduate_degree';
    }

    /**
     * Normalize legacy member status.
     */
    public function normalizeMemberStatus(mixed $status): string
    {
        if (empty($status)) {
            return 'active';
        }

        $val = strtolower(trim((string) $status));
        return match ($val) {
            'active', 'approved', 'current', 'valid' => 'active',
            'expired', 'inactive', 'lapsed' => 'inactive',
            'suspended', 'hold', 'restricted' => 'restricted',
            'revoked', 'cancelled', 'terminated' => 'revoked',
            default => 'active',
        };
    }

    /**
     * Normalize legacy lead status.
     */
    public function normalizeLeadStatus(mixed $status): string
    {
        if (empty($status)) {
            return 'submitted';
        }

        $val = strtolower(trim((string) $status));
        return match ($val) {
            'draft' => 'draft',
            'new', 'submitted', 'pending' => 'submitted',
            'in review', 'under review', 'reviewing' => 'under_review',
            'missing items', 'missing', 'incomplete' => 'missing_items',
            'approved' => 'approved',
            'converted' => 'converted',
            'rejected', 'declined' => 'rejected',
            'blocked', 'banned' => 'blocked',
            default => 'submitted',
        };
    }

    /**
     * Normalize bKash TxID to 10-char uppercase alphanumeric string per DEC-05.
     */
    public function normalizeBkashTxid(mixed $txid): ?string
    {
        if (empty($txid)) {
            return null;
        }

        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $txid));
        if (strlen($clean) >= 10) {
            return substr($clean, 0, 10);
        }

        return !empty($clean) ? $clean : null;
    }

    /**
     * Resolve membership term ID by term code, duration, or fallback to first active.
     */
    public function resolveMembershipTermId(mixed $rawTerm, string $workspaceId): int
    {
        if (!empty($rawTerm)) {
            $termStr = strtolower(trim((string) $rawTerm));

            // Check by code
            $term = MembershipTerm::where('workspace_id', $workspaceId)
                ->where('code', $rawTerm)
                ->first();

            if ($term) {
                return (int) $term->id;
            }

            // Check by name contains
            $term = MembershipTerm::where('workspace_id', $workspaceId)
                ->where(function ($query) use ($termStr) {
                    $query->whereRaw('LOWER(name) LIKE ?', ["%{$termStr}%"])
                        ->orWhereRaw('LOWER(code) LIKE ?', ["%{$termStr}%"]);
                })
                ->first();

            if ($term) {
                return (int) $term->id;
            }

            // Check if contains 1 year or 2 year
            if (str_contains($termStr, '1') || str_contains($termStr, 'one') || str_contains($termStr, '12')) {
                $term = MembershipTerm::where('workspace_id', $workspaceId)
                    ->where('duration_months', 12)
                    ->first();
                if ($term) {
                    return (int) $term->id;
                }
            }

            if (str_contains($termStr, '2') || str_contains($termStr, 'two') || str_contains($termStr, '24')) {
                $term = MembershipTerm::where('workspace_id', $workspaceId)
                    ->where('duration_months', 24)
                    ->first();
                if ($term) {
                    return (int) $term->id;
                }
            }
        }

        // Fallback: first active term in workspace
        $defaultTerm = MembershipTerm::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->first();

        if ($defaultTerm) {
            return (int) $defaultTerm->id;
        }

        // Fallback: any term in workspace or any term
        $anyTerm = MembershipTerm::where('workspace_id', $workspaceId)->first() ?? MembershipTerm::first();
        if ($anyTerm) {
            return (int) $anyTerm->id;
        }

        return 1;
    }

    /**
     * Get duration months for term ID.
     */
    public function getTermDurationMonths(int $termId): int
    {
        $term = MembershipTerm::find($termId);
        return $term !== null ? (int) $term->duration_months : 12;
    }
}
