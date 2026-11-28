<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mawjood · Customer signup</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('mawjood-tab.png') }}">
    @viteReactRefresh
    @vite('resources/js/admin/CustomerSignup.jsx')
</head>
<body>
    <div id="mawjood-signup" data-api-base="{{ url('/api/v1') }}"
        data-csrf-url="{{ url('/sanctum/csrf-cookie') }}"></div>
</body>
</html>
