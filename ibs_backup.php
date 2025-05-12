<?php

date_default_timezone_set("Asia/Tehran"); // Set your timezone if needed

$token = "TOKEN"; // Replace with your token
$chat_id = "ADMIN_ID"; // Replace with your chat ID
$timestamp = date("Y-m-d_H-00");


exec("service IBSng stop");

$truncateCmd = "psql -d IBSng -U ibs -c \"TRUNCATE TABLE connection_log_details, internet_bw_snapshot, connection_log, internet_onlines_snapshot\"";
exec($truncateCmd);

exec("service IBSng start");

exec("rm /var/log/IBSng/ibs_*");

$dumpFile = "IBSng_{$timestamp}.sql";
exec("su postgres -c \"pg_dump IBSng\" > $dumpFile");



if (!file_exists($dumpFile)) {
    die("File not found!");
}

$url = "https://api.telegram.org/bot{$token}/sendDocument";

$boundary = uniqid();

$body = "--{$boundary}\r\n";
$body .= "Content-Disposition: form-data; name=\"chat_id\"\r\n\r\n";
$body .= "{$chat_id}\r\n";
$body .= "--{$boundary}\r\n";
$body .= "Content-Disposition: form-data; name=\"document\"; filename=\"$dumpFile\"\r\n";
$body .= "Content-Type: text/plain\r\n\r\n";
$body .= file_get_contents($dumpFile) . "\r\n";
$body .= "--{$boundary}--\r\n";

$headers = [
    "Content-Type: multipart/form-data; boundary={$boundary}",
    "Content-Length: " . strlen($body)
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $body
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "Error: Failed to send the request.";
} else {
    $result = json_decode($response, true);
    if ($result['ok']) {
        echo "File sent successfully!";
        unset($dumpFile);
    } else {
        echo "Telegram API Error: " . $result['description'];
    }
}
