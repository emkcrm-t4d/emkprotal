<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds for all workspaces.
     */
    public function run(): void
    {
        $workspaceIds = Workspace::query()->pluck('id')->all();

        if (! in_array('ws-emk-dhaka', $workspaceIds, true) && Workspace::query()->where('id', 'ws-emk-dhaka')->exists()) {
            $workspaceIds[] = 'ws-emk-dhaka';
        }

        foreach ($workspaceIds as $workspaceId) {
            self::seedForWorkspace((string) $workspaceId);
        }
    }

    /**
     * Seed all 9 canonical lifecycle templates for a specific workspace.
     */
    public static function seedForWorkspace(string $workspaceId): void
    {
        if (! Workspace::query()->where('id', $workspaceId)->exists()) {
            return;
        }

        foreach (self::canonicalTemplates() as $templateData) {
            EmailTemplate::query()
                ->where('workspace_id', $workspaceId)
                ->updateOrCreate(
                    [
                        'workspace_id' => $workspaceId,
                        'template_key' => $templateData['template_key'],
                    ],
                    [
                        'name' => $templateData['name'],
                        'milestone_type' => $templateData['milestone_type'],
                        'subject_en' => $templateData['subject_en'],
                        'body_en' => $templateData['body_en'],
                        'subject_bn' => $templateData['subject_bn'],
                        'body_bn' => $templateData['body_bn'],
                        'allowed_variables' => $templateData['allowed_variables'],
                        'is_active' => true,
                    ]
                );
        }
    }

    /**
     * Return all 9 canonical lifecycle milestone templates in English and Bengali.
     *
     * @return list<array{
     *     template_key: string,
     *     name: string,
     *     milestone_type: string,
     *     subject_en: string,
     *     body_en: string,
     *     subject_bn: string,
     *     body_bn: string,
     *     allowed_variables: list<string>
     * }>
     */
    public static function canonicalTemplates(): array
    {
        return [
            [
                'template_key' => EmailTemplate::MILESTONE_APPLICATION_RECEIPT,
                'name' => 'Application Receipt Confirmation',
                'milestone_type' => EmailTemplate::MILESTONE_APPLICATION_RECEIPT,
                'subject_en' => 'Application Received — {{workspace_name}} Membership',
                'body_en' => "<p>Dear {{applicant_name}},</p>\n<p>Thank you for submitting your membership application to <strong>{{workspace_name}}</strong>. Our Information Desk team has received your submission and is currently verifying your payment (bKash BDT {{amount_bdt}}), identity document, and studio photograph.</p>\n<p>You will receive an email update once verification is complete.</p>\n<p>Warm regards,<br>{{workspace_name}} Information Desk</p>",
                'subject_bn' => 'সদস্যপদ আবেদন গৃহীত হয়েছে — {{workspace_name}}',
                'body_bn' => "<p>প্রিয় {{applicant_name}},</p>\n<p><strong>{{workspace_name}}</strong>-এ আপনার সদস্যপদ আবেদন জমা দেওয়ার জন্য ধন্যবাদ। আমাদের ইনফরমেশন ডেস্ক টিম আপনার আবেদনটি গ্রহণ করেছে এবং আপনার পেমেন্ট (বিকাশ {{amount_bdt}} টাকা), পরিচয়পত্র ও ছবি যাচাই করছে।</p>\n<p>যাচাই প্রক্রিয়া সম্পন্ন হলে আপনাকে ইমেইলের মাধ্যমে জানানো হবে।</p>\n<p>শুভেচ্ছান্তে,<br>{{workspace_name}} ইনফরমেশন ডেস্ক</p>",
                'allowed_variables' => ['applicant_name', 'workspace_name', 'amount_bdt', 'action_url'],
            ],
            [
                'template_key' => EmailTemplate::MILESTONE_MISSING_ITEMS,
                'name' => 'Missing or Resubmission Items Request',
                'milestone_type' => EmailTemplate::MILESTONE_MISSING_ITEMS,
                'subject_en' => 'Action Required: Additional Information Needed for {{workspace_name}} Application',
                'body_en' => "<p>Dear {{applicant_name}},</p>\n<p>While reviewing your membership application for <strong>{{workspace_name}}</strong>, our verification team noted the following required correction or missing item:</p>\n<p><strong>Details:</strong> {{reason}}</p>\n<p>Please reply directly to this official email or follow the instructions at <a href=\"{{action_url}}\">{{action_url}}</a> so we can complete your application review.</p>\n<p>Sincerely,<br>{{workspace_name}} Verification Team</p>",
                'subject_bn' => 'জরুরি পদক্ষেপ প্রয়োজন: {{workspace_name}} আবেদনের জন্য অতিরিক্ত তথ্য আবশ্যক',
                'body_bn' => "<p>প্রিয় {{applicant_name}},</p>\n<p><strong>{{workspace_name}}</strong>-এ আপনার সদস্যপদ আবেদন পর্যালোচনার সময় আমাদের যাচাইকরণ টিম নিচের বিষয়টি লক্ষ্য করেছে:</p>\n<p><strong>বিস্তারিত:</strong> {{reason}}</p>\n<p>অনুগ্রহ করে এই ইমেইলের উত্তর দিন অথবা <a href=\"{{action_url}}\">{{action_url}}</a> লিংকে প্রদত্ত নির্দেশনা অনুসরণ করুন।</p>\n<p>শুভেচ্ছান্তে,<br>{{workspace_name}} যাচাইকরণ টিম</p>",
                'allowed_variables' => ['applicant_name', 'workspace_name', 'reason', 'action_url'],
            ],
            [
                'template_key' => EmailTemplate::MILESTONE_REJECTION,
                'name' => 'Application Rejection Notice',
                'milestone_type' => EmailTemplate::MILESTONE_REJECTION,
                'subject_en' => 'Update Regarding Your {{workspace_name}} Membership Application',
                'body_en' => "<p>Dear {{applicant_name}},</p>\n<p>Thank you for your interest in joining <strong>{{workspace_name}}</strong>. After careful review of your submission, we regret to inform you that your application could not be approved at this time.</p>\n<p><strong>Reason:</strong> {{reason}}</p>\n<p>If your circumstances or documentation have been updated, you may submit a fresh application via <a href=\"{{action_url}}\">{{action_url}}</a>.</p>\n<p>Regards,<br>{{workspace_name}} Information Desk</p>",
                'subject_bn' => 'আপনার {{workspace_name}} সদস্যপদ আবেদনের ফলাফল প্রসঙ্গে',
                'body_bn' => "<p>প্রিয় {{applicant_name}},</p>\n<p><strong>{{workspace_name}}</strong>-এ সদস্যপদের জন্য আবেদন করার জন্য ধন্যবাদ। আপনার আবেদনটি সতর্কতার সাথে পর্যালোচনার পর আমরা দুঃখের সাথে জানাচ্ছি যে এই মুহূর্তে আপনার আবেদনটি অনুমোদন করা সম্ভব হয়নি।</p>\n<p><strong>কারণ:</strong> {{reason}}</p>\n<p>সঠিক কাগজপত্রসহ পুনরায় আবেদন করতে ভিজিট করুন: <a href=\"{{action_url}}\">{{action_url}}</a>।</p>\n<p>শুভেচ্ছান্তে,<br>{{workspace_name}} ইনফরমেশন ডেস্ক</p>",
                'allowed_variables' => ['applicant_name', 'workspace_name', 'reason', 'action_url'],
            ],
            [
                'template_key' => EmailTemplate::MILESTONE_CONVERSION_CONFIRMATION,
                'name' => 'Membership Conversion & Invoice Confirmation',
                'milestone_type' => EmailTemplate::MILESTONE_CONVERSION_CONFIRMATION,
                'subject_en' => 'Welcome to {{workspace_name}} — Membership Confirmed ({{member_id}})',
                'body_en' => "<p>Dear {{member_name}},</p>\n<p>Congratulations! Your membership with <strong>{{workspace_name}}</strong> has been officially approved and activated.</p>\n<ul>\n<li><strong>Member ID:</strong> {{member_id}}</li>\n<li><strong>Invoice Number:</strong> {{invoice_number}} (BDT {{amount_bdt}})</li>\n<li><strong>Valid Until:</strong> {{expiry_date}}</li>\n</ul>\n<p>We look forward to welcoming you at {{workspace_name}}.</p>",
                'subject_bn' => '{{workspace_name}}-এ স্বাগতম — আপনার সদস্যপদ নিশ্চিত হয়েছে ({{member_id}})',
                'body_bn' => "<p>প্রিয় {{member_name}},</p>\n<p>অভিনন্দন! <strong>{{workspace_name}}</strong>-এ আপনার সদস্যপদ আনুষ্ঠানিকভাবে অনুমোদিত ও সক্রিয় করা হয়েছে।</p>\n<ul>\n<li><strong>মেম্বার আইডি:</strong> {{member_id}}</li>\n<li><strong>ইনভয়েস নম্বর:</strong> {{invoice_number}} ({{amount_bdt}} টাকা)</li>\n<li><strong>মেয়াদ শেষ হওয়ার তারিখ:</strong> {{expiry_date}}</li>\n</ul>\n<p>{{workspace_name}}-এ আপনাকে স্বাগত জানাতে আমরা আনন্দিত।</p>",
                'allowed_variables' => ['member_name', 'member_id', 'workspace_name', 'expiry_date', 'invoice_number', 'amount_bdt', 'action_url'],
            ],
            [
                'template_key' => EmailTemplate::MILESTONE_PORTAL_ACTIVATION,
                'name' => 'Member Portal Account Activation',
                'milestone_type' => EmailTemplate::MILESTONE_PORTAL_ACTIVATION,
                'subject_en' => 'Activate Your {{workspace_name}} Member Portal Account ({{member_id}})',
                'body_en' => "<p>Dear {{member_name}},</p>\n<p>Your read-only Member Portal account for <strong>{{workspace_name}}</strong> (Member ID: <strong>{{member_id}}</strong>) is ready for activation.</p>\n<p>Please sign in and set your personal password using the following link: <a href=\"{{action_url}}\">{{action_url}}</a>.</p>\n<p>Warm regards,<br>{{workspace_name}} Team</p>",
                'subject_bn' => 'আপনার {{workspace_name}} মেম্বার পোর্টাল অ্যাকাউন্ট সক্রিয় করুন ({{member_id}})',
                'body_bn' => "<p>প্রিয় {{member_name}},</p>\n<p><strong>{{workspace_name}}</strong>-এর মেম্বার পোর্টালে আপনার অ্যাকাউন্ট (মেম্বার আইডি: <strong>{{member_id}}</strong>) প্রস্তুত করা হয়েছে।</p>\n<p>অনুগ্রহ করে নিচের লিংকে প্রবেশ করে আপনার পাসওয়ার্ড সেট করুন: <a href=\"{{action_url}}\">{{action_url}}</a>।</p>\n<p>শুভেচ্ছান্তে,<br>{{workspace_name}} টিম</p>",
                'allowed_variables' => ['member_name', 'member_id', 'workspace_name', 'action_url'],
            ],
            [
                'template_key' => EmailTemplate::MILESTONE_ID_CARD_READY,
                'name' => 'Physical Membership ID Card Ready for Collection',
                'milestone_type' => EmailTemplate::MILESTONE_ID_CARD_READY,
                'subject_en' => 'Your {{workspace_name}} ID Card ({{member_id}}) is Ready for Pickup',
                'body_en' => "<p>Dear {{member_name}},</p>\n<p>Your physical membership ID card (<strong>{{member_id}}</strong>) has been printed and is ready for pickup at the <strong>{{workspace_name}}</strong> Information Desk during regular operating hours.</p>\n<p>Please bring a valid photo ID when collecting your card. You can also view your status at <a href=\"{{action_url}}\">{{action_url}}</a>.</p>",
                'subject_bn' => 'আপনার {{workspace_name}} আইডি কার্ড ({{member_id}}) সংগ্রহের জন্য প্রস্তুত',
                'body_bn' => "<p>প্রিয় {{member_name}},</p>\n<p>আপনার মেম্বারশিপ আইডি কার্ড (<strong>{{member_id}}</strong>) মুদ্রিত হয়েছে এবং <strong>{{workspace_name}}</strong>-এর ইনফরমেশন ডেস্ক থেকে সংগ্রহের জন্য প্রস্তুত।</p>\n<p>কার্ডটি সংগ্রহ করার সময় অনুগ্রহ করে আপনার বৈধ পরিচয়পত্র সাথে আনুন। পোর্টাল লিংক: <a href=\"{{action_url}}\">{{action_url}}</a>।</p>",
                'allowed_variables' => ['member_name', 'member_id', 'workspace_name', 'action_url'],
            ],
            [
                'template_key' => EmailTemplate::MILESTONE_RENEWAL_WINDOW,
                'name' => 'Upcoming Membership Renewal Window Reminder',
                'milestone_type' => EmailTemplate::MILESTONE_RENEWAL_WINDOW,
                'subject_en' => 'Membership Renewal Reminder — {{workspace_name}} ({{member_id}})',
                'body_en' => "<p>Dear {{member_name}},</p>\n<p>This is a friendly reminder that your membership with <strong>{{workspace_name}}</strong> (Member ID: <strong>{{member_id}}</strong>) will expire on <strong>{{expiry_date}}</strong>.</p>\n<p>To enjoy uninterrupted access, please submit your renewal request (Fee: BDT {{amount_bdt}}) via the Member Portal: <a href=\"{{action_url}}\">{{action_url}}</a>.</p>",
                'subject_bn' => 'সদস্যপদ নবায়নের রিমাইন্ডার — {{workspace_name}} ({{member_id}})',
                'body_bn' => "<p>প্রিয় {{member_name}},</p>\n<p><strong>{{workspace_name}}</strong>-এ আপনার সদস্যপদ (মেম্বার আইডি: <strong>{{member_id}}</strong>) আগামী <strong>{{expiry_date}}</strong> তারিখে মেয়াদোত্তীর্ণ হতে যাচ্ছে।</p>\n<p>নিরবচ্ছিন্ন সেবা পেতে অনুগ্রহ করে মেম্বার পোর্টালের মাধ্যমে আপনার সদস্যপদ নবায়ন করুন (ফি: {{amount_bdt}} টাকা): <a href=\"{{action_url}}\">{{action_url}}</a>।</p>",
                'allowed_variables' => ['member_name', 'member_id', 'workspace_name', 'expiry_date', 'amount_bdt', 'action_url'],
            ],
            [
                'template_key' => EmailTemplate::MILESTONE_EXPIRY,
                'name' => 'Membership Expiry Notification',
                'milestone_type' => EmailTemplate::MILESTONE_EXPIRY,
                'subject_en' => 'Your {{workspace_name}} Membership ({{member_id}}) Has Expired',
                'body_en' => "<p>Dear {{member_name}},</p>\n<p>Your membership with <strong>{{workspace_name}}</strong> (Member ID: <strong>{{member_id}}</strong>) expired on <strong>{{expiry_date}}</strong>.</p>\n<p>You can reactivate your membership anytime by submitting a renewal application through your Member Portal at <a href=\"{{action_url}}\">{{action_url}}</a>.</p>",
                'subject_bn' => 'আপনার {{workspace_name}} সদস্যপদ ({{member_id}})-এর মেয়াদ শেষ হয়েছে',
                'body_bn' => "<p>প্রিয় {{member_name}},</p>\n<p><strong>{{workspace_name}}</strong>-এ আপনার সদস্যপদের (মেম্বার আইডি: <strong>{{member_id}}</strong>) মেয়াদ <strong>{{expiry_date}}</strong> তারিখে শেষ হয়েছে।</p>\n<p>পুনরায় সদস্যপদ সক্রিয় করতে মেম্বার পোর্টালে লগইন করে নবায়ন আবেদন জমা দিন: <a href=\"{{action_url}}\">{{action_url}}</a>।</p>",
                'allowed_variables' => ['member_name', 'member_id', 'workspace_name', 'expiry_date', 'action_url'],
            ],
            [
                'template_key' => EmailTemplate::MILESTONE_REQUEST_OUTCOME,
                'name' => 'Profile Change or Email Update Request Outcome',
                'milestone_type' => EmailTemplate::MILESTONE_REQUEST_OUTCOME,
                'subject_en' => 'Outcome of Your {{workspace_name}} Profile Request ({{member_id}})',
                'body_en' => "<p>Dear {{member_name}},</p>\n<p>Our administrative team at <strong>{{workspace_name}}</strong> has completed the review of your recent profile or email change request for Member ID <strong>{{member_id}}</strong>.</p>\n<p><strong>Outcome / Notes:</strong> {{reason}}</p>\n<p>You can review your updated profile details in the Member Portal: <a href=\"{{action_url}}\">{{action_url}}</a>.</p>",
                'subject_bn' => 'আপনার {{workspace_name}} প্রোফাইল পরিবর্তন আবেদনের ফলাফল ({{member_id}})',
                'body_bn' => "<p>প্রিয় {{member_name}},</p>\n<p><strong>{{workspace_name}}</strong>-এর প্রশাসনিক টিম আপনার মেম্বার আইডি <strong>{{member_id}}</strong>-এর প্রোফাইল বা ইমেইল পরিবর্তন আবেদনটি পর্যালোচনা সম্পন্ন করেছে।</p>\n<p><strong>ফলাফল / মন্তব্য:</strong> {{reason}}</p>\n<p>আপনার হালনাগাদ প্রোফাইল দেখতে মেম্বার পোর্টালে ভিজিট করুন: <a href=\"{{action_url}}\">{{action_url}}</a>।</p>",
                'allowed_variables' => ['member_name', 'member_id', 'workspace_name', 'reason', 'action_url'],
            ],
            [
                'template_key' => EmailTemplate::MILESTONE_PASSWORD_RESET,
                'name' => 'Password Reset & Account Recovery',
                'milestone_type' => EmailTemplate::MILESTONE_PASSWORD_RESET,
                'subject_en' => 'Reset Your {{workspace_name}} Password',
                'body_en' => "<p>Dear {{member_name}},</p>\n<p>We received a request to reset your password for your <strong>{{workspace_name}}</strong> account.</p>\n<p>Please click the link below to securely set a new password:</p>\n<p><a href=\"{{action_url}}\" style=\"display: inline-block; background-color: #d97706; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;\">Reset Password</a></p>\n<p>Or copy and paste this URL into your browser: <br><a href=\"{{action_url}}\">{{action_url}}</a></p>\n<p>This password reset link will expire in 60 minutes. If you did not make this request, you can safely ignore this email.</p>\n<p>Sincerely,<br>{{workspace_name}} Security & Admissions</p>",
                'subject_bn' => 'আপনার {{workspace_name}} পাসওয়ার্ড রিসেট করুন',
                'body_bn' => "<p>প্রিয় {{member_name}},</p>\n<p><strong>{{workspace_name}}</strong> অ্যাকাউন্টের পাসওয়ার্ড রিসেট করার একটি অনুরোধ আমরা পেয়েছি।</p>\n<p>নতুন পাসওয়ার্ড সেট করতে নিচের বাটনে বা লিংকে ক্লিক করুন:</p>\n<p><a href=\"{{action_url}}\" style=\"display: inline-block; background-color: #d97706; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;\">পাসওয়ার্ড রিসেট করুন</a></p>\n<p>অথবা এই লিংকটি ব্রাউজারে পেস্ট করুন: <br><a href=\"{{action_url}}\">{{action_url}}</a></p>\n<p>এই লিংকটি আগামী ৬০ মিনিট সক্রিয় থাকবে। আপনি যদি এই অনুরোধ না করে থাকেন, তবে বার্তাটি এড়িয়ে চলুন।</p>\n<p>শুভেচ্ছান্তে,<br>{{workspace_name}} নিরাপত্তা ও ভর্তি টিম</p>",
                'allowed_variables' => ['member_name', 'workspace_name', 'action_url'],
            ],
            [
                'template_key' => EmailTemplate::MILESTONE_ADMIN_NEW_USER,
                'name' => 'Admin Alert: New User & Membership Application',
                'milestone_type' => EmailTemplate::MILESTONE_ADMIN_NEW_USER,
                'subject_en' => '[New Application] {{applicant_name}} submitted membership application for {{workspace_name}}',
                'body_en' => "<p>Dear Administrator,</p>\n<p>A new membership application or user account has been submitted for <strong>{{workspace_name}}</strong>.</p>\n<ul>\n<li><strong>Applicant Name:</strong> {{applicant_name}}</li>\n<li><strong>Payment Reference (bKash):</strong> BDT {{amount_bdt}}</li>\n<li><strong>Submission Status:</strong> Awaiting Document & Payment Review</li>\n</ul>\n<p>Please log in to the Staff & Admin Console to inspect documents, verify transaction credentials, and process the application:</p>\n<p><a href=\"{{action_url}}\" style=\"display: inline-block; background-color: #d97706; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;\">Review New Application</a></p>\n<p>Automated notification from {{workspace_name}} Admissions Engine.</p>",
                'subject_bn' => '[নতুন আবেদন] {{workspace_name}}-এ {{applicant_name}}-এর সদস্যপদ আবেদন জমা হয়েছে',
                'body_bn' => "<p>শ্রদ্ধেয় অ্যাডমিনিস্ট্রেটর,</p>\n<p><strong>{{workspace_name}}</strong>-এ একজন নতুন সদস্যের আবেদন জমা হয়েছে।</p>\n<ul>\n<li><strong>আবেদনকারীর নাম:</strong> {{applicant_name}}</li>\n<li><strong>পেমেন্ট রেফারেন্স (বিকাশ):</strong> {{amount_bdt}} টাকা</li>\n<li><strong>অবস্থা:</strong> কাগজপত্র ও পেমেন্ট যাচাইকরণের অপেক্ষায়</li>\n</ul>\n<p>আবেদনটি পর্যালোচনা ও অনুমোদন করতে অনুগ্রহ করে স্টাফ কনসোলে প্রবেশ করুন:</p>\n<p><a href=\"{{action_url}}\" style=\"display: inline-block; background-color: #d97706; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;\">আবেদন পর্যালোচনা করুন</a></p>\n<p>{{workspace_name}} অ্যাডমিশন ইঞ্জিন থেকে স্বয়ংক্রিয় নোটিফিকেশন।</p>",
                'allowed_variables' => ['applicant_name', 'workspace_name', 'amount_bdt', 'action_url'],
            ],
        ];
    }
}
