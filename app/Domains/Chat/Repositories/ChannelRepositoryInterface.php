<?php

declare(strict_types=1);

namespace App\Domains\Chat\Repositories;

use App\Domains\Chat\Models\Channel;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface ChannelRepositoryInterface
{
    public function create(array $data): Channel;

    public function findById(int $id): ?Channel;

    public function findBySlug(string $slug): ?Channel;

    /** @return LengthAwarePaginator<Channel> */
    public function listPublic(int $perPage = 20): LengthAwarePaginator;

    /** @return LengthAwarePaginator<Channel> */
    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator;

    public function addMember(Channel $channel, User $user): void;

    public function removeMember(Channel $channel, User $user): void;

    public function isMember(Channel $channel, User $user): bool;
}
