<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    require_role(['admin','institution_admin','staff']);
    [$filterSql,$filterParams]=report_filters();
    $stmt=$pdo->prepare("SELECT b.id,u.name AS peminjam,u.student_number AS nim,b.type,b.status,b.start_date,b.end_date,b.requested_at,d.resource_id,d.quantity FROM borrowings b JOIN users u ON u.id=b.user_id JOIN borrowing_details d ON d.borrowing_id=b.id{$filterSql} ORDER BY b.requested_at DESC");
    $stmt->execute($filterParams);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-sipintar-'.date('Ymd').'.csv"');
    $output=fopen('php://output','w');
    fputcsv($output,['ID','Peminjam','NIS/NIM','Jenis','Status','Tanggal mulai','Tanggal selesai','Diajukan','ID objek','Jumlah']);
    foreach($stmt->fetchAll() as $row){
        $row['type']=type_label($row['type']);
        $row['status']=status_label($row['status']);
        fputcsv($output,array_map('csv_safe',$row));
    }
    fclose($output);
    exit;
}

handle_post($pdo);
$page=(string)($_GET['page']??(current_user()?'dashboard':'login'));
$allowed=['login','register','register_institution','awaiting_approval','dashboard','catalog','borrowings','reports','users','profile','settings','institutions','complaints'];
if(!in_array($page,$allowed,true)){
    http_response_code(404);
    $page='not_found';
}
$user=current_user();
$platformAdmin=$user&&$user['role']==='admin';
$tenantId=current_tenant_id();
$authenticatedPages=['dashboard','catalog','borrowings','reports','users','profile','settings','institutions','complaints'];
if(in_array($page,$authenticatedPages,true)){
    require_login();
}
if($user&&in_array($page,['login','register','register_institution'],true)){
    redirect('?page=dashboard');
}
if($page==='register_institution'){
    $page='register';
}
if($user&&$user['role']!=='admin'&&($user['institution_status']??null)==='pending'&&$page!=='awaiting_approval'){
    redirect('?page=awaiting_approval');
}
if($user&&$user['role']!=='admin'&&($user['institution_status']??null)!=='active'&&($user['institution_status']??null)!=='pending'){
    http_response_code(403);
    exit('Institusi belum aktif atau aksesnya dinonaktifkan.');
}
if($page==='institutions'){
    require_role(['admin']);
}
if($page==='users'){
    require_role(['admin','institution_admin','staff']);
}
if($page==='settings'){
    require_role(['institution_admin']);
}
if($page==='reports'){
    require_role(['admin','institution_admin','staff']);
}
if($page==='complaints'&&!feature_enabled($pdo,'complaints')){
    http_response_code(404);
    exit('Fitur keluhan tidak diaktifkan oleh institusi.');
}
if($page==='catalog'&&!in_array($user['role'],['admin','institution_admin','staff','user'],true)){
    http_response_code(403);
    exit('Akses katalog ditolak.');
}
if($page==='catalog'&&$platformAdmin&&!$tenantId){
    redirect('?page=institutions');
}
$data=[];

if($page==='catalog'){
    $type=(string)($_GET['type']??'item');
    if(!in_array($type,['item','book','lab','room'],true)) $type='item';
    if(!feature_enabled($pdo,$type)){
        http_response_code(404);
        exit('Kategori ini tidak diaktifkan oleh institusi.');
    }
    $table=['item'=>'items','book'=>'books','lab'=>'laboratory_equipments','room'=>'rooms'][$type];
    $search=trim((string)($_GET['q']??''));
    $tenantClause=tenant_clause('r');
    if($platformAdmin&&!$tenantId){
        $tenantClause=[' AND 1=0',[]];
    }
    $sql="SELECT r.*,c.name AS category_name FROM {$table} r LEFT JOIN categories c ON c.id=r.category_id WHERE (r.name LIKE ? OR c.name LIKE ?)".$tenantClause[0].' ORDER BY r.name';
    $stmt=$pdo->prepare($sql);
    $stmt->execute(array_merge(['%'.$search.'%','%'.$search.'%'],$tenantClause[1]));
    $data=['type'=>$type,'resources'=>$stmt->fetchAll(),'categories'=>categories($pdo),'search'=>$search,'editable'=>in_array($user['role'],['admin','institution_admin','staff'],true)];
    if($user['role']==='admin'){
        $stmt=$pdo->prepare('SELECT * FROM categories WHERE type=? AND (tenant_id=? OR tenant_id IS NULL) ORDER BY name');
        $stmt->execute([$type,$tenantId]);
        $data['type_categories']=$stmt->fetchAll();
    }else{
        $stmt=$pdo->prepare('SELECT * FROM categories WHERE type=? AND (tenant_id=? OR tenant_id IS NULL) ORDER BY name');
        $stmt->execute([$type,$tenantId]);
        $data['type_categories']=$stmt->fetchAll();
    }
    if($type==='room'){
        $sql="SELECT rb.room_id,rb.start_at,rb.end_at,b.status FROM room_bookings rb JOIN borrowings b ON b.id=rb.borrowing_id WHERE b.status IN ('waiting','approved','borrowed','return_pending') AND rb.end_at>=NOW()";
        $params=[];
        if($tenantId){$sql.=' AND b.tenant_id=?';$params[]=$tenantId;}
        $sql.=' ORDER BY rb.start_at LIMIT 100';
        $stmt=$pdo->prepare($sql);$stmt->execute($params);$data['room_schedule']=[];
        foreach($stmt->fetchAll() as $slot)$data['room_schedule'][$slot['room_id']][]=$slot;
    }
    if(isset($_GET['edit'])&&$data['editable'])$data['edit_resource']=resource_data($pdo,$type,(int)$_GET['edit']);
    if($user['role']==='user')$data['max_loan_days']=(int)$pdo->query("SELECT setting_value FROM system_settings WHERE tenant_id=".(int)$tenantId." AND setting_key='max_loan_days'")->fetchColumn();
}
if($page==='borrowings'){
    $sql="SELECT b.*,u.name AS borrower_name,u.email AS borrower_email,u.student_number,u.cohort,u.phone,d.resource_type,d.resource_id,d.quantity,rb.start_at,rb.end_at,i.name AS institution_name FROM borrowings b JOIN users u ON u.id=b.user_id JOIN borrowing_details d ON d.borrowing_id=b.id LEFT JOIN room_bookings rb ON rb.borrowing_id=b.id LEFT JOIN institutions i ON i.id=b.tenant_id";
    $params=[];
    if($user['role']==='user'){$sql.=' WHERE b.user_id=?';$params[]=$user['id'];}
    elseif($tenantId){$sql.=' WHERE b.tenant_id=?';$params[]=$tenantId;}
    $sql.=' ORDER BY b.requested_at DESC';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
    foreach($rows as &$row){
        $row['resource']=resource_data($pdo,$row['resource_type'],(int)$row['resource_id']);
        $row['status_display']=$row['status']==='borrowed'&&$row['end_date']<date('Y-m-d')?'overdue':$row['status'];
    }
    unset($row);$data['borrowings']=$rows;
}
if($page==='dashboard'){
    $role=$user['role'];
    if($role==='user'){
        foreach(['my_total'=>'SELECT COUNT(*) FROM borrowings WHERE user_id=?','my_waiting'=>"SELECT COUNT(*) FROM borrowings WHERE user_id=? AND status='waiting'",'my_completed'=>"SELECT COUNT(*) FROM borrowings WHERE user_id=? AND status='completed'"] as $key=>$query){$stmt=$pdo->prepare($query);$stmt->execute([$user['id']]);$data[$key]=(int)$stmt->fetchColumn();}
        $stmt=$pdo->prepare('SELECT b.*,d.resource_type,d.resource_id FROM borrowings b JOIN borrowing_details d ON d.borrowing_id=b.id WHERE b.user_id=? ORDER BY b.requested_at DESC LIMIT 5');$stmt->execute([$user['id']]);$data['recent']=$stmt->fetchAll();
        $stmt=$pdo->prepare('SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 4');$stmt->execute([$user['id']]);$data['notifications']=$stmt->fetchAll();
    }else{
        $base=' FROM borrowings'.($tenantId?' WHERE tenant_id='.(int)$tenantId:'');
        foreach(['total'=>'SELECT COUNT(*)','waiting'=>"SELECT COUNT(*) WHERE status='waiting'",'borrowed'=>"SELECT COUNT(*) WHERE status IN ('borrowed','return_pending')",'completed'=>"SELECT COUNT(*) WHERE status='completed'"] as $key=>$query){
            $query=str_replace(' WHERE',' FROM borrowings WHERE',$query);
            if($key==='total')$query.=$base;
            elseif($tenantId)$query.=' AND tenant_id='.(int)$tenantId;
            $data[$key]=(int)$pdo->query($query)->fetchColumn();
        }
        $tenantWhere=$tenantId?' WHERE tenant_id='.(int)$tenantId:'';
        $data['inventory']=(int)$pdo->query("SELECT (SELECT COALESCE(SUM(stock),0) FROM items{$tenantWhere})+(SELECT COALESCE(SUM(stock),0) FROM books{$tenantWhere})+(SELECT COALESCE(SUM(stock),0) FROM laboratory_equipments{$tenantWhere})")->fetchColumn();
        $sql='SELECT b.*,u.name AS borrower_name,d.resource_type,d.resource_id FROM borrowings b JOIN users u ON u.id=b.user_id JOIN borrowing_details d ON d.borrowing_id=b.id'.($tenantId?' WHERE b.tenant_id='.(int)$tenantId:'').' ORDER BY b.requested_at DESC LIMIT 6';
        $data['recent']=$pdo->query($sql)->fetchAll();
        $data['pending_count']=$data['waiting'];
    }
}
if($page==='reports'){
    [$filterSql,$filterParams]=report_filters();
    foreach(['by_type'=>"SELECT type,COUNT(*) AS total FROM borrowings b",'by_status'=>"SELECT status,COUNT(*) AS total FROM borrowings b"] as $key=>$base){
        $group=$key==='by_type'?'type':'status';$stmt=$pdo->prepare($base.$filterSql." GROUP BY {$group} ORDER BY total DESC");$stmt->execute($filterParams);$data[$key]=$stmt->fetchAll();
    }
    $stmt=$pdo->prepare("SELECT COUNT(*) total,SUM(status='waiting') waiting,SUM(status='completed') completed,SUM(status='borrowed' AND end_date<CURDATE()) late FROM borrowings b{$filterSql}");$stmt->execute($filterParams);$counts=$stmt->fetch();
    foreach(['total','waiting','completed','late'] as $key)$data[$key]=(int)($counts[$key]??0);
    $feeStmt=$pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='late_fee_per_day'".($tenantId?' AND tenant_id=?':' LIMIT 1'));
    $feeStmt->execute($tenantId?[$tenantId]:[]);$fee=(int)($feeStmt->fetchColumn()?:0);
    $stmt=$pdo->prepare("SELECT COALESCE(SUM(DATEDIFF(CURDATE(),b.end_date)),0) FROM borrowings b{$filterSql}".($filterSql?' AND ':' WHERE ')."b.status='borrowed' AND b.end_date<CURDATE()");$stmt->execute($filterParams);$data['late_fee_total']=(int)$stmt->fetchColumn()*$fee;
    $stmt=$pdo->prepare("SELECT id,name FROM users WHERE role_id=(SELECT id FROM roles WHERE name='user') AND is_active=1".($tenantId?' AND tenant_id=?':'')." ORDER BY name");$stmt->execute($tenantId?[$tenantId]:[]);$data['report_users']=$stmt->fetchAll();
    $data['filters']=['from'=>(string)($_GET['from']??''),'to'=>(string)($_GET['to']??''),'type'=>(string)($_GET['type']??''),'user_id'=>(int)($_GET['user_id']??0)];
}
if($page==='users'){
    $tenantClause=tenant_clause('u');
    if($platformAdmin&&!$tenantId)$tenantClause=[' AND 1=0',[]];
    $stmt=$pdo->prepare("SELECT u.id,u.name,u.email,u.student_number,u.cohort,u.phone,u.is_active,u.created_at,r.name AS role,(SELECT COUNT(*) FROM borrowings b WHERE b.user_id=u.id) AS borrowing_count FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name IN ('staff','user'){$tenantClause[0]} ORDER BY r.name,u.name");
    $stmt->execute($tenantClause[1]);$data['users']=$stmt->fetchAll();
}
if($page==='institutions'){
    $data['institutions']=$pdo->query("SELECT i.*,p.name AS province_name,r.name AS regency_name,(SELECT COUNT(*) FROM users u WHERE u.tenant_id=i.id) AS user_count,(SELECT COUNT(*) FROM borrowings b WHERE b.tenant_id=i.id) AS borrowing_count FROM institutions i LEFT JOIN region_codes p ON p.code=i.province_code LEFT JOIN region_codes r ON r.code=i.regency_code ORDER BY FIELD(i.status,'pending','active','suspended'),i.created_at DESC")->fetchAll();
}
if($page==='complaints'){
    $sql='SELECT c.*,u.name AS reporter,u.email,u.student_number,u.cohort,b.id AS borrowing_number FROM complaints c JOIN users u ON u.id=c.user_id LEFT JOIN borrowings b ON b.id=c.borrowing_id';
    $params=[];
    if($user['role']==='user'){$sql.=' WHERE c.user_id=?';$params[]=$user['id'];}
    elseif($tenantId){$sql.=' WHERE c.tenant_id=?';$params[]=$tenantId;}
    $sql.=' ORDER BY c.created_at DESC';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$data['complaints']=$stmt->fetchAll();
    if($user['role']==='user'){$stmt=$pdo->prepare('SELECT id FROM borrowings WHERE user_id=? AND tenant_id=? ORDER BY requested_at DESC');$stmt->execute([$user['id'],$tenantId]);$data['my_borrowings']=$stmt->fetchAll();}
}
if($page==='settings'){
    $stmt=$pdo->prepare('SELECT setting_key,setting_value FROM system_settings WHERE tenant_id=?');$stmt->execute([$tenantId]);$settings=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $data['settings']=$settings+['school_name'=>$user['institution_name']??'Institusi','max_loan_days'=>'14','late_fee_per_day'=>'0'];
    $stmt=$pdo->prepare('SELECT module_key,is_enabled FROM institution_modules WHERE tenant_id=?');$stmt->execute([$tenantId]);$data['modules']=array_column($stmt->fetchAll(),'is_enabled','module_key');
    foreach(['item','book','lab','room','complaints'] as $key)if(!isset($data['modules'][$key]))$data['modules'][$key]=1;
}
if($page==='register'||$page==='login')$data['institutions']=$pdo->query("SELECT id,name,institution_type,province_code,regency_code FROM institutions WHERE status='active' ORDER BY name")->fetchAll();
if(in_array($page,['register','institutions'],true)){
    $data['provinces']=$pdo->query('SELECT code,name FROM region_codes WHERE level=1 ORDER BY name')->fetchAll();
    $data['regencies']=$pdo->query('SELECT code,parent_code,name FROM region_codes WHERE level=2 ORDER BY name')->fetchAll();
}
if($user&&$user['role']==='admin'){
    $data['pending_institution_count']=(int)$pdo->query("SELECT COUNT(*) FROM institutions WHERE status='pending'")->fetchColumn();
}
$data['registration_type'] = $page === 'register' && (((string) ($_GET['type'] ?? '') === 'institution') || ((string) ($_GET['page'] ?? '') === 'register_institution')) ? 'institution' : '';
$data['site_name']=$user['institution_name']??'SIPINTAR';
$data['tenant_id']=$tenantId;
$data['is_platform_admin']=$platformAdmin;
if($user&&in_array($user['role'],['admin','institution_admin','staff'],true)&&!isset($data['pending_count'])){
    $sql="SELECT COUNT(*) FROM borrowings WHERE status='waiting'".($tenantId?' AND tenant_id='.(int)$tenantId:'');
    $data['pending_count']=(int)$pdo->query($sql)->fetchColumn();
}
render($page,$data,$pdo);
