<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// ── Talen & categorieën ───────────────────────────────────────────────────────
$talen = [
    'Nederlands' => 'nl', 'Engels'     => 'en', 'Duits'      => 'de',
    'Frans'      => 'fr', 'Spaans'     => 'es', 'Italiaans'  => 'it',
    'Portugees'  => 'pt', 'Russisch'   => 'ru', 'Turks'      => 'tr',
    'Arabisch'   => 'ar', 'Chinees'    => 'zh', 'Japans'     => 'ja',
    'Pools'      => 'pl', 'Zweeds'     => 'sv',
];

$categorieen = [
    'Kleuren'        => ['rood','blauw','groen','geel','wit','zwart','oranje','paars','roze','bruin','grijs','beige'],
    'Dieren'         => ['hond','kat','paard','vis','vogel','konijn','koe','schaap','tijger','leeuw','aap','olifant','slang','beer'],
    'Lichaamsdelen'  => ['hoofd','hand','voet','oog','oor','mond','neus','arm','been','hart','rug','buik'],
    'Familie'        => ['vader','moeder','broer','zus','opa','oma','oom','tante','neef','nicht','baby','kind'],
    'Eten & drinken' => ['brood','melk','water','kaas','ei','appel','banaan','tomaat','rijst','koffie','thee','wijn','soep','vlees'],
    'Transport'      => ['auto','fiets','trein','vliegtuig','boot','bus','motor','taxi','metro','step','schip'],
    'Huis & wonen'   => ['huis','kamer','tafel','stoel','bed','deur','raam','keuken','badkamer','tuin','trap','plafond'],
    'Werkwoorden'    => ['werken','lezen','schrijven','eten','drinken','slapen','lopen','praten','kopen','geven','zien','horen'],
    'Natuur & weer'  => ['zon','maan','regen','wind','sneeuw','boom','bloem','zee','berg','rivier','wolk','storm'],
    'Kleding'        => ['broek','shirt','jas','schoen','jurk','sok','muts','sjaal','handschoen','trui','rok','riem'],
    'Getallen'       => ['één','twee','drie','vier','vijf','zes','zeven','acht','negen','tien','twintig','honderd'],
    'Tijdwoorden'    => ['dag','week','maand','jaar','uur','minuut','gisteren','vandaag','morgen','altijd','nooit','nu'],
];

// ── Parallel vertalen via MyMemory API ───────────────────────────────────────
function vertaalParallel(array $woorden, string $van, string $naar): array {
    $mh      = curl_multi_init();
    $handles = [];

    foreach ($woorden as $i => $woord) {
        $url = 'https://api.mymemory.translated.net/get?q=' . urlencode($woord) . '&langpair=' . $van . '|' . $naar;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true]);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }

    do { curl_multi_exec($mh, $actief); curl_multi_select($mh, 0.1); } while ($actief);

    $resultaten = [];
    foreach ($handles as $i => $ch) {
        $data       = json_decode(curl_multi_getcontent($ch), true);
        $vertaling  = $data['responseData']['translatedText'] ?? $woorden[$i];
        // Soms geeft MyMemory meerdere suggesties met komma — neem de eerste
        $vertaling  = trim(explode(',', $vertaling)[0]);
        $resultaten[$i] = $vertaling;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $resultaten;
}

$fout    = null;
$preview = null;
$formdata = [];

// ── Stap 2: opslaan ───────────────────────────────────────────────────────────
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
    $fout = 'Vul alle velden in.';
}

// ── Stap 1: genereer lijst ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['genereer'])) {
    $cat       = $_POST['categorie']  ?? '';
    $taal_van  = trim($_POST['taal_van']  ?? '');
    $taal_naar = trim($_POST['taal_naar'] ?? '');
    $naam      = trim($_POST['lijst_naam'] ?? '');

    $formdata = compact('cat', 'taal_van', 'taal_naar', 'naam');

    if (!$cat || !isset($categorieen[$cat]) || !$naam) {
        $fout = 'Vul alle velden in.';
    } elseif (!isset($talen[$taal_van]) || !isset($talen[$taal_naar])) {
        $fout = 'Kies geldige talen.';
    } elseif ($taal_van === $taal_naar) {
        $fout = 'Kies twee verschillende talen.';
    } else {
        $nl_woorden   = $categorieen[$cat];
        $code_van     = $talen[$taal_van];
        $code_naar    = $talen[$taal_naar];

        if ($taal_van === 'Nederlands') {
            // Bron is NL: vertaal NL → doel
            $vertalingen = vertaalParallel($nl_woorden, 'nl', $code_naar);
            $preview = array_map(
                fn($i, $nl) => ['woord' => $nl, 'vertaling' => $vertalingen[$i]],
                array_keys($nl_woorden), $nl_woorden
            );
        } elseif ($taal_naar === 'Nederlands') {
            // Doel is NL: vertaal NL → bron, bron = vertaling
            $bronen = vertaalParallel($nl_woorden, 'nl', $code_van);
            $preview = array_map(
                fn($i, $nl) => ['woord' => $bronen[$i], 'vertaling' => $nl],
                array_keys($nl_woorden), $nl_woorden
            );
        } else {
            // Beide niet-NL: vertaal NL → bron en NL → doel parallel
            $mh = curl_multi_init();
            $chs_van  = [];
            $chs_naar = [];
            foreach ($nl_woorden as $i => $woord) {
                foreach (['van' => $code_van, 'naar' => $code_naar] as $richting => $code) {
                    $url = 'https://api.mymemory.translated.net/get?q=' . urlencode($woord) . '&langpair=nl|' . $code;
                    $ch  = curl_init($url);
                    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
                    curl_multi_add_handle($mh, $ch);
                    if ($richting === 'van') $chs_van[$i]  = $ch;
                    else                     $chs_naar[$i] = $ch;
                }
            }
            do { curl_multi_exec($mh, $actief); curl_multi_select($mh, 0.1); } while ($actief);

            $preview = [];
            foreach ($nl_woorden as $i => $nl) {
                $r_van  = json_decode(curl_multi_getcontent($chs_van[$i]),  true);
                $r_naar = json_decode(curl_multi_getcontent($chs_naar[$i]), true);
                $woord     = trim(explode(',', $r_van['responseData']['translatedText']  ?? $nl)[0]);
                $vertaling = trim(explode(',', $r_naar['responseData']['translatedText'] ?? $nl)[0]);
                $preview[] = ['woord' => $woord, 'vertaling' => $vertaling];
                curl_multi_remove_handle($mh, $chs_van[$i]);  curl_close($chs_van[$i]);
                curl_multi_remove_handle($mh, $chs_naar[$i]); curl_close($chs_naar[$i]);
            }
            curl_multi_close($mh);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Woordenlijst genereren – Taaltrainer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>:root { --bs-font-sans-serif: 'Inter', system-ui, sans-serif; }</style>
    <style>
        body { background-color: #F3F4F6; }
        .navbar { background-color: #2563EB; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .cat-optie { cursor: pointer; border: 2px solid #E5E7EB; border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.9rem; transition: border-color 0.1s, background 0.1s; display: inline-block; margin: 0.2rem; }
        .cat-optie:hover, .cat-optie.actief { border-color: #2563EB; background: #EFF6FF; color: #2563EB; font-weight: 600; }
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
            <h1 class="fs-4 fw-bold text-dark mb-0">✨ Woordenlijst genereren</h1>
            <p class="text-muted small">Kies een categorie en talen — de vertaling gaat via de gratis MyMemory API.</p>
        </div>

        <?php if ($fout): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fout) ?></div>
        <?php endif; ?>

        <?php if ($preview === null): ?>
        <div class="card p-4">
            <form method="POST" id="gen-form">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Naam voor de woordenlijst</label>
                    <input type="text" class="form-control" name="lijst_naam"
                           placeholder="bijv. Kleuren Duits" required
                           value="<?= htmlspecialchars($formdata['naam'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Categorie</label>
                    <div>
                        <?php foreach (array_keys($categorieen) as $cat): ?>
                            <span class="cat-optie <?= ($formdata['cat'] ?? '') === $cat ? 'actief' : '' ?>"
                                  onclick="kiesCat(this, '<?= addslashes($cat) ?>')">
                                <?= htmlspecialchars($cat) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="categorie" id="cat-hidden" value="<?= htmlspecialchars($formdata['cat'] ?? '') ?>" required>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col">
                        <label class="form-label fw-semibold">Taal van</label>
                        <select class="form-select" name="taal_van" required>
                            <?php foreach (array_keys($talen) as $taal): ?>
                                <option value="<?= $taal ?>" <?= ($formdata['taal_van'] ?? 'Nederlands') === $taal ? 'selected' : '' ?>>
                                    <?= $taal ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label fw-semibold">Taal naar</label>
                        <select class="form-select" name="taal_naar" required>
                            <?php foreach (array_keys($talen) as $taal): ?>
                                <option value="<?= $taal ?>" <?= ($formdata['taal_naar'] ?? 'Duits') === $taal ? 'selected' : '' ?>>
                                    <?= $taal ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" name="genereer" value="1">
                <button type="submit" class="btn btn-primary fw-semibold w-100" id="gen-btn">
                    ✨ Genereer woordenlijst
                </button>
            </form>
        </div>

        <?php else: ?>
        <div class="card p-4 mb-3">
            <h5 class="fw-bold mb-1">Preview — <?= count($preview) ?> woorden</h5>
            <p class="text-muted small mb-3">Vertaald via MyMemory. Controleer de vertaling voor je opslaat.</p>
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
            <input type="hidden" name="lijst_naam" value="<?= htmlspecialchars($formdata['naam']) ?>">
            <input type="hidden" name="taal_van"   value="<?= htmlspecialchars($formdata['taal_van']) ?>">
            <input type="hidden" name="taal_naar"  value="<?= htmlspecialchars($formdata['taal_naar']) ?>">
            <?php foreach ($preview as $paar): ?>
                <input type="hidden" name="paren[]" value="<?= htmlspecialchars($paar['woord'] . '|' . $paar['vertaling']) ?>">
            <?php endforeach; ?>
            <a href="ai_lijst.php" class="btn btn-outline-secondary">↩ Opnieuw</a>
            <button type="submit" name="opslaan" class="btn btn-success fw-semibold flex-fill">
                Opslaan en oefenen
            </button>
        </form>
        <?php endif; ?>
    </div>

    <script>
    function kiesCat(el, naam) {
        document.querySelectorAll('.cat-optie').forEach(e => e.classList.remove('actief'));
        el.classList.add('actief');
        document.getElementById('cat-hidden').value = naam;
    }
    document.getElementById('gen-form')?.addEventListener('submit', function() {
        const btn = document.getElementById('gen-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Bezig met vertalen...';
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
