<?php

$ch = curl_init('http://127.0.0.1:8005/chat');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-Key: ganti-dengan-api-key-yang-aman'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'question' => 'jelaskan panduan pendaftaran yudisium'
    ])
]);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error: ' . curl_error($ch) . "\n";
} else {
    echo $response . "\n";
}
curl_close($ch);
