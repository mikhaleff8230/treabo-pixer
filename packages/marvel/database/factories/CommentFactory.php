<?php

namespace Marvel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Marvel\Database\Models\Comment;
use Marvel\Database\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Marvel\Database\Models\Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commentable_type' => 'product',
            'commentable_id' => $this->faker->numberBetween(1, 100),
            'parent_id' => null,
            'body' => $this->faker->paragraph(3),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
        ];
    }

    /**
     * Указать, что комментарий одобрен
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    /**
     * Указать, что комментарий ожидает модерации
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Указать, что комментарий отклонен
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    /**
     * Указать, что это ответ на комментарий
     */
    public function reply(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }
}

