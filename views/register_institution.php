<section class="auth-card auth-card-wide">
    <a class="brand auth-brand" href="?page=login"><span class="brand-mark">S</span><span>SIPINTAR<small>SMART LOAN SYSTEM</small></span></a>
    <div class="auth-kicker">PORTAL INSTITUSI</div>
    <h1>Daftarkan sekolah atau kampus</h1>
    <p class="muted">Admin SIPINTAR akan meninjau pendaftaran sebelum portal dibuka untuk siswa/mahasiswa.</p>
    <form method="post" class="form-stack">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="register_institution">
        <label>Nama sekolah/kampus<input name="institution_name" minlength="3" required placeholder="Contoh: SMA Negeri 1 Bandung"></label>
        <label>Jenis institusi<select name="institution_type"><option value="school">Sekolah</option><option value="university">Perguruan tinggi</option><option value="other">Lainnya</option></select></label>
        <label>Nama admin/pengelola<input name="owner_name" minlength="2" required></label>
        <label>Email institusi<input type="email" name="email" required></label>
        <label>Nomor telepon<input type="tel" name="phone"></label>
        <label>Alamat<textarea name="address" rows="2"></textarea></label>
        <label>Kata sandi admin<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
        <button class="btn btn-primary btn-full">Kirim pendaftaran institusi</button>
    </form>
    <p class="auth-switch">Sudah terdaftar? <a href="?page=login">Masuk</a></p>
</section>
