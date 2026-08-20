<?php

namespace App\Http\Controllers;

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\SchoolWebsite;
use Illuminate\View\View;

class PublicSchoolWebsiteController extends Controller
{
    public function show(School $school): View
    {
        $website = $this->publishedWebsite($school);

        return view('public.school-website', [
            'school' => $school,
            'website' => $website,
            'heroSlides' => $school->heroSlides,
            'galleryImages' => $school->galleryImages()->take(6)->get(),
            'academicLevels' => $school->academicLevels()->with('classes')->get(),
            'teachers' => $school->staff()->where('role', StaffRole::Teacher)->where('is_active', true)->take(10)->get(),
            'upcomingEvents' => $school->events()->where('starts_at', '>=', now())->orderBy('starts_at')->take(5)->get(),
            'latestNews' => $school->newsPosts()->where('is_published', true)->orderByDesc('published_at')->take(3)->get(),
            'openJobs' => $school->jobPostings()->where('is_active', true)->orderByDesc('posted_at')->take(3)->get(),
            'testimonials' => $school->testimonials()->where('is_active', true)->orderBy('sort_order')->take(6)->get(),
            'facilities' => $school->facilities()->take(6)->get(),
        ]);
    }

    public function admissions(School $school): View
    {
        $website = $this->publishedWebsite($school);

        return view('public.school-admissions', [
            'school' => $school,
            'website' => $website,
        ]);
    }

    public function about(School $school): View
    {
        $website = $this->publishedWebsite($school);

        return view('public.school-about', [
            'school' => $school,
            'website' => $website,
        ]);
    }

    public function contact(School $school): View
    {
        $website = $this->publishedWebsite($school);

        return view('public.school-contact', [
            'school' => $school,
            'website' => $website,
        ]);
    }

    public function facilities(School $school): View
    {
        $website = $this->publishedWebsite($school);

        return view('public.school-facilities-index', [
            'school' => $school,
            'website' => $website,
            'facilities' => $school->facilities,
        ]);
    }

    public function news(School $school): View
    {
        $website = $this->publishedWebsite($school);

        return view('public.school-news-index', [
            'school' => $school,
            'website' => $website,
            'posts' => $school->newsPosts()->where('is_published', true)->orderByDesc('published_at')->paginate(9),
        ]);
    }

    public function newsShow(School $school, NewsPost $post): View
    {
        $website = $this->publishedWebsite($school);

        abort_unless($post->school_id === $school->id && $post->is_published, 404);

        return view('public.school-news-show', [
            'school' => $school,
            'website' => $website,
            'post' => $post,
            'otherPosts' => $school->newsPosts()->where('is_published', true)->whereKeyNot($post->id)->orderByDesc('published_at')->take(3)->get(),
        ]);
    }

    public function events(School $school): View
    {
        $website = $this->publishedWebsite($school);

        return view('public.school-events-index', [
            'school' => $school,
            'website' => $website,
            'events' => $school->events()->where('starts_at', '>=', now())->orderBy('starts_at')->paginate(12),
        ]);
    }

    public function careers(School $school): View
    {
        $website = $this->publishedWebsite($school);

        return view('public.school-careers-index', [
            'school' => $school,
            'website' => $website,
            'jobs' => $school->jobPostings()->where('is_active', true)->orderByDesc('posted_at')->paginate(12),
        ]);
    }

    public function gallery(School $school): View
    {
        $website = $this->publishedWebsite($school);

        return view('public.school-gallery-index', [
            'school' => $school,
            'website' => $website,
            'galleryImages' => $school->galleryImages()->paginate(24),
        ]);
    }

    /**
     * Re-checked on every request, not just when the site was published -
     * otherwise a school that downgrades from Standard/Exclusive to Basic
     * (which has no front-facing website) would keep serving its old public
     * site forever.
     */
    private function publishedWebsite(School $school): SchoolWebsite
    {
        $website = $school->website;

        abort_unless($website && $website->is_published && $school->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive), 404);

        return $website;
    }
}
