<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Open Sans có bộ ký tự vietnamese đầy đủ, nên dấu tiếng Việt không bị
             rơi sang font dự phòng của hệ điều hành. Các độ đậm liệt kê ở đây phải
             khớp với những `font-*` đang dùng trong giao diện (400/500/600/700/800),
             vì `font-synthesis: none` trong app.css không cho trình duyệt bịa độ đậm. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=open-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
