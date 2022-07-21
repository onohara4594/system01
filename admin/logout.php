<?php
try {
    session_start();
    $_SESSION = array();
    session_destroy();

    header('Location:./login.php');
} catch (Exception $e) {
    //エラー時の処理
    header('Location:../error.php');
    exit;
}
