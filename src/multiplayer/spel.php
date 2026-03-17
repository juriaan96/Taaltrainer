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
        /* Meerkeuze */
        .btn-keuze { background:#fff; border:2px solid #E5E7EB; color:#1F2937; border-radius:10px; padding:0.7rem 1rem; text-align:left; font-weight:500; transition:border-color 0.15s,background 0.15s; }
        .btn-keuze:hover { border-color:#7C3AED; background:#F5F3FF; color:#1F2937; }
        .btn-keuze-correct { border-color:#16A34A !important; background:#F0FDF4 !important; color:#15803D !important; font-weight:700 !important; }
        .btn-keuze-fout    { border-color:#DC2626 !important; background:#FEF2F2 !important; color:#B91C1C !important; }
        /* Emoji */
        .emoji-bar { display:none; text-align:center; margin-top:0.75rem; }
        .emoji-btn { background:none; border:none; font-size:1.4rem; cursor:pointer; opacity:0.7; transition:opacity 0.15s,transform 0.15s; padding:0.2rem 0.4rem; }
        .emoji-btn:hover { opacity:1; transform:scale(1.2); }
        .emoji-ontvangen { font-size:1.8rem; min-height:2rem; text-align:center; transition:opacity 0.3s; }
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
                        <div class="emoji-ontvangen" id="emoji-ontvangen"></div>
                    </div>
                </div>

                <!-- Woord -->
                <div class="woord-display mb-3" id="woord-display">...</div>

                <!-- Antwoord / feedback blok -->
                <div class="antwoord-blok">
                    <!-- Invullen -->
                    <div id="input-vak">
                        <input type="text" id="antwoord-input" class="form-control form-control-lg text-center mb-2"
                               placeholder="Vertaal hier..." autocomplete="off">
                        <button class="btn btn-lg w-100 text-white" id="controleer-btn"
                                style="background:#2563EB;border-color:#2563EB"
                                onclick="stuurAntwoord()">Controleer</button>
                    </div>
                    <!-- Meerkeuze -->
                    <div id="keuze-vak" class="d-grid gap-2" style="display:none !important"></div>
                    <!-- Feedback -->
                    <div id="feedback-vak" style="display:none">
                        <div class="feedback-vak" id="feedback-bericht"></div>
                    </div>
                    <div class="teg-status" id="teg-status"></div>
                </div>
                <!-- Emoji-bar -->
                <div class="emoji-bar" id="emoji-bar">
                    <button class="emoji-btn" onclick="stuurEmoji('👍')">👍</button>
                    <button class="emoji-btn" onclick="stuurEmoji('😅')">😅</button>
                    <button class="emoji-btn" onclick="stuurEmoji('🎉')">🎉</button>
                    <button class="emoji-btn" onclick="stuurEmoji('😤')">😤</button>
                </div>
            </div>

            <!-- Game over -->
            <div id="fase-klaar" style="display:none; text-align:center;">
                <div id="eindresultaat-emoji" style="font-size:3rem"></div>
                <div class="fw-bold fs-4 mt-2" id="eindresultaat-tekst"></div>
                <div class="mt-2 text-muted" id="eindscore"></div>
                <button id="rematch-btn" class="btn btn-success mt-4 w-100 fw-bold" onclick="startRematch()" style="display:none">
                    🔁 Rematch – zelfde lijst &amp; modus
                </button>
                <div id="rematch-wacht" class="text-muted small mt-2" style="display:none">
                    ⏳ Wachten op rematch van tegenstander...
                </div>
                <a href="lobby.php" class="btn btn-primary mt-2 w-100" style="background:#2563EB;border-color:#2563EB">
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
    let huidigeModus  = 'invullen';
    let tegAntwoordDetectedAt = null;
    let countdownInterval     = null;
    let vorigeEmoji   = null;
    let woordZichtbaarOp      = 0;  // timestamp waarop het woord zichtbaar wordt na 3-2-1

    // ── Timeout (tegenstander al klaar, jij nog niet) ─────────────────────────
    async function tijdVoorbij() {
        if (beantwoord) return;
        beantwoord = true;
        document.getElementById('controleer-btn').disabled = true;
        disableKeuzeKnoppen();
        try {
            const r    = await fetch(`api.php?actie=timeout&game=${GAME_CODE}`, { method: 'POST' });
            const data = await r.json();
            verwerkStatus(data);
        } catch(e) {}
    }

    // ── Polling ───────────────────────────────────────────────────────────────
    async function pollStatus() {
        if (!pollingActief) return;
        try {
            const r    = await fetch(`api.php?actie=status&game=${GAME_CODE}`);
            const data = await r.json();
            verwerkStatus(data);
        } catch(e) {}
    }

    // ── Antwoord sturen (invullen) ────────────────────────────────────────────
    async function stuurAntwoord() {
        const antwoord = document.getElementById('antwoord-input').value.trim();
        if (!antwoord || beantwoord) return;
        verzendAntwoord(antwoord);
    }

    // ── Keuze sturen (meerkeuze) ──────────────────────────────────────────────
    async function stuurKeuze(optie) {
        if (beantwoord) return;
        disableKeuzeKnoppen();
        verzendAntwoord(optie);
    }

    async function verzendAntwoord(antwoord) {
        beantwoord = true;
        document.getElementById('controleer-btn').disabled = true;
        const fd = new FormData();
        fd.append('game', GAME_CODE);
        fd.append('antwoord', antwoord);
        if (tegAntwoordDetectedAt) {
            fd.append('teg_wacht_ms', Date.now() - tegAntwoordDetectedAt);
        }
        try {
            const r    = await fetch('api.php?actie=antwoord', { method: 'POST', body: fd });
            const data = await r.json();
            verwerkStatus(data);
        } catch(e) {}
    }

    // ── Emoji sturen ──────────────────────────────────────────────────────────
    async function stuurEmoji(emoji) {
        await fetch(`api.php?actie=emoji&game=${GAME_CODE}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'emoji=' + encodeURIComponent(emoji),
        });
    }

    // ── Rematch ───────────────────────────────────────────────────────────────
    async function startRematch() {
        document.getElementById('rematch-btn').style.display  = 'none';
        document.getElementById('rematch-wacht').style.display = 'block';
        try {
            const r    = await fetch(`api.php?actie=rematch&game=${GAME_CODE}`, { method: 'POST' });
            const data = await r.json();
            if (data.code) window.location.href = `pregame.php?game=${data.code}`;
        } catch(e) {
            document.getElementById('rematch-wacht').textContent = 'Rematch niet mogelijk.';
        }
    }

    // ── Status verwerken ──────────────────────────────────────────────────────
    function verwerkStatus(data) {
        if (data.fase === 'wachten' || data.fase === 'pregame') {
            window.location.href = `pregame.php?game=${GAME_CODE}`;
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
            // Rematch knop alleen voor speler 1
            if (data.jij_is_speler1) {
                document.getElementById('rematch-btn').style.display = 'block';
            }
            return;
        }

        if (data.fase === 'bezig') {
            toonFase('bezig');
            huidigeModus = data.modus ?? 'invullen';

            document.getElementById('score-jij').textContent   = data.score_jij;
            document.getElementById('score-teg').textContent   = data.score_tegenstander;
            document.getElementById('teg-naam').textContent    = data.tegenstander_naam;
            document.getElementById('ronde-label').textContent = `${data.ronde}/${data.max_rondes}`;
            document.getElementById('emoji-bar').style.display = 'block';

            // Emoji van tegenstander
            if (data.emoji_ontvangen && data.emoji_ontvangen !== vorigeEmoji) {
                vorigeEmoji = data.emoji_ontvangen;
                const el = document.getElementById('emoji-ontvangen');
                el.textContent = data.emoji_ontvangen;
                el.style.opacity = '1';
                setTimeout(() => { el.style.opacity = '0'; setTimeout(() => { el.textContent = ''; }, 300); }, 2000);
            }

            // Nieuwe ronde → reset UI met countdown
            if (data.ronde !== huidigeRonde) {
                huidigeRonde = data.ronde;
                beantwoord   = false;
                tegAntwoordDetectedAt = null;
                clearInterval(countdownInterval);
                countdownInterval = null;
                vorigeEmoji = null;

                document.getElementById('feedback-vak').style.display = 'none';
                document.getElementById('teg-status').textContent     = '';
                document.getElementById('input-vak').style.display    = 'none';
                document.getElementById('keuze-vak').style.display    = 'none';

                // Countdown zodat beide spelers tegelijk starten
                let tick = 3;
                woordZichtbaarOp = Date.now() + 3000;
                const woordEl = document.getElementById('woord-display');
                woordEl.textContent = tick;
                const cdInterval = setInterval(() => {
                    tick--;
                    if (tick > 0) {
                        woordEl.textContent = tick;
                    } else {
                        clearInterval(cdInterval);
                        woordZichtbaarOp = Date.now();
                        woordEl.textContent = data.woord;
                        if (huidigeModus === 'meerkeuze') {
                            document.getElementById('keuze-vak').style.display = 'grid';
                            vulKeuzeKnoppen(data.opties ?? []);
                        } else {
                            document.getElementById('input-vak').style.display = 'block';
                            document.getElementById('antwoord-input').value    = '';
                            document.getElementById('controleer-btn').disabled = false;
                            setTimeout(() => document.getElementById('antwoord-input').focus(), 50);
                        }
                    }
                }, 1000);
            }

            // Tegenstander-countdown
            const tegStatus = document.getElementById('teg-status');
            if (data.tegenstander_beantwoord && !data.jij_beantwoord) {
                if (!tegAntwoordDetectedAt && Date.now() >= woordZichtbaarOp) {
                    // Timer start pas als het woord zichtbaar is (na 3-2-1 countdown)
                    tegAntwoordDetectedAt = Date.now();
                    clearInterval(countdownInterval);
                    countdownInterval = setInterval(() => {
                        const elapsed = Math.floor((Date.now() - tegAntwoordDetectedAt) / 1000);
                        const sec     = Math.max(0, 3 - elapsed);
                        const punten  = Math.max(0, 1.0 - elapsed * 0.2).toFixed(1);
                        tegStatus.textContent = `⚡ Tegenstander klaar! Nog ${sec}s — nog ${punten} pt bij goed`;
                        if (sec <= 0) { clearInterval(countdownInterval); countdownInterval = null; tijdVoorbij(); }
                    }, 100);
                }
            } else if (data.tegenstander_beantwoord) {
                tegStatus.textContent = '✓ Tegenstander heeft geantwoord';
            } else if (data.jij_beantwoord) {
                tegStatus.textContent = '⏳ Wachten op tegenstander...';
            } else {
                tegStatus.textContent = '';
            }

            // Feedback
            if (data.jij_beantwoord) {
                document.getElementById('input-vak').style.display  = 'none';
                document.getElementById('keuze-vak').style.display  = 'none';
                document.getElementById('feedback-vak').style.display = 'block';
                const fb = document.getElementById('feedback-bericht');
                if (data.jij_correct) {
                    fb.className = 'feedback-vak feedback-correct';
                    fb.textContent = '✓ Goed! ' + (data.correcte_antwoord ?? '');
                } else {
                    fb.className = 'feedback-vak feedback-fout';
                    fb.textContent = '✗ Fout – juist: ' + (data.correcte_antwoord ?? '');
                }
                // Kleur de keuze-knoppen na-achteraf bij meerkeuze
                if (huidigeModus === 'meerkeuze') kleurKeuzeKnoppen(data);
            }
        }
    }

    function vulKeuzeKnoppen(opties) {
        const vak = document.getElementById('keuze-vak');
        vak.innerHTML = opties.map(o =>
            `<button class="btn btn-keuze w-100" data-optie="${o.replace(/"/g,'&quot;')}" onclick="stuurKeuze(this.dataset.optie)">${escHtml(o)}</button>`
        ).join('');
    }

    function disableKeuzeKnoppen() {
        document.querySelectorAll('#keuze-vak .btn-keuze').forEach(b => b.disabled = true);
    }

    function kleurKeuzeKnoppen(data) {
        document.querySelectorAll('#keuze-vak .btn-keuze, #keuze-vak .btn-keuze-correct, #keuze-vak .btn-keuze-fout')
            .forEach(b => {
                const tekst = b.textContent.trim();
                if (tekst === (data.correcte_antwoord ?? '')) {
                    b.className = 'btn btn-keuze-correct w-100 disabled';
                    b.textContent += ' ✓';
                } else if (tekst === (data.jij_antwoord_tekst ?? '') && !data.jij_correct) {
                    b.className = 'btn btn-keuze-fout w-100 disabled';
                    b.textContent += ' ✗';
                } else {
                    b.disabled = true;
                }
            });
    }

    function toonFase(fase) {
        ['wachten','bezig','klaar'].forEach(f =>
            document.getElementById('fase-' + f).style.display = f === fase ? 'block' : 'none'
        );
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    document.addEventListener('keydown', e => { if (e.key === 'Enter') stuurAntwoord(); });
    setInterval(pollStatus, 300);
    pollStatus();
    </script>
</body>
</html>
