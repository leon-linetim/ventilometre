<?php
// manage_addresses.php
session_start();
require_once 'config.php';

if (!isset($_GET['user_id'])) {
    die("user_id manquant");
}
$userId = $_GET['user_id'];

// Vérifier user
$sqlU = "SELECT * FROM users WHERE id=:id";
$stmtU = $pdo->prepare($sqlU);
$stmtU->execute([':id'=>$userId]);
$user = $stmtU->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die("Utilisateur introuvable.");
}

// Traitement form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $label     = $_POST['label'];
        $cityName  = $_POST['city'];
        $postal    = $_POST['postal_code'];
        list($lat, $lng) = geocodeCity($cityName);

        $cityId = findOrCreateCity($pdo, $cityName, $postal, $lat, $lng);

        $sqlA = "INSERT INTO addresses (user_id, city_id, address_label)
                 VALUES (:uid, :cid, :lbl)";
        $stmtA = $pdo->prepare($sqlA);
        $stmtA->execute([
            ':uid'=>$userId,
            ':cid'=>$cityId,
            ':lbl'=>$label
        ]);

        // Mise à jour météo
        updateWeatherForCity($pdo, $cityId);

    } elseif ($action === 'update' && isset($_POST['addr_id'])) {
        $addrId = $_POST['addr_id'];
        $label  = $_POST['label'];
        $cityName = $_POST['city'];
        $postal   = $_POST['postal_code'];
        list($lat, $lng) = geocodeCity($cityName);

        // Crée / retrouve la ville
        $cityId = findOrCreateCity($pdo, $cityName, $postal, $lat, $lng);

        // Update address (change city_id)
        $sqlUp = "UPDATE addresses
                  SET address_label=:lbl, city_id=:cid
                  WHERE id=:aid AND user_id=:uid";
        $stmtUp = $pdo->prepare($sqlUp);
        $stmtUp->execute([
            ':lbl'=>$label,
            ':cid'=>$cityId,
            ':aid'=>$addrId,
            ':uid'=>$userId
        ]);

        updateWeatherForCity($pdo, $cityId);

    } elseif ($action === 'delete' && isset($_POST['addr_id'])) {
        $addrId = $_POST['addr_id'];
        $sqlDel = "DELETE FROM addresses WHERE id=:aid AND user_id=:uid";
        $stmtDel = $pdo->prepare($sqlDel);
        $stmtDel->execute([
            ':aid'=>$addrId,
            ':uid'=>$userId
        ]);
    }
    header("Location: manage_addresses.php?user_id=$userId");
    exit;
}

// Récup addresses existantes
$sqlA = "SELECT a.id AS addr_id, a.address_label,
                c.name AS city_name, c.postal_code
         FROM addresses a
         JOIN city c ON a.city_id = c.id
         WHERE a.user_id=:uid";
$stmtA = $pdo->prepare($sqlA);
$stmtA->execute([':uid'=>$userId]);
$addresses = $stmtA->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Gérer Adresses</title>
  <link rel="stylesheet" href="css/style.css">
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
      <a href="user.php?id=<?php echo $userId; ?>" class="btn">Retour Profil</a>
    </div>
  </nav>
</header>

<main>
  <h1>Gérer les résidences de <?php echo htmlspecialchars($user['first_name']." ".$user['last_name']); ?></h1>

  <h2>Résidences existantes</h2>
  <?php if ($addresses): ?>
    <ul>
      <?php foreach($addresses as $a): ?>
        <li>
          <form method="POST" class="address-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="addr_id" value="<?php echo $a['addr_id']; ?>">

            <label>Label</label>
            <input type="text" name="label" value="<?php echo htmlspecialchars($a['address_label']); ?>">

            <label>Ville</label>
            <input type="text" name="city" id="city_<?php echo $a['addr_id']; ?>"
                   value="<?php echo htmlspecialchars($a['city_name']); ?>">

            <label>Code Postal</label>
            <input type="text" name="postal_code" id="postal_<?php echo $a['addr_id']; ?>"
                   value="<?php echo htmlspecialchars($a['postal_code']); ?>">

            <button type="submit">Modifier</button>
          </form>

          <form method="POST" style="display:inline-block">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="addr_id" value="<?php echo $a['addr_id']; ?>">
            <button type="submit" onclick="return confirm('Supprimer cette adresse ?');">Supprimer</button>
          </form>

          <script>
            initAddressAutocomplete("city_<?php echo $a['addr_id']; ?>", "postal_<?php echo $a['addr_id']; ?>");
          </script>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>Aucune adresse enregistrée.</p>
  <?php endif; ?>

  <h2>Ajouter une nouvelle résidence</h2>
  <form method="POST" class="address-form">
    <input type="hidden" name="action" value="add">

    <label>Label</label>
    <input type="text" name="label" value="Résidence secondaire">

    <label>Ville</label>
    <input type="text" name="city" id="city_new">

    <label>Code Postal</label>
    <input type="text" name="postal_code" id="postal_new">

    <button type="submit">Ajouter</button>
  </form>

  <script>
    initAddressAutocomplete("city_new", "postal_new");
  </script>
</main>

<footer>
  <p>&copy; 2025 – Ventilomètre – hébergé et propulsé en ligne par <strong>ElsassCloud.fr</strong></p>
</footer>
</body>
</html>