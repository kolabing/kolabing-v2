<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendTransactionalEmail;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sends a Postmark connectivity test email through the real transactional
 * pipeline (job → {@see \App\Services\PostmarkClient}). Verifies Phase 0 + 1.
 *
 *   php artisan email:test daniel@serrawealth.com info@kolabing.com
 *   php artisan email:test foo@bar.com --queue   # enqueue instead of sending inline
 */
class SendTestEmail extends Command
{
    protected $signature = 'email:test
        {addresses* : One or more recipient email addresses}
        {--queue : Enqueue the job(s) instead of sending synchronously}';

    protected $description = 'Send a Postmark connectivity test email through the transactional email pipeline.';

    public function handle(): int
    {
        if (blank(config('services.postmark.key'))) {
            $this->error('POSTMARK_API_KEY is not set. Add it to .env (and MAIL_FROM_ADDRESS) before sending.');

            return self::FAILURE;
        }

        /** @var array<int, string> $addresses */
        $addresses = $this->argument('addresses');
        $from = config('services.postmark.from');
        $stream = config('services.postmark.message_stream');

        $this->info(sprintf('From: %s | stream: %s', $from, $stream));

        $subject = 'Kolabing — transactional email pipeline test';
        $html = $this->html();
        $text = $this->text();

        $failures = 0;

        foreach ($addresses as $address) {
            $job = SendTransactionalEmail::raw($address, $subject, $html, $text);

            try {
                if ($this->option('queue')) {
                    dispatch($job);
                    $this->line("  • queued → {$address}");
                } else {
                    dispatch_sync($job);
                    $this->info("  ✓ sent → {$address}");
                }
            } catch (Throwable $e) {
                $failures++;
                $this->error("  ✗ failed → {$address}: {$e->getMessage()}");
            }
        }

        if ($failures > 0) {
            $this->error("{$failures} send(s) failed. Check Postmark token, sender signature, and logs.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done. Check Postmark Activity and the inboxes.');

        return self::SUCCESS;
    }

    private function html(): string
    {
        return <<<'HTML'
            <!doctype html>
            <html>
              <body style="margin:0;background:#F7F8FA;font-family:Arial,Helvetica,sans-serif;color:#232323;">
                <div style="max-width:520px;margin:0 auto;padding:32px 24px;">
                  <div style="background:#FFD861;border-radius:16px;padding:20px 24px;text-align:center;">
                    <span style="font-size:22px;font-weight:bold;letter-spacing:0.5px;color:#232323;">KOLABING</span>
                  </div>
                  <div style="background:#ffffff;border-radius:16px;padding:28px 24px;margin-top:16px;">
                    <h1 style="font-size:20px;margin:0 0 12px;">Email pipeline is live ✅</h1>
                    <p style="font-size:15px;line-height:1.5;margin:0 0 12px;">
                      This is a connectivity test from the new Kolabing transactional email
                      system (Postmark). If you are reading this, sending works end to end:
                      configuration → queued job → Postmark API → your inbox.
                    </p>
                    <p style="font-size:14px;line-height:1.5;color:#666;margin:0;">
                      No action needed. Branded templates (welcome, password reset, collaboration
                      updates, etc.) come next.
                    </p>
                  </div>
                  <p style="font-size:12px;color:#999;text-align:center;margin:16px 0 0;">
                    Sent by Kolabing · automated test message
                  </p>
                </div>
              </body>
            </html>
            HTML;
    }

    private function text(): string
    {
        return "Kolabing — transactional email pipeline test\n\n"
            ."This is a connectivity test from the new Kolabing transactional email system "
            ."(Postmark). If you are reading this, sending works end to end: configuration → "
            ."queued job → Postmark API → your inbox.\n\n"
            ."No action needed. Branded templates come next.\n";
    }
}
