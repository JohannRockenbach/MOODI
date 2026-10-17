<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Pago por QR · MOODI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0b0e15;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            padding: 1.5rem;
        }

        .qr-card {
            max-width: 28rem;
            width: 100%;
            background: #1c2026;
            border: 1px solid #272a31;
            border-radius: 1.5rem;
            padding: 3rem 2.5rem;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
        }

        .qr-icon {
            width: 5rem;
            height: 5rem;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            background: rgba(251, 188, 72, 0.14);
            border-radius: 1.25rem;
        }

        h1 {
            color: #e0e2ec;
            font-size: 1.5rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        p {
            margin-top: 1rem;
            color: #c1c6d5;
            font-size: 1.05rem;
            line-height: 1.6;
        }

        p.muted {
            color: rgba(193, 198, 213, 0.65);
            font-size: 0.95rem;
        }

        .badge {
            display: inline-block;
            margin-top: 1.75rem;
            padding: 0.5rem 1.25rem;
            border-radius: 9999px;
            background: rgba(251, 188, 72, 0.15);
            color: #fbbc48;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
    </style>
</head>
<body>
    <div class="qr-card">
        <div class="qr-icon">📱</div>
        <h1>Pago por QR</h1>
        <p>El pago por código QR estará disponible en una <strong>futura actualización</strong>.</p>
        <p class="muted">Este código aún no procesa pagos. Gracias por tu paciencia.</p>
        <span class="badge">Próximamente</span>
    </div>
</body>
</html>