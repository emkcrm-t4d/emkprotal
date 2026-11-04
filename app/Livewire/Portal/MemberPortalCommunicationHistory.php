<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailThread;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MemberPortalCommunicationHistory extends Component
{
    public ?int $selectedThreadId = null;
    public string $search = '';

    public function mount(?int $initialThreadId = null): void
    {
        $this->selectedThreadId = $initialThreadId;

        if ($this->selectedThreadId === null) {
            $firstThread = $this->getThreads()->first();
            if ($firstThread) {
                $this->selectedThreadId = (int) $firstThread->id;
            }
        }
    }

    public function selectThread(int $threadId): void
    {
        $this->selectedThreadId = $threadId;
    }

    /**
     * Resolve email threads associated with the authenticated member.
     * Enforces tenant boundary and member identity isolation.
     *
     * @return Collection<int, EmailThread>
     */
    public function getThreads(): Collection
    {
        $user = Auth::guard('member')->user();
        $member = $user?->member;

        if (!$member) {
            return new Collection();
        }

        $query = EmailThread::withoutGlobalScopes()
            ->where('workspace_id', $member->workspace_id)
            ->where(function ($q) use ($member) {
                $q->where('member_id', $member->id)
                    ->orWhere('participant_email', $member->email);

                if ($member->lead_id) {
                    $q->orWhere('lead_id', $member->lead_id);
                }
            })
            ->with(['messages' => function ($mq) {
                $mq->orderBy('created_at', 'asc')->orderBy('id', 'asc');
            }])
            ->orderByDesc('last_message_at');

        if (!empty(trim($this->search))) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', $term)
                    ->orWhere('participant_email', 'like', $term);
            });
        }

        return $query->get();
    }

    /**
     * Get currently selected thread ensuring it belongs to authenticated member.
     */
    public function getSelectedThreadProperty(): ?EmailThread
    {
        if ($this->selectedThreadId === null) {
            return null;
        }

        return $this->getThreads()->firstWhere('id', $this->selectedThreadId);
    }

    /**
     * Sanitize email HTML bodies to neutralize XSS vulnerabilities (T-09-06).
     * Strips script, iframe, object, embed, form tags, and inline DOM event listeners.
     */
    public function sanitizeHtml(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // 1. Strip script, iframe, object, embed, form, link, style tags and contents
        $cleaned = preg_replace('#<(script|iframe|object|embed|form|style)[^>]*?>.*?</\1>#is', '', $html);
        $cleaned = preg_replace('#<(script|iframe|object|embed|form|link|meta|base)[^>]*?>#is', '', (string) $cleaned);

        // 2. Strip inline event handlers (onload, onclick, onerror, etc.)
        $cleaned = preg_replace('#\s*on[a-zA-Z]+\s*=\s*(["\'][^"\']*?["\']|[^\s>]+)#is', '', (string) $cleaned);

        // 3. Neutralize dangerous pseudo-protocols in href or src (javascript:, vbscript:, data:)
        $cleaned = preg_replace('#(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data):.*?\2#is', '$1="#"', (string) $cleaned);

        return (string) $cleaned;
    }

    public function render(): View
    {
        return view('livewire.portal.member-portal-communication-history', [
            'threads' => $this->getThreads(),
            'selectedThread' => $this->selectedThread,
        ]);
    }
}
