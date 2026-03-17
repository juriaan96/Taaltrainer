<?php
require_once 'auth.php';
require_once '../config.php';

$resultaten = $pdo->query('
    SELECT r.*, u.username, w.naam AS lijst_naam
    FROM resultaten r
    JOIN users u ON r.user_id = u.id
    JOIN woordenlijsten w ON r.woordenlijst_id = w.id
    ORDER BY r.created_at DESC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scores – Taaltrainer</title>
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
        <span class="navbar-brand fw-bold">Scores – Taaltrainer</span>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-sm btn-outline-light">← Beheer</a>
            <a href="../logout.php" class="btn btn-sm btn-outline-light">Uitloggen</a>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="card p-4">
            <h5 class="fw-bold mb-3">Alle scores</h5>
            <?php if (empty($resultaten)): ?>
                <p class="text-muted">Nog geen resultaten.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Leerling</th>
                                <th>Woordenlijst</th>
                                <th class="text-center">Score</th>
                                <th class="text-center">%</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultaten as $r): ?>
                                <?php $pct = round(($r['score'] / $r['totaal']) * 100); ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($r['username']) ?></td>
                                    <td><?= htmlspecialchars($r['lijst_naam']) ?></td>
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
</body>
</html>
