<?php
include_once 'DbManager.php';
$dbManager = new DbManager("account");
if($_SERVER["REQUEST_METHOD"] === 'POST'){
    $_post = json_decode(file_get_contents('php://input'), true);
    $existingAccount = count($dbManager->getAll("email = '$_post[email]'")) > 0;
    if(!$existingAccount){
        $inserted = $dbManager->insertOne($_post, true);
    }
    else{
        $inserted = false;
    }
    echo json_encode($inserted);
}