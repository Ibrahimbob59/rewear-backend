<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReWear Verification Code</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
        }

        .logo {
            color: #ffffff;
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .tagline {
            color: #ffffff;
            font-size: 14px;
            opacity: 0.9;
        }

        .email-body {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 24px;
            font-weight: 600;
            color: #333333;
            margin-bottom: 20px;
        }

        .message {
            font-size: 16px;
            color: #666666;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .code-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }

        .code-label {
            color: #ffffff;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .code {
            font-size: 42px;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }

        .expiry-info {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .expiry-info p {
            font-size: 14px;
            color: #856404;
            margin: 0;
        }

        .security-notice {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }

        .security-notice h3 {
            font-size: 16px;
            color: #333333;
            margin-bottom: 10px;
        }

        .security-notice p {
            font-size: 14px;
            color: #666666;
            margin-bottom: 8px;
        }

        .email-footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .footer-text {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .social-links {
            margin: 20px 0;
        }

        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .copyright {
            font-size: 12px;
            color: #999999;
            margin-top: 20px;
        }

        @media only screen and (max-width: 600px) {
            .email-body {
                padding: 20px;
            }

            .code {
                font-size: 32px;
                letter-spacing: 4px;
            }

            .greeting {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="logo">ReWear</div>
            <div class="tagline">Buy, Sell, Donate</div>
        </div>

        <!-- Body -->
        <div class="email-body">
            <h1 class="greeting">
                @if($type === 'registration')
                    Welcome to ReWear!
                @else
                    Login to ReWear
                @endif
            </h1>

            <p class="message">
                @if($type === 'registration')
                    Thank you for joining ReWear, the sustainable fashion marketplace! To complete your registration, please use the verification code below:
                @else
                    To login to your ReWear account, please use the verification code below:
                @endif
            </p>

            <!-- Verification Code -->
            <div class="code-container">
                <div class="code-label">Your Verification Code</div>
                <div class="code">{{ $code }}</div>
            </div>

            <!-- Expiry Info -->
            <div class="expiry-info">
                <p>⏰ This code will expire in <strong>{{ $expiresInMinutes }} minutes</strong>. Please use it soon!</p>
            </div>

            <!-- Security Notice -->
            <div class="security-notice">
                <h3>🔒 Security Notice</h3>
                <p>• Never share this code with anyone.</p>
                <p>• ReWear staff will never ask for your verification code.</p>
                <p>• If you didn't request this code, please ignore this email or contact our support team.</p>
            </div>

            <p class="message">
                @if($type === 'registration')
                    Once verified, you'll be able to:
                    <br>
                    ✓ Buy and sell sustainable fashion items
                    <br>
                    ✓ Donate clothing to charities
                    <br>
                    ✓ Track your environmental impact
                    <br>
                    ✓ Support the circular fashion economy
                @else
                    After entering this code, you'll be logged into your account securely.
                @endif
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p class="footer-text">
                Need help? Contact us at <a href="mailto:support@rewear.com" style="color: #667eea;">support@rewear.com</a>
            </p>

            <div class="social-links">
                <a href="#">Instagram</a> |
                <a href="#">Facebook</a> |
                <a href="#">Twitter</a>
            </div>

            <p class="copyright">
                © {{ date('Y') }} ReWear. All rights reserved.
                <br>
                Making fashion sustainable and accessible.
            </p>

            <p style="font-size: 11px; color: #999; margin-top: 15px;">
                This email was sent to you as part of your ReWear account {{ $type === 'registration' ? 'registration' : 'login' }}.
                <br>
                Lebanon
            </p>
        </div>
    </div>
</body>
</html>
