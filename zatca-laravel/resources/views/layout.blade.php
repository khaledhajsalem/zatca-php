<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ZATCA Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('vendor/zatca/app.css') }}">
</head>
<body>
    <div id="zatca-app"
         data-base-path="{{ config('zatca.path') }}"
         data-environment="{{ config('zatca.environment') }}"
    ></div>

    <script src="{{ asset('vendor/zatca/app.js') }}"></script>
</body>
</html>
