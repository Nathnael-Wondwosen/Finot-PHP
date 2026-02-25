<?php
session_start();
unset($_SESSION['portal_user_id'], $_SESSION['portal_role'], $_SESSION['portal_teacher_id']);
session_destroy();
header('Location: login.php');
exit;
?>
