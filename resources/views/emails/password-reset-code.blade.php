@component('mail::message')
# Reset Token

Dear {{ $data['fname'] }},

Your password reset token is **{{ $data['token'] }}**.

Kind regards,<br>
{{ config('app.name') }}
@endcomponent
