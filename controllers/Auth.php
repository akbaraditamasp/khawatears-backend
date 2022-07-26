<?php
namespace Controller;

use function App\Db;
use function App\JSON;
use function App\RandString;
use function App\Validate;
use \Firebase\JWT\JWT;

class Auth
{
    public static function login()
    {
        $db = Db();

        Validate($_GET, [
            "username" => "required",
            "password" => "required",
        ]);

        $user = $db->get("users", "*", ["username" => $_GET["username"]]);
        if ($user) {
            if (password_verify($_GET["password"], $user["password"])) {
                unset($user["password"]);

                $unique_char = RandString(5);
                while ($db->get("tokens", "*", ["unique_char" => $unique_char])) {
                    $unique_char = RandString(5);
                }

                $db->insert("tokens", ["user_id" => $user["id"], "unique_char" => $unique_char]);

                $jwt = JWT::encode(["unique" => $unique_char], $_ENV["JWT_SECRET"], 'HS256');

                JSON($user + ["token" => $jwt]);
            }
        }

        JSON(["error" => "Unauthorized"], 401);
    }

    public static function logout($req)
    {
        $db = Db();
        $db->delete("tokens", ["unique_char" => $req["user"]["unique"]]);

        JSON($req["user"]);
    }
}
