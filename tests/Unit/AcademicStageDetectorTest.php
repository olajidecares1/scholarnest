<?php

use App\Enums\AcademicStage;
use App\Support\AcademicStageDetector;

test('detects junior secondary classes', function (string $className) {
    expect(AcademicStageDetector::detect($className))->toBe(AcademicStage::JuniorSecondary);
})->with([
    'JSS 1', 'JSS 2', 'JSS 3', 'Junior Secondary 1',
]);

test('detects senior secondary classes', function (string $className) {
    expect(AcademicStageDetector::detect($className))->toBe(AcademicStage::SeniorSecondary);
})->with([
    'SS 1', 'SS 2', 'SS 3', 'SSS 1', 'SSS 2', 'SSS 3', 'Senior Secondary 2',
]);

test('detects primary classes', function (string $className) {
    expect(AcademicStageDetector::detect($className))->toBe(AcademicStage::Primary);
})->with([
    'Primary 1', 'Primary 4', 'Primary 6',
]);

test('detects early years classes', function (string $className) {
    expect(AcademicStageDetector::detect($className))->toBe(AcademicStage::EarlyYears);
})->with([
    'Creche', 'Nursery', 'Nursery 1', 'Nursery 2', 'Nursery 3', 'KG 1', 'KG 2', 'Kindergarten',
]);

test('returns null for unrecognized or missing class names', function (?string $className) {
    expect(AcademicStageDetector::detect($className))->toBeNull();
})->with([
    null, '', 'Alpha Class', 'Room 4',
]);
