<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes "something happened" alerts to a private Telegram chat.
 *
 * The contact page has always promised that every enquiry reaches me on
 * Telegram straight away; until now nothing actually sent it, so a message
 * sat unread in the admin panel until someone thought to look.
 *
 * Delivery is best-effort and deliberately synchronous: there is no queue
 * worker on the server, so a queued job would never run. A five-second
 * timeout and a swallowed failure keep a Telegram outage from turning a
 * visitor's successful submission into an error page — the record is already
 * saved by the time this is called, and a missed alert is recoverable, a lost
 * enquiry is not.
 */
class TelegramNotifier
{
    private const API = 'https://api.telegram.org';

    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly ?string $botToken,
        private readonly ?string $chatId,
    ) {}

    /**
     * Whether credentials are present. Absent ones are a valid state — local
     * and CI runs have none — so nothing is logged when they are missing.
     */
    public function isConfigured(): bool
    {
        return filled($this->botToken) && filled($this->chatId);
    }

    /**
     * @param  array<string, string>  $fields  Label => value, rendered as lines.
     */
    public function notify(string $title, array $fields = [], ?string $body = null): bool
    {
        $lines = ['<b>'.self::escape($title).'</b>'];

        foreach ($fields as $label => $value) {
            if ($value === '') {
                continue;
            }

            $lines[] = self::escape($label).': '.self::escape($value);
        }

        if (filled($body)) {
            $lines[] = '';
            $lines[] = self::escape($body);
        }

        return $this->send(implode("\n", $lines));
    }

    public function send(string $text): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->post(self::API."/bot{$this->botToken}/sendMessage", [
                    'chat_id' => $this->chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                // The token is in the URL, never in the log line.
                Log::warning('Telegram notification rejected', [
                    'status' => $response->status(),
                    'description' => $response->json('description'),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Telegram notification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Escape the three characters Telegram's HTML parse mode reserves. Without
     * this a message containing "<" is rejected as malformed markup.
     */
    private static function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
