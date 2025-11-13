ReWear - {{ $type === 'registration' ? 'Registration' : 'Login' }} Verification Code

@if($type === 'registration')
Welcome to ReWear!

Thank you for joining ReWear, the sustainable fashion marketplace!
@else
Login to ReWear

To login to your ReWear account, please use the verification code below.
@endif

===================================
YOUR VERIFICATION CODE: {{ $code }}
===================================

⏰ This code will expire in {{ $expiresInMinutes }} minutes.

SECURITY NOTICE:
• Never share this code with anyone
• ReWear staff will never ask for your verification code
• If you didn't request this code, please ignore this email

@if($type === 'registration')
Once verified, you'll be able to:
✓ Buy and sell sustainable fashion items
✓ Donate clothing to charities
✓ Track your environmental impact
✓ Support the circular fashion economy
@endif

---

Need help? Contact us at support@rewear.com

© {{ date('Y') }} ReWear. All rights reserved.
Making fashion sustainable and accessible.

This email was sent to you as part of your ReWear account {{ $type === 'registration' ? 'registration' : 'login' }}.
