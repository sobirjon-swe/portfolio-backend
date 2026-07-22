<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MessageRepository implements MessageRepositoryInterface
{
    public function all(): Collection
    {
        return Message::query()->latest()->get();
    }

    public function findById(int $id): ?Message
    {
        return Message::query()->find($id);
    }

    public function create(array $data): Message
    {
        return Message::query()->create($data);
    }

    public function delete(Message $message): void
    {
        $message->delete();
    }
}
