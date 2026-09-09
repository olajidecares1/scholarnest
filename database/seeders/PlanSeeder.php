<?php

namespace Database\Seeders;

use App\Enums\PlanKey;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plan::updateOrCreate(
            ['key' => PlanKey::Basic],
            [
                'name' => 'Basic Plan',
                'tagline' => 'Perfect for schools that need to run their academics - results, attendance and report cards - without a public website.',
                'price_per_student_per_term' => 500,
                'max_teachers' => null,
                'features' => [
                    'School Management Dashboard',
                    'Student Management (Billed Per Student, Per Term)',
                    'Teacher & Staff Management (Unlimited Teacher Accounts)',
                    'Academics, Classes & Subjects',
                    'Examinations, Results & Report Cards',
                    'Result-Checking PIN System',
                    'Attendance Management',
                    'Timetable',
                    'Announcements & Notices',
                    'Mobile App Access',
                    'Support',
                ],
                'is_popular' => false,
                'sort_order' => 1,
            ]
        );

        Plan::updateOrCreate(
            ['key' => PlanKey::Standard],
            [
                'name' => 'Standard Plan',
                'tagline' => 'Get a professional school website and powerful features to engage with parents and the public.',

                // Per student now, like Basic, at twice the Basic rate. The
                // old ₦200,000-a-term fee is gone: both flat-fee columns are
                // nulled rather than left behind, because a stale 200000 in
                // price_per_term is exactly the number a future reader would
                // pick up by mistake.
                'price_per_student_per_term' => 1000,
                'price_monthly' => null,
                'price_per_term' => null,
                'max_teachers' => null,

                // UNCHANGED, deliberately. Only the way Standard is PRICED has
                // moved; everything it can do it still does.
                'features' => [
                    'Everything in Basic (No Teacher Account Limit)',
                    'Student, Parent & Teacher Portal Access',
                    'Assignments (Set, Collect & Mark Online)',
                    'Finance & Invoicing',
                    'Library Management',
                    'Transport & Hostel Management',
                    'Computer-Based Testing (CBT)',
                    'Public School Website',
                    'Subdomain (akademicnest.schoolname.com)',
                    'ID Card Management',
                    'Hero Slider',
                    'Gallery (Photos & Videos)',
                    'Events (Upcoming, Current, Past)',
                    'Contact Page',
                    'Misconduct Reporting (Photos & Videos)',
                    'SEO Optimization',
                    'SSL Certificate',
                    'Google Analytics',
                ],
                'is_popular' => true,
                'sort_order' => 2,
            ]
        );

        Plan::updateOrCreate(
            ['key' => PlanKey::Exclusive],
            [
                'name' => 'Exclusive Plan',
                'tagline' => 'All Standard features, plus premium benefits and your own custom domain.',
                'has_custom_pricing' => true,

                // The row STAYS, with every feature it has. Exclusive is not
                // being removed - it cannot be subscribed to yet, and that is
                // decided by PlanKey::isAvailableToSubscribe() rather than by
                // deleting anything here. Turning it on later is one line.
                'features' => [
                    'Everything in Standard',
                    'Custom Domain (schoolname.com) & Subdomains',
                    'Domain Verification & DNS Configuration Guide',
                    'Automatic SSL Certificate & HTTPS',
                    'Domain Status Monitoring',
                    'Default Subdomain Redirect',
                    'White-Label Experience',
                    'Premium Branding',
                    'Priority Support',
                    'Dedicated Account Manager',
                    'Advanced Analytics',
                    'More Storage & Resources',
                ],
                'is_popular' => false,
                'sort_order' => 3,
            ]
        );
    }
}
