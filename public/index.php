<?php

require_once __DIR__ . "/../vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->load();

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods:*');

header('Access-Control-Allow-Headers: *');

if ($_ENV["ENV_MODE"] === "production") {
    error_reporting(0);
}

$_POST = json_decode(file_get_contents('php://input'), true);

$routes = file_get_contents(__DIR__ . "/../routes.json");
$routes = json_decode($routes, true);

$params = [];
$handlers = [];
foreach ($routes as $route) {
    if (preg_match("/\[([A-Z]*)\](.*)/", $route["route"], $match)) {
        $uri = explode("?", $_SERVER["REQUEST_URI"])[0];
        $method_allowed = explode("|", $match[1]);
        $paths = explode("/", trim($match[2], "/"));
        $requests = explode("/", trim($uri, "/"));
        $method = isset($_POST["_method"]) ? $_POST["_method"] : $_SERVER['REQUEST_METHOD'];

        $correct = true;
        $params_temp = [];
        if (in_array($method, $method_allowed)) {
            foreach ($requests as $i => $request) {
                if (isset($paths[$i])) {
                    if ($request === $paths[$i] || preg_match("/\:([A-Za-z]+)/", $paths[$i], $match)) {
                        if ($match && $request !== $paths[$i]) {
                            $key = $match[1];
                            $value = $request;

                            $params_temp[$key] = $value;
                        }
                    } else if ($paths[$i] === "*") {
                        break;
                    } else {
                        $correct = false;
                        break;
                    }
                } else {
                    $correct = false;
                    break;
                }
            }
        } else {
            $correct = false;
        }

        if ($correct) {
            $params = ["params" => $params_temp];
            $handlers = $route["handlers"];
            break;
        }
    }
}

function run($params, $position, $handlers)
{
    $handler = $handlers[$position];
    if (isset($handlers[$position + 1])) {
        $position++;
        return $handler($params, function ($pass_req = null) use ($params, $position, $handlers) {
            $next = "run";
            $next($pass_req ? $pass_req : $params, $position, $handlers);
        });
    } else {
        return $handler($params);
    }
};

if (count($handlers)) {
    run($params, 0, $handlers);
} else {
    echo "404";
}
