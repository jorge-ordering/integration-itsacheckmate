<?php
require_once("config.php");
// header('Content-Type: text/html; charset=utf-8');
header('Content-Type: application/json; charset=utf-8');

file_put_contents('auth.json', json_encode($_GET));
$credentials = $_GET['api_key'];
$credentials = explode(':', $credentials);
$project = $credentials[0];
$api_key = $credentials[1];
$sandbox = $credentials[2];
debug($project);
$code = $_GET['code'];

$configs = get_configs($project, $api_key);
$configs = $configs->result;
debug($configs);
$development_mode = $sandbox ? true : false;
// $development_mode = true;


$params = new stdClass();
$params->client_id = $configs->client_id;
$params->client_secret = $configs->client_secret;
$params->code = $code;
$params->redirect_uri = "https://integrations.ordering.co/pro-itsacheckmate/create_token.php?api_key={$project}:{$api_key}:{$sandbox}";
$params->grant_type = 'authorization_code';

$url_params = [];
foreach ($params as $key => $value) {
    array_push($url_params, "{$key}={$value}");
}
debug($url_params);
$url_params = implode('&', $url_params);

$url = getIntegrationUrl($development_mode)."/oauth/token?{$url_params}";

$token = json_decode(request($url, 'POST', null, null));

file_put_contents('createtokenurl.txt', $url);
debug($token);

if ($token->error) {
    file_put_contents('error.json', json_encode($token));
    header('Location: ' . 'http://integrations.ordering.co/itsacheckmate/status.html?status=ERROR', true, 301);
    die();
    return;
} else {
    $api = DEVELOPMENT ? ORDERING_URL_DEVELOPMENT : ORDERING_URL;
    $version = API_VERSION;
    $language = 'en';
    $ordering_url = "{$api}/{$version}/{$language}/{$project}";
    $headers = [
        'x-api-key: '.$api_key,
    ];
    $payload = [
        "value" => json_encode($token)
    ];
    request($ordering_url."/configs/{$configs->oauth_id}", 'PUT', $headers, json_encode($payload));
    header('Location: ' . 'http://integrations.ordering.co/itsacheckmate/status.html?status=OK', true, 301);
    die();
}

