<section class="auth-card auth-card-wide">
    <a class="brand auth-brand" href="?page=login"><span class="brand-mark">S</span><span>SIPINTAR<small>SMART LOAN SYSTEM</small></span></a>
    <div class="auth-kicker">PENDAFTARAN SIPINTAR</div>
    <h1>Buat akun baru</h1>
    <p class="muted">Pilih jenis akun yang ingin Anda daftarkan.</p>
    <label>Daftar sebagai
        <select id="registrationType" required>
            <option value="">Pilih jenis akun</option>
            <option value="institution" <?= ($registration_type ?? '') === 'institution' ? 'selected' : '' ?>>Pihak sekolah/kampus</option>
            <option value="student" <?= ($registration_type ?? '') === 'student' ? 'selected' : '' ?>>Siswa/mahasiswa</option>
        </select>
    </label>

    <div id="institutionRegistration" <?= ($registration_type ?? '') !== 'institution' ? 'hidden' : '' ?>>
        <h2>Daftarkan sekolah atau kampus</h2>
        <p class="muted">Pendaftaran akan ditinjau admin SIPINTAR sebelum portal dapat digunakan.</p>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="register_institution">
            <label>Jenis institusi<select name="institution_type" data-institution-type><option value="school">Sekolah</option><option value="university">Perguruan tinggi</option><option value="other">Lainnya</option></select></label>
            <label>Provinsi<select name="province_code" data-region-province required><option value="">Pilih provinsi</option><?php foreach($provinces as $province): ?><option value="<?= e($province['code']) ?>"><?= e($province['name']) ?></option><?php endforeach; ?></select></label>
            <label>Kabupaten/kota<select name="regency_code" data-region-regency required disabled><option value="">Pilih kabupaten/kota</option><?php foreach($regencies as $regency): ?><option value="<?= e($regency['code']) ?>" data-parent="<?= e($regency['parent_code']) ?>"><?= e($regency['name']) ?></option><?php endforeach; ?></select></label>
            <label>Nama sekolah/kampus<input name="institution_name" minlength="3" required placeholder="Pilih daerah, lalu ketik nama sekolah" data-school-search><small class="muted" data-school-hint>Pilih provinsi dan kabupaten/kota, lalu ketik minimal 3 huruf untuk mencari. Nama juga dapat diisi manual.</small><input type="hidden" name="institution_npsn" data-school-npsn></label>
            <div class="directory-results" data-school-results aria-live="polite" hidden></div>
            <label>Nama pihak sekolah/pengelola<input name="owner_name" minlength="2" required placeholder="Nama admin atau pengelola"></label>
            <label>Email akun sekolah<input type="email" name="email" required placeholder="admin@sekolah.ac.id"></label>
            <label>Nomor telepon<input type="tel" name="phone" maxlength="40"></label>
            <label>Alamat sekolah/kampus<textarea name="address" rows="2" maxlength="255" required placeholder="Alamat lengkap institusi"></textarea></label>
            <fieldset class="service-options">
                <legend>Layanan yang ingin didaftarkan</legend>
                <label><input type="checkbox" name="services[]" value="item" checked> Peminjaman Barang</label>
                <label><input type="checkbox" name="services[]" value="book" checked> Peminjaman Buku</label>
                <label><input type="checkbox" name="services[]" value="room" checked> Peminjaman Ruangan</label>
            </fieldset>
            <label>Kata sandi admin sekolah<input type="password" name="password" minlength="8" autocomplete="new-password" required placeholder="Minimal 8 karakter"></label>
            <button class="btn btn-primary btn-full">Daftarkan sekolah/kampus <span>→</span></button>
        </form>
    </div>

    <div id="studentRegistration" <?= ($registration_type ?? '') !== 'student' ? 'hidden' : '' ?>>
        <h2>Buat akun siswa/mahasiswa</h2>
        <p class="muted">Pilih sekolah/kampus yang telah aktif untuk mendaftar.</p>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="register">
            <label>Nama lengkap<input type="text" name="name" minlength="2" placeholder="Nama lengkap" required></label>
            <label>Provinsi<select name="province_code" data-region-province required><option value="">Pilih provinsi</option><?php foreach($provinces as $province): ?><option value="<?= e($province['code']) ?>"><?= e($province['name']) ?></option><?php endforeach; ?></select></label>
            <label>Kabupaten/kota<select name="regency_code" data-region-regency required disabled><option value="">Pilih kabupaten/kota</option><?php foreach($regencies as $regency): ?><option value="<?= e($regency['code']) ?>" data-parent="<?= e($regency['parent_code']) ?>"><?= e($regency['name']) ?></option><?php endforeach; ?></select></label>
            <label>Sekolah/kampus<select name="tenant_id" data-institution-select required disabled><option value="">Pilih daerah terlebih dahulu</option><?php foreach($institutions as $institution): ?><option value="<?= (int)$institution['id'] ?>" data-province="<?= e($institution['province_code'] ?? '') ?>" data-regency="<?= e($institution['regency_code'] ?? '') ?>"><?= e($institution['name']) ?> · <?= e(['school'=>'Sekolah','university'=>'Perguruan tinggi','other'=>'Institusi'][$institution['institution_type']]??'Institusi') ?></option><?php endforeach; ?></select><small class="muted" data-institution-hint>Pilih provinsi dan kabupaten/kota terlebih dahulu. Hanya institusi yang sudah aktif di SIPINTAR yang dapat dipilih.</small></label>
            <label>Cari institusi aktif<input type="search" data-institution-search disabled placeholder="Pilih daerah terlebih dahulu" autocomplete="off"></label>
            <div class="directory-results institution-results" data-institution-results aria-live="polite" hidden></div>
            <p class="muted">Sekolah/kampus belum terdaftar? <a class="text-link" href="?page=register&type=institution">Daftarkan sekolah/kampus terlebih dahulu.</a></p>
            <label>NIS / NIM<input type="text" name="student_number" maxlength="50" placeholder="Nomor induk"></label>
            <label>Kelas / program studi<input type="text" name="cohort" maxlength="120" placeholder="Contoh: XII IPA 1 / Teknik Informatika"></label>
            <label>Nomor telepon<input type="tel" name="phone" maxlength="40"></label>
            <label>Email<input type="email" name="email" placeholder="nama@sekolah.ac.id" required></label>
            <label>Kata sandi<input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter" required></label>
            <button class="btn btn-primary btn-full">Buat akun siswa/mahasiswa <span>→</span></button>
        </form>
    </div>
    <p class="auth-switch">Sudah memiliki akun? <a href="?page=login">Masuk</a></p>
    <div class="auth-foot">Admin pusat membuat akun admin SIPINTAR. Akun institusi dan siswa/mahasiswa didaftarkan melalui formulir ini.</div>
</section>
