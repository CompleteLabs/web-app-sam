<?php

namespace Database\Factories;

use App\Models\TableView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TableView>
 */
class TableViewFactory extends Factory
{
    protected $model = TableView::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'filterable_type' => 'App\\Models\\User',
            'user_id' => User::factory(),
            'filters' => [
                'tableSearch' => $this->faker->optional()->word,
                'tableFilters' => $this->faker->optional()->randomElement([
                    ['status' => 'active'],
                    ['role' => 'admin'],
                    ['created_at' => ['from' => '2024-01-01']],
                ]),
                'tableSortColumn' => $this->faker->optional()->randomElement(['name', 'email', 'created_at']),
                'tableSortDirection' => $this->faker->optional()->randomElement(['asc', 'desc']),
            ],
            'is_public' => $this->faker->boolean(30), // 30% chance of being public
            'icon' => $this->faker->optional()->randomElement([
                'heroicon-o-eye',
                'heroicon-o-star',
                'heroicon-o-heart',
                'heroicon-o-bookmark',
            ]),
            'color' => $this->faker->optional()->randomElement([
                'primary',
                'secondary',
                'success',
                'warning',
                'danger',
            ]),
        ];
    }

    /**
     * Indicate that the table view is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the table view is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    /**
     * Create a table view with specific filters.
     */
    public function withFilters(array $filters): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => array_merge($attributes['filters'], $filters),
        ]);
    }

    /**
     * Create a table view for a specific filterable type.
     */
    public function forType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'filterable_type' => $type,
        ]);
    }
}
