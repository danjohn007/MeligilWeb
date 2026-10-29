<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/layout.php';
require_auth();
$user=current_user();
$page=preg_replace('/[^a-z_]/','',$_GET['page']??'dashboard');
$allowed=['dashboard','contacts','contact','products','orders','quotes','sale_points','point_queries','messages','settings','users','profile'];
if(!in_array($page,$allowed,true))$page='dashboard';
if($page==='users' && !is_admin()) redirect_to(base_url('index.php?page='.first_allowed_page()));
if($page!=='users' && $page!=='profile'){ $module=page_module($page); if($module) require_can($module,'view'); }

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'';
    try{
        if($action==='send_template'){
            require_can('messages','edit');
            $cid=(int)($_POST['contact_id']??0); $body=trim((string)($_POST['body_text']??''));
            if(!$cid)throw new RuntimeException('Selecciona un contacto.'); if($body==='')throw new RuntimeException('Escribe el mensaje que se enviará en {{1}}.');
            $c=db_one($pdo,'SELECT id,phone FROM mg_contacts WHERE id=? AND active=1',[$cid]); if(!$c)throw new RuntimeException('El contacto no existe o está inactivo.');
            $name=setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']??'marketing_meligil_texto');
            $lang=setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']??'es_MX');
            $r=firebase_post(firebase_runtime_config($pdo,$config),['action'=>'template','contact_id'=>$cid,'phone'=>$c['phone'],'template_name'=>$name,'language'=>$lang,'body_text'=>$body]);
            if(!$r['ok'])throw new RuntimeException($r['data']['error']??('No se pudo enviar. HTTP '.$r['http']));
            flash('success','Plantilla enviada correctamente.'); redirect_to(base_url('index.php?page=messages'));
        }
        if($action==='send_template_bulk'){
            require_can('messages','edit'); $body=trim((string)($_POST['body_text']??'')); if($body==='')throw new RuntimeException('Escribe el mensaje que se enviará en {{1}}.');
            $name=setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']??'marketing_meligil_texto');
            $lang=setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']??'es_MX');
            $r=firebase_post(firebase_runtime_config($pdo,$config),['action'=>'template_bulk','template_name'=>$name,'language'=>$lang,'body_text'=>$body]);
            if($r['http']<200||$r['http']>=300)throw new RuntimeException($r['data']['error']??('No se pudo completar el envío masivo. HTTP '.$r['http']));
            flash(($r['data']['failed']??0)?'warning':'success','Envío masivo terminado: '.(int)($r['data']['sent']??0).' enviados; '.(int)($r['data']['failed']??0).' con error.'); redirect_to(base_url('index.php?page=messages'));
        }
        if($action==='send_template_segment'){
            require_can('messages','edit');
            $body=trim((string)($_POST['body_text']??''));
            $segment=(string)($_POST['segment']??'');
            $allowedSegments=['prospecto','prospecto_recurrente','cliente','cliente_recurrente'];
            if($body==='') throw new RuntimeException('Escribe el mensaje que se enviará en {{1}}.');
            if(!in_array($segment,$allowedSegments,true)) throw new RuntimeException('Selecciona un segmento válido del Customer Journey.');
            $name=setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']??'marketing_meligil_texto');
            $lang=setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']??'es_MX');
            $targets=customer_journey_contacts($pdo,$segment,200);
            if(!$targets) throw new RuntimeException('No hay contactos disponibles en el segmento '.customer_journey_stage_label($segment).'.');
            $sent=0; $failed=0;
            foreach($targets as $c){
                $r=firebase_post(firebase_runtime_config($pdo,$config),['action'=>'template','contact_id'=>(int)$c['id'],'phone'=>$c['phone'],'template_name'=>$name,'language'=>$lang,'body_text'=>$body]);
                $r['ok']?$sent++:$failed++;
            }
            $label=customer_journey_stage_label($segment);
            flash($failed?'warning':'success',"Envío a {$label} terminado: {$sent} enviados; {$failed} con error."); redirect_to(base_url('index.php?page=messages'));
        }
        if($action==='save_product'){
            require_can('products','edit'); $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $short=trim($_POST['short_name']??''); $emoji=trim($_POST['emoji']??''); $desc=trim($_POST['description']??''); $active=!empty($_POST['active'])?1:0; $sort=(int)($_POST['sort_order']??100);
            if($name==='')throw new RuntimeException('El nombre del producto es obligatorio.');
            if($id)$pdo->prepare('UPDATE mg_products SET name=?,short_name=?,emoji=?,description=?,active=?,sort_order=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$name,$short?:null,$emoji?:null,$desc?:null,$active,$sort,$id]);
            else $pdo->prepare('INSERT INTO mg_products(name,short_name,emoji,description,active,sort_order) VALUES(?,?,?,?,?,?)')->execute([$name,$short?:null,$emoji?:null,$desc?:null,$active,$sort]);
            flash('success','Producto guardado. El bot tomará el catálogo directamente de la BD.'); redirect_to(base_url('index.php?page=products'));
        }
        if($action==='delete_product'){
            require_admin(); $id=(int)($_POST['id']??0); $pdo->prepare('DELETE FROM mg_products WHERE id=?')->execute([$id]); flash('success','Producto eliminado.'); redirect_to(base_url('index.php?page=products'));
        }
        if($action==='save_sale_point_category'){
            require_can('sale_points','edit');
            $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $description=trim($_POST['description']??''); $active=!empty($_POST['active'])?1:0; $sort=(int)($_POST['sort_order']??100);
            if($name==='') throw new RuntimeException('Escribe el nombre de la categoría o zona.');
            $dup=(int)db_value($pdo,'SELECT COUNT(*) FROM mg_sale_point_categories WHERE name=? AND id<>?',[$name,$id]);
            if($dup) throw new RuntimeException('Ya existe una categoría o zona con ese nombre.');
            if($id) $pdo->prepare('UPDATE mg_sale_point_categories SET name=?,description=?,active=?,sort_order=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$name,$description?:null,$active,$sort,$id]);
            else $pdo->prepare('INSERT INTO mg_sale_point_categories(name,description,active,sort_order) VALUES(?,?,?,?)')->execute([$name,$description?:null,$active,$sort]);
            flash('success','Categoría / zona guardada.'); redirect_to(base_url('index.php?page=sale_points'));
        }
        if($action==='toggle_sale_point_category'){
            require_can('sale_points','edit'); $id=(int)($_POST['id']??0);
            $pdo->prepare('UPDATE mg_sale_point_categories SET active=IF(active=1,0,1),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
            flash('success','Estado de la categoría actualizado.'); redirect_to(base_url('index.php?page=sale_points'));
        }
        if($action==='delete_sale_point_category'){
            require_admin(); $id=(int)($_POST['id']??0);
            $used=(int)db_value($pdo,'SELECT COUNT(*) FROM mg_sale_points WHERE category_id=?',[$id]);
            if($used>0) throw new RuntimeException('No puedes eliminar esta categoría porque todavía tiene puntos de venta asignados. Reasígnalos primero.');
            $pdo->prepare('DELETE FROM mg_sale_point_categories WHERE id=?')->execute([$id]);
            flash('success','Categoría / zona eliminada.'); redirect_to(base_url('index.php?page=sale_points'));
        }
        if($action==='save_sale_point'){
            require_can('sale_points','edit'); $id=(int)($_POST['id']??0); $categoryId=(int)($_POST['category_id']??0); $name=trim($_POST['name']??''); $city=trim($_POST['city']??''); $municipality=trim($_POST['municipality']??''); $address=trim($_POST['address']??''); $schedule=trim($_POST['schedule']??''); $phone=trim($_POST['phone']??''); $map=trim($_POST['map_url']??''); $active=!empty($_POST['active'])?1:0; $sort=(int)($_POST['sort_order']??100);
            if(!$categoryId) throw new RuntimeException('Selecciona una categoría o zona para el punto de venta.');
            if(!(int)db_value($pdo,'SELECT COUNT(*) FROM mg_sale_point_categories WHERE id=?',[$categoryId])) throw new RuntimeException('La categoría seleccionada no existe.');
            if($name===''||$city==='')throw new RuntimeException('Nombre y ciudad son obligatorios.'); if($map!==''&&!filter_var($map,FILTER_VALIDATE_URL))throw new RuntimeException('La URL del mapa no es válida.');
            if($id)$pdo->prepare('UPDATE mg_sale_points SET category_id=?,name=?,city=?,municipality=?,address=?,schedule=?,phone=?,map_url=?,active=?,sort_order=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$categoryId,$name,$city,$municipality?:null,$address?:null,$schedule?:null,$phone?:null,$map?:null,$active,$sort,$id]);
            else $pdo->prepare('INSERT INTO mg_sale_points(category_id,name,city,municipality,address,schedule,phone,map_url,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$categoryId,$name,$city,$municipality?:null,$address?:null,$schedule?:null,$phone?:null,$map?:null,$active,$sort]);
            flash('success','Punto de venta guardado y asignado a su categoría / zona.'); redirect_to(base_url('index.php?page=sale_points'));
        }
        if($action==='delete_sale_point'){
            require_admin(); $id=(int)($_POST['id']??0); $pdo->prepare('DELETE FROM mg_sale_points WHERE id=?')->execute([$id]); flash('success','Punto de venta eliminado.'); redirect_to(base_url('index.php?page=sale_points'));
        }
        if($action==='update_order'){
            require_can('orders','edit');
            $id=(int)($_POST['id']??0); $status=$_POST['status']??'pending'; $notes=trim($_POST['notes']??''); if(!in_array($status,['pending','confirmed','closed','cancelled'],true))throw new RuntimeException('Estado inválido.');
            $pdo->prepare('UPDATE mg_orders SET status=?,notes=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,$notes?:null,$id]); flash('success','Pedido actualizado.'); redirect_to(base_url('index.php?page=orders'));
        }
        if($action==='update_quote'){
            require_can('quotes','edit');
            $id=(int)($_POST['id']??0); $status=$_POST['status']??'pending'; $notes=trim($_POST['notes']??''); if(!in_array($status,['pending','review','quoted','closed','cancelled'],true))throw new RuntimeException('Estado inválido.');
            $pdo->prepare('UPDATE mg_quotes SET status=?,notes=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,$notes?:null,$id]); flash('success','Cotización actualizada.'); redirect_to(base_url('index.php?page=quotes'));
        }
        if($action==='update_point_query'){
            require_can('point_queries','edit');
            $id=(int)($_POST['id']??0); $status=$_POST['status']??'pending'; $notes=trim($_POST['notes']??''); if(!in_array($status,['resolved_bot','pending','contacted','closed'],true))throw new RuntimeException('Estado inválido.');
            $pdo->prepare('UPDATE mg_sale_point_queries SET status=?,notes=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,$notes?:null,$id]); flash('success','Consulta actualizada.'); redirect_to(base_url('index.php?page=point_queries'));
        }
        if($action==='add_contact_note'){
            require_can('contacts','edit');
            $cid=(int)($_POST['contact_id']??0); $note=trim($_POST['note']??''); if(!$cid||$note==='')throw new RuntimeException('Escribe una nota.');
            $pdo->prepare('INSERT INTO mg_contact_notes(contact_id,admin_user_id,note) VALUES(?,?,?)')->execute([$cid,$user['id'],$note]); flash('success','Nota guardada.'); redirect_to(base_url('index.php?page=contact&id='.$cid));
        }
        if($action==='convert_contact_to_client'){
            require_can('contacts','edit');
            $cid=(int)($_POST['contact_id']??0);
            if(!$cid) throw new RuntimeException('Contacto inválido.');
            if(!column_exists($pdo,'mg_contacts','client_converted_at')) throw new RuntimeException('Falta ejecutar la migración 13 de Customer Journey.');
            $exists=(int)db_value($pdo,'SELECT COUNT(*) FROM mg_contacts WHERE id=?',[$cid]); if(!$exists) throw new RuntimeException('Contacto no encontrado.');
            $pdo->prepare('UPDATE mg_contacts SET client_converted_at=COALESCE(client_converted_at,UTC_TIMESTAMP()),client_converted_by=COALESCE(client_converted_by,?),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([(int)$user['id'],$cid]);
            $metrics=customer_journey_contact($pdo,$cid); $stage=$metrics?customer_journey_stage($metrics):'cliente';
            flash('success',$stage==='cliente_recurrente'?'Contacto convertido. Por sus compras acumuladas ya corresponde a Cliente recurrente.':'Contacto convertido manualmente en Cliente.');
            redirect_to(base_url('index.php?page=contact&id='.$cid));
        }
        if($action==='save_settings'){
            require_can('settings','edit'); $template=trim($_POST['marketing_template_name']??'marketing_meligil_texto')?:'marketing_meligil_texto'; $lang=trim($_POST['marketing_template_language']??'es_MX')?:'es_MX'; $url=trim($_POST['firebase_crm_url']??''); if($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))throw new RuntimeException('La URL de Firebase no es válida.');
            $s=$pdo->prepare('INSERT INTO mg_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); $s->execute(['marketing_template_name',$template]); $s->execute(['marketing_template_language',$lang]); $s->execute(['firebase_crm_url',$url]);
            flash('success','Configuración guardada.'); redirect_to(base_url('index.php?page=settings'));
        }
        if($action==='test_firebase'){
            require_can('settings','edit'); $r=firebase_post(firebase_runtime_config($pdo,$config),['action'=>'health']); flash($r['ok']?'success':'danger',$r['ok']?'Conexión Web → Firebase → MySQL correcta.':($r['data']['error']??('No se pudo conectar. HTTP '.$r['http']))); redirect_to(base_url('index.php?page=settings'));
        }
        if($action==='save_user'){
            require_admin(); $id=(int)($_POST['id']??0); $first=trim($_POST['first_name']??''); $last=trim($_POST['last_name']??''); $email=mb_strtolower(trim($_POST['email']??'')); $role=$_POST['role']??'user'; $active=!empty($_POST['active'])?1:0; $password=(string)($_POST['password']??'');
            if($first===''||$last===''||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Nombre, apellidos y correo válido son obligatorios.');
            if(!in_array($role,['admin','user'],true)) throw new RuntimeException('Tipo de cuenta inválido.');
            $dup=(int)db_value($pdo,'SELECT COUNT(*) FROM mg_admin_users WHERE email=? AND id<>?',[$email,$id]); if($dup) throw new RuntimeException('Ese correo ya está registrado.');
            $full=trim($first.' '.$last);
            if($id){
                $target=db_one($pdo,'SELECT * FROM mg_admin_users WHERE id=?',[$id]); if(!$target) throw new RuntimeException('Colaborador no encontrado.');
                if($id===(int)$user['id'] && (!$active||$role!=='admin')) throw new RuntimeException('No puedes retirar tu propio acceso de Administrador desde esta pantalla.');
                if(($target['role']??'')==='admin' && ($role!=='admin'||!$active)){ $admins=(int)db_value($pdo,"SELECT COUNT(*) FROM mg_admin_users WHERE role='admin' AND active=1 AND id<>?",[$id]); if($admins<1) throw new RuntimeException('Debe existir al menos un Administrador activo.'); }
                $params=[$first,$last,$full,$email,$role,$active]; $sql='UPDATE mg_admin_users SET first_name=?,last_name=?,full_name=?,email=?,role=?,active=?';
                if($password!==''){ if(strlen($password)<8) throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.'); $sql.=',password_hash=?'; $params[]=password_hash($password,PASSWORD_DEFAULT); }
                $sql.=' WHERE id=?'; $params[]=$id; $pdo->prepare($sql)->execute($params);
                sync_user_permissions($pdo,$id,$role,(array)($_POST['permissions']??[])); flash('success','Colaborador actualizado.');
            } else {
                if(strlen($password)<8) throw new RuntimeException('La contraseña temporal debe tener al menos 8 caracteres.');
                $pdo->prepare('INSERT INTO mg_admin_users(email,password_hash,full_name,first_name,last_name,role,active) VALUES(?,?,?,?,?,?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT),$full,$first,$last,$role,$active]);
                $id=(int)$pdo->lastInsertId(); sync_user_permissions($pdo,$id,$role,(array)($_POST['permissions']??[])); flash('success','Colaborador creado correctamente.');
            }
            redirect_to(base_url('colaboradores'));
        }
        if($action==='toggle_user'){
            require_admin(); $id=(int)($_POST['id']??0); if($id===(int)$user['id']) throw new RuntimeException('No puedes desactivar tu propia cuenta.'); $target=db_one($pdo,'SELECT id,role,active FROM mg_admin_users WHERE id=?',[$id]); if(!$target) throw new RuntimeException('Colaborador no encontrado.');
            if(($target['role']??'')==='admin' && (int)$target['active']===1){ $admins=(int)db_value($pdo,"SELECT COUNT(*) FROM mg_admin_users WHERE role='admin' AND active=1 AND id<>?",[$id]); if($admins<1) throw new RuntimeException('Debe existir al menos un Administrador activo.'); }
            $pdo->prepare('UPDATE mg_admin_users SET active=IF(active=1,0,1),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]); flash('success','Estado del colaborador actualizado.'); redirect_to(base_url('colaboradores'));
        }
        if($action==='delete_user'){
            require_admin(); $id=(int)($_POST['id']??0); if($id===(int)$user['id']) throw new RuntimeException('No puedes eliminar tu propia cuenta.'); $target=db_one($pdo,'SELECT id,role,active FROM mg_admin_users WHERE id=?',[$id]); if(!$target) throw new RuntimeException('Colaborador no encontrado.');
            if(($target['role']??'')==='admin'){ $admins=(int)db_value($pdo,"SELECT COUNT(*) FROM mg_admin_users WHERE role='admin' AND active=1 AND id<>?",[$id]); if($admins<1) throw new RuntimeException('Debe existir al menos un Administrador activo.'); }
            $pdo->prepare('DELETE FROM mg_admin_users WHERE id=?')->execute([$id]); flash('success','Colaborador eliminado.'); redirect_to(base_url('colaboradores'));
        }
        if($action==='update_profile'){
            $first=trim($_POST['first_name']??''); $last=trim($_POST['last_name']??''); $email=mb_strtolower(trim($_POST['email']??''));
            if($first===''||$last===''||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Nombre, apellidos y correo válido son obligatorios.');
            $dup=(int)db_value($pdo,'SELECT COUNT(*) FROM mg_admin_users WHERE email=? AND id<>?',[$email,(int)$user['id']]); if($dup) throw new RuntimeException('Ese correo ya está registrado.');
            $row=db_one($pdo,'SELECT profile_photo FROM mg_admin_users WHERE id=?',[(int)$user['id']]); $photo=profile_upload($pdo,$_FILES['profile_photo']??[], $row['profile_photo']??null); $full=trim($first.' '.$last);
            $pdo->prepare('UPDATE mg_admin_users SET first_name=?,last_name=?,full_name=?,email=?,profile_photo=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$first,$last,$full,$email,$photo,(int)$user['id']]); refresh_session_user($pdo); flash('success','Datos de perfil actualizados.'); redirect_to(base_url('index.php?page=profile'));
        }
        if($action==='change_password'){
            $current=(string)($_POST['current_password']??''); $new=(string)($_POST['new_password']??''); $confirm=(string)($_POST['confirm_password']??''); if(strlen($new)<8)throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.'); if($new!==$confirm)throw new RuntimeException('Las contraseñas nuevas no coinciden.');
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

    $journeyCounts=customer_journey_counts($pdo);
    $funnel=[
      'Prospecto'=>$journeyCounts['prospecto'],
      'Prospecto recurrente'=>$journeyCounts['prospecto_recurrente'],
      'Cliente'=>$journeyCounts['cliente'],
      'Cliente recurrente'=>$journeyCounts['cliente_recurrente'],
    ];
    $clientTotal=$journeyCounts['cliente']+$journeyCounts['cliente_recurrente'];
    $conversion=$m['contactos']>0?round(($clientTotal/$m['contactos'])*100,1):0;

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
      <div class="journey-title"><div><h3>Customer Journey comercial</h3><p>Clasificación de los contactos según recurrencia, conversión manual y compras confirmadas.</p></div><div class="journey-conversion"><span>Conversión a cliente</span><strong><?=$conversion?>%</strong></div></div>
      <div class="journey-grid">
        <?php $jn=0; foreach($funnel as $label=>$value): $jn++; $key=['Prospecto'=>'prospecto','Prospecto recurrente'=>'prospecto_recurrente','Cliente'=>'cliente','Cliente recurrente'=>'cliente_recurrente'][$label]; ?>
          <div class="journey-step"><div class="journey-step-top"><span class="step-no"><?=$jn?></span><strong><?=$value?></strong></div><h4><?=h($label)?></h4><p><?=h(customer_journey_stage_description($key))?></p></div>
        <?php endforeach; ?>
      </div>
      <div class="journey-note">Prospecto = 1.er y 2.º contacto · Prospecto recurrente = desde el 3.er contacto · Cliente = conversión manual por un Colaborador · Cliente recurrente = 3.ª compra confirmada o cerrada.</div>
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
      <section class="summary-card"><h3>Conversión de contactos a clientes</h3><p>Clientes con compra confirmada</p><strong class="big-percent"><?=$conversion?>%</strong><div class="mini-progress"><span style="width:<?=min(100,$conversion)?>%"></span></div><small><?=$clientTotal?> clientes de <?=$m['contactos']?> contactos</small></section>
      <?php foreach($activity as $a): $pct=$a['total']?round(($a['done']/$a['total'])*100):0; ?>
        <section class="summary-card"><h3>Atención de <?=h(strtolower($a['label']))?></h3><p>Atendidos vs. por atender</p><div class="attention-numbers"><strong><?=$a['done']?> / <?=$a['open']?></strong><span><?=$a['done']?> atendidos · <?=$a['open']?> por atender</span></div><div class="mini-progress"><span style="width:<?=$pct?>%"></span></div><small><?=$a['total']?> registros totales</small></section>
      <?php endforeach; ?>
    </div>

    <div class="section-title"><h2>Actividad reciente</h2></div><div class="table-wrap"><table><thead><tr><th>Contacto</th><th>WhatsApp</th><th>Ciudad</th><th>Última interacción</th><th></th></tr></thead><tbody><?php foreach($latest as $r):?><tr><td><strong><?=h($r['full_name']?:$r['profile_name']?:'Sin nombre')?></strong></td><td><?=h($r['phone'])?></td><td><?=h($r['city']?:'—')?></td><td><?=h(fmt_dt($r['last_inbound_at']))?></td><td><a class="btn secondary" href="<?=h(base_url('index.php?page=contact&id='.$r['id']))?>">Ver</a></td></tr><?php endforeach;?></tbody></table></div>
    <?php render_footer();exit;
}

if($page==='contacts'){
    $q=trim($_GET['q']??''); $params=[]; $sql='SELECT * FROM mg_contacts'; if($q!==''){ $sql.=' WHERE full_name LIKE ? OR profile_name LIKE ? OR phone LIKE ? OR email LIKE ? OR company LIKE ?'; $like='%'.$q.'%';$params=array_fill(0,5,$like); } $sql.=' ORDER BY last_inbound_at DESC,id DESC'; $rows=db_all($pdo,$sql,$params);
    $metricRows=customer_journey_metric_rows($pdo,false,false); $metricMap=[]; foreach($metricRows as $mr)$metricMap[(int)$mr['id']]=$mr;
    render_header('Contactos','contacts'); ?><form class="toolbar"><input type="hidden" name="page" value="contacts"><input name="q" value="<?=h($q)?>" placeholder="Buscar por nombre, teléfono, correo o empresa"><button class="btn dark">Buscar</button></form><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>WhatsApp</th><th>Customer Journey</th><th>Correo</th><th>Empresa</th><th>Ciudad</th><th>Última interacción</th><th></th></tr></thead><tbody><?php foreach($rows as $r):$jm=$metricMap[(int)$r['id']]??['contact_count'=>1,'purchase_count'=>0,'client_converted_at'=>null];$stage=customer_journey_stage($jm);?><tr><td><strong><?=h($r['full_name']?:$r['profile_name']?:'Sin nombre')?></strong></td><td><?=h($r['phone'])?></td><td><span class="badge neutral"><?=h(customer_journey_stage_label($stage))?></span><br><span class="help"><?=number_format((int)$jm['contact_count'])?> contactos · <?=number_format((int)$jm['purchase_count'])?> compras</span></td><td><?=h($r['email']?:'—')?></td><td><?=h($r['company']?:'—')?></td><td><?=h($r['city']?:'—')?></td><td><?=h(fmt_dt($r['last_inbound_at']))?></td><td><a class="btn secondary" href="<?=h(base_url('index.php?page=contact&id='.$r['id']))?>">Ver</a></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='contact'){
    $id=(int)($_GET['id']??0); $c=db_one($pdo,'SELECT * FROM mg_contacts WHERE id=?',[$id]); if(!$c){flash('danger','Contacto no encontrado.');redirect_to(base_url('contacts'));}
    $journey=customer_journey_contact($pdo,$id)??['contact_count'=>1,'purchase_count'=>0,'client_converted_at'=>null]; $stage=customer_journey_stage($journey);
    $orders=db_all($pdo,'SELECT * FROM mg_orders WHERE contact_id=? ORDER BY id DESC LIMIT 10',[$id]); $quotes=db_all($pdo,'SELECT * FROM mg_quotes WHERE contact_id=? ORDER BY id DESC LIMIT 10',[$id]); $notes=db_all($pdo,'SELECT n.*,u.full_name admin_name FROM mg_contact_notes n LEFT JOIN mg_admin_users u ON u.id=n.admin_user_id WHERE n.contact_id=? ORDER BY n.id DESC',[$id]);
    render_header('Contacto','contacts'); ?><div class="grid two"><div class="card"><h3><?=h($c['full_name']?:$c['profile_name']?:'Sin nombre')?></h3><p><strong>WhatsApp:</strong> <?=h($c['phone'])?></p><p><strong>Correo:</strong> <?=h($c['email']?:'—')?></p><p><strong>Empresa:</strong> <?=h($c['company']?:'—')?></p><p><strong>Ciudad:</strong> <?=h($c['city']?:'—')?></p><p><strong>Última interacción:</strong> <?=h(fmt_dt($c['last_inbound_at']))?></p><p><strong>Customer Journey:</strong> <span class="badge neutral"><?=h(customer_journey_stage_label($stage))?></span></p><p class="help"><?=h(customer_journey_stage_description($stage))?><br><?=number_format((int)$journey['contact_count'])?> contactos contabilizados · <?=number_format((int)$journey['purchase_count'])?> compras confirmadas/cerradas</p><?php if(can_edit('contacts') && in_array($stage,['prospecto','prospecto_recurrente'],true)):?><form method="post" onsubmit="return confirm('¿Convertir manualmente este contacto en Cliente?')"><?=csrf_input()?><input type="hidden" name="action" value="convert_contact_to_client"><input type="hidden" name="contact_id" value="<?=$id?>"><button class="btn secondary">Convertir en cliente</button></form><?php endif;?><?php if(can_view('messages')):?><a class="btn primary" href="<?=h(base_url('index.php?page=messages&contact_id='.$id))?>">Enviar mensaje</a><?php endif;?></div><div class="card"><h3>Nota interna</h3><?php if(can_edit('contacts')):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="add_contact_note"><input type="hidden" name="contact_id" value="<?=$id?>"><textarea name="note" placeholder="Seguimiento interno..." required></textarea><button class="btn dark">Guardar nota</button></form><?php else:?><p class="help">Tu cuenta tiene permiso de consulta. Para agregar notas se requiere Editar en Contactos.</p><?php endif;?><?php foreach($notes as $n):?><p class="help"><strong><?=h($n['admin_name']?:'Sistema')?>:</strong> <?=nl2br(h($n['note']))?><br><?=h(fmt_dt($n['created_at']))?></p><?php endforeach;?></div></div><div class="section-title"><h3>Pedidos</h3></div><div class="table-wrap"><table><thead><tr><th>Folio</th><th>Productos</th><th>Ciudad</th><th>Tipo</th><th>Estado</th></tr></thead><tbody><?php foreach($orders as $r):?><tr><td><?=h($r['folio'])?></td><td><?=h($r['products_text'])?></td><td><?=h($r['city'])?></td><td><?=h($r['delivery_type']==='pickup'?'Recoger':'Envío')?></td><td><span class="badge <?=status_class($r['status'])?>"><?=h($r['status'])?></span></td></tr><?php endforeach;?></tbody></table></div><div class="section-title"><h3>Cotizaciones</h3></div><div class="table-wrap"><table><thead><tr><th>Folio</th><th>Empresa</th><th>Productos</th><th>Cantidad</th><th>Fecha</th><th>Estado</th></tr></thead><tbody><?php foreach($quotes as $r):?><tr><td><?=h($r['folio'])?></td><td><?=h($r['company']?:'—')?></td><td><?=h($r['products_text'])?></td><td><?=h($r['approximate_quantity'])?></td><td><?=h(fmt_date($r['needed_date']))?></td><td><span class="badge <?=status_class($r['status'])?>"><?=h($r['status'])?></span></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='products'){ $rows=db_all($pdo,'SELECT * FROM mg_products ORDER BY sort_order,id'); $edit=null; if(!empty($_GET['edit']))$edit=db_one($pdo,'SELECT * FROM mg_products WHERE id=?',[(int)$_GET['edit']]);
    render_header('Productos','products'); ?><div class="grid two"><div class="card"><h3><?=$edit?'Editar producto':'Nuevo producto'?></h3><?php if(can_edit('products')):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>"><label>Nombre<input name="name" value="<?=h($edit['name']??'')?>" required></label><label>Nombre corto<input name="short_name" value="<?=h($edit['short_name']??'')?>"></label><label>Emoji<input name="emoji" value="<?=h($edit['emoji']??'')?>" maxlength="16"></label><label>Descripción<textarea name="description"><?=h($edit['description']??'')?></textarea></label><label>Orden<input type="number" name="sort_order" value="<?=h($edit['sort_order']??100)?>"></label><label><input style="width:auto" type="checkbox" name="active" value="1" <?=!isset($edit['active'])||$edit['active']?'checked':''?>> Activo</label><button class="btn primary">Guardar</button></form><?php else:?><p class="help">Tienes permiso de consulta. Para crear o modificar productos se requiere Editar.</p><?php endif;?></div><div class="card"><h3>Uso en el bot</h3><p class="muted">El bot consulta esta tabla al mostrar el catálogo y las descripciones. Puedes cambiar productos sin redeploy.</p></div></div><div class="section-title"><h3>Catálogo</h3></div><div class="table-wrap"><table><thead><tr><th>Producto</th><th>Descripción</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=h(($r['emoji']?:'').' '.$r['name'])?></td><td><?=h($r['description'])?></td><td><span class="badge <?=$r['active']?'success':'danger'?>"><?=$r['active']?'Activo':'Inactivo'?></span></td><td class="actions"><?php if(can_edit('products')):?><a class="btn secondary" href="<?=h(base_url('index.php?page=products&edit='.$r['id']))?>">Editar</a><?php endif;?><?php if(is_admin()):?><form method="post" onsubmit="return confirm('¿Eliminar producto?')"><?=csrf_input()?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn danger">Eliminar</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='orders'){
    $rows=db_all($pdo,'SELECT o.*,c.phone,c.email FROM mg_orders o JOIN mg_contacts c ON c.id=o.contact_id ORDER BY o.id DESC'); render_header('Pedidos','orders'); ?><div class="table-wrap"><table><thead><tr><th>Folio / cliente</th><th>Productos</th><th>Cantidades</th><th>Entrega</th><th>Estado / notas</th><th>Fecha</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=h($r['folio'])?></strong><br><?=h($r['full_name'])?><br><span class="muted"><?=h($r['phone'])?></span></td><td><?=nl2br(h($r['products_text']))?></td><td><?=nl2br(h($r['quantities_text']))?></td><td><?=h($r['city'])?><br><?=h($r['delivery_type']==='pickup'?'Recoger':'Envío')?></td><td><?php if(can_edit('orders')):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="update_order"><input type="hidden" name="id" value="<?=$r['id']?>"><select name="status"><option value="pending" <?=$r['status']==='pending'?'selected':''?>>Pendiente</option><option value="confirmed" <?=$r['status']==='confirmed'?'selected':''?>>Confirmado</option><option value="closed" <?=$r['status']==='closed'?'selected':''?>>Cerrado</option><option value="cancelled" <?=$r['status']==='cancelled'?'selected':''?>>Cancelado</option></select><textarea name="notes" placeholder="Notas"><?=h($r['notes']??'')?></textarea><button class="btn secondary">Guardar</button></form><?php else:?><span class="badge <?=status_class($r['status'])?>"><?=h($r['status'])?></span><?php if(!empty($r['notes'])):?><br><span class="help"><?=nl2br(h($r['notes']))?></span><?php endif;?><?php endif;?></td><td><?=h(fmt_dt($r['created_at']))?></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='quotes'){
    $rows=db_all($pdo,'SELECT q.*,c.phone FROM mg_quotes q JOIN mg_contacts c ON c.id=q.contact_id ORDER BY q.id DESC'); render_header('Cotizaciones','quotes'); ?><div class="table-wrap"><table><thead><tr><th>Folio / cliente</th><th>Empresa</th><th>Productos</th><th>Cantidad</th><th>Se necesita</th><th>Estado / notas</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=h($r['folio'])?></strong><br><?=h($r['full_name']?:'—')?><br><span class="muted"><?=h($r['phone'])?></span></td><td><?=h($r['company']?:'No aplica')?></td><td><?=nl2br(h($r['products_text']))?></td><td><?=h($r['approximate_quantity'])?></td><td><?=h(fmt_date($r['needed_date']))?></td><td><?php if(can_edit('quotes')):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="update_quote"><input type="hidden" name="id" value="<?=$r['id']?>"><select name="status"><option value="pending" <?=$r['status']==='pending'?'selected':''?>>Pendiente</option><option value="review" <?=$r['status']==='review'?'selected':''?>>En revisión</option><option value="quoted" <?=$r['status']==='quoted'?'selected':''?>>Cotizada</option><option value="closed" <?=$r['status']==='closed'?'selected':''?>>Cerrada</option><option value="cancelled" <?=$r['status']==='cancelled'?'selected':''?>>Cancelada</option></select><textarea name="notes" placeholder="Notas"><?=h($r['notes']??'')?></textarea><button class="btn secondary">Guardar</button></form><?php else:?><span class="badge <?=status_class($r['status'])?>"><?=h($r['status'])?></span><?php if(!empty($r['notes'])):?><br><span class="help"><?=nl2br(h($r['notes']))?></span><?php endif;?><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='sale_points'){
    $categories=db_all($pdo,'SELECT c.*,COUNT(p.id) AS point_count FROM mg_sale_point_categories c LEFT JOIN mg_sale_points p ON p.category_id=c.id GROUP BY c.id ORDER BY c.sort_order,c.name,c.id');
    $rows=db_all($pdo,'SELECT p.*,c.name AS category_name,c.active AS category_active FROM mg_sale_points p LEFT JOIN mg_sale_point_categories c ON c.id=p.category_id ORDER BY COALESCE(c.sort_order,9999),c.name,p.sort_order,p.id');
    $edit=null; if(!empty($_GET['edit']))$edit=db_one($pdo,'SELECT * FROM mg_sale_points WHERE id=?',[(int)$_GET['edit']]);
    $editCategory=null; if(!empty($_GET['edit_category']))$editCategory=db_one($pdo,'SELECT * FROM mg_sale_point_categories WHERE id=?',[(int)$_GET['edit_category']]);
    render_header('Puntos de venta','sale_points'); ?>
    <div class="grid two">
      <div class="card">
        <h3><?=$editCategory?'Editar categoría / zona':'Nueva categoría / zona'?></h3>
        <p class="help">Agrupa los puntos de venta por zona, ciudad, región o la clasificación que prefieras. El bot mostrará primero estas categorías.</p>
        <?php if(can_edit('sale_points')):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_sale_point_category"><input type="hidden" name="id" value="<?=h($editCategory['id']??'')?>"><label>Nombre de categoría / zona<input name="name" value="<?=h($editCategory['name']??'')?>" placeholder="Ej. Querétaro Centro" required></label><label>Descripción corta<input name="description" value="<?=h($editCategory['description']??'')?>" placeholder="Ej. Centro y colonias cercanas"></label><label>Orden<input type="number" name="sort_order" value="<?=h($editCategory['sort_order']??100)?>"></label><label><input style="width:auto" type="checkbox" name="active" value="1" <?=!isset($editCategory['active'])||$editCategory['active']?'checked':''?>> Activa</label><button class="btn primary">Guardar categoría</button><?php if($editCategory):?> <a class="btn secondary" href="<?=h(base_url('index.php?page=sale_points'))?>">Cancelar</a><?php endif;?></form><?php else:?><p class="help">Tienes permiso de consulta. Para administrar categorías se requiere Editar.</p><?php endif;?>
      </div>
      <div class="card">
        <h3>Flujo del bot</h3>
        <p class="muted">El usuario verá primero las categorías / zonas activas. Al elegir una, verá únicamente los puntos de venta activos asociados. Así evitamos conversaciones largas y ninguna ubicación queda fuera por el límite de WhatsApp.</p>
      </div>
    </div>
    <div class="section-title"><div><h3>Categorías / zonas</h3><p>Administra aquí las agrupaciones que verá el usuario en WhatsApp.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>Categoría / zona</th><th>Descripción</th><th>Puntos</th><th>Estado</th><th></th></tr></thead><tbody><?php if(!$categories):?><tr><td colspan="5"><span class="muted">Aún no hay categorías. Ejecuta la migración y crea la primera categoría.</span></td></tr><?php endif;?><?php foreach($categories as $c):?><tr><td><strong><?=h($c['name'])?></strong></td><td><?=h($c['description']?:'—')?></td><td><?=h($c['point_count'])?></td><td><span class="badge <?=$c['active']?'success':'danger'?>"><?=$c['active']?'Activa':'Inactiva'?></span></td><td class="actions"><?php if(can_edit('sale_points')):?><a class="btn secondary" href="<?=h(base_url('index.php?page=sale_points&edit_category='.$c['id']))?>">Editar</a><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="toggle_sale_point_category"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn secondary"><?=$c['active']?'Desactivar':'Activar'?></button></form><?php endif;?><?php if(is_admin()):?><form method="post" onsubmit="return confirm('¿Eliminar esta categoría? Solo es posible si no tiene puntos asignados.')"><?=csrf_input()?><input type="hidden" name="action" value="delete_sale_point_category"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn danger" <?=((int)$c['point_count']>0)?'disabled title="Reasigna primero los puntos de venta"':''?>>Eliminar</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div>

    <div class="section-title"><div><h3><?=$edit?'Editar punto de venta':'Nuevo punto de venta'?></h3><p>Cada punto debe pertenecer a una categoría / zona.</p></div></div>
    <div class="grid two"><div class="card"><?php if(can_edit('sale_points')):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_sale_point"><input type="hidden" name="id" value="<?=h($edit['id']??'')?>"><label>Categoría / zona<select name="category_id" required><option value="">Selecciona...</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=((int)($edit['category_id']??0)===(int)$c['id'])?'selected':''?>><?=h($c['name'])?><?=$c['active']?'':' (inactiva)'?></option><?php endforeach;?></select></label><label>Nombre<input name="name" value="<?=h($edit['name']??'')?>" required></label><label>Ciudad<input name="city" value="<?=h($edit['city']??'')?>" required></label><label>Municipio<input name="municipality" value="<?=h($edit['municipality']??'')?>"></label><label>Dirección<input name="address" value="<?=h($edit['address']??'')?>"></label><label>Horario<input name="schedule" value="<?=h($edit['schedule']??'')?>"></label><label>Teléfono<input name="phone" value="<?=h($edit['phone']??'')?>"></label><label>URL de mapa<input name="map_url" value="<?=h($edit['map_url']??'')?>"></label><label>Orden<input type="number" name="sort_order" value="<?=h($edit['sort_order']??100)?>"></label><label><input style="width:auto" type="checkbox" name="active" value="1" <?=!isset($edit['active'])||$edit['active']?'checked':''?>> Activo</label><button class="btn primary">Guardar punto</button><?php if($edit):?> <a class="btn secondary" href="<?=h(base_url('index.php?page=sale_points'))?>">Cancelar</a><?php endif;?></form><?php else:?><p class="help">Tienes permiso de consulta. Para crear o modificar puntos de venta se requiere Editar.</p><?php endif;?></div><div class="card"><h3>Cómo se mostrará</h3><p class="muted">En WhatsApp el cliente elige una categoría / zona, después selecciona un punto de venta y finalmente recibe dirección, horario, teléfono y mapa de ese punto.</p></div></div>
    <div class="section-title"><h3>Ubicaciones</h3></div><div class="table-wrap"><table><thead><tr><th>Punto de venta</th><th>Categoría / zona</th><th>Ciudad / municipio</th><th>Dirección</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=h($r['name'])?></strong></td><td><?=h($r['category_name']?:'Sin categoría')?></td><td><?=h($r['city'])?><?= $r['municipality']?' / '.h($r['municipality']):''?></td><td><?=h($r['address']?:'—')?></td><td><span class="badge <?=$r['active']?'success':'danger'?>"><?=$r['active']?'Activo':'Inactivo'?></span></td><td class="actions"><?php if(can_edit('sale_points')):?><a class="btn secondary" href="<?=h(base_url('index.php?page=sale_points&edit='.$r['id']))?>">Editar</a><?php endif;?><?php if(is_admin()):?><form method="post" onsubmit="return confirm('¿Eliminar punto?')"><?=csrf_input()?><input type="hidden" name="action" value="delete_sale_point"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn danger">Eliminar</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}
if($page==='point_queries'){
    $rows=db_all($pdo,'SELECT q.*,c.full_name,c.profile_name,c.phone FROM mg_sale_point_queries q JOIN mg_contacts c ON c.id=q.contact_id ORDER BY q.id DESC'); render_header('Consultas de puntos de venta','point_queries'); ?><div class="table-wrap"><table><thead><tr><th>Contacto</th><th>Categoría / zona consultada</th><th>Coincidencias</th><th>Fecha</th><th>Estado / notas</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=h($r['full_name']?:$r['profile_name']?:'Sin nombre')?><br><span class="muted"><?=h($r['phone'])?></span></td><td><?=h($r['city_query'])?></td><td><?=h($r['matched_points'])?></td><td><?=h(fmt_dt($r['created_at']))?></td><td><?php if(can_edit('point_queries')):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="update_point_query"><input type="hidden" name="id" value="<?=$r['id']?>"><select name="status"><option value="resolved_bot" <?=$r['status']==='resolved_bot'?'selected':''?>>Resuelta por bot</option><option value="pending" <?=$r['status']==='pending'?'selected':''?>>Pendiente</option><option value="contacted" <?=$r['status']==='contacted'?'selected':''?>>Contactado</option><option value="closed" <?=$r['status']==='closed'?'selected':''?>>Cerrada</option></select><textarea name="notes"><?=h($r['notes']??'')?></textarea><button class="btn secondary">Guardar</button></form><?php else:?><span class="badge <?=status_class($r['status'])?>"><?=h($r['status'])?></span><?php if(!empty($r['notes'])):?><br><span class="help"><?=nl2br(h($r['notes']))?></span><?php endif;?><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}


if($page==='messages'){
    $contacts=db_all($pdo,'SELECT id,COALESCE(NULLIF(full_name,""),NULLIF(profile_name,""),phone) display_name,phone,email FROM mg_contacts WHERE active=1 ORDER BY display_name');
    $template=setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']??'marketing_meligil_texto'); $lang=setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']??'es_MX');
    $totalContacts=(int)db_value($pdo,'SELECT COUNT(*) FROM mg_contacts WHERE active=1 AND phone<>""');
    $journeyCounts=customer_journey_counts($pdo,true);
    $selectedId=(int)($_GET['contact_id']??0);
    render_header('Mensajes','messages'); ?>
    <div class="message-audience-grid">
      <div class="card"><h3>Contacto específico</h3><p class="help">Envía el mensaje únicamente a la persona seleccionada.</p><?php if(can_edit('messages')):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="send_template"><label>Contacto<select name="contact_id" required><option value="">Selecciona...</option><?php foreach($contacts as $c):?><option value="<?=$c['id']?>" <?=$selectedId===(int)$c['id']?'selected':''?>><?=h($c['display_name'].' · '.$c['phone'].($c['email']?' · '.$c['email']:''))?></option><?php endforeach;?></select></label><label>Mensaje<textarea name="body_text" required placeholder="Escribe el mensaje..."></textarea></label><button class="btn primary wide">Enviar mensaje</button></form><?php else:?><p class="help">Tu cuenta tiene permiso Ver. Para enviar mensajes se requiere Editar.</p><?php endif;?></div>
      <div class="card"><h3>Por Customer Journey</h3><p class="help">Selecciona exactamente a qué tipo de contacto deseas enviar el mensaje.</p><div class="segment-counts"><span>Prospecto: <strong><?=number_format($journeyCounts['prospecto'])?></strong></span><span>Prospecto recurrente: <strong><?=number_format($journeyCounts['prospecto_recurrente'])?></strong></span><span>Cliente: <strong><?=number_format($journeyCounts['cliente'])?></strong></span><span>Cliente recurrente: <strong><?=number_format($journeyCounts['cliente_recurrente'])?></strong></span></div><?php if(can_edit('messages')):?><form method="post" onsubmit="return confirm('¿Enviar este mensaje al segmento seleccionado?')"><?=csrf_input()?><input type="hidden" name="action" value="send_template_segment"><label>Segmento<select name="segment" required><option value="prospecto">Prospecto</option><option value="prospecto_recurrente">Prospecto recurrente</option><option value="cliente">Cliente</option><option value="cliente_recurrente">Cliente recurrente</option></select></label><label>Mensaje<textarea name="body_text" required></textarea></label><button class="btn primary wide">Enviar al segmento</button></form><?php endif;?></div>
      <div class="card"><h3>Todos</h3><p class="help">Incluye todos los contactos activos con WhatsApp disponible.</p><div class="message-count"><?=number_format($totalContacts)?></div><?php if(can_edit('messages')):?><form method="post" onsubmit="return confirm('¿Enviar este mensaje a todos los contactos activos?')"><?=csrf_input()?><input type="hidden" name="action" value="send_template_bulk"><label>Mensaje<textarea name="body_text" required></textarea></label><button class="btn dark wide">Enviar a todos</button></form><?php endif;?></div>
    </div><div class="message-help">La plantilla autorizada configurada en el sistema se aplica al envío. Los envíos por Customer Journey procesan hasta 200 destinatarios por ejecución para proteger la estabilidad del servidor.</div>
    <?php render_footer();exit;
}

if($page==='settings'){
    $template=setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']??'marketing_meligil_texto'); $lang=setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']??'es_MX'); $url=setting_nonempty($pdo,'firebase_crm_url',$config['firebase_crm_url']??''); render_header('Configuración','settings'); ?><div class="grid two"><div class="card"><h3>WhatsApp / Meta</h3><?php if(can_edit('settings')):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_settings"><label>Plantilla aprobada<input name="marketing_template_name" value="<?=h($template)?>" required></label><label>Idioma<input name="marketing_template_language" value="<?=h($lang)?>" required></label><label>Endpoint crmSendMeligil<input name="firebase_crm_url" value="<?=h($url)?>" required></label><button class="btn primary">Guardar configuración</button></form><?php else:?><p><strong>Plantilla:</strong> <?=h($template)?></p><p><strong>Idioma:</strong> <?=h($lang)?></p><p><strong>Endpoint:</strong><br><span class="help"><?=h($url)?></span></p><?php endif;?></div><div class="card"><h3>Prueba de conexión</h3><p class="help">Comprueba Web → Firebase → MySQL usando la configuración actual.</p><?php if(can_edit('settings')):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="test_firebase"><button class="btn dark">Probar conexión</button></form><?php endif;?></div></div><?php render_footer();exit;
}

if($page==='users'){
    require_admin(); render_header('Colaboradores','users'); $edit=(int)($_GET['edit']??0); $editing=$edit?db_one($pdo,'SELECT * FROM mg_admin_users WHERE id=?',[$edit]):null; $editPerms=$editing?permission_rows($pdo,$edit):[]; $rows=db_all($pdo,"SELECT * FROM mg_admin_users ORDER BY (role='admin') DESC,active DESC,full_name,email,id");
    ?><div class="card users-intro"><div class="section-title" style="margin-top:0"><div><h2><?=$editing?'Editar colaborador':'Nuevo colaborador'?></h2><p>Administrador tiene acceso total. Colaborador recibe permisos Ver / Editar por módulo.</p></div></div><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?=h($editing['id']??'')?>"><div class="form-grid"><label>Nombre<input name="first_name" value="<?=h($editing['first_name']??'')?>" required></label><label>Apellidos<input name="last_name" value="<?=h($editing['last_name']??'')?>" required></label><label>Correo electrónico<input type="email" name="email" value="<?=h($editing['email']??'')?>" required></label><label><?=$editing?'Nueva contraseña (opcional)':'Contraseña temporal'?><div class="password-wrap"><input id="userPassword" type="password" name="password" minlength="8" <?=$editing?'':'required'?>> <button type="button" class="password-toggle" data-password-toggle="userPassword">Ver</button></div></label><label>Tipo de cuenta<select name="role" id="userRole"><option value="user" <?=($editing['role']??'user')==='user'||($editing['role']??'')==='viewer'?'selected':''?>>Colaborador</option><option value="admin" <?=($editing['role']??'')==='admin'?'selected':''?>>Administrador</option></select></label><label><input type="checkbox" name="active" value="1" <?=!isset($editing['active'])||$editing['active']?'checked':''?> style="width:auto"> Cuenta activa</label></div>
    <div class="section-title"><div><h2>Permisos del Colaborador</h2><p>Editar activa también Ver.</p></div></div><p id="adminPermissionNote" class="help" hidden>El Administrador tiene acceso total; la matriz no es necesaria.</p><div class="permission-grid"><?php foreach(MELIGIL_MODULES as $key=>$label):$p=$editPerms[$key]??['view'=>false,'edit'=>false];?><div class="permission-row"><strong><?=h($label)?></strong><label><input data-perm="view" type="checkbox" name="permissions[<?=$key?>][view]" value="1" <?=$p['view']?'checked':''?>> Ver</label><label><input data-perm="edit" type="checkbox" name="permissions[<?=$key?>][edit]" value="1" <?=$p['edit']?'checked':''?>> Editar</label></div><?php endforeach;?></div><div style="margin-top:16px"><button class="btn primary">Guardar colaborador</button><?php if($editing):?> <a class="btn secondary" href="<?=h(base_url('colaboradores'))?>">Cancelar</a><?php endif;?></div></form></div>
    <div class="table-wrap"><table><thead><tr><th>Colaborador</th><th>Tipo</th><th>Estado</th><th>Permisos</th><th>Último acceso</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=h(trim(($r['first_name']??'').' '.($r['last_name']??''))?:$r['full_name'])?></strong><br><span class="help"><?=h($r['email'])?></span></td><td><span class="badge neutral"><?=h(role_label($r['role']))?></span></td><td><span class="badge <?=$r['active']?'success':'danger'?>"><?=$r['active']?'Activo':'Inactivo'?></span></td><td><span class="help"><?=h(permission_summary($pdo,$r))?></span></td><td><?=h(fmt_dt($r['last_login_at']))?></td><td><div class="actions"><a class="btn secondary" href="<?=h(base_url('colaboradores?edit='.$r['id']))?>">Editar</a><?php if((int)$r['id']!==(int)$user['id']):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn secondary"><?=$r['active']?'Desactivar':'Activar'?></button></form><form method="post" onsubmit="return confirm('¿Eliminar este colaborador?')"><?=csrf_input()?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn danger">Eliminar</button></form><?php endif;?></div></td></tr><?php endforeach;?></tbody></table></div><?php render_footer();exit;
}

if($page==='profile'){
    $user=current_user(); render_header('Mi perfil','profile'); ?>
    <div class="profile-grid"><div class="card profile-summary"><div class="profile-photo-large"><?php if(!empty($user['profile_photo'])):?><img src="<?=h(base_url($user['profile_photo']))?>" alt="Foto de perfil"><?php else:?><span><?=h(initials($user))?></span><?php endif;?></div><h2><?=h(user_full_name($user))?></h2><p><?=h($user['email']??'')?></p><span class="badge neutral"><?=h(role_label($user['role']??''))?></span><p class="help">Mi perfil siempre está disponible, independientemente de los permisos de otros módulos.</p></div>
    <div class="grid"><div class="card"><h2>Datos personales</h2><form method="post" enctype="multipart/form-data"><?=csrf_input()?><input type="hidden" name="action" value="update_profile"><div class="form-grid"><label>Nombre<input name="first_name" value="<?=h($user['first_name']??'')?>" required></label><label>Apellidos<input name="last_name" value="<?=h($user['last_name']??'')?>" required></label><label class="full">Correo electrónico<input type="email" name="email" value="<?=h($user['email']??'')?>" required></label><label class="full">Foto de perfil<input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><span class="help">JPG, PNG o WEBP. Máximo 2 MB.</span></label></div><button class="btn primary">Guardar cambios</button></form></div>
    <div class="card"><h2>Cambiar contraseña</h2><p class="help">Primero se valida tu contraseña actual.</p><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="change_password"><label>Contraseña actual<div class="password-wrap"><input id="passwordCurrent" type="password" name="current_password" required><button type="button" class="password-toggle" data-password-toggle="passwordCurrent">Ver</button></div></label><label>Nueva contraseña<div class="password-wrap"><input id="passwordNew" type="password" name="new_password" minlength="8" required><button type="button" class="password-toggle" data-password-toggle="passwordNew">Ver</button></div></label><label>Confirmar nueva contraseña<div class="password-wrap"><input id="passwordConfirm" type="password" name="confirm_password" minlength="8" required><button type="button" class="password-toggle" data-password-toggle="passwordConfirm">Ver</button></div></label><button class="btn primary">Actualizar contraseña</button></form></div></div></div><?php render_footer();exit;
}
