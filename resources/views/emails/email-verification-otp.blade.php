@component('mail::message')
# Verify your email

Hi {{ $fullName }},

Use the code below to verify your ResQPulse account:

@component('mail::panel')
# {{ $otp }}
@endcomponent

This code expires in 10 minutes. If you didn't create this account, you can ignore this email.

Thanks,
MDRRMO Rosales
@endcomponent