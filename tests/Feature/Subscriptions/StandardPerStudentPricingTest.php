<?php

use App\Enums\PlanFeature;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentLicenceAllocation;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Standard is priced per pupil now, exactly as Basic is, and that is ALL that
 * changed about it. Its features, its portals and its permissions are
 * untouched; only the way it is sold has moved.
 *
 * Exclusive is Coming Soon: still built, still in the database, simply not for
 * sale yet.
 */
beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Storage::fake('local');

    $this->school = School::factory()->create(['name' => 'Greenfield Academy']);
    $this->admin = User::factory()->create(['school_id' => $this->school->id, 'role' => UserRole::SchoolAdmin]);
});

describe('the price comes from the plan record, never from the page', function () {
    test('Standard is one thousand a pupil and Basic is five hundred', function () {
        expect((float) Plan::where('key', PlanKey::Standard)->firstOrFail()->price_per_student_per_term)->toBe(1000.0)
            ->and((float) Plan::where('key', PlanKey::Basic)->firstOrFail()->price_per_student_per_term)->toBe(500.0);
    });

    test('the old flat term fee is gone from Standard entirely', function () {
        $standard = Plan::where('key', PlanKey::Standard)->firstOrFail();

        // A stale 200000 sitting in price_per_term is exactly the number a
        // future reader would pick up by mistake.
        expect($standard->price_per_term)->toBeNull()
            ->and($standard->price_monthly)->toBeNull();
    });

    test('the AkademicNest Team can change it, and the wizard follows', function () {
        Plan::where('key', PlanKey::Standard)->update(['price_per_student_per_term' => 1500]);

        $this->actingAs($this->admin)->post(route('subscriptions.choose-plan.store'), [
            'plan_id' => Plan::where('key', PlanKey::Standard)->firstOrFail()->id,
        ]);

        $this->actingAs($this->admin)->post(route('subscriptions.students.store'), ['students_count' => 10]);

        expect(session('subscription_wizard.amount'))->toBe(15000.0);
    });

    test('the figure is never taken from the browser', function () {
        $plan = Plan::where('key', PlanKey::Standard)->firstOrFail();

        $this->actingAs($this->admin)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);

        $this->actingAs($this->admin)->post(route('subscriptions.students.store'), [
            'students_count' => 80,
            // Ignored. What the school was shown is a quote; this is the charge.
            'amount' => 1,
        ]);

        expect(session('subscription_wizard.amount'))->toBe(80000.0);
    });
});

describe('Standard takes the same road as Basic', function () {
    test('choosing it leads to the student capacity step, not straight to billing', function () {
        $plan = Plan::where('key', PlanKey::Standard)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id])
            ->assertRedirect(route('subscriptions.students'));
    });

    test('it no longer asks for a billing cycle', function () {
        // There is one way to be billed now, so the monthly/per-term toggle
        // that used to be required here is gone.
        $plan = Plan::where('key', PlanKey::Standard)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id])
            ->assertSessionHasNoErrors();
    });

    test('eighty pupils comes to eighty thousand', function () {
        $plan = Plan::where('key', PlanKey::Standard)->firstOrFail();

        $this->actingAs($this->admin)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);

        $this->actingAs($this->admin)
            ->post(route('subscriptions.students.store'), ['students_count' => 80])
            ->assertRedirect(route('subscriptions.billing-details'));

        expect(session('subscription_wizard.students_count'))->toBe(80)
            ->and(session('subscription_wizard.amount'))->toBe(80000.0);
    });

    test('the capacity page shows the Standard rate', function () {
        $plan = Plan::where('key', PlanKey::Standard)->firstOrFail();

        $this->actingAs($this->admin)->post(route('subscriptions.choose-plan.store'), ['plan_id' => $plan->id]);

        $this->actingAs($this->admin)
            ->get(route('subscriptions.students'))
            ->assertOk()
            // Read off the plan record, not written into the page.
            ->assertSee('pricePerStudent: 1000', false);
    });
});

describe('the capacity is enforced', function () {
    test('a Standard school is capped at what it paid for', function () {
        $school = activateSchool($this->school, PlanKey::Standard);
        $school->activeSubscription->update(['students_count' => 100]);

        expect($school->fresh()->studentSlotLimit())->toBe(100);
    });

    test('the hundred-and-first student is refused', function () {
        $school = activateSchool($this->school, PlanKey::Standard);
        $school->activeSubscription->update(['students_count' => 2]);

        Student::factory()->count(2)->create(['school_id' => $school->id, 'is_active' => true]);

        $allocation = app(StudentLicenceAllocation::class);

        expect($allocation->remaining($school->fresh()))->toBe(0);

        // Refused in the service every path goes through, not in one
        // controller, so a bulk import cannot walk around it.
        //
        // Refusal is a null return rather than an exception: the callers show
        // limitReachedMessage() and let the school buy more, which is a better
        // answer than an error page. The thing that matters is that the work
        // never runs.
        $result = $allocation->withCapacity($school->fresh(), fn () => Student::factory()->create([
            'school_id' => $school->id, 'is_active' => true,
        ]));

        expect($result)->toBeNull()
            ->and(Student::where('school_id', $school->id)->count())->toBe(2)
            ->and($allocation->limitReachedMessage($school->fresh()))->toContain('Capacity Reached');
    });

    test('one more space, and the next student is allowed', function () {
        // The other half: the cap must be a cap, not a blanket refusal.
        $school = activateSchool($this->school, PlanKey::Standard);
        $school->activeSubscription->update(['students_count' => 3]);

        Student::factory()->count(2)->create(['school_id' => $school->id, 'is_active' => true]);

        $allocation = app(StudentLicenceAllocation::class);

        $result = $allocation->withCapacity($school->fresh(), fn () => Student::factory()->create([
            'school_id' => $school->id, 'is_active' => true,
        ]));

        expect($result)->not->toBeNull()
            ->and(Student::where('school_id', $school->id)->count())->toBe(3);
    });

    test('Exclusive stays uncapped', function () {
        $school = activateSchool(School::factory()->create(), PlanKey::Exclusive);

        expect($school->studentSlotLimit())->toBeNull();
    });
});

describe('buying more capacity later', function () {
    test('a Standard school may top up', function () {
        $school = activateSchool($this->school, PlanKey::Standard);
        $school->activeSubscription->update(['students_count' => 50]);

        $this->actingAs($this->admin)->get(route('subscription-top-up.create'))->assertOk();
    });

    test('and a Basic school still may', function () {
        $school = activateSchool($this->school, PlanKey::Basic);
        $school->activeSubscription->update(['students_count' => 50]);

        $this->actingAs($this->admin)->get(route('subscription-top-up.create'))->assertOk();
    });

    test('an Exclusive school may not - it is not sold per student', function () {
        activateSchool($this->school, PlanKey::Exclusive);

        $this->actingAs($this->admin)->get(route('subscription-top-up.create'))->assertForbidden();
    });
});

describe('Exclusive is coming soon', function () {
    test('the plan chooser marks it so and offers no way to pick it', function () {
        $this->actingAs($this->admin)
            ->get(route('subscriptions.choose-plan'))
            ->assertOk()
            ->assertSee('Coming Soon')
            // Its radio is not merely disabled, it is not rendered at all.
            ->assertDontSee('value="'.Plan::where('key', PlanKey::Exclusive)->firstOrFail()->id.'"', false);
    });

    test('and the backend refuses it however the form is edited', function () {
        // A disabled control is a suggestion; this is the rule. Posting the id
        // by hand is the whole reason this check exists.
        $exclusive = Plan::where('key', PlanKey::Exclusive)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('subscriptions.choose-plan.store'), ['plan_id' => $exclusive->id])
            ->assertSessionHasErrors('plan_id');

        expect(session('subscription_wizard.plan_id'))->toBeNull();
    });

    test('Basic and Standard remain available', function () {
        expect(PlanKey::Basic->isAvailableToSubscribe())->toBeTrue()
            ->and(PlanKey::Standard->isAvailableToSubscribe())->toBeTrue()
            ->and(PlanKey::Exclusive->isAvailableToSubscribe())->toBeFalse();
    });

    test('nothing about Exclusive was deleted', function () {
        // It stays built for later, the row, its features and its code are
        // all still here. Only its availability changed.
        $exclusive = Plan::where('key', PlanKey::Exclusive)->firstOrFail();

        expect($exclusive->features)->not->toBeEmpty()
            ->and($exclusive->features)->toContain('Custom Domain (schoolname.com) & Subdomains');
    });
});

describe('Standard is still Standard', function () {
    test('it keeps every feature it had', function () {
        // The critical guard. Changing how Standard is SOLD must never turn it
        // into Basic.
        $school = activateSchool($this->school, PlanKey::Standard);

        foreach ([
            PlanFeature::Website,
            PlanFeature::News,
            PlanFeature::Events,
            PlanFeature::Cbt,
            PlanFeature::Guardians,
            PlanFeature::Assignments,
            PlanFeature::Library,
            PlanFeature::Finance,
            PlanFeature::IdCards,
            PlanFeature::Timetable,
        ] as $feature) {
            expect($school->canUseFeature($feature))->toBeTrue("Standard lost {$feature->value}");
        }
    });

    test('and Basic still does not have them', function () {
        // The other half: Standard keeping its features only means something
        // if Basic still lacks them.
        $basic = activateSchool(School::factory()->create(), PlanKey::Basic);

        expect($basic->canUseFeature(PlanFeature::Website))->toBeFalse()
            ->and($basic->canUseFeature(PlanFeature::Cbt))->toBeFalse();
    });

    test('its feature list still advertises them', function () {
        expect(Plan::where('key', PlanKey::Standard)->firstOrFail()->features)
            ->toContain('Public School Website')
            ->toContain('Computer-Based Testing (CBT)')
            ->toContain('Student, Parent & Teacher Portal Access');
    });
});
