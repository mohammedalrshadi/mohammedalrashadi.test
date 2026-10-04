    </main>
  </div>

  <script>
    // Dashboard Mobile Sidebar Controller
    document.addEventListener('DOMContentLoaded', () => {
      const toggleBtn = document.getElementById('dashboard-mobile-toggle');
      const closeBtn = document.getElementById('dashboard-close-sidebar');
      const sidebar = document.getElementById('dashboard-sidebar');
      const backdrop = document.getElementById('dashboard-backdrop');

      function openSidebar() {
        if (!sidebar || !backdrop) return;
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
      }

      function closeSidebar() {
        if (!sidebar || !backdrop) return;
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
      }

      if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
      if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
      if (backdrop) backdrop.addEventListener('click', closeSidebar);
    });

    // Global User Dashboard API Helper
    window.UserAPI = {
      getCSRF() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
      },
      async post(url, body = {}) {
        const csrf = this.getCSRF();
        const res = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
          },
          credentials: 'same-origin',
          body: JSON.stringify(body)
        });
        return await res.json();
      },
      async get(url) {
        const res = await fetch(url, {
          method: 'GET',
          credentials: 'same-origin'
        });
        return await res.json();
      }
    };
  </script>
</body>
</html>

