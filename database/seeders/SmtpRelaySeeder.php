<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailThread;
use App\Domain\Mail\Models\SmtpRelay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SmtpRelaySeeder extends Seeder
{
    public function run(): void
    {
        if (SmtpRelay::count() === 0) {
            SmtpRelay::create([
                'workspace_id' => 'ws-emk-dhaka',
                'name' => 'Brevo Primary Gateway',
                'priority' => 1,
                'sender_email' => 'noreply@jaago.com.bd',
                'sender_name' => 'JAAGO HUB',
                'host' => 'smtp-relay.brevo.com',
                'port' => 587,
                'encryption' => 'starttls',
                'username' => 'nasif.kamal@jaago.com.bd',
                'password' => 'xsmtpsib-demo-key-for-emk-portal',
                'throttle_seconds' => 0,
                'reply_to' => 'pnc@jaago.com.bd',
                'is_active' => true,
                'status' => 'untested',
                'last_tested_at' => null,
            ]);
        }

        if (EmailMessage::withoutGlobalScopes()->where('workspace_id', 'ws-emk-dhaka')->count() === 0) {
            $t1 = EmailThread::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => 'ws-emk-dhaka',
                'participant_email' => 'farhana.rahman@example.com',
                'subject' => 'Application Receipt Confirmation — EMK Center Dhaka',
                'last_message_at' => now()->subMinutes(25),
                'message_count' => 1,
            ]);

            EmailMessage::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => 'ws-emk-dhaka',
                'email_thread_id' => $t1->id,
                'direction' => 'outbound',
                'status' => 'sent',
                'from_email' => 'noreply@jaago.com.bd',
                'to_email' => 'farhana.rahman@example.com',
                'subject' => 'Application Receipt Confirmation — EMK Center Dhaka',
                'template_key' => 'application_receipt',
                'milestone_type' => 'application_receipt',
                'body_text' => 'Dear Farhana, your membership application has been received successfully.',
                'sent_at' => now()->subMinutes(25),
            ]);

            $t2 = EmailThread::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => 'ws-emk-dhaka',
                'participant_email' => 'tanvir.ahmed@example.com',
                'subject' => 'Welcome to EMK Center — Membership Confirmed',
                'last_message_at' => now()->subHours(1),
                'message_count' => 1,
            ]);

            EmailMessage::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => 'ws-emk-dhaka',
                'email_thread_id' => $t2->id,
                'direction' => 'outbound',
                'status' => 'sent',
                'from_email' => 'noreply@jaago.com.bd',
                'to_email' => 'tanvir.ahmed@example.com',
                'subject' => 'Welcome to EMK Center — Membership Confirmed',
                'template_key' => 'conversion_confirmation',
                'milestone_type' => 'conversion_confirmation',
                'body_text' => 'Dear Tanvir, welcome to EMK Center! Your membership card is ready for pickup.',
                'sent_at' => now()->subHours(1),
            ]);

            $t3 = EmailThread::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => 'ws-emk-dhaka',
                'participant_email' => 'adnan.sami@example.com',
                'subject' => 'Action Required: Additional Information Needed for Application',
                'last_message_at' => now()->subHours(2),
                'message_count' => 1,
            ]);

            EmailMessage::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => 'ws-emk-dhaka',
                'email_thread_id' => $t3->id,
                'direction' => 'outbound',
                'status' => 'failed',
                'from_email' => 'noreply@jaago.com.bd',
                'to_email' => 'adnan.sami@example.com',
                'subject' => 'Action Required: Additional Information Needed for Application',
                'template_key' => 'missing_items',
                'milestone_type' => 'missing_items',
                'failure_reason' => '535 5.7.8 Authentication failed on Brevo SMTP server. Invalid API key.',
                'created_at' => now()->subHours(2),
            ]);
        }
    }
}

