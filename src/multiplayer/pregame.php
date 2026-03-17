<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$code = strtoupper(trim($_GET['game'] ?? ''));
if (!$code) { header('Location: lobby.php'); exit; }

// Verifieer toegang
$stmt = $pdo->prepare('SELECT g.*, u1.username AS speler1_naam, u2.username AS speler2_naam
                       FROM multiplayer_games g
                       LEFT JOIN users u1 ON u1.id = g.speler1_id
                       LEFT JOIN users u2 ON u2.id = g.speler2_id
                       WHERE g.code = ? AND (g.speler1_id = ? OR g.speler2_id = ?)');
$stmt->execute([$code, $_SESSION['user_id'], $_SESSION['user_id']]);
$game = $stmt->fetch();

if (!$game) { header('Location: lobby.php'); exit; }

// Als game al bezig is, meteen doorgaan
if (in_array($game['status'], ['bezig', 'klaar'])) {
    header('Location: spel.php?game=' . $code);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lobby – Taaltrainer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>:root { --bs-font-sans-serif: 'Inter', system-ui, sans-serif; }</style>
    <style>
        body { background-color: #F3F4F6; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background-color: #2563EB; }
        .lobby-wrap { flex: 1; display: flex; align-items: flex-start; justify-content: center; padding: 2rem 1rem; }
        .lobby-inner { width: 100%; max-width: 560px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .code-badge { font-size: 2rem; font-weight: 800; letter-spacing: 0.25em; color: #2563EB; }
        .speler-kaart { border: 2px solid #E5E7EB; border-radius: 10px; padding: 0.9rem 1.2rem; transition: border-color 0.2s, background 0.2s; }
        .speler-kaart.klaar { border-color: #16A34A; background: #F0FDF4; }
        .speler-kaart.wachten-op { border-color: #D1D5DB; background: #F9FAFB; color: #9CA3AF; }
        .chat-berichten { height: 220px; overflow-y: auto; background: #F9FAFB; border-radius: 8px; padding: 0.75rem; border: 1px solid #E5E7EB; }
        .chat-bericht { margin-bottom: 0.4rem; font-size: 0.9rem; }
        .chat-naam { font-weight: 600; color: #2563EB; }
        .chat-naam.mij { color: #7C3AED; }
        .chat-tijd { font-size: 0.75rem; color: #9CA3AF; margin-left: 4px; }
        .btn-klaar { font-size: 1.1rem; font-weight: 700; padding: 0.85rem; }
        .btn-klaar.ben-klaar { background: #16A34A; border-color: #16A34A; }
        .btn-klaar.ben-klaar:hover { background: #15803D; border-color: #15803D; }
        .wacht-indicator { color: #6B7280; font-size: 0.9rem; }
        .pulse { animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.4; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3">
        <span class="navbar-brand fw-bold">Taaltrainer – Lobby</span>
        <a href="lobby.php" class="btn btn-sm btn-outline-light">← Verlaten</a>
    </nav>

    <div class="lobby-wrap">
        <div class="lobby-inner">

            <!-- Spelcode -->
            <div class="card p-4 mb-3 text-center">
                <div class="text-muted small mb-1">Spelcode — deel met je tegenstander</div>
                <div class="code-badge" id="spelcode"><?= htmlspecialchars($code) ?></div>
                <button class="btn btn-outline-secondary btn-sm mt-2" onclick="kopieerCode()" id="kopier-btn">
                    Kopiëren
                </button>
            </div>

            <!-- Spelers -->
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <div class="speler-kaart" id="s1-kaart">
                        <div class="small text-muted mb-1">Speler 1</div>
                        <div class="fw-bold" id="s1-naam"><?= htmlspecialchars($game['speler1_naam']) ?></div>
                        <div class="mt-1" id="s1-status"><span class="text-muted small pulse">wachten...</span></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="speler-kaart wachten-op" id="s2-kaart">
                        <div class="small mb-1">Speler 2</div>
                        <div class="fw-bold" id="s2-naam">—</div>
                        <div class="mt-1" id="s2-status"><span class="small">wacht op speler...</span></div>
                    </div>
                </div>
            </div>

            <!-- Chat -->
            <div class="card p-3 mb-3" id="chat-sectie" style="display:none">
                <div class="fw-semibold small mb-2">Chat</div>
                <div class="chat-berichten mb-2" id="chat-berichten"></div>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" id="chat-input"
                           placeholder="Schrijf een bericht..." maxlength="200" autocomplete="off">
                    <button class="btn btn-primary btn-sm px-3" onclick="stuurBericht()">Stuur</button>
                </div>
            </div>

            <!-- Klaar knop -->
            <div id="klaar-sectie" style="display:none">
                <button class="btn btn-outline-success btn-klaar w-100" id="klaar-btn" onclick="toggleKlaar()">
                    Ik ben klaar om te starten ✓
                </button>
                <div class="text-center wacht-indicator mt-2" id="wacht-tekst" style="display:none">
                    Wachten tot de ander ook klaar is...
                </div>
            </div>

        </div>
    </div>

    <script>
    const GAME_CODE    = '<?= $code ?>';
    const MIJN_ID      = <?= (int)$_SESSION['user_id'] ?>;
    const SPELER1_ID   = <?= (int)$game['speler1_id'] ?>;
    const JIJ_BENT     = MIJN_ID === SPELER1_ID ? 1 : 2;

    let benKlaar       = false;
    let vorigeAantal   = -1;

    async function pollLobby() {
        try {
            const r    = await fetch(`api.php?actie=lobby_status&game=${GAME_CODE}`);
            const data = await r.json();

            if (data.fase === 'start') {
                window.location.href = `spel.php?game=${GAME_CODE}`;
                return;
            }

            // Speler 2 aanwezig?
            if (data.speler2_naam) {
                document.getElementById('s2-naam').textContent    = data.speler2_naam;
                document.getElementById('s2-kaart').classList.remove('wachten-op');
                document.getElementById('chat-sectie').style.display = 'block';
                document.getElementById('klaar-sectie').style.display = 'block';
            }

            // Klaar-status per speler
            updateSpelerKaart(1, data.speler1_naam, data.speler1_klaar);
            updateSpelerKaart(2, data.speler2_naam, data.speler2_klaar);

            // Knop bijwerken op basis van eigen status
            const mijzelf_klaar = JIJ_BENT === 1 ? data.speler1_klaar : data.speler2_klaar;
            const ander_klaar   = JIJ_BENT === 1 ? data.speler2_klaar : data.speler1_klaar;
            benKlaar = mijzelf_klaar;

            const btn = document.getElementById('klaar-btn');
            if (mijzelf_klaar) {
                btn.textContent = '✓ Klaar! (klik om te annuleren)';
                btn.className   = 'btn btn-klaar ben-klaar w-100';
            } else {
                btn.textContent = 'Ik ben klaar om te starten ✓';
                btn.className   = 'btn btn-outline-success btn-klaar w-100';
            }

            document.getElementById('wacht-tekst').style.display =
                (mijzelf_klaar && !ander_klaar) ? 'block' : 'none';

            // Nieuwe chatberichten
            if (data.berichten && data.berichten.length !== vorigeAantal) {
                vorigeAantal = data.berichten.length;
                renderChat(data.berichten, data.speler1_naam);
            }
        } catch(e) {}

        setTimeout(pollLobby, 1000);
    }

    function updateSpelerKaart(nr, naam, klaar) {
        const kaart  = document.getElementById(`s${nr}-kaart`);
        const status = document.getElementById(`s${nr}-status`);
        if (!naam) return;
        if (klaar) {
            kaart.classList.add('klaar');
            status.innerHTML = '<span class="text-success fw-semibold small">✓ Klaar!</span>';
        } else {
            kaart.classList.remove('klaar');
            status.innerHTML = '<span class="text-muted small">Nog niet klaar</span>';
        }
    }

    function renderChat(berichten, speler1_naam) {
        const div = document.getElementById('chat-berichten');
        div.innerHTML = berichten.map(b => {
            const isMij = (JIJ_BENT === 1 && b.username === speler1_naam) ||
                          (JIJ_BENT === 2 && b.username !== speler1_naam);
            return `<div class="chat-bericht">
                <span class="chat-naam${isMij ? ' mij' : ''}">${escHtml(b.username)}</span>
                <span class="chat-tijd">${b.tijd}</span>
                <div class="ms-2 text-dark">${escHtml(b.bericht)}</div>
            </div>`;
        }).join('');
        div.scrollTop = div.scrollHeight;
    }

    async function stuurBericht() {
        const inp    = document.getElementById('chat-input');
        const tekst  = inp.value.trim();
        if (!tekst) return;
        inp.value = '';
        await fetch(`api.php?actie=chat&game=${GAME_CODE}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'bericht=' + encodeURIComponent(tekst),
        });
    }

    async function toggleKlaar() {
        await fetch(`api.php?actie=klaar&game=${GAME_CODE}`, { method: 'POST' });
    }

    function kopieerCode() {
        navigator.clipboard.writeText(GAME_CODE).then(() => {
            const btn = document.getElementById('kopier-btn');
            btn.textContent = 'Gekopieerd!';
            setTimeout(() => btn.textContent = 'Kopiëren', 2000);
        });
    }

    document.getElementById('chat-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') stuurBericht();
    });

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    pollLobby();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
