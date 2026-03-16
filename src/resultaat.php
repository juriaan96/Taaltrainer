<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['resultaat'])) {
    header('Location: index.php');
    exit;
}

$resultaat  = $_SESSION['resultaat'];
$score      = $resultaat['score'];
$totaal     = $resultaat['totaal'];
$fouten     = $resultaat['fouten'];
$lijst_naam = $resultaat['lijst_naam'];
$percentage = round(($score / $totaal) * 100);

if ($percentage >= 80) {
    $cijfer_kleur = 'text-success';
    $emoji = '🎉';
    $boodschap = 'Uitstekend gedaan!';
} elseif ($percentage >= 50) {
    $cijfer_kleur = 'text-warning';
    $emoji = '👍';
    $boodschap = 'Goed bezig, blijf oefenen!';
} else {
    $cijfer_kleur = 'text-danger';
    $emoji = '💪';
    $boodschap = 'Nog even doorzetten!';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultaat – Taaltrainer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #F3F4F6; }
        .navbar { background-color: #2563EB; }
        .result-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 540px; margin: 2rem auto; padding: 2.5rem; }
        .score-display { font-size: 4rem; font-weight: 800; line-height: 1; }
        .score-sub { font-size: 1.1rem; color: #6B7280; }
        .fout-rij { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #F3F4F6; font-size: 0.95rem; }
        .fout-rij:last-child { border-bottom: none; }
        .btn-opnieuw { background-color: #16A34A; border-color: #16A34A; color: white; }
        .btn-opnieuw:hover { background-color: #15803D; }
        .btn-andere { background-color: #2563EB; border-color: #2563EB; color: white; }
        .btn-andere:hover { background-color: #1D4ED8; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3">
        <span class="navbar-brand fw-bold">Taaltrainer</span>
        <a href="logout.php" class="btn btn-sm btn-outline-light">Uitloggen</a>
    </nav>

    <div class="container px-3">
        <div class="result-card text-center">

            <div class="mb-1" style="font-size:2.5rem"><?= $emoji ?></div>
            <div class="score-display <?= $cijfer_kleur ?>"><?= $score ?><span style="font-size:2rem; color:#9CA3AF">/<?= $totaal ?></span></div>
            <div class="score-sub mb-1"><?= $percentage ?>% goed</div>
            <div class="fw-semibold mb-4"><?= $boodschap ?></div>

            <!-- Foute / bijna goede woorden -->
            <?php if (!empty($fouten)): ?>
                <div class="text-start mb-4">
                    <div class="fw-semibold text-muted small text-uppercase mb-2">Nog te leren</div>
                    <?php foreach ($fouten as $fout): ?>
                        <div class="fout-rij">
                            <span class="fw-semibold"><?= htmlspecialchars($fout['woord']) ?></span>
                            <span class="text-muted mx-2">→</span>
                            <span class="text-success fw-semibold"><?= htmlspecialchars($fout['correct']) ?></span>
                            <?php if (!empty($fout['bijna'])): ?>
                                <span class="ms-auto small" style="color:#D97706"><?= htmlspecialchars($fout['gegeven']) ?> ≈ +½</span>
                            <?php else: ?>
                                <span class="ms-auto text-danger small"><?= htmlspecialchars($fout['gegeven']) ?> ✗</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-success mb-4">Alle woorden goed! Perfect score!</div>
            <?php endif; ?>

            <!-- Knoppen -->
            <div class="d-grid gap-2">
                <a href="quiz.php?lijst=<?= (int)$resultaat['lijst_id'] ?>" class="btn btn-opnieuw btn-lg">
                    Opnieuw oefenen
                </a>
                <a href="index.php" class="btn btn-andere btn-lg">Andere woordenlijst</a>
            </div>

        </div>
    </div>
</body>
</html>
<?php unset($_SESSION['resultaat']); ?>
