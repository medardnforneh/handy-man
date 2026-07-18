<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
final class NoteFactory extends Factory
{
    protected $model = Note::class;

    /**
     * @return array<model-property<Note>, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
