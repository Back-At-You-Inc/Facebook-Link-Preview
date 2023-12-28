<?php
/**
 * Copyright (c) 2014 Leonardo Cardoso (http://leocardz.com)
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Version: 1.3.0
 */

namespace baymedia\facebooklinkpreview;

include_once "Media.php";
include_once "Regex.php";
include_once "SetUp.php";
include_once "Url.php";
include_once "Content.php";
include_once "Json.php";

class LinkPreview
{
	private $user_agent;

	public function __construct(array $options=[]){
		$this->user_agent = $options['user-agent'] ?? null;
	}

	public function crawl(string $url, int $image_quantity) {
		$match = [];
		if(preg_match(Regex::$urlRegex, $url, $match)) {
			$header = "";
			$title = "";
			$description = "";
			$videoIframe = "";
			$video = "no";

			$finalUrl = $url;
			$page_url = str_replace("https://", "http://", $finalUrl);

			$images = [];
			if(Content::isImage($page_url)) {
				$images[] = $page_url;
			} else {
				$urlData = $this->getPage($page_url);
				if(!$urlData['content']) {
					if(strpos($page_url, "//www.") === false) {
						if(strpos($page_url, "http://") !== false) {
							$page_url = str_replace("http://", "http://www.", $page_url);
						} else if(strpos($page_url, "https://") !== false) {
							$page_url = str_replace("https://", "https://www.", $page_url);
						}

						$urlData = $this->getPage($page_url);
					}
					if(!$urlData['content']) return null;
				}

				$page_url = $finalUrl = $urlData['url'];
				$raw = $urlData['content'];
				$header = $urlData['header'];

				$metaTags = Content::getMetaTags($raw);

				$tempTitle = Content::extendedTrim($metaTags['title']);
				if($tempTitle !== "") {
					$title = $tempTitle;
				}

				if($title === "") {
					$matching = [];
					if(preg_match(Regex::$titleRegex, str_replace("\n", " ", $raw), $matching)) {
						$title = $matching[2];
					}
				}

				$tempDescription = Content::extendedTrim($metaTags['description']);
				if($tempDescription != "") {
					$description = $tempDescription;
				} else {
					$description = Content::crawlCode($raw);
				}

				$descriptionUnderstood = $description !== "";
				if(($descriptionUnderstood == false && strlen($title) > strlen($description) && !preg_match(Regex::$urlRegex, $description) && $description !== "" && !preg_match('/[A-Z]/', $description)) || $title === $description) {
					$title = $description;
					$description = Content::crawlCode($raw);
				}

				if(Content::isJson($title)){
					$title = "";
				}
				if(Content::isJson($description)){
					$description = "";
				}

				$media = self::getMedia($page_url);
				if(count($media) === 0) {
					foreach($metaTags['images'] as $metaImage) {
						$images[] = !preg_match(Regex::$httpRegex, $metaImage) ? Url::canonicalLink(Content::extendedTrim($metaImage), $page_url) : $metaImage;
					}
				} else if(count($media) === 2) {
					if(!empty($media[0])) {
						$images[] = $media[0];
					}
					$videoIframe = $media[1];
				}
				if(!empty($media) && $media[0] !== "" && $media[1] !== "") {
					$video = "yes";
				}

				if($image_quantity == 0) {
					$images = [];
				} else {
					$images = array_merge($images, Content::getImages($raw, $page_url, $image_quantity));
					$images = array_keys(array_flip($images));// filter out duplicate image urls
				}

				$title = Content::extendedTrim($title);
				$page_url = Content::extendedTrim($page_url);
				$description = Content::extendedTrim($description);

				$description = preg_replace(Regex::$scriptRegex, "", $description);
			}

			$finalLink = explode("&", $finalUrl);
			$finalLink = $finalLink[0];

			$description = strip_tags($description);

			$answer = [
				'title' => $title,
				'url' => $finalLink,
				'page_url' => $finalUrl,
				'canonicalUrl' => Url::canonicalPage($page_url),
				'description' => $description,
				'images' => $images,
				'video' => $video,
				'videoIframe' => $videoIframe
			];

			$result_json = Json::jsonSafe($answer, $header);
			$result_json_decoded = json_decode($result_json);

			$flagged = false;

			if(!isset($result_json_decoded->title)) {
				$answer['title'] = utf8_encode($title);
				$flagged = true;
			}

			if(!isset($result_json_decoded->description)) {
				$answer['description'] = utf8_encode($description);
				$flagged = true;
			}

			if($flagged) {
				return Json::jsonSafe($answer, $header);
			}
			return $result_json;
		}
		return null;
	}

	private function getPage($url) {
		$options = [
			CURLOPT_SSL_VERIFYPEER => false,//FALSE to stop cURL from verifying the peer's certificate
			CURLOPT_RETURNTRANSFER => true, // return web page
			CURLOPT_HEADER => true, // return headers
			CURLOPT_FOLLOWLOCATION => true, // follow redirects
			CURLOPT_USERAGENT => $this->user_agent ?? "CurlBAYLinkPreviewer/1.0",
			CURLOPT_AUTOREFERER => true, // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 30, // timeout on connect
			CURLOPT_TIMEOUT => 30, // timeout on response
			CURLOPT_MAXREDIRS => 10, // stop after 10 redirects
			CURLOPT_ENCODING => "",// sets "Accept-Encoding: " header to all supported encoding types
			CURLOPT_REFERER => "https://www.backatyou.com",// set Referer: header
			CURLOPT_HTTPHEADER => [
				"Accept-Language: *",
				"Accept: */*"
			]
		];
		$ch = curl_init($url);
		curl_setopt_array($ch, $options);
		$content = curl_exec($ch);
		$header = curl_getinfo($ch);
		curl_close($ch);

		$curl_header_size = $header['header_size'];
		$body = trim(mb_substr($content, $curl_header_size));
		$response_header = explode("\n",trim(mb_substr($content, 0, $curl_header_size)));
		unset($response_header[0]);
		$aHeaders = [];
		foreach($response_header as $line){
			if (strpos($line,':') != false) {
				list($key,$val) = explode(':',$line,2);
				$aHeaders[strtolower($key)] = trim($val);
			}
		}

		return [
			'content' => $body,
			'url' => $header['url'],
			'header' => $header['content_type'],
			'headers' => $aHeaders
		];
	}

	public static function getMedia($page_url) {
		$media = [];
		if(strpos($page_url, "youtube.com") !== false || strpos($page_url, "youtu.be") !== false) {
			$media = Media::mediaYoutube($page_url);
		} else if(strpos($page_url, "vimeo.com") !== false) {
			$media = Media::mediaVimeo($page_url);
		} else if(strpos($page_url, "vine.co") !== false) {
			$media = Media::mediaVine($page_url);
		} else if(strpos($page_url, "metacafe.com") !== false) {
			$media = Media::mediaMetacafe($page_url);
		} else if(strpos($page_url, "dailymotion.com") !== false) {
			$media = Media::mediaDailymotion($page_url);
		} else if(strpos($page_url, "collegehumor.com") !== false) {
			$media = Media::mediaCollegehumor($page_url);
		} else if(strpos($page_url, "blip.tv") !== false) {
			$media = Media::mediaBlip($page_url);
		} else if(strpos($page_url, "funnyordie.com") !== false) {
			$media = Media::mediaFunnyordie($page_url);
		}
		return $media;
	}
}
?>