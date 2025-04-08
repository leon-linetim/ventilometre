<?php
// index.php
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Accueil – Ventilomètre</title>
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
      <a href="signup.php" class="btn">S'inscrire</a>
      <a href="import_export.php" class="btn">Import/Export</a>
    </div>
  </nav>
</header>

<main>
  <h1>Rechercher un utilisateur</h1>

  <form method="GET" action="index.php" class="search-form">
    <label>Nom</label>
    <input type="text" name="nom" placeholder="Nom">
    <label>Prénom</label>
    <input type="text" name="prenom" placeholder="Prénom">
    <label>Groupe</label>
    <select name="group">
      <option value="">Tous les groupes</option>
      <option value="ALT1-GRP1">ALT1-GRP1</option>
      <option value="ALT1-GRP2">ALT1-GRP2</option>
      <option value="ALT1-GRP3">ALT1-GRP3</option>
      <option value="ALT1-GRP4">ALT1-GRP4</option>
    </select>
    <label>ID utilisateur (UUID)</label>
    <input type="text" name="id_user">

    <button type="submit">Rechercher</button>
  </form>

  <?php
  // Traitement de la recherche
  if (!empty($_GET)) {
      $conditions = [];
      $params = [];

      if (!empty($_GET['nom'])) {
          $conditions[] = "last_name LIKE :lname";
          $params[':lname'] = "%".$_GET['nom']."%";
      }
      if (!empty($_GET['prenom'])) {
          $conditions[] = "first_name LIKE :fname";
          $params[':fname'] = "%".$_GET['prenom']."%";
      }
      if (!empty($_GET['id_user'])) {
          $conditions[] = "user_uuid = :uuid";
          $params[':uuid'] = $_GET['id_user'];
      }
      if (!empty($_GET['group'])) {
          $conditions[] = "user_group = :grp";
          $params[':grp'] = $_GET['group'];
      }

      $sql = "SELECT * FROM users";
      if (count($conditions) > 0) {
          $sql .= " WHERE ".implode(" AND ", $conditions);
      }

      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if ($results) {
          echo "<h2>Résultats</h2><ul>";
          foreach ($results as $r) {
              echo "<li><a href='user.php?id={$r['id']}'>
                      {$r['first_name']} {$r['last_name']} (UUID: {$r['user_uuid']}, G: {$r['user_group']})
                    </a></li>";
          }
          echo "</ul>";
      } else {
          echo "<p>Aucun résultat.</p>";
      }
  }
  ?>
</main>

<footer>
  <p>&copy; 2025 – Ventilomètre – hébergé et propulsé en ligne par <strong>ElsassCloud.fr</strong></p>
</footer>

</body>
</html>