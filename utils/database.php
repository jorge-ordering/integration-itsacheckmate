<?php

function get_database($database_name) {
    $database_path = __DIR__ . "/../databases";
    $configuration = [
        "timeout" => false,
        "primary_key" => "_id",
    ];

    $database = new \SleekDB\Store($database_name, $database_path, $configuration);

    return $database;
}

function get_location_connection($location_id) {
    $locations = get_database("locations");

    $location = $locations->findOneBy([ "location_id", "=", $location_id ]);

    return $location;
}

function insert_location_connection($location_id, $project_code, $api_key) {
    $locations = get_database("locations");

    $location = [
        'location_id' => $location_id,
        'project_code' => $project_code,
        'api_key' => $api_key
    ];

    $location = $locations->insert($location);

    return $location;
}

function sync_location_connection($location_id, $project_code, $api_key) {
    $locations = get_database("locations");

    $location = get_location_connection($location_id);

    if ($location === null) {
        $location = insert_location_connection($location_id, $project_code, $api_key);

        return $location;
    }

    $location = $locations->updateById($location['_id'], [ "api_key" => $api_key, "project_code" => $project_code]);

    return $location;
}
