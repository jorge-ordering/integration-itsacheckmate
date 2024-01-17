<?php
require_once('config.php');
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
$data =  json_decode(file_get_contents('php://input'));

$api = DEVELOPMENT ? ORDERING_URL_DEVELOPMENT : ORDERING_URL;
$version = API_VERSION;
$language = 'en';
$base_url = "{$api}/{$version}/{$language}/{$data->credentials->project}";
// debug($base_url);
$slug = "iacm_".$data->credentials->location_id;

$key = $data->credentials->api_key;
$headers = [];
$headers[] = 'x-api-key: '.$key;
$business = json_decode(request("{$base_url}/business/{$slug}?mode=dashboard", 'GET', $headers, null));

$business = $business->result;

$ordering_products = [];
$ordering_categories = [];
foreach ($business->categories as $category) {
    foreach ($category->products as $product) {
        if ($product->external_id) {
            $ordering_products[$product->external_id] = $product->id;
            $ordering_categories[$product->id] = $product->category_id;
        }
    }
}
$ordering_menus = [];
foreach ($business->menus as $menu) {
    if ($menu->external_id) {
        $ordering_menus[$menu->external_id] = $menu->id;
    }
}
// debug($business);
// return;
$menus = [];
$alcoholics = [];
$tax = 0;
$business_schedule = default_schedule();
$business_schedule_count = 0;
foreach ($data->menus->data as $menu) {
    $_menu = [
        "external_id" => $menu->id,
        "name" => $menu->name,
        "id" => $ordering_menus[$menu->id] ?? null,
        "products" => [],
        "schedule" => json_encode(transformShedule($menu->hours)),
        "comment" => $menu->description,
        "pickup" => true,
        "delivery" => true,
    ];
    $business_schedule = transformShedule($menu->hours, $business_schedule, $business_schedule_count != 0);
    $business_schedule_count++;
    foreach ($menu->sections as $section) {
        foreach ($section->items as $item) {
            if ($ordering_products[$item->id]) {
                array_push($_menu['products'], $ordering_products[$item->id]);
                if ($item->is_alcohol) {
                    array_push($alcoholics, $ordering_products[$item->id]);
                }
            }
            if ($item->tax_rate && $item->tax_rate > $tax) {
                $tax = $item->tax_rate;
            }
        }
    }
    $_menu['products'] = json_encode($_menu['products']);
    array_push($menus, $_menu);
    $method = $_menu['id'] ? 'PUT' : 'POST';
    $url = $_menu['id'] ?
        "{$base_url}/business/{$business->id}/menus/{$_menu['id']}" :
        "{$base_url}/business/{$business->id}/menus";
    // debug($url);
    $add_update = request($url, $method, $headers, json_encode($_menu));
    debug($add_update);
}

if (!$business->zones) {
    $zone = '{"name":"EVERYWHERE","type":4,"data":"null","minimum":"0","price":"0","enabled":true,"schedule":"[{\"enabled\":true,\"lapses\":[{\"open\":{\"hour\":0,\"minute\":0},\"close\":{\"hour\":23,\"minute\":59}}]},{\"enabled\":true,\"lapses\":[{\"open\":{\"hour\":0,\"minute\":0},\"close\":{\"hour\":23,\"minute\":59}}]},{\"enabled\":true,\"lapses\":[{\"open\":{\"hour\":0,\"minute\":0},\"close\":{\"hour\":23,\"minute\":59}}]},{\"enabled\":true,\"lapses\":[{\"open\":{\"hour\":0,\"minute\":0},\"close\":{\"hour\":23,\"minute\":59}}]},{\"enabled\":true,\"lapses\":[{\"open\":{\"hour\":0,\"minute\":0},\"close\":{\"hour\":23,\"minute\":59}}]},{\"enabled\":true,\"lapses\":[{\"open\":{\"hour\":0,\"minute\":0},\"close\":{\"hour\":23,\"minute\":59}}]},{\"enabled\":true,\"lapses\":[{\"open\":{\"hour\":0,\"minute\":0},\"close\":{\"hour\":23,\"minute\":59}}]}]"}';
    $add_zone = request("{$base_url}/business/{$business->id}/deliveryzones", 'POST', $headers, $zone);
    debug($add_zone);
}

if (!$business->webhooks) {
    $hook = '{"hook":"orders_register","url":"https://integrations.ordering.co/pro-itsacheckmate/ordering/create_order.php?api_key='.$data->credentials->api_key.'&project='.$data->credentials->project.'"}';
    $add_hook = request("{$base_url}/business/{$business->id}/webhooks", 'POST', $headers, $hook);
    debug($add_hook);

}

if (!$business->paymethods) {
    $get_paymethods = json_decode(request("{$base_url}/paymethods", 'GET', $headers, null));
    if ($get_paymethods->result) {
        $cash = null;
        foreach ($get_paymethods->result as $paymethod) {
            if ($paymethod->gateway === 'cash') {
                $cash = $paymethod->id;
            }
        }
        if ($cash) {
            $pay = [
                "enabled" => true,
                "paymethod_id" => $cash,
                "sandbox" => false,
            ];
            $add_pay = request("{$base_url}/business/{$business->id}/paymethods", 'POST', $headers, json_encode($pay));
            debug($add_pay);
        }
    }
}

if ($business->tax != $tax || $business->tax_type != 2) {
    $update_tax = [
        "tax" => $tax,
        "tax_type" => 2,
    ];
    $update_tax = request("{$base_url}/business/{$business->id}", 'POST', $headers, json_encode($update_tax));
    debug($update_tax);
}

if (count($alcoholics) > 0) {
    $tags = json_decode(request("{$base_url}/tags", 'GET', $headers, null));
    $tags = $tags->result;
    $alcoholics_tag = null;
    foreach ($tags as $tag) {
        if ($tag->name == 'Alcoholics') {
            $alcoholics_tag = $tag->id;
        }
    }
    if (!$alcoholics_tag) {
        $tag = json_encode([
            "name" => 'Alcoholics',
        ]);
        $tags = json_decode(request("{$base_url}/tags", 'POST', $headers, $tag));
        $alcoholics_tag = $tags->result->id;
    }
    foreach ($alcoholics as $alcoholic) {
        $tag = json_encode([$alcoholics_tag]);
        $tag = json_encode([
            "tags" => $tag,
        ]);
        request("{$base_url}/business/{$business->id}/categories/{$ordering_categories[$alcoholic]}/products/{$alcoholic}", 'POST', $headers, $tag);
    }
}

$business_update = json_encode([
    "schedule" => json_encode($business_schedule)
]);
request("{$base_url}/business/{$business->id}", 'POST', $headers, $business_update);
// file_put_contents('schedule.json', json_encode($business_schedule));
// debug($menus);
