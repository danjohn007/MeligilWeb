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
    exit('<h2>No se pudo conectar a MySQL</h2><p>Revisa <code>config.php</code> e importa la migración correspondiente.</p>');
}

const MELIGIL_MODULES = [
    'dashboard'     => 'Dashboard',
    'contacts'      => 'Contactos',
    'products'      => 'Productos',
    'orders'        => 'Pedidos',
    'quotes'        => 'Cotizaciones',
    'sale_points'   => 'Puntos de venta',
    'point_queries' => 'Consultas de ubicación',
    'messages'      => 'Mensajes',
    'settings'      => 'Configuración',
];

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function db_one(PDO $pdo, string $sql, array $params=[]): ?array { $s=$pdo->prepare($sql); $s->execute($params); $r=$s->fetch(); return $r ?: null; }
function db_all(PDO $pdo, string $sql, array $params=[]): array { $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
function db_value(PDO $pdo, string $sql, array $params=[]) { $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchColumn(); }
function table_exists(PDO $pdo,string $table): bool { try { return (bool)db_value($pdo,"SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?",[$table]); } catch(Throwable $e){ return false; } }
function column_exists(PDO $pdo,string $table,string $column): bool { try { return (bool)db_value($pdo,"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?",[$table,$column]); } catch(Throwable $e){ return false; } }
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
function is_admin(?array $user=null): bool { $user=$user??current_user(); return ($user['role']??'') === 'admin'; }
function role_label(?string $role): string { return $role==='admin'?'Administrador':'Colaborador'; }
function user_full_name(?array $user=null): string {
    $user=$user??current_user();
    $name=trim((string)($user['first_name']??'').' '.(string)($user['last_name']??''));
    if($name!=='') return $name;
    return trim((string)($user['full_name']??'')) ?: 'Colaborador';
}
function initials(?array $user=null): string {
    $parts=preg_split('/\s+/u',user_full_name($user),-1,PREG_SPLIT_NO_EMPTY); $out='';
    foreach(array_slice($parts,0,2) as $p) $out.=mb_strtoupper(mb_substr($p,0,1));
    return $out ?: 'U';
}
function refresh_session_user(PDO $pdo): void {
    if(!logged_in()) return;
    try {
        $u=db_one($pdo,'SELECT * FROM mg_admin_users WHERE id=? LIMIT 1',[(int)$_SESSION['mg_user']['id']]);
        if(!$u || !(int)$u['active']) { $_SESSION=[]; return; }
        if(($u['role']??'')==='viewer') $u['role']='user';
        unset($u['password_hash']); $_SESSION['mg_user']=$u;
    } catch(Throwable $e) {}
}
function permission_rows(PDO $pdo,int $userId): array {
    if(!table_exists($pdo,'mg_user_permissions')) return [];
    $rows=db_all($pdo,'SELECT module_key,can_view,can_edit FROM mg_user_permissions WHERE user_id=?',[$userId]);
    $out=[]; foreach($rows as $r) $out[$r['module_key']]=['view'=>(bool)$r['can_view'],'edit'=>(bool)$r['can_edit']]; return $out;
}
function current_permissions(PDO $pdo): array {
    static $cache=null; if($cache!==null) return $cache; $cache=[]; $u=current_user(); if(!$u) return $cache;
    if(is_admin($u)){ foreach(MELIGIL_MODULES as $k=>$v) $cache[$k]=['view'=>true,'edit'=>true]; return $cache; }
    if(!table_exists($pdo,'mg_user_permissions') && in_array(($u['role']??''),['viewer','user'],true)){ foreach(MELIGIL_MODULES as $k=>$v) $cache[$k]=['view'=>true,'edit'=>false]; return $cache; }
    $cache=permission_rows($pdo,(int)$u['id']); return $cache;
}
function can_view(string $module): bool { global $pdo; if(is_admin()) return true; $p=current_permissions($pdo); return !empty($p[$module]['view']) || !empty($p[$module]['edit']); }
function can_edit(string $module): bool { global $pdo; if(is_admin()) return true; $p=current_permissions($pdo); return !empty($p[$module]['edit']); }
function require_admin(): void { if(!is_admin()){ http_response_code(403); exit('Esta acción requiere permisos de Administrador.'); } }
function require_can(string $module,string $action='view'): void { $ok=$action==='edit'?can_edit($module):can_view($module); if(!$ok){ http_response_code(403); exit('No tienes permiso para realizar esta acción.'); } }
function page_module(string $page): ?string { if($page==='contact') return 'contacts'; return array_key_exists($page,MELIGIL_MODULES)?$page:null; }
function first_allowed_page(): string { foreach(array_keys(MELIGIL_MODULES) as $m) if(can_view($m)) return $m; return 'profile'; }
function sync_user_permissions(PDO $pdo,int $userId,string $role,array $permissions): void {
    if(!table_exists($pdo,'mg_user_permissions')) return;
    $pdo->prepare('DELETE FROM mg_user_permissions WHERE user_id=?')->execute([$userId]);
    if($role==='admin') return;
    $s=$pdo->prepare('INSERT INTO mg_user_permissions(user_id,module_key,can_view,can_edit) VALUES(?,?,?,?)');
    foreach(MELIGIL_MODULES as $key=>$label){
        $edit=!empty($permissions[$key]['edit']); $view=$edit || !empty($permissions[$key]['view']);
        $s->execute([$userId,$key,$view?1:0,$edit?1:0]);
    }
}
function permission_summary(PDO $pdo,array $user): string {
    if(($user['role']??'')==='admin') return 'Acceso total';
    $p=permission_rows($pdo,(int)$user['id']); $labels=[];
    foreach(MELIGIL_MODULES as $k=>$label){ if(!empty($p[$k]['edit'])) $labels[]=$label.' (Editar)'; elseif(!empty($p[$k]['view'])) $labels[]=$label.' (Ver)'; }
    return $labels?implode(', ',$labels):'Sin módulos asignados';
}
function profile_upload(PDO $pdo,array $file,?string $current): ?string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return $current;
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException('No se pudo cargar la fotografía.');
    if((int)($file['size']??0)>2*1024*1024) throw new RuntimeException('La fotografía no debe superar 2 MB.');
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
    if(!$ext) throw new RuntimeException('La fotografía debe ser JPG, PNG o WEBP.');
    $dir=dirname(__DIR__).'/uploads/profiles'; if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar la carpeta de perfiles.');
    $name='profile_'.(int)(current_user()['id']??0).'_'.bin2hex(random_bytes(6)).'.'.$ext; $target=$dir.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$target)) throw new RuntimeException('No se pudo guardar la fotografía.');
    if($current && str_starts_with($current,'uploads/profiles/')){ $old=dirname(__DIR__).'/'.$current; if(is_file($old)) @unlink($old); }
    return 'uploads/profiles/'.$name;
}

function customer_journey_stage_label(string $stage): string {
    return [
        'prospecto'=>'Prospecto',
        'prospecto_recurrente'=>'Prospecto recurrente',
        'cliente'=>'Cliente',
        'cliente_recurrente'=>'Cliente recurrente',
    ][$stage] ?? 'Prospecto';
}
function customer_journey_stage_description(string $stage): string {
    return [
        'prospecto'=>'Contacto en su 1.er o 2.º contacto con MeliGil.',
        'prospecto_recurrente'=>'Contacto que llegó a su 3.er contacto y permanece aquí hasta que un Colaborador lo convierta manualmente en Cliente.',
        'cliente'=>'Contacto convertido manualmente en Cliente por un Colaborador.',
        'cliente_recurrente'=>'Cliente que alcanzó su 3.ª compra confirmada o cerrada; el cambio es automático.',
    ][$stage] ?? '';
}
function customer_journey_metric_rows(PDO $pdo,bool $activeOnly=true,bool $phoneOnly=false): array {
    $converted = column_exists($pdo,'mg_contacts','client_converted_at') ? 'c.client_converted_at' : 'NULL';
    $sql="SELECT c.id,c.phone,c.full_name,c.profile_name,c.email,c.company,c.city,c.active,c.last_inbound_at,c.created_at,
      GREATEST(1,(SELECT COUNT(*) FROM mg_messages m
        WHERE m.contact_id=c.id AND m.direction='IN'
          AND NOT EXISTS (
            SELECT 1 FROM mg_messages p
            WHERE p.contact_id=m.contact_id AND p.direction='IN'
              AND p.created_at>=DATE_SUB(m.created_at,INTERVAL 24 HOUR)
              AND (p.created_at<m.created_at OR (p.created_at=m.created_at AND p.id<m.id))
          ))) AS contact_count,
      (SELECT COUNT(*) FROM mg_orders o WHERE o.contact_id=c.id AND o.status IN ('confirmed','closed')) AS purchase_count,
      {$converted} AS client_converted_at
      FROM mg_contacts c WHERE 1=1";
    if($activeOnly) $sql.=' AND c.active=1';
    if($phoneOnly) $sql.=" AND c.phone<>''";
    $sql.=' ORDER BY c.last_inbound_at DESC,c.id DESC';
    return db_all($pdo,$sql);
}
function customer_journey_stage(array $row): string {
    $contacts=max(1,(int)($row['contact_count']??1));
    $purchases=(int)($row['purchase_count']??0);
    $converted=!empty($row['client_converted_at']);
    if($converted && $purchases>=3) return 'cliente_recurrente';
    if($converted) return 'cliente';
    return $contacts>=3 ? 'prospecto_recurrente' : 'prospecto';
}
function customer_journey_counts(PDO $pdo,bool $phoneOnly=false): array {
    $counts=['prospecto'=>0,'prospecto_recurrente'=>0,'cliente'=>0,'cliente_recurrente'=>0];
    foreach(customer_journey_metric_rows($pdo,true,$phoneOnly) as $r){ $stage=customer_journey_stage($r); $counts[$stage]++; }
    return $counts;
}
function customer_journey_contacts(PDO $pdo,string $stage,int $limit=200): array {
    $allowed=['prospecto','prospecto_recurrente','cliente','cliente_recurrente'];
    if(!in_array($stage,$allowed,true)) return [];
    $out=[];
    foreach(customer_journey_metric_rows($pdo,true,true) as $r){
        if(customer_journey_stage($r)!==$stage) continue;
        $out[]=$r;
        if(count($out)>=$limit) break;
    }
    return $out;
}
function customer_journey_contact(PDO $pdo,int $contactId): ?array {
    foreach(customer_journey_metric_rows($pdo,false,false) as $r) if((int)$r['id']===$contactId) return $r;
    return null;
}

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
refresh_session_user($pdo);
