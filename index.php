<?php

include_once "TextDB.php";
include_once "Api.php";

ignore_user_abort(true);
set_time_limit(0);
ini_set("memory_limit",-1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

$request_method = $_SERVER["REQUEST_METHOD"];
$user_agent = $_SERVER["HTTP_USER_AGENT"];
$controller = new Api($uri, $request_method,$user_agent);
$controller->processRequest();

