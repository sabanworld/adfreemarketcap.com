<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendWatchlistMoveAlerts;
use App\Livewire\Watchlist;
use App\Mail\WatchlistMoveMail;
use App\Mail\WatchlistRecapMail;
use App\Models\Coin;
use App\Models\User;
use App\Models\WatchlistPriceAlert;
use App\Services\Watchlist\WatchlistAlertCoin;
use App\Services\Watchlist\WatchlistAlertLine;
use App\Services\Watchlist\WatchlistMailFigures;
use App\Services\Watchlist\WatchlistPriceAlertService;
use App\Services\Watchlist\WatchlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class WatchlistPriceAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_bands_start_at_five_and_step_by_five(): void
    {
        $alerts = app(WatchlistPriceAlertService::class);

        $this->assertSame(0, $alerts->bandFor(4.99));
        $this->assertSame(0, $alerts->bandFor(-4.99));
        $this->assertSame(5, $alerts->bandFor(5));
        $this->assertSame(5, $alerts->bandFor(9.99));
        $this->assertSame(10, $alerts->bandFor(10));
        $this->assertSame(15, $alerts->bandFor(15.4));
        $this->assertSame(15, $alerts->bandFor(-15.4));
        $this->assertSame(20, $alerts->bandFor(20));
        $this->assertSame(25, $alerts->bandFor(25));
        $this->assertSame(30, $alerts->bandFor(30));
    }

    public function test_a_jump_to_fifteen_percent_sends_one_email_for_the_highest_mark(): void
    {
        Mail::fake();
        $this->travelTo('2026-09-20 10:00:00');

        $user = User::factory()->create(['email' => 'ada@example.com']);
        $coin = $this->coin(['percent_change_24h' => 1]);
        $user->watchedCoins()->attach($coin);

        $alerts = app(WatchlistPriceAlertService::class);
        $this->assertSame(0, $alerts->sendMoveAlerts());
        Mail::assertNothingSent();
        $this->assertSame(0, $this->alert($user, $coin, '24h')->notified_percent);

        $coin->update(['percent_change_24h' => 15.4]);

        $this->assertSame(1, $alerts->sendMoveAlerts());

        Mail::assertSent(WatchlistMoveMail::class, function (WatchlistMoveMail $mail) use ($user): bool {
            $html = $mail->render();

            $this->assertSame('Bitcoin is up 15% over 24 hours', $mail->subjectLine);
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertCount(1, $mail->alerts);
            $this->assertCount(1, $mail->alerts[0]->lines);
            $this->assertSame(15, $mail->alerts[0]->lines[0]->band);
            $this->assertSame('24h', $mail->alerts[0]->lines[0]->window);
            $this->assertStringContainsString('+15.40%', $html);
            $this->assertStringContainsString('over 24 hours, past the 15% mark', $html);
            $this->assertStringNotContainsString('past the 5% mark', $html);
            $this->assertStringNotContainsString('past the 10% mark', $html);
            $this->assertStringContainsString('$65,000.00', $html);

            return true;
        });

        $this->assertSame(15, $this->alert($user, $coin, '24h')->notified_percent);

        $coin->update(['percent_change_24h' => 22]);
        $this->assertSame(0, $alerts->sendMoveAlerts());
        Mail::assertSentTimes(WatchlistMoveMail::class, 1);

        $this->travelTo('2026-09-20 11:01:00');
        $this->assertSame(1, $alerts->sendMoveAlerts());

        Mail::assertSent(WatchlistMoveMail::class, function (WatchlistMoveMail $mail): bool {
            return $mail->subjectLine === 'Bitcoin is up 20% over 24 hours'
                && $mail->alerts[0]->lines[0]->band === 20;
        });
        $this->assertSame(20, $this->alert($user, $coin, '24h')->notified_percent);
    }

    public function test_other_windows_can_still_email_inside_the_same_hour(): void
    {
        Mail::fake();
        $this->travelTo('2026-09-20 10:00:00');

        $user = User::factory()->create();
        $coin = $this->coin();
        $user->watchedCoins()->attach($coin);
        $alerts = app(WatchlistPriceAlertService::class);
        $alerts->sendMoveAlerts();

        $coin->update(['percent_change_24h' => 15.4]);
        $alerts->sendMoveAlerts();
        Mail::assertSentTimes(WatchlistMoveMail::class, 1);

        $coin->update(['percent_change_1h' => 6.2]);
        $this->assertSame(1, $alerts->sendMoveAlerts());

        Mail::assertSent(WatchlistMoveMail::class, function (WatchlistMoveMail $mail): bool {
            return $mail->subjectLine === 'Bitcoin is up 5% over the last hour';
        });
    }

    public function test_several_windows_crossed_together_are_one_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $coin = $this->coin();
        $user->watchedCoins()->attach($coin);
        $alerts = app(WatchlistPriceAlertService::class);
        $alerts->sendMoveAlerts();

        $coin->update([
            'percent_change_1h' => 6,
            'percent_change_24h' => 15.4,
            'percent_change_7d' => 11,
        ]);

        $this->assertSame(1, $alerts->sendMoveAlerts());

        Mail::assertSent(WatchlistMoveMail::class, function (WatchlistMoveMail $mail): bool {
            $bands = array_map(
                static fn ($line): int => $line->band,
                $mail->alerts[0]->lines,
            );

            return $mail->subjectLine === 'Bitcoin moved on your watchlist'
                && $bands === [5, 15, 10];
        });
    }

    public function test_a_reversal_waits_out_the_hour_and_then_emails_the_new_direction(): void
    {
        Mail::fake();
        $this->travelTo('2026-09-20 10:00:00');

        $user = User::factory()->create();
        $coin = $this->coin();
        $user->watchedCoins()->attach($coin);
        $alerts = app(WatchlistPriceAlertService::class);
        $alerts->sendMoveAlerts();

        $coin->update(['percent_change_24h' => 6]);
        $alerts->sendMoveAlerts();
        Mail::assertSentTimes(WatchlistMoveMail::class, 1);

        $coin->update(['percent_change_24h' => -12]);
        $this->assertSame(0, $alerts->sendMoveAlerts());
        Mail::assertSentTimes(WatchlistMoveMail::class, 1);

        $this->travelTo('2026-09-20 11:01:00');
        $this->assertSame(1, $alerts->sendMoveAlerts());

        Mail::assertSent(WatchlistMoveMail::class, function (WatchlistMoveMail $mail): bool {
            return $mail->subjectLine === 'Bitcoin is down 10% over 24 hours'
                && $mail->alerts[0]->lines[0]->band === 10;
        });
    }

    public function test_falling_back_does_not_email_until_the_mark_is_crossed_again(): void
    {
        Mail::fake();
        $this->travelTo('2026-09-20 10:00:00');

        $user = User::factory()->create();
        $coin = $this->coin();
        $user->watchedCoins()->attach($coin);
        $alerts = app(WatchlistPriceAlertService::class);
        $alerts->sendMoveAlerts();

        $coin->update(['percent_change_24h' => 16]);
        $alerts->sendMoveAlerts();
        $this->assertSame(15, $this->alert($user, $coin, '24h')->notified_percent);

        $coin->update(['percent_change_24h' => 8]);
        $this->assertSame(0, $alerts->sendMoveAlerts());
        $this->assertSame(5, $this->alert($user, $coin, '24h')->notified_percent);

        $coin->update(['percent_change_24h' => 16]);
        $this->assertSame(0, $alerts->sendMoveAlerts());

        $this->travelTo('2026-09-20 11:01:00');
        $this->assertSame(1, $alerts->sendMoveAlerts());
        $this->assertSame(15, $this->alert($user, $coin, '24h')->notified_percent);
    }

    public function test_the_first_reading_of_an_existing_move_is_a_baseline(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $coin = $this->coin(['percent_change_24h' => 18]);
        $user->watchedCoins()->attach($coin);

        $alerts = app(WatchlistPriceAlertService::class);
        $this->assertSame(0, $alerts->sendMoveAlerts());
        Mail::assertNothingSent();
        $this->assertSame(15, $this->alert($user, $coin, '24h')->notified_percent);

        $coin->update(['percent_change_24h' => 21]);
        $this->assertSame(1, $alerts->sendMoveAlerts());
        Mail::assertSent(WatchlistMoveMail::class, function (WatchlistMoveMail $mail): bool {
            return $mail->alerts[0]->lines[0]->band === 20;
        });
    }

    public function test_daily_recap_includes_coins_that_did_not_move_and_sends_once_per_day(): void
    {
        Mail::fake();
        $this->travelTo('2026-09-20 06:00:00');

        $user = User::factory()->create();
        $flat = $this->coin([
            'slug' => 'bitcoin',
            'name' => 'Bitcoin',
            'percent_change_1h' => 0,
            'percent_change_24h' => 0,
            'percent_change_7d' => 0,
        ]);
        $other = $this->coin([
            'slug' => 'ethereum',
            'symbol' => 'eth',
            'name' => 'Ethereum',
            'rank' => 2,
            'percent_change_24h' => 1.2,
        ]);
        $user->watchedCoins()->attach([$flat->id, $other->id]);

        $alerts = app(WatchlistPriceAlertService::class);
        $this->assertSame(1, $alerts->sendDailyRecaps());

        Mail::assertSent(WatchlistRecapMail::class, function (WatchlistRecapMail $mail) use ($user): bool {
            $html = $mail->render();

            $this->assertSame('Your watchlist for 20 September 2026', $mail->subjectLine);
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertStringContainsString('Bitcoin', $html);
            $this->assertStringContainsString('Ethereum', $html);
            $this->assertStringContainsString('0.00%', $html);
            $this->assertStringContainsString('whether it moved or not', $html);

            return true;
        });

        $this->assertSame(0, $alerts->sendDailyRecaps());
        Mail::assertSentTimes(WatchlistRecapMail::class, 1);

        $this->travelTo('2026-09-21 06:00:00');
        $this->assertSame(1, $alerts->sendDailyRecaps());
        Mail::assertSentTimes(WatchlistRecapMail::class, 2);
    }

    public function test_price_emails_can_be_turned_off(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $coin = $this->coin(['percent_change_24h' => 20]);
        $user->watchedCoins()->attach($coin);

        $alerts = app(WatchlistPriceAlertService::class);
        $alerts->sendMoveAlerts();
        $this->assertDatabaseHas('watchlist_price_alerts', ['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(Watchlist::class)
            ->assertSee('Marks start at 5%', false)
            ->assertSet('priceAlertsEnabled', true)
            ->set('priceAlertsEnabled', false);

        $user->refresh();
        $this->assertFalse($user->price_alerts_enabled);
        $this->assertSame(0, WatchlistPriceAlert::query()->where('user_id', $user->id)->count());

        $coin->update(['percent_change_24h' => 30]);
        $this->assertSame(0, $alerts->sendMoveAlerts());
        $this->assertSame(0, $alerts->sendDailyRecaps());
        Mail::assertNothingSent();
    }

    public function test_removing_a_star_stops_that_coin_and_drops_its_marks(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $coin = $this->coin();
        $user->watchedCoins()->attach($coin);

        $alerts = app(WatchlistPriceAlertService::class);
        $alerts->sendMoveAlerts();
        $this->assertDatabaseHas('watchlist_price_alerts', [
            'user_id' => $user->id,
            'coin_id' => $coin->id,
        ]);

        app(WatchlistService::class)->toggle($user, $coin);

        $this->assertSame(0, WatchlistPriceAlert::query()->where('coin_id', $coin->id)->count());

        $coin->update(['percent_change_24h' => 40]);
        $this->assertSame(0, $alerts->sendMoveAlerts());
        Mail::assertNothingSent();
    }

    public function test_a_non_numeric_change_does_not_throw(): void
    {
        $user = User::factory()->create();
        $coin = $this->coin();
        $coin->setRawAttributes(array_merge($coin->getAttributes(), [
            'percent_change_1h' => false,
            'percent_change_24h' => false,
            'percent_change_7d' => false,
        ]));
        $user->setRelation('watchedCoins', collect([$coin]));

        $method = new ReflectionMethod(WatchlistPriceAlertService::class, 'pendingAlerts');
        $alerts = $method->invoke(app(WatchlistPriceAlertService::class), $user);

        $this->assertSame([], $alerts);
    }

    /**
     * The brand is drawn with table cells and the type falls back to a system stack, so an
     * opened email fetches nothing. A remote logo or webfont would report the open back to us.
     */
    public function test_both_emails_carry_the_brand_and_request_nothing_remote(): void
    {
        $user = User::factory()->create();
        $coin = $this->coin(['percent_change_24h' => 12]);

        $line = new WatchlistAlertLine('24h', '24 hours', 12.0, 10);
        $move = (new WatchlistMoveMail($user, [new WatchlistAlertCoin($coin, [$line])]))->render();
        $recap = (new WatchlistRecapMail($user, collect([$coin])))->render();

        foreach (['move' => $move, 'recap' => $recap] as $name => $html) {
            $this->assertStringContainsString('adfreemarketcap', $html, "The {$name} email lost the wordmark.");
            $this->assertStringContainsString('#FF7A00', $html, "The {$name} email lost the amber accent bar.");
            $this->assertStringContainsString(route('watchlist'), $html);
            $this->assertStringContainsString('The Saban Company B.V.', $html);

            $this->assertDoesNotMatchRegularExpression('/<img\b/i', $html, "The {$name} email loads an image.");
            $this->assertStringNotContainsString('@font-face', $html);
            $this->assertStringNotContainsString('http://localhost/build', $html);
        }
    }

    public function test_a_flat_change_carries_no_direction(): void
    {
        $this->assertSame('0.00%', WatchlistMailFigures::signedPercent(0.0));
        $this->assertSame('0.00%', WatchlistMailFigures::signedPercent(0.004));
        $this->assertSame('+1.20%', WatchlistMailFigures::signedPercent(1.2));
        $this->assertSame('-1.20%', WatchlistMailFigures::signedPercent(-1.2));
        $this->assertSame('flat', WatchlistMailFigures::direction('0'));
        $this->assertSame('empty', WatchlistMailFigures::direction(null));
        $this->assertSame('empty', WatchlistMailFigures::direction(false));
    }

    public function test_the_move_job_sends_through_the_service(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $coin = $this->coin();
        $user->watchedCoins()->attach($coin);
        app(WatchlistPriceAlertService::class)->sendMoveAlerts();

        $coin->update(['percent_change_7d' => 12]);

        (new SendWatchlistMoveAlerts)->handle(app(WatchlistPriceAlertService::class));

        Mail::assertSent(WatchlistMoveMail::class, function (WatchlistMoveMail $mail): bool {
            return $mail->subjectLine === 'Bitcoin is up 10% over 7 days';
        });
    }

    public function test_alerts_can_be_disabled_in_config(): void
    {
        Mail::fake();
        config(['watchlist_alerts.enabled' => false]);

        $user = User::factory()->create();
        $coin = $this->coin(['percent_change_24h' => 30]);
        $user->watchedCoins()->attach($coin);

        $alerts = app(WatchlistPriceAlertService::class);
        $this->assertSame(0, $alerts->sendMoveAlerts());
        $this->assertSame(0, $alerts->sendDailyRecaps());
        Mail::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function coin(array $overrides = []): Coin
    {
        return Coin::query()->create(array_merge([
            'slug' => 'bitcoin',
            'symbol' => 'btc',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 65000,
            'percent_change_1h' => 0,
            'percent_change_24h' => 0,
            'percent_change_7d' => 0,
        ], $overrides));
    }

    private function alert(User $user, Coin $coin, string $window): WatchlistPriceAlert
    {
        return WatchlistPriceAlert::query()
            ->where('user_id', $user->id)
            ->where('coin_id', $coin->id)
            ->where('window', $window)
            ->firstOrFail();
    }
}
