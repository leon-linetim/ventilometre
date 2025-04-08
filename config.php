<?php
// config.php
date_default_timezone_set("Europe/Paris");
// ---------- Paramètres BDD ----------
$db_host = 'localhost';
$db_name = 'DBNAME';
$db_user = 'DBUSER';
$db_pass = 'DBPASS';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// ---------- Clé API OpenWeather ----------
$API_WEATHER_KEY = 'OPENWEATHER_API'; // Mets ta clé ici

// ---------- Trouve ou crée une ville (city) ----------
function findOrCreateCity($pdo, $cityName, $postalCode = null, $lat = null, $lng = null) {
    // Vérifie si la ville existe déjà
    $sql = "SELECT * FROM city WHERE name = :cname AND postal_code = :pcode";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cname'=>$cityName, ':pcode'=>$postalCode]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        return $existing['id'];
    } else {
        // Insertion de la ville
        $sqlI = "INSERT INTO city (name, postal_code, latitude, longitude)
                 VALUES (:cname, :pcode, :lat, :lng)";
        $stmtI = $pdo->prepare($sqlI);
        $stmtI->execute([
            ':cname'=>$cityName,
            ':pcode'=>$postalCode,
            ':lat'=>$lat,
            ':lng'=>$lng
        ]);
        return $pdo->lastInsertId();
    }
}

// ---------- Met à jour la météo d'une ville (weather_data) ----------
function updateWeatherForCity($pdo, $cityId) {
    global $API_WEATHER_KEY;
    // Récup info de la ville
    $sqlC = "SELECT * FROM city WHERE id=:cid";
    $stmtC = $pdo->prepare($sqlC);
    $stmtC->execute([':cid'=>$cityId]);
    $city = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$city) return;

    $cityName = $city['name'];
    if (!$cityName || !$API_WEATHER_KEY) return;

    // Appel OpenWeather
    $url = "https://api.openweathermap.org/data/2.5/weather?q="
         . urlencode($cityName)
         . "&appid={$API_WEATHER_KEY}&units=metric&lang=fr";
    $response = @file_get_contents($url);
    if (!$response) return;

    $data = json_decode($response, true);
    if (!isset($data['wind']['speed'])) return;

    $windKmh = round($data['wind']['speed'] * 3.6);
    $temp    = $data['main']['temp'] ?? 0;
    $desc    = $data['weather'][0]['description'] ?? 'N/A';
    $now     = date("Y-m-d H:i:s");

    // Vérifie s'il y a déjà un enregistrement dans weather_data
    $sqlW = "SELECT * FROM weather_data WHERE city_id=:cid";
    $stmtW = $pdo->prepare($sqlW);
    $stmtW->execute([':cid'=>$cityId]);
    $existing = $stmtW->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // update
        $sqlU = "UPDATE weather_data
                 SET last_update=:lu, temperature=:t, wind_speed=:w, description=:d
                 WHERE city_id=:cid";
        $stmtU = $pdo->prepare($sqlU);
        $stmtU->execute([
            ':lu'=>$now,
            ':t'=>$temp,
            ':w'=>$windKmh,
            ':d'=>$desc,
            ':cid'=>$cityId
        ]);
    } else {
        // insert
        $sqlI = "INSERT INTO weather_data (city_id, last_update, temperature, wind_speed, description)
                 VALUES (:cid, :lu, :t, :w, :d)";
        $stmtI = $pdo->prepare($sqlI);
        $stmtI->execute([
            ':cid'=>$cityId,
            ':lu'=>$now,
            ':t'=>$temp,
            ':w'=>$windKmh,
            ':d'=>$desc
        ]);
    }
}

// ---------- Récupère la météo stockée pour une ville ----------
function getWeatherForCity($pdo, $cityId) {
    $sql = "SELECT * FROM weather_data WHERE city_id=:cid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cid'=>$cityId]);
    return $stmt->fetch(PDO::FETCH_ASSOC); // ou false si pas trouvé
}

// ---------- geocodeCity (optionnel) ----------
function geocodeCity($cityName) {
    // Pour simplifier, on ne fait rien, on pourrait faire un appel à geo.api.gouv.fr
    return [null, null];
}
?>