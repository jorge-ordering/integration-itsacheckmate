<?php
#CONSTANS
define("CHECKMATE_URL_DEVELOPMENT", "https://sandbox-api.itsacheckmate.com");
define("CHECKMATE__URL_PRODUCTION", "https://api.itsacheckmate.com");
define("INTEGRATION_URL", "https://integrations.ordering.co/itsacheckmate");
define("CLIENT_ID", "2edd625e7ce97ad961ebd450085960908a255280cd6281459a10af3f2b2a30a8");
define("CLIENT_SECRET", "581b0cfab4956fb13936c4396a65a16b7577a63ec22cec3edc1ac40e5794fef5");
define("API_KEY", "4b55d857-07bb-437a-b3e1-38db5fae7d06");
define("API_SECRET", "8543c70d-7529-4183-8f64-849318b7f71f");
define("SOURCE", "gotchew");

// define("INTEGRATION_URL", "https://3cde-152-201-174-69.ngrok.io/plugins/itsacheckmate");

// Ordering constants
define("ORDERING_URL", "https://apiv4.ordering.co");
define("ORDERING_URL_DEVELOPMENT", "https://21da-167-0-212-53.ngrok.io");
define("API_VERSION", "v400");
define("DEVELOPMENT", FALSE);
define("DEBUG", TRUE);

#UTILS


#Any HTTP REQUEST
function request($url, $method, $additional_headers, $data = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if (in_array($method, ['PUT', 'POST'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, 1);
    }
    $additional_headers[] = 'Accept: application/json';
    $additional_headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $additional_headers);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

#Any HTTP REQUEST
function requestWS($url, $method, $additional_headers, $data = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if (in_array($method, ['PUT', 'POST'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, 1);
    }
    $additional_headers[] = 'Accept: application/json';
    $additional_headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $additional_headers);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);

    curl_close($ch);
    return [
        "response" => json_decode($res),
        "info" => $info
    ];
}

# Get plugin settings
function get_configs($project = null, $api_key = null, $options = [])
{
    $api = DEVELOPMENT ? ORDERING_URL_DEVELOPMENT : ORDERING_URL;
    $version = API_VERSION;
    $language = 'en';
    $url = "{$api}/{$version}/{$language}/{$project}/config_categories?mode=dictionary&where=[{%22attribute%22:%22key%22,%22value%22:%22plugin_itsacheckmate_integration%22}]";
    $headers = [
        'x-api-key: ' . $api_key,
    ];
    if (isset($options['token'])) {
        $headers = [
            'authorization: Bearer ' . $options['token'],
        ];
    }
    $configs = json_decode(request($url, 'GET', $headers, null));
    if ($configs->error) {
        return error_response($configs->result, true);
    }
    if (!$configs->result) {
        return error_response("Its a Ckeckmate plugin must be setup on Ordering Platform", true);
    }
    $_configs = new stdClass();
    foreach ($configs->result[0]->configs as $config) {
        $key = str_replace("itsacheckmate_integration_", "", $config->key);
        $value = $config->value;
        $_configs->$key = $value;
        if ($key = "oauth") {
            $_configs->$key = json_decode($value);
            $_configs->oauth_id = $config->id;
        }
    }
    $_configs->api_key = API_KEY;
    $_configs->api_secret = API_SECRET;
    $_configs->source_order = SOURCE;
    if ($_configs->oauth) {
        $oauth = $_configs->oauth;
        // if (tokenExpired($oauth->created_at + $oauth->expires_in)) {
        //     $params = new stdClass();
        //     $params->client_id = $_configs->client_id;
        //     $params->client_secret = $_configs->client_secret;
        //     $params->refresh_token = $oauth->refresh_token;
        //     $params->grant_type = 'refresh_token';
        //     $url_params = [];
        //     foreach ($params as $key => $value) {
        //         array_push($url_params, "{$key}={$value}");
        //     }
        //     $url_params = implode('&', $url_params);
        //     $development_mode = $_configs->development_mode == '1';
        //     $url = getIntegrationUrl($development_mode) . "/oauth/token?{$url_params}";
        //     $token = json_decode(request($url, 'POST', null, null));
        //     $_configs->oauth = $token;
        //     $ordering_url = "{$api}/{$version}/{$language}/{$project}";
        //     $payload = [
        //         "value" => json_encode($token)
        //     ];
        //     request($ordering_url . "/configs/{$_configs->oauth_id}", 'PUT', $headers, json_encode($payload));
        // }
    }
    return success_response($_configs);
}

function getIntegrationUrl($development = true)
{
    return $development ? CHECKMATE_URL_DEVELOPMENT : CHECKMATE__URL_PRODUCTION;
}

function debug($data)
{
    if (DEBUG) {
        if (in_array(gettype($data), ['object', 'array'])) {
            $data = json_encode($data);
        }
        echo $data;
    }
}


#Responses
function error_response($data, $http = false, $code = 400)
{
    $response = [
        "error" => true,
        "result" => $data
    ];
    if ($http) {
        http_response_code($code);
        echo json_encode($response);
    }
    return (object) $response;
}

function success_response($data, $http = false, $code = 200)
{
    $response = [
        "error" => false,
        "result" => $data
    ];
    if ($http) {
        http_response_code($code);
        echo json_encode($response);
    }
    return (object) $response;
}

#Dictionary

function traduceIntegration($translate)
{
    $translations = [
        "delivery:1" => "driver_delivery",
        "delivery:2" => "pick_up",
        "delivery:3" => "catering",
        "delivery:4" => "restaurant_delivery",
    ];
    return $translations[$translate] ?? null;
}

#defaul schedule
function default_schedule()
{
    return $SCHEDULE = [
        [
            'enabled' => false,
            'lapses' => [
                [
                    'open' => [
                        'hour' => 0,
                        'minute' => 0,
                    ],
                    'close' => [
                        'hour' => 23,
                        'minute' => 59,
                    ],
                ],
            ],
        ],
        [
            'enabled' => false,
            'lapses' => [
                [
                    'open' => [
                        'hour' => 0,
                        'minute' => 0,
                    ],
                    'close' => [
                        'hour' => 23,
                        'minute' => 59,
                    ],
                ],
            ],
        ],
        [
            'enabled' => false,
            'lapses' => [
                [
                    'open' => [
                        'hour' => 0,
                        'minute' => 0,
                    ],
                    'close' => [
                        'hour' => 23,
                        'minute' => 59,
                    ],
                ],
            ],
        ],
        [
            'enabled' => false,
            'lapses' => [
                [
                    'open' => [
                        'hour' => 0,
                        'minute' => 0,
                    ],
                    'close' => [
                        'hour' => 23,
                        'minute' => 59,
                    ],
                ],
            ],
        ],
        [
            'enabled' => false,
            'lapses' => [
                [
                    'open' => [
                        'hour' => 0,
                        'minute' => 0,
                    ],
                    'close' => [
                        'hour' => 23,
                        'minute' => 59,
                    ],
                ],
            ],
        ],
        [
            'enabled' => false,
            'lapses' => [
                [
                    'open' => [
                        'hour' => 0,
                        'minute' => 0,
                    ],
                    'close' => [
                        'hour' => 23,
                        'minute' => 59,
                    ],
                ],
            ],
        ],
        [
            'enabled' => false,
            'lapses' => [
                [
                    'open' => [
                        'hour' => 0,
                        'minute' => 0,
                    ],
                    'close' => [
                        'hour' => 23,
                        'minute' => 59,
                    ],
                ],
            ],
        ],
    ];
}
#Transform schedule to ordering format
function transformShedule($hours, $schedule = null, $merge = false)
{
    $dict_schedule = [
        "monday" => 1,
        "tuesday" => 2,
        "wednesday" => 3,
        "thursday" => 4,
        "friday" => 5,
        "saturday" => 6,
        "sunday" => 0,
    ];
    if (!$schedule) {
        $schedule = default_schedule();
    }
    $hours = stardarHour($hours);
    foreach ($hours as $key => $value) {
        $pos = $dict_schedule[$key];
        $schedule[$pos]['enabled'] = true;
        $lapses = [];
        // debug($value);
        foreach ($value as $lapse) {
            $_lapse = [
                "open" => [
                    "hour" => intval(explode(':', $lapse->start_time)[0]),
                    "minute" => intval(explode(':', $lapse->start_time)[1]),
                ],
                "close" => [
                    "hour" => intval(explode(':', $lapse->end_time)[0]),
                    "minute" => intval(explode(':', $lapse->end_time)[1]),
                ]
            ];
            array_push($lapses, $_lapse);
        }
        $schedule[$pos]['lapses'] = !$merge ? $lapses : array_merge($schedule[$pos]['lapses'], $lapses);
    }
    // debug($schedule);
    return $schedule;
}

function tokenExpired($expire_at)
{
    if (!$expire_at) {
        return true;
    }
    $now = time();
    return $expire_at < ($now - 300);
}

#STANDARIZE HOURS
function stardarHour($hours)
{
    $next = [
        "monday" => "tuesday",
        "tuesday" => "wednesday",
        "wednesday" => "thursday",
        "thursday" => "friday",
        "friday" => "saturday",
        "saturday" => "sunday",
        "sunday" => "monday",
    ];
    foreach ($hours as $key => $value) {
        foreach ($value as $lapse) {
            if (intval(explode(':', $lapse->start_time)[0] > intval(explode(':', $lapse->end_time)[0]))) {
                $next_lapse = (object) [
                    "start_time" => "00:00",
                    "end_time" => $lapse->end_time,
                ];
                $next_day = $next[$key];
                array_push($hours->$next_day, $next_lapse);
                $lapse->end_time = "23:59";
            }
        }
    }
    return $hours;
}


#GET API KEY
function get_api_key($project = null, $token = null)
{
    $api = DEVELOPMENT ? ORDERING_URL_DEVELOPMENT : ORDERING_URL;
    $version = API_VERSION;
    $language = 'en';
    $url = "{$api}/{$version}/{$language}/{$project}/";

    $headers = [
        'authorization: Bearer ' . $token,
    ];
    $me = json_decode(request($url.'users/me', 'GET', $headers, null));
    $apiKeys = json_decode(request($url.'users/'.$me->result->id.'/keys', 'GET', $headers, null));
    $apiKeys = $apiKeys->result;
    if  ($apiKeys) {
        return $apiKeys[0]->key;
    } else {
        $apiKey = json_decode(request($url.'users/'.$me->result->id.'/keys', 'POST', $headers, null));
        return $apiKey->result->key;
    }
}

/**
 * Get header Authorization
 * */
function getAuthorizationHeader()
{
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        //print_r($requestHeaders);
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

/**
 * get access token from header
 * */
function getBearerToken()
{
    $headers = getAuthorizationHeader();
    // HEADER: Get the access token from the header
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}
