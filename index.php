<?php

include_once "TextDB.php";
include_once "Api.php";

// Database format is CSV and not JSON, because CSV format allow to add new line to file without need to rewrite whole file, this way we can prevent bugs on high load moments when many users trying to write to one file
// Note that unique user is identified by email and session_id as each user can open many browsers/tabs with same email, so each one should be handled as different session
// All unique users are also identified by hash code in the file db.csv, I preferred to use hash code over autoincrement to prevent possible bugs when many users will try to enter simultaneously so each one will be added as new hash without problem of correct order

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

