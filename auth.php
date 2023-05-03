<?php
require_once("config.php");
// header('Content-Type: text/html; charset=utf-8');
header('Content-Type: application/json; charset=utf-8');

header("Access-Control-Allow-Origin: *");
$get = $_GET;
// debug($get);

$configs = get_configs($get['project'], $get['api_key']);
$configs = $configs->result;
$development_mode = $get['sandbox'] == true;
$sandbox = $development_mode ? '1' : '0';
// debug($configs);
$params = new stdClass();
$params->client_id = $configs->client_id;
$params->redirect_uri = "https://integrations.ordering.co/itsacheckmate/create_token.php?api_key={$_GET['project']}:{$_GET['api_key']}:{$sandbox}";
$params->response_type = 'code';
$params->location_id = $get['location_id'];
$params->scope = 'locations menus orders';

$url_params = [];
foreach ($params as $key => $value) {
    array_push($url_params, "{$key}={$value}");
}
debug($url_params);
$url_params = implode('&', $url_params);

$url = getIntegrationUrl($development_mode) . "/oauth/authorize?{$url_params}";
file_put_contents("authurl.txt", $url);
// echo $url;
// echo $development_mode*1;
// echo json_encode($get);
header('Location: ' . $url, true, 301);
