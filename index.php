<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/layout.php';
require_auth();
$user=current_user();
$page=preg_replace('/[^a-z_]/','',$_GET['page']??'dashboard');
$allowed=['dashboard','contacts','contact','products','orders','quotes','sale_points','point_queries','conversations','conversation','messages','settings','profile'];
if(!in_array($page,$allowed,true))$page='dashboard';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'';
    try{
        if($action==='send_template'){
            $cid=(int)($_POST['contact_id']??0); $body=trim((string)($_POST['body_text']??''));
            if(!$cid)throw new RuntimeException('Selecciona un usuario.'); if($body==='')throw new RuntimeException('Escribe el mensaje que se enviará en {{1}}.');
            $c=db_one($pdo,'SELECT id,phone FROM mg_contacts WHERE id=? AND active=1',[$cid]); if(!$c)throw new RuntimeException('El contacto no existe o está inactivo.');
            $name=setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']??'marketing_meligil_texto');
            $lang=setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']??'es_MX');
            $r=firebase_post(firebase_runtime_config($pdo,$config),['action'=>'template','contact_id'=>$cid,'phone'=>$c['phone'],'template_name'=>$name,'language'=>$lang,'body_text'=>$body]);
            if(!$r['ok'])throw new RuntimeException($r['data']['error']??('No se pudo enviar. HTTP '.$r['http']));
            flash('success','Plantilla enviada correctamente.'); redirect_to(base_url('index.php?page=messages'));
        }
        if($action==='send_template_bulk'){
            require_admin(); $body=trim((string)($_POST['body_text']??'')); if($body==='')throw new RuntimeException('Escribe el mensaje que se enviará en {{1}}.');
            $name=setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']??'marketing_meligil_texto');
            $lang=setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']??'es_MX');
            $r=firebase_post(firebase_runtime_config($pdo,$config),['action'=>'template_bulk','template_name'=>$name,'language'=>$lang,'body_text'=>$body]);
            if($r['http']<200||$r['http']>=300)throw new RuntimeException($r['data']['error']??('No se pudo completar el envío masivo. HTTP '.$r['http']));
            flash(($r['data']['failed']??0)?'warning':'success','Envío masivo terminado: '.(int)($r['data']['sent']??0).' enviados; '.(int)($r['data']['failed']??0).' con error.'); redirect_to(base_url('index.php?page=messages'));
        }
        if($action==='save_product'){
            require_admin(); $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $short=trim($_POST['short_name']??''); $emoji=trim($_POST['emoji']??''); $desc=trim($_POST['description']??''); $active=!empty($_POST['active'])?1:0; $sort=(int)($_POST['sort_order']??100);
            if($name==='')throw new RuntimeException('El nombre del producto es obligatorio.');
            if($id)$pdo->prepare('UPDATE mg_products SET name=?,short_name=?,emoji=?,description=?,active=?,sort_order=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$name,$short?:null,$emoji?:null,$desc?:null,$active,$sort,$id]);
            else $pdo->prepare('INSERT INTO mg_products(name,short_name,emoji,description,active,sort_order) VALUES(?,?,?,?,?,?)')->execute([$name,$short?:null,$emoji?:null,$desc?:null,$active,$sort]);
            flash('success','Producto guardado. El bot tomará el catálogo directamente de la BD.'); redirect_to(base_url('index.php?page=products'));
        }
        if($action==='delete_product'){
            require_admin(); $id=(int)($_POST['id']??0); $pdo->prepare('DELETE FROM mg_products WHERE id=?')->execute([$id]); flash('success','Producto eliminado.'); redirect_to(base_url('index.php?page=products'));
        }
        if($action==='save_sale_point'){
            require_admin(); $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $city=trim($_POST['city']??''); $municipality=trim($_POST['municipality']??''); $address=trim($_POST['address']??''); $schedule=trim($_POST['schedule']??''); $phone=trim($_POST['phone']??''); $map=trim($_POST['map_url']??''); $active=!empty($_POST['active'])?1:0; $sort=(int)($_POST['sort_order']??100);
            if($name===''||$city==='')throw new RuntimeException('Nombre y ciudad son obligatorios.'); if($map!==''&&!filter_var($map,FILTER_VALIDATE_URL))throw new RuntimeException('La URL del mapa no es válida.');
            if($id)$pdo->prepare('UPDATE mg_sale_points SET name=?,city=?,municipality=?,address=?,schedule=?,phone=?,map_url=?,active=?,sort_order=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$name,$city,$municipality?:null,$address?:null,$schedule?:null,$phone?:null,$map?:null,$active,$sort,$id]);
            else $pdo->prepare('INSERT INTO mg_sale_points(name,city,municipality,address,schedule,phone,map_url,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$name,$city,$municipality?:null,$address?:null,$schedule?:null,$phone?:null,$map?:null,$active,$sort]);
            flash('success','Punto de venta guardado. El bot podrá consultarlo por ciudad/municipio.'); redirect_to(base_url('index.php?page=sale_points'));
        }
        if($action==='delete_sale_point'){
            require_admin(); $id=(int)($_POST['id']??0); $pdo->prepare('DELETE FROM mg_sale_points WHERE id=?')->execute([$id]); flash('success','Punto de venta eliminado.'); redirect_to(base_url('index.php?page=sale_points'));
        }
        if($action==='update_order'){
            $id=(int)($_POST['id']??0); $status=$_POST['status']??'pending'; $notes=trim($_POST['notes']??''); if(!in_array($status,['pending','confirmed','closed','cancelled'],true))throw new RuntimeException('Estado inválido.');
            $pdo->prepare('UPDATE mg_orders SET status=?,notes=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,$notes?:null,$id]); flash('success','Pedido actualizado.'); redirect_to(base_url('index.php?page=orders'));
        }
        if($action==='update_quote'){
            $id=(int)($_POST['id']??0); $status=$_POST['status']??'pending'; $notes=trim($_POST['notes']??''); if(!in_array($status,['pending','review','quoted','closed','cancelled'],true))throw new RuntimeException('Estado inválido.');
            $pdo->prepare('UPDATE mg_quotes SET status=?,notes=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,$notes?:null,$id]); flash('success','Cotización actualizada.'); redirect_to(base_url('index.php?page=quotes'));
        }
        if($action==='update_point_query'){
            $id=(int)($_POST['id']??0); $status=$_POST['status']??'pending'; $notes=trim($_POST['notes']??''); if(!in_array($status,['resolved_bot','pending','contacted','closed'],true))throw new RuntimeException('Estado inválido.');
            $pdo->prepare('UPDATE mg_sale_point_queries SET status=?,notes=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,$notes?:null,$id]); flash('success','Consulta actualizada.'); redirect_to(base_url('index.php?page=point_queries'));
        }
        if($action==='add_contact_note'){
            $cid=(int)($_POST['contact_id']??0); $note=trim($_POST['note']??''); if(!$cid||$note==='')throw new RuntimeException('Escribe una nota.');
            $pdo->prepare('INSERT INTO mg_contact_notes(contact_id,admin_user_id,note) VALUES(?,?,?)')->execute([$cid,$user['id'],$note]); flash('success','Nota guardada.'); redirect_to(base_url('index.php?page=contact&id='.$cid));
        }
        if($action==='save_settings'){
            require_admin(); $template=trim($_POST['marketing_template_name']??'marketing_meligil_texto')?:'marketing_meligil_texto'; $lang=trim($_POST['marketing_template_language']??'es_MX')?:'es_MX'; $url=trim($_POST['firebase_crm_url']??''); if($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))throw new RuntimeException('La URL de Firebase no es válida.');
            $s=$pdo->prepare('INSERT INTO mg_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); $s->execute(['marketing_template_name',$template]); $s->execute(['marketing_template_language',$lang]); $s->execute(['firebase_crm_url',$url]);
            flash('success','Configuración guardada.'); redirect_to(base_url('index.php?page=settings'));
        }
        if($action==='test_firebase'){
            require_admin(); $r=firebase_post(firebase_runtime_config($pdo,$config),['action'=>'health']); flash($r['ok']?'success':'danger',$r['ok']?'Conexión Web → Firebase → MySQL correcta.':($r['data']['error']??('No se pudo conectar. HTTP '.$r['http']))); redirect_to(base_url('index.php?page=settings'));
        }
        if($action==='change_password'){
            $current=(string)($_POST['current_password']??''); $new=(string)($_POST['new_password']??''); $confirm=(string)($_POST['confirm_password']??''); if(strlen($new)<10)throw new RuntimeException('La nueva contraseña debe tener al menos 10 caracteres.'); if($new!==$confirm)throw new RuntimeException('Las contraseñas no coinciden.');
            $r=db_one($pdo,'SELECT password_hash FROM mg_admin_users WHERE id=?',[$user['id']]); if(!$r||!password_verify($current,$r['password_hash']))throw new RuntimeException('La contraseña actual no es correcta.');
            $pdo->prepare('UPDATE mg_admin_users SET password_hash=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]); flash('success','Contraseña actualizada.'); redirect_to(base_url('index.php?page=profile'));
        }
    }catch(Throwable $e){ flash('danger',$e->getMessage()); redirect_to($_SERVER['REQUEST_URI']); }
}

if($page==='dashboard'){
    $m=[
      'contactos'=>(int)db_value($pdo,'SELECT COUNT(*) FROM mg_contacts WHERE active=1'),
      'pedidos_pendientes'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_orders WHERE status='pending'"),
      'cotizaciones_abiertas'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_quotes WHERE status IN ('pending','review')"),
      'interacciones_hoy'=>(int)db_value($pdo,'SELECT COUNT(*) FROM mg_messages WHERE DATE(created_at)=UTC_DATE()'),
      'pedidos_confirmados'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_orders WHERE status IN ('confirmed','closed')"),
      'ubicacion_pendiente'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_sale_point_queries WHERE status='pending'")
    ];

    $funnel=[
      'Contactos'=>$m['contactos'],
      'Cotizaciones'=>(int)db_value($pdo,'SELECT COUNT(DISTINCT contact_id) FROM mg_quotes'),
      'Pedidos'=>(int)db_value($pdo,'SELECT COUNT(DISTINCT contact_id) FROM mg_orders'),
      'Clientes'=>(int)db_value($pdo,"SELECT COUNT(DISTINCT contact_id) FROM mg_orders WHERE status IN ('confirmed','closed')")
    ];
    $conversion=$m['contactos']>0?round(($funnel['Clientes']/$m['contactos'])*100,1):0;

    $monthRows=db_all($pdo,"SELECT DATE_FORMAT(created_at,'%Y-%m') ym,COUNT(*) total FROM mg_contacts WHERE created_at>=DATE_FORMAT(DATE_SUB(UTC_DATE(),INTERVAL 5 MONTH),'%Y-%m-01') GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY ym");
    $monthMap=[]; foreach($monthRows as $r)$monthMap[$r['ym']]=(int)$r['total'];
    $monthNames=[1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
    $months=[]; $base=new DateTimeImmutable('first day of this month');
    for($i=5;$i>=0;$i--){$d=$base->modify("-$i months");$key=$d->format('Y-m');$months[]=['label'=>$monthNames[(int)$d->format('n')],'value'=>$monthMap[$key]??0];}
    $monthMax=max(1,...array_column($months,'value'));

    $stages=[
      ['label'=>'Pedidos pendientes','value'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_orders WHERE status='pending'")],
      ['label'=>'Pedidos confirmados','value'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_orders WHERE status='confirmed'")],
      ['label'=>'Pedidos cerrados','value'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_orders WHERE status='closed'")],
      ['label'=>'Cotizaciones pendientes','value'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_quotes WHERE status='pending'")],
      ['label'=>'Cotizaciones en revisión','value'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_quotes WHERE status='review'")],
      ['label'=>'Cotizaciones enviadas','value'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_quotes WHERE status IN ('quoted','closed')")],
      ['label'=>'Consultas de ubicación','value'=>(int)db_value($pdo,"SELECT COUNT(*) FROM mg_sale_point_queries WHERE status='pending'")]
    ];
    $stageMax=max(1,...array_column($stages,'value'));

    $ordersTotal=(int)db_value($pdo,'SELECT COUNT(*) FROM mg_orders');
    $ordersOpen=(int)db_value($pdo,"SELECT COUNT(*) FROM mg_orders WHERE status='pending'");
    $ordersDone=$ordersTotal-$ordersOpen;
    $quotesTotal=(int)db_value($pdo,'SELECT COUNT(*) FROM mg_quotes');
    $quotesOpen=(int)db_value($pdo,"SELECT COUNT(*) FROM mg_quotes WHERE status IN ('pending','review')");
    $quotesDone=$quotesTotal-$quotesOpen;
    $queriesTotal=(int)db_value($pdo,'SELECT COUNT(*) FROM mg_sale_point_queries');
    $queriesOpen=(int)db_value($pdo,"SELECT COUNT(*) FROM mg_sale_point_queries WHERE status='pending'");
    $queriesDone=$queriesTotal-$queriesOpen;
    $activity=[
      ['label'=>'Pedidos','total'=>$ordersTotal,'done'=>$ordersDone,'open'=>$ordersOpen],
      ['label'=>'Cotizaciones','total'=>$quotesTotal,'done'=>$quotesDone,'open'=>$quotesOpen],
      ['label'=>'Ubicaciones','total'=>$queriesTotal,'done'=>$queriesDone,'open'=>$queriesOpen]
    ];

    $latest=db_all($pdo,'SELECT c.id,c.full_name,c.profile_name,c.phone,c.city,c.last_inbound_at FROM mg_contacts c ORDER BY c.last_inbound_at DESC,c.id DESC LIMIT 8');
    render_header('Inicio','dashboard'); ?>

    <div class="dashboard-heading">
      <div><span class="eyebrow">RESUMEN GENERAL</span><h2>Dashboard comercial</h2><p>Estado actual de contactos, pedidos, cotizaciones e interacciones captadas por MeliGilBOT.</p></div>
      <a class="btn primary" href="<?=h(base_url('index.php?page=contacts'))?>">Ver contactos</a>
    </div>

    <div class="metric-grid">
      <div class="metric-card"><div class="metric-icon">◎</div><div><span>Contactos</span><strong><?=$m['contactos']?></strong><small>Registrados en el CRM</small></div></div>
      <div class="metric-card"><div class="metric-icon">🛒</div><div><span>Pedidos pendientes</span><strong><?=$m['pedidos_pendientes']?></strong><small>Por confirmar o atender</small></div></div>
      <div class="metric-card"><div class="metric-icon">▤</div><div><span>Cotizaciones abiertas</span><strong><?=$m['cotizaciones_abiertas']?></strong><small>Pendientes o en revisión</small></div></div>
      <div class="metric-card"><div class="metric-icon">☎</div><div><span>Interacciones hoy</span><strong><?=$m['interacciones_hoy']?></strong><small>Actividad de WhatsApp</small></div></div>
      <div class="metric-card"><div class="metric-icon">✓</div><div><span>Pedidos atendidos</span><strong><?=$m['pedidos_confirmados']?></strong><small>Confirmados o cerrados</small></div></div>
      <div class="metric-card"><div class="metric-icon">⌖</div><div><span>Ubicación pendiente</span><strong><?=$m['ubicacion_pendiente']?></strong><small>Consultas por revisar</small></div></div>
    </div>

    <section class="journey-card">
      <div class="journey-title"><div><h3>Customer Journey comercial</h3><p>Vista rápida del avance desde un contacto hasta una compra confirmada.</p></div><div class="journey-conversion"><span>Conversión a cliente</span><strong><?=$conversion?>%</strong></div></div>
      <div class="journey-grid">
        <?php $jn=0; foreach($funnel as $label=>$value): $jn++; ?>
          <div class="journey-step"><div class="journey-step-top"><span class="step-no"><?=$jn?></span><strong><?=$value?></strong></div><h4><?=h($label)?></h4><p><?php if($label==='Contactos'):?>Personas registradas por el bot o el CRM.<?php elseif($label==='Cotizaciones'):?>Contactos que ya solicitaron una cotización.<?php elseif($label==='Pedidos'):?>Contactos que iniciaron un pedido.<?php else:?>Contactos con al menos un pedido confirmado o cerrado.<?php endif;?></p></div>
        <?php endforeach; ?>
      </div>
      <div class="journey-note">Contactos → Cotizaciones → Pedidos → Clientes. Los datos se calculan directamente con la información real del CRM.</div>
    </section>

    <div class="dashboard-two-col">
      <section class="analytics-card">
        <div class="analytics-title"><div><h3>Nuevos contactos</h3><p>Últimos seis meses</p></div><strong><?=array_sum(array_column($months,'value'))?> totales</strong></div>
        <div class="bar-chart">
          <?php foreach($months as $month): $height=($month['value']/$monthMax)*100; ?>
            <div class="bar-item"><span class="bar-value"><?=$month['value']?></span><div class="bar-track"><div class="bar-fill" style="height:<?=max(4,$height)?>%"></div></div><span class="bar-label"><?=h($month['label'])?></span></div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="analytics-card">
        <div class="analytics-title"><div><h3>Actividad comercial</h3><p>Distribución actual</p></div></div>
        <div class="stage-list">
          <?php foreach($stages as $stage): $width=($stage['value']/$stageMax)*100; ?>
            <div class="stage-row"><div><span><?=h($stage['label'])?></span><strong><?=$stage['value']?></strong></div><div class="stage-track"><span style="width:<?=$width?>%"></span></div></div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <div class="summary-grid">
      <section class="summary-card"><h3>Conversión de contactos a clientes</h3><p>Clientes con compra confirmada</p><strong class="big-percent"><?=$conversion?>%</strong><div class="mini-progress"><span style="width:<?=min(100,$conversion)?>%"></span></div><small><?=$funnel['Clientes']?> clientes de <?=$m['contactos']?> contactos</small></section>
      <?php foreach($activity as $a): $pct=$a['total']?round(($a['done']/$a['total'])*100):0; ?>
        <section class="summary-card"><h3>Atención de <?=h(strtolower($a['label']))?></h3><p>Atendidos vs. por atender</p><div class="attention-numbers"><strong><?=$a['done']?> / <?=$a['open']?></strong><span><?=$a['done']?> atendidos · <?=$a['open']?> por atender</span></div><div class="mini-progress"><span style="width:<?=$pct?>%"></span></div><small><?=$a['total']?> registros totales</small></section>
      <?php endforeach; ?>
    </div>

    <div class="section-title"><h2>Actividad reciente</h2></div><div class="table-wrap"><table><thead><tr><th>Contacto</th><th>WhatsApp</th><th>Ciudad</th><th>Última interacción</th><th></th></tr></thead><tbody><?php foreach($latest as $r):?><tr><td><strong><?=h($r['full_name']?:$r['profile_name']?:'Sin nombre')?></strong></td><td><?=h($r['phone'])?></td><td><?=h($r['city']?:'—')?></td><td><?=h(fmt_dt($r['last_inbound_at']))?></td><td><a class="btn secondary" href="<?=h(base_url('index.php?page=contact&id='.$r['id']))?>">Ver</a></td></tr><?php endforeach;?></tbody></table></div>
    <?php render_footer();exit;
}

if($page==='contacts'){
    $q=trim($_GET['q']??''); $params=[]; $sql='SELECT * FROM mg_contacts'; if($q!==''){ $sql.=' WHERE full_name LIKE ? OR profile_name LIKE ? OR phone LIKE ? OR email LIKE ? OR company LIKE ?'; $like='%'.$q.'%';$params=array_fill(0,5,$like); } $sql.=' ORDER BY last_inbound_at DESC,id DESC'; $rows=db_all($pdo,$sql,$params);
    render_header('Contactos','contacts'); ?><form class="toolbar"><input type="hidden" name="page" value="contacts"><input name="q" value="<?=h($q)?>" placeholder="Buscar por nombre, teléfono, correo o empresa"><button class="btn dark">Buscar</button></form><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>WhatsApp</th><th>Correo</th><th>Empresa</th><th>Ciudad</th><th>Última interacción</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=h($r['full_name']?:$r['profile_name']?:'Sin nombre')?></strong></td><td><?=h($r['phone'])?></td><td><?=h($r['email']?:'—')?></td><td><?=h($r['company']?:'—')?></td><td><?=h($r['city']?:'—')?></td><td><?=h(fmt_dt($r['last_inbound_at']))?></td><td><a class="btn secondary" href="<?=h(base_url('index.php?page=contact&id='.$r['id']))?>">Ver</a></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='contact'){
    $id=(int)($_GET['id']??0); $c=db_one($pdo,'SELECT * FROM mg_contacts WHERE id=?',[$id]); if(!$c){flash('danger','Contacto no encontrado.');redirect_to(base_url('contacts'));}
    $orders=db_all($pdo,'SELECT * FROM mg_orders WHERE contact_id=? ORDER BY id DESC LIMIT 10',[$id]); $quotes=db_all($pdo,'SELECT * FROM mg_quotes WHERE contact_id=? ORDER BY id DESC LIMIT 10',[$id]); $notes=db_all($pdo,'SELECT n.*,u.full_name admin_name FROM mg_contact_notes n LEFT JOIN mg_admin_users u ON u.id=n.admin_user_id WHERE n.contact_id=? ORDER BY n.id DESC',[$id]);
    render_header('Contacto','contacts'); ?><div class="grid two"><div class="card"><h3><?=h($c['full_name']?:$c['profile_name']?:'Sin nombre')?></h3><p><strong>WhatsApp:</strong> <?=h($c['phone'])?></p><p><strong>Correo:</strong> <?=h($c['email']?:'—')?></p><p><strong>Empresa:</strong> <?=h($c['company']?:'—')?></p><p><strong>Ciudad:</strong> <?=h($c['city']?:'—')?></p><p><strong>Última interacción:</strong> <?=h(fmt_dt($c['last_inbound_at']))?></p><a class="btn primary" href="<?=h(base_url('index.php?page=conversation&id='.$id))?>">Ver conversación</a></div><div class="card"><h3>Nota interna</h3><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="add_contact_note"><input type="hidden" name="contact_id" value="<?=$id?>"><textarea name="note" placeholder="Seguimiento interno..." required></textarea><button class="btn dark">Guardar nota</button></form><?php foreach($notes as $n):?><p class="help"><strong><?=h($n['admin_name']?:'Sistema')?>:</strong> <?=nl2br(h($n['note']))?><br><?=h(fmt_dt($n['created_at']))?></p><?php endforeach;?></div></div><div class="section-title"><h3>Pedidos</h3></div><div class="table-wrap"><table><thead><tr><th>Folio</th><th>Productos</th><th>Ciudad</th><th>Tipo</th><th>Estado</th></tr></thead><tbody><?php foreach($orders as $r):?><tr><td><?=h($r['folio'])?></td><td><?=h($r['products_text'])?></td><td><?=h($r['city'])?></td><td><?=h($r['delivery_type']==='pickup'?'Recoger':'Envío')?></td><td><span class="badge <?=status_class($r['status'])?>"><?=h($r['status'])?></span></td></tr><?php endforeach;?></tbody></table></div><div class="section-title"><h3>Cotizaciones</h3></div><div class="table-wrap"><table><thead><tr><th>Folio</th><th>Empresa</th><th>Productos</th><th>Cantidad</th><th>Fecha</th><th>Estado</th></tr></thead><tbody><?php foreach($quotes as $r):?><tr><td><?=h($r['folio'])?></td><td><?=h($r['company']?:'—')?></td><td><?=h($r['products_text'])?></td><td><?=h($r['approximate_quantity'])?></td><td><?=h(fmt_date($r['needed_date']))?></td><td><span class="badge <?=status_class($r['status'])?>"><?=h($r['status'])?></span></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='products'){
    require_admin(); $rows=db_all($pdo,'SELECT * FROM mg_products ORDER BY sort_order,id'); $edit=null; if(!empty($_GET['edit']))$edit=db_one($pdo,'SELECT * FROM mg_products WHERE id=?',[(int)$_GET['edit']]);
    render_header('Productos','products'); ?><div class="grid two"><div class="card"><h3><?=$edit?'Editar producto':'Nuevo producto'?></h3><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>"><label>Nombre<input name="name" value="<?=h($edit['name']??'')?>" required></label><label>Nombre corto<input name="short_name" value="<?=h($edit['short_name']??'')?>"></label><label>Emoji<input name="emoji" value="<?=h($edit['emoji']??'')?>" maxlength="16"></label><label>Descripción<textarea name="description"><?=h($edit['description']??'')?></textarea></label><label>Orden<input type="number" name="sort_order" value="<?=h($edit['sort_order']??100)?>"></label><label><input style="width:auto" type="checkbox" name="active" value="1" <?=!isset($edit['active'])||$edit['active']?'checked':''?>> Activo</label><button class="btn primary">Guardar</button></form></div><div class="card"><h3>Uso en el bot</h3><p class="muted">El bot consulta esta tabla al mostrar el catálogo y las descripciones. Puedes cambiar productos sin redeploy.</p></div></div><div class="section-title"><h3>Catálogo</h3></div><div class="table-wrap"><table><thead><tr><th>Producto</th><th>Descripción</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=h(($r['emoji']?:'').' '.$r['name'])?></td><td><?=h($r['description'])?></td><td><span class="badge <?=$r['active']?'success':'danger'?>"><?=$r['active']?'Activo':'Inactivo'?></span></td><td class="actions"><a class="btn secondary" href="<?=h(base_url('index.php?page=products&edit='.$r['id']))?>">Editar</a><form method="post" onsubmit="return confirm('¿Eliminar producto?')"><?=csrf_input()?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn danger">Eliminar</button></form></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='orders'){
    $rows=db_all($pdo,'SELECT o.*,c.phone,c.email FROM mg_orders o JOIN mg_contacts c ON c.id=o.contact_id ORDER BY o.id DESC'); render_header('Pedidos','orders'); ?><div class="table-wrap"><table><thead><tr><th>Folio / cliente</th><th>Productos</th><th>Cantidades</th><th>Entrega</th><th>Estado / notas</th><th>Fecha</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=h($r['folio'])?></strong><br><?=h($r['full_name'])?><br><span class="muted"><?=h($r['phone'])?></span></td><td><?=nl2br(h($r['products_text']))?></td><td><?=nl2br(h($r['quantities_text']))?></td><td><?=h($r['city'])?><br><?=h($r['delivery_type']==='pickup'?'Recoger':'Envío')?></td><td><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="update_order"><input type="hidden" name="id" value="<?=$r['id']?>"><select name="status"><option value="pending" <?=$r['status']==='pending'?'selected':''?>>Pendiente</option><option value="confirmed" <?=$r['status']==='confirmed'?'selected':''?>>Confirmado</option><option value="closed" <?=$r['status']==='closed'?'selected':''?>>Cerrado</option><option value="cancelled" <?=$r['status']==='cancelled'?'selected':''?>>Cancelado</option></select><textarea name="notes" placeholder="Notas"><?=h($r['notes']??'')?></textarea><button class="btn secondary">Guardar</button></form></td><td><?=h(fmt_dt($r['created_at']))?></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='quotes'){
    $rows=db_all($pdo,'SELECT q.*,c.phone FROM mg_quotes q JOIN mg_contacts c ON c.id=q.contact_id ORDER BY q.id DESC'); render_header('Cotizaciones','quotes'); ?><div class="table-wrap"><table><thead><tr><th>Folio / cliente</th><th>Empresa</th><th>Productos</th><th>Cantidad</th><th>Se necesita</th><th>Estado / notas</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=h($r['folio'])?></strong><br><?=h($r['full_name']?:'—')?><br><span class="muted"><?=h($r['phone'])?></span></td><td><?=h($r['company']?:'No aplica')?></td><td><?=nl2br(h($r['products_text']))?></td><td><?=h($r['approximate_quantity'])?></td><td><?=h(fmt_date($r['needed_date']))?></td><td><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="update_quote"><input type="hidden" name="id" value="<?=$r['id']?>"><select name="status"><option value="pending" <?=$r['status']==='pending'?'selected':''?>>Pendiente</option><option value="review" <?=$r['status']==='review'?'selected':''?>>En revisión</option><option value="quoted" <?=$r['status']==='quoted'?'selected':''?>>Cotizada</option><option value="closed" <?=$r['status']==='closed'?'selected':''?>>Cerrada</option><option value="cancelled" <?=$r['status']==='cancelled'?'selected':''?>>Cancelada</option></select><textarea name="notes" placeholder="Notas"><?=h($r['notes']??'')?></textarea><button class="btn secondary">Guardar</button></form></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='sale_points'){
    require_admin(); $rows=db_all($pdo,'SELECT * FROM mg_sale_points ORDER BY sort_order,id'); $edit=null; if(!empty($_GET['edit']))$edit=db_one($pdo,'SELECT * FROM mg_sale_points WHERE id=?',[(int)$_GET['edit']]); render_header('Puntos de venta','sale_points'); ?><div class="grid two"><div class="card"><h3><?=$edit?'Editar punto':'Nuevo punto de venta'?></h3><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_sale_point"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>"><label>Nombre<input name="name" value="<?=h($edit['name']??'')?>" required></label><label>Ciudad<input name="city" value="<?=h($edit['city']??'')?>" required></label><label>Municipio<input name="municipality" value="<?=h($edit['municipality']??'')?>"></label><label>Dirección<input name="address" value="<?=h($edit['address']??'')?>"></label><label>Horario<input name="schedule" value="<?=h($edit['schedule']??'')?>"></label><label>Teléfono<input name="phone" value="<?=h($edit['phone']??'')?>"></label><label>URL de mapa<input name="map_url" value="<?=h($edit['map_url']??'')?>"></label><label>Orden<input type="number" name="sort_order" value="<?=h($edit['sort_order']??100)?>"></label><label><input style="width:auto" type="checkbox" name="active" value="1" <?=!isset($edit['active'])||$edit['active']?'checked':''?>> Activo</label><button class="btn primary">Guardar</button></form></div><div class="card"><h3>Respuesta del bot</h3><p class="muted">Cuando el usuario escriba su ciudad o municipio, el bot buscará coincidencias activas aquí y responderá con los puntos disponibles.</p></div></div><div class="section-title"><h3>Ubicaciones</h3></div><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Ciudad / municipio</th><th>Dirección</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=h($r['name'])?></strong></td><td><?=h($r['city'])?><?= $r['municipality']?' / '.h($r['municipality']):''?></td><td><?=h($r['address']?:'—')?></td><td><span class="badge <?=$r['active']?'success':'danger'?>"><?=$r['active']?'Activo':'Inactivo'?></span></td><td class="actions"><a class="btn secondary" href="<?=h(base_url('index.php?page=sale_points&edit='.$r['id']))?>">Editar</a><form method="post" onsubmit="return confirm('¿Eliminar punto?')"><?=csrf_input()?><input type="hidden" name="action" value="delete_sale_point"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn danger">Eliminar</button></form></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='point_queries'){
    $rows=db_all($pdo,'SELECT q.*,c.full_name,c.profile_name,c.phone FROM mg_sale_point_queries q JOIN mg_contacts c ON c.id=q.contact_id ORDER BY q.id DESC'); render_header('Consultas de puntos de venta','point_queries'); ?><div class="table-wrap"><table><thead><tr><th>Contacto</th><th>Ciudad consultada</th><th>Coincidencias</th><th>Fecha</th><th>Estado / notas</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=h($r['full_name']?:$r['profile_name']?:'Sin nombre')?><br><span class="muted"><?=h($r['phone'])?></span></td><td><?=h($r['city_query'])?></td><td><?=h($r['matched_points'])?></td><td><?=h(fmt_dt($r['created_at']))?></td><td><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="update_point_query"><input type="hidden" name="id" value="<?=$r['id']?>"><select name="status"><option value="resolved_bot" <?=$r['status']==='resolved_bot'?'selected':''?>>Resuelta por bot</option><option value="pending" <?=$r['status']==='pending'?'selected':''?>>Pendiente</option><option value="contacted" <?=$r['status']==='contacted'?'selected':''?>>Contactado</option><option value="closed" <?=$r['status']==='closed'?'selected':''?>>Cerrada</option></select><textarea name="notes"><?=h($r['notes']??'')?></textarea><button class="btn secondary">Guardar</button></form></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='conversations'){
    $rows=db_all($pdo,'SELECT c.id contact_id,c.full_name,c.profile_name,c.phone,v.current_step,v.last_message_at,(SELECT COUNT(*) FROM mg_messages m WHERE m.contact_id=c.id) total FROM mg_contacts c JOIN mg_conversations v ON v.contact_id=c.id ORDER BY v.last_message_at DESC'); render_header('Conversaciones','conversations'); ?><div class="table-wrap"><table><thead><tr><th>Contacto</th><th>WhatsApp</th><th>Paso</th><th>Mensajes</th><th>Última actividad</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=h($r['full_name']?:$r['profile_name']?:'Sin nombre')?></td><td><?=h($r['phone'])?></td><td><?=h($r['current_step'])?></td><td><?=h($r['total'])?></td><td><?=h(fmt_dt($r['last_message_at']))?></td><td><a class="btn secondary" href="<?=h(base_url('index.php?page=conversation&id='.$r['contact_id']))?>">Abrir</a></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='conversation'){
    $cid=(int)($_GET['id']??0); $c=db_one($pdo,'SELECT * FROM mg_contacts WHERE id=?',[$cid]); if(!$c){flash('danger','Contacto no encontrado.');redirect_to(base_url('conversaciones'));} $rows=db_all($pdo,'SELECT * FROM mg_messages WHERE contact_id=? ORDER BY id ASC LIMIT 1000',[$cid]); render_header('Conversación','conversations'); ?><div class="conversation"><h3><?=h($c['full_name']?:$c['profile_name']?:$c['phone'])?></h3><?php foreach($rows as $r):?><div class="bubble <?=$r['direction']==='IN'?'in':'out'?>"><strong><?=h($r['direction']==='IN'?'Usuario':$r['sender_type'])?></strong><br><?=nl2br(h($r['body']??''))?><br><span class="muted"><?=h(fmt_dt($r['created_at']))?></span></div><?php endforeach;?></div><?php render_footer();exit;
}

if($page==='messages'){
    $contacts=db_all($pdo,'SELECT id,COALESCE(NULLIF(full_name,""),NULLIF(profile_name,""),phone) display_name,phone,email FROM mg_contacts WHERE active=1 ORDER BY display_name'); $template=setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']??'marketing_meligil_texto'); $lang=setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']??'es_MX'); render_header('Plantillas y mensajes','messages'); ?><div class="grid two"><div class="card"><h3>Enviar a un usuario</h3><p class="help">La plantilla de Meta usa una sola variable. Todo el texto siguiente se enviará en <code>{{1}}</code>.</p><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="send_template"><label>Usuario<select name="contact_id" required><option value="">Selecciona...</option><?php foreach($contacts as $c):?><option value="<?=$c['id']?>"><?=h($c['display_name'].' · '.$c['phone'].($c['email']?' · '.$c['email']:''))?></option><?php endforeach;?></select></label><label>Plantilla<input value="<?=h($template)?>" disabled></label><label>Idioma<input value="<?=h($lang)?>" disabled></label><label>Mensaje completo para {{1}}<textarea name="body_text" required placeholder="Hola 👋 Tenemos información importante para ti..."></textarea></label><button class="btn primary">Enviar plantilla</button></form></div><div class="card"><h3>Enviar a todos</h3><p class="help">Se enviará únicamente a los contactos activos registrados en el CRM. La Function procesa cada contacto por separado.</p><form method="post" onsubmit="return confirm('¿Enviar este mensaje a todos los contactos activos?')"><?=csrf_input()?><input type="hidden" name="action" value="send_template_bulk"><label>Plantilla<input value="<?=h($template)?>" disabled></label><label>Mensaje completo para {{1}}<textarea name="body_text" required></textarea></label><button class="btn dark">Enviar a todos</button></form></div></div><?php render_footer();exit;
}

if($page==='settings'){
    require_admin(); $template=setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']??'marketing_meligil_texto'); $lang=setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']??'es_MX'); $url=setting_nonempty($pdo,'firebase_crm_url',$config['firebase_crm_url']??''); render_header('Configuración','settings'); ?><div class="grid two"><div class="card"><h3>WhatsApp / Meta</h3><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_settings"><label>Plantilla aprobada<input name="marketing_template_name" value="<?=h($template)?>" required></label><label>Idioma<input name="marketing_template_language" value="<?=h($lang)?>" required></label><label>Endpoint crmSendMeligil<input name="firebase_crm_url" value="<?=h($url)?>" required></label><button class="btn primary">Guardar configuración</button></form></div><div class="card"><h3>Prueba de conexión</h3><p class="help">Comprueba Web → Firebase → MySQL usando el admin token configurado en <code>config.php</code>.</p><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="test_firebase"><button class="btn dark">Probar conexión</button></form></div></div><?php render_footer();exit;
}

if($page==='profile'){
    render_header('Mi cuenta',''); ?><div class="card" style="max-width:600px"><h3>Cambiar contraseña</h3><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="change_password"><label>Contraseña actual<input type="password" name="current_password" required></label><label>Nueva contraseña<input type="password" name="new_password" minlength="10" required></label><label>Confirmar<input type="password" name="confirm_password" minlength="10" required></label><button class="btn primary">Actualizar contraseña</button></form></div><?php render_footer();exit;
}
