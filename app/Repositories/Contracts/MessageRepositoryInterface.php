<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

interface MessageRepositoryInterface
{
    /**
     * @return Collection<int, Message>
     */
    public function all(): Collection;

    public function findById(int $id): ?Message;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Message;

    public function delete(Message $message): void;
}
