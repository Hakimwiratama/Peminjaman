<section class="auth-card">
    <a class="brand auth-brand" href="?page=login"><span class="brand-mark">S</span><span>SIPINTAR<small>SMART LOAN SYSTEM</small></span></a>
    <div class="auth-kicker">SELAMAT DATANG KEMBALI</div>
    <h1>Masuk ke akun Anda</h1>
    <p class="muted">Kelola kebutuhan peminjaman sekolah dengan mudah.</p>
    <form method="post" class="form-stack">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="login">
        <label>Email<div class="input-wrap"><span>✉</span><input type="email" name="email" autocomplete="username" placeholder="nama@sekolah.ac.id" required autofocus></div></label>
        <label>Kata sandi<div class="input-wrap"><span>⚿</span><input type="password" name="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required></div></label>
        <button class="btn btn-primary btn-full">Masuk <span>→</span></button>
    </form>
    <p class="auth-switch">Belum punya akun? <a href="?page=register">Daftar sebagai sekolah/kampus atau siswa/mahasiswa</a></p>
    <div class="auth-foot">Akses aman · SIPINTAR</div>
</section>
