<?php

namespace App\Support;

use App\Enums\AcademicStage;

class AcademicStageDetector
{
    /**
     * Schools freely rename/add classes (e.g. "SS 1" vs "SSS 1" for senior
     * classes, "Creche"/"Nursery"/"KG" for early years), so this pattern-matches
     * the free-text class name defensively rather than requiring a canonical
     * taxonomy. Junior must be checked before senior, since senior's "SS"
     * pattern would otherwise never be reached for "JSS" (it's checked first
     * on purpose, not because SS would match JSS - it wouldn't - but to keep
     * the precedence obvious and future-proof if senior patterns broaden).
     */
    public static function detect(?string $className): ?AcademicStage
    {
        if (! $className) {
            return null;
        }

        $normalized = trim($className);

        if (preg_match('/\bjss\b|junior/i', $normalized)) {
            return AcademicStage::JuniorSecondary;
        }

        if (preg_match('/\bsss\b|\bss[\s-]?\d|senior/i', $normalized)) {
            return AcademicStage::SeniorSecondary;
        }

        if (preg_match('/primary/i', $normalized)) {
            return AcademicStage::Primary;
        }

        if (preg_match('/nursery|creche|crèche|\bkg\b|kindergarten/i', $normalized)) {
            return AcademicStage::EarlyYears;
        }

        return null;
    }
}
