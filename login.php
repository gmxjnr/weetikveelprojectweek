<?php
require_once 'modules/User.php';
require_once 'modules/Logs.php';
require_once 'db.php';

$user = new User($pdo);
$logs = new Logs($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $loggedInUser = $user->login($_POST['usernameOrEmail'], $_POST['password']);
        if ($loggedInUser) {
            $_SESSION['user_id'] = $loggedInUser['id'];
            $logs->logAction($_SESSION['user_id'], 'login', 'User logged in successfully.');
            header('Location: upload.php');
            exit;
        } else {
            echo "Ongeldige gebruikersnaam/e-mail of wachtwoord.";
        }
    } catch (RuntimeException $e) {
        echo $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weet Ik Veel — Inloggen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- ambient background -->
    <div class="aurora" aria-hidden="true">
        <span class="blob blob-a"></span>
        <span class="blob blob-b"></span>
        <span class="blob blob-c"></span>
    </div>

    <!-- pure-CSS toggle drives the slide -->
    <input type="checkbox" id="toggle" class="toggle">

    <div class="card">

        <!-- Sign In -->
        <div class="pane pane--signin">
            <form action="login.php" method="post" class="form">
                <span class="eyebrow">Welkom terug</span>
                <h1>Log in</h1>

                <label class="field">
                    <span class="field__label">Gebruikersnaam of e-mail</span>
                    <input type="text" name="usernameOrEmail" autocomplete="username" required>
                </label>

                <label class="field">
                    <span class="field__label">Wachtwoord</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>

                <a href="#" class="link-muted">Wachtwoord vergeten?</a>

                <button type="submit" class="btn">Inloggen</button>

                <p class="swap-line">Nog geen account? <label for="toggle">Maak er een aan</label></p>
            </form>
        </div>

        <!-- Sign Up -->
        <div class="pane pane--signup">
            <form action="register.php" method="post" class="form">
                <span class="eyebrow">Nieuw hier</span>
                <h1>Account aanmaken</h1>

                <label class="field">
                    <span class="field__label">Gebruikersnaam</span>
                    <input type="text" name="username" autocomplete="username" required>
                </label>

                <label class="field">
                    <span class="field__label">E-mailadres</span>
                    <input type="email" name="email" autocomplete="email" required>
                </label>

                <label class="field">
                    <span class="field__label">Wachtwoord</span>
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>

                <button type="submit" class="btn">Registreren</button>

                <p class="swap-line">Heb je al een account? <label for="toggle">Log in</label></p>
            </form>
        </div>

        <!-- sliding feature panel -->
        <div class="cover">
            <div class="cover__inner">
                <div class="cover__face cover__face--toSignup">
                    <div class="brand">
                        <span class="brand__mark">?!</span>
                        <span class="brand__name">Weet Ik Veel</span>
                    </div>
                    <h2>Weet ik<br>veel</h2>
                    <p>Maak een account en weet ik veel.</p>
                    <label for="toggle" class="btn btn--ghost">Registreren</label>
                </div>

                <div class="cover__face cover__face--toSignin">
                    <div class="brand">
                        <span class="brand__mark">?!</span>
                        <span class="brand__name">Weet Ik Veel</span>
                    </div>
                    <h2>Goed je weer<br>te zien.</h2>
                    <p>Log in en ga verder waar je gebleven was. Je voortgang staat klaar.</p>
                    <label for="toggle" class="btn btn--ghost">Inloggen</label>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
