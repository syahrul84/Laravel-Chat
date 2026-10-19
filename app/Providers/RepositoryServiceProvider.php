<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Chat\Repositories\ChannelRepositoryInterface;
use App\Domains\Chat\Repositories\MessageRepositoryInterface;
use App\Domains\Chat\Repositories\UserRepositoryInterface;
use App\Infrastructure\Repositories\ChannelRepository;
use App\Infrastructure\Repositories\MessageRepository;
use App\Infrastructure\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ChannelRepositoryInterface::class,
            ChannelRepository::class,
        );

        $this->app->bind(
            MessageRepositoryInterface::class,
            MessageRepository::class,
        );

        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class,
        );
    }
}
