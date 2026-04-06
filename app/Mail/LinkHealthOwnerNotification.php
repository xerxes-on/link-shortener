<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class LinkHealthOwnerNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $failedLinks,
        public ?Collection $previouslyFailedLinks = null,
        public int $totalCount = 0,
        public int $newCount = 0,
        public int $previousCount = 0,
    ) {}

    public function envelope(): Envelope
    {
        if ($this->newCount > 0 && $this->previousCount > 0) {
            $subject = 'Your Links Health Alert - '.$this->newCount.' New + '.$this->previousCount.' Previously Failed';
        } elseif ($this->newCount > 0) {
            $subject = 'Your Link'.($this->newCount > 1 ? 's' : '').' Health Alert';
        } else {
            $subject = 'Your '.$this->previousCount.' Link'.($this->previousCount > 1 ? 's' : '').' Still Failed';
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.link-health-owner',
            with: [
                'user' => $this->user,
                'failed_links' => $this->failedLinks,
                'previously_failed_links' => $this->previouslyFailedLinks,
                'total_count' => $this->totalCount,
                'new_count' => $this->newCount,
                'previous_count' => $this->previousCount,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
