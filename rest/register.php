<?php //session_start();
include_once 'Argon2.php';
include_once 'DbManager.php';
$dbManager = new DbManager("account");

// echo base_convert(microtime(true), 10, 32);
// echo base64_encode(time());

if($_SERVER["REQUEST_METHOD"] === 'POST'){//Create account
    $_post = json_decode(file_get_contents('php://input'), true);
    $existingAccount = count($dbManager->getAll("email = '$_post[email]'")) > 0;
    $insertedAccount = false;
    $response = ['token' => false];
    if(!$existingAccount){
        $_post['password'] = Argon2::hash($_post['password']);  
        $insertedAccount = $dbManager->insertOne($_post, true);
        if($insertedAccount){
            $_post['token'] = Argon2::createToken(['email'=>$insertedAccount->email, 'token_date'=>$insertedAccount->updated_at]);
            $_post['id'] = $insertedAccount->id;
            $updated = $dbManager->updateOne($_post) == $insertedAccount->id;
            if($updated){
                //mail to $insertedAccount->email with the token button
                $response = ['token' => $_post['token']];
            }
        }
    } 
    echo json_encode($response);
}

if($_SERVER["REQUEST_METHOD"] === 'GET'){//Validate and activate account
    $_get = $_GET;
    if(isset($_get['t'])){
        $entries = Argon2::verifyToken($_get['t']);
        if($entries){
            $email = $entries->email;
            $accounts = $dbManager->getAll("email = '$email'");
            $existingAccount = count($accounts) > 0;
            if($existingAccount){
                $account = $accounts[0];
                if($account->token == $_get['t']){
                    $toUpdate = ['id' => $account->id, 'active' => 1, 'token' => null];
                    $updated = $dbManager->updateOne($toUpdate) == $account->id;
                    if($updated){
                        //echo json_encode(true); die;

                        header("Location: ../login.html");
                    }
                    
                }
                
            }
        }
    }
    echo json_encode(false);//renvoyer un email avec un token valide (non expiré)
}