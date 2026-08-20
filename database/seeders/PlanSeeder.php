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
                'tagline' => 'Perfect for schools that need to manage results, attendance, and finance without a public website.',
                'price_per_student_per_term' => 500,
                'max_teachers' => 5,
                'features' => [
                    'School Management Dashboard',
                    'Student Management (Billed Per Student, Per Term)',
                    'Teacher & Staff Management (Up to 5 Teacher Accounts)',
                    'Result Upload, Management & Report Cards',
                    'Result-Checking PIN System',
                    'Attendance Management',
                    'Finance Management',
                    'Library Management',
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
                'price_monthly' => 50000,
                'price_per_term' => 200000,
                'features' => [
                    'Everything in Basic (No Teacher Account Limit)',
                    'Student, Parent & Teacher Portal Access',
                    'Computer-Based Testing (CBT)',
                    'Public School Website',
                    'Subdomain (edunest.schoolname.com)',
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
