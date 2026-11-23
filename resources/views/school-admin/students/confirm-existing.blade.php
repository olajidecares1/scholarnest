{{-- "This child may already be registered": the School Admin's Add Student
     form. The page itself is the shared student-match-confirmation component. --}}
<x-dashboard-layout page-title="Is this the same student?" page-subtitle="A student/pupil with this name may already be registered at your school.">
    <x-student-match-confirmation
        :candidates="$candidates"
        :data="$data"
        :has-photo="$hasPhoto"
        :auto-generate="$autoGenerate"
        :confirm-url="route('students.existing.confirm', ['token' => $token])"
        :cancel-url="route('students.existing.cancel', ['token' => $token])"
    />
</x-dashboard-layout>
