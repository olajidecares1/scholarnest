<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Curated Website Fonts
    |--------------------------------------------------------------------------
    |
    | Google Fonts families available in the School Website Builder's
    | typography controls, mapped to the specific weights we've confirmed are
    | published for each family.
    |
    | THE WEIGHTS ARE NOT DECORATION. Google's CSS API returns a stylesheet
    | that silently omits any weight a family does not publish, and the browser
    | then synthesises the difference, faux bold, visibly worse than the real
    | cut. Everything offered here is a weight the family really ships.
    |
    | Curated rather than the full 1500-family catalogue, because a picker with
    | every font in it is a picker nobody can choose from, and each entry costs
    | a verification of its real weights.
    |
    */
    'fonts' => [
        'Inter' => [400, 500, 600, 700, 800],
        'Roboto' => [400, 500, 700, 900],
        'Open Sans' => [400, 600, 700, 800],
        'Lato' => [400, 700, 900],
        'Montserrat' => [400, 500, 600, 700, 800],
        'Poppins' => [400, 500, 600, 700, 800],
        'Nunito' => [400, 600, 700, 800],
        'Raleway' => [400, 500, 600, 700, 800],
        'Oswald' => [400, 500, 600, 700],
        'Playfair Display' => [400, 600, 700, 800],
        'Merriweather' => [400, 700, 900],
        'PT Sans' => [400, 700],
        'Ubuntu' => [400, 500, 700],
        'Rubik' => [400, 500, 600, 700, 800],
        'Work Sans' => [400, 500, 600, 700, 800],
        'Quicksand' => [400, 500, 600, 700],
        'Josefin Sans' => [400, 500, 600, 700],
        'Karla' => [400, 500, 600, 700, 800],
        'Mulish' => [400, 500, 600, 700, 800],
        'Inconsolata' => [400, 500, 600, 700],
        'Barlow' => [400, 500, 600, 700, 800],
        'DM Sans' => [400, 500, 700],
        'Manrope' => [400, 500, 600, 700, 800],
        'Space Grotesk' => [400, 500, 600, 700],
        'Fira Sans' => [400, 500, 600, 700],
        'Noto Sans' => [400, 500, 600, 700],
        'Cabin' => [400, 500, 600, 700],
        'Libre Baskerville' => [400, 700],
        'Crimson Text' => [400, 600, 700],
        'Bitter' => [400, 500, 600, 700, 800],
        'Archivo' => [400, 500, 600, 700, 800],

        // Added for schools that want a more formal or more distinctive page
        // than the workhorse sans-serifs above.
        'Source Sans 3' => [400, 500, 600, 700, 800],
        'Figtree' => [400, 500, 600, 700, 800],
        'Outfit' => [400, 500, 600, 700, 800],
        'Plus Jakarta Sans' => [400, 500, 600, 700, 800],
        'Lexend' => [400, 500, 600, 700, 800],
        'Nunito Sans' => [400, 600, 700, 800],
        'PT Serif' => [400, 700],
        'Lora' => [400, 500, 600, 700],
        'EB Garamond' => [400, 500, 600, 700, 800],
        'Cormorant Garamond' => [400, 500, 600, 700],
        'Roboto Slab' => [400, 500, 600, 700, 800],
        'Arvo' => [400, 700],
        'Domine' => [400, 500, 600, 700],
        'Spectral' => [400, 500, 600, 700, 800],
        'Cardo' => [400, 700],
        'Alegreya' => [400, 500, 600, 700, 800],
        'Titillium Web' => [400, 600, 700, 900],
        'Exo 2' => [400, 500, 600, 700, 800],
        'Asap' => [400, 500, 600, 700],
        'Catamaran' => [400, 500, 600, 700, 800],
        'Heebo' => [400, 500, 600, 700, 800],
        'Hind' => [400, 500, 600, 700],
        'Prompt' => [400, 500, 600, 700],
        'Kanit' => [400, 500, 600, 700],
        'Bebas Neue' => [400],
        'Anton' => [400],
        'Teko' => [400, 500, 600, 700],
        'Comfortaa' => [400, 500, 600, 700],
        'Dancing Script' => [400, 500, 600, 700],
        'Pacifico' => [400],
        'Lobster' => [400],
        'Caveat' => [400, 500, 600, 700],
        'Abril Fatface' => [400],
        'Cinzel' => [400, 500, 600, 700, 800],
        'Marcellus' => [400],
        'Roboto Mono' => [400, 500, 600, 700],
        'JetBrains Mono' => [400, 500, 600, 700, 800],
        'Source Code Pro' => [400, 500, 600, 700],
    ],

    /*
    |--------------------------------------------------------------------------
    | System Fonts
    |--------------------------------------------------------------------------
    |
    | Faces that are NOT fetched from anywhere. They render only where the
    | visitor's device already has them installed, and fall back to the stack
    | beside them everywhere else.
    |
    | ALGERIAN IS THE REASON THIS SECTION EXISTS. It is a Microsoft display
    | face shipped with Office, not a Google font, there is no URL to request
    | it from. Putting it in the list above would have produced a Google Fonts
    | request for a family Google does not have, and a heading that rendered in
    | the default serif on every machine, including the ones that own the font.
    |
    | So they are declared and never requested, and each carries its own
    | fallback: a school that picks Algerian gets Algerian on a Windows machine
    | with Office and a sensible display face elsewhere, rather than a broken
    | page or a silent substitution into something unrelated.
    |
    | The weights are what the installed files really contain. Algerian is a
    | single decorative cut, offering it a bold would only ask the browser to
    | smear the outlines, which on a face this heavy looks like a fault.
    |
    */
    'system_fonts' => [
        'Algerian' => [
            'weights' => [400],
            'stack' => "'Algerian', 'Copperplate Gothic Bold', 'Impact', 'Haettenschweiler', fantasy",
        ],
        'Arial' => [
            'weights' => [400, 700],
            'stack' => "Arial, 'Helvetica Neue', Helvetica, sans-serif",
        ],
        'Helvetica' => [
            'weights' => [400, 700],
            'stack' => "'Helvetica Neue', Helvetica, Arial, sans-serif",
        ],
        'Verdana' => [
            'weights' => [400, 700],
            'stack' => 'Verdana, Geneva, sans-serif',
        ],
        'Tahoma' => [
            'weights' => [400, 700],
            'stack' => 'Tahoma, Verdana, Geneva, sans-serif',
        ],
        'Trebuchet MS' => [
            'weights' => [400, 700],
            'stack' => "'Trebuchet MS', 'Lucida Grande', sans-serif",
        ],
        'Segoe UI' => [
            'weights' => [400, 600, 700],
            'stack' => "'Segoe UI', system-ui, sans-serif",
        ],
        'Calibri' => [
            'weights' => [400, 700],
            'stack' => "Calibri, Candara, Segoe, 'Segoe UI', sans-serif",
        ],
        'Georgia' => [
            'weights' => [400, 700],
            'stack' => "Georgia, 'Times New Roman', serif",
        ],
        'Times New Roman' => [
            'weights' => [400, 700],
            'stack' => "'Times New Roman', Times, serif",
        ],
        'Garamond' => [
            'weights' => [400, 700],
            'stack' => "Garamond, 'Times New Roman', serif",
        ],
        'Palatino Linotype' => [
            'weights' => [400, 700],
            'stack' => "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
        ],
        'Book Antiqua' => [
            'weights' => [400, 700],
            'stack' => "'Book Antiqua', Palatino, 'Palatino Linotype', serif",
        ],
        'Courier New' => [
            'weights' => [400, 700],
            'stack' => "'Courier New', Courier, monospace",
        ],
        'Impact' => [
            'weights' => [400],
            'stack' => "Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif",
        ],
        'Comic Sans MS' => [
            'weights' => [400, 700],
            'stack' => "'Comic Sans MS', 'Comic Sans', cursive",
        ],
    ],
];
