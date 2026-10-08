<?php
// Run: php tests/fastimage_test.php — exits 1 on any failure. No network needed.
error_reporting(E_ALL);
set_error_handler(function ($no, $str, $file, $line) {
    if (!(error_reporting() & $no)) return false;// respect @-suppression
    throw new ErrorException($str, 0, $no, $file, $line);
});

require __DIR__ . "/../php/classes/FastImage.php";
require __DIR__ . "/support/images.php";

use baymedia\facebooklinkpreview\FastImage;

$images = buildImageFixtures();
$expected = [
    "ok.png" => [200, 150],
    "ok.gif" => [160, 130],
    "ok.bmp" => [180, 140],
    "ok.jpg" => [300, 250],
    "trunc.png" => false,
    "trunc.gif" => false,
    "trunc.bmp" => false,
    "trunc.jpg" => false,
    "junk.jpg" => false,
];

$failed = 0;
foreach ($expected as $name => $want) {
    try {
        $got = (new FastImage($images[$name]))->getSize();
    } catch (\Throwable $e) {
        $got = get_class($e) . ": " . $e->getMessage();
    }
    $ok = $got === $want;
    echo ($ok ? "PASS" : "FAIL") . " getSize($name) => " . json_encode($got) . "\n";
    if (!$ok) $failed++;
}

try {
    new FastImage($images["missing.jpg"]);
    echo "FAIL missing file did not throw\n";
    $failed++;
} catch (\RuntimeException $e) {
    echo "PASS missing file throws RuntimeException\n";
}

foreach ($images as $path) @unlink($path);
@rmdir(dirname($images["ok.png"]));

exit($failed ? 1 : 0);
