<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MessageRepository implements MessageRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Message::query()->latest()->paginate($perPage);
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
