<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Application Scheduled Tasks
|--------------------------------------------------------------------------
|
| Scheduled maintenance, Horizon metric snapshots, and queue pruning.
|
*/

// Horizon snapshot metrics every five minutes
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Prune failed queue jobs older than 48 hours daily
Schedule::command('queue:prune-failed --hours=48')->daily();

// Prune job batches older than 48 hours daily
Schedule::command('queue:prune-batches --hours=48')->daily();

// Synchronize official Information Desk IMAP mailbox every five minutes
Schedule::command('crm:mailbox-sync')->everyFiveMinutes();

