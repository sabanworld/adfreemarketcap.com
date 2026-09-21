<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Coin;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class WatchlistRecapMail extends Mailable
{
    public string $subjectLine;

    public string $sentOn;

    public string $sentAt;

    public string $heading;

    public string $closing;

    /**
     * Columns of percentage change, per period. An hourly figure is noise in a
     * week in review, so the weekly stops at 24 hours and 7 days.
     *
     * @var array<string, string>
     */
    public array $windows;

    /**
     * @param  Collection<int, Coin>  $coins
     */
    public function __construct(
        public User $user,
        public Collection $coins,
        public string $period = 'daily',
    ) {
        $weekly = $this->period === 'weekly';
        $this->windows = $weekly
            ? ['24h' => __('24 hours'), '7d' => __('7 days')]
            : ['1h' => __('1 hour'), '24h' => __('24 hours'), '7d' => __('7 days')];
        $this->sentOn = ($weekly ? now()->copy()->startOfWeek(CarbonInterface::MONDAY) : now())->format('j F Y');
        $this->sentAt = now()->format('H:i') . ' UTC';
        $this->heading = $weekly ? __('Weekly recap') : __('Daily recap');
        $this->subjectLine = $weekly
            ? __('Your watchlist for the week of :date', ['date' => $this->sentOn])
            : __('Your watchlist for :date', ['date' => $this->sentOn]);
        $this->closing = $weekly
            ? __('Figures come from the same sync that feeds the site, so they are as fresh as the last run rather than live. One recap goes out each week.')
            : __('Figures come from the same sync that feeds the site, so they are as fresh as the last run rather than live. One recap goes out each day.');
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
