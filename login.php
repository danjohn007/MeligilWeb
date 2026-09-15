<?php
require __DIR__.'/app/bootstrap.php';
if(logged_in()){ header('Location: '.base_url('dashboard')); exit; }
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $now=time(); $attempts=$_SESSION['login_attempts']??[]; $attempts=array_values(array_filter($attempts,fn($t)=>$t>$now-900));
    if(count($attempts)>=8){ $error='Demasiados intentos. Espera unos minutos antes de intentar de nuevo.'; }
    else{
        $email=mb_strtolower(trim($_POST['email']??'')); $password=(string)($_POST['password']??'');
        $user=db_one($pdo,'SELECT id,email,password_hash,full_name,role,active FROM mg_admin_users WHERE email=? LIMIT 1',[$email]);
        if($user && (int)$user['active']===1 && password_verify($password,$user['password_hash'])){
            session_regenerate_id(true); unset($_SESSION['login_attempts']);
            $_SESSION['mg_user']=['id'=>$user['id'],'email'=>$user['email'],'full_name'=>$user['full_name'],'role'=>$user['role']];
            $pdo->prepare('UPDATE mg_admin_users SET last_login_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$user['id']]);
            header('Location: '.base_url('dashboard')); exit;
        }
        $_SESSION['login_attempts']=array_merge($attempts,[$now]); $error='Correo electrónico o contraseña incorrectos.';
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Iniciar sesión | MeliGil CRM</title><link rel="stylesheet" href="<?=h(base_url('assets/css/app.css'))?>"></head><body class="login-page">
<div class="login-shell">
  <section class="login-hero">
    <div class="hero-watermark"><img src="<?=h(base_url('assets/img/logo.png'))?>" alt=""></div>
    <div class="hero-content"><span class="mini-pill">MeliGil · Mini CRM</span><h1>Convierte conversaciones en pedidos y oportunidades reales.</h1><p>Consulta contactos, pedidos, cotizaciones e interacciones de WhatsApp desde un solo lugar.</p><div class="hero-points"><span>✓ Seguimiento comercial</span><span>✓ Historial de WhatsApp</span><span>✓ Catálogo centralizado</span></div></div>
  </section>
  <section class="login-side"><div class="login-card"><img class="login-logo" src="<?=h(base_url('assets/img/logo.png'))?>" alt="MeliGil"><h2>Bienvenido</h2><p>Ingresa tus credenciales para continuar.</p><?php if($error):?><div class="alert danger"><?=h($error)?></div><?php endif; ?><form method="post"><?=csrf_input()?><label>Correo electrónico<input type="email" name="email" autocomplete="username" required></label><label>Contraseña<div class="password-wrap"><input id="pass" type="password" name="password" autocomplete="current-password" required><button type="button" class="eye" aria-label="Mostrar u ocultar contraseña" title="Mostrar u ocultar contraseña" onclick="const p=document.getElementById('pass'); const hidden=p.type==='password'; p.type=hidden?'text':'password'; this.classList.toggle('visible',hidden);"><svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.8"/></svg><svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A10.9 10.9 0 0 1 12 6c6 0 9.5 6 9.5 6a16.3 16.3 0 0 1-3 3.7M6.2 6.3C3.9 8 2.5 12 2.5 12s3.5 6 9.5 6a9.9 9.9 0 0 0 3.2-.5M9.9 9.8a3 3 0 0 0 4.3 4.3"/></svg></button></div></label><button class="btn primary wide">Iniciar sesión</button></form><small class="login-foot">Acceso exclusivo para personal autorizado.</small></div></section>
</div></body></html>
