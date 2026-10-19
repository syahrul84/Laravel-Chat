<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domains\Chat\Services\DirectMessageService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DirectMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private DirectMessageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DirectMessageService::class);
    }

    public function test_it_creates_a_two_member_direct_channel(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $dm = $this->service->start($alice, $bob);

        $this->assertSame('direct', $dm->type);
        $this->assertTrue($dm->hasMember($alice));
        $this->assertTrue($dm->hasMember($bob));
    }

    public function test_it_returns_the_same_channel_for_the_same_pair(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $first = $this->service->start($alice, $bob);
        $second = $this->service->start($bob, $alice);

        $this->assertSame($first->id, $second->id);
    }

    public function test_it_refuses_a_conversation_with_yourself(): void
    {
        $alice = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service->start($alice, $alice);
    }

    public function test_it_lists_conversations_with_the_other_person(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $this->service->start($alice, $bob);
        $this->service->start($carol, $alice);

        $partners = collect($this->service->listForUser($alice))->pluck('partner.name')->sort()->values()->all();

        $this->assertSame(collect([$bob->name, $carol->name])->sort()->values()->all(), $partners);
    }
}
