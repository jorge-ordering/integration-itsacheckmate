<?php 
require_once("config.php");
require_once  './vendor/autoload.php';
require_once("./utils/database.php");
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
$data = json_decode(file_get_contents('php://input'));

$apiKeyOrdering = get_api_key($data->project, $data->token);


if ($data->project && $data->location_id && $apiKeyOrdering) {
    sync_location_connection($data->location_id, $data->project, $apiKeyOrdering);
}

