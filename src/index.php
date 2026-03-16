<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->query('SELECT w.*, u.username AS auteur, COUNT(wo.id) AS aantal_woorden
                     FROM woordenlijsten w
                     JOIN users u ON w.created_by = u.id
                     LEFT JOIN woorden wo ON wo.woordenlijst_id = w.id
                     GROUP BY w.id
                     ORDER BY w.naam ASC');
$woordenlijsten = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kies een woordenlijst – Taaltrainer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #F3F4F6; }
        .navbar { background-color: #2563EB; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); transition: transform 0.15s; }
        .card:hover { transform: translateY(-3px); }
        .badge-taal { background-color: #EFF6FF; color: #2563EB; font-weight: 500; }
        .btn-start { background-color: #2563EB; border-color: #2563EB; }
        .btn-start:hover { background-color: #1D4ED8; border-color: #1D4ED8; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3 mb-4">
        <span class="navbar-brand fw-bold fs-5">Taaltrainer</span>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white-50 small">Ingelogd als <strong class="text-white"><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
            <?php if ($_SESSION['role'] === 'docent'): ?>
                <a href="admin/index.php" class="btn btn-sm btn-outline-light">Beheer</a>
            <?php endif; ?>
            <a href="scores.php" class="btn btn-sm btn-outline-light">Mijn scores</a>
            <a href="logout.php" class="btn btn-sm btn-outline-light">Uitloggen</a>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="fs-4 fw-bold text-dark mb-0">Kies een woordenlijst</h1>
                <p class="text-muted mb-0 small">Selecteer een lijst om mee te oefenen.</p>
            </div>
            <a href="multiplayer/lobby.php" class="btn btn-outline-primary">
                ⚔️ Multiplayer
            </a>
        </div>

        <?php if (empty($woordenlijsten)): ?>
            <div class="alert alert-info">Er zijn nog geen woordenlijsten beschikbaar.</div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($woordenlijsten as $lijst): ?>
                    <div class="col">
                        <div class="card h-100 p-3">
                            <div class="card-body">
                                <h5 class="card-title fw-bold"><?= htmlspecialchars($lijst['naam']) ?></h5>
                                <p class="mb-2">
                                    <span class="badge badge-taal"><?= htmlspecialchars($lijst['taal_van']) ?></span>
                                    <span class="mx-1 text-muted">→</span>
                                    <span class="badge badge-taal"><?= htmlspecialchars($lijst['taal_naar']) ?></span>
                                </p>
                                <p class="text-muted small mb-0"><?= $lijst['aantal_woorden'] ?> woorden &nbsp;·&nbsp; door <?= htmlspecialchars($lijst['auteur']) ?></p>
                            </div>
                            <div class="card-footer bg-white border-0 pt-0">
                                <a href="quiz.php?lijst=<?= $lijst['id'] ?>" class="btn btn-start btn-sm text-white w-100">Starten</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
