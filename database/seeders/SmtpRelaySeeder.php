<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Mail\Models\SmtpRelay;
use Illuminate\Database\Seeder;

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
    }
}
