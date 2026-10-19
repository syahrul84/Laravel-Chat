<?php

declare(strict_types=1);

namespace App\Domains\Chat\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    /**
     * Users whose name contains the term (or whose email matches it exactly), excluding one user.
     *
     * @return Collection<int, User>
     */
    public function search(string $term, User $exclude, int $limit = 10): Collection;
}
