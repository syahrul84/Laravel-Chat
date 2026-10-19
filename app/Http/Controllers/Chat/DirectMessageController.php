<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Domains\Chat\Repositories\UserRepositoryInterface;
use App\Domains\Chat\Services\DirectMessageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StartDirectMessageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DirectMessageController extends Controller
{
    public function __construct(
        private readonly DirectMessageService $directMessageService,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function store(StartDirectMessageRequest $request): RedirectResponse
    {
        $channel = $this->directMessageService->start(
            from: $request->user(),
            to: $this->userRepository->findById($request->integer('user_id')),
        );

        return redirect()->route('channels.show', $channel);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:100'],
        ]);

        return response()->json(
            $this->userRepository->search($validated['q'], $request->user())
        );
    }
}
