<x-mail::message>
# Verify Your Email Address

Welcome! Please verify your email address to complete your account setup on [Loops]({{ config('app.url') }}).

## <center>Your Verification Code</center>

<div style="text-align: center; margin: 0 0 30px 0;">
    <div style="display: inline-block; font-family: 'Courier New', monospace; font-size: 0;">
        @php
            $digits = str_split($verify->verify_code);
        @endphp
        @foreach($digits as $digit)
            <span style="
                display: inline-block;
                width: 45px;
                height: 55px;
                line-height: 55px;
                margin: 0 4px;
                font-size: 24px;
                font-weight: bold;
                text-align: center;
                background-color: #f8f9fa;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                color: #495057;
                letter-spacing: 0;
            ">{{ $digit }}</span>
        @endforeach
    </div>
</div>

<!-- Hidden plan text version for Apple AutoFill -->
<p style="font-size: 1px; line-height: 1px; color:transparent; mso-hide: all;" aria-hidden="true">
    {{ implode('', $digits) }}
</p>

**Having trouble?**
- Make sure you're using the latest verification code
- Check your spam folder if you don't see this email
- Contact support if you continue having issues

If you didn't create an account, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
