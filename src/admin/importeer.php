<?php
require_once 'auth.php';
require_once '../config.php';

$fout   = '';
$succes = '';
$preview = [];

// Bestaande lijsten ophalen voor dropdown
$lijsten = $pdo->query('SELECT * FROM woordenlijsten ORDER BY naam ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    // Stap 1: bestand uploaden + preview tonen
    if ($actie === 'preview') {
        if (empty($_FILES['csv_bestand']['tmp_name'])) {
            $fout = 'Selecteer een CSV-bestand.';
        } else {
            $pad       = $_FILES['csv_bestand']['tmp_name'];
            $inhoud    = file_get_contents($pad);
            $separator = strpos($inhoud, ';') !== false ? ';' : ',';
            $regels    = array_filter(explode("\n", str_replace("\r", "", $inhoud)));
            $skip      = !empty($_POST['heeft_header']);
            $rijen      = [];

            foreach ($regels as $i => $regel) {
                if ($skip && $i === array_key_first($regels)) continue;
                $kolommen = str_getcsv($regel, $separator);
                if (count($kolommen) >= 2 && trim($kolommen[0]) && trim($kolommen[1])) {
                    $rijen[] = [trim($kolommen[0]), trim($kolommen[1])];
                }
            }

            if (empty($rijen)) {
                $fout = 'Geen geldige woorden gevonden. Verwacht formaat: woord,vertaling (één per regel).';
            } else {
                $_SESSION['import_data'] = $rijen;
                $preview = $rijen;
            }
        }

    // Stap 2: definitief importeren
    } elseif ($actie === 'importeren') {
        $rijen       = $_SESSION['import_data'] ?? [];
        $lijst_keuze = $_POST['lijst_keuze'] ?? 'nieuw';

        if ($lijst_keuze === 'nieuw') {
            $naam      = trim($_POST['nieuwe_naam'] ?? '');
            $taal_van  = trim($_POST['taal_van'] ?? '');
            $taal_naar = trim($_POST['taal_naar'] ?? '');

            if (!$naam || !$taal_van || !$taal_naar) {
                $fout = 'Vul de naam en talen in voor de nieuwe woordenlijst.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO woordenlijsten (naam, taal_van, taal_naar, created_by) VALUES (?, ?, ?, ?)');
                $stmt->execute([$naam, $taal_van, $taal_naar, $_SESSION['user_id']]);
                $lijst_id = $pdo->lastInsertId();
            }
        } else {
            $lijst_id = (int)$lijst_keuze;
        }

        if (!$fout && !empty($rijen) && $lijst_id) {
            $stmt = $pdo->prepare('INSERT INTO woorden (woordenlijst_id, woord, vertaling) VALUES (?, ?, ?)');
            foreach ($rijen as $rij) {
                $stmt->execute([$lijst_id, $rij[0], $rij[1]]);
            }
            unset($_SESSION['import_data']);
            $succes = count($rijen) . ' woorden succesvol geïmporteerd.';
        }
    }
} elseif (isset($_SESSION['import_data'])) {
    $preview = $_SESSION['import_data'];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importeren – Taaltrainer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #F3F4F6; }
        .navbar { background-color: #2563EB; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3 mb-4">
        <span class="navbar-brand fw-bold">Importeren – Taaltrainer</span>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-sm btn-outline-light">← Beheer</a>
            <a href="../logout.php" class="btn btn-sm btn-outline-light">Uitloggen</a>
        </div>
    </nav>

    <div class="container pb-5" style="max-width:700px">

        <?php if ($succes): ?>
            <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
        <?php endif; ?>
        <?php if ($fout): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fout) ?></div>
        <?php endif; ?>

        <!-- Stap 1: Bestand uploaden -->
        <?php if (empty($preview)): ?>
        <div class="card p-4 mb-4">
            <h5 class="fw-bold mb-1">CSV-bestand uploaden</h5>
            <p class="text-muted small mb-3">
                Verwacht formaat: twee kolommen, <code>woord</code> en <code>vertaling</code>, komma of puntkomma als scheidingsteken.
                <br>Excel-gebruikers: sla je bestand op als <strong>CSV</strong> via Bestand → Opslaan als.
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="actie" value="preview">
                <div class="mb-3">
                    <label class="form-label fw-semibold">CSV-bestand</label>
                    <input type="file" name="csv_bestand" class="form-control" accept=".csv,.txt" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="heeft_header" id="heeft_header">
                    <label class="form-check-label" for="heeft_header">Eerste rij is een koptekst (overslaan)</label>
                </div>
                <button type="submit" class="btn btn-primary" style="background:#2563EB;border-color:#2563EB">Bestand laden →</button>
            </form>
        </div>

        <!-- Stap 2: Preview + doellijst kiezen -->
        <?php else: ?>
        <div class="card p-4 mb-4">
            <h5 class="fw-bold mb-1">Preview <span class="badge bg-secondary"><?= count($preview) ?> woorden</span></h5>
            <p class="text-muted small mb-3">Controleer de eerste woorden en kies de doellijst.</p>

            <table class="table table-sm table-bordered mb-4">
                <thead class="table-light">
                    <tr><th>Woord</th><th>Vertaling</th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($preview, 0, 5) as $rij): ?>
                        <tr>
                            <td><?= htmlspecialchars($rij[0]) ?></td>
                            <td><?= htmlspecialchars($rij[1]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($preview) > 5): ?>
                        <tr><td colspan="2" class="text-muted text-center small">... en nog <?= count($preview) - 5 ?> woorden</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <form method="POST">
                <input type="hidden" name="actie" value="importeren">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Importeren naar</label>
                    <select name="lijst_keuze" class="form-select" id="lijst_keuze" onchange="toggleNieuw(this.value)">
                        <option value="nieuw">— Nieuwe woordenlijst aanmaken —</option>
                        <?php foreach ($lijsten as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['naam']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="nieuw_blok">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Naam van de nieuwe lijst</label>
                        <input type="text" name="nieuwe_naam" class="form-control" placeholder="Dieren (NL → EN)">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label small fw-semibold">Taal van</label>
                            <input type="text" name="taal_van" class="form-control" placeholder="Nederlands">
                        </div>
                        <div class="col">
                            <label class="form-label small fw-semibold">Taal naar</label>
                            <input type="text" name="taal_naar" class="form-control" placeholder="Engels">
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">Importeren</button>
                    <a href="importeer.php" class="btn btn-outline-secondary">Annuleren</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </div>

    <script>
        function toggleNieuw(waarde) {
            document.getElementById('nieuw_blok').style.display = waarde === 'nieuw' ? 'block' : 'none';
        }
    </script>
</body>
</html>
