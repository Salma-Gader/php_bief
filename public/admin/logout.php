<?php
declare(strict_types=1);

/**
 * Sign out. POST only, with a CSRF token: a plain link or an image on another
 * site cannot sign staff out.
 */

require __DIR__ . '/../../src/bootstrap.php';

Auth::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::isValid()) {
    redirect('index.php');
}

Auth::logout();
redirect('login.php?logged_out=1');
