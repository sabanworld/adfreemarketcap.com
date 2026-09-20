<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PostmarkMailerTest extends TestCase
{
    public function test_postmark_sends_on_the_notifications_message_stream(): void
    {
        config(['services.postmark.token' => 'test-token']);

        $transport = Mail::mailer('postmark')->getSymfonyTransport();

        $this->assertSame('notifications', config('mail.mailers.postmark.message_stream_id'));
        $this->assertStringContainsString('message_stream=notifications', (string) $transport);
    }
}
