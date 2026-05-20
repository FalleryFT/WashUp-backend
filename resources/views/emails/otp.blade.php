<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .card { background: white; border-radius: 10px; padding: 30px; max-width: 400px; margin: auto; }
        .otp-code { font-size: 36px; font-weight: bold; color: #1a3d5c; letter-spacing: 8px; text-align: center; margin: 20px 0; }
        .footer { color: #999; font-size: 12px; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="color: #1a3d5c;">Reset Password</h2>
        <p>Gunakan kode OTP berikut untuk mereset password Anda:</p>

        <div class="otp-code">{{ $otp }}</div>

        <p>Kode ini berlaku selama <strong>5 menit</strong>.</p>
        <p>Jika Anda tidak merasa meminta reset password, abaikan email ini.</p>

        <div class="footer">Email ini dikirim otomatis, jangan dibalas.</div>
    </div>
</body>
</html>