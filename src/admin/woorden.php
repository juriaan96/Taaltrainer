<?php
require_once 'auth.php';
require_once '../config.php';

$lijst_id = (int)($_GET['lijst'] ?? $_POST['lijst_id'] ?? 0);

if (!$lijst_id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM woordenlijsten WHERE id = ?');
$stmt->execute([$lijst_id]);
$lijst = $stmt->fetch();

if (!$lijst) {
    header('Location: index.php');
    exit;
}

$fout   = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'toevoegen') {
        $woord    = trim($_POST['woord'] ?? '');
        $vertaling = trim($_POST['vertaling'] ?? '');

        if ($woord && $vertaling) {
            $stmt = $pdo->prepare('INSERT INTO woorden (woordenlijst_id, woord, vertaling) VALUES (?, ?, ?)');
            $stmt->execute([$lijst_id, $woord, $vertaling]);
            $succes = 'Woord toegevoegd.';
        } else {
            $fout = 'Vul beide velden in.';
        }

    } elseif ($actie === 'bewerken') {
        $id        = (int)$_POST['woord_id'];
        $woord     = trim($_POST['woord'] ?? '');
        $vertaling = trim($_POST['vertaling'] ?? '');

        if ($id && $woord && $vertaling) {
            $stmt = $pdo->prepare('UPDATE woorden SET woord=?, vertaling=? WHERE id=? AND woordenlijst_id=?');
            $stmt->execute([$woord, $vertaling, $id, $lijst_id]);
            $succes = 'Woord bijgewerkt.';
        } else {
            $fout = 'Vul beide velden in.';
        }

    } elseif ($actie === 'verwijderen') {
        $id = (int)$_POST['woord_id'];
        if ($id) {
            $pdo->prepare('DELETE FROM woorden WHERE id=? AND woordenlijst_id=?')->execute([$id, $lijst_id]);
            $succes = 'Woord verwijderd.';
        }
    }
}

$woorden = $pdo->prepare('SELECT * FROM woorden WHERE woordenlijst_id = ? ORDER BY woord ASC');
$woorden->execute([$lijst_id]);
$woorden = $woorden->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Woorden – <?= htmlspecialchars($lijst['naam']) ?></title>
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
        <span class="navbar-brand fw-bold"><?= htmlspecialchars($lijst['naam']) ?></span>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-sm btn-outline-light">← Woordenlijsten</a>
            <a href="../logout.php" class="btn btn-sm btn-outline-light">Uitloggen</a>
        </div>
    </nav>

    <div class="container pb-5">

        <?php if ($succes): ?>
            <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
        <?php endif; ?>
        <?php if ($fout): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fout) ?></div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Nieuw woord toevoegen -->
            <div class="col-lg-4">
                <div class="card p-4">
                    <h5 class="fw-bold mb-3">Woord toevoegen</h5>
                    <form method="POST">
                        <input type="hidden" name="actie" value="toevoegen">
                        <input type="hidden" name="lijst_id" value="<?= $lijst_id ?>">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold"><?= htmlspecialchars($lijst['taal_van']) ?></label>
                            <input type="text" name="woord" class="form-control" placeholder="bijv. hond" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold"><?= htmlspecialchars($lijst['taal_naar']) ?></label>
                            <input type="text" name="vertaling" class="form-control" placeholder="bijv. dog" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="background:#2563EB;border-color:#2563EB">Toevoegen</button>
                    </form>
                </div>
            </div>

            <!-- Woordenlijst -->
            <div class="col-lg-8">
                <div class="card p-4">
                    <h5 class="fw-bold mb-1">Woorden <span class="badge bg-secondary"><?= count($woorden) ?></span></h5>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($lijst['taal_van']) ?> → <?= htmlspecialchars($lijst['taal_naar']) ?></p>

                    <?php if (empty($woorden)): ?>
                        <p class="text-muted">Nog geen woorden. Voeg er een toe via het formulier.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= htmlspecialchars($lijst['taal_van']) ?></th>
                                        <th><?= htmlspecialchars($lijst['taal_naar']) ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($woorden as $woord): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($woord['woord']) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($woord['vertaling']) ?></td>
                                            <td class="text-end">
                                                <div class="d-flex gap-1 justify-content-end">
                                                    <button class="btn btn-sm btn-outline-secondary"
                                                        onclick="openBewerken(<?= $woord['id'] ?>, '<?= htmlspecialchars(addslashes($woord['woord'])) ?>', '<?= htmlspecialchars(addslashes($woord['vertaling'])) ?>')">
                                                        Bewerken
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Woord verwijderen?')">
                                                        <input type="hidden" name="actie" value="verwijderen">
                                                        <input type="hidden" name="lijst_id" value="<?= $lijst_id ?>">
                                                        <input type="hidden" name="woord_id" value="<?= $woord['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Verwijderen</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bewerken modal -->
    <div class="modal fade" id="bewerkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="actie" value="bewerken">
                    <input type="hidden" name="lijst_id" value="<?= $lijst_id ?>">
                    <input type="hidden" name="woord_id" id="bewerk_id">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Woord bewerken</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold"><?= htmlspecialchars($lijst['taal_van']) ?></label>
                            <input type="text" name="woord" id="bewerk_woord" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold"><?= htmlspecialchars($lijst['taal_naar']) ?></label>
                            <input type="text" name="vertaling" id="bewerk_vertaling" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary" style="background:#2563EB;border-color:#2563EB">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openBewerken(id, woord, vertaling) {
            document.getElementById('bewerk_id').value        = id;
            document.getElementById('bewerk_woord').value     = woord;
            document.getElementById('bewerk_vertaling').value = vertaling;
            new bootstrap.Modal(document.getElementById('bewerkModal')).show();
        }
    </script>
</body>
</html>
