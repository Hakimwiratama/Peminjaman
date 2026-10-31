<div class="welcome-row"><div><div class="eyebrow"><?= $user['role']==='user'?'AKUN SAYA':'OPERASIONAL' ?></div><h1><?= $user['role']==='user'?'Riwayat peminjaman':'Permohonan peminjaman' ?></h1><p class="muted"><?= $user['role']==='user'?'Pantau status dan jadwal seluruh permohonan Anda.':'Verifikasi permohonan dan konfirmasi pengembalian.' ?></p></div><?php if($user['role']==='user'): ?><a href="?page=catalog&type=item" class="btn btn-primary">＋ Ajukan pinjaman</a><?php endif; ?></div>
<section class="panel">
<?php if(!$borrowings): ?><div class="empty-state"><span>◷</span><strong>Belum ada permohonan</strong><p>Transaksi yang diajukan akan muncul di halaman ini.</p></div><?php else: ?>
<div class="table-wrap"><table class="wide-table"><thead><tr><?php if($user['role']!=='user'): ?><th>Peminjam</th><?php endif; ?><th>Objek</th><th>Jadwal</th><th>Tujuan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($borrowings as $borrowing): ?>
<tr>
<?php if($user['role']!=='user'): ?><td><strong><?= e($borrowing['borrower_name']) ?></strong><small class="cell-sub"><?= e($borrowing['student_number']?:$borrowing['borrower_email']) ?><?= $borrowing['cohort']?' · '.e($borrowing['cohort']):'' ?></small><?php if($user['role']==='admin'): ?><small class="cell-sub"><?= e($borrowing['institution_name']??'') ?></small><?php endif; ?></td><?php endif; ?>
<td><strong><?= e($borrowing['resource']['name']??'Data tidak tersedia') ?></strong><small class="cell-sub"><?= e(type_label($borrowing['type'])) ?> · #<?= (int)$borrowing['id'] ?><?= $borrowing['type']!=='room'?' · '.(int)$borrowing['quantity'].' unit':'' ?></small></td>
<td><?= e(date('d M Y',strtotime($borrowing['start_date']))) ?><small class="cell-sub">s/d <?= e(date('d M Y',strtotime($borrowing['end_date']))) ?></small><?php if($borrowing['type']==='room'&&$borrowing['start_at']): ?><small class="cell-sub"><?= e(date('H:i',strtotime($borrowing['start_at']))) ?>–<?= e(date('H:i',strtotime($borrowing['end_at']))) ?></small><?php endif; ?></td>
<td class="reason-cell"><?= e($borrowing['reason']) ?><?php if($borrowing['rejection_reason']): ?><small class="rejection-reason">Alasan: <?= e($borrowing['rejection_reason']) ?></small><?php endif; ?></td>
<td><span class="badge status-<?= e($borrowing['status_display']) ?>"><?= e(status_label($borrowing['status_display'])) ?></span></td>
<td class="actions-cell">
<?php if(in_array($user['role'],['admin','institution_admin','staff'],true)&&$borrowing['status']==='waiting'): ?>
    <form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_borrowing"><input type="hidden" name="borrowing_id" value="<?= (int)$borrowing['id'] ?>"><input type="hidden" name="decision" value="approve"><button class="btn btn-success">Setujui</button></form>
    <details class="reject-details"><summary class="btn btn-danger-light">Tolak</summary><form method="post" class="mini-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_borrowing"><input type="hidden" name="borrowing_id" value="<?= (int)$borrowing['id'] ?>"><input type="hidden" name="decision" value="reject"><label>Alasan penolakan<textarea name="rejection_reason" rows="2" required></textarea></label><button class="btn btn-danger">Konfirmasi tolak</button></form></details>
<?php elseif(in_array($user['role'],['admin','institution_admin','staff'],true)&&$borrowing['status']==='approved'): ?>
    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="set_borrowed"><input type="hidden" name="borrowing_id" value="<?= (int)$borrowing['id'] ?>"><button class="btn btn-primary">Tandai diserahkan</button></form>
<?php elseif(in_array($user['role'],['admin','institution_admin','staff'],true)&&$borrowing['status']==='return_pending'): ?>
    <form method="post" class="return-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="confirm_return"><input type="hidden" name="borrowing_id" value="<?= (int)$borrowing['id'] ?>"><input name="condition_note" placeholder="Kondisi saat kembali"><button class="btn btn-success">Konfirmasi kembali</button></form>
<?php elseif($user['role']==='user'&&$borrowing['status']==='borrowed'): ?>
    <form method="post" data-confirm="Ajukan pengembalian kepada petugas?"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="request_return"><input type="hidden" name="borrowing_id" value="<?= (int)$borrowing['id'] ?>"><button class="btn btn-light">Ajukan kembali</button></form>
<?php else: ?><span class="muted">—</span><?php endif; ?>
</td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
