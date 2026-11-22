<?php

namespace App\Services;

/**
 * The old name of DefaultClassSubjects, from when only JSS classes had
 * default subjects. Kept so migrations that already ran on production
 * still load on a fresh database; new code uses DefaultClassSubjects.
 *
 * @deprecated Use DefaultClassSubjects.
 */
class JuniorSecondarySubjects extends DefaultClassSubjects {}
