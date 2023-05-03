!<?php
    require_once('config.php');
    header('Content-Type: application/json; charset=utf-8');
    header("Access-Control-Allow-Origin: *");
    $data =  json_decode(file_get_contents('php://input'));

    debug($data);
    $bearer = [
        'authorization: Bearer ' . $data->oauth->access_token,
    ];
    $url = getIntegrationUrl($development_mode) . "/api/v2/activate";
    $activate = request($url, 'GET', $bearer, null);
    debug($activate);
    $url = getIntegrationUrl($development_mode) . "/api/v2/get_location";
    $cur_location = json_decode(request($url, 'GET', $bearer, null));
    debug($cur_location);
    // return;
    $headers = [
        'x-api-key: ' . $data->api_key,
    ];
    $api = DEVELOPMENT ? ORDERING_URL_DEVELOPMENT : ORDERING_URL;
    $version = API_VERSION;
    $language = 'en';
    $ordering_url = "{$api}/{$version}/{$language}/{$data->project}";

    $business = json_decode(request("{$ordering_url}/business/iacm_{$data->location_id}?mode=dashboard", 'GET', $headers, null));

    file_put_contents('chskbu.json', json_encode($cur_location));

    $business = $business->result;
    file_put_contents('curbus.json', json_encode($business));

    debug($business);


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
        $data->location_id,
        ($business && $business->name) ? $business->name : $cur_location->data->name,
        ($business && $business->logo) ? $business->logo : '',
        ($business && $business->header) ? $business->header : '',
        "iacm_" . $data->location_id,
        ($business && $business->timezone) ? $business->timezone : 'UTC',
        ($business && $business->address) ? $business->address : "{$cur_location->data->address}, {$cur_location->data->city}, {$cur_location->data->state}",
        ($business && $business->location) ? json_encode($business->location) : json_encode($location),
        '',
        '',
        ($business && $business->cellphone) ? $business->cellphone : $cur_location->data->phone_number,
        0,
        1
    );
    debug($businesses_csv);
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

    sleep(2);
    unlink($file_name_with_full_path);
