<?php

/*
|--------------------------------------------------------------------------
| Default subjects, by class
|--------------------------------------------------------------------------
|
| The ONE place the platform's default subjects are listed. Every school on
| the platform gets these for its Reception/KG, Nursery 1, Nursery 2,
| Primary, JSS and SS classes without
| the School Admin setting anything up, and the admin cannot remove them;
| they can still add subjects of their own.
|
| Edit a list here, then run
|
|     php artisan subjects:backfill
|
| to bring schools already on the platform in line. Running it again never
| adds a subject twice. A subject taken OFF a list is removed from a class
| only if no score has been recorded for it there; otherwise it stays, as
| the school's own subject, so no result is ever lost.
|
| Each group says which classes it is for, and lists its subjects in the
| order they are printed on the result sheet:
|
|   classes    a pattern for the class name ("JSS 1", "JSS 1 Gold", "JS1")
|   level      optional: a pattern for the level a class is filed under,
|              used when the class name itself says nothing (a school that
|              named its classes "Year 7", "Year 8"...) ...
|   positions  ... together with which class of that level this group is
|              for, counted from 1 in the level's own order. No positions
|              means every such class under the level.
|   subjects   name => [category, aliases]
|              category  science, arts, commercial or general, used when
|                        the subject is not in the shared catalogue yet
|              aliases   other spellings of the SAME subject. A class that
|                        already has one of these is treated as having the
|                        subject, so it is never added a second time. Matching
|                        ignores capitals, extra spaces, dots, hyphens and
|                        "&" versus "and".
|   streams    (SS only) extra subjects for each stream's classes, after
|              the general ones. A class's stream is the one set on it in
|              Academics, or failing that the word Science, Art or
|              Commercial in its name.
|
| A combined subject, e.g. "BST 1 (PHE & ICT)", is ONE subject with ONE
| score on the report card.
|
| A class no group is for (Creche, Nursery 3...) gets no defaults; the
| school sets its subjects up itself.
|
*/

$jss = '(?:j\.?\s*s\.?\s*s?\.?|junior\s+secondary(?:\s+school)?)\s*';
$sss = '(?:s\.?\s*s\.?\s*s?\.?|senior\s+secondary(?:\s+school)?)\s*';

return [

    'class_defaults' => [

        'kg' => [
            'label' => 'Reception / KG',
            'classes' => '/^\s*(?:reception|kindergarten|k\.?\s*g(?![a-z]))/i',
            'subjects' => [
                'Numeracy' => ['general', ['Number Work']],
                'Literacy' => ['general', ['Letter Work']],
                'Pre-Science' => ['science', ['Pre Science']],
                'Protective Measures' => ['general', []],
                'Safety Measures' => ['general', []],
                'Health Habits' => ['general', []],
                'Social Habits' => ['general', []],
                'Bible Story / CRS' => ['arts', ['Bible Story', 'Bible Stories']],
                'Moral Instruction' => ['general', ['Moral Instructions']],
                'Handwriting' => ['general', ['Hand Writing']],
            ],
        ],

        'nursery1' => [
            'label' => 'Nursery 1',
            'classes' => '/^\s*(?:nursery|nur\.?)\s*0?(?:1(?!\d)|one\b)/i',
            'level' => '/^\s*nursery/i',
            'positions' => [1],
            'subjects' => [
                'Number Work' => ['general', ['Number Works']],
                'Letter Work' => ['general', ['Letter Works']],
                'Moral Instruction' => ['general', ['Moral Instructions']],
                'Science' => ['science', []],
                'Food and Nutrition' => ['general', ['Foods and Nutrition']],
                'Social Habits' => ['general', []],
                'Health Habits' => ['general', []],
                'CRS' => ['arts', ['Christian Religious Studies', 'Christian Religious Knowledge', 'CRK']],
                'Writing' => ['general', []],
                'Creative Arts' => ['arts', ['Creative Art']],
            ],
        ],

        'nursery2' => [
            'label' => 'Nursery 2',
            'classes' => '/^\s*(?:nursery|nur\.?)\s*0?(?:2(?!\d)|two\b)/i',
            'level' => '/^\s*nursery/i',
            'positions' => [2],
            'subjects' => [
                'Number Work' => ['general', ['Number Works']],
                'Letter Work' => ['general', ['Letter Works']],
                'BST' => ['science', ['Basic Science and Technology']],
                'Social Habits' => ['general', []],
                'Health Habits' => ['general', []],
                'CRS' => ['arts', ['Christian Religious Studies', 'Christian Religious Knowledge', 'CRK']],
                'Food and Nutrition' => ['general', ['Foods and Nutrition']],
                'Quantitative Reasoning' => ['general', []],
                'Verbal Reasoning' => ['general', []],
                'Civic Education' => ['general', []],
                'Agricultural Science' => ['general', ['Agriculture', 'Agric', 'Agric Science']],
                'Writing' => ['general', []],
            ],
        ],

        'primary' => [
            'label' => 'Primary',
            'classes' => '/^\s*(?:primary|pry\.?|basic)\s*0?(?:[1-6](?!\d)|one\b|two\b|three\b|four\b|five\b|six\b)/i',
            'level' => '/^\s*(?:(?:lower|upper)\s+)?primary/i',
            'subjects' => [
                'Mathematics' => ['general', ['Maths', 'General Mathematics']],
                'English' => ['general', ['English Studies']],
                'History' => ['arts', []],
                'CCA' => ['arts', ['Cultural and Creative Arts', 'Cultural and Creative Art']],
                'French' => ['arts', ['French Language']],
                'Quantitative Reasoning' => ['general', []],
                'Verbal Reasoning' => ['general', []],
                'RNV' => ['general', ['Religion and National Values']],
                'BST' => ['science', ['Basic Science and Technology']],
                'Phonics' => ['general', []],
                'Yoruba / Igbo' => ['arts', []],
                'Handwriting' => ['general', ['Hand Writing']],
                'Computer' => ['science', []],
                'Music' => ['arts', []],
            ],
        ],

        'jss1' => [
            'label' => 'JSS 1',
            'classes' => '/^\s*'.$jss.'0?(?:1(?!\d)|one\b)/i',
            'level' => '/^\s*(?:j\.?\s*s\.?\s*s|junior\s+(?:secondary|high))/i',
            'positions' => [1],
            'subjects' => [
                'English Language' => ['general', ['English']],
                'Mathematics' => ['general', ['Maths', 'General Mathematics']],
                'Intermediate Science' => ['science', []],
                'Social and Citizenship Studies' => ['general', []],
                'Nigerian History' => ['arts', []],
                'Business Studies' => ['commercial', []],
                'CRS' => ['arts', ['Christian Religious Studies', 'Christian Religious Knowledge', 'CRK']],
                'PHE' => ['science', ['Physical and Health Education']],
                'Diction' => ['general', []],
                'French' => ['arts', ['French Language']],
                'Music' => ['arts', []],
                'Fashion Design' => ['general', []],
                'Digital Technology' => ['science', ['Digital Technologies']],
                'Yoruba' => ['arts', ['Yoruba Language']],
            ],
        ],

        'jss2_3' => [
            'label' => 'JSS 2 & JSS 3',
            'classes' => '/^\s*'.$jss.'0?(?:[23](?!\d)|two\b|three\b)/i',
            'level' => '/^\s*(?:j\.?\s*s\.?\s*s|junior\s+(?:secondary|high))/i',
            'positions' => [2, 3],
            'subjects' => [
                'English Language' => ['general', ['English']],
                'Mathematics' => ['general', ['Maths', 'General Mathematics']],
                'BST 1 (PHE & ICT)' => ['science', ['BST 1', 'BST I']],
                'BST 2 (Basic Science & Technology)' => ['science', ['BST 2', 'BST II']],
                'Business Studies' => ['commercial', []],
                'RNV (Social Studies, CRS & Civic Education)' => ['general', ['RNV']],
                'Music' => ['arts', []],
                'French' => ['arts', ['French Language']],
                'Yoruba' => ['arts', ['Yoruba Language']],
                'PVS (Agriculture & Home Economics)' => ['general', ['PVS']],
                'Phonics' => ['general', []],
            ],
        ],

        'ss' => [
            'label' => 'SS 1 – SS 3',
            'classes' => '/^\s*'.$sss.'0?(?:[123](?!\d)|one\b|two\b|three\b)/i',
            'level' => '/^\s*(?:s\.?\s*s\.?\s*s|senior\s+(?:secondary|high))/i',
            'subjects' => [
                'English Language' => ['general', ['English']],
                'Mathematics' => ['general', ['Maths', 'General Mathematics']],
                'Biology' => ['science', []],
                'Civic Education' => ['general', []],
                'Economics' => ['general', []],
                'Digital Technology' => ['science', ['Digital Technologies']],
                'Diction' => ['general', []],
                'Agricultural Science' => ['general', ['Agriculture', 'Agric', 'Agric Science']],
                'Geography' => ['general', []],
                'Food and Nutrition' => ['general', ['Foods and Nutrition']],
            ],
            'streams' => [
                'science' => [
                    'Physics' => ['science', []],
                    'Chemistry' => ['science', []],
                    'Further Mathematics' => ['science', ['Further Maths']],
                ],
                'art' => [
                    'CRS' => ['arts', ['Christian Religious Studies', 'Christian Religious Knowledge', 'CRK']],
                    'Government' => ['arts', []],
                    'Literature in English' => ['arts', ['Literature']],
                ],
                'commercial' => [
                    'Financial Accounting' => ['commercial', ['Accounting']],
                    'Commerce' => ['commercial', []],
                ],
            ],
        ],

    ],

];
