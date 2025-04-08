<?php
// signup.php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname  = trim($_POST['first_name']);
    $lname  = trim($_POST['last_name']);
    $group  = $_POST['user_group'];
    $cityName   = trim($_POST['city']);
    $postalCode = trim($_POST['postal_code']);

    $uuid   = uniqid('user_');

    // Insérer user
    $sqlU = "INSERT INTO users (user_uuid, first_name, last_name, user_group)
             VALUES (:uuid, :fname, :lname, :grp)";
    $stmtU = $pdo->prepare($sqlU);
    $stmtU->execute([
        ':uuid'=>$uuid,
        ':fname'=>$fname,
        ':lname'=>$lname,
        ':grp'=>$group
    ]);
    $newUserId = $pdo->lastInsertId();

    // City (créée ou trouvée)
    list($lat, $lng) = geocodeCity($cityName);
    $cityId = findOrCreateCity($pdo, $cityName, $postalCode, $lat, $lng);

    // Address
    $sqlA = "INSERT INTO addresses (user_id, city_id, address_label)
             VALUES (:uid, :cid, 'Résidence principale')";
    $stmtA = $pdo->prepare($sqlA);
    $stmtA->execute([
        ':uid'=>$newUserId,
        ':cid'=>$cityId
    ]);

    // On peut aussi mettre à jour la météo de la ville si on veut un 1er relevé
    updateWeatherForCity($pdo, $cityId);

    header("Location: user.php?id=$newUserId");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Inscription</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
  <nav class="navbar">
    <div class="navbar-left">
      <img src="https://cdn.elsasscloud.fr/ventilateur.png" alt="Logo">
      <div class="logo" onclick="window.location.href='index.php'">Ventilomètre</div>
    </div>
    <div class="nav-user"></div>
  </nav>
</header>

<main>
  <h1>Inscription</h1>
  <form method="POST" class="signup-form">
    <label>Prénom</label>
    <input type="text" name="first_name" required>
    <label>Nom</label>
    <input type="text" name="last_name" required>
    <label>Groupe</label>
    <select name="user_group">
      <option value="ALT1-GRP1">ALT1-GRP1</option>
      <option value="ALT1-GRP2">ALT1-GRP2</option>
      <option value="ALT1-GRP3">ALT1-GRP3</option>
      <option value="ALT1-GRP4">ALT1-GRP4</option>
    </select>

    <label>Ville</label>
    <input type="text" name="city" id="city-input" required>
    <label>Code Postal</label>
    <input type="text" name="postal_code" id="postal-input" required>

    <button type="submit">Créer mon compte</button>
  </form>
</main>

<footer>
  <p>&copy; 2025 – Ventilomètre – hébergé et propulsé en ligne par <strong>ElsassCloud.fr</strong></p>
</footer>

<script src="js/script.js"></script>
<script>
  // Si tu veux l'autocomplétion
  initAddressAutocomplete("city-input", "postal-input");
</script>
</body>
</html>