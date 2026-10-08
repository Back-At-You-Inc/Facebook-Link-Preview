<?php
/**
 * Copyright (c) 2014 Leonardo Cardoso (http://leocardz.com)
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Version: 1.3.0
 */
include_once "classes/LinkPreview.php";

use baymedia\facebooklinkpreview\LinkPreview;
use baymedia\facebooklinkpreview\Regex;
use baymedia\facebooklinkpreview\SetUp;

SetUp::init();

$text = $_POST["text"] ?? "";
$imageQuantity = (int)($_POST["imagequantity"] ?? -1);
$text = " " . str_replace("\n", " ", $text);

// a url starts with a scheme or follows whitespace (mirrors the js urlRegex); prefer one with a scheme
$answer = null;
if (preg_match_all('~(?:https?://|(?<=\s))[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}(?::\d+)?(?:[/?#]\S*)?~i', $text, $matches)) {
    $url = $matches[0][0];
    foreach ($matches[0] as $candidate) {
        if (preg_match(Regex::$httpRegex, $candidate)) {
            $url = $candidate;
            break;
        }
    }
    if (!preg_match(Regex::$httpRegex, $url)) {
        $url = "http://" . $url;
    }
    $linkPreview = new LinkPreview();
    $answer = $linkPreview->crawl($url, $imageQuantity);
}

// the js front end expects an object and fills in defaults for null fields
echo $answer ?? json_encode([
    'title' => null,
    'url' => null,
    'page_url' => null,
    'canonicalUrl' => null,
    'description' => null,
    'images' => null,
    'video' => null,
    'videoIframe' => null
]);

SetUp::finish();

