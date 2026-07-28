<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComingSoonController extends Controller
{
    /**
     * Modules that are on the roadmap but not built yet, keyed by route name.
     *
     * @var array<string, array{label: string, description: string}>
     */
    private const MODULES = [
        'assignments.index' => ['label' => 'Assignments', 'description' => 'Set and track student assignments and homework.'],
        'events.index' => ['label' => 'Events', 'description' => 'Plan and publish a school calendar of events.'],
        'library.index' => ['label' => 'Library', 'description' => 'Track your library catalog and book loans.'],
        'finance.index' => ['label' => 'Finance', 'description' => 'Manage student fees, invoices, and payment records.'],
        'website.index' => ['label' => 'Website', 'description' => 'Build your school\'s public website, hero slider, and gallery.'],
        'settings.index' => ['label' => 'Settings', 'description' => 'Manage your school\'s profile, branding, and preferences.'],
    ];

    public function show(Request $request): View
    {
        $module = self::MODULES[$request->route()->getName()] ?? ['label' => 'This feature', 'description' => 'This feature is on our roadmap.'];

        return view('school-admin.coming-soon', $module);
    }
}
