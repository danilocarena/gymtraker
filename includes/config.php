<?php
// includes/config.php
require_once 'env_loader.php';

define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
