<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['fout' => 'Niet ingelogd']); exit; }

$user_id = (int)$_SESSION['user_id'];
$actie   = $_GET['actie'] ?? $_POST['actie'] ?? '';
$code    = strtoupper(trim($_GET['game'] ?? $_POST['game'] ?? ''));

// ── Ronde afsluiten als beide geantwoord hebben OF timeout verstreken ─────────
function advanceerRondeAlsKlaar(PDO $pdo, array $game): void {
    if ($game['status'] !== 'bezig') return;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM multiplayer_games WHERE id = ? FOR UPDATE');
    $stmt->execute([$game['id']]);
    $game_locked = $stmt->fetch();

    if ($game_locked['ronde'] != $game['ronde'] || $game_locked['status'] !== 'bezig') {
        $pdo->commit(); return;
    }

    $stmt2 = $pdo->prepare('SELECT user_id, correct, ingediend_op FROM multiplayer_antwoorden
                            WHERE game_id = ? AND ronde = ? ORDER BY ingediend_op ASC');
    $stmt2->execute([$game['id'], $game['ronde']]);
    $ants = $stmt2->fetchAll();

    $klaar = false;
    if (count($ants) === 2) {
        $klaar = true;
    } elseif (count($ants) === 1) {
        $verstreken = (new DateTime())->getTimestamp() - (new DateTime($ants[0]['ingediend_op']))->getTimestamp();
        if ($verstreken >= 3) $klaar = true;
    }

    if ($klaar) {
        $s1_delta = 0; $s2_delta = 0;
        $correcten = array_filter($ants, fn($a) => $a['correct']);
        if (!empty($correcten)) {
            $winnaar_ant = reset($correcten);
            if ($winnaar_ant['user_id'] == $game['speler1_id']) $s1_delta = 1;
            else                                                  $s2_delta = 1;
        }
        $nieuwe_ronde = $game['ronde'] + 1;
        if ($nieuwe_ronde > $game['max_rondes']) {
            $pdo->prepare('UPDATE multiplayer_games SET status="klaar", score_speler1=score_speler1+?, score_speler2=score_speler2+? WHERE id=?')
                ->execute([$s1_delta, $s2_delta, $game['id']]);
        } else {
            // Nieuwe ronde: reset scores delta + emoji reacties
            try {
                $pdo->prepare('UPDATE multiplayer_games SET ronde=?, score_speler1=score_speler1+?, score_speler2=score_speler2+?, emoji_speler1=NULL, emoji_speler2=NULL WHERE id=?')
                    ->execute([$nieuwe_ronde, $s1_delta, $s2_delta, $game['id']]);
            } catch (PDOException $e) {
                // emoji kolommen bestaan nog niet – update zonder emoji reset
                $pdo->prepare('UPDATE multiplayer_games SET ronde=?, score_speler1=score_speler1+?, score_speler2=score_speler2+? WHERE id=?')
                    ->execute([$nieuwe_ronde, $s1_delta, $s2_delta, $game['id']]);
            }
        }
    }
    $pdo->commit();
}

// ── Spelstatus samenstellen ───────────────────────────────────────────────────
function spelStatus(PDO $pdo, array $game, int $user_id): array {
    $is1        = ($game['speler1_id'] == $user_id);
    $mijn_score = $is1 ? $game['score_speler1'] : $game['score_speler2'];
    $teg_score  = $is1 ? $game['score_speler2'] : $game['score_speler1'];
    $teg_naam   = $game['teg_naam'] ?? '...';

    if ($game['status'] === 'wachten') {
        // Als speler2 al aanwezig is maar nog niet gestart → terug naar pregame
        $fase = $game['speler2_id'] ? 'pregame' : 'wachten';
        return ['fase' => $fase, 'code' => $game['code']];
    }

    if ($game['status'] === 'klaar') {
        if ($mijn_score > $teg_score)     $winnaar = 'jij';
        elseif ($teg_score > $mijn_score) $winnaar = 'tegenstander';
        else                              $winnaar = 'gelijk';
        return [
            'fase'               => 'klaar',
            'score_jij'          => $mijn_score,
            'score_tegenstander' => $teg_score,
            'tegenstander_naam'  => $teg_naam,
            'winnaar'            => $winnaar,
            'jij_is_speler1'     => $is1,
        ];
    }

    $stmt = $pdo->prepare('SELECT w.woord, w.vertaling FROM multiplayer_woorden mw
                           JOIN woorden w ON mw.woord_id = w.id
                           WHERE mw.game_id = ? AND mw.volgorde = ?');
    $stmt->execute([$game['id'], $game['ronde']]);
    $woord = $stmt->fetch();

    $stmt2 = $pdo->prepare('SELECT user_id, correct, antwoord, ingediend_op
                            FROM multiplayer_antwoorden WHERE game_id = ? AND ronde = ?');
    $stmt2->execute([$game['id'], $game['ronde']]);
    $antwoorden = $stmt2->fetchAll();

    $mijn_ant        = null;
    $teg_ant         = false;
    $teg_antwoord_op = null;
    foreach ($antwoorden as $a) {
        if ($a['user_id'] == $user_id) {
            $mijn_ant = $a;
        } else {
            $teg_ant         = true;
            $teg_antwoord_op = $a['ingediend_op'];
        }
    }

    $modus = $game['modus'] ?? 'invullen';

    $result = [
        'fase'                    => 'bezig',
        'ronde'                   => $game['ronde'],
        'max_rondes'              => $game['max_rondes'],
        'woord'                   => $woord['woord'] ?? '',
        'score_jij'               => $mijn_score,
        'score_tegenstander'      => $teg_score,
        'tegenstander_naam'       => $teg_naam,
        'jij_beantwoord'          => $mijn_ant !== null,
        'jij_correct'             => $mijn_ant ? (bool)$mijn_ant['correct'] : null,
        'jij_antwoord_tekst'      => $mijn_ant ? $mijn_ant['antwoord'] : null,
        'correcte_antwoord'       => $mijn_ant ? $woord['vertaling'] : null,
        'tegenstander_beantwoord' => $teg_ant,
        'tegenstander_antwoord_op'=> $teg_antwoord_op,
        'modus'                   => $modus,
        'jij_is_speler1'          => $is1,
        'emoji_ontvangen'         => $is1 ? ($game['emoji_speler2'] ?? null) : ($game['emoji_speler1'] ?? null),
    ];

    // Meerkeuze: genereer 4 opties deterministisch (zelfde voor beide spelers)
    if ($modus === 'meerkeuze') {
        $alle = $pdo->prepare('SELECT w.vertaling FROM multiplayer_woorden mw
                               JOIN woorden w ON mw.woord_id = w.id WHERE mw.game_id = ?');
        $alle->execute([$game['id']]);
        $alle_vertalingen = array_column($alle->fetchAll(), 'vertaling');

        $foute = array_values(array_filter($alle_vertalingen, fn($v) => $v !== ($woord['vertaling'] ?? '')));
        srand($game['id'] * 1000 + $game['ronde']); // deterministisch: zelfde opties voor beide spelers
        shuffle($foute);
        $foute  = array_slice($foute, 0, 3);
        $opties = array_merge([$woord['vertaling'] ?? ''], $foute);
        shuffle($opties);
        srand();
        $result['opties'] = $opties;
    }

    return $result;
}

// ── Game ophalen ──────────────────────────────────────────────────────────────
function getGame(PDO $pdo, string $code, int $user_id): ?array {
    $stmt = $pdo->prepare('SELECT g.*,
                           u1.username AS speler1_naam,
                           u2.username AS speler2_naam,
                           teg.username AS teg_naam
                           FROM multiplayer_games g
                           LEFT JOIN users u1  ON u1.id = g.speler1_id
                           LEFT JOIN users u2  ON u2.id = g.speler2_id
                           LEFT JOIN users teg ON teg.id = IF(g.speler1_id = ?, g.speler2_id, g.speler1_id)
                           WHERE g.code = ? AND (g.speler1_id = ? OR g.speler2_id = ?)');
    $stmt->execute([$user_id, $code, $user_id, $user_id]);
    return $stmt->fetch() ?: null;
}

// ── STATUS ────────────────────────────────────────────────────────────────────
if ($actie === 'status') {
    $game = getGame($pdo, $code, $user_id);
    if (!$game) { echo json_encode(['fout' => 'Spel niet gevonden']); exit; }
    advanceerRondeAlsKlaar($pdo, $game);
    $game = getGame($pdo, $code, $user_id);
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

    $stmt = $pdo->prepare('SELECT w.woord, w.vertaling FROM multiplayer_woorden mw
                           JOIN woorden w ON mw.woord_id = w.id
                           WHERE mw.game_id = ? AND mw.volgorde = ?');
    $stmt->execute([$game['id'], $game['ronde']]);
    $woord = $stmt->fetch();

    $correct = (int)(strtolower(trim($antwoord)) === strtolower(trim($woord['vertaling']))
                     && strtolower(trim($antwoord)) !== strtolower(trim($woord['woord'])));

    try {
        $pdo->prepare('INSERT IGNORE INTO multiplayer_antwoorden (game_id, ronde, user_id, antwoord, correct, ingediend_op)
                       VALUES (?, ?, ?, ?, ?, NOW())')->execute([$game['id'], $game['ronde'], $user_id, $antwoord, $correct]);
    } catch (Exception $e) {}

    advanceerRondeAlsKlaar($pdo, $game);
    $game = getGame($pdo, $code, $user_id);
    echo json_encode(spelStatus($pdo, $game, $user_id));
    exit;
}

// ── TIMEOUT (speler heeft niet op tijd geantwoord) ───────────────────────────
if ($actie === 'timeout') {
    $game = getGame($pdo, $code, $user_id);
    if (!$game || $game['status'] !== 'bezig') {
        echo json_encode(['fout' => 'Ongeldige actie']); exit;
    }
    // Alleen invoegen als deze speler nog geen antwoord heeft
    $check = $pdo->prepare('SELECT id FROM multiplayer_antwoorden WHERE game_id = ? AND ronde = ? AND user_id = ?');
    $check->execute([$game['id'], $game['ronde'], $user_id]);
    if (!$check->fetch()) {
        $pdo->prepare('INSERT IGNORE INTO multiplayer_antwoorden (game_id, ronde, user_id, antwoord, correct, ingediend_op)
                       VALUES (?, ?, ?, ?, 0, NOW())')->execute([$game['id'], $game['ronde'], $user_id, '']);
    }
    advanceerRondeAlsKlaar($pdo, $game);
    $game = getGame($pdo, $code, $user_id);
    echo json_encode(spelStatus($pdo, $game, $user_id));
    exit;
}

// ── LOBBY STATUS (polling voor pregame) ───────────────────────────────────────
if ($actie === 'lobby_status') {
    $game = getGame($pdo, $code, $user_id);
    if (!$game) { echo json_encode(['fout' => 'Spel niet gevonden']); exit; }

    // Spel is al gestart
    if ($game['status'] === 'bezig' || $game['status'] === 'klaar') {
        echo json_encode(['fase' => 'start']); exit;
    }

    // Fase bepalen: speler2 aanwezig = lobby, anders wachten
    $fase = $game['speler2_id'] ? 'lobby' : 'wachten';

    $berichten = [];
    if ($fase === 'lobby') {
        try {
            $stmt = $pdo->prepare('SELECT u.username, c.bericht, DATE_FORMAT(c.verzonden_op, "%H:%i") AS tijd
                                   FROM multiplayer_chat c JOIN users u ON u.id = c.user_id
                                   WHERE c.game_id = ? ORDER BY c.verzonden_op ASC');
            $stmt->execute([$game['id']]);
            $berichten = $stmt->fetchAll();
        } catch (PDOException $e) {
            // multiplayer_chat tabel bestaat nog niet – zie docs/database.sql voor migratie
            $berichten = [];
        }
    }

    $is1 = ($game['speler1_id'] == $user_id);
    echo json_encode([
        'fase'          => $fase,
        'code'          => $game['code'],
        'speler1_naam'  => $game['speler1_naam'] ?? null,
        'speler2_naam'  => $game['speler2_naam'] ?? null,
        'speler1_klaar' => (bool)($game['speler1_klaar'] ?? false),
        'speler2_klaar' => (bool)($game['speler2_klaar'] ?? false),
        'jij_bent'      => $is1 ? 1 : 2,
        'berichten'     => $berichten,
    ]);
    exit;
}

// ── CHAT BERICHT STUREN ───────────────────────────────────────────────────────
if ($actie === 'chat') {
    $bericht = trim($_POST['bericht'] ?? '');
    $game    = getGame($pdo, $code, $user_id);
    if (!$game || $bericht === '' || mb_strlen($bericht) > 300) {
        echo json_encode(['ok' => false]); exit;
    }
    try {
        $pdo->prepare('INSERT INTO multiplayer_chat (game_id, user_id, bericht) VALUES (?, ?, ?)')
            ->execute([$game['id'], $user_id, $bericht]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'fout' => 'Chat-tabel ontbreekt – run de SQL-migratie.']); exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── KLAAR MELDEN (toggle) ─────────────────────────────────────────────────────
if ($actie === 'klaar') {
    $game = getGame($pdo, $code, $user_id);
    if (!$game || $game['status'] !== 'wachten' || !$game['speler2_id']) {
        echo json_encode(['fout' => 'Ongeldige actie']); exit;
    }

    $is1  = ($game['speler1_id'] == $user_id);
    $veld = $is1 ? 'speler1_klaar' : 'speler2_klaar';
    $huidig = $is1 ? (int)$game['speler1_klaar'] : (int)$game['speler2_klaar'];
    $nieuw  = $huidig ? 0 : 1;

    try {
        $pdo->prepare("UPDATE multiplayer_games SET {$veld} = ? WHERE id = ?")
            ->execute([$nieuw, $game['id']]);
    } catch (PDOException $e) {
        echo json_encode(['fout' => 'Kolommen speler1_klaar/speler2_klaar ontbreken – run de SQL-migratie.']); exit;
    }

    // Als beide klaar: game starten
    $game = getGame($pdo, $code, $user_id);
    if (!empty($game['speler1_klaar']) && !empty($game['speler2_klaar'])) {
        $pdo->prepare("UPDATE multiplayer_games SET status='bezig', speler1_klaar=0, speler2_klaar=0 WHERE id=?")
            ->execute([$game['id']]);
    }

    echo json_encode(['ok' => true]);
    exit;
}

// ── EMOJI REACTIE ─────────────────────────────────────────────────────────────
if ($actie === 'emoji') {
    $emoji = $_POST['emoji'] ?? '';
    if (!in_array($emoji, ['👍','😅','🎉','😤'])) { echo json_encode(['ok'=>false]); exit; }
    $game = getGame($pdo, $code, $user_id);
    if (!$game) { echo json_encode(['ok'=>false]); exit; }
    $veld = ($game['speler1_id'] == $user_id) ? 'emoji_speler1' : 'emoji_speler2';
    try {
        $pdo->prepare("UPDATE multiplayer_games SET {$veld} = ? WHERE id = ?")
            ->execute([$emoji, $game['id']]);
    } catch (PDOException $e) {}
    echo json_encode(['ok' => true]);
    exit;
}

// ── REMATCH ────────────────────────────────────────────────────────────────────
if ($actie === 'rematch') {
    $game = getGame($pdo, $code, $user_id);
    if (!$game || $game['status'] !== 'klaar' || $game['speler1_id'] != $user_id) {
        echo json_encode(['fout' => 'Alleen speler 1 kan een rematch starten na afloop']); exit;
    }
    $modus = $game['modus'] ?? 'invullen';
    $stmt = $pdo->prepare("SELECT id FROM woorden WHERE woordenlijst_id = ? ORDER BY RAND() LIMIT " . (int)$game['max_rondes']);
    $stmt->execute([$game['lijst_id']]);
    $woorden = $stmt->fetchAll();
    if (count($woorden) < $game['max_rondes']) { echo json_encode(['fout' => 'Te weinig woorden']); exit; }

    $nieuwe_code = strtoupper(substr(md5(uniqid()), 0, 6));
    try {
        $pdo->prepare('INSERT INTO multiplayer_games (code, speler1_id, lijst_id, max_rondes, modus) VALUES (?,?,?,?,?)')
            ->execute([$nieuwe_code, $user_id, $game['lijst_id'], $game['max_rondes'], $modus]);
        $new_id = $pdo->lastInsertId();
        $wstmt = $pdo->prepare('INSERT INTO multiplayer_woorden (game_id, volgorde, woord_id) VALUES (?,?,?)');
        foreach ($woorden as $i => $w) { $wstmt->execute([$new_id, $i + 1, $w['id']]); }
    } catch (PDOException $e) {
        echo json_encode(['fout' => 'Kon rematch niet aanmaken']); exit;
    }
    echo json_encode(['code' => $nieuwe_code]);
    exit;
}

echo json_encode(['fout' => 'Onbekende actie']);
