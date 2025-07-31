</main>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            lucide.createIcons();

            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleButton = document.getElementById('sidebarToggle');
            const toggleIcon = toggleButton.querySelector('i');

            const setSidebarState = (collapsed) => {
                if (collapsed) {
                    sidebar.classList.add('collapsed');
                    document.body.classList.add('sidebar-collapsed');
                    toggleIcon.setAttribute('data-lucide', 'chevron-right');
                } else {
                    sidebar.classList.remove('collapsed');
                    document.body.classList.remove('sidebar-collapsed');
                    toggleIcon.setAttribute('data-lucide', 'chevron-left');
                }
                lucide.createIcons();
            };

            // Check for saved state in localStorage
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            setSidebarState(isCollapsed);

            toggleButton.addEventListener('click', () => {
                const collapsed = sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', collapsed);
                setSidebarState(collapsed);
            });
        });
    </script>
    <!-- เพิ่ม Lucide initialization -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>