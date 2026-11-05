<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Members\Models\Member;
use App\Livewire\Forms\PublicMembershipForm;
use App\Livewire\Portal\MemberPortalProfile;
use App\Livewire\Portal\PortalFirstPasswordReset;
use App\Models\MemberUser;
use Database\Seeders\DefaultMembershipFormSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Tests\TestCase;

class AccessibilityAndResponsiveLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(DefaultMembershipFormSeeder::class);
    }

    /**
     * Helper to provision a Member fixture with linked records.
     */
    protected function createMemberFixture(): Member
    {
        $term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'REG-1YR'],
            [
                'name' => 'General Membership (1 Year)',
                'duration_months' => 12,
                'fee_bdt' => 500.00,
                'is_active' => true,
            ]
        );

        $memberUser = MemberUser::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Amina Chowdhury',
            'email' => 'amina.accessibility@emk.test',
            'password' => Hash::make('Password123!'),
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $memberUser->assignRole('member');

        $lead = Lead::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXID' . strtoupper(\Illuminate\Support\Str::random(6)),
            'first_name' => 'Amina',
            'last_name' => 'Chowdhury',
            'email' => 'amina.accessibility@emk.test',
            'contact_number' => '+8801712345678',
            'date_of_birth' => '1998-05-15',
            'age_at_submission' => 28,
            'sex' => 'female',
            'address_house_no' => 'House 42',
            'address_area' => 'Dhanmondi',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'address_zip_code' => '1209',
            'occupation' => 'student',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Computer Science',
            'submitted_at' => now(),
        ]);

        LeadAttachment::create([
            'lead_id' => $lead->id,
            'document_type' => 'identity_document',
            'original_filename' => 'nid_card_front.pdf',
            'storage_path' => 'leads/nid_card_front.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024 * 350,
            'file_hash' => hash('sha256', 'mock_nid'),
            'youth_protection' => false,
        ]);

        $member = Member::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $memberUser->id,
            'membership_term_id' => $term->id,
            'member_id' => 'EMK-2026-A11Y',
            'first_name' => 'Amina',
            'last_name' => 'Chowdhury',
            'email' => 'amina.accessibility@emk.test',
            'contact_number' => '+8801712345678',
            'date_of_birth' => '1998-05-15',
            'sex' => 'female',
            'address_house_no' => 'House 42',
            'address_area' => 'Dhanmondi',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'address_zip_code' => '1209',
            'occupation' => 'student',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Computer Science',
            'status' => 'active',
            'start_date' => now()->subMonths(1),
            'expiry_date' => now()->addMonths(11),
        ]);

        IdCard::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $member->id,
            'card_number' => 'CARD-A11Y-001',
            'status' => 'card_printed_ready_for_pickup',
        ]);

        return $member;
    }

    /**
     * WCAG 2.1 AA Landmark & Skip-Link Verification for Public Layout
     */
    public function test_public_layout_contains_skip_navigation_and_aria_landmarks(): void
    {
        $view = $this->view('forms.public-layout', [
            'slot' => new HtmlString('<div id="test-form">Public Form Body</div>'),
        ]);

        // Skip navigation link targeting main content
        $view->assertSee('href="#main-content"', false);
        $view->assertSee('Skip to main content');

        // Required ARIA Landmark Roles
        $view->assertSee('role="banner"', false);
        $view->assertSee('id="main-content"', false);
        $view->assertSee('role="main"', false);
        $view->assertSee('role="contentinfo"', false);

        // HTML language attribute
        $view->assertSee('lang="en"', false);

        // Responsive viewport meta tag
        $view->assertSee('name="viewport" content="width=device-width, initial-scale=1"', false);
    }

    /**
     * WCAG 2.1 AA Landmark & Skip-Link Verification for Member Portal Layout
     */
    public function test_portal_layout_contains_skip_navigation_and_aria_landmarks(): void
    {
        $view = $this->view('layouts.portal', [
            'slot' => new HtmlString('<div id="test-portal">Member Portal Body</div>'),
        ]);

        // Skip navigation link targeting main content
        $view->assertSee('href="#main-content"', false);
        $view->assertSee('Skip to main content');

        // ARIA Landmark Roles
        $view->assertSee('role="banner"', false);
        $view->assertSee('id="main-content"', false);
        $view->assertSee('role="main"', false);
        $view->assertSee('role="contentinfo"', false);

        // Notification container with live region
        $view->assertSee('id="notification-container"', false);
        $view->assertSee('aria-live="polite"', false);
        $view->assertSee('role="region"', false);

        // HTML language attribute and responsive viewport
        $view->assertSee('lang="en"', false);
        $view->assertSee('name="viewport" content="width=device-width, initial-scale=1"', false);
    }

    /**
     * Programmatic Label Association for Public Membership Form Controls (RELS-03)
     */
    public function test_public_membership_form_all_form_controls_have_explicit_labels(): void
    {
        $response = $this->get('/apply/membership-application');
        $response->assertStatus(200);

        $html = $response->getContent();
        $this->assertNotEmpty($html);

        // Expected form controls with explicit id and matching <label for="...">
        $formFields = [
            'first_name',
            'middle_name',
            'last_name',
            'american_space',
            'sex',
            'date_of_birth',
            'parental_consent_file',
            'address_house_no',
            'address_road_no',
            'address_area',
            'address_zip_code',
            'address_division',
            'address_district',
            'contact_number',
            'email',
            'website',
            'occupation',
            'occupation_other',
            'institution_study',
            'highest_certification',
            'highest_certification_other',
            'area_of_subject',
            'has_us_experience_yes',
            'has_us_experience_no',
            'us_experience_location',
            'is_american_alumni_yes',
            'is_american_alumni_no',
            'american_institution_name',
            'employer_name',
            'job_title',
            'work_phone',
            'identity_document_files',
            'photo_file',
            'bkash_txid',
            'terms_agreed',
        ];

        foreach ($formFields as $fieldId) {
            $this->assertStringContainsString(
                'for="' . $fieldId . '"',
                $html,
                "Failed asserting that <label for=\"{$fieldId}\"> exists in public form HTML."
            );
            $this->assertStringContainsString(
                'id="' . $fieldId . '"',
                $html,
                "Failed asserting that element id=\"{$fieldId}\" exists in public form HTML."
            );
        }
    }

    /**
     * WCAG 2.1 AA: Required attributes and ARIA attributes on mandatory inputs
     */
    public function test_public_membership_form_required_fields_have_aria_required(): void
    {
        $response = $this->get('/apply/membership-application');
        $response->assertStatus(200);
        $html = $response->getContent();

        $requiredFields = [
            'first_name',
            'last_name',
            'american_space',
            'sex',
            'date_of_birth',
            'address_house_no',
            'address_area',
            'address_zip_code',
            'address_division',
            'contact_number',
            'email',
            'occupation',
            'institution_study',
            'highest_certification',
            'area_of_subject',
            'bkash_txid',
            'terms_agreed',
        ];

        foreach ($requiredFields as $field) {
            $pattern = '/id="' . preg_quote($field, '/') . '"[^>]*aria-required="true"/';
            $altPattern = '/aria-required="true"[^>]*id="' . preg_quote($field, '/') . '"/';
            $matches = preg_match($pattern, $html) || preg_match($altPattern, $html);

            $this->assertTrue(
                (bool) $matches,
                "Failed asserting that field '{$field}' has aria-required=\"true\"."
            );
        }
    }

    /**
     * Screen-Reader Live Regions for Dynamic Calculations & Consent Notices
     */
    public function test_dynamic_calculations_and_notices_have_aria_live_regions(): void
    {
        $response = $this->get('/apply/membership-application');
        $response->assertStatus(200);
        $html = $response->getContent();

        // Dynamic minor consent notice container must have role="region" and aria-live="polite"
        $this->assertStringContainsString('id="minor-consent-section"', $html);
        $this->assertStringContainsString('role="region"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('aria-label="Age and consent notice"', $html);

        // Age calculation display live region
        $this->assertStringContainsString('id="age-display"', $html);

        // bKash instruction card
        $this->assertStringContainsString('aria-label="bKash payment instructions"', $html);
    }

    /**
     * Member Portal Profile ARIA Roles and Document Table Accessibility
     */
    public function test_member_portal_profile_status_badges_and_document_table(): void
    {
        $member = $this->createMemberFixture();
        $memberUser = MemberUser::findOrFail($member->user_id);

        Auth::guard('member')->login($memberUser);

        Livewire::test(MemberPortalProfile::class, ['member' => $member])
            ->set('activeTab', 'documents')
            ->assertStatus(200)
            ->assertSee('role="status"', false)
            ->assertSee('Membership Status: Active Member')
            ->assertSee('Physical ID Card Status: Ready for Pickup at Front Desk')
            ->assertSee('aria-label="Member Uploaded Documents Table"', false)
            ->assertSee('scope="col"', false)
            ->assertSee('overflow-x-auto', false)
            ->assertSee('Download');
    }

    /**
     * Portal First Password Reset Accessibility & Rules Association
     */
    public function test_portal_first_password_reset_has_explicit_labels_and_rules_linkage(): void
    {
        $member = $this->createMemberFixture();
        $memberUser = MemberUser::findOrFail($member->user_id);
        $memberUser->update(['must_reset_password' => true]);

        Auth::guard('member')->login($memberUser);

        Livewire::test(PortalFirstPasswordReset::class)
            ->assertStatus(200)
            ->assertSee('for="password"', false)
            ->assertSee('id="password"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('for="password_confirmation"', false)
            ->assertSee('id="password_confirmation"', false)
            ->assertSee('aria-describedby="password-rules', false)
            ->assertSee('id="password-rules"', false)
            ->assertSee('role="region"', false)
            ->assertSee('aria-label="Password Requirements"', false)
            ->assertSee('aria-label="Save Password and Access Portal"', false);
    }

    /**
     * Mobile Navigation Drawer ARIA States, Controls & Keyboard Escape
     */
    public function test_portal_mobile_navigation_drawer_attributes_and_escape_listener(): void
    {
        $view = $this->view('layouts.portal', [
            'slot' => new HtmlString('<div>Portal Content</div>'),
        ]);

        // Mobile drawer hamburger button with aria-expanded and aria-controls
        $view->assertSee('aria-controls="mobile-navigation-menu"', false);
        $view->assertSee(':aria-expanded="mobileMenuOpen.toString()"', false);
        $view->assertSee('aria-label="Toggle mobile navigation menu"', false);

        // Mobile drawer dialog container
        $view->assertSee('id="mobile-navigation-menu"', false);
        $view->assertSee('role="dialog"', false);
        $view->assertSee('aria-modal="true"', false);
        $view->assertSee('aria-label="Mobile Navigation Drawer"', false);

        // Keyboard Escape dismissal listener
        $view->assertSee('@keydown.escape.window="mobileMenuOpen = false"', false);
        $view->assertSee('@keydown.escape="mobileMenuOpen = false"', false);
    }

    /**
     * Touch Target Sizes on Mobile Viewports (Minimum 44x44px per RELS-03)
     */
    public function test_interactive_elements_meet_minimum_touch_target_dimensions(): void
    {
        $response = $this->get('/apply/membership-application');
        $response->assertStatus(200);
        $html = $response->getContent();

        // Download consent button touch target
        $this->assertStringContainsString('min-h-[44px]', $html);

        // Form submit button touch target
        $pattern = '/<button[^>]*type="submit"[^>]*min-h-\[44px\]/';
        $this->assertMatchesRegularExpression($pattern, $html);

        // Portal layout action buttons
        $portalView = $this->view('layouts.portal', [
            'slot' => new HtmlString('<div>Content</div>'),
        ]);
        $portalView->assertSee('min-h-[44px]', false);
        $portalView->assertSee('min-w-[44px]', false);
    }

    /**
     * Color Contrast Ratios (WCAG 2.1 AA requires >= 4.5:1 for normal text)
     */
    public function test_high_contrast_color_tokens_applied_across_templates(): void
    {
        $response = $this->get('/apply/membership-application');
        $html = $response->getContent();

        // Avoid low-contrast text classes on light backgrounds
        $this->assertStringNotContainsString('text-slate-300', $html);
        $this->assertStringNotContainsString('text-gray-300', $html);

        // Validate high contrast color pairs (e.g., slate-900 on white = 15.6:1, indigo-700 on white = 7.1:1)
        $this->assertStringContainsString('text-slate-900', $html);
        $this->assertStringContainsString('text-slate-700', $html);
        $this->assertStringContainsString('bg-indigo-600', $html);
    }
}
