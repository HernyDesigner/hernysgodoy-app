<?php
Auth::startSession();
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Turnero HG</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f5;
            color: #111827;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 380px;
            background: #ffffff;
            padding: 32px;
            border-radius: 18px;
            box-shadow: 0 20px 45px rgba(0,0,0,0.08);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 26px;
        }

        p {
            margin: 0 0 24px;
            color: #6b7280;
            font-size: 14px;
        }

        label {
            display: block;
            font-size: 14px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input {
            width: 100%;
            height: 44px;
            padding: 0 12px;
            margin-bottom: 16px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 15px;
        }

        button {
            width: 100%;
            height: 46px;
            border: none;
            border-radius: 10px;
            background: #111111;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <h1>Turnero HG</h1>
    <p>Ingresá al panel de administración.</p>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= APP_URL ?>/login">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" required>

        <label for="password">Contraseña</label>
        <input type="password" name="password" id="password" required>

        <button type="submit">Ingresar</button>
    </form>
</div>

</body>
</html>