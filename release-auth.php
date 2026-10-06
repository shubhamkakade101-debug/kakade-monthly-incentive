<?php
function releaseClaim(string $envelope):array {
 $parts=explode('.',$envelope,2);$payload=base64_decode($parts[0]??'',true);$sig=base64_decode($parts[1]??'',true);
 if(!$payload||!$sig||openssl_verify($payload,$sig,file_get_contents(__DIR__.'/config/release-public.pem'),OPENSSL_ALGO_SHA256)!==1){http_response_code(403);header('Content-Type: application/json');echo '{"error":"Invalid release authorization."}';exit;}
 $c=json_decode($payload,true);$host=strtolower($_SERVER['HTTP_HOST']??'');if(!is_array($c)||($c['expires']??0)<time()||($c['expires']??0)>time()+1800||strtolower($c['host']??'')!==$host){http_response_code(403);header('Content-Type: application/json');echo '{"error":"Release authorization expired or wrong host."}';exit;}return $c;
}
