<?php
namespace Middleware;

use Exception;
use function App\Db;
use function App\JSON;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class Auth
{
    public static function optional($request, $next)
    {
        $headers = "";
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } else if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        $bearer = "";
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                $bearer = $matches[1];
            }
        }

        $decoded = [];
        try {
            $decoded = JWT::decode($bearer, new Key($_ENV["JWT_SECRET"], 'HS256'));
            $decoded = (array) $decoded;
        } catch (Exception $e) {
            $next();
            return;
        }

        $db = Db();

        $user = $db->get("users", [
            "[>]tokens" => [
                "id" => "user_id",
            ],
        ], [
            "users.id",
            "users.username",
        ], [
            "tokens.unique_char" => $decoded["unique"],
        ]);

        if ($user) {
            $request["user"] = $user;
            $next($request);
            return;
        }

        $next();
    }

    public static function verify($request, $next)
    {
        $headers = "";
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } else if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        $bearer = "";
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                $bearer = $matches[1];
            }
        }

        $decoded = [];
        try {
            $decoded = JWT::decode($bearer, new Key($_ENV["JWT_SECRET"], 'HS256'));
            $decoded = (array) $decoded;
        } catch (Exception $e) {
            JSON(["error" => "Unauthorized"], 401);
        }

        $db = Db();

        $user = $db->get("users", [
            "[>]tokens" => [
                "id" => "user_id",
            ],
        ], [
            "users.id",
            "users.username",
        ], [
            "tokens.unique_char" => $decoded["unique"],
        ]);

        if ($user) {
            $request["user"] = $user + $decoded;
            $next($request);
            return;
        }

        JSON(["error" => "Unauthorized"], 401);
    }
}
