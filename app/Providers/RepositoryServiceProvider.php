<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Domains\Chat\Repositories\ChannelRepositoryInterface::class,
            \App\Infrastructure\Repositories\ChannelRepository::class,
        );

        $this->app->bind(
            \App\Domains\Chat\Repositories\MessageRepositoryInterface::class,
            \App\Infrastructure\Repositories\MessageRepository::class,
        );
    }
}
