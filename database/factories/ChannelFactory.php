<?php

namespace Database\Factories;

use App\Domains\Chat\Models\Channel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name'        => $name,
            'slug'        => Str::slug($name),
            'description' => $this->faker->sentence(),
            'type'        => 'public',
            'created_by'  => User::factory(),
        ];
    }

    public function private(): static
    {
        return $this->state(['type' => 'private']);
    }
}
