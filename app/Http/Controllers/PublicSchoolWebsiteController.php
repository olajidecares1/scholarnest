<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\View\View;

class PublicSchoolWebsiteController extends Controller
{
    public function show(School $school): View
    {
        $website = $school->website;

        abort_unless($website && $website->is_published, 404);

        return view('public.school-website', [
            'school' => $school,
            'website' => $website,
            'galleryImages' => $school->galleryImages,
        ]);
    }
}
