<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akun Disuspend</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
        }
        .isolir-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .icon-lock {
            font-size: 80px;
            color: #e74c3c;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
        }
        h1 { font-size: 24px; font-weight: 700; margin-bottom: 10px; }
        .subtitle { color: rgba(255,255,255,0.6); font-size: 14px; margin-bottom: 30px; }
        .info-box {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid rgba(231, 76, 60, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .info-box p { margin: 8px 0; font-size: 14px; color: rgba(255,255,255,0.9); }
        .info-box strong { color: #e74c3c; }
        .steps {
            text-align: left;
            margin: 20px 0;
        }
        .steps li {
            padding: 8px 0;
            font-size: 14px;
            color: rgba(255,255,255,0.8);
            list-style: none;
        }
        .steps li::before {
            content: "\f058";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #2ecc71;
            margin-right: 10px;
        }
        .btn-bayar {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
            padding: 12px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-bayar:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 204, 113, 0.4);
        }
        .contact {
            margin-top: 20px;
            font-size: 13px;
            color: rgba(255,255,255,0.5);
        }
        .contact a { color: #3498db; text-decoration: none; }
    </style>
</head>
<body>
    <div class="isolir-card">
        <div class="icon-lock">
            <i class="fas fa-lock"></i>
        </div>
        <h1>Akun Anda Disuspend</h1>
        <p class="subtitle">Layanan internet Anda sementara dinonaktifkan</p>

        <div class="info-box">
            <p><i class="fas fa-exclamation-triangle"></i> <strong>Anda memiliki tagihan yang belum dibayar</strong></p>
            <p>Silakan lakukan pembayaran untuk mengaktifkan kembali layanan Anda.</p>
        </div>

        <ol class="steps">
            <li>Hubungi admin untuk mengetahui jumlah tagihan</li>
            <li>Lakukan pembayaran sesuai tagihan</li>
            <li>Layanan akan aktif otomatis setelah pembayaran</li>
        </ol>

        <button class="btn-bayar" onclick="window.location.reload()">
            <i class="fas fa-sync-alt"></i> Muat Ulang
        </button>

        <p class="contact">
            Hubungi Admin: <a href="https://wa.me/6281234567890">0812-3456-7890</a>
        </p>
    </div>
</body>
</html>
