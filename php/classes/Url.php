<?php
/**
 * Copyright (c) 2014 Leonardo Cardoso (http://leocardz.com)
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Version: 1.3.0
 */

/** This class handles url analysis */
namespace baymedia\facebooklinkpreview;

include_once "Regex.php";

class Url
{
    static function canonicalLink($imgSrc, $referrer) {
        if (strpos($imgSrc, "//") === 0) {
            $imgSrc = "http:" . $imgSrc;
		}
        else if (strpos($imgSrc, "/") === 0) {
            $imgSrc = self::canonicalPage($referrer) . $imgSrc;
		}
        else {
            $imgSrc = self::canonicalPage($referrer) . '/' . $imgSrc;
		}
        return $imgSrc;
    }

    static function canonicalImgSrc($imgSrc) {
        $imgSrc = str_replace("../", "", $imgSrc);
        $imgSrc = str_replace("./", "", $imgSrc);
        $imgSrc = str_replace(" ", "%20", $imgSrc);
        return $imgSrc;
    }

    static function canonicalRefererPage($url) {
        $canonical = "";
        $barCounter = 0;
        for ($i = 0; $i < strlen($url); $i++) {
            if ($url[$i] != "/") {
                $canonical .= $url[$i];
            } else {
                $canonical .= $url[$i];
                $barCounter++;
            }
            if ($barCounter == 3) {
                break;
            }
        }
        return $canonical;
    }

    static function canonicalPage($url) {
        $canonical = "";

        if (substr_count($url, 'http://') > 1 || substr_count($url, 'https://') > 1 || (strpos($url, 'http://') !== false && strpos($url, 'https://') !== false))
            return $url;

        $protocol = "";
        if (strpos($url, "http://") !== false) {
            $url = substr($url, 7);
            $protocol = "http://";
        }
        else if (strpos($url, "https://") !== false) {
            $url = substr($url, 8);
            $protocol = "https://";
        }

        for ($i = 0; $i < strlen($url); $i++) {
            if ($url[$i] != "/")
                $canonical .= $url[$i];
            else
                break;
        }

        return $protocol . $canonical;
    }

    static function getImageUrl($pathCounter, $url) {
        $src = "";
        if ($pathCounter > 0) {
            $urlBreaker = explode('/', $url);
            for ($j = 0; $j < $pathCounter; $j++) {
                $src .= $urlBreaker[$j] . '/';
            }
        } else {
            $src = $url;
        }
        return $src;
    }

    /** First http(s) url in free text (else the first bare domain), with trailing punctuation removed; null when none. */
    static function extractFromText($text) {
        // a url starts with a scheme or follows whitespace (mirrors the js urlRegex)
        if (!preg_match_all('~(?:https?://|(?<=\s))[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}(?::\d+)?(?:[/?#]\S*)?~i', " " . $text, $matches)) {
            return null;
        }
        $url = $matches[0][0];
        foreach ($matches[0] as $candidate) {
            if (preg_match(Regex::$httpRegex, $candidate)) {
                $url = $candidate;
                break;
            }
        }
        $url = rtrim($url, ".,;:!?'\"");
        // keep a closing paren only when it pairs with one in the url, e.g. wiki/Foo_(bar)
        if (substr($url, -1) === ")" && substr_count($url, "(") < substr_count($url, ")")) {
            $url = rtrim(substr($url, 0, -1), ".,;:!?'\"");
        }
        if (!preg_match(Regex::$httpRegex, $url)) {
            $url = "http://" . $url;
        }
        return $url;
    }
}
?>