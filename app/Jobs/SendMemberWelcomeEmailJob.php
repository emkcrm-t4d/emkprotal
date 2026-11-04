<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Members\Mail\MemberWelcomeMail;
use App\Domain\Members\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMemberWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $memberId
    ) {}

    public function handle(): void
    {
        $member = Member::find($this->memberId);
        if ($member && !empty($member->email)) {
            Mail::to($member->email)->send(new MemberWelcomeMail($member));
        }
    }
}
