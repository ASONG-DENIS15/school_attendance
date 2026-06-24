<?php
require_once 'config.php';
$_SESSION = [];
session_destroy();
redirect_to('login.php');
