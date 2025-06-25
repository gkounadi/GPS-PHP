<?php
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    $latDiff = deg2rad($lat2 - $lat1);
    $lonDiff = deg2rad($lon2 - $lon1);
    $a = sin($latDiff / 2) * sin($latDiff / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDiff / 2) * sin($lonDiff / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c; // meters
}
function convertDistance($distanceMeters, $unit) {
    return $unit === 'yards' ? ($distanceMeters * 1.09361) : $distanceMeters;
}

$data = json_decode(file_get_contents('php://input'), true);
if (($data['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$player_lat = $data['player_lat'];
$player_lon = $data['player_lon'];
$fairway_lat = $data['fairway_lat'] ?? null;
$fairway_lon = $data['fairway_lon'] ?? null;
$hole_number = $data['hole_number'];
$unit = $data['unit'] ?? 'meters';

$stmt = $pdo->prepare("SELECT * FROM holes WHERE course_id = 1 AND hole_number = ?");
$stmt->execute([$hole_number]);
$hole = $stmt->fetch(PDO::FETCH_ASSOC);

$distances = [
    'front' => convertDistance(haversineDistance($player_lat, $player_lon, $hole['green_front_lat'], $hole['green_front_lon']), $unit),
    'center' => convertDistance(haversineDistance($player_lat, $player_lon, $hole['green_center_lat'], $hole['green_center_lon']), $unit),
    'back' => convertDistance(haversineDistance($player_lat, $player_lon, $hole['green_back_lat'], $hole['green_back_lon']), $unit)
];

if ($fairway_lat && $fairway_lon) {
    $distances['to_point'] = convertDistance(haversineDistance($player_lat, $player_lon, $fairway_lat, $fairway_lon), $unit);
    $distances['from_point_to_green'] = [
        'front' => convertDistance(haversineDistance($fairway_lat, $fairway_lon, $hole['green_front_lat'], $hole['green_front_lon']), $unit),
        'center' => convertDistance(haversineDistance($fairway_lat, $fairway_lon, $hole['green_center_lat'], $hole['green_center_lon']), $unit),
        'back' => convertDistance(haversineDistance($fairway_lat, $fairway_lon, $hole['green_back_lat'], $hole['green_back_lon']), $unit)
    ];
}

echo json_encode($distances);