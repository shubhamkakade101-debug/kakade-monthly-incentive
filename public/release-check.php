<?php
require __DIR__.'/../app.php';header('Content-Type: application/json');header('Cache-Control: no-store');
if(!$releaseCheck||$_SERVER['REQUEST_METHOD']!=='POST')fail('Verification authorization required.',403);
$operation=json_decode(file_get_contents('php://input'),true)['operation']??'';
if($operation==='cleanup'){$db=null;function removeCheck(string $path):void{foreach(scandir($path) as $name){if($name==='.'||$name==='..')continue;$p=$path.'/'.$name;if(is_dir($p)&&!is_link($p))removeCheck($p);else unlink($p);}rmdir($path);}removeCheck($storage);echo '{"cleaned":true}';}
elseif($operation==='production-counts'){$production=getenv('INCENTIVE_STORAGE')?:__DIR__.'/../storage';$prod=new PDO('sqlite:'.$production.'/incentives.sqlite');$out=[];foreach(['users','employees','departments','requests','audit','files'] as $table)$out[$table]=(int)$prod->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();$out['demo_accounts']=(int)$prod->query('SELECT COUNT(*) FROM users WHERE username IN ("admin","requester","plant","ceo","hr")')->fetchColumn();echo json_encode($out);}
else fail('Unknown verification operation.');
