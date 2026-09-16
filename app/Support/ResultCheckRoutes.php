<?php

namespace App\Support;

use App\Models\School;

/**
 * One result-checking flow, three addresses. Each step has to link and
 * redirect inside the address the parent actually opened:
 *
 *   greenfield.akademicanest.com/results           the school's own website (tenant.results.*)
 *   akademicanest.com/hT4wLpZs3H8Kq2mV/result      the school's shareable link (school-result.*)
 *   akademicanest.com/p/{portal_key}/check-result  the older default path (check-result.*)
 *
 * On the tenant address the school comes from the HOST, so nothing naming a
 * school is put into the URL at all.
 */
final class ResultCheckRoutes
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public static function url(string $action, School $school, array $extra = []): string
    {
        $request = request();

        if ($request->routeIs('tenant.results.*')) {
            // Relative: the next step stays on exactly the host, scheme and
            // port this page was served on.
            return route("tenant.results.{$action}", [...$extra, 'tenantDomain' => $request->getHost()], absolute: false);
        }

        if ($request->routeIs('school-result.*')) {
            return route("school-result.{$action}", [...$extra, 'school' => $school->result_link_slug]);
        }

        return route("check-result.{$action}", [...$extra, 'school' => $school]);
    }
}
