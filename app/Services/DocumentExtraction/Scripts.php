<?php

namespace App\Services\DocumentExtraction;

/**
 * Raised and lowered text, written so it survives as plain text.
 *
 * x² and CO₂ have Unicode characters of their own, and using them keeps a
 * formula readable everywhere it is shown: the question bank, the practice
 * screen, a printed paper. Where there is no such character (x^(ab)) the
 * caret and underscore a mathematician would write by hand are used instead,
 * which is still unambiguous and still one line of text.
 *
 * Shared by the Word reader and the PDF reader so that the same formula comes
 * out of both the same way.
 */
class Scripts
{
    /**
     * "o" and "O" raised are how a paper written before anyone had a degree
     * sign to hand writes 60°, and that is what they mean every time.
     */
    private const UP = ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹', '+' => '⁺', '-' => '⁻', '−' => '⁻', '=' => '⁼', '(' => '⁽', ')' => '⁾', 'n' => 'ⁿ', 'o' => '°', 'O' => '°', 'x' => 'ˣ'];

    private const DOWN = ['0' => '₀', '1' => '₁', '2' => '₂', '3' => '₃', '4' => '₄', '5' => '₅', '6' => '₆', '7' => '₇', '8' => '₈', '9' => '₉', '+' => '₊', '-' => '₋', '−' => '₋', '=' => '₌', '(' => '₍', ')' => '₎'];

    public static function up(string $text): string
    {
        return self::convert($text, self::UP, '^');
    }

    public static function down(string $text): string
    {
        return self::convert($text, self::DOWN, '_');
    }

    /**
     * @param  array<string, string>  $map
     */
    private static function convert(string $text, array $map, string $marker): string
    {
        if (trim($text) === '') {
            return $text;
        }

        $converted = '';

        foreach (mb_str_split($text) as $character) {
            if (! isset($map[$character])) {
                return $marker.(mb_strlen($text) > 1 ? '('.$text.')' : $text);
            }

            $converted .= $map[$character];
        }

        return $converted;
    }
}
