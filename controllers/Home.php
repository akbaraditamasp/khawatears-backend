<?php
namespace Controller;

class Home
{
    public static function index()
    {
        \App\SendFile(__DIR__ . "/../public/index.html");
    }
}
