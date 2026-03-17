<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Geeft een gemaskeerde hint van een woord: "horse" → "h...e", "brown" → "b..wn"
function maskeerWoord(string $woord): string {
    $len = mb_strlen($woord);
    if ($len <= 2) return $woord;
    if ($len === 3) {
        return mb_substr($woord, 0, 1) . '.' . mb_substr($woord, -1);
    }
    $begin  = $len >= 7 ? 2 : 1;
    $eind   = 2;
    $midden = $len - $begin - $eind;
    return mb_substr($woord, 0, $begin) . str_repeat('.', $midden) . mb_substr($woord, -$eind);
}

// Quiz initialiseren bij eerste bezoek (alleen bij GET, niet bij form submit)
if (isset($_GET['lijst']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $lijst_id = (int)$_GET['lijst'];
    $modus    = $_GET['modus'] ?? 'invullen';
    if (!in_array($modus, ['invullen', 'aanvullen', 'meerkeuze'])) $modus = 'invullen';

    $stmt = $pdo->prepare('SELECT * FROM woorden WHERE woordenlijst_id = ?');
    $stmt->execute([$lijst_id]);
    $woorden = $stmt->fetchAll();

    if (empty($woorden)) {
        header('Location: index.php');
        exit;
    }

    $stmt2 = $pdo->prepare('SELECT * FROM woordenlijsten WHERE id = ?');
    $stmt2->execute([$lijst_id]);
    $lijst = $stmt2->fetch();

    // Statistieken ophalen voor gewogen volgorde (foute woorden eerst)
    $stat_stmt = $pdo->prepare('SELECT woord_id, correct, fout FROM woord_statistieken WHERE user_id = ?');
    $stat_stmt->execute([$_SESSION['user_id']]);
    $stats = array_column($stat_stmt->fetchAll(), null, 'woord_id');

    shuffle($woorden);
    usort($woorden, function($a, $b) use ($stats) {
        $gewicht_a = isset($stats[$a['id']]) ? ($stats[$a['id']]['fout'] - $stats[$a['id']]['correct']) : 0;
        $gewicht_b = isset($stats[$b['id']]) ? ($stats[$b['id']]['fout'] - $stats[$b['id']]['correct']) : 0;
        return $gewicht_b <=> $gewicht_a;
    });

    // Optioneel: beperk aantal woorden
    $aantal = (int)($_GET['aantal'] ?? 0);
    if ($aantal > 0) {
        $woorden = array_slice($woorden, 0, $aantal);
    }

    // Optioneel: tijdslimiet
    $tijdslimiet = (int)($_GET['tijd'] ?? 0);

    // Meerkeuze: genereer 4 opties per woord (1 correct + max 3 foute)
    $opties_per_woord = [];
    if ($modus === 'meerkeuze') {
        $alle_vertalingen = array_column($woorden, 'vertaling');
        foreach ($woorden as $w) {
            $foute = array_values(array_filter($alle_vertalingen, fn($v) => $v !== $w['vertaling']));
            shuffle($foute);
            $foute   = array_slice($foute, 0, 3);
            $opties  = array_merge([$w['vertaling']], $foute);
            shuffle($opties);
            $opties_per_woord[$w['id']] = $opties;
        }
    }

    $_SESSION['quiz'] = [
        'lijst_id'        => $lijst_id,
        'lijst_naam'      => $lijst['naam'],
        'woorden'         => $woorden,
        'index'           => 0,
        'score'           => 0,
        'fouten'          => [],
        'fase'            => 'vraag',
        'feedback'        => null,
        'modus'           => $modus,
        'opties'          => $opties_per_woord,
        'woord_resultaten'=> [],
        'tijdslimiet'     => $tijdslimiet,
        'tijdslimiet_start' => $tijdslimiet > 0 ? time() : null,
    ];
}

if (!isset($_SESSION['quiz'])) {
    header('Location: index.php');
    exit;
}

$quiz  = &$_SESSION['quiz'];
$totaal = count($quiz['woorden']);
$modus  = $quiz['modus'];

// Hulpfunctie: sla resultaat op en redirect naar resultaatscherm
function slaResultaatOp(PDO $pdo, array &$quiz, int $user_id): never {
    $stmt = $pdo->prepare('INSERT INTO resultaten (user_id, woordenlijst_id, score, totaal) VALUES (?, ?, ?, ?)');
    $stmt->execute([$user_id, $quiz['lijst_id'], $quiz['score'], count($quiz['woorden'])]);
    $stat_stmt = $pdo->prepare('INSERT INTO woord_statistieken (user_id, woord_id, correct, fout)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE correct = correct + VALUES(correct), fout = fout + VALUES(fout)');
    foreach ($quiz['woord_resultaten'] as $woord_id => $res) {
        $stat_stmt->execute([$user_id, $woord_id, $res['correct'], $res['fout']]);
    }
    $_SESSION['resultaat'] = [
        'score'      => $quiz['score'],
        'totaal'     => count($quiz['woorden']),
        'fouten'     => $quiz['fouten'],
        'lijst_naam' => $quiz['lijst_naam'],
        'lijst_id'   => $quiz['lijst_id'],
    ];
    unset($_SESSION['quiz']);
    header('Location: resultaat.php');
    exit;
}

// Tijdslimiet verlopen?
if (!empty($quiz['tijdslimiet']) && $quiz['tijdslimiet'] > 0) {
    if (time() - $quiz['tijdslimiet_start'] >= $quiz['tijdslimiet']) {
        slaResultaatOp($pdo, $quiz, $_SESSION['user_id']);
    }
}

// POST verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Tijdslimiet verlopen (client-side trigger)
    if (isset($_POST['tijdop'])) {
        slaResultaatOp($pdo, $quiz, $_SESSION['user_id']);
    }

    // Antwoord controleren
    if (isset($_POST['antwoord']) && $quiz['fase'] === 'vraag') {
        $huidig      = $quiz['woorden'][$quiz['index']];
        $antwoord    = trim($_POST['antwoord']);
        $antwoord_lc = strtolower($antwoord);
        $correct_lc  = strtolower(trim($huidig['vertaling']));
        $woord_lc    = strtolower(trim($huidig['woord']));

        if ($modus === 'meerkeuze') {
            // Tijdsbonus: na 3 seconden 0,2 punt aftrekken per seconde
            $elapsed_s = (int)($_POST['elapsed_ms'] ?? 0) / 1000;
            $afschrijving = $elapsed_s > 3 ? min(1.0, floor($elapsed_s - 3) * 0.2) : 0;
            $punten = round(max(0, 1 - $afschrijving), 1);

            if ($antwoord_lc === $correct_lc) {
                $quiz['score']        += $punten;
                $quiz['feedback']      = 'correct';
                $quiz['punten_verdiend'] = $punten;
                $quiz['woord_resultaten'][$huidig['id']] = ['correct' => 1, 'fout' => 0];
            } else {
                $quiz['feedback']      = 'fout';
                $quiz['punten_verdiend'] = 0;
                $quiz['fouten'][] = [
                    'woord'   => $huidig['woord'],
                    'gegeven' => $antwoord,
                    'correct' => $huidig['vertaling'],
                    'bijna'   => false,
                ];
                $quiz['woord_resultaten'][$huidig['id']] = ['correct' => 0, 'fout' => 1];
            }
        } else {
            // Invullen / aanvullen: met fuzzy matching
            if ($antwoord_lc === $correct_lc && $antwoord_lc !== $woord_lc) {
                $quiz['score']   += 1;
                $quiz['feedback'] = 'correct';
                $quiz['woord_resultaten'][$huidig['id']] = ['correct' => 1, 'fout' => 0];
            } else {
                similar_text($antwoord_lc, $correct_lc, $gelijkenis);
                if ($gelijkenis >= 75 && $antwoord_lc !== $woord_lc) {
                    $quiz['score']   += 0.5;
                    $quiz['feedback'] = 'bijna';
                } else {
                    $quiz['feedback'] = 'fout';
                }
                $quiz['fouten'][] = [
                    'woord'   => $huidig['woord'],
                    'gegeven' => $antwoord,
                    'correct' => $huidig['vertaling'],
                    'bijna'   => isset($gelijkenis) && $gelijkenis >= 75 && $antwoord_lc !== $woord_lc,
                ];
                $quiz['woord_resultaten'][$huidig['id']] = ['correct' => 0, 'fout' => 1];
            }
        }
        $quiz['fase'] = 'feedback';

    // Volgende woord
    } elseif (isset($_POST['volgende']) && $quiz['fase'] === 'feedback') {
        $quiz['index']++;
        $quiz['fase']     = 'vraag';
        $quiz['feedback'] = null;

        if ($quiz['index'] >= $totaal) {
            slaResultaatOp($pdo, $quiz, $_SESSION['user_id']);
        }
    }
}

$huidig    = $quiz['woorden'][$quiz['index']];
$vraag_nr  = $quiz['index'] + 1;
$voortgang = round(($quiz['index'] / $totaal) * 100);

// Resterende tijd berekenen voor de UI
$resterend_sec = null;
if (!empty($quiz['tijdslimiet']) && $quiz['tijdslimiet'] > 0) {
    $resterend_sec = max(0, $quiz['tijdslimiet'] - (time() - $quiz['tijdslimiet_start']));
}

$modus_labels = [
    'invullen'  => ['label' => 'Invullen',  'kleur' => '#2563EB'],
    'aanvullen' => ['label' => 'Aanvullen', 'kleur' => '#16A34A'],
    'meerkeuze' => ['label' => 'Meerkeuze', 'kleur' => '#7C3AED'],
];
$modus_info = $modus_labels[$modus];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz – <?= htmlspecialchars($quiz['lijst_naam']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>:root { --bs-font-sans-serif: 'Inter', system-ui, sans-serif; }</style>
    <style>
        body { background-color: #F3F4F6; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background-color: #2563EB; }
        .quiz-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .quiz-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 100%; max-width: 480px; padding: 2.5rem; }
        .woord-display { font-size: 2.5rem; font-weight: 700; color: #1F2937; text-align: center; margin: 1.2rem 0 0.4rem; }
        .hint-display { font-size: 1.3rem; font-weight: 600; color: #16A34A; text-align: center; letter-spacing: 0.12em; margin-bottom: 1.2rem; font-family: monospace; }
        .progress { height: 8px; border-radius: 4px; }
        .progress-bar { background-color: #2563EB; }
        .feedback-correct { background-color: #F0FDF4; border: 2px solid #16A34A; border-radius: 10px; padding: 1rem; }
        .feedback-bijna   { background-color: #FFFBEB; border: 2px solid #D97706; border-radius: 10px; padding: 1rem; }
        .feedback-fout    { background-color: #FEF2F2; border: 2px solid #DC2626; border-radius: 10px; padding: 1rem; }
        .btn-correct { background-color: #16A34A; border-color: #16A34A; color: white; }
        .btn-correct:hover { background-color: #15803D; }
        .btn-bijna { background-color: #D97706; border-color: #D97706; color: white; }
        .btn-bijna:hover { background-color: #B45309; }
        .btn-fout { background-color: #DC2626; border-color: #DC2626; color: white; }
        .btn-fout:hover { background-color: #B91C1C; }
        /* Meerkeuze knoppen */
        .btn-keuze {
            background: #fff;
            border: 2px solid #E5E7EB;
            color: #1F2937;
            border-radius: 10px;
            padding: 0.75rem 1.25rem;
            text-align: left;
            font-weight: 500;
            transition: border-color 0.15s, background 0.15s;
        }
        .btn-keuze:hover { border-color: #7C3AED; background: #F5F3FF; color: #1F2937; }
        /* Feedback meerkeuze: toon welke juist/fout was */
        .btn-keuze-correct { border-color: #16A34A !important; background: #F0FDF4 !important; color: #15803D !important; font-weight: 700 !important; }
        .btn-keuze-fout    { border-color: #DC2626 !important; background: #FEF2F2 !important; color: #B91C1C !important; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3">
        <span class="navbar-brand fw-bold"><?= htmlspecialchars($quiz['lijst_naam']) ?></span>
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill" style="background:<?= $modus_info['kleur'] ?>"><?= $modus_info['label'] ?></span>
            <?php if ($resterend_sec !== null): ?>
                <span class="badge bg-warning text-dark" id="tijdslimiet-badge">
                    ⏳ <span id="tijdslimiet-tekst"></span>
                </span>
            <?php endif; ?>
            <a href="index.php" class="btn btn-sm btn-outline-light">Stoppen</a>
        </div>
    </nav>

    <div class="quiz-wrap">
        <div class="quiz-card">

            <!-- Voortgang -->
            <div class="d-flex justify-content-between text-muted small mb-1">
                <span>Vraag <?= $vraag_nr ?> van <?= $totaal ?></span>
                <span>Score: <?= $quiz['score'] ?></span>
            </div>
            <div class="progress mb-2">
                <div class="progress-bar" style="width: <?= $voortgang ?>%"></div>
            </div>

            <!-- Woord -->
            <div class="woord-display"><?= htmlspecialchars($huidig['woord']) ?></div>

            <?php if ($modus === 'aanvullen' && $quiz['fase'] === 'vraag'): ?>
                <div class="hint-display"><?= htmlspecialchars(maskeerWoord($huidig['vertaling'])) ?></div>
            <?php else: ?>
                <div style="margin-bottom:1.2rem"></div>
            <?php endif; ?>

            <?php if ($quiz['fase'] === 'vraag'): ?>

                <?php if ($modus === 'meerkeuze'): ?>
                    <!-- Timer voor tijdsdruk -->
                    <div id="quiz-timer" class="text-center mb-2">
                        <span id="timer-badge" class="badge bg-success" style="font-size:0.9rem">⏱ 0.0s</span>
                    </div>
                    <!-- Meerkeuze opties -->
                    <div class="d-grid gap-2">
                        <?php foreach ($quiz['opties'][$huidig['id']] as $optie): ?>
                            <form method="POST" class="keuze-form">
                                <input type="hidden" name="antwoord" value="<?= htmlspecialchars($optie) ?>">
                                <input type="hidden" name="elapsed_ms" value="0" class="elapsed-input">
                                <button type="submit" class="btn btn-keuze w-100">
                                    <?= htmlspecialchars($optie) ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <!-- Tekstveld (invullen / aanvullen) -->
                    <form method="POST" id="quizForm">
                        <div class="mb-3">
                            <input
                                type="text"
                                class="form-control form-control-lg text-center"
                                name="antwoord"
                                placeholder="Vertaal hier..."
                                autocomplete="off"
                                autofocus
                                required
                            >
                        </div>
                        <button type="submit" class="btn btn-lg w-100 text-white"
                                style="background:#2563EB;border-color:#2563EB">Controleer</button>
                    </form>
                <?php endif; ?>

            <?php else: ?>

                <!-- Feedback -->
                <?php if ($quiz['feedback'] === 'correct'): ?>
                    <?php $pv = $quiz['punten_verdiend'] ?? 1; ?>
                    <div class="feedback-correct text-center mb-3">
                        <div class="fw-bold text-success fs-5">Goed! +<?= $pv == 1 ? '1' : number_format($pv, 1) ?> punt<?= ($modus === 'meerkeuze' && $pv < 1) ? ' <span class="text-muted small">(langzaam)</span>' : '' ?></div>
                        <div class="text-muted small mt-1"><?= htmlspecialchars($huidig['vertaling']) ?></div>
                    </div>
                <?php elseif ($quiz['feedback'] === 'bijna'): ?>
                    <div class="feedback-bijna text-center mb-3">
                        <div class="fw-bold fs-5" style="color:#D97706">Bijna goed! +½ punt</div>
                        <div class="text-muted small mt-1">Juiste antwoord: <strong><?= htmlspecialchars($huidig['vertaling']) ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="feedback-fout text-center mb-3">
                        <div class="fw-bold text-danger fs-5">Fout</div>
                        <div class="text-muted small mt-1">Juiste antwoord: <strong><?= htmlspecialchars($huidig['vertaling']) ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ($modus === 'meerkeuze'): ?>
                    <!-- Meerkeuze feedback: toon welke optie goed/fout was -->
                    <div class="d-grid gap-2 mb-3">
                        <?php
                        $gegeven_antwoord = $quiz['fouten'] ? end($quiz['fouten'])['gegeven'] ?? '' : '';
                        if ($quiz['feedback'] === 'correct') $gegeven_antwoord = $huidig['vertaling'];
                        foreach ($quiz['opties'][$huidig['id']] as $optie):
                            $is_correct = strtolower($optie) === strtolower($huidig['vertaling']);
                            $is_gegeven = strtolower($optie) === strtolower($gegeven_antwoord);
                            $klasse = '';
                            if ($is_correct)                        $klasse = 'btn-keuze-correct';
                            elseif ($is_gegeven && !$is_correct)    $klasse = 'btn-keuze-fout';
                        ?>
                            <div class="btn btn-keuze <?= $klasse ?> w-100 disabled">
                                <?= htmlspecialchars($optie) ?>
                                <?php if ($is_correct): ?> ✓<?php endif; ?>
                                <?php if ($is_gegeven && !$is_correct): ?> ✗<?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <button
                        type="submit"
                        name="volgende"
                        value="1"
                        class="btn <?= $quiz['feedback'] === 'correct' ? 'btn-correct' : ($quiz['feedback'] === 'bijna' ? 'btn-bijna' : 'btn-fout') ?> btn-lg w-100"
                        autofocus
                    >
                        <?= $quiz['index'] + 1 >= $totaal ? 'Bekijk resultaat' : 'Volgende →' ?>
                    </button>
                </form>

            <?php endif; ?>

        </div>
    </div>
    <?php if ($resterend_sec !== null): ?>
    <script>
    (function() {
        let resterend = <?= $resterend_sec ?>;
        const tekst   = document.getElementById('tijdslimiet-tekst');
        const badge   = document.getElementById('tijdslimiet-badge');

        function toonTijd() {
            const min = Math.floor(resterend / 60);
            const sec = resterend % 60;
            tekst.textContent = min + ':' + String(sec).padStart(2, '0');
            if (resterend <= 30) badge.className = 'badge bg-danger';
            else if (resterend <= 60) badge.className = 'badge bg-warning text-dark';
        }

        toonTijd();
        const interval = setInterval(function() {
            resterend--;
            if (resterend <= 0) {
                clearInterval(interval);
                // Tijd op: stuur naar server via verborgen form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'quiz.php';
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'tijdop'; inp.value = '1';
                form.appendChild(inp);
                document.body.appendChild(form);
                form.submit();
            } else {
                toonTijd();
            }
        }, 1000);
    })();
    </script>
    <?php endif; ?>

    <?php if ($modus === 'meerkeuze' && $quiz['fase'] === 'vraag'): ?>
    <script>
    (function() {
        const startMs = Date.now();
        const badge   = document.getElementById('timer-badge');

        function updateTimer() {
            const elapsed = (Date.now() - startMs) / 1000;
            const afschrijving = elapsed > 3 ? Math.min(1, Math.floor(elapsed - 3) * 0.2) : 0;
            const punten = Math.max(0, 1 - afschrijving).toFixed(1);

            if (elapsed <= 3) {
                badge.className = 'badge bg-success';
                badge.textContent = '⏱ ' + elapsed.toFixed(1) + 's';
            } else if (punten > 0) {
                badge.className = 'badge bg-warning text-dark';
                badge.textContent = '⏱ ' + elapsed.toFixed(1) + 's  –  +' + punten + ' punt';
            } else {
                badge.className = 'badge bg-danger';
                badge.textContent = '⏱ ' + elapsed.toFixed(1) + 's  –  +0 punt';
            }

            // Update elapsed_ms in alle keuze-forms
            document.querySelectorAll('.elapsed-input').forEach(function(inp) {
                inp.value = Math.round(Date.now() - startMs);
            });
        }

        setInterval(updateTimer, 100);
    })();
    </script>
    <?php endif; ?>
</body>
</html>
