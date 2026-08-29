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

    function toggleSidebarMinimize() {
        var sidebar = document.getElementById('sidebar');
        var wrapper = document.querySelector('.content-wrapper');
        var icon = document.getElementById('minimizeIcon');
        
        if (window.innerWidth <= 768) {
            toggleSidebar();
            return;
        }
        
        sidebar.classList.toggle('collapsed');
        wrapper.classList.toggle('expanded');
        
        if (sidebar.classList.contains('collapsed')) {
            icon.classList.remove('fa-chevron-left');
            icon.classList.add('fa-chevron-right');
            localStorage.setItem('sidebarCollapsed', '1');
        } else {
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-left');
            localStorage.setItem('sidebarCollapsed', '0');
        }
    }

    function toggleMenuGroup(header) {
        if (document.getElementById('sidebar').classList.contains('collapsed')) return;
        
        var items = header.nextElementSibling;
        var icon = header.querySelector('.toggle-icon');
        
        header.classList.toggle('collapsed');
        items.classList.toggle('collapsed');
        
        if (items.classList.contains('collapsed')) {
            items.style.maxHeight = '0';
        } else {
            items.style.maxHeight = items.scrollHeight + 'px';
        }
    }

    // Restore sidebar state
    (function() {
        var collapsed = localStorage.getItem('sidebarCollapsed');
        var sidebar = document.getElementById('sidebar');
        var wrapper = document.querySelector('.content-wrapper');
        var icon = document.getElementById('minimizeIcon');
        
        if (collapsed === '1' && window.innerWidth > 768) {
            sidebar.classList.add('collapsed');
            wrapper.classList.add('expanded');
            icon.classList.remove('fa-chevron-left');
            icon.classList.add('fa-chevron-right');
        }
        
        // Set menu-items max-height
        document.querySelectorAll('.menu-items').forEach(function(el) {
            el.style.maxHeight = el.scrollHeight + 'px';
        });
        
        // Collapse inactive menus
        document.querySelectorAll('.menu-group').forEach(function(group) {
            var activeLink = group.querySelector('a.active');
            if (!activeLink) {
                var header = group.querySelector('.menu-header');
                var items = group.querySelector('.menu-items');
                if (header && items) {
                    header.classList.add('collapsed');
                    items.classList.add('collapsed');
                    items.style.maxHeight = '0';
                }
            }
        });
    })();

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
