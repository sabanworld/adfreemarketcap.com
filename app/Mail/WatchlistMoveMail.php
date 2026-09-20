<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Services\Watchlist\WatchlistAlertCoin;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WatchlistMoveMail extends Mailable
{
    public string $subjectLine;

    /**
     * @param  list<WatchlistAlertCoin>  $alerts
     */
    public function __construct(
        public User $user,
        public array $alerts,
    ) {
        $this->subjectLine = $this->composeSubject();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.watchlist-move',
            text: 'mail.watchlist-move-text',
        );
    }

    private function composeSubject(): string
    {
        if (count($this->alerts) === 1) {
            $alert = $this->alerts[0];

            if (count($alert->lines) === 1) {
                $line = $alert->lines[0];

                return __(':name is :direction :band% over :window', [
                    'name' => $alert->coin->name,
                    'direction' => $line->percent < 0 ? __('down') : __('up'),
                    'band' => $line->band,
                    'window' => $line->windowLabel,
                ]);
            }

            return __(':name moved on your watchlist', [
                'name' => $alert->coin->name,
            ]);
        }

        return trans_choice(':count saved coin moved|:count saved coins moved', count($this->alerts), [
            'count' => count($this->alerts),
        ]);
    }
}
