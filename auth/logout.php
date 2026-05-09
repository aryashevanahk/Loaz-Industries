<?php
session_start();
session_destroy();
header('Location: /loaz_industries/auth/login.php');
exit();
?>