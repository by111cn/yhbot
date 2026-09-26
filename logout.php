<?php
require_once 'functions.php';

session_destroy();
header('Location: ' . SITE_URL . '/login.php');
exit;
