<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$fout   = '';
$lijsten = $pdo->query('SELECT * FROM woordenlijsten ORDER BY naam ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    // ── Spel aanmaken ─────────────────────────────────────────────────────────
    if ($actie === 'aanmaken') {
        $lijst_id   = (int)$_POST['lijst_id'];
        $max_rondes = min(10, max(3, (int)($_POST['max_rondes'] ?? 5)));

        // Genoeg woorden?
        $stmt = $pdo->prepare("SELECT id FROM woorden WHERE woordenlijst_id = ? ORDER BY RAND() LIMIT $max_rondes");
        $stmt->execute([$lijst_id]);
        $woorden = $stmt->fetchAll();

        if (count($woorden) < $max_rondes) {
            $fout = 'Deze woordenlijst heeft te weinig woorden voor ' . $max_rondes . ' rondes.';
        } else {
            $code = strtoupper(substr(md5(uniqid()), 0, 6));

            $pdo->prepare('INSERT INTO multiplayer_games (code, speler1_id, lijst_id, max_rondes) VALUES (?,?,?,?)')
                ->execute([$code, $_SESSION['user_id'], $lijst_id, $max_rondes]);
            $game_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO multiplayer_woorden (game_id, volgorde, woord_id) VALUES (?,?,?)');
            foreach ($woorden as $i => $w) {
                $stmt->execute([$game_id, $i + 1, $w['id']]);
            }

            header('Location: spel.php?game=' . $code);
            exit;
        }

    // ── Meedoen ───────────────────────────────────────────────────────────────
    } elseif ($actie === 'meedoen') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $stmt = $pdo->prepare('SELECT * FROM multiplayer_games WHERE code = ?');
        $stmt->execute([$code]);
        $game = $stmt->fetch();

        if (!$game) {
            $fout = 'Spelcode niet gevonden.';
        } elseif ($game['status'] !== 'wachten') {
            $fout = 'Dit spel is al gestart of afgelopen.';
        } elseif ($game['speler1_id'] == $_SESSION['user_id']) {
            $fout = 'Je kunt niet je eigen spel joinen.';
        } else {
            $pdo->prepare('UPDATE multiplayer_games SET speler2_id=?, status="bezig" WHERE id=?')
                ->execute([$_SESSION['user_id'], $game['id']]);
            header('Location: spel.php?game=' . $code);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multiplayer – Taaltrainer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #F3F4F6; }
        .navbar { background-color: #2563EB; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .btn-primary { background-color: #2563EB; border-color: #2563EB; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3 mb-4">
        <span class="navbar-brand fw-bold">Taaltrainer – Multiplayer</span>
        <a href="../index.php" class="btn btn-sm btn-outline-light">← Terug</a>
    </nav>

    <div class="container pb-5" style="max-width:700px">

        <?php if ($fout): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fout) ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Spel aanmaken -->
            <div class="col-md-6">
                <div class="card p-4 h-100">
                    <h5 class="fw-bold mb-3">Spel aanmaken</h5>
                    <form method="POST">
                        <input type="hidden" name="actie" value="aanmaken">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Woordenlijst</label>
                            <select name="lijst_id" class="form-select" required>
                                <?php foreach ($lijsten as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['naam']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Aantal rondes</label>
                            <select name="max_rondes" class="form-select">
                                <option value="5">5 rondes</option>
                                <option value="7">7 rondes</option>
                                <option value="10">10 rondes</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Spel aanmaken</button>
                    </form>
                </div>
            </div>

            <!-- Meedoen -->
            <div class="col-md-6">
                <div class="card p-4 h-100">
                    <h5 class="fw-bold mb-3">Meedoen</h5>
                    <p class="text-muted small">Vraag de spelcode op bij je tegenstander.</p>
                    <form method="POST">
                        <input type="hidden" name="actie" value="meedoen">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Spelcode</label>
                            <input type="text" name="code" class="form-control text-uppercase fw-bold fs-5 text-center"
                                   maxlength="6" placeholder="ABC123" autocomplete="off" required
                                   style="letter-spacing:0.3em">
                        </div>
                        <button type="submit" class="btn btn-success w-100">Meedoen</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
