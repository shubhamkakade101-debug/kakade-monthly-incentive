<?php
/* Private storage is outside the public document root. Demo seeding is opt-in. */
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__.'/release-auth.php';
$storage=getenv('INCENTIVE_STORAGE') ?: __DIR__.'/storage';
$releaseCheck=null;
if(isset($_SERVER['HTTP_X_RELEASE_CHECK'])){$releaseCheck=releaseClaim($_SERVER['HTTP_X_RELEASE_CHECK']);if(($releaseCheck['purpose']??'')!=='verification'||!preg_match('/^[a-f0-9]{32}$/',$releaseCheck['id']??''))fail('Invalid verification scope.',403);$storage.='/release-checks/'.$releaseCheck['id'];putenv('INCENTIVE_DEMO=1');}
if(!is_dir($storage)) mkdir($storage,0700,true);
if(!is_dir($storage.'/uploads')) mkdir($storage.'/uploads',0700,true);
$db=new PDO('sqlite:'.$storage.'/incentives.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
$db->exec('CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY, username TEXT UNIQUE, name TEXT, password TEXT, role TEXT, units TEXT, reports INTEGER DEFAULT 0, active INTEGER DEFAULT 1);
CREATE TABLE IF NOT EXISTS departments(id INTEGER PRIMARY KEY, name TEXT UNIQUE);
CREATE TABLE IF NOT EXISTS employees(id INTEGER PRIMARY KEY, code TEXT UNIQUE, name TEXT, unit INTEGER, department INTEGER REFERENCES departments(id));
CREATE TABLE IF NOT EXISTS requests(id INTEGER PRIMARY KEY, employee INTEGER REFERENCES employees(id), unit INTEGER, department INTEGER, month TEXT, reason TEXT, requester INTEGER REFERENCES users(id), status TEXT, amount INTEGER, created TEXT, received TEXT);
CREATE TABLE IF NOT EXISTS audit(id INTEGER PRIMARY KEY, request INTEGER, actor INTEGER, event TEXT, at TEXT);
CREATE TABLE IF NOT EXISTS files(id INTEGER PRIMARY KEY, request INTEGER REFERENCES requests(id), path TEXT, type TEXT);
CREATE TABLE IF NOT EXISTS attempts(ip TEXT PRIMARY KEY, count INTEGER, at INTEGER);');
$userColumns=$db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN,1);if(!in_array('force_change',$userColumns,true))$db->exec('ALTER TABLE users ADD COLUMN force_change INTEGER DEFAULT 0');
$fileColumns=$db->query('PRAGMA table_info(files)')->fetchAll(PDO::FETCH_COLUMN,1);if(!in_array('visibility',$fileColumns,true))$db->exec("ALTER TABLE files ADD COLUMN visibility TEXT DEFAULT 'private'");
function q(string $sql,array $args=[]): PDOStatement {global $db;$s=$db->prepare($sql);$s->execute($args);return $s;}
function audit(?int $id,int $actor,string $event):void {q('INSERT INTO audit(request,actor,event,at) VALUES(?,?,?,?)',[$id,$actor,$event,date('c')]);}
function fail(string $message,int $status=400):never {http_response_code($status);header('Content-Type: application/json');echo json_encode(['error'=>$message]);exit;}
function money(array $u):bool{return in_array($u['role'],['CEO','HR Head'],true);}
function scope(array $u,int $unit):bool{return in_array($unit,json_decode($u['units'],true),true);}
function clean(string $v,int $max=200):string {$v=trim($v);if($v===''||mb_strlen($v)>$max||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$v)) fail('Please supply valid text.');return $v;}
function currentUser():array {if(!isset($_SESSION['uid']))fail('Please sign in.',401);$u=q('SELECT * FROM users WHERE id=? AND active=1',[$_SESSION['uid']])->fetch(PDO::FETCH_ASSOC);if(!$u)fail('Account unavailable.',401);return $u;}
function viewRequest(array $u,int $id):array {$r=q('SELECT r.*,e.name employee_name,e.code,d.name department_name FROM requests r JOIN employees e ON e.id=r.employee JOIN departments d ON d.id=r.department WHERE r.id=?',[$id])->fetch(PDO::FETCH_ASSOC);if(!$r)fail('Request not found.',404);if(!scope($u,(int)$r['unit']))fail('Unit access denied.',403);if($u['role']==='Requester'&&!$u['reports']&&(int)$r['requester']!==(int)$u['id'])fail('Request access denied.',403);return $r;}
function projected(array $u,array $r):array {unset($r['requester']);if(!money($u))unset($r['amount']);return $r;}
if(!q('SELECT COUNT(*) FROM users')->fetchColumn() && getenv('INCENTIVE_DEMO')==='1') {
 foreach(['Production','Quality','Maintenance','Dispatch'] as $n)q('INSERT INTO departments(name) VALUES(?)',[$n]);
 foreach(['Admin','Requester','Plant Head','CEO','HR Head'] as $i=>$role)q('INSERT INTO users(username,name,password,role,units,reports) VALUES(?,?,?,?,?,?)',[['admin','requester','plant','ceo','hr'][$i],'Demo '.$role,password_hash('DemoOnly!2026',PASSWORD_DEFAULT),$role,'[1,2,3]',$role==='Admin'?1:0]);
 foreach(['A','B','C','D','E','F'] as $i=>$n)q('INSERT INTO employees(code,name,unit,department) VALUES(?,?,?,?)',['DEMO-'.(101+$i),'Demo Employee '.$n,($i%3)+1,($i%4)+1]);
 foreach([1,2,3,1,4,5] as $i=>$employee){$e=q('SELECT * FROM employees WHERE id=?',[$employee])->fetch(PDO::FETCH_ASSOC);q('INSERT INTO requests(employee,unit,department,month,reason,requester,status,amount,created,received) VALUES(?,?,?,?,?,?,?,?,?,?)',[$employee,$e['unit'],$e['department'],'2026-09',['Reduced rework through quality checks','Preventive maintenance completed','Dispatch accuracy improvement'][$i%3],2,'Processed',150000+$i*25000,'2026-09-20T10:00:00+05:30','2026-09-30T16:00:00+05:30']);$id=(int)$db->lastInsertId();foreach(['Requested','Plant approved','CEO approved','Amount assigned','Processed'] as $event)audit($id,$event==='Requested'?2:($event==='Plant approved'?3:($event==='CEO approved'?4:5)),$event);}
}

function queueStages(array $u):array {
 $defs=[
 'Request'=>['label'=>'Request','hint'=>'Submission & tracking','pending'=>true],
 'Plant Head'=>['label'=>'Plant Head approval','hint'=>'Awaiting approval','pending'=>true],
 'CEO'=>['label'=>'CEO approval','hint'=>'Awaiting approval','pending'=>true],
 'HR Value'=>['label'=>'HR amount assignment','hint'=>'Awaiting value','pending'=>true],
 'HR Processing'=>['label'=>'HR processing','hint'=>'Awaiting processing','pending'=>true],
 'Processed'=>['label'=>'Processed','hint'=>'Completed','pending'=>false],
 'Not Processed'=>['label'=>'Not Processed','hint'=>'Final outcome','pending'=>false]];
 $keys=match($u['role']) {'Admin'=>array_keys($defs),'Requester'=>['Request','Processed','Not Processed'],'Plant Head'=>['Plant Head'],'CEO'=>['CEO'],'HR Head'=>['HR Value','HR Processing','Processed','Not Processed'],default=>[]};
 $out=[];foreach($keys as $key)$out[]=['key'=>$key,...$defs[$key]];return $out;
}
function queueRows(array $u,string $stage):array {
 if(!in_array($stage,array_column(queueStages($u),'key'),true))fail('Stage access denied.',403);
 $rows=[];
 foreach(q('SELECT id,unit,requester,status FROM requests ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) as $r){
  if(!scope($u,(int)$r['unit']))continue;
  // A report grant never widens the requester tracking queue.
  if($u['role']==='Requester'&&(int)$r['requester']!==(int)$u['id'])continue;
  if($stage!=='Request'&&$r['status']!==$stage)continue;
  $rows[]=projected($u,viewRequest($u,(int)$r['id']));
 }
 return $rows;
}
function queueSummary(array $u):array {
 $out=[];foreach(queueStages($u) as $stage){$rows=queueRows($u,$stage['key']);$stage['count']=$stage['key']==='Request'?count(array_filter($rows,fn($r)=>in_array($r['status'],['Plant Head','CEO','HR Value','HR Processing'],true))):count($rows);$out[]=$stage;}return $out;
}

function canFile(array $u,array $f):bool{return money($u)||($f['visibility']??'private')==='approval';}

function accountPassword(string $password,string $hash):bool {
 if(str_starts_with($hash,'$pbkdf2$')){$parts=explode('$',$hash);if(count($parts)!==4||!ctype_xdigit($parts[2])||!ctype_xdigit($parts[3]))return false;return hash_equals($parts[3],hash_pbkdf2('sha256',$password,hex2bin($parts[2]),600000,64));}
 return password_verify($password,$hash);
}
