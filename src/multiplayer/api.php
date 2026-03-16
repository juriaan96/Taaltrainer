<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['fout' => 'Niet ingelogd']); exit; }

$user_id = (int)$_SESSION['user_id'];
$actie   = $_GET['actie'] ?? $_POST['actie'] ?? '';
$code    = strtoupper(trim($_GET['game'] ?? $_POST['game'] ?? ''));

// ── Hulpfunctie: geef volledige spelstatus terug ──────────────────────────────
function spelStatus(PDO $pdo, array $game, int $user_id): array {
    $is1        = ($game['speler1_id'] == $user_id);
    $mijn_score = $is1 ? $game['score_speler1'] : $game['score_speler2'];
    $teg_score  = $is1 ? $game['score_speler2'] : $game['score_speler1'];
    $teg_naam   = $game['teg_naam'] ?? '...';

    if ($game['status'] === 'wachten') {
        return ['fase' => 'wachten', 'code' => $game['code']];
    }

    if ($game['status'] === 'klaar') {
        if ($mijn_score > $teg_score)      $winnaar = 'jij';
        elseif ($teg_score > $mijn_score)  $winnaar = 'tegenstander';
        else                               $winnaar = 'gelijk';
        return [
            'fase'                 => 'klaar',
            'score_jij'            => $mijn_score,
            'score_tegenstander'   => $teg_score,
            'tegenstander_naam'    => $teg_naam,
            'winnaar'              => $winnaar,
        ];
    }

    // Huidig woord ophalen
    $stmt = $pdo->prepare('SELECT w.woord, w.vertaling FROM multiplayer_woorden mw
                           JOIN woorden w ON mw.woord_id = w.id
                           WHERE mw.game_id = ? AND mw.volgorde = ?');
    $stmt->execute([$game['id'], $game['ronde']]);
    $woord = $stmt->fetch();

    // Antwoorden voor deze ronde
    $stmt2 = $pdo->prepare('SELECT user_id, correct, antwoord FROM multiplayer_antwoorden
                            WHERE game_id = ? AND ronde = ?');
    $stmt2->execute([$game['id'], $game['ronde']]);
    $antwoorden = $stmt2->fetchAll();

    $mijn_ant = null;
    $teg_ant  = false;
    foreach ($antwoorden as $a) {
        if ($a['user_id'] == $user_id) $mijn_ant = $a;
        else                           $teg_ant  = true;
    }

    return [
        'fase'                  => 'bezig',
        'ronde'                 => $game['ronde'],
        'max_rondes'            => $game['max_rondes'],
        'woord'                 => $woord['woord'] ?? '',
        'score_jij'             => $mijn_score,
        'score_tegenstander'    => $teg_score,
        'tegenstander_naam'     => $teg_naam,
        'jij_beantwoord'        => $mijn_ant !== null,
        'jij_correct'           => $mijn_ant ? (bool)$mijn_ant['correct'] : null,
        'jij_antwoord_tekst'    => $mijn_ant ? $mijn_ant['antwoord'] : null,
        'correcte_antwoord'     => $mijn_ant ? $woord['vertaling'] : null,
        'tegenstander_beantwoord' => $teg_ant,
    ];
}

// ── Game ophalen ──────────────────────────────────────────────────────────────
function getGame(PDO $pdo, string $code, int $user_id): ?array {
    $stmt = $pdo->prepare('SELECT g.*, u.username AS teg_naam
                           FROM multiplayer_games g
                           LEFT JOIN users u ON u.id = IF(g.speler1_id = ?, g.speler2_id, g.speler1_id)
                           WHERE g.code = ? AND (g.speler1_id = ? OR g.speler2_id = ?)');
    $stmt->execute([$user_id, $code, $user_id, $user_id]);
    return $stmt->fetch() ?: null;
}

// ── STATUS ────────────────────────────────────────────────────────────────────
if ($actie === 'status') {
    $game = getGame($pdo, $code, $user_id);
    if (!$game) { echo json_encode(['fout' => 'Spel niet gevonden']); exit; }
    echo json_encode(spelStatus($pdo, $game, $user_id));
    exit;
}

// ── ANTWOORD INDIENEN ─────────────────────────────────────────────────────────
if ($actie === 'antwoord') {
    $antwoord = trim($_POST['antwoord'] ?? '');
    $game = getGame($pdo, $code, $user_id);

    if (!$game || $game['status'] !== 'bezig' || !$antwoord) {
        echo json_encode(['fout' => 'Ongeldige actie']); exit;
    }

    // Huidig woord ophalen
    $stmt = $pdo->prepare('SELECT w.woord, w.vertaling FROM multiplayer_woorden mw
                           JOIN woorden w ON mw.woord_id = w.id
                           WHERE mw.game_id = ? AND mw.volgorde = ?');
    $stmt->execute([$game['id'], $game['ronde']]);
    $woord = $stmt->fetch();

    $correct = (int)(strtolower(trim($antwoord)) === strtolower(trim($woord['vertaling']))
                     && strtolower(trim($antwoord)) !== strtolower(trim($woord['woord'])));

    // Antwoord opslaan (negeer als al ingestuurd)
    try {
        $pdo->prepare('INSERT IGNORE INTO multiplayer_antwoorden (game_id, ronde, user_id, antwoord, correct)
                       VALUES (?, ?, ?, ?, ?)')->execute([$game['id'], $game['ronde'], $user_id, $antwoord, $correct]);
    } catch (Exception $e) {}

    // Kijk of beide spelers hebben geantwoord → ronde afsluiten
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM multiplayer_games WHERE id = ? FOR UPDATE');
    $stmt->execute([$game['id']]);
    $game_locked = $stmt->fetch();

    if ($game_locked['ronde'] == $game['ronde']) {
        $stmt2 = $pdo->prepare('SELECT user_id, correct, ingediend_op FROM multiplayer_antwoorden
                                WHERE game_id = ? AND ronde = ? ORDER BY ingediend_op ASC');
        $stmt2->execute([$game['id'], $game['ronde']]);
        $ants = $stmt2->fetchAll();

        if (count($ants) === 2) {
            // Bepaal wie scoort
            $s1_delta = 0; $s2_delta = 0;
            $correcten = array_filter($ants, fn($a) => $a['correct']);
            if (!empty($correcten)) {
                $winnaar_ant = reset($correcten); // eerste correct = snelste
                if ($winnaar_ant['user_id'] == $game['speler1_id']) $s1_delta = 1;
                else                                                  $s2_delta = 1;
            }

            $nieuwe_ronde = $game['ronde'] + 1;
            if ($nieuwe_ronde > $game['max_rondes']) {
                $pdo->prepare('UPDATE multiplayer_games SET status="klaar", score_speler1=score_speler1+?, score_speler2=score_speler2+? WHERE id=?')
                    ->execute([$s1_delta, $s2_delta, $game['id']]);
            } else {
                $pdo->prepare('UPDATE multiplayer_games SET ronde=?, score_speler1=score_speler1+?, score_speler2=score_speler2+? WHERE id=?')
                    ->execute([$nieuwe_ronde, $s1_delta, $s2_delta, $game['id']]);
            }
        }
    }
    $pdo->commit();

    // Herlaad game state
    $game = getGame($pdo, $code, $user_id);
    echo json_encode(spelStatus($pdo, $game, $user_id));
    exit;
}

echo json_encode(['fout' => 'Onbekende actie']);
