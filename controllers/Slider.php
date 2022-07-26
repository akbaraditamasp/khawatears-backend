<?php
namespace Controller;

use function App\Db;
use function App\Get;
use function App\ImageOptimize;
use function App\JSON;
use function App\Post;
use function App\Validate;

class Slider
{
    public static function create()
    {
        Validate($_POST, [
            "name" => "required",
            "media_id" => "required|numeric",
        ]);

        $db = Db();

        $image = $db->get("media", ["id", "name"], ["id" => Post("media_id")]);

        if (!$image) {
            return JSON(["error" => "Media not found"], 404);
        }

        $data = [
            "name" => Post("name"),
            "url" => Post("url"),
            "media_id" => Post("media_id"),
        ];

        $db->insert("sliders", $data);

        JSON($data + [
            "id" => $db->id(),
            "image" => [
                "id" => $image["id"],
                "url" => ImageOptimize($image["name"], "1024x320"),
            ],
        ]);
    }

    public static function index($req)
    {
        $db = Db();

        $limit = 12;
        $offset = (Get("page") ? ((int) Get("page") - 1) : 0) * $limit;

        $count_of_page = ceil(((int) $db->count("products")) / $limit);

        $sliders = [];
        if (!isset($req["user"])) {
            $sliders = $db->select("sliders", [
                "[>]media" => ["sliders.media_id" => "id"],
            ], [
                "sliders.id [Int]",
                "sliders.name",
                "sliders.url",
                "image" => [
                    "sliders.media_id [Int]",
                    "media.name (media_name)",
                ],
            ], [
                "ORDER" => ["sliders.id" => "DESC"],
                "LIMIT" => [$offset, $limit],
            ]);
        } else {
            $sliders = $db->select("sliders", [
                "[>]media" => ["sliders.media_id" => "id"],
            ], [
                "sliders.id [Int]",
                "sliders.name",
                "sliders.url",
                "image" => [
                    "sliders.media_id [Int]",
                    "media.name (media_name)",
                ],
            ], [
                "ORDER" => ["sliders.id" => "DESC"],
            ]);
        }

        $sliders = array_map(function ($val) {
            $val["image"]["id"] = $val["image"]["media_id"];
            $val["image"]["url"] = ImageOptimize($val["image"]["media_name"], "1024x320");
            unset($val["image"]["media_name"]);
            unset($val["image"]["media_id"]);

            return $val;
        }, $sliders);

        JSON(["page_total" => $count_of_page, "data" => $sliders]);
    }

    public static function get($req)
    {
        $id = $req["params"]["id"];

        $db = Db();

        $slider = $db->get("sliders", [
            "[>]media" => ["sliders.media_id" => "id"],
        ], [
            "sliders.id [Int]",
            "sliders.name",
            "sliders.url",
            "image" => [
                "sliders.media_id [Int]",
                "media.name (media_name)",
            ],
        ], [
            "sliders.id" => $id,
        ]);

        if (!$slider) {
            return JSON(["error" => "Slider not found"], 404);
        }

        $slider["image"]["id"] = $slider["image"]["media_id"];
        $slider["image"]["url"] = ImageOptimize($slider["image"]["media_name"], "1024x320");

        unset($slider["image"]["media_id"]);
        unset($slider["image"]["media_name"]);

        return JSON($slider);
    }

    public static function delete($req)
    {
        $id = $req["params"]["id"];

        $db = Db();

        $slider = $db->get("sliders", "*", ["id" => $id]);

        if (!$slider) {
            return JSON(["error" => "Slider not found"], 404);
        }

        $db->delete("sliders", ["id" => $id]);

        JSON($slider);
    }

    public static function update($req)
    {
        $id = $req["params"]["id"];

        $db = Db();

        $slider = $db->get("sliders", "*", ["id" => $id]);

        if (!$slider) {
            return JSON(["error" => "Slider not found"], 404);
        }

        Validate($_POST, [
            "name" => "required",
            "media_id" => "required|numeric",
        ]);

        $image = $db->get("media", ["id", "name"], ["id" => Post("media_id")]);

        if (!$image) {
            return JSON(["error" => "Media not found"], 404);
        }

        $data = [
            "name" => Post("name"),
            "url" => Post("url"),
            "media_id" => Post("media_id"),
        ];

        $db->update("sliders", $data, ["id" => $id]);

        JSON($data + [
            "id" => $slider["id"],
            "image" => [
                "id" => $image["id"],
                "url" => ImageOptimize($image["name"], "1024x320"),
            ],
        ]);
    }
}
