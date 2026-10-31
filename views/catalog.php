<?php
$typeNames = ['item'=>'Peralatan sekolah','book'=>'Koleksi buku','lab'=>'Alat laboratorium','room'=>'Ruangan'];
$editable = in_array($user['role'], ['admin','institution_admin','staff'], true);
$editing = $edit_resource ?? null;
?>
<div class="welcome-row"><div><div class="eyebrow">KATALOG SIPINTAR</div><h1><?= e($typeNames[$type]) ?></h1><p class="muted">Temukan fasilitas dan kirim permohonan peminjaman.</p></div>
<?php if ($editable): ?><a href="#form-katalog" class="btn btn-primary"><?= $editing?'Edit data':'＋ Tambah data' ?></a><?php endif; ?></div>
<div class="tabs"><?php foreach ($typeNames as $key=>$name): if(!feature_enabled($pdo,$key))continue; ?><a class="<?= $type===$key?'selected':'' ?>" href="?page=catalog&type=<?= e($key) ?>"><?= e($name) ?></a><?php endforeach; ?></div>
<?php if(in_array($user['role'],['admin','institution_admin'],true)): ?><details class="panel resource-form category-manager"><summary>Kelola kategori <?= e(strtolower($typeNames[$type])) ?></summary><form method="post" class="category-create"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_category"><input type="hidden" name="type" value="<?= e($type) ?>"><input name="name" placeholder="Nama kategori baru" required><button class="btn btn-primary">Tambah kategori</button></form><div class="category-list"><?php foreach($type_categories as $category): ?><span class="category-chip"><?= e($category['name']) ?><?php if((int)($category['tenant_id']??0)===(int)$tenant_id): ?><form method="post" data-confirm="Hapus kategori ini? Kategori yang dipakai data tidak dapat dihapus."><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>"><button aria-label="Hapus kategori">×</button></form><?php endif; ?></span><?php endforeach; ?></div></details><?php endif; ?>
<form class="search-bar" method="get"><input type="hidden" name="page" value="catalog"><input type="hidden" name="type" value="<?= e($type) ?>"><span>⌕</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau kategori..."><button class="btn btn-light">Cari</button></form>
<?php if ($editable): ?>
<details class="panel resource-form" id="form-katalog" <?= $editing?'open':'' ?>><summary><?= $editing?'Perbarui data katalog':'Tambah data katalog' ?></summary>
    <form method="post" enctype="multipart/form-data" class="resource-fields">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_resource"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="resource_id" value="<?= (int)($editing['id']??0) ?>">
        <label>Nama<input name="name" required value="<?= e($editing['name']??'') ?>"></label>
        <label>Kategori<select name="category_id" required><option value="">Pilih kategori</option><?php foreach ($categories as $category): if($category['type']!==$type) continue; ?><option value="<?= (int)$category['id'] ?>" <?= (int)($editing['category_id']??0)===(int)$category['id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
        <?php if($type==='room'): ?><label>Kapasitas<input type="number" name="capacity" min="1" value="<?= e($editing['capacity']??1) ?>" required></label><label>Fasilitas<input name="facilities" value="<?= e($editing['facilities']??'') ?>" placeholder="Proyektor, AC, papan tulis"></label>
        <?php else: ?><label>Stok<input type="number" name="stock" min="0" value="<?= e($editing['stock']??0) ?>" required></label><label>Kondisi<input name="condition" value="<?= e($editing['condition']??'Baik') ?>"></label><label>Lokasi<input name="location" value="<?= e($editing['location']??'') ?>"></label>
            <?php if(in_array($type,['book','lab'],true)): ?><label>Penulis / pembuat<input name="author" value="<?= e($editing['author']??'') ?>"></label><label>Penerbit<input name="publisher" value="<?= e($editing['publisher']??'') ?>"></label><label>Tahun terbit<input type="number" name="published_year" min="1900" max="<?= date('Y')+1 ?>" value="<?= e($editing['published_year']??'') ?>"></label><?php endif; ?>
        <?php endif; ?>
        <label>Foto (JPG, PNG, WebP · maks. 3 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
        <label class="field-wide">Deskripsi<textarea name="description" rows="2"><?= e($editing['description']??'') ?></textarea></label>
        <div class="field-wide form-actions"><button class="btn btn-primary"><?= $editing?'Simpan perubahan':'Simpan data' ?></button><?php if($editing): ?><a class="btn btn-light" href="?page=catalog&type=<?= e($type) ?>">Batal</a><?php endif; ?></div>
    </form>
</details>
<?php endif; ?>
<?php if (!$resources): ?><div class="panel empty-state"><span>⌕</span><strong>Tidak ada data ditemukan</strong><p>Coba ubah kata pencarian atau tambahkan data katalog.</p></div><?php else: ?>
<div class="catalog-grid">
<?php foreach($resources as $resource): $available=$type==='room'?null:max(0,(int)$resource['stock']-active_count($pdo,$type,(int)$resource['id'])); ?>
<article class="catalog-card"><div class="resource-visual <?= e($type) ?>" <?php if($resource['image_path']): ?>style="background-image:linear-gradient(#142d4e22,#142d4e22),url('<?= e($resource['image_path']) ?>');background-size:cover;background-position:center"<?php endif; ?>><span><?= $resource['image_path']?'':e(['item'=>'▣','book'=>'▤','lab'=>'⚗','room'=>'⌂'][$type]) ?></span><small><?= e($resource['category_name']??'Tanpa kategori') ?></small></div>
    <div class="catalog-info"><h3><?= e($resource['name']) ?></h3><p><?= e($resource['description'] ?: ($resource['location'] ?? 'Fasilitas sekolah')) ?></p>
        <?php if($type==='room'): ?><div class="resource-meta"><span>♙ Kapasitas <?= (int)$resource['capacity'] ?></span><span>✦ <?= e($resource['facilities']?:'Fasilitas standar') ?></span></div><?php if(!empty($room_schedule[$resource['id']])): ?><div class="room-slots"><strong>Jadwal terisi</strong><?php foreach(array_slice($room_schedule[$resource['id']],0,3) as $slot): ?><small><?= e(date('d M, H:i',strtotime($slot['start_at']))) ?>–<?= e(date('H:i',strtotime($slot['end_at']))) ?> · <?= e(status_label($slot['status'])) ?></small><?php endforeach; ?></div><?php else: ?><div class="room-slots available-slot">✓ Belum ada jadwal terisi</div><?php endif; ?>
        <?php else: ?><div class="resource-meta"><span>▣ Stok tersedia <strong><?= $available ?></strong></span><span>◉ <?= e($resource['condition']??'Baik') ?></span></div><?php if(in_array($type,['book','lab'],true)&&$resource['author']): ?><small class="book-author"><?= e($resource['author']) ?><?= $resource['published_year']?' · '.e($resource['published_year']):'' ?></small><?php endif; ?><?php endif; ?>
        <div class="card-actions">
        <?php if($user['role']==='user'): ?><details class="borrow-details"><summary class="btn btn-primary <?= $type!=='room'&&$available<1?'disabled':'' ?>"><?= $type!=='room'&&$available<1?'Stok habis':'Ajukan pinjam' ?></summary><?php if($type==='room'||$available>0): ?><form method="post" class="borrow-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_borrowing"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>">
            <label>Tanggal mulai<input type="date" name="start_date" min="<?= date('Y-m-d') ?>" <?= $type!=='room'?'data-max-days="'.(int)$max_loan_days.'"':'' ?> required></label>
            <label>Tanggal selesai<input type="date" name="end_date" min="<?= date('Y-m-d') ?>" required></label>
            <?php if($type==='room'): ?><label>Jam mulai<input type="time" name="start_time" required></label><label>Jam selesai<input type="time" name="end_time" required></label><?php else: ?><label>Jumlah<input type="number" name="quantity" min="1" max="<?= max(1,$available) ?>" value="1" required></label><?php endif; ?>
            <label class="field-wide">Tujuan peminjaman<textarea name="reason" rows="2" required></textarea></label><button class="btn btn-primary field-wide">Kirim permohonan</button></form><?php endif; ?></details>
        <?php elseif($editable): ?><a class="btn btn-light" href="?page=catalog&type=<?= e($type) ?>&edit=<?= (int)$resource['id'] ?>">Edit</a><?php if(in_array($user['role'],['admin','institution_admin'],true)): ?><form method="post" data-confirm="Hapus data katalog ini?"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_resource"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>"><button class="btn btn-danger-light">Hapus</button></form><?php endif; ?><?php endif; ?>
        </div>
    </div>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
