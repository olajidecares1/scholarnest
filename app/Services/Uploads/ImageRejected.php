<?php

namespace App\Services\Uploads;

use RuntimeException;

/**
 * An uploaded image that cannot be accepted, with a message fit to show the
 * person who uploaded it. Never a stack trace, never "error 500".
 */
class ImageRejected extends RuntimeException {}
