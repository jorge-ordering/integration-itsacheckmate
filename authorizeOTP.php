<?php
require_once("config.php");
// header('Content-Type: text/html; charset=utf-8');
header('Content-Type: application/json; charset=utf-8');

// file_put_contents('authorize.json', json_encode($_GET));
// file_put_contents('authorizeinput.json', file_get_contents('php://input'));
$data = json_decode(file_get_contents('php://input'));
$project = $data->project;
$code = $data->code;
$sandbox = $data->sandbox;
$development_mode = $sandbox ? true : false;
$api = DEVELOPMENT ? ORDERING_URL_DEVELOPMENT : ORDERING_URL;
$version = API_VERSION;
$language = 'en';
$ordering_url = "{$api}/{$version}/{$language}/{$project}";

//START LOGIN ORDERING
$auth_data = [
    "email" => $data->email,
    "one_time_password" => $data->password,
];
$auth_response = requestWS($ordering_url."/auth", 'POST', null, json_encode($auth_data));
if ($auth_response["response"]->error) {
    error_response($auth_response["response"]->result, true, $auth_response["info"]["http_code"]);
    return;
}
if ($auth_response["response"]->result->level != 0) {
    error_response("User Not Authorized", true, 401);
    return;
}

//Check if plugin is installed
$headers = [
    'authorization: Bearer ' . $auth_response["response"]->result->session->access_token,
];
$plugins = request($ordering_url."/plugins", 'GET', $headers, null);
// echo $plugins;
$plugins = json_decode($plugins);
$plugins = $plugins->result;
$plugin_installed = false;
foreach ($plugins as $plugin) {
    if ($plugin->key == 'itsacheckmate_integration') {
        $plugin_installed = true;
        break;
    }
}
if (!$plugin_installed) {
    // echo 'need to install';
    $plugin = request($ordering_url."/plugins", 'POST', $headers, json_encode(["url" => INTEGRATION_URL]));
    // echo $plugin;
}
sleep(2);
// file_put_contents('response.json', json_encode($_GET));
//END LOGIN ORDERING

//START SETUP CONFIGS
$configs = get_configs($project, null, [
    "token" => $auth_response["response"]->result->session->access_token
]);
if ($configs->error) {
    // error_response($configs->result, true);
    return;
}
$configs = $configs->result;
$configs->client_secret = CLIENT_SECRET;
$configs->client_id = CLIENT_ID;
if (!$configs->client_id) {
    error_response("Missing Cliend ID on ordering Platform", true, 400);
    return;
}
if (!$configs->client_secret) {
    error_response("Missing Cliend Secret on ordering Platform", true, 400);
    return;
}
// echo $configs->client_id." -- ".$configs->client_secret;

$params = new stdClass();
$params->client_id = $configs->client_id;
$params->client_secret = $configs->client_secret;
$params->code = $code;
$params->redirect_uri = INTEGRATION_URL."/authorizeOTP.html?code={$data->code}";
$params->grant_type = 'authorization_code';

$url_params = [];
foreach ($params as $key => $value) {
    array_push($url_params, "{$key}={$value}");
}
// debug($url_params);
$url_params = implode('&', $url_params);

$url = getIntegrationUrl($development_mode)."/oauth/token?{$url_params}";
$token = requestWS($url, 'POST', null, null);
if ($token['info']['http_code'] != 200) {
    error_response($token['response'], true, $token['info']['http_code']);
    return;
}

$token = $token["response"];
$url_activate = getIntegrationUrl($development_mode)."/api/v2/activate";
$url_location = getIntegrationUrl($development_mode)."/api/v2/get_location";
$bearer = [
    'authorization: Bearer '.$token->access_token,
];
$activate = request($url_activate, 'GET', $bearer, null);
$cur_location = json_decode(request($url_location, 'GET', $bearer, null));
$payload = [
    "value" => json_encode($token)
];
$headers = [
    'authorization: Bearer ' . $auth_response["response"]->result->session->access_token,
];
request($ordering_url."/configs/{$configs->oauth_id}", 'PUT', $headers, json_encode($payload));
$final = [
    "token" => $auth_response["response"]->result->session->access_token,
    "project" => $project,
    "oauth" => $token,
    "config_id" => $configs->oauth_id,
    "location" => $cur_location->data,
    "plugins" => json_decode($plugins)
];
success_response($final, true);
//END SETUP CONFIGS
// success_response($auth_response['response']->result, true);

