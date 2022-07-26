<?php
namespace Controller;

use function App\Db;
use function App\JSON;
use function App\Post;
use function App\Validate;

class Store
{
    public static function save()
    {
        Validate($_POST, [
            "store_name" => "required",
            "tagline" => "required",
            "short_description" => "required",
            "social" => "required|array",
            "social.*.name" => "required",
            "social.*.link" => "required",
        ]);

        $db = Db();

        $info = $db->get("store_info", "*");

        $data = [
            "store_name" => Post("store_name"),
            "tagline" => Post("tagline"),
            "short_description" => Post("short_description"),
            "social [JSON]" => Post("social"),
        ];

        if ($info) {
            $db->update("store_info", $data);
        } else {
            $db->insert("store_info", $data);
        }

        unset($data["social [JSON]"]);
        $data["social"] = Post("social");

        JSON($data);
    }

    public static function get()
    {
        $db = Db();

        $info = $db->get("store_info", [
            "store_name",
            "tagline",
            "short_description",
            "social [JSON]",
        ]);

        JSON($info);
    }
}
