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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>:root { --bs-font-sans-serif: 'Inter', system-ui, sans-serif; }</style>
    <style>
        body { background-color: #F3F4F6; }
        .navbar { background-color: #2563EB; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); transition: transform 0.15s; }
        .card:hover { transform: translateY(-3px); }
        .badge-taal { background-color: #EFF6FF; color: #2563EB; font-weight: 500; }
        .btn-start { background-color: #2563EB; border-color: #2563EB; }
        .btn-start:hover { background-color: #1D4ED8; border-color: #1D4ED8; }
        .modus-optie { cursor: pointer; border: 2px solid #E5E7EB; border-radius: 10px; padding: 1rem; transition: border-color 0.15s, background 0.15s; }
        .modus-optie:hover { border-color: #2563EB; background: #EFF6FF; }
        .modus-icon { font-size: 1.6rem; margin-bottom: 0.35rem; }
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
            <div class="d-flex gap-2">
                <a href="ai_lijst.php" class="btn btn-outline-secondary">✨ AI lijst maken</a>
                <a href="multiplayer/lobby.php" class="btn btn-outline-primary">⚔️ Multiplayer</a>
            </div>
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
                                <button onclick="openModusModal(<?= $lijst['id'] ?>)"
                                        class="btn btn-start btn-sm text-white w-100">
                                    Starten
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Moduskeuze modal -->
    <div class="modal fade" id="modusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:16px">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modal-titel">Kies een oefenmodus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">

                    <!-- Stap 1: moduskeuze -->
                    <div id="stap1">
                        <div class="d-grid gap-3">
                            <div class="modus-optie" onclick="selectModus('invullen')">
                                <div class="modus-icon">✏️</div>
                                <div class="fw-semibold">Invullen</div>
                                <div class="text-muted small">Typ de volledige vertaling zelf in.</div>
                            </div>
                            <div class="modus-optie" onclick="selectModus('aanvullen')">
                                <div class="modus-icon">🔤</div>
                                <div class="fw-semibold">Aanvullen <span class="badge bg-success ms-1" style="font-size:0.7rem">makkelijker</span></div>
                                <div class="text-muted small">Je ziet een deel van het antwoord als hint, bijv. <strong>b..wn</strong></div>
                            </div>
                            <div class="modus-optie" onclick="selectModus('meerkeuze')">
                                <div class="modus-icon">🔘</div>
                                <div class="fw-semibold">Meerkeuze <span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem">tijdsdruk</span></div>
                                <div class="text-muted small">Kies het juiste antwoord uit vier opties. Snel antwoorden geeft meer punten.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Stap 2: instellingen -->
                    <div id="stap2" style="display:none">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Hoeveel woorden wil je oefenen?</label>
                            <select class="form-select" id="aantal-select">
                                <option value="0">Alles (hele lijst)</option>
                                <option value="5">5 woorden</option>
                                <option value="10">10 woorden</option>
                                <option value="15">15 woorden</option>
                                <option value="20">20 woorden</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Maximale tijdsduur</label>
                            <select class="form-select" id="tijd-select">
                                <option value="0">Geen tijdslimiet</option>
                                <option value="300">5 minuten</option>
                                <option value="600">10 minuten</option>
                                <option value="900">15 minuten</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-secondary" onclick="terug()">← Terug</button>
                            <button class="btn btn-primary flex-fill fw-semibold" onclick="startQuiz()">Starten</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let huidigeLijstId = null;
        let geselecteerdeModus = null;
        let modusModal = null;

        function openModusModal(lijstId) {
            huidigeLijstId = lijstId;
            // Reset naar stap 1
            document.getElementById('stap1').style.display = 'block';
            document.getElementById('stap2').style.display = 'none';
            document.getElementById('modal-titel').textContent = 'Kies een oefenmodus';
            modusModal = new bootstrap.Modal(document.getElementById('modusModal'));
            modusModal.show();
        }

        function selectModus(modus) {
            geselecteerdeModus = modus;
            document.getElementById('stap1').style.display = 'none';
            document.getElementById('stap2').style.display = 'block';
            document.getElementById('modal-titel').textContent = 'Instellingen';
        }

        function terug() {
            document.getElementById('stap2').style.display = 'none';
            document.getElementById('stap1').style.display = 'block';
            document.getElementById('modal-titel').textContent = 'Kies een oefenmodus';
        }

        function startQuiz() {
            const aantal = document.getElementById('aantal-select').value;
            const tijd   = document.getElementById('tijd-select').value;
            let url = `quiz.php?lijst=${huidigeLijstId}&modus=${geselecteerdeModus}`;
            if (aantal > 0) url += `&aantal=${aantal}`;
            if (tijd   > 0) url += `&tijd=${tijd}`;
            window.location.href = url;
        }
    </script>
</body>
</html>
