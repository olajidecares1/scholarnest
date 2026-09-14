<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class MakeSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a AkademicNest Team account. There is no public registration route for this role by design.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = text(
            label: 'Full name',
            required: true,
            validate: fn (string $value) => Validator::make(['name' => $value], ['name' => ['required', 'string', 'max:255']])
                ->errors()->first('name'),
        );

        $email = text(
            label: 'Email address',
            required: true,
            validate: fn (string $value) => Validator::make(['email' => $value], [
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            ])->errors()->first('email'),
        );

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value) => Validator::make(['password' => $value], [
                'password' => ['required', 'string', 'min:12'],
            ])->errors()->first('password'),
        );

        $confirmation = password(
            label: 'Confirm password',
            required: true,
            validate: fn (string $value) => $value !== $password ? 'The passwords do not match.' : null,
        );

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'username' => User::generateUniqueUsernameFromEmail($email),
            'password' => Hash::make($password),
            'role' => UserRole::SuperAdmin,
            'school_id' => null,
        ]);

        // forceFill, because `email_verified_at` is not fillable and was being
        // silently dropped from the create() above, every account this
        // command has ever made came out unverified. Nothing depends on it
        // for a Super Admin today, since no super-admin route carries the
        // `verified` middleware, but an account created "verified" that is
        // not is a trap set for whoever adds that middleware later.
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->components->info("AkademicNest Team \"{$user->name}\" ({$user->email}) created successfully.");

        return self::SUCCESS;
    }
}
