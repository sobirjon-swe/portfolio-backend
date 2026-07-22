<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class LeetCodeController extends Controller
{
    private const QUERY = <<<'GQL'
    query($u: String!) {
      matchedUser(username: $u) {
        username
        profile { ranking }
        submitStatsGlobal { acSubmissionNum { difficulty count } }
      }
      allQuestionsCount { difficulty count }
    }
    GQL;

    /**
     * Public: proxied + cached LeetCode stats for the configured username.
     */
    public function stats(): JsonResponse
    {
        $username = (string) config('services.leetcode.username');

        if ($username === '') {
            return response()->json(['message' => 'LeetCode username sozlanmagan.'], Response::HTTP_NOT_FOUND);
        }

        $stats = Cache::remember("leetcode:{$username}", now()->addHours(6), function () use ($username) {
            $request = Http::timeout(10)
                ->withHeaders(['Referer' => 'https://leetcode.com', 'Content-Type' => 'application/json']);

            // Windows dev PHP often lacks a configured CA bundle (curl.cainfo).
            // Production (Linux) has one, so only skip verification locally.
            if (app()->isLocal()) {
                $request = $request->withoutVerifying();
            }

            $response = $request->post('https://leetcode.com/graphql', [
                'query' => self::QUERY,
                'variables' => ['u' => $username],
            ]);

            $user = $response->json('data.matchedUser');

            if (! $response->successful() || $user === null) {
                return null;
            }

            return [
                'username' => $user['username'],
                'ranking' => $user['profile']['ranking'] ?? null,
                'solved' => $this->pluckCounts($user['submitStatsGlobal']['acSubmissionNum'] ?? []),
                'total' => $this->pluckCounts($response->json('data.allQuestionsCount') ?? []),
            ];
        });

        if ($stats === null) {
            Cache::forget("leetcode:{$username}");

            return response()->json(['message' => 'LeetCode ma’lumotini olib bo‘lmadi.'], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json(['data' => $stats]);
    }

    /**
     * Turn LeetCode's [{difficulty, count}] into ['all'=>..,'easy'=>..,..].
     *
     * @param  array<int, array{difficulty: string, count: int}>  $rows
     * @return array<string, int>
     */
    private function pluckCounts(array $rows): array
    {
        $out = ['all' => 0, 'easy' => 0, 'medium' => 0, 'hard' => 0];

        foreach ($rows as $row) {
            $key = strtolower($row['difficulty'] ?? '');
            if (array_key_exists($key, $out)) {
                $out[$key] = (int) $row['count'];
            }
        }

        return $out;
    }
}
