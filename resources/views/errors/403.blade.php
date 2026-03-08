<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Servora') }} — Access Denied</title>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="flex items-center justify-center min-h-screen">
        <div class="text-center">
            <div class="text-7xl font-bold text-gray-200 mb-4">403</div>
            <h2 class="text-xl font-semibold text-gray-700 mb-2">Access Denied</h2>
            <p class="text-gray-500 mb-6">You don't have permission to access this page.</p>
            <a href="{{ url('/dashboard') }}"
               class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
