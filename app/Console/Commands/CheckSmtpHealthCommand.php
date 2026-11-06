<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Mail\Models\SmtpRelay;
use App\Domain\Mail\Services\SmtpTransportManager;
use Illuminate\Console\Command;

class CheckSmtpHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:check-smtp {--send-test= : Optional recipient email to send test verification email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify connectivity and cryptographic authentication for all active SMTP email relays.';

    /**
     * Execute the console command.
     */
    public function handle(SmtpTransportManager $transportManager): int
    {
        $relays = SmtpRelay::query()->active()->orderedByPriority()->get();

        if ($relays->isEmpty()) {
            $this->info('No active custom SMTP relays configured in database.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Testing %d active SMTP relay(s)...', $relays->count()));
        $hasErrors = false;
        $testRecipient = $this->option('send-test');

        $rows = [];
        foreach ($relays as $relay) {
            $result = $transportManager->testRelay($relay, $testRecipient ?: null);

            $statusBadge = $result['success']
                ? '<info>[CONNECTED]</info>'
                : '<error>[BROKEN]</error>';

            if (! $result['success']) {
                $hasErrors = true;
            }

            $rows[] = [
                '#' . $relay->priority . ' ' . $relay->name,
                "{$relay->host}:{$relay->port} (" . strtoupper($relay->encryption) . ")",
                $relay->sender_email,
                $statusBadge,
                $result['latency_ms'] . ' ms',
                $result['message'],
            ];
        }

        $this->table(['Relay', 'Host & Port', 'Sender', 'Status', 'Latency', 'Diagnostic'], $rows);

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }
}

