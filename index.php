<?php
/**
 * Root entry point — delegates to public/index.php (login page).
 * This file exists because the server document root is the project root,
 * not the public/ subdirectory.
 */
require_once __DIR__ . '/public/index.php';
