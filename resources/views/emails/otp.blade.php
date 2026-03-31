<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OTP Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 500px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333333;
        }
        .otp-box {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 10px;
            color: #4CAF50;
            text-align: center;
            padding: 20px;
            background: #f0fff0;
            border-radius: 8px;
            margin: 20px 0;
        }
        p {
            color: #666666;
            line-height: 1.6;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #999999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Password Reset OTP</h2>
        <p>Hello,</p>
        <p>Aapne password reset request ki hai. Neeche diya gaya OTP use karein:</p>

        <div class="otp-box">{{ $otp }}</div>

        <p>Yeh OTP <strong>10 minutes</strong> ke liye valid hai.</p>
        <p>Agar aapne yeh request nahi ki, toh is email ko ignore karein.</p>

        <div class="footer">
            &copy; {{ date('Y') }} Your App Name. All rights reserved.
        </div>
    </div>
</body>
</html>
