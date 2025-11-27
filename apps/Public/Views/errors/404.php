<?php
declare(strict_types=1);
/** @var string $message */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>404 Not Found</title>
  <link rel="icon" href="/favicon.ico">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #f9fafb;
      color: #1f2937;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
      text-align: center;
    }
    h1 { font-size: 3rem; color: #2563eb; margin-bottom: 0.5rem; }
    p  { color: #6b7280; margin-bottom: 1.5rem; }
    a {
      display: inline-block;
      background: #2563eb;
      color: #fff;
      padding: 0.6rem 1.2rem;
      border-radius: 0.5rem;
      text-decoration: none;
      font-weight: 500;
    }
    a:hover { background: #1e40af; }
  </style>
</head>
<body>
  <h1>404</h1>
  <p><?= htmlspecialchars($message ?? "Page not found.") ?></p>
  <a href="/">← Back to Home</a>
</body>
</html>