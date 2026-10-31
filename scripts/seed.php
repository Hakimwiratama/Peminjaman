<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db']['host'], $config['db']['port'], $config['db']['name']),
    $config['db']['user'],
    $config['db']['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$demoTenantId = $pdo->query("SELECT id FROM institutions WHERE slug='sekolah-demo'")->fetchColumn();
if (!$demoTenantId) {
    $legacyDemoId = $pdo->query("SELECT id FROM institutions WHERE id=1 AND slug='institusi-demo'")->fetchColumn();
    if ($legacyDemoId) {
        $pdo->prepare("UPDATE institutions SET name='Sekolah Demo SIPINTAR',slug='sekolah-demo',institution_type='school',email='sekolah@sipintar.local',status='active' WHERE id=?")->execute([$legacyDemoId]);
    } else {
        $pdo->prepare("INSERT INTO institutions (name,slug,institution_type,email,status) VALUES ('Sekolah Demo SIPINTAR','sekolah-demo','school','sekolah@sipintar.local','active')")->execute();
    }
}
$tenantId=(int)$pdo->query("SELECT id FROM institutions WHERE slug='sekolah-demo'")->fetchColumn();
$pdo->prepare("UPDATE institutions SET province_code='31',regency_code='31.71' WHERE id=? AND province_code IS NULL AND regency_code IS NULL")->execute([$tenantId]);
$accounts=[
    ['Administrator SIPINTAR','admin@sipintar.local','admin',null],
    ['Admin Sekolah Demo','sekolah@sipintar.local','institution_admin',$tenantId],
    ['Petugas SIPINTAR','petugas@sipintar.local','staff',$tenantId],
    ['Pengguna Demo','pengguna@sipintar.local','user',$tenantId],
];
$stmt=$pdo->prepare('INSERT INTO users (role_id,tenant_id,name,email,password) SELECT id,?,?,?,? FROM roles WHERE name=? ON DUPLICATE KEY UPDATE tenant_id=VALUES(tenant_id),name=VALUES(name)');
foreach($accounts as [$name,$email,$role,$accountTenant]){
    $stmt->execute([$accountTenant,$name,$email,password_hash('SIPINTAR123!',PASSWORD_DEFAULT),$role]);
    echo "{$role}: {$email} / SIPINTAR123!".PHP_EOL;
}

$moduleStmt=$pdo->prepare('INSERT IGNORE INTO institution_modules (tenant_id,module_key,is_enabled) VALUES (?,?,1)');
foreach(['item','book','lab','room','complaints'] as $module)$moduleStmt->execute([$tenantId,$module]);
$settingStmt=$pdo->prepare('INSERT INTO system_settings (tenant_id,setting_key,setting_value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
foreach(['school_name'=>'Sekolah Demo SIPINTAR','max_loan_days'=>'14','late_fee_per_day'=>'0'] as $key=>$value)$settingStmt->execute([$tenantId,$key,$value]);

$examples=[
    ['items','item','Elektronik',['Proyektor Epson',5,'Baik','Gudang sarana','Proyektor untuk presentasi kelas.']],
    ['books','book','Referensi',['Dasar-Dasar Pemrograman','Budi Santoso','Pustaka Ilmu',2024,8,'Baik','Perpustakaan','Buku pengantar pemrograman.']],
    ['laboratory_equipments','lab','Biologi',['Mikroskop Binokuler','','',null,4,'Baik','Lab Biologi','Alat pengamatan praktikum.']],
    ['rooms','room','Ruang kelas',['Ruang Kelas A-201',32,'Proyektor, AC, papan tulis','Ruang belajar kapasitas sedang.']],
];
foreach($examples as [$table,$type,$categoryName,$values]){
    $category=$pdo->prepare('SELECT id FROM categories WHERE type=? AND name=? AND (tenant_id=? OR tenant_id IS NULL) ORDER BY tenant_id IS NULL LIMIT 1');
    $category->execute([$type,$categoryName,$tenantId]);
    $categoryId=$category->fetchColumn();
    $exists=$pdo->prepare("SELECT id FROM {$table} WHERE name=? AND tenant_id=? LIMIT 1");
    $exists->execute([$values[0],$tenantId]);
    if(!$exists->fetchColumn()){
        if($type==='item'){
            $pdo->prepare("INSERT INTO {$table} (tenant_id,category_id,name,stock,`condition`,location,description) VALUES (?,?,?,?,?,?,?)")->execute([$tenantId,$categoryId,...$values]);
        }elseif(in_array($type,['book','lab'],true)){
            $pdo->prepare("INSERT INTO {$table} (tenant_id,category_id,name,author,publisher,published_year,stock,`condition`,location,description) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$tenantId,$categoryId,...$values]);
        }else{
            $pdo->prepare("INSERT INTO {$table} (tenant_id,category_id,name,capacity,facilities,description) VALUES (?,?,?,?,?,?)")->execute([$tenantId,$categoryId,...$values]);
        }
    }
}
echo 'Data contoh katalog disiapkan.'.PHP_EOL;
