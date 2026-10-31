<?php
declare(strict_types=1);

function handle_post(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'login':
                $stmt = $pdo->prepare("SELECT u.id,u.name,u.email,u.password,u.tenant_id,u.student_number,u.cohort,u.phone,r.name AS role,i.name AS institution_name,i.status AS institution_status FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN institutions i ON i.id=u.tenant_id WHERE u.email=? AND u.is_active=1 LIMIT 1");
                $stmt->execute([filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '']);
                $user = $stmt->fetch();
                if (!$user || !password_verify((string) ($_POST['password'] ?? ''), $user['password']) || (($user['institution_status'] ?? null) === 'suspended')) {
                    throw new RuntimeException('Email atau kata sandi tidak cocok.');
                }
                session_regenerate_id(true);
                unset($user['password']);
                $_SESSION['user'] = $user;
                record_activity($pdo, 'login');
                redirect('?page=dashboard');

            case 'register':
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
                $password = (string) ($_POST['password'] ?? '');
                $tenantId = filter_var($_POST['tenant_id'] ?? null, FILTER_VALIDATE_INT);
                $provinceCode = trim((string) ($_POST['province_code'] ?? ''));
                $regencyCode = trim((string) ($_POST['regency_code'] ?? ''));
                $studentNumber = trim((string) ($_POST['student_number'] ?? ''));
                $cohort = trim((string) ($_POST['cohort'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                if (mb_strlen($name) < 2 || !$email || strlen($password) < 8) {
                    throw new RuntimeException('Nama, email yang valid, dan kata sandi minimal 8 karakter wajib diisi.');
                }
                if (!valid_region_pair($pdo, $provinceCode, $regencyCode)) {
                    throw new RuntimeException('Pilih provinsi dan kabupaten/kota yang sesuai.');
                }
                $school = $pdo->prepare("SELECT id FROM institutions WHERE id=? AND status='active' AND province_code=? AND regency_code=?");
                $school->execute([$tenantId, $provinceCode, $regencyCode]);
                if (!$tenantId || !$school->fetchColumn()) {
                    throw new RuntimeException('Pilih sekolah/kampus aktif yang terdaftar di daerah tersebut.');
                }
                $role = $pdo->query("SELECT id FROM roles WHERE name = 'user'")->fetchColumn();
                $stmt = $pdo->prepare('INSERT INTO users (role_id,tenant_id,name,email,password,student_number,cohort,phone) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$role, $tenantId, $name, $email, password_hash($password, PASSWORD_DEFAULT), $studentNumber ?: null, $cohort ?: null, $phone ?: null]);
                flash('Akun berhasil dibuat. Silakan masuk ke portal institusi Anda.');
                redirect('?page=login');

            case 'register_institution':
                $name = trim((string) ($_POST['institution_name'] ?? ''));
                $type = (string) ($_POST['institution_type'] ?? 'school');
                $npsn = trim((string) ($_POST['institution_npsn'] ?? ''));
                $provinceCode = trim((string) ($_POST['province_code'] ?? ''));
                $regencyCode = trim((string) ($_POST['regency_code'] ?? ''));
                $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
                $owner = trim((string) ($_POST['owner_name'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $address = trim((string) ($_POST['address'] ?? ''));
                $services = array_values(array_unique(array_map('strval', (array) ($_POST['services'] ?? []))));
                $availableServices = ['item','book','room'];
                if (mb_strlen($name) < 3 || !in_array($type, ['school','university','other'], true) || ($npsn !== '' && ($type !== 'school' || !preg_match('/^\d{8}$/', $npsn))) || !$email || mb_strlen($owner) < 2 || strlen($password) < 8 || $address === '' || array_diff($services, $availableServices) || !$services || !valid_region_pair($pdo, $provinceCode, $regencyCode)) {
                    throw new RuntimeException('Lengkapi data sekolah/kampus, wilayah, alamat, admin, kata sandi, dan pilih minimal satu layanan. NPSN harus 8 digit.');
                }
                $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name)), '-');
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO institutions (name,slug,institution_type,institution_npsn,email,phone,address,province_code,regency_code,status) VALUES (?,?,?,?,?,?,?,?,?,'pending')");
                $stmt->execute([$name, $slug . '-' . bin2hex(random_bytes(3)), $type, $npsn ?: null, $email, $phone ?: null, $address ?: null, $provinceCode, $regencyCode]);
                $tenantId = (int) $pdo->lastInsertId();
                $roleId = $pdo->query("SELECT id FROM roles WHERE name='institution_admin'")->fetchColumn();
                $stmt = $pdo->prepare('INSERT INTO users (role_id,tenant_id,name,email,password) VALUES (?,?,?,?,?)');
                $stmt->execute([$roleId, $tenantId, $owner, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $stmt = $pdo->prepare('INSERT INTO institution_modules (tenant_id,module_key,is_enabled) VALUES (?,?,?)');
                foreach (['item','book','lab','room','complaints'] as $module) {
                    $enabled = in_array($module, $services, true) || $module === 'complaints';
                    $stmt->execute([$tenantId, $module, $enabled ? 1 : 0]);
                }
                $stmt = $pdo->prepare('INSERT INTO system_settings (tenant_id,setting_key,setting_value) VALUES (?,?,?)');
                foreach (['school_name' => $name, 'max_loan_days' => '14', 'late_fee_per_day' => '0'] as $key => $value) {
                    $stmt->execute([$tenantId, $key, $value]);
                }
                $pdo->commit();
                flash('Pendaftaran institusi diterima. Akun pengelola menunggu persetujuan admin SIPINTAR.');
                redirect('?page=login');

            case 'set_institution_region':
                require_role(['admin']);
                $tenantId = filter_var($_POST['tenant_id'] ?? null, FILTER_VALIDATE_INT);
                $provinceCode = trim((string) ($_POST['province_code'] ?? ''));
                $regencyCode = trim((string) ($_POST['regency_code'] ?? ''));
                if (!$tenantId || !valid_region_pair($pdo, $provinceCode, $regencyCode)) {
                    throw new RuntimeException('Pilih institusi dan pasangan provinsi/kabupaten yang valid.');
                }
                $stmt = $pdo->prepare('UPDATE institutions SET province_code=?,regency_code=? WHERE id=?');
                $stmt->execute([$provinceCode, $regencyCode, $tenantId]);
                flash('Daerah institusi berhasil diperbarui.');
                redirect('?page=institutions');

            case 'select_institution':
                require_role(['admin']);
                $tenantId = (int) ($_POST['tenant_id'] ?? 0);
                $stmt = $pdo->prepare('SELECT id FROM institutions WHERE id=?');
                $stmt->execute([$tenantId]);
                if (!$tenantId || !$stmt->fetchColumn()) {
                    throw new RuntimeException('Institusi tidak ditemukan.');
                }
                $_SESSION['selected_tenant_id'] = $tenantId;
                redirect('?page=dashboard');

            case 'update_institution_status':
                require_role(['admin']);
                $tenantId = (int) ($_POST['tenant_id'] ?? 0);
                $status = (string) ($_POST['status'] ?? '');
                if (!in_array($status, ['active','suspended'], true)) {
                    throw new RuntimeException('Status institusi tidak valid.');
                }
                $pdo->prepare('UPDATE institutions SET status=? WHERE id=?')->execute([$status, $tenantId]);
                flash($status === 'active' ? 'Institusi disetujui dan dapat digunakan.' : 'Akses institusi dinonaktifkan.');
                redirect('?page=institutions');

            case 'logout':
                record_activity($pdo, 'logout');
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                }
                session_destroy();
                redirect('?page=login');

            case 'create_borrowing':
                require_role(['user']);
                $tenantId = current_tenant_id();
                if (!$tenantId) {
                    throw new RuntimeException('Akun ini belum terhubung ke sekolah/kampus.');
                }
                $type = (string) ($_POST['type'] ?? '');
                $resourceId = filter_var($_POST['resource_id'] ?? null, FILTER_VALIDATE_INT);
                $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
                $start = (string) ($_POST['start_date'] ?? '');
                $end = (string) ($_POST['end_date'] ?? '');
                $reason = trim((string) ($_POST['reason'] ?? ''));
                $validStart = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
                $validEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
                if (!in_array($type, ['item', 'book', 'lab', 'room'], true) || !$resourceId || !$quantity || $quantity < 1 || !$validStart || $validStart->format('Y-m-d') !== $start || !$validEnd || $validEnd->format('Y-m-d') !== $end || $end < $start || $start < date('Y-m-d') || $reason === '') {
                    throw new RuntimeException('Lengkapi objek, tanggal, dan tujuan peminjaman dengan benar.');
                }
                if (!feature_enabled($pdo, $type)) {
                    throw new RuntimeException('Layanan ini sedang dinonaktifkan oleh institusi.');
                }
                if ($type !== 'room') {
                    $stmt=$pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id=? AND setting_key='max_loan_days'");
                    $stmt->execute([$tenantId]);
                    $maxDays=(int)$stmt->fetchColumn();
                    if ($validEnd->diff($validStart)->days > $maxDays) {
                        throw new RuntimeException("Durasi peminjaman maksimal {$maxDays} hari.");
                    }
                }
                $resource = resource_data($pdo, $type, $resourceId);
                if (!$resource) {
                    throw new RuntimeException('Objek peminjaman tidak ditemukan.');
                }
                $pdo->beginTransaction();
                if ($type === 'room') {
                    $lock = $pdo->prepare('SELECT id FROM rooms WHERE id = ? AND tenant_id=? FOR UPDATE');
                    $lock->execute([$resourceId, $tenantId]);
                    $startTime = (string) ($_POST['start_time'] ?? '');
                    $endTime = (string) ($_POST['end_time'] ?? '');
                    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $endTime) || strtotime("{$end} {$endTime}") <= strtotime("{$start} {$startTime}")) {
                        throw new RuntimeException('Jam mulai dan selesai ruangan tidak valid.');
                    }
                    $overlap = $pdo->prepare("SELECT COUNT(*) FROM room_bookings rb JOIN borrowings b ON b.id = rb.borrowing_id WHERE rb.room_id = ? AND b.status IN ('waiting','approved','borrowed','return_pending') AND rb.start_at < ? AND rb.end_at > ?");
                    $overlap->execute([$resourceId, "{$end} {$endTime}:00", "{$start} {$startTime}:00"]);
                    if ((int) $overlap->fetchColumn() > 0) {
                        throw new RuntimeException('Jadwal ruangan berbenturan dengan permohonan lain.');
                    }
                    $quantity = 1;
                } else {
                    $table = ['item' => 'items', 'book' => 'books', 'lab' => 'laboratory_equipments'][$type];
                    $lock = $pdo->prepare("SELECT stock FROM {$table} WHERE id = ? AND tenant_id=? FOR UPDATE");
                    $lock->execute([$resourceId, $tenantId]);
                    $stock = (int) $lock->fetchColumn() - active_count($pdo, $type, $resourceId);
                    if ($quantity > $stock) {
                        throw new RuntimeException("Stok tersedia saat ini hanya {$stock}.");
                    }
                }
                $stmt = $pdo->prepare("INSERT INTO borrowings (tenant_id,user_id,type,status,start_date,end_date,reason) VALUES (?,? ,?,'waiting',?,?,?)");
                $stmt->execute([$tenantId, current_user()['id'], $type, $start, $end, $reason]);
                $borrowingId = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO borrowing_details (borrowing_id, resource_type, resource_id, quantity) VALUES (?, ?, ?, ?)')->execute([$borrowingId, $type, $resourceId, $quantity]);
                if ($type === 'room') {
                    $pdo->prepare('INSERT INTO room_bookings (borrowing_id, room_id, start_at, end_at) VALUES (?, ?, ?, ?)')->execute([$borrowingId, $resourceId, "{$start} {$startTime}:00", "{$end} {$endTime}:00"]);
                }
                $pdo->prepare("INSERT INTO notifications (user_id,title,message) SELECT u.id,'Permohonan baru',? FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name IN ('staff','institution_admin') AND u.tenant_id=? AND u.is_active=1")->execute(["Permohonan #{$borrowingId} menunggu verifikasi.", $tenantId]);
                $pdo->commit();
                record_activity($pdo, 'create_borrowing', "Permohonan #{$borrowingId}");
                flash('Permohonan peminjaman berhasil dikirim.');
                redirect('?page=borrowings');

            case 'review_borrowing':
                require_role(['admin','institution_admin','staff']);
                $id = (int) ($_POST['borrowing_id'] ?? 0);
                $decision = (string) ($_POST['decision'] ?? '');
                $tenantId = current_tenant_id();
                $scope = $tenantId ? ' AND b.tenant_id=?' : '';
                $stmt = $pdo->prepare("SELECT b.*,bd.resource_type,bd.resource_id,bd.quantity,rb.room_id FROM borrowings b JOIN borrowing_details bd ON bd.borrowing_id=b.id LEFT JOIN room_bookings rb ON rb.borrowing_id=b.id WHERE b.id=?{$scope} FOR UPDATE");
                $pdo->beginTransaction();
                $stmt->execute($tenantId ? [$id,$tenantId] : [$id]);
                $borrowing = $stmt->fetch();
                if (!$borrowing || $borrowing['status'] !== 'waiting') {
                    throw new RuntimeException('Permohonan tidak ditemukan atau sudah diproses.');
                }
                if ($decision === 'approve') {
                    if ($borrowing['type'] !== 'room') {
                        $table = ['item' => 'items', 'book' => 'books', 'lab' => 'laboratory_equipments'][$borrowing['type']];
                        $pdo->prepare("SELECT id FROM {$table} WHERE id=? AND tenant_id=? FOR UPDATE")->execute([$borrowing['resource_id'],$borrowing['tenant_id']]);
                        $resource = resource_data($pdo, $borrowing['type'], (int) $borrowing['resource_id']);
                        if (!$resource || (int) $resource['stock'] - active_count($pdo, $borrowing['type'], (int) $borrowing['resource_id']) < (int) $borrowing['quantity']) {
                            throw new RuntimeException('Stok sudah tidak mencukupi untuk menyetujui permohonan ini.');
                        }
                    } else {
                        $pdo->prepare('SELECT id FROM rooms WHERE id=? AND tenant_id=? FOR UPDATE')->execute([$borrowing['room_id'],$borrowing['tenant_id']]);
                        $conflict = $pdo->prepare("SELECT COUNT(*) FROM room_bookings rb JOIN borrowings b ON b.id = rb.borrowing_id WHERE rb.room_id = ? AND rb.borrowing_id <> ? AND b.status IN ('approved','borrowed','return_pending') AND rb.start_at < (SELECT end_at FROM room_bookings WHERE borrowing_id = ?) AND rb.end_at > (SELECT start_at FROM room_bookings WHERE borrowing_id = ?)");
                        $conflict->execute([$borrowing['room_id'], $id, $id, $id]);
                        if ((int) $conflict->fetchColumn() > 0) {
                            throw new RuntimeException('Jadwal ruangan sudah disetujui untuk permohonan lain.');
                        }
                    }
                    $pdo->prepare("UPDATE borrowings SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = NULL WHERE id = ?")->execute([current_user()['id'], $id]);
                    $message = "Permohonan #{$id} disetujui.";
                } elseif ($decision === 'reject') {
                    $reason = trim((string) ($_POST['rejection_reason'] ?? ''));
                    if ($reason === '') {
                        throw new RuntimeException('Alasan penolakan wajib diisi.');
                    }
                    $pdo->prepare("UPDATE borrowings SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ? WHERE id = ?")->execute([current_user()['id'], $reason, $id]);
                    $message = "Permohonan #{$id} ditolak. Alasan: {$reason}";
                } else {
                    throw new RuntimeException('Keputusan tidak valid.');
                }
                $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)')->execute([$borrowing['user_id'], 'Pembaruan permohonan', $message]);
                $pdo->commit();
                record_activity($pdo, "borrowing_{$decision}", "Permohonan #{$id}");
                flash($message);
                redirect('?page=borrowings');

            case 'set_borrowed':
                require_role(['admin','institution_admin','staff']);
                $id = (int) ($_POST['borrowing_id'] ?? 0);
                $tenantId = current_tenant_id();
                $scope = $tenantId ? ' AND tenant_id=?' : '';
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE borrowings SET status='borrowed' WHERE id=? AND status='approved'{$scope}");
                $stmt->execute($tenantId ? [$id,$tenantId] : [$id]);
                if (!$stmt->rowCount()) {
                    throw new RuntimeException('Hanya peminjaman yang disetujui dapat diserahkan.');
                }
                $pdo->prepare("INSERT INTO notifications (user_id,title,message) SELECT user_id,'Peminjaman dimulai',? FROM borrowings WHERE id=?{$scope}")->execute($tenantId ? ["Peminjaman #{$id} telah diserahkan.",$id,$tenantId] : ["Peminjaman #{$id} telah diserahkan.",$id]);
                $pdo->commit();
                record_activity($pdo, 'borrowing_borrowed', "Permohonan #{$id}");
                flash('Peminjaman ditandai sedang digunakan.');
                redirect('?page=borrowings');

            case 'request_return':
                require_role(['user']);
                $id = (int) ($_POST['borrowing_id'] ?? 0);
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE borrowings SET status='return_pending' WHERE id=? AND user_id=? AND tenant_id=? AND status='borrowed'");
                $stmt->execute([$id,current_user()['id'],current_tenant_id()]);
                if (!$stmt->rowCount()) {
                    throw new RuntimeException('Peminjaman tidak dapat diajukan untuk pengembalian.');
                }
                $pdo->prepare("INSERT INTO notifications (user_id,title,message) SELECT u.id,'Pengajuan pengembalian',? FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name IN ('staff','institution_admin') AND u.tenant_id=? AND u.is_active=1")->execute(["Pengembalian untuk permohonan #{$id} menunggu konfirmasi.",current_tenant_id()]);
                $pdo->commit();
                record_activity($pdo, 'return_requested', "Permohonan #{$id}");
                flash('Pengajuan pengembalian dikirim kepada petugas.');
                redirect('?page=borrowings');

            case 'confirm_return':
                require_role(['admin','institution_admin','staff']);
                $id = (int) ($_POST['borrowing_id'] ?? 0);
                $tenantId = current_tenant_id();
                $scope = $tenantId ? ' AND tenant_id=?' : '';
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE borrowings SET status='completed',returned_at=NOW(),confirmed_by=? WHERE id=? AND status='return_pending'{$scope}");
                $stmt->execute($tenantId ? [current_user()['id'],$id,$tenantId] : [current_user()['id'],$id]);
                if (!$stmt->rowCount()) {
                    throw new RuntimeException('Pengembalian belum diajukan atau sudah dikonfirmasi.');
                }
                $pdo->prepare('INSERT INTO returns (borrowing_id, confirmed_by, condition_note) VALUES (?, ?, ?)')->execute([$id, current_user()['id'], trim((string) ($_POST['condition_note'] ?? ''))]);
                $pdo->prepare("INSERT INTO notifications (user_id,title,message) SELECT user_id,'Pengembalian selesai',? FROM borrowings WHERE id=?{$scope}")->execute($tenantId ? ["Pengembalian untuk permohonan #{$id} telah dikonfirmasi.",$id,$tenantId] : ["Pengembalian untuk permohonan #{$id} telah dikonfirmasi.",$id]);
                $pdo->commit();
                record_activity($pdo, 'return_confirmed', "Permohonan #{$id}");
                flash('Pengembalian berhasil dikonfirmasi dan stok telah tersedia kembali.');
                redirect('?page=borrowings');

            case 'save_resource':
                require_role(['admin','institution_admin','staff']);
                save_resource($pdo);
                flash('Data katalog berhasil disimpan.');
                redirect('?page=catalog&type=' . rawurlencode((string) ($_POST['type'] ?? 'item')));

            case 'delete_resource':
                require_role(['admin','institution_admin']);
                $type = (string) ($_POST['type'] ?? '');
                $tables = ['item' => 'items', 'book' => 'books', 'lab' => 'laboratory_equipments', 'room' => 'rooms'];
                if (!isset($tables[$type])) {
                    throw new RuntimeException('Jenis katalog tidak valid.');
                }
                $reference = $pdo->prepare('SELECT COUNT(*) FROM borrowing_details WHERE resource_type=? AND resource_id=?');
                $reference->execute([$type, (int) ($_POST['resource_id'] ?? 0)]);
                if ((int) $reference->fetchColumn() > 0) {
                    throw new RuntimeException('Data katalog memiliki riwayat transaksi dan tidak dapat dihapus.');
                }
                $tenantId = current_tenant_id();
                if (!$tenantId) {
                    throw new RuntimeException('Pilih institusi terlebih dahulu.');
                }
                $stmt = $pdo->prepare("DELETE FROM {$tables[$type]} WHERE id=? AND tenant_id=?");
                $stmt->execute([(int)($_POST['resource_id']??0),$tenantId]);
                flash($stmt->rowCount() ? 'Data katalog dihapus.' : 'Data tidak ditemukan.', $stmt->rowCount() ? 'success' : 'error');
                redirect('?page=catalog&type=' . rawurlencode($type));

            case 'create_user':
                require_role(['admin','institution_admin']);
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
                $password = (string) ($_POST['password'] ?? '');
                $role = (string) ($_POST['role'] ?? 'user');
                if ($name === '' || !$email || strlen($password) < 8 || !in_array($role, ['staff','user'], true) || !current_tenant_id()) {
                    throw new RuntimeException('Lengkapi nama, email, role, dan kata sandi minimal 8 karakter.');
                }
                $stmt = $pdo->prepare('INSERT INTO users (role_id,tenant_id,name,email,password,student_number,cohort,phone) SELECT id,?,?,?,?,?,?,? FROM roles WHERE name=?');
                $stmt->execute([current_tenant_id(),$name,$email,password_hash($password,PASSWORD_DEFAULT),trim((string)($_POST['student_number']??''))?:null,trim((string)($_POST['cohort']??''))?:null,trim((string)($_POST['phone']??''))?:null,$role]);
                flash('Akun berhasil ditambahkan.');
                redirect('?page=users');

            case 'toggle_user':
                require_role(['admin','institution_admin']);
                $id = (int) ($_POST['user_id'] ?? 0);
                if ($id === (int) current_user()['id']) {
                    throw new RuntimeException('Akun yang sedang digunakan tidak dapat dinonaktifkan.');
                }
                $pdo->prepare('UPDATE users SET is_active=NOT is_active WHERE id=? AND tenant_id=?')->execute([$id,current_tenant_id()]);
                flash('Status akun diperbarui.');
                redirect('?page=users');

            case 'update_user':
                require_role(['admin','institution_admin']);
                $id = (int) ($_POST['user_id'] ?? 0);
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
                $role = (string) ($_POST['role'] ?? '');
                $password = (string) ($_POST['password'] ?? '');
                $tenantId = current_tenant_id();
                if (!$id || $name === '' || !$email || !in_array($role, ['staff','user'], true) || ($password !== '' && strlen($password) < 8) || !$tenantId) {
                    throw new RuntimeException('Periksa nama, email, role, dan kata sandi minimal 8 karakter.');
                }
                $roleId = $pdo->prepare('SELECT id FROM roles WHERE name=?');
                $roleId->execute([$role]);
                if ($password === '') {
                    $pdo->prepare('UPDATE users SET role_id=?,name=?,email=?,student_number=?,cohort=?,phone=? WHERE id=? AND tenant_id=?')->execute([$roleId->fetchColumn(),$name,$email,trim((string)($_POST['student_number']??''))?:null,trim((string)($_POST['cohort']??''))?:null,trim((string)($_POST['phone']??''))?:null,$id,$tenantId]);
                } else {
                    $pdo->prepare('UPDATE users SET role_id=?,name=?,email=?,password=?,student_number=?,cohort=?,phone=? WHERE id=? AND tenant_id=?')->execute([$roleId->fetchColumn(),$name,$email,password_hash($password,PASSWORD_DEFAULT),trim((string)($_POST['student_number']??''))?:null,trim((string)($_POST['cohort']??''))?:null,trim((string)($_POST['phone']??''))?:null,$id,$tenantId]);
                }
                flash('Data akun berhasil diperbarui.');
                redirect('?page=users');

            case 'delete_user':
                require_role(['admin','institution_admin']);
                $id = (int) ($_POST['user_id'] ?? 0);
                if (!$id || $id === (int) current_user()['id']) {
                    throw new RuntimeException('Akun yang sedang digunakan tidak dapat dihapus.');
                }
                $tenantId = current_tenant_id();
                $references = $pdo->prepare('SELECT COUNT(*) FROM borrowings WHERE user_id=? AND tenant_id=?');
                $references->execute([$id,$tenantId]);
                if ((int) $references->fetchColumn() > 0) {
                    throw new RuntimeException('Akun memiliki riwayat peminjaman. Nonaktifkan akun agar riwayat tetap tersimpan.');
                }
                $pdo->prepare("DELETE FROM users WHERE id=? AND tenant_id=? AND role_id=(SELECT id FROM roles WHERE name='user')")->execute([$id,$tenantId]);
                flash('Akun berhasil dihapus.');
                redirect('?page=users');

            case 'save_settings':
                require_role(['institution_admin']);
                $schoolName = trim((string) ($_POST['school_name'] ?? ''));
                $maxDays = filter_var($_POST['max_loan_days'] ?? null, FILTER_VALIDATE_INT);
                $dailyFee = filter_var($_POST['late_fee_per_day'] ?? null, FILTER_VALIDATE_INT);
                if ($schoolName === '' || mb_strlen($schoolName) > 100 || !$maxDays || $maxDays < 1 || $maxDays > 365 || $dailyFee === false || $dailyFee < 0 || $dailyFee > 1000000) {
                    throw new RuntimeException('Nama institusi wajib diisi; durasi harus 1–365 hari dan denda 0–1.000.000 per hari.');
                }
                $stmt = $pdo->prepare('INSERT INTO system_settings (tenant_id,setting_key,setting_value,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
                $stmt->execute([current_tenant_id(),'school_name',$schoolName,current_user()['id']]);
                $stmt->execute([current_tenant_id(),'max_loan_days',(string)$maxDays,current_user()['id']]);
                $stmt->execute([current_tenant_id(),'late_fee_per_day',(string)$dailyFee,current_user()['id']]);
                $pdo->prepare('UPDATE institutions SET name=? WHERE id=?')->execute([$schoolName,current_tenant_id()]);
                record_activity($pdo, 'settings_updated');
                flash('Pengaturan SIPINTAR berhasil diperbarui.');
                redirect('?page=settings');

            case 'save_profile':
                require_login();
                $name = trim((string) ($_POST['name'] ?? ''));
                if (mb_strlen($name) < 2) {
                    throw new RuntimeException('Nama minimal 2 karakter.');
                }
                $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, current_user()['id']]);
                $_SESSION['user']['name'] = $name;
                flash('Profil berhasil diperbarui.');
                redirect('?page=profile');

            case 'create_category':
                require_role(['admin','institution_admin']);
                $type = (string) ($_POST['type'] ?? '');
                $name = trim((string) ($_POST['name'] ?? ''));
                if (!in_array($type, ['item', 'book', 'lab', 'room'], true) || $name === '') {
                    throw new RuntimeException('Jenis dan nama kategori wajib diisi.');
                }
                $tenantId = current_tenant_id();
                if (!$tenantId) {
                    throw new RuntimeException('Pilih institusi terlebih dahulu.');
                }
                $pdo->prepare('INSERT INTO categories (tenant_id,type,name) VALUES (?,?,?)')->execute([$tenantId,$type,$name]);
                flash('Kategori berhasil ditambahkan.');
                redirect('?page=catalog&type=' . rawurlencode($type));

            case 'delete_category':
                require_role(['admin','institution_admin']);
                $type = (string) ($_POST['type'] ?? '');
                $id = (int) ($_POST['category_id'] ?? 0);
                $category = $pdo->prepare('SELECT type FROM categories WHERE id=? AND tenant_id=?');
                $category->execute([$id,current_tenant_id()]);
                if ($category->fetchColumn() !== $type) {
                    throw new RuntimeException('Kategori tidak ditemukan.');
                }
                $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
                flash('Kategori berhasil dihapus.');
                redirect('?page=catalog&type=' . rawurlencode($type));

            case 'save_modules':
                require_role(['institution_admin']);
                $enabled = array_unique(array_map('strval', (array) ($_POST['modules'] ?? [])));
                $allowed = ['item','book','lab','room','complaints'];
                if (array_diff($enabled, $allowed)) {
                    throw new RuntimeException('Pilihan fitur tidak valid.');
                }
                $stmt = $pdo->prepare('INSERT INTO institution_modules (tenant_id,module_key,is_enabled) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled)');
                foreach ($allowed as $module) {
                    $stmt->execute([current_tenant_id(),$module,in_array($module,$enabled,true)?1:0]);
                }
                flash('Fitur institusi berhasil diperbarui.');
                redirect('?page=settings');

            case 'create_complaint':
                require_role(['user']);
                if (!feature_enabled($pdo,'complaints')) {
                    throw new RuntimeException('Layanan keluhan sedang dinonaktifkan oleh institusi.');
                }
                $subject = trim((string) ($_POST['subject'] ?? ''));
                $message = trim((string) ($_POST['message'] ?? ''));
                $category = (string) ($_POST['category'] ?? 'lainnya');
                $borrowingId = filter_var($_POST['borrowing_id'] ?? null,FILTER_VALIDATE_INT) ?: null;
                if (mb_strlen($subject)<4 || mb_strlen($subject)>180 || mb_strlen($message)<10 || !in_array($category,['fasilitas','peminjaman','akun','layanan','lainnya'],true)) {
                    throw new RuntimeException('Subjek minimal 4 karakter dan isi keluhan minimal 10 karakter.');
                }
                if ($borrowingId) {
                    $stmt=$pdo->prepare('SELECT id FROM borrowings WHERE id=? AND user_id=? AND tenant_id=?');
                    $stmt->execute([$borrowingId,current_user()['id'],current_tenant_id()]);
                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException('Transaksi yang dipilih tidak ditemukan.');
                    }
                }
                $stmt=$pdo->prepare("INSERT INTO complaints (tenant_id,user_id,borrowing_id,category,subject,message) VALUES (?,?,?,?,?,?)");
                $stmt->execute([current_tenant_id(),current_user()['id'],$borrowingId,$category,$subject,$message]);
                $complaintId=(int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO notifications (user_id,title,message) SELECT id,'Keluhan baru',? FROM users WHERE tenant_id=? AND role_id=(SELECT id FROM roles WHERE name='institution_admin') AND is_active=1")->execute(["Keluhan #{$complaintId} membutuhkan tindak lanjut.",current_tenant_id()]);
                flash('Keluhan berhasil dikirim kepada admin institusi.');
                redirect('?page=complaints');

            case 'update_complaint':
                require_role(['admin','institution_admin','staff']);
                $id=(int)($_POST['complaint_id']??0);
                $status=(string)($_POST['status']??'');
                $reply=trim((string)($_POST['admin_reply']??''));
                if (!in_array($status,['new','in_progress','resolved','closed'],true)) {
                    throw new RuntimeException('Status keluhan tidak valid.');
                }
                $tenantId=current_tenant_id();
                $scope=$tenantId?' AND tenant_id=?':'';
                $stmt=$pdo->prepare("UPDATE complaints SET status=?,admin_reply=?,handled_by=? WHERE id=?{$scope}");
                $stmt->execute($tenantId?[$status,$reply?:null,current_user()['id'],$id,$tenantId]:[$status,$reply?:null,current_user()['id'],$id]);
                if (!$stmt->rowCount()) {
                    throw new RuntimeException('Keluhan tidak ditemukan atau tidak ada perubahan.');
                }
                $stmt=$pdo->prepare("INSERT INTO notifications (user_id,title,message) SELECT user_id,'Pembaruan keluhan',? FROM complaints WHERE id=?{$scope}");
                $stmt->execute($tenantId?["Keluhan #{$id}: ".($reply?:$status),$id,$tenantId]:["Keluhan #{$id}: ".($reply?:$status),$id]);
                flash('Keluhan berhasil diperbarui.');
                redirect('?page=complaints');

            default:
                throw new RuntimeException('Perintah tidak dikenali.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
            flash('Data tidak dapat disimpan karena email atau relasi sudah digunakan.', 'error');
        } elseif ($exception instanceof RuntimeException) {
            flash($exception->getMessage(), 'error');
        } else {
            error_log((string) $exception);
            flash('Terjadi kesalahan saat menyimpan data. Silakan coba lagi.', 'error');
        }
        redirect($action === 'register_institution' ? '?page=register&type=institution' : ($_SERVER['REQUEST_URI'] ?? '?page=dashboard'));
    }
}

function save_resource(PDO $pdo): void
{
    $type = (string) ($_POST['type'] ?? '');
    $tables = ['item' => 'items', 'book' => 'books', 'lab' => 'laboratory_equipments', 'room' => 'rooms'];
    if (!isset($tables[$type])) {
        throw new RuntimeException('Jenis katalog tidak valid.');
    }
    $id = filter_var($_POST['resource_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $tenantId = current_tenant_id();
    if (!$tenantId) {
        throw new RuntimeException('Pilih institusi terlebih dahulu.');
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $category = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $description = trim((string) ($_POST['description'] ?? ''));
    if ($name === '' || !$category) {
        throw new RuntimeException('Nama dan kategori wajib diisi.');
    }
    $categoryCheck = $pdo->prepare('SELECT type FROM categories WHERE id=? AND is_active=1 AND (tenant_id=? OR tenant_id IS NULL)');
    $categoryCheck->execute([$category,$tenantId]);
    if ($categoryCheck->fetchColumn() !== $type) {
        throw new RuntimeException('Pilih kategori yang sesuai dengan jenis katalog.');
    }
    $table = $tables[$type];
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK || $_FILES['image']['size'] > 3 * 1024 * 1024) {
            throw new RuntimeException('Unggahan foto gagal atau ukurannya melebihi 3 MB.');
        }
        $temporaryPath = $_FILES['image']['tmp_name'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || @getimagesize($temporaryPath) === false) {
            throw new RuntimeException('Foto harus berupa gambar JPG, PNG, atau WebP yang valid.');
        }
        $uploadDirectory = dirname(__DIR__) . '/uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Folder upload tidak dapat dibuat.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($temporaryPath, $uploadDirectory . '/' . $filename)) {
            throw new RuntimeException('Foto gagal disimpan. Periksa izin folder uploads.');
        }
        $imagePath = 'uploads/' . $filename;
    }
    if ($type === 'room') {
        $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT);
        if (!$capacity || $capacity < 1) {
            throw new RuntimeException('Kapasitas ruangan minimal 1 orang.');
        }
        $facilities = trim((string) ($_POST['facilities'] ?? ''));
        if ($id) {
            $stmt = $pdo->prepare("UPDATE {$table} SET category_id=?,name=?,capacity=?,facilities=?,description=?,image_path=COALESCE(?,image_path) WHERE id=? AND tenant_id=?");
            $stmt->execute([$category,$name,$capacity,$facilities,$description,$imagePath,$id,$tenantId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$table} (tenant_id,category_id,name,capacity,facilities,description,image_path) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$tenantId,$category,$name,$capacity,$facilities,$description,$imagePath]);
        }
        return;
    }
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
    if ($stock === false || $stock < 0) {
        throw new RuntimeException('Stok harus berupa bilangan bulat nol atau lebih.');
    }
    $condition = trim((string) ($_POST['condition'] ?? 'Baik'));
    $location = trim((string) ($_POST['location'] ?? ''));
    if ($id && $stock < active_count($pdo, $type, $id)) {
        throw new RuntimeException('Stok tidak dapat lebih kecil dari jumlah unit yang sedang dipinjam.');
    }
    if (in_array($type, ['book', 'lab'], true)) {
        $author = trim((string) ($_POST['author'] ?? ''));
        $publisher = trim((string) ($_POST['publisher'] ?? ''));
        $year = filter_var($_POST['published_year'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($id) {
            $stmt = $pdo->prepare("UPDATE {$table} SET category_id=?,name=?,author=?,publisher=?,published_year=?,stock=?,`condition`=?,location=?,description=?,image_path=COALESCE(?,image_path) WHERE id=? AND tenant_id=?");
            $stmt->execute([$category,$name,$author,$publisher,$year,$stock,$condition,$location,$description,$imagePath,$id,$tenantId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$table} (tenant_id,category_id,name,author,publisher,published_year,stock,`condition`,location,description,image_path) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$tenantId,$category,$name,$author,$publisher,$year,$stock,$condition,$location,$description,$imagePath]);
        }
        return;
    }
    if ($id) {
        $stmt = $pdo->prepare("UPDATE {$table} SET category_id=?,name=?,stock=?,`condition`=?,location=?,description=?,image_path=COALESCE(?,image_path) WHERE id=? AND tenant_id=?");
        $stmt->execute([$category,$name,$stock,$condition,$location,$description,$imagePath,$id,$tenantId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO {$table} (tenant_id,category_id,name,stock,`condition`,location,description,image_path) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$tenantId,$category,$name,$stock,$condition,$location,$description,$imagePath]);
    }
}
