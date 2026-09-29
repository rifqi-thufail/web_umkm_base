<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="AMPUH — marketplace UMKM dan koperasi. Setiap transaksi yang lunas dicatat di jaringan Base agar bisa diverifikasi siapa saja.">
        <meta name="theme-color" content="#a3171f">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&family=Schibsted+Grotesk:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
        @routes
        @viteReactRefresh
        @vite('resources/js/app.tsx')
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
