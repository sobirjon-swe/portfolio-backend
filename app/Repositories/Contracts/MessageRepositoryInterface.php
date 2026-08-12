<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MessageRepositoryInterface
{
    /**
     * Newest inquiries first, one page at a time.
     *
     * Unlike the owner-authored content tables, this one is fed by a public
     * endpoint and grows with traffic (and spam), so it is never loaded whole.
     *
     * @return LengthAwarePaginator<int, Message>
     */
    public function paginate(int $perPage): LengthAwarePaginator;

    public function findById(int $id): ?Message;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Message;

    public function delete(Message $message): void;
}
