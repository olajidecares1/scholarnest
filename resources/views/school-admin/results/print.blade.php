<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Report Card | {{ $student->fullName() }}</title>
        @vite(['resources/css/app.css'])
        <style>
            @media print {
                @page { size: A4; margin: 10mm; }
            }
            body { background: #f3f4f6; }
        </style>
    </head>
    <body class="p-8">
        @include('school-admin.results._report-card', ['school' => $school, 'examination' => $examination, 'student' => $student, 'subjects' => $subjects, 'summary' => $summary, 'report' => $report])

        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    </body>
</html>
