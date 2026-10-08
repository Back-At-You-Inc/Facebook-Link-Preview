<?php
/**
 * Copyright (c) 2014 Leonardo Cardoso (http://leocardz.com)
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Version: 1.3.0
 */

/** This class is for database connection. It's just an example, neither security is being handled here nor mysql errors that might be occurred. */
namespace baymedia\facebooklinkpreview;

include_once "HighLight.php";

class Database
{

    private static $lastError = "";

    static function insert($save)
    {
        $conn = Database::connect();
        if (!$conn) return null;

        $stmt = $conn->prepare("INSERT INTO `linkpreview`.`linkpreview` (`id`, `text`, `image`, `title`, `canonicalUrl`, `url`, `description`, `iframe`)
                        VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            self::$lastError = $conn->error;
            Database::close($conn);
            return null;
        }
        $stmt->bind_param("sssssss", $save["text"], $save["image"], $save["title"], $save["canonicalUrl"], $save["url"], $save["description"], $save["iframe"]);

        $id = null;
        if ($stmt->execute()) {
            $id = $conn->insert_id;
        } else {
            self::$lastError = $stmt->error;
        }
        $stmt->close();

        Database::close($conn);

        return $id;
    }

    static function delete($delete)
    {
        $conn = Database::connect();
        if (!$conn) return;

        $stmt = $conn->prepare("DELETE FROM `linkpreview`.`linkpreview` WHERE `id` = ?");
        if (!$stmt) {
            self::$lastError = $conn->error;
            Database::close($conn);
            return;
        }
        $stmt->bind_param("i", $delete["id"]);

        if (!$stmt->execute()) {
            self::$lastError = $stmt->error;
        }
        $stmt->close();

        Database::close($conn);
    }

    static function connect()
    {

        $host = "localhost";
        $user = "root";
        $password = "";
        $database = "linkpreview";

        mysqli_report(MYSQLI_REPORT_OFF);
        $connection = new \mysqli($host, $user, $password, $database);
        if ($connection->connect_error) {
            self::$lastError = $connection->connect_error;
            return null;
        }

        mb_language('uni');
        mb_internal_encoding('UTF-8');

        $connection->set_charset("utf8");

        return $connection;
    }

    static function close($conn)
    {
        $conn->close();
    }

    static function error()
    {
        return self::$lastError;
    }

    static function select()
    {
        $conn = Database::connect();
        if (!$conn) return array();

        $result = $conn->query("SELECT * FROM `linkpreview` ORDER BY id DESC");

        $rows = array();
        if ($result) {
            while ($r = $result->fetch_assoc()) {

                $r["text"] = HighLight::url($r["text"]);
                $r["description"] = HighLight::url($r["description"]);

                array_push($rows, $r);
            }
            $result->free();
        } else {
            self::$lastError = $conn->error;
        }

        Database::close($conn);

        return $rows;
    }


}
