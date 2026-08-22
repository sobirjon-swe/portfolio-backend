<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Classifies a User-Agent string without pulling in a parsing library.
 *
 * A portfolio site sees a narrow slice of the web's agents — a handful of real
 * browsers plus the well-behaved crawlers that announce themselves — so a
 * curated table beats a general-purpose parser here: it stays readable, has no
 * regex catalogue to keep current, and costs one pass over a short string.
 *
 * Everything is matched case-insensitively against the lowercased agent.
 */
class UserAgentParser
{
    /**
     * Known agents, most specific first. Order matters: Edge and Opera both
     * carry "Chrome" in their agent, and Chrome carries "Safari", so the
     * derivatives have to be tested before the browser they impersonate.
     *
     * @var array<int, array{0: string, 1: string, 2: string}> [needle, name, category]
     */
    private const AGENTS = [
        // --- Search engines: traffic worth having ---
        ['googlebot', 'Googlebot', 'search_engine'],
        ['google-inspectiontool', 'Google Inspection', 'search_engine'],
        ['storebot-google', 'Googlebot', 'search_engine'],
        ['bingbot', 'Bingbot', 'search_engine'],
        ['bingpreview', 'Bingbot', 'search_engine'],
        ['yandexbot', 'YandexBot', 'search_engine'],
        ['yandex', 'YandexBot', 'search_engine'],
        ['duckduckbot', 'DuckDuckBot', 'search_engine'],
        ['baiduspider', 'Baiduspider', 'search_engine'],
        ['sogou', 'Sogou', 'search_engine'],
        ['applebot', 'Applebot', 'search_engine'],
        ['petalbot', 'PetalBot', 'search_engine'],
        ['seznambot', 'SeznamBot', 'search_engine'],

        // --- AI crawlers: separated out because whether to allow them is a
        //     policy decision, not a traffic one ---
        ['gptbot', 'GPTBot', 'ai_crawler'],
        ['oai-searchbot', 'OpenAI SearchBot', 'ai_crawler'],
        ['chatgpt-user', 'ChatGPT', 'ai_crawler'],
        ['claudebot', 'ClaudeBot', 'ai_crawler'],
        ['claude-web', 'ClaudeBot', 'ai_crawler'],
        ['anthropic', 'Anthropic', 'ai_crawler'],
        ['perplexitybot', 'PerplexityBot', 'ai_crawler'],
        ['perplexity', 'PerplexityBot', 'ai_crawler'],
        ['ccbot', 'CCBot', 'ai_crawler'],
        ['bytespider', 'Bytespider', 'ai_crawler'],
        ['amazonbot', 'Amazonbot', 'ai_crawler'],
        ['meta-externalagent', 'Meta AI', 'ai_crawler'],
        ['google-extended', 'Google Extended', 'ai_crawler'],
        ['diffbot', 'Diffbot', 'ai_crawler'],
        ['timpibot', 'Timpibot', 'ai_crawler'],
        ['youbot', 'YouBot', 'ai_crawler'],

        // --- Hostile or unsolicited scanning ---
        ['censys', 'Censys', 'scanner'],
        ['zgrab', 'zgrab', 'scanner'],
        ['masscan', 'masscan', 'scanner'],
        ['nmap', 'nmap', 'scanner'],
        ['l9scan', 'LeakIX', 'scanner'],
        ['leakix', 'LeakIX', 'scanner'],
        ['libredtail', 'libredtail', 'scanner'],
        ['expanse', 'Expanse', 'scanner'],
        ['internetmeasurement', 'InternetMeasure', 'scanner'],
        ['paloalto', 'Palo Alto', 'scanner'],
        ['palo alto', 'Palo Alto', 'scanner'],
        ['netcraft', 'Netcraft', 'scanner'],
        ['netsystemsresearch', 'NetSystems', 'scanner'],
        ['zoominfobot', 'ZoomInfo', 'scanner'],

        // --- SEO / marketing crawlers ---
        ['ahrefsbot', 'AhrefsBot', 'seo_crawler'],
        ['semrushbot', 'SemrushBot', 'seo_crawler'],
        ['mj12bot', 'MJ12bot', 'seo_crawler'],
        ['dotbot', 'DotBot', 'seo_crawler'],
        ['dataforseo', 'DataForSEO', 'seo_crawler'],
        ['serpstat', 'Serpstat', 'seo_crawler'],
        ['screaming frog', 'Screaming Frog', 'seo_crawler'],
        ['blexbot', 'BLEXBot', 'seo_crawler'],
        ['barkrowler', 'Barkrowler', 'seo_crawler'],

        // --- Link unfurlers: a person shared the link somewhere ---
        ['facebookexternalhit', 'Facebook', 'social'],
        ['facebookcatalog', 'Facebook', 'social'],
        ['twitterbot', 'Twitterbot', 'social'],
        ['linkedinbot', 'LinkedInBot', 'social'],
        ['telegrambot', 'TelegramBot', 'social'],
        ['whatsapp', 'WhatsApp', 'social'],
        ['slackbot', 'Slackbot', 'social'],
        ['discordbot', 'Discordbot', 'social'],
        ['redditbot', 'Redditbot', 'social'],
        ['pinterest', 'Pinterest', 'social'],
        ['embedly', 'Embedly', 'social'],
        ['vkshare', 'VK', 'social'],
        ['skypeuripreview', 'Skype', 'social'],

        // --- Scripts and monitors ---
        ['curl/', 'curl', 'tool'],
        ['wget', 'wget', 'tool'],
        ['python-requests', 'python-requests', 'tool'],
        ['python-urllib', 'python-urllib', 'tool'],
        ['aiohttp', 'aiohttp', 'tool'],
        ['scrapy', 'Scrapy', 'tool'],
        ['go-http-client', 'Go HTTP', 'tool'],
        ['okhttp', 'OkHttp', 'tool'],
        ['axios/', 'axios', 'tool'],
        ['node-fetch', 'node-fetch', 'tool'],
        ['postmanruntime', 'Postman', 'tool'],
        ['insomnia', 'Insomnia', 'tool'],
        ['headlesschrome', 'HeadlessChrome', 'tool'],
        ['phantomjs', 'PhantomJS', 'tool'],
        ['uptimerobot', 'UptimeRobot', 'tool'],
        ['pingdom', 'Pingdom', 'tool'],
        ['statuscake', 'StatusCake', 'tool'],
        ['betteruptime', 'BetterUptime', 'tool'],
    ];

    /**
     * Catch-alls for crawlers not named above. Checked only after the table
     * misses, so a named agent never lands in the generic bucket.
     *
     * @var array<int, string>
     */
    private const GENERIC_BOT_MARKERS = ['bot', 'crawler', 'crawl', 'spider', 'slurp', 'scraper', 'fetcher', 'monitoring'];

    /**
     * Browsers, most specific first — see the note on AGENTS about order.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const BROWSERS = [
        ['edg/', 'Edge'],
        ['edga/', 'Edge'],
        ['edgios/', 'Edge'],
        ['opr/', 'Opera'],
        ['opera', 'Opera'],
        ['yabrowser', 'Yandex Browser'],
        ['samsungbrowser', 'Samsung Internet'],
        ['ucbrowser', 'UC Browser'],
        ['vivaldi', 'Vivaldi'],
        ['brave', 'Brave'],
        ['firefox/', 'Firefox'],
        ['fxios/', 'Firefox'],
        ['crios/', 'Chrome'],
        ['chromium/', 'Chromium'],
        ['chrome/', 'Chrome'],
        ['safari/', 'Safari'],
        ['msie', 'Internet Explorer'],
        ['trident/', 'Internet Explorer'],
    ];

    /**
     * Platforms. iOS/iPadOS are tested before macOS because iPhone agents also
     * carry "Mac OS X".
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const PLATFORMS = [
        ['iphone', 'iOS'],
        ['ipad', 'iPadOS'],
        ['ipod', 'iOS'],
        ['android', 'Android'],
        ['cros', 'ChromeOS'],
        ['windows nt', 'Windows'],
        ['mac os x', 'macOS'],
        ['macintosh', 'macOS'],
        ['ubuntu', 'Linux'],
        ['linux', 'Linux'],
        ['freebsd', 'FreeBSD'],
    ];

    /**
     * @return array{is_bot: bool, agent: string, category: string, device: ?string, browser: ?string, platform: ?string}
     */
    public function parse(?string $userAgent): array
    {
        $ua = strtolower(trim((string) $userAgent));

        if ($ua === '' || $ua === '-') {
            // An absent User-Agent is never a real browser: every browser sends
            // one. Counting these as people would inflate the visitor number.
            return $this->result(true, 'Unknown', 'tool', null, null, null);
        }

        foreach (self::AGENTS as [$needle, $name, $category]) {
            if (str_contains($ua, $needle)) {
                return $this->result(true, $name, $category, null, null, null);
            }
        }

        foreach (self::GENERIC_BOT_MARKERS as $marker) {
            if (str_contains($ua, $marker)) {
                return $this->result(true, 'Other bot', 'other_bot', null, null, null);
            }
        }

        return $this->result(
            false,
            'Human',
            'human',
            $this->device($ua),
            $this->firstMatch($ua, self::BROWSERS) ?? 'Other',
            $this->firstMatch($ua, self::PLATFORMS) ?? 'Other',
        );
    }

    public function isBot(?string $userAgent): bool
    {
        return $this->parse($userAgent)['is_bot'];
    }

    /**
     * Tablets are checked first: an Android tablet's agent says "Android"
     * without "Mobile", while an Android phone says both.
     */
    private function device(string $ua): string
    {
        if (str_contains($ua, 'ipad') || (str_contains($ua, 'android') && ! str_contains($ua, 'mobile'))) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobi') || str_contains($ua, 'iphone') || str_contains($ua, 'ipod')
            || str_contains($ua, 'android') || str_contains($ua, 'windows phone')) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $table
     */
    private function firstMatch(string $ua, array $table): ?string
    {
        foreach ($table as [$needle, $name]) {
            if (str_contains($ua, $needle)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array{is_bot: bool, agent: string, category: string, device: ?string, browser: ?string, platform: ?string}
     */
    private function result(bool $isBot, string $agent, string $category, ?string $device, ?string $browser, ?string $platform): array
    {
        return [
            'is_bot' => $isBot,
            'agent' => $agent,
            'category' => $category,
            'device' => $device,
            'browser' => $browser,
            'platform' => $platform,
        ];
    }
}
