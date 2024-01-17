<?php

use App\Models\Business;

require_once('../config.php');

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

if (!$_GET["api_key"] || !$_GET["project"]) {
    error_response('error Missing project or Api key', true, 401);
    return;
}

$project = $_GET["project"];
$api_key = $_GET["api_key"];

$data = json_decode(file_get_contents('php://input'));
$headers_ordering = [
    'x-api-key: ' . $api_key,
];
$api = DEVELOPMENT ? ORDERING_URL_DEVELOPMENT : ORDERING_URL;
$version = API_VERSION;
$language = 'en';
$ordering_url = "{$api}/{$version}/{$language}/{$project}";
$data = json_decode(request("{$ordering_url}/orders/{$data->id}?mode=dashboard", "GET", $headers_ordering, null));
$data = $data->result;
$business = json_decode(request("{$ordering_url}/business/{$data->business_id}?mode=dashboard", "GET", $headers_ordering, null));
$business = $business->result;
$addresses = json_decode(request("{$ordering_url}/users/{$data->customer_id}/addresses", "GET", $headers_ordering, null));
$addresses = $addresses->result;
$address = null;
foreach ($addresses as $_address) {
    if ($_address->address == $data->customer->address) {
        $address = $_address;
    }
}
file_put_contents('data_order.json', json_encode($data));
file_put_contents('address.json', json_encode($address));
$location_id = null;
if (strpos($business->slug, 'iacm_') !== false) {
    $location_id = substr($business->slug, strlen('iacm_'));
    // echo "El número encontrado es: " . $numero;
} else {
    echo "El prefijo no está presente en la palabra.";
    return;
}
// return;
$oauth_data = null;
$config_id = null;
foreach ($business->configs as $config) {
    if ($config->key == 'itsacheckmate_integration_oauth') {
        $oauth_data = $config->value;
        $config_id =  $config->id;
    }
}
echo $oauth_data;
if ($oauth_data) {
    //check valid
    $oauth_data = json_decode($oauth_data, true);
    $expirationTime = $oauth_data['created_at'] + $oauth_data['expires_in'];
    $currentTimestamp = time();

    if ($currentTimestamp < $expirationTime) {
        // echo "El token es válido.";
    } else {
        // echo json_encode($configs);
        $refresh_data = json_encode([
            "client_id" => CLIENT_ID,
            "client_secret" => CLIENT_SECRET,
            "refresh_token" => $oauth_data['refresh_token'],
            "grant_type" => 'refresh_token'
        ]);
        $token = requestWS('https://api.itsacheckmate.com/oauth/token', "POST", null, $refresh_data);
        if ($token['info']['http_code'] != 200) {
            echo 'erro';
            error_response($token['response'], true, $token['info']['http_code']);
            return;
        }
        // echo json_encode($token);
        $oauth_data = json_decode(json_encode($token['response']), true);
        $config_data = json_encode([
            "value" => json_encode($token['response'])
        ]);
        $config = json_decode(request("{$ordering_url}/business/{$data->business_id}/configs/{$config_id}", "PUT", $headers_ordering, $config_data));
        // echo json_encode($config);

        //api.itsacheckmate.com/oauth/token
    }
} else {
    return;
}
$location = [
    "id" => (int) $location_id,
    "timezone" => "UTC"
];
$customer = [
    "first_name" => $data->customer->name,
    "last_name" => $data->customer->lastname,
    "phone" => $data->customer->cellphone,
    "email" => $data->customer->email,
    "address" => [
        "street" => $address ? "{$address->street_number} {$address->route}" : $data->customer->address,
        "city" => $address ? $address->city : $data->customer->address,
        "state" => $address ? $address->state  : $data->customer->address,
        "postal_code" => $address ? $address->zipcode  : $data->customer->zipcode,
    ]
];
$items = [];
foreach ($data->products as $product) {
    $item = [
        "name" => $product->name,
        "quantity" => $product->quantity,
        "price" => $product->price*100,
        "special_request" => $product->comment,
        "id" => $product->external_id,
        "modifiers" => []
    ];
    $main_modifiers = [];
    $sub_modifiers = [];
    foreach ($product->options as $option) {
        foreach ($option->suboptions as $suboption) {
            if (count(explode('::::', $suboption->external_id)) > 1) {
                $modifier = [
                    "name" => $suboption->name,
                    "quantity" => $suboption->quantity,
                    "price" => $suboption->price*100,
                    "group_name" => $option->name,
                    "id" => explode('::::', $suboption->external_id)[0],
                    "parent" => explode('::::', $suboption->external_id)[1]
                ];
                array_push($sub_modifiers, $modifier);
            } else {
                $modifier = [
                    "name" => $suboption->name,
                    "quantity" => $suboption->quantity,
                    "price" => $suboption->price*100,
                    "group_name" => $option->name,
                    "id" => $suboption->external_id,
                    "modifiers" => []
                ];
                $main_modifiers[$suboption->external_id] = $modifier;
            }
        }
    }
    foreach ($sub_modifiers as $sub_modifier) {
        array_push($main_modifiers[$sub_modifier["parent"]]["modifiers"],  [
            "name" => $sub_modifier["name"],
            "quantity" => $sub_modifier["quantity"],
            "price" => $sub_modifier["price"] * 100,
            "group_name" => $sub_modifier["group_name"],
            "id" => $sub_modifier["id"],
        ]);
    }
    $item["modifiers"] = array_values($main_modifiers);
    array_push($items, $item);
}
$meta = [
    "id" => $data->id,
    "type" => traduceIntegration("delivery:{$data->delivery_type}"),
    "notes" => $data->comment,
    "requested_at" => (in_array($data->delivery_type, [1, 2]) && $data->status == 13) ? (new DateTime($data->delivery_datetime_utc,  new DateTimeZone("UTC")))->format('c') : null,
];

if ($data->delivery_option) {
    $meta["notes"] .= " Delivery Option: {$data->delivery_option->name}";
}

$payment = [
    "cash_payment" => $data->paymethod->gateway == 'cash',
    "tip" => $data->summary->driver_tip,
    "discounts" => [
        [
            "name" => "General Discount",
            "amount" => $data->summary->discount,
        ]
    ],
    // "service_fees" => [$data->summary->service_fee],
];

if ($data->fees) {
    $payment["service_fees"] = [];
    foreach ($data->fees as $fee) {
        array_push($payment["service_fees"], ["name" => $fee->name, "amount" => $fee->summary->fixed + $fee->summary->percentage_after_discount]);
    }
}
$order = json_encode([
    "order" => [
        "customer" => $customer,
        "items" => $items,
        "meta" => $meta,
        "location" => $location,
        "payment" => $payment,
    ]
]);

debug($order);

// $headers = [
//     "API_KEY: 4b55d857-07bb-437a-b3e1-38db5fae7d06",
//     "API_SECRET: 8543c70d-7529-4183-8f64-849318b7f71f",
// ];
$headers = [
    "Authorization: Bearer {$oauth_data['access_token']}"
];
$inject = request('https://api.itsacheckmate.com/api/v2/orders/gotchew', "POST", $headers, $order);
echo $inject;

file_put_contents("order.json", $order);
file_put_contents("inject.json", $inject);

// debug($inject);
$inject = json_decode($inject);
$update = request("{$ordering_url}/orders/{$data->id}", "PUT", $headers_ordering, json_encode(["external_id" => $inject->ext_order_id]));
// debug($update);
