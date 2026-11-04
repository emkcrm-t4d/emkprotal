<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Leads\Models\Lead;
use App\Livewire\Leads\LeadDirectory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LeadDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected Lead $leadA;
    protected Lead $leadB;
    protected Lead $leadC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Admissions Officer',
            'email' => 'officer.dir@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        // Create 3 diverse leads in ws-emk-dhaka
        $this->leadA = Lead::create([
            'uuid' => Str::uuid()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDAAAA01',
            'first_name' => 'Karim',
            'last_name' => 'Uddin',
            'email' => 'karim.test@example.com',
            'contact_number' => '+8801711111111',
            'date_of_birth' => '1998-01-10',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => 'House 1',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Physics',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'submitted_at' => now()->subDays(10),
        ]);

        $this->leadB = Lead::create([
            'uuid' => Str::uuid()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'approved',
            'bkash_txid' => 'TXIDBBBB02',
            'first_name' => 'Ayesha',
            'last_name' => 'Sultana',
            'email' => 'ayesha.test@example.com',
            'contact_number' => '+8801722222222',
            'date_of_birth' => '2000-05-15',
            'age_at_submission' => 26,
            'sex' => 'female',
            'address_house_no' => 'House 2',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'educator',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'graduate',
            'area_of_subject' => 'Literature',
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'pending',
            'submitted_at' => now()->subDays(5),
        ]);

        $this->leadC = Lead::create([
            'uuid' => Str::uuid()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXIDCCCC03',
            'first_name' => 'Jamal',
            'last_name' => 'Khan',
            'email' => 'jamal.test@example.com',
            'contact_number' => '+8801733333333',
            'date_of_birth' => '1995-12-20',
            'age_at_submission' => 31,
            'sex' => 'male',
            'address_house_no' => 'House 3',
            'address_area' => 'Uttara',
            'address_zip_code' => '1230',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'entrepreneur',
            'institution_study' => 'NSU',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Business',
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'verified',
            'submitted_at' => now()->subDay(),
        ]);
    }

    public function test_search_filtering_by_name_email_contact_and_bkash_txid(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(LeadDirectory::class, ['workspace' => 'ws-emk-dhaka']);

        // Initially all 3 leads are visible
        $component->assertSee('Karim Uddin');
        $component->assertSee('Ayesha Sultana');
        $component->assertSee('Jamal Khan');

        // 1. Search by name (Karim)
        $component->set('search', 'Karim');
        $component->assertSee('Karim Uddin');
        $component->assertDontSee('Ayesha Sultana');
        $component->assertDontSee('Jamal Khan');

        // 2. Search by email (ayesha.test@example.com)
        $component->set('search', 'ayesha.test@example.com');
        $component->assertSee('Ayesha Sultana');
        $component->assertDontSee('Karim Uddin');
        $component->assertDontSee('Jamal Khan');

        // 3. Search by contact number (+8801733333333)
        $component->set('search', '+8801733333333');
        $component->assertSee('Jamal Khan');
        $component->assertDontSee('Karim Uddin');
        $component->assertDontSee('Ayesha Sultana');

        // 4. Search by bKash TxID (TXIDAAAA01)
        $component->set('search', 'TXIDAAAA01');
        $component->assertSee('Karim Uddin');
        $component->assertDontSee('Ayesha Sultana');
        $component->assertDontSee('Jamal Khan');
    }

    public function test_status_filtering_by_lead_status(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(LeadDirectory::class, ['workspace' => 'ws-emk-dhaka']);

        // Filter by 'approved'
        $component->set('statusFilter', 'approved');
        $component->assertSee('Ayesha Sultana');
        $component->assertDontSee('Karim Uddin');
        $component->assertDontSee('Jamal Khan');

        // Filter by 'converted'
        $component->set('statusFilter', 'converted');
        $component->assertSee('Jamal Khan');
        $component->assertDontSee('Karim Uddin');
        $component->assertDontSee('Ayesha Sultana');

        // Filter by 'submitted'
        $component->set('statusFilter', 'submitted');
        $component->assertSee('Karim Uddin');
        $component->assertDontSee('Ayesha Sultana');
        $component->assertDontSee('Jamal Khan');
    }

    public function test_independent_verification_stream_filtering(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(LeadDirectory::class, ['workspace' => 'ws-emk-dhaka']);

        // 1. Filter Payment = 'verified' (Lead A)
        $component->set('paymentFilter', 'verified');
        $component->assertSee('Karim Uddin');
        $component->assertDontSee('Ayesha Sultana');
        $component->assertDontSee('Jamal Khan');

        // 2. Filter Document = 'verified' (Lead B)
        $component->set('paymentFilter', '');
        $component->set('documentFilter', 'verified');
        $component->assertSee('Ayesha Sultana');
        $component->assertDontSee('Karim Uddin');
        $component->assertDontSee('Jamal Khan');

        // 3. Filter Photo = 'verified' (Lead C)
        $component->set('documentFilter', '');
        $component->set('photoFilter', 'verified');
        $component->assertSee('Jamal Khan');
        $component->assertDontSee('Karim Uddin');
        $component->assertDontSee('Ayesha Sultana');
    }

    public function test_date_range_filtering_on_submitted_at(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(LeadDirectory::class, ['workspace' => 'ws-emk-dhaka']);

        // Target range around Lead B (submitted 5 days ago)
        $from = now()->subDays(7)->format('Y-m-d');
        $to = now()->subDays(3)->format('Y-m-d');

        $component->set('dateFrom', $from);
        $component->set('dateTo', $to);

        $component->assertSee('Ayesha Sultana');
        $component->assertDontSee('Karim Uddin'); // submitted 10 days ago
        $component->assertDontSee('Jamal Khan');   // submitted 1 day ago
    }

    public function test_sorting_by_date_name_and_status(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(LeadDirectory::class, ['workspace' => 'ws-emk-dhaka']);

        // Sort by first_name asc
        $component->call('sortBy', 'first_name');
        $component->assertSet('sortField', 'first_name');
        $component->assertSet('sortDirection', 'asc');

        // Toggle first_name desc
        $component->call('sortBy', 'first_name');
        $component->assertSet('sortDirection', 'desc');

        // Sort by status
        $component->call('sortBy', 'status');
        $component->assertSet('sortField', 'status');
        $component->assertSet('sortDirection', 'asc');

        // Reset all filters
        $component->set('search', 'Karim');
        $component->set('statusFilter', 'submitted');
        $component->call('resetFilters');

        $component->assertSet('search', '');
        $component->assertSet('statusFilter', '');
        $component->assertSet('sortField', 'submitted_at');
        $component->assertSet('sortDirection', 'desc');
    }
}
