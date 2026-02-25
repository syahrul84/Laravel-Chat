<?php

namespace Database\Factories;

use App\Domains\Chat\Models\Channel;
use App\Domains\Chat\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'sender_id' => User::factory(),
            'channel_id' => Channel::factory(),
            'content' => $this->faker->sentence(),
            'read_at' => null,
        ];
    }
}
