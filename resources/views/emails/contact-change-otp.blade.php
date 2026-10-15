@component('mail::message')
@if($kind === 'mobile')
# Confirm your new mobile number

Hi {{ $fullName }},

Enter this code in the ResQPulse app to change your mobile number to **{{ $newNumber }}**. We sent it to your email because text messages aren't available right now.
@else
# Confirm your new email

Hi {{ $fullName }},

Enter this code in the ResQPulse app to confirm this email for your account.
@endif

@component('mail::panel')
# {{ $otp }}
@endcomponent

This code expires in 10 minutes. If you didn't ask for this, you can ignore this email — nothing on your account will change.

Thanks,
MDRRMO Rosales
@endcomponent
