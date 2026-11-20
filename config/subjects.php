<?php

/*
|--------------------------------------------------------------------------
| Default subjects
|--------------------------------------------------------------------------
|
| The ONE place the default Junior Secondary (JSS 1–3) subjects are listed.
| Every school on the platform gets these for its JSS classes without the
| School Admin setting anything up, and the admin cannot remove them.
|
| Edit this list to change the defaults, then run
|
|     php artisan subjects:backfill-jss
|
| to bring schools already on the platform in line. Running it again never
| adds a subject twice.
|
| Each entry is "name => settings", in the order the subjects are printed on
| the JSS result sheet:
|
|   category  the catalogue category (science, arts, commercial, general)
|             used if the subject is not in the catalogue yet
|   aliases   other spellings of the SAME subject. A school, or the shared
|             catalogue, that already has one of these is treated as having
|             the subject, so it is never added a second time under the
|             name above. Matching ignores capital letters, extra spaces,
|             and "&" versus "and".
|
*/

return [

    'jss_defaults' => [
        'English Language' => ['category' => 'general', 'aliases' => ['English']],
        'Mathematics' => ['category' => 'general', 'aliases' => ['Maths', 'General Mathematics']],
        'Basic Science' => ['category' => 'science', 'aliases' => []],
        'Basic Technology' => ['category' => 'science', 'aliases' => []],
        'Agricultural Science' => ['category' => 'general', 'aliases' => ['Agriculture', 'Agric', 'Agric Science']],
        'Home Economics' => ['category' => 'general', 'aliases' => []],
        'Social Studies' => ['category' => 'general', 'aliases' => []],
        'PHE' => ['category' => 'science', 'aliases' => ['Physical and Health Education', 'Physical & Health Education', 'P.H.E.', 'P.H.E']],
        'Computer Science' => ['category' => 'science', 'aliases' => ['Information Technology (Computer Studies)', 'Information Technology', 'Computer Studies', 'ICT']],
        'Yoruba' => ['category' => 'arts', 'aliases' => ['Yoruba Language', 'Nigerian Language', 'Nigerian Language (Yoruba / Igbo / Hausa)', 'Nigerian Language (Yoruba/Igbo/Hausa)']],
        'French' => ['category' => 'arts', 'aliases' => ['French Language']],
        'Civic Education' => ['category' => 'general', 'aliases' => []],
        'Security Education' => ['category' => 'general', 'aliases' => []],
        'Business Studies' => ['category' => 'commercial', 'aliases' => []],
        'Christian Religious Studies' => ['category' => 'arts', 'aliases' => ['CRS', 'C.R.S.', 'Christian Religious Knowledge', 'CRK']],
        'Islamic Religious Studies' => ['category' => 'arts', 'aliases' => ['IRS', 'I.R.S.', 'Islamic Studies', 'Islamic Religious Knowledge', 'IRK']],
        'History' => ['category' => 'arts', 'aliases' => []],
        'Music' => ['category' => 'arts', 'aliases' => []],
        'Cultural & Creative Art' => ['category' => 'arts', 'aliases' => ['Cultural and Creative Arts', 'Cultural and Creative Art', 'Cultural & Creative Arts', 'CCA']],
    ],

    /*
    | Subjects that used to be JSS defaults and have been replaced. The
    | backfill takes these off JSS classes; they stay in the catalogue, and a
    | school can tick one again as its own subject if it really wants it.
    |
    | "CRS/IRS" was one combined subject; it is now Christian Religious
    | Studies and Islamic Religious Studies, so a report card can show the
    | one a student actually took.
    */
    'retired_jss_defaults' => [
        'CRS/IRS',
    ],

];
