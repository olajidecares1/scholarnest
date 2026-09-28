<?php

namespace App\Http\Controllers;

use App\Enums\PlanFeature;
use App\Enums\PlanKey;
use App\Models\LegalDocument;
use App\Models\School;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * robots.txt and sitemap.xml, answered per host.
 *
 * The platform (akademicanest.com) and every school website (its subdomain or
 * its own domain) are separate sites as far as a search engine is concerned,
 * and each needs its own robots.txt pointing at its own sitemap. A static
 * public/robots.txt could only ever say one thing for all of them, and the one
 * this project had named no sitemap at all.
 *
 * The sitemaps list only pages that answer 200 and are meant to be found:
 * the platform's front door and legal documents; a school's published website
 * pages, news posts and, where the school recruits, its job portal. Sign-in
 * pages, result pages, tokens and anything personal are never listed.
 */
class SeoController extends Controller
{
    /** Paths on the platform host that nobody should arrive at from search. */
    private const PLATFORM_DISALLOW = [
        '/media/',
        '/invoices/',
        '/continue-registration/',
        '/session/',
    ];

    /** Paths on a school's host that are personal or sign-in only. */
    private const SCHOOL_DISALLOW = [
        '/results/view/',
        '/portal/',
        '/parent-portal/',
        '/staff-portal/',
    ];

    public function platformRobots(): Response
    {
        return $this->robots(self::PLATFORM_DISALLOW, $this->platformUrl('/sitemap.xml'));
    }

    public function schoolRobots(School $school): Response
    {
        return $this->robots(self::SCHOOL_DISALLOW, $this->schoolUrl($school, '/sitemap.xml'));
    }

    public function platformSitemap(): Response
    {
        $urls = [
            ['loc' => $this->platformUrl('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
        ];

        $documents = LegalDocument::inOrder()->where('is_published', true);

        if ($documents->isNotEmpty()) {
            $urls[] = [
                'loc' => $this->platformUrl('/legal'),
                'lastmod' => $documents->max('updated_at'),
                'priority' => '0.3',
            ];

            foreach ($documents as $document) {
                $urls[] = [
                    'loc' => $this->platformUrl('/legal/'.$document->slug),
                    'lastmod' => $document->updated_at,
                    'priority' => '0.3',
                ];
            }
        }

        return $this->sitemap($urls);
    }

    public function schoolSitemap(School $school): Response
    {
        $urls = [];
        $website = $school->website;

        // Exactly the conditions under which the website pages answer 200,
        // see PublicSchoolWebsiteController::publishedWebsite().
        if ($website && $website->is_published && $school->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive)) {
            $updated = $website->updated_at;

            $urls[] = ['loc' => $school->publicUrl('public.school-website'), 'lastmod' => $updated, 'priority' => '1.0', 'changefreq' => 'weekly'];

            foreach (['about', 'admissions', 'contact', 'facilities', 'gallery', 'events', 'news'] as $page) {
                $urls[] = ['loc' => $school->publicUrl("public.school-{$page}.index"), 'lastmod' => $page === 'news' ? null : $updated, 'priority' => '0.7'];
            }

            $posts = $school->newsPosts()
                ->where('is_published', true)
                ->orderByDesc('published_at')
                ->get();

            foreach ($posts as $post) {
                $urls[] = [
                    'loc' => $school->publicUrl('public.school-news.show', ['post' => $post]),
                    'lastmod' => $post->updated_at ?? $post->published_at,
                    'priority' => '0.6',
                ];
            }
        }

        // The job portal stands apart from the website, see JobPortalController.
        if ($school->is_active && $school->canUseFeature(PlanFeature::Careers)) {
            $urls[] = ['loc' => $school->publicUrl('public.jobs.index'), 'priority' => '0.5'];

            foreach ($school->jobPostings()->open()->get() as $job) {
                $job->setRelation('school', $school);
                $urls[] = ['loc' => $job->publicUrl(), 'lastmod' => $job->updated_at, 'priority' => '0.5'];
            }
        }

        return $this->sitemap($urls);
    }

    /**
     * @param  list<string>  $disallow
     */
    private function robots(array $disallow, string $sitemap): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$sitemap;

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @param  list<array{loc: string, lastmod?: mixed, changefreq?: string, priority?: string}>  $urls
     */
    private function sitemap(array $urls): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        $seen = [];

        foreach ($urls as $url) {
            if (isset($seen[$url['loc']])) {
                continue;
            }

            $seen[$url['loc']] = true;
            $xml .= '  <url>'."\n".'    <loc>'.htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>'."\n";

            if (! empty($url['lastmod'])) {
                $xml .= '    <lastmod>'.Carbon::parse($url['lastmod'])->toAtomString().'</lastmod>'."\n";
            }

            if (! empty($url['changefreq'])) {
                $xml .= '    <changefreq>'.$url['changefreq'].'</changefreq>'."\n";
            }

            if (! empty($url['priority'])) {
                $xml .= '    <priority>'.$url['priority'].'</priority>'."\n";
            }

            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function platformUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }

    private function schoolUrl(School $school, string $path): string
    {
        $home = rtrim($school->publicUrl('public.school-website'), '/');

        return $home.$path;
    }
}
