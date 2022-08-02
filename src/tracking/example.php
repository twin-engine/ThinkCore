<?php
/**
 * Get api key url https://my.51tracking.com/get_apikey.php
 * Documentation url https://www.51tracking.com/api-index.html
 */

# Introduce file class auto loading
require_once("./Autoloader.php");
use Tracking\Api;
# Pass api key parameter
$api = new Api('you api key');
#sandbox model
$api->sandbox = true;

# Create a tracking number
$data = [
    ["tracking_number" => "YT2205421266056615", "courier_code" => "yunexpress"],
    ["tracking_number" => "303662548678", "courier_code" => "qichen"],
];
$response = $api->create($data);

# Get logistics information for multiple tracking numbers
// $data = ["tracking_numbers" => "YT2205421266056615,303662548678"];
// $response = $api->get($data);

# Get a list of all carriers
// $response = $api->courier();

# Modify the information of multiple tracking numbers
// $data = [["tracking_number"=>"YT2205421266056615", "courier_code"=>"yunexpress", "order_number" => "#1234"]];
// $response =  $api->modifyinfo($data);

# Modify the carrier code of a tracking number
// $data = ["num" => "RP325552475CN", "express" => "china-post", "new_express" => "china-ems"];
// $response =  $api->modifyCourier($data);

# Delete multiple tracking numbers
// $data = [["tracking_number"=>"YT2205421266056614", "courier_code"=>"yunexpress"]];
// $response = $api->delete($data);

var_export($response);