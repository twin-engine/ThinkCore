<?php
/**
 * setting webhook url https://my.51tracking.com/webhook_setting.php
 * Documentation url https://www.51tracking.com/webhook.html
 */

# Introduce file class auto loading
require_once(__DIR__ ."/Autoloader.php");
use Tracking\Webhook;

$verifyEmail = "";
$webhook = new Webhook();
# Get webhook content
$response = $webhook->get($verifyEmail);

# Write the push content to the log file, note: read and write permissions are required
file_put_contents(__DIR__."/webhook.txt",$response."\r\n",FILE_APPEND);

# If you pass the data review logic and return a 200 status code, here is just a simple example
if(!empty($response)) echo "200";

exit;