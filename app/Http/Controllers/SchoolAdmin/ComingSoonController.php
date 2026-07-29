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
        'settings.index' => ['label' => 'Settings', 'description' => 'Manage your school\'s profile, branding, and preferences.'],
    ];

    public function show(Request $request): View
    {
        $module = self::MODULES[$request->route()->getName()] ?? ['label' => 'This feature', 'description' => 'This feature is on our roadmap.'];

        return view('school-admin.coming-soon', $module);
    }
}
