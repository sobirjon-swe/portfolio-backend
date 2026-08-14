<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MessageService
{
    /**
     * Rows per page when the caller does not ask for a specific size.
     */
    public const DEFAULT_PER_PAGE = 20;

    /**
     * Upper bound so a crafted ?per_page= cannot pull the whole table.
     */
    public const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly MessageRepositoryInterface $repository,
        private readonly TelegramNotifier $telegram,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Message>
     */
    public function list(?int $perPage = null): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage ?? self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE));

        return $this->repository->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Message
    {
        $message = $this->repository->create($data);

        // After the save, never before: the visitor's submission must succeed
        // whether or not the alert gets through.
        $this->telegram->notify('📬 Yangi xabar', [
            'Ism' => $message->name,
            'Email' => $message->email,
            'Budjet' => (string) ($message->budget ?? ''),
        ], $message->body);

        return $message;
    }

    public function delete(int $id): void
    {
        $message = $this->repository->findById($id);

        if ($message === null) {
            throw (new ModelNotFoundException)->setModel(Message::class, [$id]);
        }

        $this->repository->delete($message);
    }
}
