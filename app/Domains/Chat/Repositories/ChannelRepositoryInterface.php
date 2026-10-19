<?php

declare(strict_types=1);

namespace App\Domains\Chat\Repositories;

use App\Domains\Chat\Models\Channel;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ChannelRepositoryInterface
{
    public function create(array $data): Channel;

    public function findById(int $id): ?Channel;

    public function findBySlug(string $slug): ?Channel;

    /** @return LengthAwarePaginator<Channel> */
    public function listPublic(int $perPage = 20): LengthAwarePaginator;

    /**
     * Public and private channels the user has joined (direct messages excluded).
     *
     * @return LengthAwarePaginator<Channel>
     */
    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator;

    public function findByDirectKey(string $key): ?Channel;

    /**
     * The user's direct-message channels, newest first, with members (id, name) loaded.
     *
     * @return Collection<int, Channel>
     */
    public function listDirectForUser(User $user): Collection;

    /**
     * Every public/private channel the user has joined, for the sidebar (id, name, type).
     *
     * @return Collection<int, Channel>
     */
    public function listSidebarChannels(User $user): Collection;

    /** Adds the user if not already a member; existing history counts as read. */
    public function addMember(Channel $channel, User $user): void;

    /**
     * Members ordered by name, with only id and name.
     *
     * @return Collection<int, User>
     */
    public function members(Channel $channel): Collection;

    /** @return list<int> */
    public function memberIds(Channel $channel): array;

    public function markRead(Channel $channel, User $user): void;

    /**
     * Messages from others the user has not read, per channel (hidden conversations excluded).
     *
     * @return array<int, int> channel id => count
     */
    public function unreadCounts(User $user): array;

    /** Messages up to this id are hidden from the user (null = nothing cleared). */
    public function clearedMessageId(Channel $channel, User $user): ?int;

    /** Hides a conversation from the user's sidebar and clears its history for them only. */
    public function hideAndClear(Channel $channel, User $user): void;

    /** Shows a hidden conversation again, for one member or (without a user) for all of them. */
    public function unhide(Channel $channel, ?User $user = null): void;

    public function removeMember(Channel $channel, User $user): void;

    public function isMember(Channel $channel, User $user): bool;
}
