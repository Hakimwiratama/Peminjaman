<section class="auth-card">
    <a class="brand auth-brand" href="?page=login"><span class="brand-mark">S</span><span>SIPINTAR<small>SMART LOAN SYSTEM</small></span></a>
    <div class="auth-kicker">PENDAFTARAN INSTITUSI</div>
    <h1>Menunggu persetujuan</h1>
    <p class="muted"><strong><?= e($user['institution_name']??'Institusi Anda') ?></strong> sedang menunggu verifikasi admin SIPINTAR. Setelah disetujui, katalog, jadwal, dan akun warga institusi dapat dikelola di portal ini.</p>
    <form method="post" class="logout-form-page"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="btn btn-primary btn-full">Keluar</button></form>
</section>
