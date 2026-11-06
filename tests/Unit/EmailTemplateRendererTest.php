<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Services\EmailTemplateRenderer;
use Database\Seeders\EmailTemplateSeeder;
use PHPUnit\Framework\TestCase;

class EmailTemplateRendererTest extends TestCase
{
    public function test_interpolates_all_nine_allowlisted_placeholders_in_en_bn_and_bilingual_modes(): void
    {
        $renderer = new EmailTemplateRenderer();

        $template = new EmailTemplate([
            'subject_en' => 'Welcome {{member_name}} ({{member_id}}) to {{workspace_name}}',
            'body_en' => '<p>Applicant: {{applicant_name}}, Member: {{member_name}}, ID: {{member_id}}, Workspace: {{workspace_name}}, Expiry: {{expiry_date}}, Invoice: {{invoice_number}}, Amount: {{amount_bdt}}, Reason: {{reason}}, URL: {{action_url}}</p>',
            'subject_bn' => 'স্বাগতম {{member_name}} ({{member_id}}) — {{workspace_name}}',
            'body_bn' => '<p>আবেদনকারী: {{applicant_name}}, সদস্য: {{member_name}}, আইডি: {{member_id}}, কেন্দ্র: {{workspace_name}}, মেয়াদ: {{expiry_date}}, ইনভয়েস: {{invoice_number}}, টাকা: {{amount_bdt}}, কারণ: {{reason}}, লিংক: {{action_url}}</p>',
            'allowed_variables' => EmailTemplateRenderer::ALLOWED_PLACEHOLDERS,
            'is_active' => true,
        ]);

        $variables = [
            'applicant_name' => 'Tanvir Ahmed',
            'member_name' => 'Tanvir Ahmed',
            'member_id' => 'EMK-2026-00099',
            'workspace_name' => 'EMK Center Dhaka',
            'expiry_date' => '31 Dec 2027',
            'invoice_number' => 'INV-2026-00099',
            'amount_bdt' => '1000.00',
            'reason' => 'All verifications passed',
            'action_url' => 'https://hub.emkcenter.org/portal',
        ];

        $enResult = $renderer->render($template, $variables, 'en');
        $this->assertSame('Welcome Tanvir Ahmed (EMK-2026-00099) to EMK Center Dhaka', $enResult['subject']);
        $this->assertStringContainsString('Applicant: Tanvir Ahmed, Member: Tanvir Ahmed, ID: EMK-2026-00099, Workspace: EMK Center Dhaka, Expiry: 31 Dec 2027, Invoice: INV-2026-00099, Amount: 1000.00, Reason: All verifications passed, URL: https://hub.emkcenter.org/portal', $enResult['body_html']);

        $bnResult = $renderer->render($template, $variables, 'bn');
        $this->assertSame('স্বাগতম Tanvir Ahmed (EMK-2026-00099) — EMK Center Dhaka', $bnResult['subject']);
        $this->assertStringContainsString('আবেদনকারী: Tanvir Ahmed, সদস্য: Tanvir Ahmed, আইডি: EMK-2026-00099', $bnResult['body_html']);

        $bilingualResult = $renderer->render($template, $variables, 'bilingual');
        $this->assertStringContainsString('Welcome Tanvir Ahmed (EMK-2026-00099) to EMK Center Dhaka | স্বাগতম Tanvir Ahmed (EMK-2026-00099) — EMK Center Dhaka', $bilingualResult['subject']);
        $this->assertStringContainsString('Applicant: Tanvir Ahmed', $bilingualResult['body_html']);
        $this->assertStringContainsString('<hr', $bilingualResult['body_html']);
        $this->assertStringContainsString('আবেদনকারী: Tanvir Ahmed', $bilingualResult['body_html']);
    }

    public function test_escapes_html_and_xss_payloads_in_variable_values(): void
    {
        $renderer = new EmailTemplateRenderer();

        $template = new EmailTemplate([
            'subject_en' => 'Application for {{applicant_name}}',
            'body_en' => '<p>Hello {{applicant_name}}, reason: {{reason}}</p>',
            'allowed_variables' => ['applicant_name', 'reason'],
        ]);

        $result = $renderer->render($template, [
            'applicant_name' => '<script>alert("xss")</script>',
            'reason' => '<img src=x onerror="alert(1)">',
        ], 'en');

        $this->assertStringNotContainsString('<script>', $result['body_html']);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result['body_html']);
        $this->assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $result['body_html']);
    }

    public function test_never_evaluates_non_allowlisted_placeholders_or_dynamic_expressions(): void
    {
        $renderer = new EmailTemplateRenderer();

        $template = new EmailTemplate([
            'subject_en' => 'Notice {{applicant_name}} {{system_secret}}',
            'body_en' => '<p>Hi {{applicant_name}}, secret: {{system_secret}}, expr: {{7*7}}, missing_allowed: {{reason}}</p>',
            'allowed_variables' => ['applicant_name', 'reason'],
        ]);

        $result = $renderer->render($template, [
            'applicant_name' => 'Rahim',
            'system_secret' => 'LEAKED_SECRET_VALUE',
            '7*7' => '49',
        ], 'en');

        $this->assertStringNotContainsString('LEAKED_SECRET_VALUE', $result['body_html']);
        $this->assertStringContainsString('{{system_secret}}', $result['body_html']);
        $this->assertStringContainsString('{{7*7}}', $result['body_html']);
        // Unsupplied allowlisted placeholder {{reason}} is cleanly stripped
        $this->assertStringNotContainsString('{{reason}}', $result['body_html']);
    }

    public function test_canonical_seeder_defines_all_eleven_bilingual_milestone_templates(): void
    {
        $templates = EmailTemplateSeeder::canonicalTemplates();

        $this->assertCount(11, $templates);

        $keys = array_column($templates, 'template_key');
        $this->assertSame(EmailTemplate::CANONICAL_MILESTONES, $keys);

        foreach ($templates as $tpl) {
            $this->assertNotEmpty($tpl['subject_en']);
            $this->assertNotEmpty($tpl['body_en']);
            $this->assertNotEmpty($tpl['subject_bn']);
            $this->assertNotEmpty($tpl['body_bn']);
            $this->assertNotEmpty($tpl['allowed_variables']);
        }
    }
}
