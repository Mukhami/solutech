@component('mail::message')
# Reset Successful

Dear {{ $data['fname'] }},

Your password has been successfully reset.

Kind regards,<br>
{{ config('app.name') }}
@endcomponent
