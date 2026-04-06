<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class LinkHealthGroupNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $groupName,
        public Collection $failedLinks,
        public ?Collection $previouslyFailedLinks = null,
        public int $totalCount = 0,
        public int $newCount = 0,
        public int $previousCount = 0,
    ) {}

    public function envelope(): Envelope
    {
        $subject = 'Link Health Alert - ';
        if ($this->newCount > 0 && $this->previousCount > 0) {
            $subject .= $this->newCount.' New + '.$this->previousCount.' Previously Failed Links';
        } elseif ($this->newCount > 0) {
            $subject .= $this->newCount.' Link'.($this->newCount > 1 ? 's' : '').' Failed';
        } else {
            $subject .= $this->previousCount.' Previously Failed Link'.($this->previousCount > 1 ? 's' : '');
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.link-health-group',
            with: [
                'group_name' => $this->groupName,
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
