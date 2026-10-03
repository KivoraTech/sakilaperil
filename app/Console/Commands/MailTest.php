<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Engagement\Mail\Mailer;
use Illuminate\Console\Command;
use Throwable;

/** `php artisan sakila:mail-test you@example.org` — checks the MAIL_* (cPanel mailbox) settings. */
final class MailTest extends Command
{
    protected $signature = 'sakila:mail-test {to : Address to send the test email to}';

    protected $description = 'Send a test email using the MAIL_* settings';

    public function handle(): int
    {
        if (! Mailer::isConfigured()) {
            $this->error('Email is not configured: set MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD and MAIL_FROM_ADDRESS in .env.');

            return self::FAILURE;
        }
        try {
            Mailer::send((string) $this->argument('to'), 'Sakila CMS test email', "This is a test email from the Sakila website.\nIf you can read this, email sending works.\n");
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Sent. Check the inbox (and the spam folder) of '.$this->argument('to').'.');

        return self::SUCCESS;
    }
}
