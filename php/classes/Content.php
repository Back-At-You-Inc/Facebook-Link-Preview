<?php
/**
 * Copyright (c) 2014 Leonardo Cardoso (http://leocardz.com)
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Version: 1.3.0
 */

/** This class handles the content analysis */
namespace baymedia\facebooklinkpreview;

include_once "Regex.php";
include_once "FastImage.php";

class Content
{
	public static function crawlCode($text) {
		$contentSpan = self::getTagContent("span", $text);
		$contentParagraph = self::getTagContent("p", $text);
		$contentDiv = self::getTagContent("div", $text);
		if(strlen($contentParagraph) > strlen($contentSpan) && strlen($contentParagraph) >= strlen($contentDiv)) {
			$content = $contentParagraph;
		} else if(strlen($contentParagraph) > strlen($contentSpan) && strlen($contentParagraph) < strlen($contentDiv)) {
			$content = $contentDiv;
		} else {
			$content = $contentParagraph;
		}
		return $content;
	}

	private static function getTagContent($tag, $string) {
		$pattern = "/<$tag(.*?)>(.*?)<\/$tag>/is";

		preg_match_all($pattern, $string, $matches);
		$content = "";
		for ($i = 0; $i < count($matches[0]); $i++) {
			$currentMatch = strip_tags($matches[0][$i]);
			if (strlen($currentMatch) >= 120) {
				$content = $currentMatch;
				break;
			}
		}
		if ($content == "") {// if no long enough string was found
			preg_match($pattern, $string, $matches);
			if(isset($matches[0])) {// just use the first thing found, if anything
				$content = $matches[0];
			}
		}
		return str_replace("&nbsp;", "", $content);
	}

	public static function isImage($url) {
		return preg_match(Regex::$imagePrefixRegex, $url)
			? true
			: false;
	}

	public static function getImages($text, $url, $imageQuantity) {
		$content = array();
		// get all images from img src
		if(preg_match_all(Regex::$imageRegex, $text, $matching)) {

			for($i = 0; $i < count($matching[0]); $i++) {
				$src = "";
				$pathCounter = substr_count($matching[0][$i], "../");
				preg_match(Regex::$srcRegex, $matching[0][$i], $imgSrc);

				$imgSrc = Url::canonicalImgSrc($imgSrc[2]);
				if(!preg_match(Regex::$httpRegex, $imgSrc)) {
					$src = Url::getImageUrl($pathCounter, Url::canonicalLink($imgSrc, $url));
				}
				if($src . $imgSrc != $url) {
					if($src == "") {
						array_push($content, $src . $imgSrc);
					} else {
						array_push($content, $src);
					}
				}
			}
		}

		// get all full image urls from anywhere on the page
		if (preg_match_all(Regex::$urlRegex, $text, $matching)) {
			for ($i = 0; $i < count($matching[0]); $i++) {
				if(self::isImage($matching[0][$i])) {
					$content[] = $matching[0][$i];
				}
			}
		}

		$content = array_unique($content);
		$content = array_values($content);

		$maxImages = $imageQuantity != -1 && $imageQuantity < count($content) ? $imageQuantity : count($content);

		$exclude_paths = ["bay_files.s3.amazonaws.com", "www.homeforsale.at"];
		$images = array();
		for($i = 0; $i < count($content); $i++) {
			$excluded = false;
			foreach($exclude_paths as $path) {
				if(strpos($content[$i], $path) !== false) {
					$excluded = true;
					break;
				}
			}
			if($excluded) continue;
			try {
				$image = new FastImage($content[$i]);
				list($width, $height) = $image->getSize();
				if($width > 120 && $height > 120) {// avoids getting very small images
					$images[] = $content[$i];
					$maxImages--;
					if ($maxImages == 0) break;
				}
			} catch(\Exception $ex) {}// skip images that can't be fetched
		}

		return $images;
	}

	public static function getMetaTags($contents) {
		$result = false;

		if(isset($contents)) {
			$list = [
				"UTF-8",
				"EUC-CN",
				"EUC-JP",
				"EUC-KR",
				'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5',
				'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10',
				'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16',
				'Windows-1251', 'Windows-1252', 'Windows-1254',
			];

			$encoding_check = mb_detect_encoding($contents, $list, true);
			$encoding = ($encoding_check === false) ? "UTF-8" : $encoding_check;

			$metaTags = Content::getMetaTagsEncoding($contents, $encoding);

			$result = $metaTags;
		}

		return $result;
	}

	public static function getMetaTagsEncoding($contents, $encoding) {
		$result = false;
		$metaTags = [
			'url' => "",
			'title' => "",
			'description' => "",
			'images' => []
		];

		if(!empty($contents)) {

			$doc = new \DOMDocument("1.0", "utf-8");
			@$doc->loadHTML($contents);

			$metas = $doc->getElementsByTagName("meta");

			for($i = 0; $i < $metas->length; $i++) {
				$meta = $metas->item($i);
				if ($meta->getAttribute('name') === 'description') {
					$metaTags["description"] = $meta->getAttribute('content');
				}
				if ($meta->getAttribute('name') === 'keywords') {
					$metaTags["keywords"] = $meta->getAttribute('content');
				}
				if ($meta->getAttribute('property') === 'og:title') {
					$metaTags["title"] = $meta->getAttribute('content');
				}
				if ($meta->getAttribute('property') === 'og:image' || $meta->getAttribute('name') === 'og:image') {
					$metaTags["images"][] = $meta->getAttribute('content');
				}
				if ($meta->getAttribute('property') === 'og:description') {
					$metaTags["og_description"] = $meta->getAttribute('content');
				}
				if ($meta->getAttribute('property') === 'og:url') {
					$metaTags["url"] = $meta->getAttribute('content');
				}
			}

			if(!empty($metaTags["og_description"])) {
				$metaTags["description"] = $metaTags["og_description"];
			}

			if(empty($metaTags["title"])) {
				$nodes = $doc->getElementsByTagName('title');
				if($nodes->length > 0) {
					$metaTags["title"] = $nodes->item(0)->nodeValue;
				}
			}

			$result = $metaTags;
		}
		return $result;
	}

	public static function extendedTrim($content) {
		return trim(str_replace("\n", " ", str_replace("\t", " ", preg_replace("/\s+/", " ", $content))));
	}

	public static function isJson($string) {
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}
}
?>