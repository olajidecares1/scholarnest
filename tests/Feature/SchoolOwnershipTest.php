<?php

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A controller in miniature: nothing but the trait under test.
 */
function ownershipCheck(): object
{
    return new class
    {
        use AuthorizesSchoolOwnership;

        public function check(...$records): void
        {
            $this->authorizeSchoolOwnership(...$records);
        }
    };
}

function schoolAdminOf(?School $school): User
{
    return User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $school?->id,
    ]);
}

test('a record from the acting school passes', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $this->actingAs(schoolAdminOf($school));

    ownershipCheck()->check($student);
})->throwsNoExceptions();

test('a record from another school is refused', function () {
    $mine = School::factory()->create();
    $theirs = School::factory()->create();
    $theirStudent = Student::factory()->create(['school_id' => $theirs->id]);

    $this->actingAs(schoolAdminOf($mine));

    expect(fn () => ownershipCheck()->check($theirStudent))
        ->toThrow(HttpException::class);
});

test('every record is checked, not just the first', function () {
    $mine = School::factory()->create();
    $theirs = School::factory()->create();

    $this->actingAs(schoolAdminOf($mine));

    $ok = Student::factory()->create(['school_id' => $mine->id]);
    $notOk = Student::factory()->create(['school_id' => $theirs->id]);

    expect(fn () => ownershipCheck()->check($ok, $notOk))
        ->toThrow(HttpException::class);
});

test('an account with no school of its own sees nothing', function () {
    // The copies this replaced compared two values for equality, which passes
    // when both are null: an account whose school was deleted, looking at a
    // record whose school_id was never set. Unlikely, and a 403 either way.
    $this->actingAs(schoolAdminOf(null));

    $orphan = new Student(['school_id' => null]);

    expect(fn () => ownershipCheck()->check($orphan))
        ->toThrow(HttpException::class);
});

test('a null record is refused rather than skipped', function () {
    // Reached by traversing a relation that is not there:
    // authorizeSchoolOwnership($allocation->room->hostel).
    $school = School::factory()->create();

    $this->actingAs(schoolAdminOf($school));

    expect(fn () => ownershipCheck()->check(null))
        ->toThrow(HttpException::class);
});

test('no controller compares school ids inline any more', function () {
    // The finding was 45 hand-written copies of one rule. If a copy comes
    // back, it should come back as a failing test.
    $offenders = [];

    $controllers = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path('Http/Controllers'))
    );

    foreach ($controllers as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (preg_match('/school_id === (auth\(\)|\$request)->user\(\)->school_id/', $source)) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});
