<?php
require_once '../config/config.php';
logoutUser();
header("Location: " . BASE_URL . "mediflow/app/auth/index.php");
exit;