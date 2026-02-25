<?php

declare(strict_types=1);

namespace App\Domains\Chat\Repositories;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Models\Message;
use Illuminate\Pagination\LengthAwarePaginator;

interface MessageRepositoryInterface
{
    public function create(array $data): Message;

    /** @return LengthAwarePaginator<Message> */
    public function forChannel(Channel $channel, int $perPage = 50): LengthAwarePaginator;

    public function findById(int $id): ?Message;
}
