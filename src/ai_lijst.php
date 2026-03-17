<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$fout     = null;
$preview  = null;  // array van ['woord' => ..., 'vertaling' => ...]
$formdata = [];    // bewaar formuliervelden bij preview

// Stap 2: opslaan na preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opslaan'])) {
    $naam      = trim($_POST['lijst_naam'] ?? '');
    $taal_van  = trim($_POST['taal_van']   ?? '');
    $taal_naar = trim($_POST['taal_naar']  ?? '');
    $paren     = $_POST['paren'] ?? [];

    if ($naam && $taal_van && $taal_naar && count($paren) > 0) {
        $stmt = $pdo->prepare('INSERT INTO woordenlijsten (naam, taal_van, taal_naar, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$naam, $taal_van, $taal_naar, $_SESSION['user_id']]);
        $lijst_id = $pdo->lastInsertId();

        $wstmt = $pdo->prepare('INSERT INTO woorden (woordenlijst_id, woord, vertaling) VALUES (?, ?, ?)');
        foreach ($paren as $paar) {
            $delen = explode('|', $paar, 2);
            if (count($delen) === 2 && trim($delen[0]) !== '' && trim($delen[1]) !== '') {
                $wstmt->execute([$lijst_id, trim($delen[0]), trim($delen[1])]);
            }
        }

        header('Location: index.php');
        exit;
    }
    $fout = 'Vul alle velden in en zorg dat er woorden zijn.';
}

// Stap 1: genereer via Claude API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['genereer'])) {
    $prompt    = trim($_POST['prompt']    ?? '');
    $taal_van  = trim($_POST['taal_van']  ?? '');
    $taal_naar = trim($_POST['taal_naar'] ?? '');
    $naam      = trim($_POST['lijst_naam'] ?? '');
    $aantal    = max(5, min(50, (int)($_POST['aantal'] ?? 10)));

    $formdata = compact('prompt', 'taal_van', 'taal_naar', 'naam', 'aantal');

    if (!$prompt || !$taal_van || !$taal_naar || !$naam) {
        $fout = 'Vul alle velden in.';
    } elseif (ANTHROPIC_API_KEY === 'YOUR_API_KEY_HERE') {
        $fout = 'Voeg eerst een Anthropic API-sleutel toe in src/config.php.';
    } else {
        $systeem = 'Je bent een taaldocent die woordenlijsten maakt. Geef ALLEEN een CSV-lijst terug (geen uitleg, geen markdown), met op elke regel: woord_bron,vertaling. Gebruik een komma als scheider. Gebruik geen aanhalingstekens tenzij het woord een komma bevat.';
        $gebruiker = "Maak een woordenlijst van {$aantal} woorden. Taal van: {$taal_van}. Taal naar: {$taal_naar}. Onderwerp: {$prompt}. Geef precies {$aantal} woorden, één per regel als: woord,vertaling";

        $body = json_encode([
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 1024,
            'system'     => $systeem,
            'messages'   => [['role' => 'user', 'content' => $gebruiker]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . ANTHROPIC_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $fout = 'Verbindingsfout: ' . $err;
        } else {
            $data = json_decode($response, true);
            if (isset($data['error'])) {
                $fout = 'API-fout: ' . ($data['error']['message'] ?? 'Onbekend');
            } else {
                $tekst  = $data['content'][0]['text'] ?? '';
                $regels = array_filter(array_map('trim', explode("\n", $tekst)));
                $preview = [];
                foreach ($regels as $regel) {
                    // Ondersteuning voor komma- en tab-scheiders
                    if (str_contains($regel, "\t")) {
                        $delen = explode("\t", $regel, 2);
                    } else {
                        $delen = explode(',', $regel, 2);
                    }
                    if (count($delen) === 2 && trim($delen[0]) !== '' && trim($delen[1]) !== '') {
                        $preview[] = ['woord' => trim($delen[0]), 'vertaling' => trim($delen[1])];
                    }
                }
                if (empty($preview)) {
                    $fout = 'Kon de AI-reactie niet verwerken. Probeer het opnieuw.';
                    $preview = null;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Woordenlijst – Taaltrainer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>:root { --bs-font-sans-serif: 'Inter', system-ui, sans-serif; }</style>
    <style>
        body { background-color: #F3F4F6; }
        .navbar { background-color: #2563EB; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .ai-icon { font-size: 2rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3 mb-4">
        <span class="navbar-brand fw-bold fs-5">Taaltrainer</span>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-sm btn-outline-light">← Terug</a>
            <a href="logout.php" class="btn btn-sm btn-outline-light">Uitloggen</a>
        </div>
    </nav>

    <div class="container pb-5" style="max-width:640px">
        <div class="mb-4">
            <h1 class="fs-4 fw-bold text-dark mb-0">✨ AI Woordenlijst maken</h1>
            <p class="text-muted small">Beschrijf welke woorden je wil oefenen en de AI genereert een lijst voor je.</p>
        </div>

        <?php if ($fout): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fout) ?></div>
        <?php endif; ?>

        <?php if ($preview === null): ?>
        <!-- Formulier: beschrijf de lijst -->
        <div class="card p-4">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Naam voor de woordenlijst</label>
                    <input type="text" class="form-control" name="lijst_naam"
                           placeholder="bijv. Kleuren Duits" required
                           value="<?= htmlspecialchars($formdata['naam'] ?? '') ?>">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col">
                        <label class="form-label fw-semibold">Taal van</label>
                        <input type="text" class="form-control" name="taal_van"
                               placeholder="bijv. Nederlands" required
                               value="<?= htmlspecialchars($formdata['taal_van'] ?? '') ?>">
                    </div>
                    <div class="col">
                        <label class="form-label fw-semibold">Taal naar</label>
                        <input type="text" class="form-control" name="taal_naar"
                               placeholder="bijv. Duits" required
                               value="<?= htmlspecialchars($formdata['taal_naar'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Beschrijf het onderwerp</label>
                    <input type="text" class="form-control" name="prompt"
                           placeholder="bijv. kleuren, dieren, eten en drinken, werkwoorden..." required
                           value="<?= htmlspecialchars($formdata['prompt'] ?? '') ?>">
                    <div class="form-text">Voorbeelden: "kleuren", "de meest gebruikte werkwoorden", "lichaamsdelen", "fruit en groente"</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Aantal woorden</label>
                    <select class="form-select" name="aantal">
                        <?php foreach ([10, 15, 20, 30] as $n): ?>
                            <option value="<?= $n ?>" <?= ($formdata['aantal'] ?? 10) == $n ? 'selected' : '' ?>><?= $n ?> woorden</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="genereer" class="btn btn-primary fw-semibold w-100">
                    ✨ Genereer woordenlijst
                </button>
            </form>
        </div>

        <?php else: ?>
        <!-- Preview van gegenereerde woorden -->
        <div class="card p-4 mb-3">
            <h5 class="fw-bold mb-3">Preview — <?= count($preview) ?> woorden gegenereerd</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= htmlspecialchars($formdata['taal_van']) ?></th>
                            <th><?= htmlspecialchars($formdata['taal_naar']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview as $paar): ?>
                            <tr>
                                <td><?= htmlspecialchars($paar['woord']) ?></td>
                                <td><?= htmlspecialchars($paar['vertaling']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" class="d-flex gap-2">
            <!-- Verstuur alle data mee voor opslaan -->
            <input type="hidden" name="lijst_naam" value="<?= htmlspecialchars($formdata['naam']) ?>">
            <input type="hidden" name="taal_van"   value="<?= htmlspecialchars($formdata['taal_van']) ?>">
            <input type="hidden" name="taal_naar"  value="<?= htmlspecialchars($formdata['taal_naar']) ?>">
            <?php foreach ($preview as $paar): ?>
                <input type="hidden" name="paren[]" value="<?= htmlspecialchars($paar['woord'] . '|' . $paar['vertaling']) ?>">
            <?php endforeach; ?>
            <a href="ai_lijst.php" class="btn btn-outline-secondary">↩ Opnieuw genereren</a>
            <button type="submit" name="opslaan" class="btn btn-success fw-semibold flex-fill">
                Opslaan en gebruiken
            </button>
        </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
