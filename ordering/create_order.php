<?php
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
// debug($data);
$location = [
    "id" => 225500,
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
        "price" => $product->price,
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
                    "price" => $suboption->price,
                    "group_name" => $option->name,
                    "id" => explode('::::', $suboption->external_id)[0],
                    "parent" => explode('::::', $suboption->external_id)[1]
                ];
                array_push($sub_modifiers, $modifier);
            } else {
                $modifier = [
                    "name" => $suboption->name,
                    "quantity" => $suboption->quantity,
                    "price" => $suboption->price,
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
            "price" => $sub_modifier["price"],
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

$headers = [
    "API_KEY: 4b55d857-07bb-437a-b3e1-38db5fae7d06",
    "API_SECRET: 8543c70d-7529-4183-8f64-849318b7f71f",
];

$inject = request('https://sandbox-api.itsacheckmate.com/third_party_orders/open_api/orders/gotchew', "POST", $headers, $order);

file_put_contents("order.json", $order);

// debug($inject);
$inject = json_decode($inject);
$update = request("{$ordering_url}/orders/{$data->id}", "PUT", $headers_ordering, json_encode(["external_id" => $inject->ext_order_id]));
// debug($update);
