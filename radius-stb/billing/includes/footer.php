        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }

    function confirmDelete(msg) {
        return confirm(msg || 'Yakin ingin menghapus?');
    }

    document.querySelectorAll('.sidebar a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        });
    });

    function showToast(msg, type) {
        var toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;padding:12px 24px;border-radius:8px;color:#fff;font-size:14px;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,0.3);animation:fadeInUp .3s ease;display:flex;align-items:center;gap:8px;max-width:400px;';
        var colors = {success:'#27ae60', danger:'#e74c3c', warning:'#f39c12', info:'#3498db'};
        toast.style.background = colors[type] || colors.success;
        var icon = {success:'fa-check-circle', danger:'fa-times-circle', warning:'fa-exclamation-circle', info:'fa-info-circle'};
        toast.innerHTML = '<i class="fas ' + (icon[type]||icon.success) + '"></i> ' + msg;
        document.body.appendChild(toast);
        setTimeout(function(){ toast.style.opacity='0'; toast.style.transition='opacity .3s'; setTimeout(function(){ toast.remove(); },300); }, 3000);
    }

    <?php if ($flash = getFlash()): ?>
    showToast("<?= addslashes($flash['message']) ?>", "<?= $flash['type'] ?>");
    <?php endif; ?>
    </script>
    <style>
    @keyframes fadeInUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
    </style>
</body>
</html>
