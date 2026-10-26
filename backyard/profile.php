<?php
declare(strict_types=1);

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/backyard_private/app/bootstrap.php';

$u = requireUser();
$error = '';
$success = '';

$globalApproved = cfg('TEAM_HR_SHARING_APPROVED', '0') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();

        $action = (string)($_POST['action'] ?? '');

        if ($action === 'hf_consent') {
            $consent = isset($_POST['share_hr']) ? 1 : 0;

            if ($consent === 1 && !$globalApproved) {
                throw new RuntimeException(
                    'Die allgemeine HF-Freigabe ist noch nicht aktiviert.'
                );
            }

            $stmt = db()->prepare(
                'UPDATE users SET share_team_hr = ? WHERE id = ?'
            );
            $stmt->execute([$consent, (int)$u['id']]);

            $success = $consent
                ? 'Deine Herzfrequenz ist jetzt für den Teamvergleich freigegeben.'
                : 'Deine Herzfrequenz wird nicht mehr im Teamvergleich geteilt.';

        } elseif ($action === 'password') {
            $old = (string)($_POST['old_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            if (strlen($new) < 12 || strlen($new) > 1024) {
                throw new RuntimeException(
                    'Das neue Passwort muss mindestens 12 Zeichen lang sein.'
                );
            }

            if (!hash_equals($new, $confirm)) {
                throw new RuntimeException(
                    'Die neuen Passwörter stimmen nicht überein.'
                );
            }

            if (hash_equals($old, $new)) {
                throw new RuntimeException(
                    'Bitte ein anderes Passwort verwenden.'
                );
            }

            $stmt = db()->prepare(
                'SELECT password_hash FROM users WHERE id = ?'
            );
            $stmt->execute([(int)$u['id']]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($old, $row['password_hash'])) {
                throw new RuntimeException(
                    'Das bisherige Passwort ist falsch.'
                );
            }

            $hash = password_hash($new, PASSWORD_DEFAULT);

            db()->beginTransaction();

            try {
                $stmt = db()->prepare(
                    'UPDATE users SET password_hash = ? WHERE id = ?'
                );
                $stmt->execute([$hash, (int)$u['id']]);

                $stmt = db()->prepare(
                    'DELETE FROM remember_tokens WHERE user_id = ?'
                );
                $stmt->execute([(int)$u['id']]);

                db()->commit();
            } catch (Throwable $e) {
                db()->rollBack();
                throw $e;
            }

            forgetRememberCookie();
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(24));

            $success = 'Passwort geändert. Dauerhafte Anmeldungen wurden widerrufen.';

        } else {
            throw new RuntimeException('Ungültige Aktion.');
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stmt = db()->prepare(
    'SELECT share_team_hr FROM users WHERE id = ?'
);
$stmt->execute([(int)$u['id']]);

$sharing = (int)$stmt->fetchColumn() === 1;
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>LLU 27 · Mein Profil</title>

    <link rel="stylesheet" href="assets/style.css?v=profile-4">
    <link rel="icon" href="/assets/favicon-32-v4.png">
    <link rel="apple-touch-icon" href="/assets/apple-touch-icon-v4.png">
    <link rel="manifest" href="/manifest-v4.webmanifest">

    <meta name="theme-color" content="#101923">

    <style>
        .profile-wrap {
            max-width: 680px;
            margin: 36px auto;
        }

        .profile-card {
            background: linear-gradient(145deg, #1a283a, #152131);
            border: 1px solid #344558;
            border-radius: 20px;
            padding: clamp(20px, 4vw, 34px);
        }

        .profile-card h1 {
            margin: 5px 0 8px;
            font-size: clamp(30px, 5vw, 42px);
        }

        .profile-card .eyebrow {
            color: #fc4c02;
        }

        .profile-back {
            color: #ff875d;
            text-decoration: none;
        }

        .sharing-card {
            margin: 26px 0;
            padding: 25px;
            border: 1px solid #405066;
            border-radius: 16px;
            background: #101c2b;
        }

        .sharing-card h2 {
            font-size: 24px;
            margin: 14px 0;
        }

        .sharing-note {
            color: #acbbc9;
            font-size: 14px;
            line-height: 1.7;
        }

        .hf-form {
            display: block !important;
            margin-top: 24px;
        }

        /* Wichtig: kein allgemeines 100%-Input-Layout */
        .profile-card .hf-form .hf-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            width: 100%;
            cursor: pointer;
            padding: 16px;
            background: #1c2b3d;
            border: 1px solid #405066;
            border-radius: 12px;
        }

        .hf-toggle-text {
            display: block;
            flex: 1;
            min-width: 0;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
        }

        .profile-card .hf-toggle input[type="checkbox"] {
            appearance: auto;
            display: block;
            flex: 0 0 23px;
            width: 23px !important;
            height: 23px !important;
            min-width: 23px;
            margin: 0 !important;
            padding: 0 !important;
            accent-color: #fc4c02;
            cursor: pointer;
        }

        .hf-toggle input:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .hf-save {
            display: block;
            width: 100%;
            margin-top: 16px;
            padding: 14px;
            font-size: 14px;
        }

        .hf-status {
            margin: 18px 0 0;
            font-size: 13px;
            font-weight: 700;
        }

        .hf-status.active {
            color: #8be4bc;
        }

        .hf-status.inactive {
            color: #ffb38f;
        }

        .password-form {
            display: grid;
            gap: 17px;
            margin-top: 22px;
        }

        .password-form label {
            display: block;
            font-size: 13px;
            color: #b9c8d7;
        }

        .password-form input {
            display: block;
            width: 100%;
            margin-top: 7px;
        }

        .password-form button {
            margin-top: 7px;
        }

        .section-title {
            margin-top: 32px;
            font-size: 22px;
        }

        .profile-card .note {
            margin-top: 15px;
            font-size: 12px;
        }

        @media (max-width: 550px) {
            .sharing-card {
                padding: 18px;
            }

            .hf-toggle-text {
                font-size: 13px;
            }
        }
    </style>
<link rel="stylesheet" href="assets/llu-navigation.css?v=2"><script src="assets/llu-navigation.js?v=2" defer></script></head>

<body class="llu-layout">
<?php require __DIR__ . "/partials/navigation.php"; ?>

<div class="shell">

    <header class="top">
        <div class="brand">
            LLU <em>27</em>
            <span class="brand-caption">/ BACKYARD RUNNING</span>
        </div>

        <a class="btn outline" href="dashboard.php">
            ← Dashboard
        </a>
    </header>

    <main class="profile-wrap">
        <section class="profile-card">

            <p class="eyebrow">LLU 27 · MEIN PROFIL</p>
            <h1>Dein Profil.</h1>
            <p class="muted">
                <?= h($u['display_name']) ?> · Einstellungen verwalten
            </p>

            <?php if ($error): ?>
                <p class="error" role="alert">
                    <?= h($error) ?>
                </p>
            <?php endif; ?>

            <?php if ($success): ?>
                <p class="notice" role="status">
                    <?= h($success) ?>
                </p>
            <?php endif; ?>

            <section class="sharing-card">
                <p class="eyebrow">LLU 27 · TEAMFREIGABE</p>

                <h2>Herzfrequenz teilen</h2>

                <p class="sharing-note">
                    Du entscheidest selbst, ob Tim, Jonas und Noah
                    deine durchschnittliche Herzfrequenz pro
                    Kalenderwoche vergleichen dürfen.
                    Deine einzelnen Aktivitäten bleiben privat.
                </p>

                <form method="post" class="hf-form">
                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= h(csrf()) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="hf_consent"
                    >

                    <label class="hf-toggle">
                        <span class="hf-toggle-text">
                            Meine HF-Wochenwerte im Team teilen
                        </span>

                        <input
                            type="checkbox"
                            name="share_hr"
                            value="1"
                            <?= $sharing ? 'checked' : '' ?>
                            <?= !$globalApproved ? 'disabled' : '' ?>
                        >
                    </label>

                    <p class="hf-status <?= $sharing && $globalApproved ? 'active' : 'inactive' ?>">
                        <?= $sharing && $globalApproved
                            ? '● Freigabe aktiv'
                            : '○ Freigabe nicht aktiv'
                        ?>
                    </p>

                    <?php if (!$globalApproved): ?>
                        <p class="sharing-note">
                            Die allgemeine HF-Freigabe ist in der
                            privaten Serverkonfiguration noch nicht aktiv.
                        </p>
                    <?php endif; ?>

                    <button class="btn hf-save" type="submit">
                        Freigabe speichern
                    </button>
                </form>
            </section>

            <h2 class="section-title">Passwort ändern</h2>

            <p class="muted">
                Schütze dein LLU-27-Konto mit einem sicheren Passwort.
            </p>

            <form method="post" class="password-form">
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= h(csrf()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="password"
                >

                <label>
                    Aktuelles Passwort
                    <input
                        name="old_password"
                        type="password"
                        autocomplete="current-password"
                        required
                    >
                </label>

                <label>
                    Neues Passwort (mindestens 12 Zeichen)
                    <input
                        name="new_password"
                        type="password"
                        autocomplete="new-password"
                        minlength="12"
                        required
                    >
                </label>

                <label>
                    Neues Passwort wiederholen
                    <input
                        name="confirm_password"
                        type="password"
                        autocomplete="new-password"
                        required
                    >
                </label>

                <button class="btn" type="submit">
                    Passwort ändern →
                </button>
            </form>

            <p class="note">
                Nach einer Passwortänderung werden gespeicherte
                30-Tage-Anmeldungen widerrufen.
            </p>

        </section>
    </main>

</div>
</body>
</html>
