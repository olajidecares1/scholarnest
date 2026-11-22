<?php

/*
|--------------------------------------------------------------------------
| Default subjects for every class
|--------------------------------------------------------------------------
|
| The ONE place the platform's default subjects are listed. Every school on
| the platform gets these for each of its classes without the School Admin
| setting anything up, and the admin cannot remove them. They can still add
| subjects of their own.
|
| Edit a list, then run
|
|     php artisan subjects:backfill-defaults
|
| to bring schools already on the platform in line. Running it again never
| adds a subject twice.
|
| `class_groups` says which subjects each kind of class takes, in the order
| they are printed on the result sheet. Which group a class belongs to is
| worked out from its name and the level it is filed under
| (App\Services\DefaultClassSubjects::groupFor):
|
|   reception        Reception, KG, Kindergarten, Creche, Playgroup, Pre-Nursery
|   nursery_1        Nursery 1 (and a plain "Nursery" class)
|   nursery_2        Nursery 2 and Nursery 3
|   primary          Primary 1 to 6
|   jss_1            JSS 1
|   jss_2_3          JSS 2 and JSS 3
|   sss              SS 1 to 3 with no department: the general subjects
|   sss_science      SS Science classes: general + science subjects
|   sss_art          SS Art classes: general + art subjects
|   sss_commercial   SS Commercial classes: general + commercial subjects
|
| A group with `includes` takes that group's subjects first, then its own.
|
| `subjects` gives, for every subject named above:
|
|   category  the catalogue category (science, arts, commercial, general)
|             used if the subject is not in the catalogue yet
|   aliases   other spellings of the SAME subject. A class that already
|             offers the subject under one of these keeps it, rather than
|             getting it a second time. Matching ignores capital letters,
|             extra spaces, dots and "&" versus "and".
|
*/

return [

    'class_groups' => [

        'reception' => [
            'label' => 'Reception / KG',
            'subjects' => [
                'Numeracy',
                'Literacy',
                'Pre-Science',
                'Protective Measures',
                'Safety Measures',
                'Health Habits',
                'Social Habits',
                'Bible Story / CRS',
                'Moral Instruction',
                'Handwriting',
            ],
        ],

        'nursery_1' => [
            'label' => 'Nursery 1',
            'subjects' => [
                'Number Work',
                'Letter Work',
                'Moral Instruction',
                'Science',
                'Food and Nutrition',
                'Social Habits',
                'Health Habits',
                'CRS',
                'Writing',
                'Creative Arts',
            ],
        ],

        'nursery_2' => [
            'label' => 'Nursery 2',
            'subjects' => [
                'Number Work',
                'Letter Work',
                'BST',
                'Social Habits',
                'Health Habits',
                'CRS',
                'Food and Nutrition',
                'Quantitative Reasoning',
                'Verbal Reasoning',
                'Civic Education',
                'Agricultural Science',
                'Writing',
            ],
        ],

        'primary' => [
            'label' => 'Primary',
            'subjects' => [
                'Mathematics',
                'English',
                'History',
                'CCA',
                'French',
                'Quantitative Reasoning',
                'Verbal Reasoning',
                'RNV',
                'BST',
                'Phonics',
                'Yoruba / Igbo',
                'Handwriting',
                'Computer',
                'Music',
            ],
        ],

        'jss_1' => [
            'label' => 'JSS 1',
            'subjects' => [
                'English Language',
                'Mathematics',
                'Intermediate Science',
                'Social and Citizenship Studies',
                'Nigerian History',
                'Business Studies',
                'CRS',
                'PHE',
                'Diction',
                'French',
                'Music',
                'Fashion Design',
                'Digital Technology',
                'Yoruba',
            ],
        ],

        'jss_2_3' => [
            'label' => 'JSS 2 & JSS 3',
            'subjects' => [
                'English Language',
                'Mathematics',
                'BST 1 (PHE & ICT)',
                'BST 2 (Basic Science & Technology)',
                'Business Studies',
                'RNV (Social Studies, CRS & Civic Education)',
                'Music',
                'French',
                'Yoruba',
                'PVS (Agriculture & Home Economics)',
                'Phonics',
            ],
        ],

        'sss' => [
            'label' => 'SS 1 – SS 3 (general)',
            'subjects' => [
                'English Language',
                'Mathematics',
                'Biology',
                'Civic Education',
                'Economics',
                'Digital Technology',
                'Diction',
                'Agricultural Science',
                'Geography',
                'Food and Nutrition',
            ],
        ],

        'sss_science' => [
            'label' => 'SS Science',
            'includes' => 'sss',
            'subjects' => [
                'Physics',
                'Chemistry',
                'Further Mathematics',
            ],
        ],

        'sss_art' => [
            'label' => 'SS Art',
            'includes' => 'sss',
            'subjects' => [
                'CRS',
                'Government',
                'Literature in English',
            ],
        ],

        'sss_commercial' => [
            'label' => 'SS Commercial',
            'includes' => 'sss',
            'subjects' => [
                'Financial Accounting',
                'Commerce',
            ],
        ],

    ],

    'subjects' => [
        // Reception / Nursery
        'Numeracy' => ['category' => 'general', 'aliases' => []],
        'Literacy' => ['category' => 'general', 'aliases' => []],
        'Pre-Science' => ['category' => 'science', 'aliases' => ['Pre Science', 'Prescience']],
        'Protective Measures' => ['category' => 'general', 'aliases' => ['Protective Measure']],
        'Safety Measures' => ['category' => 'general', 'aliases' => ['Safety Measure']],
        'Health Habits' => ['category' => 'general', 'aliases' => ['Health Habit']],
        'Social Habits' => ['category' => 'general', 'aliases' => ['Social Habit']],
        'Bible Story / CRS' => ['category' => 'arts', 'aliases' => ['Bible Story', 'Bible Knowledge', 'Bible Story/CRS']],
        'Moral Instruction' => ['category' => 'general', 'aliases' => ['Moral Instructions']],
        'Handwriting' => ['category' => 'general', 'aliases' => ['Hand Writing']],
        'Number Work' => ['category' => 'general', 'aliases' => ['Number Works']],
        'Letter Work' => ['category' => 'general', 'aliases' => ['Letter Works']],
        'Science' => ['category' => 'science', 'aliases' => []],
        'Food and Nutrition' => ['category' => 'general', 'aliases' => ['Food & Nutrition']],
        'Writing' => ['category' => 'general', 'aliases' => []],
        'Creative Arts' => ['category' => 'arts', 'aliases' => ['Creative Art']],

        // Shared across levels
        'Mathematics' => ['category' => 'general', 'aliases' => ['Maths', 'Math', 'General Mathematics']],
        'English' => ['category' => 'general', 'aliases' => ['English Language', 'English Studies']],
        'English Language' => ['category' => 'general', 'aliases' => ['English', 'English Studies']],
        'CRS' => ['category' => 'arts', 'aliases' => ['Christian Religious Studies', 'Christian Religious Knowledge', 'CRK']],
        'BST' => ['category' => 'science', 'aliases' => ['Basic Science and Technology', 'Basic Science & Technology']],
        'Quantitative Reasoning' => ['category' => 'general', 'aliases' => ['Quantitative Aptitude']],
        'Verbal Reasoning' => ['category' => 'general', 'aliases' => ['Verbal Aptitude']],
        'Civic Education' => ['category' => 'general', 'aliases' => ['Civics']],
        'Agricultural Science' => ['category' => 'general', 'aliases' => ['Agricultural Sciences', 'Agriculture', 'Agric', 'Agric Science']],
        'French' => ['category' => 'arts', 'aliases' => ['French Language']],
        'Music' => ['category' => 'arts', 'aliases' => []],
        'Diction' => ['category' => 'general', 'aliases' => []],
        'Digital Technology' => ['category' => 'science', 'aliases' => ['Digital Technologies']],
        'Phonics' => ['category' => 'general', 'aliases' => []],

        // Primary
        'History' => ['category' => 'arts', 'aliases' => []],
        'CCA' => ['category' => 'arts', 'aliases' => ['Cultural and Creative Arts', 'Cultural & Creative Arts', 'Cultural and Creative Art', 'Cultural & Creative Art']],
        'RNV' => ['category' => 'general', 'aliases' => ['Religion and National Values', 'Religion & National Values']],
        'Yoruba / Igbo' => ['category' => 'arts', 'aliases' => ['Yoruba/Igbo', 'Yoruba', 'Igbo', 'Nigerian Language']],
        'Computer' => ['category' => 'science', 'aliases' => ['Computer Studies', 'Computer Science', 'ICT', 'Information Technology']],

        // JSS
        'Intermediate Science' => ['category' => 'science', 'aliases' => []],
        'Social and Citizenship Studies' => ['category' => 'general', 'aliases' => []],
        'Nigerian History' => ['category' => 'arts', 'aliases' => []],
        'Business Studies' => ['category' => 'commercial', 'aliases' => []],
        'PHE' => ['category' => 'science', 'aliases' => ['Physical and Health Education', 'Physical & Health Education']],
        'Fashion Design' => ['category' => 'general', 'aliases' => ['Fashion Design and Garment Making']],
        'Yoruba' => ['category' => 'arts', 'aliases' => ['Yoruba Language']],
        'BST 1 (PHE & ICT)' => ['category' => 'science', 'aliases' => ['BST 1', 'BST I']],
        'BST 2 (Basic Science & Technology)' => ['category' => 'science', 'aliases' => ['BST 2', 'BST II']],
        'RNV (Social Studies, CRS & Civic Education)' => ['category' => 'general', 'aliases' => ['RNV']],
        'PVS (Agriculture & Home Economics)' => ['category' => 'general', 'aliases' => ['PVS', 'Pre-Vocational Studies', 'Prevocational Studies']],

        // SS 1 – SS 3
        'Biology' => ['category' => 'science', 'aliases' => []],
        'Economics' => ['category' => 'commercial', 'aliases' => []],
        'Geography' => ['category' => 'arts', 'aliases' => []],
        'Physics' => ['category' => 'science', 'aliases' => []],
        'Chemistry' => ['category' => 'science', 'aliases' => []],
        'Further Mathematics' => ['category' => 'science', 'aliases' => ['Further Maths']],
        'Government' => ['category' => 'arts', 'aliases' => []],
        'Literature in English' => ['category' => 'arts', 'aliases' => ['Literature', 'Literature-in-English']],
        'Financial Accounting' => ['category' => 'commercial', 'aliases' => ['Accounting']],
        'Commerce' => ['category' => 'commercial', 'aliases' => []],
    ],

    /*
    | Subjects that used to be platform defaults (the old JSS list) and are
    | not on any list above any more.
    |
    | A default subject comes off a class when it is no longer on that
    | class's list. On top of that, an examination subject in this session
    | that is a default somewhere else, or one of these, and has no score
    | entered and is not offered by the class, comes off the examination, so
    | teachers are not asked to score a subject the class no longer takes. A
    | subject with any score in it is never touched. They all stay in the
    | catalogue, and a school can tick one again as its own subject.
    */
    'retired_defaults' => [
        'CRS/IRS',
        'Basic Science',
        'Basic Technology',
        'Home Economics',
        'Social Studies',
        'Security Education',
        'Islamic Religious Studies',
    ],

];
