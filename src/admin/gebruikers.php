<?php
require_once 'auth.php';
require_once '../config.php';

$succes = '';
$fout   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie   = $_POST['actie'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // Mag niet zichzelf wijzigen
    if ($user_id === (int)$_SESSION['user_id']) {
        $fout = 'Je kunt je eigen rol niet wijzigen.';
    } elseif ($actie === 'maak_docent') {
        $pdo->prepare('UPDATE users SET role = "docent" WHERE id = ?')->execute([$user_id]);
        $succes = 'Gebruiker is nu beheerder.';
    } elseif ($actie === 'maak_student') {
        $pdo->prepare('UPDATE users SET role = "student" WHERE id = ?')->execute([$user_id]);
        $succes = 'Gebruiker is nu student.';
    } elseif ($actie === 'verwijderen') {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
        $succes = 'Gebruiker verwijderd.';
    }
}

$gebruikers = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY role ASC, username ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gebruikers – Taaltrainer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>:root { --bs-font-sans-serif: 'Inter', system-ui, sans-serif; }</style>
    <style>
        body { background-color: #F3F4F6; }
        .navbar { background-color: #2563EB; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .badge-docent  { background-color: #EFF6FF; color: #2563EB; }
        .badge-student { background-color: #F3F4F6; color: #6B7280; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark px-4 py-3 mb-4">
        <span class="navbar-brand fw-bold">Taaltrainer – Beheer</span>
        <div class="d-flex gap-2">
            <a href="../index.php" class="btn btn-sm btn-outline-light">← App</a>
            <a href="index.php" class="btn btn-sm btn-outline-light">Woordenlijsten</a>
            <a href="scores.php" class="btn btn-sm btn-outline-light">Scores</a>
            <a href="../logout.php" class="btn btn-sm btn-outline-light">Uitloggen</a>
        </div>
    </nav>

    <div class="container pb-5" style="max-width:800px">

        <?php if ($succes): ?>
            <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
        <?php endif; ?>
        <?php if ($fout): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fout) ?></div>
        <?php endif; ?>

        <div class="card p-4">
            <h5 class="fw-bold mb-3">Gebruikers</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Gebruikersnaam</th>
                            <th>Rol</th>
                            <th>Geregistreerd</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gebruikers as $g): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars($g['username']) ?>
                                    <?php if ($g['id'] == $_SESSION['user_id']): ?>
                                        <span class="text-muted small">(jij)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $g['role'] === 'docent' ? 'badge-docent' : 'badge-student' ?> fw-semibold">
                                        <?= $g['role'] === 'docent' ? 'Beheerder' : 'Student' ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= date('d-m-Y', strtotime($g['created_at'])) ?></td>
                                <td class="text-end">
                                    <?php if ($g['id'] != $_SESSION['user_id']): ?>
                                        <div class="d-flex gap-1 justify-content-end">
                                            <?php if ($g['role'] === 'student'): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="actie" value="maak_docent">
                                                    <input type="hidden" name="user_id" value="<?= $g['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        Beheerder maken
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST">
                                                    <input type="hidden" name="actie" value="maak_student">
                                                    <input type="hidden" name="user_id" value="<?= $g['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                        Terugzetten naar student
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('Gebruiker <?= htmlspecialchars(addslashes($g['username'])) ?> verwijderen?')">
                                                <input type="hidden" name="actie" value="verwijderen">
                                                <input type="hidden" name="user_id" value="<?= $g['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Verwijderen</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
