<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Generate a small valid JPEG fixture used by E2E tests.
$fixtureDir = __DIR__ . '/Fixtures';
if (!is_dir($fixtureDir)) {
    mkdir($fixtureDir, 0755, true);
}

$fixture = $fixtureDir . '/test-image.jpg';
if (!file_exists($fixture) && function_exists('imagecreatetruecolor')) {
    $img = imagecreatetruecolor(100, 100);
    $bg  = imagecolorallocate($img, 100, 149, 237);
    imagefill($img, 0, 0, $bg);
    imagejpeg($img, $fixture, 85);
    imagedestroy($img);
}
