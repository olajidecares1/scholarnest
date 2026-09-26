<?php

namespace App\Services\StudentImport;

use RuntimeException;

/**
 * A problem with an uploaded class list that the school can fix, so its
 * message is written to be shown to them as it stands.
 */
class StudentImportException extends RuntimeException {}
