<?php
// import_export.php
session_start();
require_once 'config.php';

$message = "";

// -------------------- IMPORT CSV (utilisateurs + 1 ville) --------------------
if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmpName, 'r');
        if ($handle) {
            // supposons un format: first_name,last_name,user_group,city_name,postal_code
            // ligne d'en-tête (optionnel)
            fgetcsv($handle);

            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                if (count($data) < 5) continue;
                $fname  = trim($data[0]);
                $lname  = trim($data[1]);
                $group  = trim($data[2]);
                $cityN  = trim($data[3]);
                $postal = trim($data[4]);

                $uuid = uniqid('user_');

                // Insert user
                $sqlU = "INSERT INTO users (user_uuid, first_name, last_name, user_group)
                         VALUES (:uuid, :fn, :ln, :grp)";
                $stmtU = $pdo->prepare($sqlU);
                $stmtU->execute([
                    ':uuid'=>$uuid, ':fn'=>$fname, ':ln'=>$lname, ':grp'=>$group
                ]);
                $newUserId = $pdo->lastInsertId();

                // City
                list($lat, $lng) = geocodeCity($cityN);
                $cityId = findOrCreateCity($pdo, $cityN, $postal, $lat, $lng);
                // Address
                $sqlA = "INSERT INTO addresses (user_id, city_id, address_label)
                         VALUES (:uid, :cid, 'Résidence principale')";
                $stmtA = $pdo->prepare($sqlA);
                $stmtA->execute([
                    ':uid'=>$newUserId, ':cid'=>$cityId
                ]);

                // Màj météo
                updateWeatherForCity($pdo, $cityId);
            }
            fclose($handle);
            $message = "Import CSV terminé avec succès.";
        } else {
            $message = "Impossible d'ouvrir le fichier CSV.";
        }
    } else {
        $message = "Erreur de chargement du fichier CSV.";
    }
}

// -------------------- IMPORT JSON --------------------
if (isset($_POST['import_json'])) {
    if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['json_file']['tmp_name'];
        $jsonStr = file_get_contents($tmpName);
        $array = json_decode($jsonStr, true);
        if (is_array($array)) {
            foreach ($array as $item) {
                // Ex: { "first_name":"...","last_name":"...","user_group":"...","city_name":"...","postal_code":"..." }
                $fname  = $item['first_name'] ?? 'N/A';
                $lname  = $item['last_name'] ?? 'N/A';
                $group  = $item['user_group'] ?? 'ALT1-GRP1';
                $cname  = $item['city_name'] ?? 'N/A';
                $postal = $item['postal_code'] ?? '';

                $uuid = uniqid('user_');
                $sqlU = "INSERT INTO users (user_uuid, first_name, last_name, user_group)
                         VALUES (:uuid, :fn, :ln, :grp)";
                $stmtU = $pdo->prepare($sqlU);
                $stmtU->execute([
                    ':uuid'=>$uuid, ':fn'=>$fname, ':ln'=>$lname, ':grp'=>$group
                ]);
                $newUserId = $pdo->lastInsertId();

                list($lat, $lng) = geocodeCity($cname);
                $cityId = findOrCreateCity($pdo, $cname, $postal, $lat, $lng);
                $sqlA = "INSERT INTO addresses (user_id, city_id) VALUES (:uid, :cid)";
                $stmtA = $pdo->prepare($sqlA);
                $stmtA->execute([
                    ':uid'=>$newUserId, ':cid'=>$cityId
                ]);

                updateWeatherForCity($pdo, $cityId);
            }
            $message = "Import JSON terminé avec succès.";
        } else {
            $message = "Le fichier JSON est invalide.";
        }
    } else {
        $message = "Erreur de chargement du fichier JSON.";
    }
}

// -------------------- EXPORT CSV (users + leurs villes + météo) --------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=export_users_meteo.csv');
    $output = fopen("php://output", "w");

    // En-tête
    fputcsv($output, ["first_name","last_name","user_group","city_name","postal_code","wind_speed","temperature","description","last_update"]);

    // On récupère la liste des users + city + weather
    $sql = "SELECT u.first_name, u.last_name, u.user_group,
                   c.name AS city_name, c.postal_code,
                   w.wind_speed, w.temperature, w.description, w.last_update
            FROM users u
            LEFT JOIN addresses a ON a.user_id = u.id
            LEFT JOIN city c ON a.city_id = c.id
            LEFT JOIN weather_data w ON w.city_id = c.id
            ORDER BY u.id";
    $stmt = $pdo->query($sql);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['first_name'],
            $row['last_name'],
            $row['user_group'],
            $row['city_name'],
            $row['postal_code'],
            $row['wind_speed'],
            $row['temperature'],
            $row['description'],
            $row['last_update']
        ]);
    }
    fclose($output);
    exit;
}

// -------------------- EXPORT JSON --------------------
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    $sql = "SELECT u.first_name, u.last_name, u.user_group,
                   c.name AS city_name, c.postal_code,
                   w.wind_speed, w.temperature, w.description, w.last_update
            FROM users u
            LEFT JOIN addresses a ON a.user_id = u.id
            LEFT JOIN city c ON a.city_id = c.id
            LEFT JOIN weather_data w ON w.city_id = c.id
            ORDER BY u.id";
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Import / Export CSV & JSON</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
  <nav class="navbar">
    <div class="navbar-left">
      <img src="https://cdn.elsasscloud.fr/ventilateur.png" alt="Logo">
      <div class="logo" onclick="window.location.href='index.php'">Ventilomètre</div>
    </div>
    <div class="nav-user">
      <a href="index.php" class="btn">Accueil</a>
    </div>
  </nav>
</header>

<main>
  <h1>Import / Export</h1>

  <?php if (!empty($message)): ?>
    <p style="color:green;"><?php echo $message; ?></p>
  <?php endif; ?>

  <section style="margin-bottom:2rem;">
    <h2>Importer un fichier CSV</h2>
    <p>Format: first_name,last_name,user_group,city_name,postal_code</p>
    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="csv_file" accept=".csv" required>
      <button type="submit" name="import_csv">Importer CSV</button>
    </form>
  </section>

  <section style="margin-bottom:2rem;">
    <h2>Importer un fichier JSON</h2>
    <p>Format: [{ "first_name":"...", "last_name":"...", "user_group":"...", "city_name":"...", "postal_code":"..." }, ...]</p>
    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="json_file" accept=".json" required>
      <button type="submit" name="import_json">Importer JSON</button>
    </form>
  </section>

  <section style="margin-bottom:2rem;">
    <h2>Exporter</h2>
    <a href="?export=csv" class="btn">Exporter en CSV</a>
    <a href="?export=json" class="btn">Exporter en JSON</a>
  </section>
</main>

<footer>
  <p>&copy; 2025 – Ventilomètre – hébergé et propulsé en ligne par <strong>ElsassCloud.fr</strong></p>
</footer>

</body>
</html>