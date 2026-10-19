<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Repositories\MessageRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(array $data): Message
    {
        return Message::create($data);
    }

    public function forChannel(Channel $channel, int $perPage = 50, ?int $afterId = null): LengthAwarePaginator
    {
        $page = $channel->messages()
            ->with('sender:id,name')
            ->when($afterId, fn ($query) => $query->where('id', '>', $afterId))
            ->latest()
            ->latest('id')
            ->paginate($perPage);

        return $page->setCollection($page->getCollection()->reverse()->values());
    }

    public function findById(int $id): ?Message
    {
        return Message::find($id);
    }
}
