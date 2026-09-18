<?php

namespace App\Exceptions;

use App\Models\School;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A school's address was reached before its website was published.
 *
 * This was a bare abort(404), and it is the single most likely thing a school
 * meets on its first day: registration gives a school its subdomain
 * immediately, and the website manager is something somebody sits down with
 * later, so between the two the school's own address answered "404 NOT FOUND"
 * on a white page. Told to visit their new address, a head teacher sees the
 * platform broken; there is nothing on that page to say the address is right,
 * that their portal is already live at it, or what is missing.
 *
 * So the address answers with the school's own name, a way into the portal
 * that already works, and a line telling their administrator what to publish.
 *
 * A 404 UNDERNEATH, deliberately. Anything reading the status, an API client,
 * a link checker, a crawler, should be told there is no website here yet,
 * because there is not. The HTML response is a 200: it is not an error page
 * shown in place of what was asked for, it is a real page for a real address,
 * and it carries noindex so an unfinished site is not indexed as the school's
 * web presence. See bootstrap/app.php.
 */
class SchoolWebsiteNotPublished extends HttpException
{
    public function __construct(public readonly School $school)
    {
        parent::__construct(404, "{$school->name} has not published a website yet.");
    }
}
