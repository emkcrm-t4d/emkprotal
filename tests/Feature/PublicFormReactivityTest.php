<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\Form;
use App\Livewire\Forms\PublicMembershipForm;
use Carbon\Carbon;
use Database\Seeders\DefaultMembershipFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PublicFormReactivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('spaces')->insert([
            ['id' => 1, 'code' => 'EMK-DHK', 'name' => 'EMK Center Dhaka', 'category' => 'Center', 'division' => 'Dhaka', 'district' => 'Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('workspaces')->insert([
            ['id' => 'ws-emk-dhaka', 'space_id' => 1, 'name' => 'EMK Center Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->seed(DefaultMembershipFormSeeder::class);
    }

    public function test_updating_division_dynamically_repopulates_available_districts(): void
    {
        $component = Livewire::test(PublicMembershipForm::class);

        // Selecting 'barishal' must populate exactly 6 districts
        $component->set('address_division', 'barishal');
        $barishalDistricts = $component->get('available_districts');
        $this->assertCount(6, $barishalDistricts);
        $this->assertContains('barguna', $barishalDistricts);
        $this->assertContains('barishal', $barishalDistricts);
        $this->assertContains('bhola', $barishalDistricts);
        $this->assertContains('jhalokati', $barishalDistricts);
        $this->assertContains('patuakhali', $barishalDistricts);
        $this->assertContains('pirojpur', $barishalDistricts);

        // Selecting 'dhaka' must populate exactly 13 districts
        $component->set('address_division', 'dhaka');
        $dhakaDistricts = $component->get('available_districts');
        $this->assertCount(13, $dhakaDistricts);
        $this->assertContains('dhaka', $dhakaDistricts);
        $this->assertContains('gazipur', $dhakaDistricts);
        $this->assertContains('narayanganj', $dhakaDistricts);
        $this->assertContains('tangail', $dhakaDistricts);

        // Test other divisions match Section 4 dictionary partition
        $component->set('address_division', 'chattogram');
        $this->assertCount(11, $component->get('available_districts'));

        $component->set('address_division', 'khulna');
        $this->assertCount(10, $component->get('available_districts'));

        $component->set('address_division', 'mymensingh');
        $this->assertCount(4, $component->get('available_districts'));

        $component->set('address_division', 'rajshahi');
        $this->assertCount(8, $component->get('available_districts'));

        $component->set('address_division', 'rangpur');
        $this->assertCount(8, $component->get('available_districts'));

        $component->set('address_division', 'sylhet');
        $this->assertCount(4, $component->get('available_districts'));
    }

    public function test_invalid_district_is_cleared_when_division_changes(): void
    {
        $component = Livewire::test(PublicMembershipForm::class)
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka');

        $this->assertSame('dhaka', $component->get('address_district'));

        // Changing division to Barishal should clear the Dhaka district because it is not in Barishal
        $component->set('address_division', 'barishal');
        $this->assertNull($component->get('address_district'));

        // Setting a valid district in Barishal
        $component->set('address_district', 'bhola');
        $this->assertSame('bhola', $component->get('address_district'));

        // Switching to Sylhet clears Bhola
        $component->set('address_division', 'sylhet');
        $this->assertNull($component->get('address_district'));
    }

    public function test_dob_with_age_under_18_triggers_requires_consent_true(): void
    {
        $component = Livewire::test(PublicMembershipForm::class);

        // Age 16
        $dob16 = Carbon::now()->subYears(16)->format('Y-m-d');
        $component->set('date_of_birth', $dob16);
        $this->assertTrue($component->get('requires_consent'));
        $this->assertSame(16, $component->get('age_years'));

        // Age 17
        $dob17 = Carbon::now()->subYears(17)->format('Y-m-d');
        $component->set('date_of_birth', $dob17);
        $this->assertTrue($component->get('requires_consent'));
        $this->assertSame(17, $component->get('age_years'));
    }

    public function test_dob_with_age_18_or_older_sets_requires_consent_false_and_clears_consent_file(): void
    {
        $component = Livewire::test(PublicMembershipForm::class);

        // Set under-18 first with dummy consent file
        $dob16 = Carbon::now()->subYears(16)->format('Y-m-d');
        $component->set('date_of_birth', $dob16);
        $component->set('parental_consent_file', 'dummy_consent_file.pdf');
        $this->assertTrue($component->get('requires_consent'));
        $this->assertNotNull($component->get('parental_consent_file'));

        // Now set age 18: requires_consent must be false and consent file cleared
        $dob18 = Carbon::now()->subYears(18)->format('Y-m-d');
        $component->set('date_of_birth', $dob18);
        $this->assertFalse($component->get('requires_consent'));
        $this->assertSame(18, $component->get('age_years'));
        $this->assertNull($component->get('parental_consent_file'));

        // Set age 25
        $dob25 = Carbon::now()->subYears(25)->format('Y-m-d');
        $component->set('date_of_birth', $dob25);
        $this->assertFalse($component->get('requires_consent'));
        $this->assertSame(25, $component->get('age_years'));
        $this->assertNull($component->get('parental_consent_file'));

        // Set age 40
        $dob40 = Carbon::now()->subYears(40)->format('Y-m-d');
        $component->set('date_of_birth', $dob40);
        $this->assertFalse($component->get('requires_consent'));
        $this->assertSame(40, $component->get('age_years'));
        $this->assertNull($component->get('parental_consent_file'));
    }

    public function test_selecting_other_occupation_triggers_requirement_for_occupation_other(): void
    {
        $component = Livewire::test(PublicMembershipForm::class);

        // When occupation is student, occupation_other is optional
        $component->set('occupation', 'student');
        $component->set('occupation_other', '');
        $component->assertHasNoErrors(['occupation_other']);

        // When occupation is other, occupation_other is required
        $component->set('occupation', 'other');
        $component->set('occupation_other', '');
        $component->assertHasErrors(['occupation_other']);

        // Providing occupation_other resolves the error
        $component->set('occupation_other', 'Freelance Journalist');
        $component->assertHasNoErrors(['occupation_other']);

        // Switching back to student clears occupation_other
        $component->set('occupation', 'student');
        $this->assertSame('', $component->get('occupation_other'));
        $component->assertHasNoErrors(['occupation_other']);
    }

    public function test_selecting_other_highest_certification_triggers_requirement_for_child_input(): void
    {
        $component = Livewire::test(PublicMembershipForm::class);

        // When certification is undergraduate_degree, other input is optional
        $component->set('highest_certification', 'undergraduate_degree');
        $component->set('highest_certification_other', '');
        $component->assertHasNoErrors(['highest_certification_other']);

        // When certification is other, other input is required
        $component->set('highest_certification', 'other');
        $component->set('highest_certification_other', '');
        $component->assertHasErrors(['highest_certification_other']);

        // Providing value resolves error
        $component->set('highest_certification_other', 'Higher National Diploma');
        $component->assertHasNoErrors(['highest_certification_other']);

        // Switching back clears the child field
        $component->set('highest_certification', 'hsc');
        $this->assertSame('', $component->get('highest_certification_other'));
        $component->assertHasNoErrors(['highest_certification_other']);
    }

    public function test_selecting_has_us_experience_triggers_requirement_for_us_experience_location(): void
    {
        $component = Livewire::test(PublicMembershipForm::class);

        // When 0, location is optional
        $component->set('has_us_experience', '0');
        $component->set('us_experience_location', '');
        $component->assertHasNoErrors(['us_experience_location']);

        // When 1, location is required
        $component->set('has_us_experience', '1');
        $component->set('us_experience_location', '');
        $component->assertHasErrors(['us_experience_location']);

        // Providing location passes validation
        $component->set('us_experience_location', 'Harvard University, Boston MA');
        $component->assertHasNoErrors(['us_experience_location']);

        // Switching to 0 clears location
        $component->set('has_us_experience', '0');
        $this->assertSame('', $component->get('us_experience_location'));
        $component->assertHasNoErrors(['us_experience_location']);
    }

    public function test_selecting_is_american_alumni_triggers_requirement_for_institution_name(): void
    {
        $component = Livewire::test(PublicMembershipForm::class);

        // When 0, institution name is optional
        $component->set('is_american_alumni', '0');
        $component->set('american_institution_name', '');
        $component->assertHasNoErrors(['american_institution_name']);

        // When 1, institution name is required
        $component->set('is_american_alumni', '1');
        $component->set('american_institution_name', '');
        $component->assertHasErrors(['american_institution_name']);

        // Providing institution name passes validation
        $component->set('american_institution_name', 'University of California, Berkeley');
        $component->assertHasNoErrors(['american_institution_name']);

        // Switching to 0 clears institution name
        $component->set('is_american_alumni', '0');
        $this->assertSame('', $component->get('american_institution_name'));
        $component->assertHasNoErrors(['american_institution_name']);
    }

    public function test_bkash_txid_validation_passes_on_valid_10_char_uppercase_and_fails_otherwise(): void
    {
        $component = Livewire::test(PublicMembershipForm::class);

        // Valid 10-character uppercase alphanumeric string per DEC-05
        $component->set('bkash_txid', 'BLK7X9Q12M');
        $component->assertHasNoErrors(['bkash_txid']);

        // Another valid 10-char alphanumeric string
        $component->set('bkash_txid', '9A8B7C6D5E');
        $component->assertHasNoErrors(['bkash_txid']);

        // Fails on 9 characters (too short)
        $component->set('bkash_txid', 'BLK7X9Q12');
        $component->assertHasErrors(['bkash_txid']);

        // Fails on 11 characters (too long)
        $component->set('bkash_txid', 'BLK7X9Q12M1');
        $component->assertHasErrors(['bkash_txid']);

        // Fails on lowercase characters
        $component->set('bkash_txid', 'blk7x9q12m');
        $component->assertHasErrors(['bkash_txid']);

        // Fails on special characters
        $component->set('bkash_txid', 'BLK7X9-12M');
        $component->assertHasErrors(['bkash_txid']);

        $component->set('bkash_txid', 'BLK7X9!@#$');
        $component->assertHasErrors(['bkash_txid']);
    }
}
