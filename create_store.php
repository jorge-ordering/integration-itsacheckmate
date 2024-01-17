<?php
require_once('config.php');
require_once  './vendor/autoload.php';
require_once("./utils/database.php");
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
$data =  json_decode(file_get_contents('php://input'));

$api = DEVELOPMENT ? ORDERING_URL_DEVELOPMENT : ORDERING_URL;
$version = API_VERSION;
$language = 'en';
$ordering_url = "{$api}/{$version}/{$language}/{$data->project}";
$headers = [
    'authorization: Bearer ' . $data->token,
];
try {
    $business = json_decode(request("{$ordering_url}/business/iacm_{$data->location->id}?mode=dashboard", 'GET', $headers, null));
    $business_error = $business->error;
    if ($business->error) {
        error_response($business->result, true);
        return;
    }
    $business = $business->result;
    if ($business) {
        error_response("Already exists a business asociated with this LOCATION ID, Select Connet existing store", true);
        return;
    }

    $businesses_csv[0] = array(
        'External business id',
        'Name',
        'Logo',
        'Header',
        'Slug',
        'Timezone',
        'Address',
        'Location',
        'Description',
        'Phone',
        'Cellphone',
        'Featured',
        'Enabled'
    );
    $location = [
        "lat" => 0,
        "lng" => 0,
        "zipcode" => -1,
        "zoom" => 15
    ];

    $businesses_csv[1] = array(
        $data->location->id,
        $data->location->name,
        '',
        '',
        "iacm_" . $data->location->id,
        'UTC',
        "{$data->location->address}, {$data->location->city}, {$data->location->state}",
        json_encode($location),
        '',
        '',
        $data->location->phone_number,
        0,
        1
    );
    $file_name_with_full_path = "csv/businesses_{$data->project}.csv";
    file_put_contents($file_name_with_full_path, "");
    $fp = fopen($file_name_with_full_path, "w");

    foreach ($businesses_csv as $line) {
        // though CSV stands for "comma separated value"
        // in many countries (including France) separator is ";"
        fputcsv($fp, $line, ',');
    }

    fclose($fp);

    if (function_exists('curl_file_create')) {
        $curlFile = curl_file_create($file_name_with_full_path);
    } else {
        $curlFile = '@' . realpath($file_name_with_full_path);
    }
    $post = array('import_options' => json_encode(array("separator" => ",", "start_line" => "2")), 'file' => $curlFile);
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "{$ordering_url}/importers/sync_businesses_default/jobs");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    curl_close($ch);

    sleep(5);
    unlink($file_name_with_full_path);

    $business = json_decode(request("{$ordering_url}/business/iacm_{$data->location->id}?mode=dashboard", 'GET', $headers, null));
    $business = $business->result;

    $payload = [
        "value" => json_encode($data->oauth)
    ];
    json_decode(request("{$ordering_url}/business/{$business->id}/configs/{$data->config_id}", 'PUT', $headers, json_encode($payload)));

    $push_menu = [
        "location_id" => $data->location->id
    ];
    $apiKeyOrdering = get_api_key($data->project, $data->token);
    // $urlPush = INTEGRATION_URL . "/sync_menu.php?project={$data->project}&api_key={$apiKeyOrdering}";
    sync_location_connection($data->location->id, $data->project, $apiKeyOrdering);
    request(INTEGRATION_URL . "/sync_menu.php?project={$data->project}&api_key={$apiKeyOrdering}", "POST", null, json_encode($push_menu));
    file_put_contents("afteCreateStore.json", json_encode(
        [
            "url" => $urlPush,
            "payload" => $push_menu
        ]
    ));
    // success_response($business, true);
} catch (Throwable $e) {
    file_put_contents('errorCreate.txt', $e->getMessage());
    // error_response($e->getMessage(), true);
    print_r( $e->getMessage());
}
