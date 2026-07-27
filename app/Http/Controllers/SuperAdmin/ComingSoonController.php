<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComingSoonController extends Controller
{
    /**
     * @var array<string, array{title: string, description: string, features: list<string>}>
     */
    private const SECTIONS = [
        'reports' => [
            'title' => 'Reports',
            'description' => 'Review community misconduct submissions and platform reports.',
            'features' => [
                'Misconduct report review queue',
                'Incident reference number tracking',
                'Media attachment review (photos & video)',
                'Status workflow: new, reviewing, resolved',
            ],
        ],
        'analytics' => [
            'title' => 'Analytics',
            'description' => 'Monitor visitor traffic and platform-wide analytics.',
            'features' => [
                'Visitor timeline and traffic sources',
                'Geographic and device breakdowns',
                'Per-school website analytics',
                'Search, social, and referral traffic',
            ],
        ],
        'communications' => [
            'title' => 'Communications',
            'description' => 'Manage inbound contact messages and outbound announcements.',
            'features' => [
                'Contact School message inbox',
                'Newsletter subscriber management',
                'Platform-wide announcements',
                'Email notification templates',
            ],
        ],
        'support-tickets' => [
            'title' => 'Support Tickets',
            'description' => 'Track and resolve support requests from schools.',
            'features' => [
                'Ticket queue with priority levels',
                'Assign tickets to team members',
                'Status tracking (open, in progress, resolved)',
                'Response time monitoring',
            ],
        ],
        'cms' => [
            'title' => 'CMS',
            'description' => 'Manage platform-wide content: pages, blog, testimonials, and more.',
            'features' => [
                'Blog posts and hero slider',
                'Testimonials, FAQ, services, and team pages',
                'About, Privacy, and Terms pages',
                'Social media links',
            ],
        ],
        'themes' => [
            'title' => 'Themes',
            'description' => 'Customize the platform and school portal appearance.',
            'features' => [
                'Color palette and branding presets',
                'Per-school theme overrides',
                'Logo and favicon manager',
                'Live theme preview',
            ],
        ],
        'audit-logs' => [
            'title' => 'Audit Logs',
            'description' => 'Review login history, activity trails, and suspicious activity.',
            'features' => [
                'Login and authentication history',
                'Admin activity trail',
                'Application error logs',
                'Suspicious activity alerts',
            ],
        ],
    ];

    public function show(Request $request): View
    {
        $key = str($request->route()->getName())
            ->after('super-admin.')
            ->before('.index')
            ->toString();

        return view('super-admin.coming-soon', [
            'section' => self::SECTIONS[$key] ?? [
                'title' => 'Coming Soon',
                'description' => 'This section is not built yet.',
                'features' => [],
            ],
        ]);
    }
}
