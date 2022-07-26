<?php
namespace Controller;

use function App\Db;
use function App\Get;
use function App\ImageOptimize;
use function App\JSON;
use function App\PrepareFileName;
use function App\Upload;
use function App\Validate;

class Media
{
    static function create()
    {
        Validate($_FILES, [
            "files" => "required|array",
            "files.*" => "uploaded_file:0,1000K,png,jpeg",
        ]);

        $db = Db();

        $files = [];
        $data = [];
        foreach ($_FILES["files"]["name"] as $index => $file) {
            $tmp["name"] = PrepareFileName($file, $db);
            $tmp["tmp"] = $_FILES["files"]["tmp_name"][$index];
            $files[] = $tmp;
            $data[] = ["name" => $tmp["name"]];
        }

        $db->insert("media", $data);
        Upload($files);

        JSON($data);
    }

    static function index()
    {
        $db = Db();

        $limit = 48;
        $offset = (Get("page") ? ((int) Get("page") - 1) : 0) * $limit;

        $count_of_page = ceil(((int) $db->count("media")) / $limit);

        $data = array_map(function ($data) {
            return [
                "id" => (int) $data["id"],
                "name" => $data["name"],
                "url" => ImageOptimize($data["name"], "400x400", "contain"),
            ];
        }, $db->select("media", "*", [
            "LIMIT" => [$offset, $limit],
            "ORDER" => ["id" => "DESC"],
        ]));

        JSON([
            "page_total" => $count_of_page,
            "data" => $data,
        ]);
    }

    static function delete($request)
    {
        $db = Db();

        $media = $db->get("media", "*", ["id" => $request["params"]["id"]]);

        if ($media) {
            if (file_exists(__DIR__ . "/../uploads/" . $media["name"])) {
                unlink(__DIR__ . "/../uploads/" . $media["name"]);
            }

            $db->delete("media", ["id" => $request["params"]["id"]]);

            JSON($media);
        } else {
            JSON(["error" => "Media not found"], 404);
        }
    }
}
