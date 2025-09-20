<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'session_id' => $this->faker->uuid,
            'author_role' => $this->faker->randomElement(['user', 'assistant']),
            'content' => $this->faker->sentence,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function forSession(string $sessionId): static
    {
        return $this->state(fn (): array => [
            'session_id' => $sessionId,
            'user_id' => null,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->getKey(),
            'session_id' => null,
        ]);
    }
}
