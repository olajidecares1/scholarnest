<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Assignment;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolNotice;
use App\Models\Student;
use App\Models\Subscription;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
});

test('the dashboard shows a module grid with links to every section', function () {
    $response = $this->actingAs($this->student, 'student')->get(route('student.dashboard', $this->school));

    $response->assertStatus(200);

    foreach ([
        'student.profile', 'student.timetable', 'student.subjects', 'student.assignments.index',
        'student.results.index', 'student.attendance.index', 'student.library.index',
        'student.cbt-practice.index', 'student.messages.index',
        'student.notifications.index', 'student.co-curricular.index', 'student.settings.index', 'student.help.index',
    ] as $routeName) {
        $response->assertSee(route($routeName, $this->school), false);
    }
});

test('the assignments card shows a badge with the pending count', function () {
    Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'due_date' => now()->addWeek()]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertStatus(200)
        ->assertSeeInOrder(['Assignments', '1']);
});

test('the dashboard shows recent notices for the student\'s class and school-wide notices', function () {
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'Term Begins', 'class_name' => null]);
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'Other Class Notice', 'class_name' => 'JSS 2']);

    $this->actingAs($this->student, 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertStatus(200)
        ->assertSee('Term Begins')
        ->assertDontSee('Other Class Notice');
});
