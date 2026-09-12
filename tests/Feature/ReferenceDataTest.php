<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\CbtExamBody;
use App\Models\Plan;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use App\Support\ReferenceData;
use Database\Seeders\PlanSeeder;

/**
 * Production's plans table was empty: plans only ever came from a seeder, and
 * a deploy runs migrations, not seeders. A registering school reached "Choose
 * Your Plan" and found nothing to choose.
 */
describe('an empty database is given its catalogues', function () {
    test('plans, subjects and the CBT catalogue are all put in place', function () {
        expect(Plan::count())->toBe(0);

        $seeded = ReferenceData::seedMissing();

        expect($seeded)->toBe(['plans', 'subjects', 'cbt'])
            ->and(Plan::pluck('key')->map->value->sort()->values()->all())->toBe(['basic', 'exclusive', 'standard'])
            ->and(Subject::count())->toBeGreaterThan(0)
            ->and(CbtExamBody::count())->toBeGreaterThan(0);
    });

    test('running it again changes nothing', function () {
        ReferenceData::seedMissing();

        expect(ReferenceData::seedMissing())->toBe([])
            ->and(Plan::count())->toBe(3);
    });
});

describe('a catalogue that already has rows is left alone', function () {
    test('a plan price changed in Super Admin survives', function () {
        (new PlanSeeder)->run();
        Plan::where('key', PlanKey::Basic)->update(['price_per_student_per_term' => 750]);

        $seeded = ReferenceData::seedMissing();

        expect($seeded)->not->toContain('plans')
            ->and((float) Plan::where('key', PlanKey::Basic)->value('price_per_student_per_term'))->toBe(750.0);
    });
});

describe('the plan chooser', function () {
    beforeEach(function () {
        $this->school = School::factory()->create();
        $this->admin = User::factory()->create(['school_id' => $this->school->id, 'role' => UserRole::SchoolAdmin]);
    });

    test('with no plans it says so instead of showing an empty page', function () {
        $this->actingAs($this->admin)
            ->get(route('subscriptions.choose-plan'))
            ->assertOk()
            ->assertSee('No subscription plans are available right now.');
    });

    test('once the catalogue is in place the plans are there to choose', function () {
        ReferenceData::seedMissing();

        $this->actingAs($this->admin)
            ->get(route('subscriptions.choose-plan'))
            ->assertOk()
            ->assertSee('Basic Plan')
            ->assertSee('Standard Plan')
            ->assertSee('name="plan_id"', false)
            ->assertDontSee('No subscription plans are available right now.');
    });
});
