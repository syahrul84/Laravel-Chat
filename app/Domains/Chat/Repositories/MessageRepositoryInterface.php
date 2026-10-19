<?php

declare(strict_types=1);

namespace App\Domains\Chat\Repositories;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Models\Message;
use Illuminate\Pagination\LengthAwarePaginator;

interface MessageRepositoryInterface
{
    public function create(array $data): Message;

    /**
     * Page 1 holds the newest messages; each page's items are ordered oldest-first for display.
     * Messages with an id up to $afterId (a member's cleared history) are left out.
     *
     * @return LengthAwarePaginator<Message>
     */
    public function forChannel(Channel $channel, int $perPage = 50, ?int $afterId = null): LengthAwarePaginator;

    public function findById(int $id): ?Message;
}
