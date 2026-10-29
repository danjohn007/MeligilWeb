<?php
function render_header(string $title,string $active=''):void{
    $u=current_user();
    $items=[
      'dashboard'=>['Inicio','⌂'],'contacts'=>['Contactos','◉'],'products'=>['Productos','🍪'],'orders'=>['Pedidos','🛒'],
      'quotes'=>['Cotizaciones','💰'],'sale_points'=>['Puntos de venta','📍'],'point_queries'=>['Consultas de ubicación','⌖'],
      'messages'=>['Mensajes','✉'],'settings'=>['Configuración','⚙']
    ];
    $cssFile = dirname(__DIR__) . '/assets/css/app.css';
    $cssVersion = is_file($cssFile) ? (string) filemtime($cssFile) : '20260923';
    $jsFile = dirname(__DIR__) . '/assets/js/app.js';
    $jsVersion = is_file($jsFile) ? (string) filemtime($jsFile) : '20260923';
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=h($title)?> | MeliGil</title><link rel="icon" href="<?=h(base_url('assets/img/logo.png'))?>"><link rel="stylesheet" href="<?=h(base_url('assets/css/app.css?v='.$cssVersion))?>"></head><body>
    <div class="app-shell"><aside class="sidebar"><div class="brand"><div class="brand-box"><img src="<?=h(base_url('assets/img/logo.png'))?>" alt="MeliGil"></div><span>CRM</span></div><nav>
    <?php foreach($items as $k=>$it): if(!can_view($k))continue; ?><a class="<?=$active===$k?'active':''?>" href="<?=h(base_url('index.php?page='.$k))?>"><span class="ico"><?=$it[1]?></span><?=$it[0]?></a><?php endforeach; ?>
    <?php if(is_admin()):?><a class="<?=$active==='users'?'active':''?>" href="<?=h(base_url('colaboradores'))?>"><span class="ico">👥</span>Colaboradores</a><?php endif;?>
    </nav><div class="side-user"><div><strong><?=h(user_full_name($u))?></strong><small><?=h($u['email']??'')?></small></div><a href="<?=h(base_url('logout'))?>">Salir</a></div></aside>
    <main class="main"><header class="topbar"><div><h1><?=h($title)?></h1><p>MeliGil · Administración comercial y comunicación con contactos</p></div><a class="profile" href="<?=h(base_url('index.php?page=profile'))?>">Mi perfil</a></header><section class="content">
    <?php foreach(pull_flashes() as $f):?><div class="alert <?=h($f['type'])?>"><?=h($f['message'])?></div><?php endforeach; ?><?php
}
function render_footer():void{ ?><script src="<?=h(base_url('assets/js/app.js?v='.(@filemtime(dirname(__DIR__).'/assets/js/app.js')?:'1')))?>"></script></section></main></div></body></html><?php }
