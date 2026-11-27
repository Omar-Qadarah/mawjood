<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mawjood · Administration</title>
    @viteReactRefresh
    @vite('resources/js/admin/main.jsx')
</head>
<body>
    <div id="mawjood-admin"
        data-api-base="{{ url('/api/v1') }}"
        data-csrf-url="{{ url('/sanctum/csrf-cookie') }}"
        data-places="{{ json_encode(config('jordan'), JSON_THROW_ON_ERROR) }}"></div>
</body>
</html>
