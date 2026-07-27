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
