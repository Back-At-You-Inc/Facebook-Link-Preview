<?php
// Run: php tests/url_test.php — exits 1 on any failure. No network needed.
error_reporting(E_ALL);
set_error_handler(function ($no, $str, $file, $line) {
    throw new ErrorException($str, 0, $no, $file, $line);
});

require __DIR__ . "/../php/classes/Url.php";

use baymedia\facebooklinkpreview\Url;

$cases = [
    "Read https://example.com/article. Next" => "https://example.com/article",
    "see https://example.info/page ok" => "https://example.info/page",
    "Hello.World see https://google.com" => "https://google.com",
    "check php.net/manual/en/ please" => "http://php.net/manual/en/",
    "see (https://example.com/x)" => "https://example.com/x",
    "https://en.wikipedia.org/wiki/Foo_(bar)" => "https://en.wikipedia.org/wiki/Foo_(bar)",
    "Wow, https://example.com/a?b=1!" => "https://example.com/a?b=1",
    "ftp://x.org and www.example.shop/x?y=1" => "http://www.example.shop/x?y=1",
    "https://example.com:8080/path" => "https://example.com:8080/path",
    "no url here" => null,
    "" => null,
];

$failed = 0;
foreach ($cases as $text => $want) {
    $got = Url::extractFromText($text);
    $ok = $got === $want;
    echo ($ok ? "PASS" : "FAIL") . " " . json_encode($text) . " => " . var_export($got, true) . ($ok ? "" : " (expected " . var_export($want, true) . ")") . "\n";
    if (!$ok) $failed++;
}

exit($failed ? 1 : 0);
