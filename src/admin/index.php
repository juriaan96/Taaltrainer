<?php
require_once 'auth.php';
require_once '../config.php';

$fout    = '';
$succes  = '';

// POST verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'toevoegen') {
        $naam     = trim($_POST['naam'] ?? '');
        $taal_van = trim($_POST['taal_van'] ?? '');
        $taal_naar = trim($_POST['taal_naar'] ?? '');

        if ($naam && $taal_van && $taal_naar) {
            $stmt = $pdo->prepare('INSERT INTO woordenlijsten (naam, taal_van, taal_naar, created_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$naam, $taal_van, $taal_naar, $_SESSION['user_id']]);
            $succes = 'Woordenlijst aangemaakt.';
        } else {
            $fout = 'Vul alle velden in.';
        }

    } elseif ($actie === 'bewerken') {
        $id        = (int)$_POST['id'];
        $naam      = trim($_POST['naam'] ?? '');
        $taal_van  = trim($_POST['taal_van'] ?? '');
        $taal_naar = trim($_POST['taal_naar'] ?? '');

        if ($id && $naam && $taal_van && $taal_naar) {
            $stmt = $pdo->prepare('UPDATE woordenlijsten SET naam=?, taal_van=?, taal_naar=? WHERE id=?');
            $stmt->execute([$naam, $taal_van, $taal_naar, $id]);
            $succes = 'Woordenlijst bijgewerkt.';
        } else {
            $fout = 'Vul alle velden in.';
        }

    } elseif ($actie === 'verwijderen') {
        $id = (int)$_POST['id'];
        if ($id) {
            $pdo->prepare('DELETE FROM woordenlijsten WHERE id=?')->execute([$id]);
            $succes = 'Woordenlijst verwijderd.';
        }
    }
}

// Woordenlijsten ophalen
$lijsten = $pdo->query('SELECT w.*, COUNT(wo.id) AS aantal_woorden
                        FROM woordenlijsten w
                        LEFT JOIN woorden wo ON wo.woordenlijst_id = w.id
                        GROUP BY w.id
                        ORDER BY w.naam ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beheerpaneel – Taaltrainer</title>
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
        <span class="navbar-brand fw-bold">Taaltrainer – Beheer</span>
        <div class="d-flex gap-2">
            <a href="../index.php" class="btn btn-sm btn-outline-light">← App</a>
            <a href="scores.php" class="btn btn-sm btn-outline-light">Scores</a>
            <a href="gebruikers.php" class="btn btn-sm btn-outline-light">Gebruikers</a>
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

            <!-- Nieuwe woordenlijst -->
            <div class="col-lg-4">
                <div class="card p-4">
                    <h5 class="fw-bold mb-3">Nieuwe woordenlijst</h5>
                    <form method="POST">
                        <input type="hidden" name="actie" value="toevoegen">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Naam</label>
                            <input type="text" name="naam" class="form-control" placeholder="Dieren (NL → EN)" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Taal van</label>
                            <input type="text" name="taal_van" class="form-control" placeholder="Nederlands" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Taal naar</label>
                            <input type="text" name="taal_naar" class="form-control" placeholder="Engels" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="background:#2563EB;border-color:#2563EB">Aanmaken</button>
                    </form>
                </div>
            </div>

            <!-- Overzicht woordenlijsten -->
            <div class="col-lg-8">
                <div class="card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">Woordenlijsten</h5>
                        <a href="importeer.php" class="btn btn-sm btn-outline-primary">CSV importeren</a>
                    </div>

                    <?php if (empty($lijsten)): ?>
                        <p class="text-muted">Nog geen woordenlijsten.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Naam</th>
                                        <th>Talen</th>
                                        <th class="text-center">Woorden</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lijsten as $lijst): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($lijst['naam']) ?></td>
                                            <td class="text-muted small"><?= htmlspecialchars($lijst['taal_van']) ?> → <?= htmlspecialchars($lijst['taal_naar']) ?></td>
                                            <td class="text-center"><?= $lijst['aantal_woorden'] ?></td>
                                            <td class="text-end">
                                                <div class="d-flex gap-1 justify-content-end">
                                                    <a href="woorden.php?lijst=<?= $lijst['id'] ?>" class="btn btn-sm btn-outline-primary">Woorden</a>
                                                    <button class="btn btn-sm btn-outline-secondary"
                                                        onclick="openBewerken(<?= $lijst['id'] ?>, '<?= htmlspecialchars(addslashes($lijst['naam'])) ?>', '<?= htmlspecialchars(addslashes($lijst['taal_van'])) ?>', '<?= htmlspecialchars(addslashes($lijst['taal_naar'])) ?>')">
                                                        Bewerken
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Woordenlijst verwijderen? Alle woorden worden ook verwijderd.')">
                                                        <input type="hidden" name="actie" value="verwijderen">
                                                        <input type="hidden" name="id" value="<?= $lijst['id'] ?>">
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
                    <input type="hidden" name="id" id="bewerk_id">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Woordenlijst bewerken</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Naam</label>
                            <input type="text" name="naam" id="bewerk_naam" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Taal van</label>
                            <input type="text" name="taal_van" id="bewerk_taal_van" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Taal naar</label>
                            <input type="text" name="taal_naar" id="bewerk_taal_naar" class="form-control" required>
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
        function openBewerken(id, naam, taal_van, taal_naar) {
            document.getElementById('bewerk_id').value       = id;
            document.getElementById('bewerk_naam').value     = naam;
            document.getElementById('bewerk_taal_van').value = taal_van;
            document.getElementById('bewerk_taal_naar').value = taal_naar;
            new bootstrap.Modal(document.getElementById('bewerkModal')).show();
        }
    </script>
</body>
</html>
