<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$username || !$password || !$password2) {
        $error = 'Vul alle velden in.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Gebruikersnaam moet tussen 3 en 50 tekens zijn.';
    } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
        $error = 'Gebruikersnaam mag alleen letters, cijfers, - en _ bevatten.';
    } elseif (strlen($password) < 6) {
        $error = 'Wachtwoord moet minimaal 6 tekens zijn.';
    } elseif ($password !== $password2) {
        $error = 'Wachtwoorden komen niet overeen.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Deze gebruikersnaam is al bezet.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, "student")')
                ->execute([$username, $hash]);

            $user_id = $pdo->lastInsertId();
            $_SESSION['user_id']  = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role']     = 'student';
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registreren – Taaltrainer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>:root { --bs-font-sans-serif: 'Inter', system-ui, sans-serif; }</style>
    <style>
        body {
            background-color: #F3F4F6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .login-logo {
            font-size: 2rem;
            font-weight: 700;
            color: #2563EB;
            text-align: center;
            margin-bottom: 0.25rem;
        }
        .login-subtitle {
            text-align: center;
            color: #6B7280;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .btn-primary { background-color: #2563EB; border-color: #2563EB; }
        .btn-primary:hover { background-color: #1D4ED8; border-color: #1D4ED8; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">Taaltrainer</div>
        <div class="login-subtitle">Maak een account aan</div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold">Gebruikersnaam</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autofocus required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">Wachtwoord</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <div class="form-text">Minimaal 6 tekens.</div>
            </div>
            <div class="mb-4">
                <label for="password2" class="form-label fw-semibold">Wachtwoord herhalen</label>
                <input type="password" class="form-control" id="password2" name="password2" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Account aanmaken</button>
        </form>

        <hr class="my-3">
        <p class="text-center text-muted small mb-0">
            Al een account? <a href="login.php">Inloggen</a>
        </p>
    </div>
</body>
</html>
