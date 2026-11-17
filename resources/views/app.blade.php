<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Portal Elektronik Sumber Daya Manusia (eSDM) Kimia Farma Plant Banjaran untuk presensi harian dan administrasi kepegawaian.">
        <meta name="theme-color" content="#005BAC">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Leaflet Maps (Geofence Presensi) -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
        <script src="https://unpkg.com/leaflet-pip@1.1.0/leaflet-pip.min.js" defer></script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

