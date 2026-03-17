<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$code = strtoupper(trim($_GET['game'] ?? ''));
if (!$code) { header('Location: lobby.php'); exit; }

// Verifieer toegang
$stmt = $pdo->prepare('SELECT g.* FROM multiplayer_games g WHERE g.code = ? AND (g.speler1_id = ? OR g.speler2_id = ?)');
$stmt->execute([$code, $_SESSION['user_id'], $_SESSION['user_id']]);
$game = $stmt->fetch();
if (!$game) { header('Location: lobby.php'); exit; }
if (in_array($game['status'], ['wachten', 'lobby'])) { header('Location: pregame.php?game=' . $code); exit; }
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multiplayer – Taaltrainer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>:root { --bs-font-sans-serif: 'Inter', system-ui, sans-serif; }</style>
    <style>
        body { background-color: #F3F4F6; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background-color: #2563EB; }
        .spel-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .spel-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 500px;
            padding: 2rem;
        }
        .woord-display {
            font-size: 2.8rem;
            font-weight: 800;
            color: #1F2937;
            text-align: center;
            min-height: 4rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .score-balk { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .speler-score { text-align: center; }
        .speler-naam { font-size: 0.85rem; color: #6B7280; }
        .speler-getal { font-size: 2rem; font-weight: 800; color: #1F2937; }
        .vs { font-size: 1rem; font-weight: 600; color: #9CA3AF; }
        /* Vaste hoogte voor het antwoord/feedback blok */
        .antwoord-blok {
            min-height: 130px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .feedback-vak {
            border-radius: 10px;
            padding: 0.875rem 1rem;
            text-align: center;
            font-weight: 600;
            font-size: 1.05rem;
        }
        .feedback-correct { background: #F0FDF4; color: #16A34A; border: 2px solid #16A34A; }
        .feedback-fout    { background: #FEF2F2; color: #DC2626; border: 2px solid #DC2626; }
        .feedback-wacht   { background: #EFF6FF; color: #2563EB; border: 2px solid #2563EB; }
        .teg-status { font-size: 0.85rem; text-align: center; margin-top: 0.5rem; color: #6B7280; min-height: 1.25rem; }
        .code-badge { font-family: monospace; letter-spacing: 0.2em; background: #EFF6FF; color: #2563EB; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3">
        <span class="navbar-brand fw-bold">Multiplayer</span>
        <a href="lobby.php" class="btn btn-sm btn-outline-light">Stoppen</a>
    </nav>

    <div class="spel-wrap">
        <div class="spel-card" id="spel-card">

            <!-- Wachten op tegenstander -->
            <div id="fase-wachten" style="display:none; text-align:center;">
                <div style="font-size:2rem">⏳</div>
                <div class="fw-bold fs-5 mt-2">Wachten op tegenstander...</div>
                <div class="mt-3">Deel de spelcode: <span class="code-badge"><?= htmlspecialchars($code) ?></span></div>
                <div class="text-muted small mt-2">Deel dit met je tegenstander</div>
            </div>

            <!-- Spel bezig -->
            <div id="fase-bezig" style="display:none;">
                <!-- Scorebalk -->
                <div class="score-balk">
                    <div class="speler-score">
                        <div class="speler-naam"><?= htmlspecialchars($_SESSION['username']) ?></div>
                        <div class="speler-getal" id="score-jij">0</div>
                    </div>
                    <div>
                        <div class="vs">VS</div>
                        <div class="text-muted small text-center" id="ronde-label"></div>
                    </div>
                    <div class="speler-score">
                        <div class="speler-naam" id="teg-naam">...</div>
                        <div class="speler-getal" id="score-teg">0</div>
                    </div>
                </div>

                <!-- Woord -->
                <div class="woord-display mb-3" id="woord-display">...</div>

                <!-- Antwoord / feedback blok (vaste hoogte) -->
                <div class="antwoord-blok">
                    <div id="input-vak">
                        <input type="text" id="antwoord-input" class="form-control form-control-lg text-center mb-2"
                               placeholder="Vertaal hier..." autocomplete="off">
                        <button class="btn btn-lg w-100 text-white" id="controleer-btn"
                                style="background:#2563EB;border-color:#2563EB"
                                onclick="stuurAntwoord()">Controleer</button>
                    </div>
                    <div id="feedback-vak" style="display:none">
                        <div class="feedback-vak" id="feedback-bericht"></div>
                    </div>
                    <div class="teg-status" id="teg-status"></div>
                </div>
            </div>

            <!-- Game over -->
            <div id="fase-klaar" style="display:none; text-align:center;">
                <div id="eindresultaat-emoji" style="font-size:3rem"></div>
                <div class="fw-bold fs-4 mt-2" id="eindresultaat-tekst"></div>
                <div class="mt-2 text-muted" id="eindscore"></div>
                <a href="lobby.php" class="btn btn-primary mt-4 w-100" style="background:#2563EB;border-color:#2563EB">
                    Nieuw spel
                </a>
                <a href="../index.php" class="btn btn-outline-secondary mt-2 w-100">Terug naar app</a>
            </div>

        </div>
    </div>

    <script>
    const GAME_CODE   = <?= json_encode($code) ?>;
    let huidigeRonde  = 0;
    let beantwoord    = false;
    let pollingActief = true;
    let tegAntwoordDetectedAt = null;
    let countdownInterval     = null;

    async function tijdVoorbij() {
        if (beantwoord) return;
        beantwoord = true;
        document.getElementById('controleer-btn').disabled = true;
        try {
            const resp = await fetch(`api.php?actie=timeout&game=${GAME_CODE}`, { method: 'POST' });
            const data = await resp.json();
            verwerkStatus(data);
        } catch(e) {}
    }

    async function pollStatus() {
        if (!pollingActief) return;
        try {
            const resp = await fetch(`api.php?actie=status&game=${GAME_CODE}`);
            const data = await resp.json();
            verwerkStatus(data);
        } catch(e) {}
    }

    async function stuurAntwoord() {
        const antwoord = document.getElementById('antwoord-input').value.trim();
        if (!antwoord || beantwoord) return;
        beantwoord = true;

        document.getElementById('controleer-btn').disabled = true;

        const formData = new FormData();
        formData.append('game', GAME_CODE);
        formData.append('antwoord', antwoord);

        try {
            const resp = await fetch('api.php?actie=antwoord', { method: 'POST', body: formData });
            const data = await resp.json();
            verwerkStatus(data);
        } catch(e) {}
    }

    function verwerkStatus(data) {
        if (data.fase === 'wachten') {
            toonFase('wachten');
            return;
        }

        if (data.fase === 'klaar') {
            pollingActief = false;
            toonFase('klaar');
            const emoji = data.winnaar === 'jij' ? '🏆' : data.winnaar === 'gelijk' ? '🤝' : '😅';
            const tekst = data.winnaar === 'jij' ? 'Gewonnen!' : data.winnaar === 'gelijk' ? 'Gelijkspel!' : 'Verloren...';
            document.getElementById('eindresultaat-emoji').textContent = emoji;
            document.getElementById('eindresultaat-tekst').textContent = tekst;
            document.getElementById('eindscore').textContent =
                `${data.score_jij} – ${data.score_tegenstander} (${data.tegenstander_naam})`;
            return;
        }

        if (data.fase === 'bezig') {
            toonFase('bezig');

            // Scores
            document.getElementById('score-jij').textContent  = data.score_jij;
            document.getElementById('score-teg').textContent  = data.score_tegenstander;
            document.getElementById('teg-naam').textContent   = data.tegenstander_naam;
            document.getElementById('ronde-label').textContent = `${data.ronde}/${data.max_rondes}`;

            // Nieuwe ronde? Reset UI
            if (data.ronde !== huidigeRonde) {
                huidigeRonde = data.ronde;
                beantwoord   = false;
                tegAntwoordDetectedAt = null;
                clearInterval(countdownInterval);
                countdownInterval = null;
                document.getElementById('woord-display').textContent = data.woord;
                document.getElementById('antwoord-input').value      = '';
                document.getElementById('controleer-btn').disabled   = false;
                document.getElementById('input-vak').style.display    = 'block';
                document.getElementById('feedback-vak').style.display = 'none';
                document.getElementById('teg-status').textContent     = '';
                setTimeout(() => document.getElementById('antwoord-input').focus(), 50);
            }

            // Tegenstander status + countdown
            const tegStatus = document.getElementById('teg-status');
            if (data.tegenstander_beantwoord && !data.jij_beantwoord) {
                if (!tegAntwoordDetectedAt) {
                    tegAntwoordDetectedAt = Date.now();
                    clearInterval(countdownInterval);
                    countdownInterval = setInterval(() => {
                        const seconden = Math.max(0, 3 - Math.floor((Date.now() - tegAntwoordDetectedAt) / 1000));
                        document.getElementById('teg-status').textContent =
                            '⚡ Tegenstander heeft geantwoord! Nog ' + seconden + 's';
                        if (seconden <= 0) {
                            clearInterval(countdownInterval);
                            countdownInterval = null;
                            tijdVoorbij();
                        }
                    }, 100);
                }
            } else if (data.tegenstander_beantwoord) {
                tegStatus.textContent = '✓ Tegenstander heeft geantwoord';
            } else if (data.jij_beantwoord) {
                tegStatus.textContent = '⏳ Wachten op tegenstander...';
            } else {
                tegStatus.textContent = '';
            }

            // Feedback tonen na antwoord
            if (data.jij_beantwoord) {
                document.getElementById('input-vak').style.display    = 'none';
                document.getElementById('feedback-vak').style.display = 'block';
                const fbEl = document.getElementById('feedback-bericht');
                if (data.jij_correct) {
                    fbEl.className = 'feedback-vak feedback-correct';
                    fbEl.textContent = '✓ Goed! ' + (data.correcte_antwoord ?? '');
                } else {
                    fbEl.className = 'feedback-vak feedback-fout';
                    fbEl.textContent = '✗ Fout – juist: ' + (data.correcte_antwoord ?? '');
                }
            }
        }
    }

    function toonFase(fase) {
        ['wachten','bezig','klaar'].forEach(f => {
            document.getElementById('fase-' + f).style.display = f === fase ? 'block' : 'none';
        });
    }

    // Enter = antwoord indienen
    document.addEventListener('keydown', e => {
        if (e.key === 'Enter') stuurAntwoord();
    });

    // Polling
    setInterval(pollStatus, 800);
    pollStatus();
    </script>
</body>
</html>
