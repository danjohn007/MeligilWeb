<?php
$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) { http_response_code(500); exit('Falta config.php'); }
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'America/Mexico_City');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('meligil_crm');
session_set_cookie_params(['lifetime'=>0,'path'=>$config['base_path'] ?? '/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

try {
    $d = $config['db'];
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $d['host'], $d['name'], $d['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $d['user'], $d['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    exit('<h2>No se pudo conectar a MySQL</h2><p>Revisa <code>config.php</code> e importa <code>meligil.sql</code>.</p>');
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function db_one(PDO $pdo, string $sql, array $params=[]): ?array { $s=$pdo->prepare($sql); $s->execute($params); $r=$s->fetch(); return $r ?: null; }
function db_all(PDO $pdo, string $sql, array $params=[]): array { $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
function db_value(PDO $pdo, string $sql, array $params=[]) { $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchColumn(); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_input(): string { return '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">'; }
function verify_csrf(): void { $t=$_POST['csrf']??''; if (!$t || !hash_equals($_SESSION['csrf']??'', $t)) { http_response_code(419); exit('Sesión expirada. Recarga la página.'); } }
function logged_in(): bool { return !empty($_SESSION['mg_user']['id']); }
function current_user(): ?array { return $_SESSION['mg_user'] ?? null; }
function base_url(string $path=''): string { global $config; return rtrim($config['base_path'] ?? '/meligil','/').'/'.ltrim($path,'/'); }
function require_auth(): void { if (!logged_in()) { header('Location: '.base_url('login')); exit; } }
function flash(string $type, string $message): void { $_SESSION['flash'][]=['type'=>$type,'message'=>$message]; }
function pull_flashes(): array { $f=$_SESSION['flash']??[]; unset($_SESSION['flash']); return $f; }
function redirect_to(string $url): never { header('Location: '.$url); exit; }
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function require_admin(): void { if (!is_admin()) throw new RuntimeException('Esta acción requiere permisos de administrador.'); }
function fmt_dt($v): string { if(!$v)return '—'; try{$d=new DateTime((string)$v,new DateTimeZone('UTC'));$d->setTimezone(new DateTimeZone(date_default_timezone_get()));return $d->format('d/m/Y H:i');}catch(Throwable $e){return (string)$v;} }
function fmt_date($v): string { if(!$v)return '—'; try{return (new DateTime((string)$v))->format('d/m/Y');}catch(Throwable $e){return (string)$v;} }
function status_class(string $s): string { $s=strtolower($s); if(in_array($s,['active','confirmed','sent','quoted','contacted','resolved','closed'],true))return 'success'; if(in_array($s,['pending','new','review'],true))return 'warning'; if(in_array($s,['cancelled','rejected','error','inactive'],true))return 'danger'; return 'neutral'; }
function setting(PDO $pdo,string $key,string $fallback=''):string{ $v=db_value($pdo,'SELECT setting_value FROM mg_settings WHERE setting_key=?',[$key]); return $v===false||$v===null?$fallback:(string)$v; }
function setting_nonempty(PDO $pdo,string $key,string $fallback=''):string{ $v=trim(setting($pdo,$key,'')); return $v!==''?$v:$fallback; }
function firebase_runtime_config(PDO $pdo,array $config):array{ $saved=trim(setting($pdo,'firebase_crm_url','')); if($saved!=='')$config['firebase_crm_url']=$saved; return $config; }
function firebase_post(array $config,array $payload):array{
    $url=trim($config['firebase_crm_url']??''); $key=trim($config['firebase_admin_key']??'');
    if(!$url||!$key)return ['ok'=>false,'http'=>0,'data'=>['error'=>'Configura firebase_crm_url y firebase_admin_key.']];
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>120,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Admin-Key: '.$key],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    $raw=curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    if($raw===false)return ['ok'=>false,'http'=>$http,'data'=>['error'=>$err?:'Error conectando con Firebase']];
    $data=json_decode($raw,true);
    if(!is_array($data))$data=['error'=>trim(strip_tags((string)$raw))?:'Respuesta no válida de Firebase'];
    return ['ok'=>$http>=200&&$http<300&&!empty($data['ok']),'http'=>$http,'data'=>$data];
}
