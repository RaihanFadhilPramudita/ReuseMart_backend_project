<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #4CAF50;
        }
        .btn {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .btn-container {
            margin: 30px 0;
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
        .url-display {
            font-size: 12px;
            color: #777;
            word-break: break-all;
        }
        hr {
            border: none;
            border-top: 1px solid #eee;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ReuseMart</h1>
            <h2>Reset Password</h2>
        </div>
        
        <p>Halo {{ $userName }},</p>
        
        <p>Anda menerima email ini karena kami menerima permintaan reset password untuk akun {{ $userType }} Anda di ReuseMart.</p>
        
        <div class="btn-container">
            <a href="{{ $url }}" class="btn">Reset Password</a>
        </div>
        
        <p>Link reset password ini akan kadaluarsa dalam {{ $expireTime }} menit.</p>
        
        <p>Jika Anda tidak meminta reset password, tidak ada tindakan yang perlu dilakukan.</p>
        
        <hr>
        
        <p class="url-display">Jika Anda mengalami kesulitan mengklik tombol "Reset Password", salin dan tempel URL di bawah ini ke browser web Anda: <br> {{ $url }}</p>
        
        <p class="footer">
            &copy; {{ date('Y') }} ReuseMart. All rights reserved.
        </p>
    </div>
</body>
</html>