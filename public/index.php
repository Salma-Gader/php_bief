<?php
declare(strict_types=1);

// Placeholder for Phase 1. The landing page is built in Phase 2.
require __DIR__ . '/../src/bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(config('app.name')) ?></title>
</head>
<body>
    <h1><?= e(config('app.name')) ?></h1>
    <p>Scaffold is running. The landing page comes in Phase 2.</p>
</body>
</html>
