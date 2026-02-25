<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Domains\Chat\Repositories\MessageRepositoryInterface;
use App\Domains\Chat\Services\ChannelService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CreateChannelRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function __construct(
        private readonly ChannelService $channelService,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly MessageRepositoryInterface $messageRepository,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Chat/Index', [
            'channels' => $this->channelService->listPublic(),
            'myChannels' => $this->channelService->listForUser($request->user()),
        ]);
    }

    public function store(CreateChannelRequest $request): RedirectResponse
    {
        $channel = $this->channelService->create(
            creator: $request->user(),
            name: $request->string('name')->toString(),
            type: $request->string('type', 'public')->toString(),
            description: $request->string('description')->toString() ?: null,
        );

        return redirect()->route('channels.show', $channel);
    }

    public function show(Request $request, Channel $channel): Response
    {
        if (! $this->channelRepository->isMember($channel, $request->user())) {
            abort(403);
        }

        return Inertia::render('Chat/Channel', [
            'channel' => $channel->load('creator'),
            'messages' => $this->messageRepository->forChannel($channel),
            'myChannels' => $this->channelService->listForUser($request->user()),
        ]);
    }

    public function join(Request $request, Channel $channel): RedirectResponse
    {
        try {
            $this->channelService->join($channel, $request->user());
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return redirect()->route('channels.show', $channel);
    }
}
