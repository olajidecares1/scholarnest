<?php

namespace App\Services\StaffImport;

use App\Services\Import\ImportTemplate;

/**
 * The blank staff list a school downloads, fills in and uploads again.
 *
 * In Excel the Role, Gender and Class columns offer drop-down lists (the
 * school's own classes), so most of a row is picked rather than typed.
 */
class StaffImportTemplate extends ImportTemplate
{
    public const HEADINGS = ['Full Name', 'Role', 'Class', 'Subjects', 'Gender', 'Phone (WhatsApp)', 'Email', 'Date of Birth (DD/MM/YYYY)', 'Password', 'Department', 'Qualification', 'Employment Date (DD/MM/YYYY)', 'Address', 'Notes'];

    /**
     * @param  list<string>  $classNames  the school's classes, for the example row and the Class drop-down
     */
    public function build(string $format, array $classNames = []): string
    {
        $sample = ['Ngozi Okafor', 'Class Teacher', $classNames[0] ?? 'Primary 1', 'English, Mathematics', 'Female', '08031234567', 'ngozi.okafor@example.com', '14/03/1988', '', 'Primary', 'B.Ed', '09/09/2024', '12 Allen Avenue, Ikeja', ''];

        return $this->render($format, self::HEADINGS, $sample, [
            'Role' => array_values(StaffRoleResolver::KINDS),
            'Gender' => ['Male', 'Female'],
            'Class' => $classNames,
        ]);
    }

    protected function title(): string
    {
        return 'Staff Import Template';
    }

    protected function sheetName(): string
    {
        return 'Staff';
    }

    protected function filenameStem(): string
    {
        return 'staff-import-template';
    }

    protected function instructions(): string
    {
        return 'One staff member per row. Role: Class Teacher, Subject Teacher, Teacher, Administrative Staff, Management, Support Staff or Other. A teacher with one Class is made its class teacher automatically. Leave Password blank to have one generated. Remove the example row, keep the heading row, then upload this file.';
    }

    protected function pdfHint(): string
    {
        return 'Dates as DD/MM/YYYY. Role: Class Teacher, Subject Teacher, Teacher, Administrative Staff, Management, Support Staff or Other. A teacher with one Class becomes its class teacher. Leave Password blank to have one generated. Remove the example row before uploading.';
    }
}
