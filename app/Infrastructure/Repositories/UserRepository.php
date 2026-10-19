<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domains\Chat\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function search(string $term, User $exclude, int $limit = 10): Collection
    {
        $escaped = addcslashes($term, '\\%_');

        return User::query()
            ->select(['id', 'name'])
            ->whereKeyNot($exclude->id)
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$escaped}%")
                ->orWhere('email', $term))
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
