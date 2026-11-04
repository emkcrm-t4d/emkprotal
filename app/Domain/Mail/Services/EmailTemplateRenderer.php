<?php

declare(strict_types=1);

namespace App\Domain\Mail\Services;

use App\Domain\Mail\Models\EmailTemplate;

class EmailTemplateRenderer
{
    /**
     * Strictly allowlisted placeholders per DEC-02 and T-08-04.
     *
     * @var list<string>
     */
    public const ALLOWED_PLACEHOLDERS = [
        'applicant_name',
        'member_name',
        'member_id',
        'workspace_name',
        'expiry_date',
        'invoice_number',
        'amount_bdt',
        'reason',
        'action_url',
    ];

    /**
     * Safely render an EmailTemplate in 'en', 'bn', or 'bilingual' mode
     * using deterministic string replacement (strtr) and HTML escaping.
     * Strictly zero eval() or dynamic Blade compilation on database strings.
     *
     * @param  array<string, scalar|null>  $variables
     * @return array{subject: string, body_html: string, body_text: string}
     */
    public function render(EmailTemplate $template, array $variables = [], string $locale = 'en'): array
    {
        $allowedKeys = self::ALLOWED_PLACEHOLDERS;

        if (is_array($template->allowed_variables) && count($template->allowed_variables) > 0) {
            $intersected = array_values(array_intersect(self::ALLOWED_PLACEHOLDERS, $template->allowed_variables));
            if (count($intersected) > 0) {
                $allowedKeys = $intersected;
            }
        }

        // Build plain-text replacement map (for subject and body_text)
        // and HTML-escaped replacement map (for body_html) across all ALLOWED_PLACEHOLDERS
        $plainMap = [];
        $htmlMap = [];

        foreach (self::ALLOWED_PLACEHOLDERS as $key) {
            $token = '{{' . $key . '}}';
            $tokenSpaced = '{{ ' . $key . ' }}';

            if (in_array($key, $allowedKeys, true) && array_key_exists($key, $variables) && $variables[$key] !== null) {
                $rawString = (string) $variables[$key];
                $escapedString = htmlspecialchars($rawString, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $plainMap[$token] = $rawString;
                $plainMap[$tokenSpaced] = $rawString;
                $htmlMap[$token] = $escapedString;
                $htmlMap[$tokenSpaced] = $escapedString;
            } else {
                // Strip unreplaced allowlisted placeholders when no value was supplied
                $plainMap[$token] = '';
                $plainMap[$tokenSpaced] = '';
                $htmlMap[$token] = '';
                $htmlMap[$tokenSpaced] = '';
            }
        }

        [$rawSubject, $rawBody] = $this->resolveLocaleTemplate($template, $locale);

        $subject = trim(strtr($rawSubject, $plainMap));
        $bodyHtml = strtr($rawBody, $htmlMap);
        $bodyText = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '<hr>', '<hr/>', '<hr />'], "\n", strtr($rawBody, $plainMap))));

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Render a live preview using sample EMK Center Dhaka variables.
     *
     * @return array{subject: string, body_html: string, body_text: string}
     */
    public function renderPreview(EmailTemplate $template, string $locale = 'en'): array
    {
        return $this->render($template, $this->sampleVariables(), $locale);
    }

    /**
     * Provide realistic EMK Center Dhaka sample values for all 9 allowlisted placeholders.
     *
     * @return array<string, string>
     */
    public function sampleVariables(): array
    {
        return [
            'applicant_name' => 'Nusrat Jahan',
            'member_name' => 'Nusrat Jahan',
            'member_id' => 'EMK-2026-00042',
            'workspace_name' => 'EMK Center Dhaka',
            'expiry_date' => '04 Nov 2027',
            'invoice_number' => 'INV-EMK-2026-00042',
            'amount_bdt' => '500.00',
            'reason' => 'National ID scan is blurry; please upload a clear high-resolution copy.',
            'action_url' => 'https://hub.emkcenter.org/portal/login',
        ];
    }

    /**
     * Resolve raw subject and body for 'en', 'bn', or 'bilingual' modes.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveLocaleTemplate(EmailTemplate $template, string $locale): array
    {
        $subjectEn = (string) $template->subject_en;
        $bodyEn = (string) $template->body_en;
        $subjectBn = trim((string) ($template->subject_bn ?? ''));
        $bodyBn = trim((string) ($template->body_bn ?? ''));

        if ($locale === 'bn') {
            return [
                $subjectBn !== '' ? $subjectBn : $subjectEn,
                $bodyBn !== '' ? $bodyBn : $bodyEn,
            ];
        }

        if ($locale === 'bilingual') {
            $combinedSubject = $subjectBn !== ''
                ? $subjectEn . ' | ' . $subjectBn
                : $subjectEn;

            $combinedBody = $bodyBn !== ''
                ? $bodyEn . "\n<hr style=\"margin: 20px 0; border: 0; border-top: 1px solid #cbd5e1;\">\n" . $bodyBn
                : $bodyEn;

            return [$combinedSubject, $combinedBody];
        }

        return [$subjectEn, $bodyEn];
    }
}
