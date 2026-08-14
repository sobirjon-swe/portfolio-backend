<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Recommendation;
use App\Support\IpHasher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class RecommendationService
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly IpHasher $ipHasher,
        private readonly TelegramNotifier $telegram,
    ) {}

    /**
     * What the public sees: approved only, newest first.
     *
     * @return Collection<int, Recommendation>
     */
    public function listApproved(): Collection
    {
        return Recommendation::query()->approved()->latest()->get();
    }

    /**
     * A visitor vouching for me. Invisible until approved.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data, ?string $ipAddress): Recommendation
    {
        $recommendation = Recommendation::query()->create([
            ...$data,
            'is_approved' => false,
            'ip_hash' => $this->ipHasher->hash($ipAddress),
        ]);

        // Without this it waits for someone to open the moderation queue, and
        // a recommendation left unpublished for a week is a wasted one.
        $this->telegram->notify('⭐ Yangi tavsiyanoma (tasdiq kutmoqda)', [
            'Kim' => $recommendation->author_name,
            'Lavozim' => (string) ($recommendation->author_role ?? ''),
            'Kompaniya' => (string) ($recommendation->author_company ?? ''),
            'Munosabat' => $recommendation->relationship,
        ], $recommendation->body);

        return $recommendation;
    }

    /**
     * Admin listing, including the ones still waiting.
     *
     * @param  'pending'|'approved'|'all'  $status
     * @return LengthAwarePaginator<int, Recommendation>
     */
    public function listForModeration(string $status = 'all', ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage ?? self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE));

        return Recommendation::query()
            ->when($status === 'pending', fn ($query) => $query->where('is_approved', false))
            ->when($status === 'approved', fn ($query) => $query->where('is_approved', true))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Entered by me rather than submitted — one I already received elsewhere,
     * so it goes straight up unless I say otherwise.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Recommendation
    {
        return Recommendation::query()->create([
            'is_approved' => true,
            ...$data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Recommendation $recommendation, array $data): Recommendation
    {
        $recommendation->update($data);

        return $recommendation->refresh();
    }

    public function delete(Recommendation $recommendation): void
    {
        $recommendation->delete();
    }

    public function pendingCount(): int
    {
        return Recommendation::query()->where('is_approved', false)->count();
    }
}
