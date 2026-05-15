<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

// The Handshake
echo json_encode([
    'status' => 'success',
    'message' => 'Connection Established! ',
    'app_name' => 'Jobmington Mobile',
    'server_time' => date('Y-m-d H:i:s'),
    'api_version' => 'v1'
]);
?>