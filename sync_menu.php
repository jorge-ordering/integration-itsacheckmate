<?php
require_once('config.php');
require_once  './vendor/autoload.php';
require_once("./utils/database.php");
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
// return;
file_put_contents("data.json", file_get_contents('php://input'));
// $_GET["project"] = "deliverect";
// $_GET["api_key"] = "p3UygJTvqT8lJYCAunRwUXdPvHOg4sMQYUxua2uLTGEe6q7wBN8Ylppupo-UAhO9i";

$data =  json_decode(file_get_contents('php://input'));
$connection = get_location_connection($data->location_id);
// echo json_encode($connection);
if ($connection !== null) {
    $_GET["api_key"] = $connection['api_key'];
    $_GET["project"] = $connection['project_code'];
}
if (!$_GET["api_key"] || !$_GET["project"]) {
    error_response('error Missing project or Api key', true, 401);
    return;
}
$project = $_GET["project"];
$api_key = $_GET["api_key"];
debug($data);

$configs = get_configs($project, $api_key);

if ($configs->error) {
    error_response($configs->result, true);
    return;
}

$configs = $configs->result;

debug($configs);

$errors = array();

if (!$configs->source_order) {
    array_push($errors, 'Source Order Field Must be setup on plugin settings');
}

if (!$configs->api_key) {
    array_push($errors, 'Source Order Field Must be setup on plugin settings');
}

if (!$configs->api_secret) {
    array_push($errors, 'Source Order Field Must be setup on plugin settings');
}

if ($errors) {
    error_response($errors, true);
    return;
}

$development_mode = DEVELOPMENT;
$business_update = [
    "project" => $project,
    "api_key" => $api_key,
    "location_id" => $data->location_id,
    "source_order" => $configs->source_order,
    "oauth" => $configs->oauth,
    "development_mode" => $development_mode
];
debug($business_update);
request(INTEGRATION_URL."/sync_business.php", "POST", null, json_encode($business_update));



// $headers = [
//     "API_KEY: ".$configs->api_key,
//     "API_SECRET: ".$configs->api_secret,
// ];
$headers = [
    "Authorization: Bearer ".$configs->oauth->access_token,
];
debug($headers);
#Get the data of the menu for the location
// $menus = json_decode(request(getIntegrationUrl($development_mode)."/third_party_orders/open_api/menu/{$data->location_id}/{$configs->source_order}", "GET", $headers, null));
// debug(request(getIntegrationUrl($development_mode)."/api/v2/menu/{$configs->source_order}", "GET", $headers, null));
$menus = json_decode(request(getIntegrationUrl($development_mode)."/api/v2/menu/{$configs->source_order}", "GET", $headers, null));
debug($menus);
// debug(getIntegrationUrl($development_mode));
$embed_data = [];
$deletes = [
    "categories" => [],
    "products" => [],
    "extras" => [],
    "options" => [],
    "suboptions" => [],
];
$category_ids = [];
$product_ids = [];
$option_ids = [];
$suboption_ids = [];
$suspend_suboptions = [];
$suboptions_updates = [];
$cat_rank = 1;
foreach ($menus->data as $menu) {
    if ($menu->sections) {
        foreach ($menu->sections as $category) {
            $category_object = (object) [
                //Business
                "busines_id" => $data->location_id,
                //Category
                "category_id" => $category->id,
                "category_parent_id" => null,
                "category_name" => $category->name,
                "category_slug" => str_replace(" ", "_", strtolower($category->name)),
                "category_description" => $category->description,
                "category_image" => "",
                "category_rank" => $cat_rank++,
                "category_enabled" => true,
                //Product
                "product_id" => '',
                "product_name" => '',
                "product_price" => '',
                "product_description" => '',
                "product_slug" => '',
                "product_enabled" => '',
                "product_images" => '',
                "product_rank" => '',
                "product_maximum_per_order" => '',
                "product_calories" => '',
                //Extra
                "extra_id" => '',
                "extra_name" => '',
                "extra_rank" => '',
                //Option
                "option_id" => '',
                "option_name" => '',
                "option_image" => '',
                "option_min" => '',
                "option_max" => '',
                "option_rank" => '',
                //Suboption
                "subtoption_id" => '',
                "subtoption_name" => '',
                "subtoption_price" => '',
                "subtoption_max" => '',
                "subtoption_rank" => '',
                "subtoption_preselected" => '',
                //contitions
                "condition_option_id" => '',
                "condition_suboption_id" => '',

                "allow_suboption_quantity" => '',
                "limit_suboptions_by_max" => '',
            ];
            array_push($category_ids, $category->id);
            if ($category->items) {
                $pro_rank = 1;
                foreach ($category->items as $product) {
                    $product_object = clone $category_object;
                    $product_object->product_id = $product->id;
                    $product_object->product_name = $product->name;
                    $product_object->product_price = $product->price;
                    $product_object->product_description = $product->description;
                    $product_object->product_slug =  str_replace(" ", "_", strtolower($product->name));
                    $product_object->product_enabled = $product->suspend_until == 0;
                    $product_object->product_images = $product->image_urls ? $product->image_urls[0]->link : '' ;
                    $product_object->product_rank = $pro_rank++;
                    array_push($product_ids, $product->id);
                    if ($product->modifier_groups) {
                        // array_push($embed_data, $product_object);
                        $opt_rank = 1;

                        foreach ($product->modifier_groups as $option) {
                            $option_object = clone $product_object;
                            $option_object->extra_id = "EXTRA:".$product->id;
                            $option_object->extra_name = "Extra for: ".$product->name;
                            $option_object->extra_rank = 1;
                            $option_object->option_id = $option->id;
                            $option_object->option_name = $option->name;
                            $option_object->option_min = $option->minimum_amount;
                            $option_object->option_max = $option->maximum_amount;
                            $option_object->option_rank = $opt_rank++;
                            array_push($option_ids, $option->id);

                            if ($option->modifiers) {
                                $sub_rank = 1;
                                foreach ($option->modifiers as $suboption) {
                                    $suboption_object = clone $option_object;
                                    $suboption_object->subtoption_id = $suboption->id;
                                    $suboption_object->subtoption_name = $suboption->name;
                                    $suboption_object->subtoption_rank = $sub_rank++;
                                    $suboption_object->subtoption_price = $suboption->price;
                                    $suboption_object->subtoption_max = 99;
                                    $suboption_object->allow_suboption_quantity = $option->allow_modifier_multiple_quantity;
                                    $suboption_object->limit_suboptions_by_max = $option->allow_modifier_multiple_quantity;
                                    array_push($embed_data, $suboption_object);
                                    array_push($suboption_ids, $suboption_object->subtoption_id);
                                    if ($suboption->suspend_until != 0) {
                                        $suspend_suboptions[] = $suboption_object->subtoption_id;
                                    }
                                    if ($suboption->modifier_groups) {
                                        foreach ($suboption->modifier_groups as $con_option) {
                                            $optionsub_object = clone $product_object;
                                            $optionsub_object->extra_id = "EXTRA:".$product->id;
                                            $optionsub_object->extra_name = "Extra for: ".$product->name;
                                            $optionsub_object->extra_rank = 1;
                                            $optionsub_object->option_id = $con_option->id.'::::'.$suboption->id;
                                            $optionsub_object->option_name = $con_option->name;
                                            $optionsub_object->option_min = $con_option->minimum_amount;
                                            $optionsub_object->option_max = $con_option->maximum_amount;
                                            $optionsub_object->option_rank = $opt_rank++;
                                            $optionsub_object->condition_option_id = $option->id;
                                            $optionsub_object->condition_suboption_id = $suboption->id;
                                            array_push($option_ids, $con_option->id);
                                            if ($con_option->modifiers) {
                                                foreach ($con_option->modifiers as $con_suboption) {
                                                    $suboptionsub_object = clone $optionsub_object;
                                                    $suboptionsub_object->subtoption_id = $con_suboption->id.'::::'.$suboption->id;
                                                    $suboptionsub_object->subtoption_name = $con_suboption->name;
                                                    $suboptionsub_object->subtoption_rank = $sub_rank++;
                                                    $suboptionsub_object->subtoption_price = $con_suboption->price;
                                                    $suboptionsub_object->subtoption_max = 99;
                                                    $suboptionsub_object->allow_suboption_quantity = $con_option->allow_modifier_multiple_quantity;
                                                    $suboptionsub_object->limit_suboptions_by_max = $con_option->allow_modifier_multiple_quantity;

                                                    array_push($suboption_ids, $suboptionsub_object->subtoption_id);
                                                    array_push($embed_data, $suboptionsub_object);
                                                    $suboption_ids[$con_suboption->id.$con_option->id] = $suboption->suspend_until;

                                                }
                                            } else {
                                                array_push($embed_data, $optionsub_object);
                                            }
                                        }
                                    }
                                    $suboption_ids[$suboption->id] = $suboption->suspend_until;
                                }
                            } else {
                                array_push($embed_data, $option_object);
                            }
                        }
                    } else {
                        array_push($embed_data, $product_object);
                    }
                }
            } else {
                array_push($embed_data, $category_object);
            }
        }
    }
}
debug($embed_data);
// return;
//Find disables

// debug([
//     "categorys" => $category_ids,
//     "products" => $product_ids,
//     "options" => $option_ids,
//     "suboptions" => $suboption_ids,
// ]);
$headers = [
    'x-api-key: '.$api_key,
];
$api = DEVELOPMENT ? ORDERING_URL_DEVELOPMENT : ORDERING_URL;
$version = API_VERSION;
$language = 'en';
$ordering_url = "{$api}/{$version}/{$language}/{$project}";

$slug = "iacm_".$data->location_id;
$business = json_decode(request("{$ordering_url}/business/{$slug}?mode=dashboard", 'GET', $headers, null));
$business = $business->result;


//START DATA TO DELETE
foreach ($business->categories as $category) {
    $found_category = in_array($category->external_id, $category_ids);
    if (!$found_category) {
        $deletes["categories"][] = $category->id;
        request("{$ordering_url}/business/{$business->id}/categories/{$category->id}", 'POST', $headers, json_encode(['enabled' => false]));
        continue;
    }
    foreach ($category->products as $product) {
        $found_product = in_array($product->external_id, $product_ids);
        if (!$found_product) {
            $deletes["products"][] = $product->id;
            request("{$ordering_url}/business/{$business->id}/categories/{$category->id}/products/{$product->id}", 'DELETE', $headers, null);
            continue;
        }
    }

}
foreach ($business->extras as $extra) {
    // $found_extra = in_array($extra->external_id, $extra_ids);
    // if (!$found_extra) {
    //     $deletes["extras"][] = $extra->id;
    //     request("{$ordering_url}/business/{$business->id}/extras/{$extra->id}", 'DELETE', $headers, null);
    //     continue;
    // }
    foreach ($extra->options as $option) {
        $found_option = in_array($option->external_id, $option_ids);
        // if (!$found_option) {
        //     $deletes["options"][] = $option->id;
        //     // debug(request("{$ordering_url}/business/{$business->id}/extras/{$extra->id}/options/{$option->id}", 'DELETE', $headers, null));
        //     continue;
        // }
        foreach ($option->suboptions as $suboption) {
            $found_suboption = in_array($suboption->external_id, $suboption_ids);
            $supend = in_array($suboption->external_id, $suspend_suboptions);
            if  ($supend && $suboption->enabled) {
                debug(request("{$ordering_url}/business/{$business->id}/extras/{$extra->id}/options/{$option->id}/suboptions/{$suboption->id}", 'PUT', $headers, json_encode(["enabled" => false])));
            } else if (!$supend && !$suboption->enabled) {
                debug(request("{$ordering_url}/business/{$business->id}/extras/{$extra->id}/options/{$option->id}/suboptions/{$suboption->id}", 'PUT', $headers, json_encode(["enabled" => true])));
            }
            if (!$found_suboption) {
                $deletes["suboptions"][] = $suboption->id;
                request("{$ordering_url}/business/{$business->id}/extras/{$extra->id}/options/{$option->id}/suboptions/{$suboption->id}", 'DELETE', $headers, null);
                continue;
            }
        }
    }
}
// debug($suboptions_updates);
// debug($suboption_ids);
// debug($business);



//FILL CSV DATASET
$CSV = [];
array_push($CSV, array(
    'External Business ID',
    'External Category ID',
    'External Parent Category ID',
    'Category Name',
    'Category Slug',
    'Category Description',
    'Category Image',
    'Category Rank',
    'Category Enabled',
    'External Product ID',
    'Product Name',
    'Product Price',
    'Product Description',
    'Product Slug',
    'Product Enabled',
    'Product Image',
    'Product Rank',
    'Product Max Order',
    'Product Calories',
    'External Extra ID',
    'Extra Name',
    'Extra Rank',
    'External Extra Option ID',
    'Extra Option Name',
    'Extra Option Image',
    'Extra Option Min',
    'Extra Option Max',
    'Extra Option Rank',
    'External Extra Option Suboption ID',
    'Extra Option Suboption Name',
    'Extra Option Suboption Price',
    'Extra Option Suboption Max',
    'Extra Option Suboption Rank',
    'Extra Option Suboption Preselect',
    'Extra Option Respect ID',
    'Extra Option Suboption Respect ID',
    'Extra Option Suboption Quantity',
    'Extra Option Suboption Limit Max',
));

foreach ($embed_data as $csv_data) {
    array_push($CSV,[
        //Business
        $csv_data->busines_id,
        //Category
        $csv_data->category_id,
        $csv_data->category_parent_id,
        $csv_data->category_name,
        $csv_data->category_slug,
        $csv_data->category_description,
        $csv_data->category_image ?? '',
        $csv_data->category_rank,
        $csv_data->category_enabled ? 1 : 0,
        //Product
        $csv_data->product_id,
        $csv_data->product_name,
        $csv_data->product_price/100,
        $csv_data->product_description,
        $csv_data->product_slug,
        $csv_data->product_enabled ? 1 : 0,
        $csv_data->product_images ?? '',
        $csv_data->product_rank,
        $csv_data->product_maximum_per_order,
        $csv_data->product_calories,
        //Extra
        $csv_data->extra_id,
        $csv_data->extra_name,
        $csv_data->extra_rank,
        //Option
        $csv_data->option_id,
        $csv_data->option_name,
        $csv_data->option_image,
        $csv_data->option_min,
        $csv_data->option_max,
        $csv_data->option_rank,
        //Suboption
        $csv_data->subtoption_id,
        $csv_data->subtoption_name,
        $csv_data->subtoption_price ? $csv_data->subtoption_price/100 : 0,
        $csv_data->subtoption_max,
        $csv_data->subtoption_rank,
        $csv_data->subtoption_preselected ? 1 : 0,
        //contitions
        $csv_data->condition_option_id,
        $csv_data->condition_suboption_id,

        $csv_data->allow_suboption_quantity ? 1 : 0,
        $csv_data->limit_suboptions_by_max ? 1 : 0,
    ]);
}
//FILL CSV
$file_name_with_full_path = "csv/menu_{$project}.csv";

file_put_contents($file_name_with_full_path, "");
$fp = fopen($file_name_with_full_path, "w");
foreach ($CSV as $line) {
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
$post = array('import_options' => json_encode(array("separator" => ",", "start_line" => "2")),'file'=> $curlFile );
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL,$ordering_url.'/importers/sync_full_menu_default_v2/jobs');
curl_setopt($ch, CURLOPT_POST,1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result=curl_exec ($ch);
curl_close ($ch);
// debug($result);
// unlink($file_name_with_full_path);

$split_menu = [
    "menus" => $menus,
    "credentials" => $business_update
];
// debug($split_menu);
file_put_contents('split_menu.json', json_encode($split_menu));
sleep(5);
debug(request(INTEGRATION_URL."/split_menu.php", "POST", null, json_encode($split_menu)));

foreach ($suboptions_updates as $sub_update) {
   json_decode(request("{$ordering_url}/business/{$business->id}/extras/{$sub_update->extra_id}/options/{$sub_update->option_id}/suboptions/{$sub_update->suboption_id}", 'POST', $headers, json_encode($sub_update)));
}
// $business = request("{$ordering_url}/business/CHECKMATE{$data->location_id}?mode=dashboard", "GET", $headers, null);

// debug($business);

// file_put_contents("data.json", $data);
