<?php

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel for a chat room — returns user info array on success, false to deny.
Broadcast::channel('channel.{channelId}', function ($user, int $channelId) {
    $channel = app(ChannelRepositoryInterface::class)->findById($channelId);

    if (! $channel || ! app(ChannelRepositoryInterface::class)->isMember($channel, $user)) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
