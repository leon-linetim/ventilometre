<?php
// cron_update.php
require_once 'config.php';

// Récupère toutes les villes
$sql = "SELECT id FROM city";
$cities = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

foreach ($cities as $c) {
    updateWeatherForCity($pdo, $c['id']);
}

echo "CRON: Mise à jour météo pour ".count($cities)." villes.\n";