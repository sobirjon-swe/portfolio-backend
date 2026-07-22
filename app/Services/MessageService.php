<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MessageService
{
    public function __construct(
        private readonly MessageRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, Message>
     */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Message
    {
        return $this->repository->create($data);
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
