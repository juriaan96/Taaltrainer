<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$resultaten = $pdo->prepare('
    SELECT r.*, w.naam AS lijst_naam
    FROM resultaten r
    JOIN woordenlijsten w ON r.woordenlijst_id = w.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
');
$resultaten->execute([$_SESSION['user_id']]);
$resultaten = $resultaten->fetchAll();

// Moeilijke woorden voor deze gebruiker
$moeilijk = $pdo->prepare('
    SELECT ws.fout, ws.correct, wo.woord, wo.vertaling, wl.naam AS lijst_naam
    FROM woord_statistieken ws
    JOIN woorden wo ON ws.woord_id = wo.id
    JOIN woordenlijsten wl ON wo.woordenlijst_id = wl.id
    WHERE ws.user_id = ? AND ws.fout > 0
    ORDER BY (ws.fout - ws.correct) DESC
    LIMIT 10
');
$moeilijk->execute([$_SESSION['user_id']]);
$moeilijk = $moeilijk->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mijn scores – Taaltrainer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>:root { --bs-font-sans-serif: 'Inter', system-ui, sans-serif; }</style>
    <style>
        body { background-color: #F3F4F6; }
        .navbar { background-color: #2563EB; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3 mb-4">
        <span class="navbar-brand fw-bold">Taaltrainer</span>
        <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="btn btn-sm btn-outline-light">← Woordenlijsten</a>
            <a href="logout.php" class="btn btn-sm btn-outline-light">Uitloggen</a>
        </div>
    </nav>

    <div class="container pb-5">
        <h1 class="fs-4 fw-bold mb-4">Mijn scores</h1>

        <div class="row g-4">

            <!-- Scoregeschiedenis -->
            <div class="col-lg-7">
                <div class="card p-4">
                    <h5 class="fw-bold mb-3">Geschiedenis</h5>
                    <?php if (empty($resultaten)): ?>
                        <p class="text-muted">Nog geen oefeningen gedaan.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Woordenlijst</th>
                                        <th class="text-center">Score</th>
                                        <th class="text-center">%</th>
                                        <th>Datum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resultaten as $r): ?>
                                        <?php $pct = $r['totaal'] > 0 ? round(($r['score'] / $r['totaal']) * 100) : 0; ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($r['lijst_naam']) ?></td>
                                            <td class="text-center"><?= $r['score'] ?>/<?= $r['totaal'] ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                                    <?= $pct ?>%
                                                </span>
                                            </td>
                                            <td class="text-muted small"><?= date('d-m-Y H:i', strtotime($r['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Moeilijke woorden -->
            <div class="col-lg-5">
                <div class="card p-4">
                    <h5 class="fw-bold mb-3">Moeilijke woorden</h5>
                    <?php if (empty($moeilijk)): ?>
                        <p class="text-muted">Nog geen fouten gemaakt.</p>
                    <?php else: ?>
                        <?php foreach ($moeilijk as $w): ?>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <span class="fw-semibold"><?= htmlspecialchars($w['woord']) ?></span>
                                    <span class="text-muted mx-1">→</span>
                                    <span class="text-success"><?= htmlspecialchars($w['vertaling']) ?></span>
                                    <div class="text-muted small"><?= htmlspecialchars($w['lijst_naam']) ?></div>
                                </div>
                                <div class="text-end small">
                                    <span class="text-danger">✗ <?= $w['fout'] ?></span>
                                    <span class="text-muted mx-1">·</span>
                                    <span class="text-success">✓ <?= $w['correct'] ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
