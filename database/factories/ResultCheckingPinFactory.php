<?php

namespace Database\Factories;

use App\Enums\ExamTerm;
use App\Enums\ResultCheckingPinStatus;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultCheckingPin>
 */
class ResultCheckingPinFactory extends Factory
{
    /**
     * The plain token of the most recently built row.
     *
     * A token exists in plain text only at the moment it is created, so a test
     * that needs to redeem one has to be handed it there and then. Reading it
     * back off the model would work through the encrypted column, but going
     * through this property keeps tests honest about the fact that the plain
     * value is not normally recoverable.
     */
    public static ?string $lastPlainToken = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A token carries its own session and term in its first five
        // characters, so the factory has to say which - there is no such thing
        // as a token that belongs to no term.
        $session = fake()->randomElement(['2024/2025', '2025/2026']);
        $term = fake()->randomElement(ExamTerm::cases());

        $plain = ResultCheckingPin::generatePlainToken($session, $term);

        self::$lastPlainToken = $plain;

        return [
            'school_id' => School::factory(),
            'examination_id' => Examination::factory(),
            'bound_student_id' => Student::factory(),
            'token_hash' => ResultCheckingPin::hashToken($plain),
            'token_encrypted' => ResultCheckingPin::normaliseToken($plain),
            'status' => ResultCheckingPinStatus::Active,
            'max_uses' => 5,
            'uses_count' => 0,
            'generated_by' => User::factory(),
            'issued_at' => now(),
        ];
    }

    /**
     * A token created but never bound - the shape tokens had before they were
     * required to name their student and result up front. Must be refused.
     */
    public function unissued(): static
    {
        return $this->state(fn (array $attributes): array => [
            'bound_student_id' => null,
            'examination_id' => null,
            'issued_at' => null,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ResultCheckingPinStatus::Revoked,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ResultCheckingPinStatus::Suspended,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ResultCheckingPinStatus::Exhausted,
            'uses_count' => 5,
            'max_uses' => 5,
        ]);
    }
}
