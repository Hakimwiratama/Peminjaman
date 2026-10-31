<?php if ($user['role'] === 'user'): ?>
    <div class="welcome-row"><div><div class="eyebrow">DASHBOARD PENGGUNA</div><h1>Halo, <?= e(explode(' ', $user['name'])[0]) ?> <span class="wave">✦</span></h1><p class="muted">Temukan dan pinjam fasilitas sekolah yang Anda butuhkan.</p></div><a class="btn btn-primary" href="?page=catalog&type=item">Jelajahi katalog <span>→</span></a></div>
    <?php if ($notifications): ?><div class="notice-strip"><span>✦</span><div><strong>Notifikasi terbaru</strong><?php foreach ($notifications as $notification): ?><p><?= e($notification['message']) ?></p><?php endforeach; ?></div><a href="?page=borrowings">Lihat status →</a></div><?php endif; ?>
    <div class="stat-grid three">
        <article class="stat-card"><div class="stat-icon blue">◷</div><span>Total permohonan</span><strong><?= $my_total ?></strong><small>Semua pengajuan Anda</small></article>
        <article class="stat-card"><div class="stat-icon amber">⌛</div><span>Menunggu persetujuan</span><strong><?= $my_waiting ?></strong><small>Dalam proses verifikasi</small></article>
        <article class="stat-card"><div class="stat-icon green">✓</div><span>Peminjaman selesai</span><strong><?= $my_completed ?></strong><small>Riwayat pengembalian</small></article>
    </div>
    <section class="section-block"><div class="section-heading"><div><h2>Mulai peminjaman</h2><p>Pilih kategori fasilitas yang ingin Anda gunakan.</p></div></div>
        <div class="category-grid">
            <?php foreach (['item'=>['Peralatan','Barang sekolah dan inventaris','▣','blue'],'book'=>['Perpustakaan','Koleksi buku dan referensi','▤','purple'],'lab'=>['Laboratorium','Alat praktikum dan riset','⚗','green'],'room'=>['Ruangan','Ruang kelas dan fasilitas','⌂','amber']] as $type=>$item): if(!feature_enabled($pdo,$type))continue; ?>
            <a class="category-card" href="?page=catalog&type=<?= e($type) ?>"><span class="category-icon <?= e($item[3]) ?>"><?= e($item[2]) ?></span><span><strong><?= e($item[0]) ?></strong><small><?= e($item[1]) ?></small></span><b>→</b></a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="section-block panel"><div class="section-heading"><div><h2>Aktivitas terbaru</h2><p>Ringkasan permohonan peminjaman Anda.</p></div><a href="?page=borrowings" class="text-link">Lihat semua →</a></div>
        <?php if (!$recent): ?><div class="empty-state"><span>◷</span><strong>Belum ada aktivitas</strong><p>Permohonan Anda akan terlihat di sini.</p></div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Objek</th><th>Tanggal</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $row): $resource=resource_data($pdo,$row['resource_type'],(int)$row['resource_id']); ?><tr><td><strong><?= e($resource['name'] ?? 'Data dihapus') ?></strong><small class="cell-sub"><?= e(type_label($row['type'])) ?></small></td><td><?= e(date('d M Y',strtotime($row['start_date']))) ?> – <?= e(date('d M Y',strtotime($row['end_date']))) ?></td><td><span class="badge status-<?= e($row['status']) ?>"><?= e(status_label($row['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <div class="welcome-row"><div><div class="eyebrow"><?= $user['role']==='admin'?'ADMIN SIPINTAR':($user['role']==='institution_admin'?'ADMIN INSTITUSI':'PANEL PETUGAS') ?></div><h1><?= e($tenant_id?($user['institution_name']??'Portal institusi'):'Dashboard operasional') ?></h1><p class="muted"><?= $user['role']==='admin'&&!$tenant_id?'Kelola institusi dan pantau aktivitas seluruh portal.':'Pantau peminjaman dan ketersediaan fasilitas secara real-time.' ?></p></div><a class="btn btn-primary" href="<?= $user['role']==='admin'&&!$tenant_id?'?page=institutions':'?page=borrowings' ?>"><?= $user['role']==='admin'&&!$tenant_id?'Kelola institusi':'Tinjau permohonan' ?> <span>→</span></a></div>
    <?php if($user['role']==='admin'): ?><div class="notice-strip"><span>▦</span><div><strong><?= (int)$pending_institution_count ?> pendaftaran sekolah/kampus menunggu persetujuan</strong><p>Buka daftar pendaftaran untuk memeriksa dan menyetujui akun institusi.</p></div><a href="?page=institutions">Lihat pendaftaran →</a></div><?php endif; ?>
    <div class="stat-grid">
        <article class="stat-card"><div class="stat-icon blue">◷</div><span>Total peminjaman</span><strong><?= $total ?></strong><small>Seluruh transaksi tercatat</small></article>
        <article class="stat-card"><div class="stat-icon amber">⌛</div><span>Menunggu verifikasi</span><strong><?= $waiting ?></strong><small>Perlu ditinjau petugas</small></article>
        <article class="stat-card"><div class="stat-icon purple">↗</div><span>Sedang dipinjam</span><strong><?= $borrowed ?></strong><small>Termasuk menunggu kembali</small></article>
        <article class="stat-card"><div class="stat-icon green">▣</div><span>Stok inventaris</span><strong><?= $inventory ?></strong><small>Barang, buku, dan alat lab</small></article>
    </div>
    <section class="section-block panel"><div class="section-heading"><div><h2>Permohonan terbaru</h2><p>Aktivitas peminjaman terkini.</p></div><a href="?page=borrowings" class="text-link">Semua permohonan →</a></div>
        <?php if (!$recent): ?><div class="empty-state"><span>▤</span><strong>Belum ada permohonan</strong><p>Transaksi baru akan tampil di sini.</p></div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Peminjam</th><th>Objek</th><th>Tanggal</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $row): $resource=resource_data($pdo,$row['resource_type'],(int)$row['resource_id']); ?><tr><td><strong><?= e($row['borrower_name']) ?></strong></td><td><?= e($resource['name'] ?? 'Data dihapus') ?><small class="cell-sub"><?= e(type_label($row['type'])) ?></small></td><td><?= e(date('d M Y',strtotime($row['start_date']))) ?></td><td><span class="badge status-<?= e($row['status']) ?>"><?= e(status_label($row['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
