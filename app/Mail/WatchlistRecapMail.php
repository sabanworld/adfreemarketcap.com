<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Coin;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class WatchlistRecapMail extends Mailable
{
    public string $subjectLine;

    public string $sentOn;

    public string $sentAt;

    /**
     * @param  Collection<int, Coin>  $coins
     */
    public function __construct(
        public User $user,
        public Collection $coins,
    ) {
        $this->sentOn = now()->format('j F Y');
        $this->sentAt = now()->format('H:i') . ' UTC';
        $this->subjectLine = __('Your watchlist for :date', ['date' => $this->sentOn]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.watchlist-recap',
            text: 'mail.watchlist-recap-text',
        );
    }
}
