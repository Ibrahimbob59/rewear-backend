<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #10b981;
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .credentials-box {
            background-color: white;
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .credential-item {
            margin: 15px 0;
        }
        .credential-label {
            font-weight: bold;
            color: #6b7280;
            font-size: 14px;
        }
        .credential-value {
            font-size: 16px;
            color: #111827;
            font-weight: 600;
            margin-top: 5px;
        }
        .warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #6b7280;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background-color: #10b981;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to ReWear!</h1>
        <p style="margin: 10px 0 0 0; font-size: 18px;">Your Charity Account is Ready</p>
    </div>

    <div class="content">
        <p>Dear <strong>{{ $organizationName }}</strong>,</p>

        <p>Welcome to ReWear - the sustainable fashion marketplace! We're excited to have you join our mission to reduce textile waste and support communities in need.</p>

        <p>Your charity account has been created by our admin team. Below are your login credentials:</p>

        <div class="credentials-box">
            <div class="credential-item">
                <div class="credential-label">EMAIL ADDRESS</div>
                <div class="credential-value">{{ $email }}</div>
            </div>

            <div class="credential-item">
                <div class="credential-label">TEMPORARY PASSWORD</div>
                <div class="credential-value">{{ $password }}</div>
            </div>
        </div>

        <div class="warning">
            <strong>⚠️ Important Security Notice:</strong><br>
            Please change your password immediately after your first login for security purposes. Keep your credentials secure and do not share them with unauthorized individuals.
        </div>

        <h3>What You Can Do:</h3>
        <ul>
            <li>View donated items from community members</li>
            <li>Accept or decline donations based on your needs</li>
            <li>Track deliveries to your organization</li>
            <li>Mark items as distributed to track your impact</li>
            <li>View your organization's impact statistics</li>
        </ul>

        <h3>Getting Started:</h3>
        <ol>
            <li>Download the ReWear mobile app from the App Store or Google Play</li>
            <li>Log in using the credentials above</li>
            <li>Change your password in Settings</li>
            <li>Complete your organization profile</li>
            <li>Start accepting donations!</li>
        </ol>

        <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>

        <p>Thank you for partnering with ReWear to make fashion more sustainable and accessible!</p>

        <p style="margin-top: 30px;">
            <strong>Best regards,</strong><br>
            The ReWear Team
        </p>
    </div>

    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} ReWear. Making fashion sustainable and accessible.</p>
    </div>
</body>
</html>
