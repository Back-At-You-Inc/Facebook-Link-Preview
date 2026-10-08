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
use baymedia\facebooklinkpreview\SetUp;
use baymedia\facebooklinkpreview\Url;

SetUp::init();

$text = $_POST["text"] ?? "";
$imageQuantity = (int)($_POST["imagequantity"] ?? -1);
$text = " " . str_replace("\n", " ", $text);

$answer = null;
$url = Url::extractFromText($text);
if ($url !== null) {
    $linkPreview = new LinkPreview();
    $answer = $linkPreview->crawl($url, $imageQuantity);
}

// the js front end expects an object (it fills in defaults for null fields), pageUrl, and "|"-joined images
$answer = $answer !== null ? json_decode($answer, true) : null;
$answer = is_array($answer) ? $answer : [
    'title' => null,
    'url' => null,
    'page_url' => null,
    'canonicalUrl' => null,
    'description' => null,
    'images' => null,
    'video' => null,
    'videoIframe' => null
];
$answer['pageUrl'] = $answer['page_url'];
unset($answer['page_url']);
if (is_array($answer['images'])) {
    $answer['images'] = implode("|", $answer['images']);
}
echo json_encode($answer);

SetUp::finish();

