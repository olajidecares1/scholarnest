<?php

namespace Database\Factories;

use App\Models\AdminRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdminRole>
 */
class AdminRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'permissions' => fake()->randomElements(array_keys(AdminRole::PERMISSIONS), 3),
        ];
    }
}
