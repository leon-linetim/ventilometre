<?php
// user.php
session_start();
require_once 'config.php';

if (!isset($_GET['id'])) {
    die("Utilisateur non spécifié");
}
$userId = $_GET['id'];

// Récup user
$sqlU = "SELECT * FROM users WHERE id=:id";
$stmtU = $pdo->prepare($sqlU);
$stmtU->execute([':id'=>$userId]);
$user = $stmtU->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die("Utilisateur introuvable.");
}

// Traitement du bouton "Rafraîchir" (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_city_id'])) {
    $cityId = $_POST['refresh_city_id'];
    updateWeatherForCity($pdo, $cityId);
    header("Location: user.php?id=".$userId);
    exit;
}

// Récup addresses + city
$sqlA = "SELECT a.id AS addr_id, a.address_label,
                c.id AS city_id, c.name AS city_name, c.postal_code,
                c.latitude, c.longitude
         FROM addresses a
         JOIN city c ON a.city_id = c.id
         WHERE a.user_id = :uid";
$stmtA = $pdo->prepare($sqlA);
$stmtA->execute([':uid'=>$userId]);
$addresses = $stmtA->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Profil – <?php echo htmlspecialchars($user['first_name']); ?></title>
  <link rel="stylesheet" href="css/style.css">

  <!-- Leaflet pour carte OSM -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

  <script src="js/script.js"></script>
</head>
<body>
<header>
  <nav class="navbar">
    <div class="navbar-left">
      <img src="https://cdn.elsasscloud.fr/ventilateur.png" alt="Logo">
      <div class="logo" onclick="window.location.href='index.php'">Ventilomètre</div>
    </div>
    <div class="nav-user">
      <span>ID: <?php echo htmlspecialchars($user['user_uuid']); ?></span>
      <a href="edit_user.php?user_id=<?php echo $userId; ?>" class="btn">Modifier Profil</a>
      <a href="manage_addresses.php?user_id=<?php echo $userId; ?>" class="btn">Gérer Adresses</a>
    </div>
  </nav>
</header>

<main>
  <h1>Profil de <?php echo htmlspecialchars($user['first_name']." ".$user['last_name']); ?></h1>
  <p>Groupe : <strong><?php echo $user['user_group']; ?></strong></p>

  <?php if ($addresses): ?>
    <?php foreach ($addresses as $addr): ?>
      <?php
        // Récup météo stockée
        $weather = getWeatherForCity($pdo, $addr['city_id']);
      ?>
      <div class="residence-card">
        <h2><?php echo htmlspecialchars($addr['address_label']); ?> – <?php echo htmlspecialchars($addr['city_name']); ?></h2>
        <?php if ($weather): ?>
          <p>Vent : <?php echo $weather['wind_speed']; ?> km/h</p>
          <p>Température : <?php echo $weather['temperature']; ?> °C</p>
          <p>Conditions : <?php echo htmlspecialchars($weather['description']); ?></p>
          <p>Dernière actualisation : <?php echo $weather['last_update']; ?></p>
        <?php else: ?>
          <p>Aucune donnée météo enregistrée pour cette ville.</p>
        <?php endif; ?>

        <form method="POST" action="user.php?id=<?php echo $userId; ?>">
          <input type="hidden" name="refresh_city_id" value="<?php echo $addr['city_id']; ?>">
          <button type="submit">Rafraîchir</button>
        </form>

        <!-- Affichage carte -->
        <?php if ($addr['latitude'] && $addr['longitude']): ?>
          <div id="map_<?php echo $addr['addr_id']; ?>" class="map"></div>
          <script>
            let mapId = "map_<?php echo $addr['addr_id']; ?>";
            let lat = <?php echo $addr['latitude']; ?>;
            let lon = <?php echo $addr['longitude']; ?>;
            var map = L.map(mapId).setView([lat, lon], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              attribution: '© OpenStreetMap'
            }).addTo(map);
            L.marker([lat, lon]).addTo(map)
             .bindPopup("<?php echo htmlspecialchars($addr['city_name']); ?>");
          </script>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p>Aucune adresse enregistrée.</p>
  <?php endif; ?>
</main>

<footer>
  <p>&copy; 2025 – Ventilomètre – hébergé et propulsé en ligne par <strong>ElsassCloud.fr</strong></p>
</footer>
</body>
</html>