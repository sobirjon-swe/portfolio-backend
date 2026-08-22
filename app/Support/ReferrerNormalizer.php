<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reduces a referrer URL to the source it represents.
 *
 * Only the host is kept. The full referring URL would say which page on
 * someone else's site a visitor came from — more than the dashboard needs, and
 * more personal data than is worth storing to answer "did they find me through
 * LinkedIn or through Google".
 *
 * Navigation inside the site is reported as "direct" rather than as a referral
 * from the site itself, which would otherwise dominate every referrer list.
 */
class ReferrerNormalizer
{
    /**
     * Hosts that mean the same source. Keys are matched as suffixes so
     * `www.google.com`, `google.co.uk` and `news.google.com` all collapse.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'google.' => 'Google',
        'bing.com' => 'Bing',
        'duckduckgo.com' => 'DuckDuckGo',
        'yandex.' => 'Yandex',
        'search.brave.com' => 'Brave Search',
        'ecosia.org' => 'Ecosia',
        'baidu.com' => 'Baidu',
        'linkedin.com' => 'LinkedIn',
        'lnkd.in' => 'LinkedIn',
        't.co' => 'X (Twitter)',
        'twitter.com' => 'X (Twitter)',
        'x.com' => 'X (Twitter)',
        'facebook.com' => 'Facebook',
        'fb.me' => 'Facebook',
        'instagram.com' => 'Instagram',
        'youtube.com' => 'YouTube',
        'youtu.be' => 'YouTube',
        't.me' => 'Telegram',
        'telegram.org' => 'Telegram',
        'reddit.com' => 'Reddit',
        'news.ycombinator.com' => 'Hacker News',
        'github.com' => 'GitHub',
        'stackoverflow.com' => 'Stack Overflow',
        'dev.to' => 'DEV',
        'medium.com' => 'Medium',
        'chat.openai.com' => 'ChatGPT',
        'chatgpt.com' => 'ChatGPT',
        'claude.ai' => 'Claude',
        'perplexity.ai' => 'Perplexity',
        'hh.uz' => 'hh.uz',
        'olx.uz' => 'OLX',
    ];

    /**
     * @param  array<int, string>  $ownHosts  Hosts belonging to this site.
     */
    public function __construct(private readonly array $ownHosts = []) {}

    /**
     * @return string|null The source label, or null for a direct visit.
     */
    public function normalize(?string $referrer): ?string
    {
        $referrer = trim((string) $referrer);

        if ($referrer === '' || $referrer === '-') {
            return null;
        }

        $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));

        if ($host === '') {
            return null;
        }

        $host = ltrim($host, '.');

        foreach ($this->ownHosts as $own) {
            $own = strtolower(ltrim($own, '.'));

            if ($own !== '' && ($host === $own || str_ends_with($host, '.'.$own))) {
                return null;
            }
        }

        foreach (self::ALIASES as $needle => $label) {
            if (str_contains($host, $needle)) {
                return $label;
            }
        }

        // Unknown source: keep the bare host, minus the www that adds nothing.
        return substr(preg_replace('/^www\./', '', $host) ?? $host, 0, 255);
    }
}
