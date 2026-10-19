<?php

declare(strict_types=1);

namespace App\Domains\Chat\Services;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Models\User;

/**
 * Builds the sidebar shown on every chat page: joined channels and direct messages, with unread counts.
 */
class SidebarService
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channels,
        private readonly DirectMessageService $directMessages,
    ) {}

    /**
     * @return array{
     *     channels: list<array{id: int, name: string, type: string, unread: int}>,
     *     directMessages: list<array{id: int, partner: array{id: int, name: string}|null, unread: int}>
     * }
     */
    public function for(User $user): array
    {
        $unread = $this->channels->unreadCounts($user);

        return [
            'channels' => $this->channels->listSidebarChannels($user)
                ->map(fn (Channel $channel) => [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'type' => $channel->type,
                    'unread' => $unread[$channel->id] ?? 0,
                ])
                ->all(),
            'directMessages' => array_map(
                fn (array $dm) => [...$dm, 'unread' => $unread[$dm['id']] ?? 0],
                $this->directMessages->listForUser($user),
            ),
        ];
    }
}
