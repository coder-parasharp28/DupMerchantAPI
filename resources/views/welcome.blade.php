<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="/images/icon.svg" type="image/x-icon">
        <title>Pie - Payments made easy</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
        <style>
            .bg-primary {
                background: #EC004E
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-primary text-white flex flex-col min-h-screen">
        <div class="flex-grow flex flex-col items-center justify-center">
            <div class="text-center">
                <img src="/images/logo-white.svg" alt="Logo" class="mx-auto mb-4">
                <p class="text-lg mt-2">Payments as easy as pie</p>
            </div>
        </div>
        <footer class="py-4 text-center text-sm text-white">
            &copy; 2024 Pie Inc.
        </footer>
    </body>
</html>