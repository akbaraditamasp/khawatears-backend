<?php
namespace App;

use Rakit\Validation\Validator;
use \Medoo\Medoo;

function JSON($data, $status_code = 200)
{
    header("Content-Type: application/json");
    http_response_code($status_code);

    echo json_encode($data);
    exit();
}

function Validate($data, $rules)
{
    $validator = new Validator;

    $validation = $validator->make($data, $rules);

    $validation->validate();

    if ($validation->fails()) {
        // handling errors
        $errors = $validation->errors();

        JSON($errors->firstOfAll(), 400);
    }
}

function Db()
{
    $database = new Medoo([
        // [required]
        'type' => 'mysql',
        'host' => $_ENV["MYSQL_HOST"],
        'database' => $_ENV["MYSQL_DB"],
        'username' => $_ENV["MYSQL_USER"],
        'password' => $_ENV["MYSQL_PASSWORD"],
        'port' => 3306,
        'error' => \PDO::ERRMODE_EXCEPTION,
    ]);

    return $database;
}

function RandString($length = 10)
{
    $characters = '0123456789ABCDEF';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function Post($key)
{
    return isset($_POST[$key]) ? $_POST[$key] : null;
}

function Get($key)
{
    return isset($_GET[$key]) ? $_GET[$key] : null;
}

function Slugify($text, string $divider = '-')
{
    $text = preg_replace('~[^\pL\d]+~u', $divider, $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, $divider);
    $text = preg_replace('~-+~', $divider, $text);
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}

function PrepareFileName($filename, Medoo $db)
{
    $rand = RandString(7);
    while (($db->get("media", ["name"], ["name" => $rand . "-" . $filename]))) {
        $rand = RandString(7);
    }

    return $rand . "-" . $filename;
}

function PrepareIdentifier(Medoo $db)
{
    $rand = RandString(10);
    while (($db->get("orders", ["identifier"], ["identifier" => $rand]))) {
        $rand = RandString(10);
    }

    return $rand;
}

function Upload(array $files)
{
    foreach ($files as $file) {
        move_uploaded_file($file["tmp"], __DIR__ . "/uploads/" . $file["name"]);
    }
}

function ImageOptimize(string $loc, string $size = "400x400", string $fit = "cover")
{
    return $_ENV["IMG_URL"] . "?url=$loc&size=$size&fit=$fit";
}

function SendFile(string $path)
{
    readfile($path);
}
