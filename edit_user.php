<?php
// edit_user.php
session_start();
require_once 'config.php';

if (!isset($_GET['user_id'])) {
    die("user_id manquant");
}
$userId = $_GET['user_id'];

// Récup user
$sql = "SELECT * FROM users WHERE id=:id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id'=>$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}

// Traitement form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_account'])) {
        // Supprimer user => addresses (cascade)
        $sqlDel = "DELETE FROM users WHERE id=:id";
        $stmtDel = $pdo->prepare($sqlDel);
        $stmtDel->execute([':id'=>$userId]);
        header("Location: index.php?msg=CompteSupprime");
        exit;
    } else {
        // Mise à jour
        $fname = $_POST['first_name'];
        $lname = $_POST['last_name'];
        $grp   = $_POST['user_group'];

        $sqlU = "UPDATE users
                 SET first_name=:fn, last_name=:ln, user_group=:grp
                 WHERE id=:id";
        $stmtU = $pdo->prepare($sqlU);
        $stmtU->execute([
            ':fn'=>$fname,
            ':ln'=>$lname,
            ':grp'=>$grp,
            ':id'=>$userId
        ]);
        header("Location: user.php?id=".$userId);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Modifier Profil</title>
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
      <a href="user.php?id=<?php echo $userId; ?>" class="btn">Retour Profil</a>
    </div>
  </nav>
</header>

<main>
  <h1>Modifier le profil de <?php echo htmlspecialchars($user['first_name']." ".$user['last_name']); ?></h1>

  <form method="POST" class="user-edit-form">
    <label>Prénom</label>
    <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>

    <label>Nom</label>
    <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>

    <label>Groupe</label>
    <select name="user_group">
      <option value="ALT1-GRP1" <?php if($user['user_group']=='ALT1-GRP1') echo 'selected';?>>ALT1-GRP1</option>
      <option value="ALT1-GRP2" <?php if($user['user_group']=='ALT1-GRP2') echo 'selected';?>>ALT1-GRP2</option>
      <option value="ALT1-GRP3" <?php if($user['user_group']=='ALT1-GRP3') echo 'selected';?>>ALT1-GRP3</option>
      <option value="ALT1-GRP4" <?php if($user['user_group']=='ALT1-GRP4') echo 'selected';?>>ALT1-GRP4</option>
    </select>

    <button type="submit">Enregistrer</button>
  </form>

  <h2>Supprimer le compte</h2>
  <form method="POST" onsubmit="return confirm('Supprimer définitivement ce compte ?');">
    <button type="submit" name="delete_account" style="background-color:red;">Supprimer mon compte</button>
  </form>
</main>

<footer>
  <p>&copy; 2025 – Ventilomètre – hébergé et propulsé en ligne par <strong>ElsassCloud.fr</strong></p>
</footer>
</body>
</html>