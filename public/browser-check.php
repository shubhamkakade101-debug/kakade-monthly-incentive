<?php
require __DIR__.'/../release-auth.php';header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
if($_SERVER['REQUEST_METHOD']==='POST'){
 $envelope=(string)($_POST['authorization']??'');$claim=releaseClaim($envelope);
 if(($claim['purpose']??'')!=='verification'||!preg_match('/^[a-f0-9]{32}$/',$claim['id']??'')){http_response_code(403);exit('Verification authorization required.');}
 setcookie('incentive_verification',$envelope,['expires'=>$claim['expires'],'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);header('Location: /');exit;
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Authorized release verification</title></head><body><h1>Authorized release verification</h1><p>Only a signed, expiring test authorization can open the isolated verification workspace.</p><form method="post"><label>Verification authorization <input name="authorization" type="password" required autocomplete="off"></label><button>Open verification workspace</button></form></body></html>
