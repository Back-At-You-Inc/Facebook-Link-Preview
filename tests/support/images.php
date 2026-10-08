<?php
/** Writes minimal valid and truncated image files to a temp dir and returns their paths keyed by name. */
function buildImageFixtures() {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "flp_fixtures_" . getmypid();
    if (!is_dir($dir)) mkdir($dir);

    $chunk = function ($type, $data) {
        return pack("N", strlen($data)) . $type . $data . pack("N", crc32($type . $data));
    };
    $png = "\x89PNG\r\n\x1a\n" . $chunk("IHDR", pack("NNCCCCC", 200, 150, 8, 2, 0, 0, 0)) . $chunk("IEND", "");
    $gif = "GIF89a" . pack("vv", 160, 130) . "\x00\x00\x00";
    $bmp = "BM" . str_repeat("\x00", 12) . pack("VVV", 40, 180, 140) . str_repeat("\x00", 4);
    $jpg = "\xFF\xD8"
        . "\xFF\xE0" . pack("n", 16) . "JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
        . "\xFF\xC0" . pack("nCnnC", 11, 8, 250, 300, 1) . "\x01\x11\x00"
        . "\xFF\xD9";

    $files = [
        "ok.png" => $png,
        "ok.gif" => $gif,
        "ok.bmp" => $bmp,
        "ok.jpg" => $jpg,
        "trunc.png" => substr($png, 0, 18),
        "trunc.gif" => substr($gif, 0, 7),
        "trunc.bmp" => substr($bmp, 0, 12),
        "trunc.jpg" => substr($jpg, 0, 22),
        "junk.jpg" => "not an image at all",
    ];
    $paths = [];
    foreach ($files as $name => $bytes) {
        $paths[$name] = $dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($paths[$name], $bytes);
    }
    $paths["missing.jpg"] = $dir . DIRECTORY_SEPARATOR . "missing.jpg";
    return $paths;
}
