<?php

use App\Services\CbtDocxTextExtractor;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

test('it extracts paragraph text and embedded images from a docx file', function () {
    Storage::fake('public');

    $tempDocx = tempnam(sys_get_temp_dir(), 'cbt_test_').'.docx';
    $tempImage = tempnam(sys_get_temp_dir(), 'cbt_test_img_').'.png';

    $image = imagecreatetruecolor(10, 10);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
    imagepng($image, $tempImage);
    imagedestroy($image);

    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('What is 2 + 2?');
    $section->addText('A. 3   B. 4   C. 5');
    $section->addImage($tempImage, ['width' => 50, 'height' => 50]);

    IOFactory::createWriter($phpWord, 'Word2007')->save($tempDocx);

    $result = app(CbtDocxTextExtractor::class)->extract($tempDocx);

    expect($result['text'])->toContain('What is 2 + 2?');
    expect($result['text'])->toContain('A. 3   B. 4   C. 5');
    expect($result['images'])->toHaveCount(1);
    Storage::disk('public')->assertExists($result['images'][0]);

    unlink($tempDocx);
    unlink($tempImage);
});

test('it returns empty results gracefully for a docx with no images', function () {
    Storage::fake('public');

    $tempDocx = tempnam(sys_get_temp_dir(), 'cbt_test_').'.docx';

    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Just plain text, no images.');
    IOFactory::createWriter($phpWord, 'Word2007')->save($tempDocx);

    $result = app(CbtDocxTextExtractor::class)->extract($tempDocx);

    expect($result['text'])->toContain('Just plain text, no images.');
    expect($result['images'])->toBe([]);

    unlink($tempDocx);
});
