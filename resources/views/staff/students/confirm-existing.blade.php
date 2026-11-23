{{-- "This pupil may already be registered": a Class Teacher's My Class form.
     A teacher can update only a pupil already in one of their own classes;
     a match in another class is shown but left to the School Admin. --}}
<x-staff-layout page-title="Is this the same pupil?" page-subtitle="A student/pupil with this name may already be registered at your school.">
    <x-student-match-confirmation
        :candidates="$candidates"
        :data="$data"
        :has-photo="$hasPhoto"
        :auto-generate="$autoGenerate"
        :show-links="false"
        cancel-method="POST"
        :confirm-url="route('staff.students.existing.confirm', ['school' => $school, 'token' => $token])"
        :cancel-url="route('staff.students.existing.cancel', ['school' => $school, 'token' => $token])"
    />
</x-staff-layout>
