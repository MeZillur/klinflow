<?php
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>404 — Not Found</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-50 text-gray-800">
  <div class="text-center p-8">
    <div class="text-6xl font-extrabold">404</div>
    <p class="mt-3 text-gray-600">We couldn’t find <code class="px-1 py-0.5 bg-gray-100 rounded"><?= $h($path) ?></code>.</p>
    <a class="mt-6 inline-block px-4 py-2 bg-green-600 text-white rounded-lg" href="/">Go home</a>
  </div>
</body></html>