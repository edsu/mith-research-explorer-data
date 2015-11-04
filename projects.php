<?php

require(dirname(__FILE__) . '/../../../wp-config.php');
require(dirname(__FILE__) . '/functions.php');

header("Content-Type: application/json");
$json = json_encode(projects());
echo $json;

?>
