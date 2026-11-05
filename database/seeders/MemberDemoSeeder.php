<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Models\MemberUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MemberDemoSeeder extends Seeder
{
    /**
     * Run the database seeds for demo member accounts.
     */
    public function run(): void
    {
        $term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'std-1yr'],
            [
                'name' => 'Standard 1-Year Membership',
                'duration_months' => 12,
                'fee_bdt' => 500.00,
                'is_active' => true,
            ]
        );

        // Demo Member 1: Active, password reset not required (direct dashboard access)
        $this->createDemoMember(
            workspaceId: 'ws-emk-dhaka',
            memberId: 'EMK-2026-00001',
            firstName: 'Tanvir',
            middleName: '',
            lastName: 'Ahmed',
            email: 'member@emk.test',
            password: 'secret123',
            cardNumber: 'CRD-EMK-202601',
            mustResetPassword: false,
            term: $term
        );

        // Demo Member 2: First-time login demo (must reset password)
        $this->createDemoMember(
            workspaceId: 'ws-emk-dhaka',
            memberId: 'EMK-2026-00002',
            firstName: 'Farhana',
            middleName: 'Akter',
            lastName: 'Rahman',
            email: 'member2@emk.test',
            password: 'secret123',
            cardNumber: 'CRD-EMK-202602',
            mustResetPassword: true,
            term: $term
        );
    }

    private function createDemoMember(
        string $workspaceId,
        string $memberId,
        string $firstName,
        string $middleName,
        string $lastName,
        string $email,
        string $password,
        string $cardNumber,
        bool $mustResetPassword,
        MembershipTerm $term
    ): void {
        $fullName = preg_replace('/\s+/', ' ', trim("{$firstName} {$middleName} {$lastName}"));
        // Ensure user account
        $user = MemberUser::withoutGlobalScopes()->where('email', $email)->first();
        if (!$user) {
            $user = MemberUser::create([
                'workspace_id' => $workspaceId,
                'name' => $fullName,
                'email' => $email,
                'password' => Hash::make($password),
                'guard' => 'member',
                'is_active' => true,
                'must_reset_password' => $mustResetPassword,
            ]);
        } else {
            $user->update([
                'workspace_id' => $workspaceId,
                'name' => $fullName,
                'password' => Hash::make($password),
                'guard' => 'member',
                'is_active' => true,
                'must_reset_password' => $mustResetPassword,
            ]);
        }

        if (!$user->hasRole('member', 'member')) {
            $user->assignRole('member');
        }

        // Lead record (conversion source)
        $lead = Lead::withoutGlobalScopes()->where('email', $email)->first();
        if (!$lead) {
            $lead = Lead::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'target_space_code' => 'EMK-DHK',
                'status' => 'converted',
                'bkash_txid' => 'TX' . strtoupper(Str::random(8)),
                'first_name' => $firstName,
                'middle_name' => $middleName ?: null,
                'last_name' => $lastName,
                'email' => $email,
                'contact_number' => '+8801711000001',
                'date_of_birth' => '1998-05-15',
                'age_at_submission' => 28,
                'sex' => 'male',
                'address_house_no' => 'House 12',
                'address_road_no' => 'Road 5',
                'address_area' => 'Dhanmondi',
                'address_zip_code' => '1209',
                'address_division' => 'Dhaka',
                'address_district' => 'Dhaka',
                'occupation' => 'Professional',
                'institution_study' => 'University of Dhaka',
                'highest_certification' => 'Masters Degree',
                'area_of_subject' => 'Computer Science',
                'submitted_at' => now()->subMonths(1),
            ]);
        }

        // Member profile
        $member = Member::withoutGlobalScopes()->where('email', $email)->first();
        if (!$member) {
            $member = Member::create([
                'uuid' => (string) Str::uuid(),
                'member_id' => $memberId,
                'workspace_id' => $workspaceId,
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'membership_term_id' => $term->id,
                'status' => 'active',
                'first_name' => $firstName,
                'middle_name' => $middleName ?: null,
                'last_name' => $lastName,
                'email' => $email,
                'contact_number' => '+8801711000001',
                'date_of_birth' => '1998-05-15',
                'sex' => 'male',
                'address_house_no' => 'House 12',
                'address_road_no' => 'Road 5',
                'address_area' => 'Dhanmondi',
                'address_zip_code' => '1209',
                'address_division' => 'Dhaka',
                'address_district' => 'Dhaka',
                'occupation' => 'Professional',
                'institution_study' => 'University of Dhaka',
                'highest_certification' => 'Masters Degree',
                'area_of_subject' => 'Computer Science',
                'start_date' => now()->subMonths(1)->toDateString(),
                'expiry_date' => now()->addMonths(11)->toDateString(),
            ]);
        } else {
            $member->update([
                'member_id' => $memberId,
                'user_id' => $user->id,
                'status' => 'active',
                'membership_term_id' => $term->id,
            ]);
        }

        // ID Card
        IdCard::firstOrCreate(
            ['member_id' => $member->id],
            [
                'workspace_id' => $workspaceId,
                'card_number' => $cardNumber,
                'status' => 'card_printed_ready_for_pickup',
                'batch_reference' => 'BATCH-2026-01',
                'printed_at' => now()->subDays(10),
            ]
        );
    }
}
