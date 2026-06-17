// ============================================================
// Main JavaScript - Sistem Peminjaman Alat Gunung
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // ---- Navbar Scroll Effect ----
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 30);
        });
    }

    // ---- Hamburger Menu ----
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', () => {
            navLinks.classList.toggle('open');
            hamburger.textContent = navLinks.classList.contains('open') ? '✕' : '☰';
        });
        document.addEventListener('click', (e) => {
            if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
                navLinks.classList.remove('open');
                hamburger.textContent = '☰';
            }
        });
    }

    // ---- Admin Sidebar Toggle (Mobile) ----
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // ---- Equipment Filter ----
    const filterBtns = document.querySelectorAll('.filter-btn');
    const equipmentCards = document.querySelectorAll('.equipment-card');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const category = btn.dataset.filter;
            equipmentCards.forEach(card => {
                if (category === 'semua' || card.dataset.category === category) {
                    card.style.display = '';
                    card.style.animation = 'fadeInUp 0.4s ease';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // ---- Rental Form: Price Calculator ----
    const alatSelect = document.getElementById('id_alat');
    const jumlahInput = document.getElementById('jumlah');
    const tglPinjam = document.getElementById('tanggal_pinjam');
    const tglKembali = document.getElementById('tanggal_kembali');
    const pricePreview = document.getElementById('price-preview');

    function updatePrice() {
        if (!alatSelect || !tglPinjam || !tglKembali || !pricePreview) return;
        const selectedOption = alatSelect.options[alatSelect.selectedIndex];
        const harga = parseFloat(selectedOption?.dataset.harga || 0);
        const jumlah = parseInt(jumlahInput?.value || 1);
        const tP = tglPinjam.value;
        const tK = tglKembali.value;

        if (harga > 0 && tP && tK && tP < tK) {
            const diffTime = new Date(tK) - new Date(tP);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            const total = harga * jumlah * diffDays;

            document.getElementById('preview-harga').textContent = formatRupiah(harga);
            document.getElementById('preview-jumlah').textContent = jumlah + ' unit';
            document.getElementById('preview-hari').textContent = diffDays + ' hari';
            document.getElementById('preview-total').textContent = formatRupiah(total);
            pricePreview.classList.add('show');
        } else {
            pricePreview.classList.remove('show');
        }
    }

    if (alatSelect) alatSelect.addEventListener('change', updatePrice);
    if (jumlahInput) jumlahInput.addEventListener('input', updatePrice);
    if (tglPinjam) {
        tglPinjam.addEventListener('change', () => {
            if (tglKembali) tglKembali.min = tglPinjam.value;
            updatePrice();
        });
        // Set min date
        const today = new Date().toISOString().split('T')[0];
        tglPinjam.min = today;
    }
    if (tglKembali) tglKembali.addEventListener('change', updatePrice);

    // ---- Set tanggal default ----
    if (tglPinjam && !tglPinjam.value) {
        const today = new Date().toISOString().split('T')[0];
        tglPinjam.value = today;
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        if (tglKembali) {
            tglKembali.min = today;
            tglKembali.value = tomorrow.toISOString().split('T')[0];
        }
        updatePrice();
    }

    // ---- Modal ----
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modalId = btn.dataset.modal;
            const modal = document.querySelector('#' + modalId);
            if (modal) modal.classList.add('show');
        });
    });

    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target === el) {
                document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('show'));
            }
        });
    });

    // ---- Rent Modal - Fill alat info ----
    document.querySelectorAll('.btn-rent').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const nama = btn.dataset.nama;
            const harga = btn.dataset.harga;
            const stok = parseInt(btn.dataset.stok || 0);

            const modal = document.getElementById('rent-modal');
            if (!modal) return;

            modal.querySelector('#modal-alat-name').textContent = nama;
            modal.querySelector('#modal-alat-price').textContent = formatRupiah(harga) + '/hari';

            const alatField = modal.querySelector('#id_alat');
            if (alatField) {
                for (let opt of alatField.options) {
                    if (opt.value == id) { opt.selected = true; break; }
                }
            }
            if (jumlahInput) {
                jumlahInput.max = stok;
                jumlahInput.value = 1;
            }
            modal.classList.add('show');
            updatePrice();
        });
    });

    // ---- Alert Auto-close ----
    const alerts = document.querySelectorAll('.alert[data-auto-close]');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            alert.style.transition = 'all 0.4s ease';
            setTimeout(() => alert.remove(), 400);
        }, 4000);
    });

    // ---- Search Table ----
    const searchInput = document.querySelector('#table-search');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    // ---- Scroll animation (Intersection Observer) ----
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.feature-card, .step-item, .equipment-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });

    // ---- Confirm Delete ----
    document.querySelectorAll('.btn-confirm-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm('Yakin ingin menghapus data ini? Tindakan ini tidak bisa dibatalkan.')) {
                e.preventDefault();
            }
        });
    });

    // ---- Counter Animation ----
    const counters = document.querySelectorAll('.stat-number[data-count]');
    counters.forEach(counter => {
        const target = parseInt(counter.dataset.count);
        const duration = 1500;
        const step = target / (duration / 16);
        let current = 0;
        const timer = setInterval(() => {
            current = Math.min(current + step, target);
            counter.textContent = Math.floor(current) + (counter.dataset.suffix || '');
            if (current >= target) clearInterval(timer);
        }, 16);
    });

    // ---- Helper ----
    function formatRupiah(amount) {
        return 'Rp ' + parseInt(amount).toLocaleString('id-ID');
    }
});
