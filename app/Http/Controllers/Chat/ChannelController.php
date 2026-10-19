<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Domains\Chat\Services\ChannelService;
use App\Domains\Chat\Services\DirectMessageService;
use App\Domains\Chat\Services\MessageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CreateChannelRequest;
use App\Http\Requests\Chat\InviteMemberRequest;
use App\Models\User;
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
        private readonly MessageService $messageService,
        private readonly DirectMessageService $directMessageService,
    ) {}

    // Landing page after login; the shared sidebar holds the user's conversations
    public function home(): Response
    {
        return Inertia::render('Chat/Home');
    }

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

        $messages = $this->messageService->getMessages($channel, $request->user());
        $this->messageService->markRead($channel, $request->user());

        return Inertia::render('Chat/Channel', [
            'channel' => $channel->load('creator:id,name'),
            'messages' => $messages,
            'members' => $this->channelService->members($channel),
            'partner' => $channel->isDirect()
                ? $this->directMessageService->partnerOf($channel, $request->user())
                : null,
        ]);
    }

    public function close(Request $request, Channel $channel): RedirectResponse
    {
        $this->channelService->close($channel, $request->user());

        return redirect()->route('chat.index');
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

    public function leave(Request $request, Channel $channel): RedirectResponse
    {
        $this->channelService->leave($channel, $request->user());

        return redirect()->route('channels.index');
    }

    public function invite(InviteMemberRequest $request, Channel $channel): RedirectResponse
    {
        try {
            $this->channelService->invite(
                channel: $channel,
                inviter: $request->user(),
                invitee: User::where('email', $request->string('email')->toString())->firstOrFail(),
            );
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return redirect()->route('channels.show', $channel)->with('success', 'Member added.');
    }
}
