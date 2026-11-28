<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Mawjood · Administrator password recovery</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('mawjood-tab.png') }}">
    @viteReactRefresh
    @vite('resources/js/admin/PasswordRecovery.jsx')
</head>
<body>
    <div id="mawjood-admin-recovery" data-mode="{{ $mode }}"
        data-api-base="{{ url('/api/v1') }}" data-csrf-url="{{ url('/sanctum/csrf-cookie') }}"
        data-sign-in-url="{{ route('admin.pharmacies', absolute: false) }}"
        data-request-url="{{ route('admin.password.request', absolute: false) }}"></div>
</body>
</html>
