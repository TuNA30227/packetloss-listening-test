<?php
$csvFile = __DIR__ . '/results.csv';

if (!file_exists($csvFile)) {
    http_response_code(404);
    echo "No data found.";
    exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="MOS_results.csv"');
header('Pragma: no-cache');
header('Expires: 0');

readfile($csvFile);
exit;
