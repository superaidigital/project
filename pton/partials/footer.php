  </main>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Function to initialize sidebar
        function initializeSidebar() {
            console.log('Initializing sidebar...');
            
            const sidebar = document.getElementById('sidebar');
            const toggleButton = document.getElementById('sidebarToggle');
            const toggleButtonCollapsed = document.getElementById('sidebarToggleCollapsed');
            
            console.log('Sidebar element:', sidebar);
            console.log('Toggle button (expanded):', toggleButton);
            console.log('Toggle button (collapsed):', toggleButtonCollapsed);
            
            if (!sidebar) {
                console.error('Sidebar element with ID "sidebar" not found');
                return false;
            }
            
            if (!toggleButton || !toggleButtonCollapsed) {
                console.error('Toggle button elements not found');
                return false;
            }

            // Function to toggle sidebar state
            function toggleSidebar() {
                const isCollapsed = sidebar.classList.contains('collapsed');
                console.log('Current state - collapsed:', isCollapsed);
                
                if (isCollapsed) {
                    // Expand sidebar
                    sidebar.classList.remove('collapsed');
                    document.body.classList.remove('sidebar-collapsed');
                    console.log('Sidebar expanded');
                } else {
                    // Collapse sidebar
                    sidebar.classList.add('collapsed');
                    document.body.classList.add('sidebar-collapsed');
                    console.log('Sidebar collapsed');
                }
                
                // Save state
                const newState = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', newState);
            }

            // Remove any existing event listeners and add new ones
            const newToggleButton = toggleButton.cloneNode(true);
            const newToggleButtonCollapsed = toggleButtonCollapsed.cloneNode(true);
            
            toggleButton.parentNode.replaceChild(newToggleButton, toggleButton);
            toggleButtonCollapsed.parentNode.replaceChild(newToggleButtonCollapsed, toggleButtonCollapsed);
            
            console.log('Adding click event listeners...');
            
            // Event listener for expanded state button
            newToggleButton.addEventListener('click', function(e) {
                console.log('Toggle button (expanded) clicked!');
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
            
            // Event listener for collapsed state button
            newToggleButtonCollapsed.addEventListener('click', function(e) {
                console.log('Toggle button (collapsed) clicked!');
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
            
            // Restore saved state
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            
            console.log('Sidebar initialization complete');
            return true;
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            
            // Initialize Lucide icons first
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
                console.log('Lucide icons initialized');
            }
            
            // Initialize sidebar after a short delay
            setTimeout(function() {
                const success = initializeSidebar();
                if (!success) {
                    console.log('Retrying sidebar initialization...');
                    setTimeout(initializeSidebar, 500);
                }
            }, 100);
        });
    </script>
</body>
</html>
