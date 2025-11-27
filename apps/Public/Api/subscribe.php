<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['error'=>'Method not allowed']); exit;
}

$raw = file_get_contents('php://input') ?: '{}';
$in  = json_decode($raw, true) ?: [];
$email = trim((string)($in['email'] ?? ''));

// basic validation
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(['error'=>'Valid email required']); exit;
}

// storage path (make sure /storage is writable)
$dir = __DIR__ . '/../../../../storage';
$file = $dir . '/subscribers.csv';
if (!is_dir($dir)) @mkdir($dir, 0775, true);

// de-duplicate by checking existing file
$already = false;
if (is_file($file)) {
  $fh = fopen($file, 'r');
  if ($fh) {
    while (($row = fgetcsv($fh)) !== false) {
      if (isset($row[0]) && strcasecmp(trim($row[0]), $email) === 0) { $already = true; break; }
    }
    fclose($fh);
  }
}

if (!$already) {
  $fh = fopen($file, 'a');
  if ($fh) {
    // email, ISO date, ip
    fputcsv($fh, [$email, gmdate('c'), $_SERVER['REMOTE_ADDR'] ?? '']);
    fclose($fh);
  } else {
    http_response_code(500);
    echo json_encode(['error'=>'Could not save subscription']); exit;
  }
}

echo json_encode(['ok'=>true]);