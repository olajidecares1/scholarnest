<x-dashboard-layout page-title="Custom Domain" page-subtitle="Connect your school's own branded domain instead of the default EduNest link.">
    @include('school-admin.custom-domain._wizard', ['domains' => $domains, 'hasCustomDomainAccess' => $hasCustomDomainAccess])
</x-dashboard-layout>
