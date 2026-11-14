document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('open'));

    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    document.querySelectorAll('.alert-close').forEach((button) => {
        button.addEventListener('click', () => button.closest('.alert')?.remove());
    });

    const registrationType = document.getElementById('registrationType');
    const institutionRegistration = document.getElementById('institutionRegistration');
    const studentRegistration = document.getElementById('studentRegistration');
    const institutionForm = institutionRegistration?.querySelector('form');
    const serviceOptions = [...(institutionForm?.querySelectorAll('[name="services[]"]') || [])];
    const updateRegistrationForm = () => {
        if (!registrationType) return;
        if (institutionRegistration) institutionRegistration.hidden = registrationType.value !== 'institution';
        if (studentRegistration) studentRegistration.hidden = registrationType.value !== 'student';
    };
    registrationType?.addEventListener('change', updateRegistrationForm);
    updateRegistrationForm();
    institutionForm?.addEventListener('submit', (event) => {
        if (!serviceOptions.some((option) => option.checked)) {
            event.preventDefault();
            serviceOptions[0]?.setCustomValidity('Pilih minimal satu layanan.');
            serviceOptions[0]?.reportValidity();
        }
    });
    serviceOptions.forEach((option) => option.addEventListener('change', () => serviceOptions[0]?.setCustomValidity('')));

    document.querySelectorAll('form').forEach((form) => {
        const province = form.querySelector('[data-region-province]');
        const regency = form.querySelector('[data-region-regency]');
        if (!province || !regency) return;

        const updateRegencies = (preferredCode = '') => {
            let hasRegencies = false;
            [...regency.options].forEach((option) => {
                if (!option.value) return;
                const available = option.dataset.parent === province.value && province.value !== '';
                option.hidden = !available;
                option.disabled = !available;
                hasRegencies ||= available;
            });
            regency.disabled = !hasRegencies;
            if (preferredCode && [...regency.options].some((option) => option.value === preferredCode && !option.disabled)) {
                regency.value = preferredCode;
            } else if (regency.selectedOptions[0]?.disabled) {
                regency.value = '';
            }
        };
        updateRegencies();
        province.addEventListener('change', () => {
            updateRegencies();
            regency.dispatchEvent(new Event('change'));
        });

        const institutionSelect = form.querySelector('[data-region-institution]');
        institutionSelect?.addEventListener('change', () => {
            const selected = institutionSelect.selectedOptions[0];
            province.value = selected?.dataset.province || '';
            updateRegencies(selected?.dataset.regency || '');
            regency.dispatchEvent(new Event('change'));
        });

        const institutionOptions = form.querySelector('[data-institution-select]');
        const institutionSearch = form.querySelector('[data-institution-search]');
        const institutionResults = form.querySelector('[data-institution-results]');
        const institutionHint = form.querySelector('[data-institution-hint]');
        const updateInstitutions = () => {
            if (!institutionOptions) return;
            const availableOptions = [];
            [...institutionOptions.options].forEach((option) => {
                if (!option.value) return;
                const available = option.dataset.province === province.value && option.dataset.regency === regency.value && regency.value !== '';
                option.hidden = !available;
                option.disabled = !available;
                if (available) availableOptions.push(option);
            });
            institutionOptions.value = '';
            if (institutionSearch) institutionSearch.value = '';
            institutionOptions.disabled = !regency.value || availableOptions.length === 0;
            institutionOptions.options[0].textContent = !regency.value
                ? 'Pilih daerah terlebih dahulu'
                : availableOptions.length
                    ? 'Pilih sekolah/kampus'
                    : 'Belum ada institusi aktif di daerah ini';
            if (institutionSearch) {
                institutionSearch.disabled = !regency.value;
                institutionSearch.placeholder = availableOptions.length ? 'Ketik nama sekolah atau kampus' : regency.value ? 'Tidak ada yang aktif; cari tetap dapat digunakan' : 'Pilih daerah terlebih dahulu';
            }
            if (institutionHint && !availableOptions.length) {
                institutionHint.textContent = !regency.value
                    ? 'Pilih provinsi dan kabupaten/kota terlebih dahulu. Hanya institusi yang sudah aktif di SIPINTAR yang dapat dipilih.'
                    : 'Belum ada institusi aktif di daerah ini. Sekolah/kampus perlu mendaftar dan disetujui admin SIPINTAR terlebih dahulu.';
            } else if (institutionHint) {
                institutionHint.textContent = 'Ketik nama untuk mencari atau pilih langsung dari daftar institusi aktif di daerah ini.';
            }
            if (institutionResults) {
                institutionResults.replaceChildren();
                institutionResults.hidden = true;
            }
        };
        regency.addEventListener('change', updateInstitutions);
        updateInstitutions();

        institutionSearch?.addEventListener('input', () => {
            const normalizeQuery = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .toLocaleLowerCase('id').replace(/[^a-z0-9]/g, '');
            const query = normalizeQuery(institutionSearch.value.trim());
            const matching = [...institutionOptions.options].filter((option) =>
                option.value &&
                option.dataset.province === province.value &&
                option.dataset.regency === regency.value &&
                normalizeQuery(option.textContent).includes(query)
            );
            institutionResults.replaceChildren();
            if (!matching.length) {
                const message = document.createElement('p');
                message.className = 'directory-message';
                message.textContent = 'Tidak ada institusi aktif yang cocok. Coba kata kunci lain.';
                institutionResults.append(message);
            } else {
                matching.slice(0, 20).forEach((option) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'directory-result';
                    button.textContent = option.textContent.trim();
                    button.addEventListener('click', () => {
                        institutionOptions.value = option.value;
                        institutionSearch.value = option.textContent.trim();
                        institutionResults.hidden = true;
                    });
                    institutionResults.append(button);
                });
            }
            institutionResults.hidden = false;
        });
    });

    document.querySelectorAll('[data-school-search]').forEach((input) => {
        const form = input.closest('form');
        const results = form.querySelector('[data-school-results]');
        const npsn = form.querySelector('[data-school-npsn]');
        const hint = form.querySelector('[data-school-hint]');
        const type = form.querySelector('[data-institution-type]');
        const province = form.querySelector('[data-region-province]');
        const regency = form.querySelector('[data-region-regency]');
        let timer;
        let controller;

        const clearResults = () => {
            results.replaceChildren();
            results.hidden = true;
        };
        const showMessage = (message, isError = false) => {
            clearResults();
            const notice = document.createElement('p');
            notice.className = isError ? 'directory-error' : 'directory-message';
            notice.textContent = message;
            results.append(notice);
            results.hidden = false;
        };
        const searchSchools = async () => {
            controller?.abort();
            controller = null;
            const query = input.value.trim();
            if (type.value !== 'school') {
                npsn.value = '';
                hint.textContent = 'Direktori nasional memuat sekolah. Nama perguruan tinggi dapat dimasukkan manual dan akan diverifikasi admin.';
                clearResults();
                return;
            }
            hint.textContent = 'Daftar sekolah mengikuti daerah yang dipilih. Jika tidak ditemukan, nama dapat dimasukkan manual dan akan diverifikasi admin.';
            if (!province.value || !regency.value) {
                clearResults();
                return;
            }
            controller = new AbortController();
            showMessage(query ? 'Mencari sekolah di daerah yang dipilih…' : 'Memuat daftar sekolah di daerah yang dipilih…');
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('directory', 'schools');
                url.searchParams.set('province_code', province.value);
                url.searchParams.set('regency_code', regency.value);
                if (query) url.searchParams.set('q', query);
                else url.searchParams.delete('q');
                const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`Direktori sekolah merespons ${response.status}.`);
                const data = await response.json();
                if (!Array.isArray(data.items)) throw new Error(data.error || 'Format data direktori tidak dikenali.');
                const matches = data.items.filter((school) =>
                    school && typeof school.name === 'string' && (typeof school.npsn === 'string' || school.npsn === null) &&
                    typeof school.school_type === 'string'
                );
                clearResults();
                if (!matches.length) {
                    showMessage(query
                        ? 'Tidak ditemukan sekolah yang cocok di daerah ini. Coba kata kunci lain atau isi nama sekolah manual.'
                        : 'Belum ada sekolah di direktori untuk daerah ini. Nama sekolah dapat diisi manual.');
                    return;
                }
                if (data.total > matches.length) {
                    const message = document.createElement('p');
                    message.className = 'directory-message';
                    message.textContent = `Menampilkan 20 dari ${data.total} sekolah. Ketik nama sekolah agar pencarian lebih spesifik.`;
                    results.append(message);
                }
                matches.forEach((school) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'directory-result';
                    button.textContent = `${school.name} · ${school.school_type}${school.npsn ? ` · NPSN ${school.npsn}` : ''}`;
                    button.addEventListener('click', () => {
                        input.value = school.name;
                        npsn.value = school.npsn || '';
                        clearResults();
                    });
                    results.append(button);
                });
                results.hidden = false;
            } catch (error) {
                if (error instanceof Error && error.name === 'AbortError') return;
                const message = error instanceof Error ? error.message : 'Terjadi kesalahan yang tidak diketahui.';
                showMessage(`Pencarian direktori gagal: ${message} Isi nama sekolah manual atau coba lagi.`, true);
            }
        };
        const queueSearch = () => {
            npsn.value = '';
            controller?.abort();
            clearResults();
            clearTimeout(timer);
            timer = setTimeout(searchSchools, 600);
        };
        input.addEventListener('input', queueSearch);
        province.addEventListener('change', () => {
            input.value = '';
            npsn.value = '';
            queueSearch();
        });
        regency.addEventListener('change', () => {
            input.value = '';
            npsn.value = '';
            queueSearch();
        });
        type.addEventListener('change', searchSchools);
        searchSchools();
    });

    document.querySelectorAll('.borrow-form').forEach((form) => {
        const start = form.querySelector('[name="start_date"]');
        const end = form.querySelector('[name="end_date"]');
        const updateEndDate = () => {
            if (end) end.min = start.value;
            const maxDays = Number(start?.dataset.maxDays || 0);
            if (end && start?.value && maxDays) {
                const limit = new Date(`${start.value}T00:00:00`);
                limit.setDate(limit.getDate() + maxDays);
                end.max = `${limit.getFullYear()}-${String(limit.getMonth() + 1).padStart(2, '0')}-${String(limit.getDate()).padStart(2, '0')}`;
            }
            if (end && end.value && end.value < start.value) end.value = start.value;
            if (end && end.value && end.max && end.value > end.max) end.value = end.max;
        };
        start?.addEventListener('change', updateEndDate);
    });
});
