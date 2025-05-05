<?php
/* Завершення сесії та вихід */
session_start();
session_destroy();
header("Location: login.php");
exit();
?>
