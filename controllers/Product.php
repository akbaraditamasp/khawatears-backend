<?php
namespace Controller;

use function App\Db;
use function App\Get;
use function App\ImageOptimize;
use function App\JSON;
use function App\Post;
use function App\RandString;
use function App\Slugify;
use function App\Validate;

class Product
{
    public static function index($req)
    {
        $db = Db();

        $limit = 12;
        $offset = (Get("page") ? ((int) Get("page") - 1) : 0) * $limit;
        $q = Get("q") ? "%" . Get("q") . "%" : "%%";

        $count_of_page = ceil(((int) $db->count("products")) / $limit);

        if (isset($req["user"])) {
            $query = $db->query("SELECT <products.id>,<products.product_name>,<products.price>,<products.price_before>,<products.slug>,<media.name> AS <image>,<products.publish> FROM <products> LEFT JOIN (SELECT <products_media.id>,<media.name>,<products_media.product_id> FROM <products_media> LEFT JOIN <media> ON <media.id> = <products_media.media_id> WHERE <products_media.position> = '0' GROUP BY <products_media.product_id>) AS <media> ON <media.product_id> = <products.id> WHERE <products.product_name> LIKE :q ORDER BY <products.id> DESC LIMIT :limit OFFSET :offset", [
                ":limit" => $limit,
                ":offset" => $offset,
                ":q" => $q,
            ]);
        } else {
            $query = $db->query("SELECT <products.id>,<products.product_name>,<products.price>,<products.price_before>,<products.slug>,<media.name> AS <image> FROM <products> LEFT JOIN (SELECT <products_media.id>,<media.name>,<products_media.product_id> FROM <products_media> LEFT JOIN <media> ON <media.id> = <products_media.media_id> WHERE <products_media.position> = '0' GROUP BY <products_media.product_id>) AS <media> ON <media.product_id> = <products.id> WHERE <products.publish>=TRUE AND <products.product_name> LIKE :q ORDER BY <products.id> DESC LIMIT :limit OFFSET :offset", [
                ":limit" => $limit,
                ":offset" => $offset,
                ":q" => $q,
            ]);
        }

        $data = [];

        while ($val = $query->fetchObject()) {
            $val->id = (int) $val->id;
            $val->image = $val->image ? ImageOptimize($val->image) : null;
            $val->price = (int) $val->price;
            $val->price_before = (int) $val->price_before;
            if (isset($val->publish)) {
                $val->publish = (bool) $val->publish;
            }
            $data[] = $val;
        }

        JSON([
            "page_total" => $count_of_page,
            "data" => $data,
        ]);
    }

    public static function create()
    {
        Validate($_POST, [
            "product_name" => "required",
            "price" => "required|numeric",
            "description" => "required",
            "price_before" => "numeric",
            "publish" => "boolean",
            "spec" => "array",
            "spec.*.key" => "required",
            "spec.*.value" => "required",
            "images" => "array",
            "images.*.id" => "required|numeric",
            "images.*.position" => "required|numeric",
        ]);

        $db = Db();

        $slug = Slugify(Post("product_name"));
        while ($db->get("products", ["id"], ["slug" => $slug])) {
            $slug = Slugify(Post("product_name") . " " . RandString(5));
        }

        $data = [
            "product_name" => Post("product_name"),
            "price" => Post("price"),
            "description" => Post("description"),
            "price_before" => Post("price_before"),
            "publish" => Post("publish") ? true : false,
            "slug" => $slug,
            "spec [JSON]" => Post("spec"),
        ];

        $db->insert("products", $data);
        $product_id = $db->id();

        $images = [];
        if (count(Post("images"))) {
            $images_data = [];
            $images = $db->select("media", "*", ["id" => array_map(function ($val) {return $val["id"];}, Post("images"))]);

            foreach ($images as $image) {
                $position = array_filter(Post("images"), function ($val) use ($image) {return $val["id"] == $image["id"];});
                $images_data[] = [
                    "media_id" => $image["id"],
                    "product_id" => $product_id,
                    "position" => array_values($position)[0]["position"],
                ];
            }

            $db->insert("products_media", $images_data);
        }

        unset($data["spec [JSON]"]);
        $data["spec"] = Post("spec");
        JSON($data + ["id" => $product_id, "images" => $images], 201);
    }

    public static function get($req)
    {
        $slug = isset($req["params"]["slug"]) ? $req["params"]["slug"] : null;
        $id = isset($req["params"]["id"]) ? $req["params"]["id"] : null;
        $publish = isset($req["user"]) ? [] : ["publish" => true];

        $db = Db();

        $data = $db->get("products", [
            "id [Int]",
            "product_name",
            "price [Int]",
            "description",
            "price_before [Int]",
            "slug",
            "spec [JSON]",
            "publish [Bool]",
        ], ($slug ? ["slug" => $slug] : ["id" => $id]) + $publish);

        if (!$data) {
            JSON(["error" => "Product not found"], 404);
        }
        $images = array_map(function ($val) {
            return [
                "id" => $val["id"],
                "url" => ImageOptimize($val["name"], "400x400", isset($req["user"]) ? "contain" : "cover"),
            ];
        }, $db->select("products_media", [
            "[>]media" => ["products_media.media_id" => "id"],
        ], [
            "media.id [Int]",
            "media.name",
        ], [
            "products_media.product_id" => $data["id"],
            "ORDER" => ["products_media.position" => "ASC"],
        ]));

        JSON($data + ["images" => $images]);
    }

    public static function delete($req)
    {
        $db = Db();

        $product = $db->get("products", "*", [
            "id" => $req["params"]["id"],
        ]);

        if (!$product) {
            JSON(["error" => "Product not found"], 404);
        }

        $db->delete("products", [
            "id" => $product["id"],
        ]);

        JSON($product);
    }

    public static function update($req)
    {
        $db = Db();
        $product = $db->get("products", "*", [
            "id" => $req["params"]["id"],
        ]);

        if (!$product) {
            JSON(["error" => "Product not found"], 404);
        }

        Validate($_POST, [
            "product_name" => "required",
            "price" => "required|numeric",
            "description" => "required",
            "price_before" => "numeric",
            "publish" => "boolean",
            "spec" => "array",
            "spec.*.key" => "required",
            "spec.*.value" => "required",
            "images" => "array",
            "images.*.id" => "required|numeric",
            "images.*.position" => "required|numeric",
        ]);

        $images_before = array_map(function ($val) {
            return $val["id"];
        }, $db->select("products_media", [
            "[>]media" => ["products_media.media_id" => "id"],
        ], [
            "media.id [Int]",
        ], [
            "products_media.product_id" => $product["id"],
            "ORDER" => ["products_media.position" => "ASC"],
        ]));

        $data = [
            "product_name" => Post("product_name"),
            "price" => Post("price"),
            "description" => Post("description"),
            "price_before" => Post("price_before"),
            "publish" => Post("publish") ? true : false,
            "spec [JSON]" => Post("spec"),
        ];

        $images = [];
        if (count(Post("images"))) {
            $images_data = [];
            $images_update = [];
            $images = $db->select("media", "*", ["id" => array_map(function ($val) {return $val["id"];}, Post("images"))]);

            foreach ($images as $image) {
                $position = array_filter(Post("images"), function ($val) use ($image) {return $val["id"] == $image["id"];});
                $before_index = array_search($image["id"], $images_before);
                if ($before_index !== false) {
                    $images_update[] = [
                        "id" => $image["id"],
                        "position" => array_values($position)[0]["position"],
                    ];
                    unset($images_before[$before_index]);
                } else {
                    $images_data[] = [
                        "media_id" => $image["id"],
                        "product_id" => $product["id"],
                        "position" => array_values($position)[0]["position"],
                    ];
                }
            }
        }

        $db->action(function ($db) use ($images, $images_data, $images_update, $product, $images_before, $data) {
            if (count($images)) {
                if (count($images_data)) {
                    $db->insert("products_media", $images_data);
                }

                if (count($images_update)) {
                    foreach ($images_update as $update) {
                        $db->update("products_media", [
                            "position" => $update["position"],
                        ], [
                            "product_id" => $product["id"],
                            "media_id" => $update["id"],
                        ]);
                    }
                }

                if (count($images_before)) {
                    foreach ($images_before as $before) {
                        $db->delete("products_media", [
                            "media_id" => $images_before,
                            "product_id" => $product["id"],
                        ]);
                    }
                }
            }

            $db->update("products", $data, ["id" => $product["id"]]);

            return true;
        });

        unset($data["spec [JSON]"]);
        $data["spec"] = Post("spec");
        JSON($data + ["id" => $product["id"], "images" => $images], 200);
    }
}
