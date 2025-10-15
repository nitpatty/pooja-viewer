<?php
$clientId = '30248921-484f-452f-8cc5-340e2a1c1bfb';
$clientSecret = 'WcWjSJ3xnPq23h1HsT72OTU0FZoxIp8IWckxF2eI';

function getAccessToken($clientId, $clientSecret) {
    $ch = curl_init('https://api.prokerala.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]));
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['access_token'] ?? null;
}

function getPanchang($token, $datetime, $coordinates, $ayanamsa) {
    $url = "https://api.prokerala.com/v2/astrology/panchang?" . http_build_query([
        'datetime' => $datetime,
        'coordinates' => $coordinates,
        'ayanamsa' => $ayanamsa
    ]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
function calculateRahuKalam($vaara, $sunrise, $sunset) {
    $map = ['Sunday'=>7,'Monday'=>2,'Tuesday'=>6,'Wednesday'=>5,'Thursday'=>4,'Friday'=>3,'Saturday'=>1];
    $start = strtotime("2025-10-01 $sunrise");
    $end = strtotime("2025-10-01 $sunset");
    $slot = $map[$vaara] ?? 1;
    $duration = ($end - $start) / 8;
    return date('H:i', $start + ($slot - 1) * $duration) . ' - ' . date('H:i', $start + $slot * $duration);
}

function calculateAbhijit($sunrise, $sunset) {
    $start = strtotime("2025-10-01 $sunrise");
    $end = strtotime("2025-10-01 $sunset");
    $mid = ($start + $end) / 2;
    $duration = ($end - $start) / 15;
    return date('H:i', $mid - $duration / 2) . ' - ' . date('H:i', $mid + $duration / 2);
}

function isShubhDay($tithi, $nakshatra, $yoga) {
    $shubhTithis = ['Dwitiya', 'Tritiya', 'Panchami', 'Saptami', 'Dashami', 'Ekadashi'];
    $shubhNakshatras = ['Rohini', 'Pushya', 'Hasta', 'Anuradha', 'Uttara Phalguni'];
    $shubhYogas = ['Siddhi', 'Sukarman', 'Dhriti', 'Shubha', 'Amrita'];
    return in_array($tithi, $shubhTithis) || in_array($nakshatra, $shubhNakshatras) || in_array($yoga, $shubhYogas);
}

$days = explode(',', $_GET['days'] ?? '');
$token = getAccessToken($clientId, $clientSecret);
if (!$token) exit;

$latitude = 28.6692;
$longitude = 77.4538;
$coordinates = "$latitude,$longitude";
$ayanamsa = 1;
$timezoneOffset = '+05:30';

foreach ($days as $key) {
    if (!preg_match('/^\d{2}-\d{2}$/', $key)) continue;
    list($month, $day) = explode('-', $key);
    $date = "2025-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
    $cacheFile = __DIR__ . "/cache/$date.json";
    if (file_exists($cacheFile)) continue;

    $datetime = "$date" . "T06:00:00$timezoneOffset";
    $data = getPanchang($token, $datetime, $coordinates, $ayanamsa);

    $tithi = $data['data']['tithi'][0]['name'] ?? 'N/A';
    $paksha = $data['data']['tithi'][0]['paksha'] ?? '';
    $nakshatra = $data['data']['nakshatra'][0]['name'] ?? 'N/A';
    $yoga = $data['data']['yoga'][0]['name'] ?? 'N/A';
    $vaara = $data['data']['vaara'] ?? 'N/A';
    $sunrise = date('H:i', strtotime($data['data']['sunrise'] ?? ''));
    $sunset = date('H:i', strtotime($data['data']['sunset'] ?? ''));

    $karanas = array_map(fn($k) => $k['name'], $data['data']['karana'] ?? []);
    $yogas = array_map(fn($y) => $y['name'], $data['data']['yoga'] ?? []);

    $rahuKalam = calculateRahuKalam($vaara, $sunrise, $sunset);
    $abhijit = calculateAbhijit($sunrise, $sunset);
    $shubh = isShubhDay($tithi, $nakshatra, $yoga);

    $result = [
        'vaara' => $vaara,
        'tithi' => "$tithi ($paksha)",
        'nakshatra' => $nakshatra,
        'yoga' => $yoga,
        'sunrise' => $sunrise,
        'sunset' => $sunset,
        'karana' => implode(', ', $karanas),
        'yoga_full' => implode(', ', $yogas),
        'rahuKalam' => $rahuKalam,
        'abhijit' => $abhijit,
        'shubh' => $shubh,
        'region' => 'hindi', // placeholder — customize per location
        'festivalIcon' => null, // optional icon path
        'raw' => $data
    ];

    if (!is_dir(__DIR__ . '/cache')) {
        mkdir(__DIR__ . '/cache', 0777, true);
    }
    file_put_contents($cacheFile, json_encode($result, JSON_PRETTY_PRINT));
    sleep(1);
}

// Optional: Add festival detection logic
function detectFestival($date, $tithi, $nakshatra) {
    $festivals = [
        '01-01' => 'New Year',
        '14-01' => 'Makar Sankranti',
        '26-01' => 'Republic Day',
        '25-03' => 'Ram Navami',
        '08-04' => 'Hanuman Jayanti',
        '17-08' => 'Raksha Bandhan',
        '07-11' => 'Diwali',
        '08-11' => 'Govardhan Puja',
        '09-11' => 'Bhai Dooj'
    ];
    return $festivals[substr($date, 5)] ?? null;
}

// Optional: Add CSV export
/*
$csv = fopen(__DIR__ . '/panchang-2025.csv', 'a');
fputcsv($csv, [$date, $vaara, $tithi, $nakshatra, $yoga, $sunrise, $sunset, $rahuKalam, $abhijit, $shubh ? 'Yes' : 'No']);
fclose($csv);
*/

// Optional: Add region mapping based on coordinates or calendar type
// $result['region'] = determineRegion($latitude, $longitude);

echo json_encode(['status' => 'completed', 'processed' => count($days)]);
