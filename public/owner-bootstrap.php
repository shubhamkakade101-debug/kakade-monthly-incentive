<?php
require __DIR__.'/../app.php';header('Cache-Control: no-store');header('Content-Type: application/json');
if($_SERVER['REQUEST_METHOD']!=='POST')fail('POST required.',405);
$c=releaseClaim(file_get_contents('php://input'));
if(($c['purpose']??'')!=='initial-admin'||!filter_var($c['email']??'',FILTER_VALIDATE_EMAIL)||!isset($c['password_hash'])||(!str_starts_with($c['password_hash'],'$2y$')&&!preg_match('/^\$pbkdf2\$[a-f0-9]{32}\$[a-f0-9]{64}$/',$c['password_hash'])))fail('Invalid provisioning data.',403);
$db->exec('BEGIN IMMEDIATE');
if(q('SELECT COUNT(*) FROM users')->fetchColumn()){ $db->exec('ROLLBACK');fail('Initial account already exists.',409);}
q('INSERT INTO users(username,name,password,role,units,reports,active,force_change) VALUES(?,?,?,"Admin","[1,2,3]",1,1,1)',[$c['email'],clean($c['name']??''),$c['password_hash']]);audit(null,(int)$db->lastInsertId(),'Initial Admin provisioned');$db->exec('COMMIT');echo '{"ok":true,"requires_password_change":true}';
