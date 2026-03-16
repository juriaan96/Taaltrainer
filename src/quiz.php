<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Quiz initialiseren bij eerste bezoek
if (isset($_GET['lijst'])) {
    $lijst_id = (int)$_GET['lijst'];

    $stmt = $pdo->prepare('SELECT * FROM woorden WHERE woordenlijst_id = ?');
    $stmt->execute([$lijst_id]);
    $woorden = $stmt->fetchAll();

    if (empty($woorden)) {
        header('Location: index.php');
        exit;
    }

    $stmt2 = $pdo->prepare('SELECT * FROM woordenlijsten WHERE id = ?');
    $stmt2->execute([$lijst_id]);
    $lijst = $stmt2->fetch();

    shuffle($woorden);

    $_SESSION['quiz'] = [
        'lijst_id'   => $lijst_id,
        'lijst_naam' => $lijst['naam'],
        'woorden'    => $woorden,
        'index'      => 0,
        'score'      => 0,
        'fouten'     => [],
        'fase'       => 'vraag',
        'feedback'   => null,
    ];
}

if (!isset($_SESSION['quiz'])) {
    header('Location: index.php');
    exit;
}

$quiz  = &$_SESSION['quiz'];
$totaal = count($quiz['woorden']);

// POST verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Antwoord controleren
    if (isset($_POST['antwoord']) && $quiz['fase'] === 'vraag') {
        $huidig  = $quiz['woorden'][$quiz['index']];
        $antwoord = trim($_POST['antwoord']);
        $correct  = strtolower($antwoord) === strtolower($huidig['vertaling']);

        if ($correct) {
            $quiz['score']++;
            $quiz['feedback'] = 'correct';
        } else {
            $quiz['fouten'][] = [
                'woord'   => $huidig['woord'],
                'gegeven' => $antwoord,
                'correct' => $huidig['vertaling'],
            ];
            $quiz['feedback'] = 'fout';
        }
        $quiz['fase'] = 'feedback';

    // Volgende woord
    } elseif (isset($_POST['volgende']) && $quiz['fase'] === 'feedback') {
        $quiz['index']++;
        $quiz['fase']     = 'vraag';
        $quiz['feedback'] = null;

        if ($quiz['index'] >= $totaal) {
            $stmt = $pdo->prepare('INSERT INTO resultaten (user_id, woordenlijst_id, score, totaal) VALUES (?, ?, ?, ?)');
            $stmt->execute([$_SESSION['user_id'], $quiz['lijst_id'], $quiz['score'], $totaal]);

            $_SESSION['resultaat'] = [
                'score'      => $quiz['score'],
                'totaal'     => $totaal,
                'fouten'     => $quiz['fouten'],
                'lijst_naam' => $quiz['lijst_naam'],
            ];
            unset($_SESSION['quiz']);

            header('Location: resultaat.php');
            exit;
        }
    }
}

$huidig   = $quiz['woorden'][$quiz['index']];
$vraag_nr = $quiz['index'] + 1;
$voortgang = round(($quiz['index'] / $totaal) * 100);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz – <?= htmlspecialchars($quiz['lijst_naam']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #F3F4F6; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background-color: #2563EB; }
        .quiz-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .quiz-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 100%; max-width: 480px; padding: 2.5rem; }
        .woord-display { font-size: 2.5rem; font-weight: 700; color: #1F2937; text-align: center; margin: 1.5rem 0; }
        .progress { height: 8px; border-radius: 4px; }
        .progress-bar { background-color: #2563EB; }
        .btn-check-primary { background-color: #2563EB; border-color: #2563EB; color: white; }
        .btn-check-primary:hover { background-color: #1D4ED8; }
        .feedback-correct { background-color: #F0FDF4; border: 2px solid #16A34A; border-radius: 10px; padding: 1rem; }
        .feedback-fout    { background-color: #FEF2F2; border: 2px solid #DC2626; border-radius: 10px; padding: 1rem; }
        .btn-correct { background-color: #16A34A; border-color: #16A34A; color: white; }
        .btn-correct:hover { background-color: #15803D; }
        .btn-fout { background-color: #DC2626; border-color: #DC2626; color: white; }
        .btn-fout:hover { background-color: #B91C1C; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3">
        <span class="navbar-brand fw-bold"><?= htmlspecialchars($quiz['lijst_naam']) ?></span>
        <a href="index.php" class="btn btn-sm btn-outline-light">Stoppen</a>
    </nav>

    <div class="quiz-wrap">
        <div class="quiz-card">

            <!-- Voortgang -->
            <div class="d-flex justify-content-between text-muted small mb-1">
                <span>Vraag <?= $vraag_nr ?> van <?= $totaal ?></span>
                <span>Score: <?= $quiz['score'] ?></span>
            </div>
            <div class="progress mb-4">
                <div class="progress-bar" style="width: <?= $voortgang ?>%"></div>
            </div>

            <!-- Woord -->
            <div class="woord-display"><?= htmlspecialchars($huidig['woord']) ?></div>

            <?php if ($quiz['fase'] === 'vraag'): ?>

                <!-- Invoerformulier -->
                <form method="POST" action="" id="quizForm">
                    <div class="mb-3">
                        <input
                            type="text"
                            class="form-control form-control-lg text-center"
                            name="antwoord"
                            placeholder="Vertaal hier..."
                            autocomplete="off"
                            autofocus
                            required
                        >
                    </div>
                    <button type="submit" class="btn btn-check-primary btn-lg w-100">Controleer</button>
                </form>

            <?php else: ?>

                <!-- Feedback -->
                <div class="<?= $quiz['feedback'] === 'correct' ? 'feedback-correct' : 'feedback-fout' ?> text-center mb-4">
                    <?php if ($quiz['feedback'] === 'correct'): ?>
                        <div class="fw-bold text-success fs-5">Goed!</div>
                        <div class="text-muted small mt-1"><?= htmlspecialchars($huidig['vertaling']) ?></div>
                    <?php else: ?>
                        <div class="fw-bold text-danger fs-5">Fout</div>
                        <div class="text-muted small mt-1">Juiste antwoord: <strong><?= htmlspecialchars($huidig['vertaling']) ?></strong></div>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <button
                        type="submit"
                        name="volgende"
                        value="1"
                        class="btn <?= $quiz['feedback'] === 'correct' ? 'btn-correct' : 'btn-fout' ?> btn-lg w-100"
                        autofocus
                    >
                        <?= $quiz['index'] + 1 >= $totaal ? 'Bekijk resultaat' : 'Volgende →' ?>
                    </button>
                </form>

            <?php endif; ?>

        </div>
    </div>
</body>
</html>
