<?php
function render_header(string $title,string $active=''):void{
    $u=current_user();
    $items=[
      'dashboard'=>['Inicio','⌂'],'contacts'=>['Contactos','◉'],'products'=>['Productos','🍪'],'orders'=>['Pedidos','🛒'],
      'quotes'=>['Cotizaciones','💰'],'sale_points'=>['Puntos de venta','📍'],'point_queries'=>['Consultas de ubicación','⌖'],
      'conversations'=>['Conversaciones','◫'],'messages'=>['Plantillas','✉'],'settings'=>['Configuración','⚙']
    ];
    $cssFile = dirname(__DIR__) . '/assets/css/app.css';
    $cssVersion = is_file($cssFile) ? (string) filemtime($cssFile) : '20260915-3';
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=h($title)?> | MeliGil</title><link rel="icon" href="<?=h(base_url('assets/img/logo.png'))?>"><link rel="stylesheet" href="<?=h(base_url('assets/css/app.css?v='.$cssVersion))?>"></head><body>
    <div class="app-shell"><aside class="sidebar"><div class="brand"><div class="brand-box"><img src="<?=h(base_url('assets/img/logo.png'))?>" alt="MeliGil"></div><span>CRM</span></div><nav>
    <?php foreach($items as $k=>$it): if($k==='settings'&&!is_admin())continue; ?><a class="<?=$active===$k?'active':''?>" href="<?=h(base_url('index.php?page='.$k))?>"><span class="ico"><?=$it[1]?></span><?=$it[0]?></a><?php endforeach; ?>
    </nav><div class="side-user"><div><strong><?=h($u['full_name']??'Usuario')?></strong><small><?=h($u['email']??'')?></small></div><a href="<?=h(base_url('logout'))?>">Salir</a></div></aside>
    <main class="main"><header class="topbar"><div><h1><?=h($title)?></h1><p>MeliGil · Administración comercial y atención desde WhatsApp</p></div><a class="profile" href="<?=h(base_url('index.php?page=profile'))?>">Mi cuenta</a></header><section class="content">
    <?php foreach(pull_flashes() as $f):?><div class="alert <?=h($f['type'])?>"><?=h($f['message'])?></div><?php endforeach; ?><?php
}
function render_footer():void{ ?></section></main></div></body></html><?php }
