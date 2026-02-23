<?php
/**
 * Mobile-responsive table component
 * Automatically converts to card layout on mobile devices
 */

function renderMobileTable($data, $columns, $options = [], $render_functions = []) {
    $defaults = [
        'show_actions' => true,
        'show_pagination' => true,
        'show_search' => true,
        'show_filters' => true,
        'actions' => [],
        'per_page' => 20,
        'current_page' => 1,
        'total_records' => 0,
        'table_id' => 'mobile-table',
        'empty_message' => 'No data available',
        'lazy_load' => false, // New option for lazy loading
        'lazy_load_url' => '', // AJAX URL for lazy loading
        'lazy_load_threshold' => 10 // Load more when 10 items from bottom
    ];

    $options = array_merge($defaults, $options);
    
    ob_start();
    ?>
    
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Table Header with Search and Filters -->
        <?php if ($options['show_search'] || $options['show_filters']): ?>
        <div class="p-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-2 sm:space-y-0">
                <!-- Search -->
                <?php if ($options['show_search']): ?>
                <div class="relative flex-1 max-w-sm">
                    <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-xs"></i>
                    </div>
                    <input type="text" 
                           id="<?= $options['table_id'] ?>-search"
                           class="block w-full pl-8 pr-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-xs"
                           placeholder="Search records...">
                </div>
                <?php endif; ?>
                
                <!-- Filters and Actions -->
                <div class="flex items-center space-x-1">
                    <?php if ($options['show_filters']): ?>
                    <button class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-2 focus:ring-primary-500">
                        <i class="fas fa-filter mr-1 text-xs"></i>
                        Filters
                    </button>
                    <?php endif; ?>
                    
                    <!-- View Toggle -->
                    <div class="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                        <button onclick="toggleTableView('<?= $options['table_id'] ?>', 'table')" 
                                class="view-toggle px-2 py-1 text-xs bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border-r border-gray-300 dark:border-gray-600"
                                data-view="table">
                            <i class="fas fa-table text-xs"></i>
                        </button>
                        <button onclick="toggleTableView('<?= $options['table_id'] ?>', 'cards')" 
                                class="view-toggle px-2 py-1 text-xs bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                                data-view="cards">
                            <i class="fas fa-th-large text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Desktop Table View -->
        <div id="<?= $options['table_id'] ?>-table-view" class="hidden lg:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <?php foreach ($columns as $key => $column): ?>
                        <th class="px-1 py-1 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <?php if (isset($column['sortable']) && $column['sortable']): ?>
                            <button class="group inline-flex items-center space-x-1 hover:text-gray-700 dark:hover:text-gray-300">
                                <?= $column['label'] ?>
                                <i class="fas fa-sort text-gray-400 group-hover:text-gray-500 text-xs"></i>
                            </button>
                            <?php else: ?>
                            <?= $column['label'] ?>
                            <?php endif; ?>
                        </th>
                        <?php endforeach; ?>
                        
                        <?php if ($options['show_actions'] ?? false): ?>
                        <th class="px-1 py-1 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="<?= count($columns) + (($options['show_actions'] ?? false) ? 1 : 0) ?>" 
                            class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center space-y-2">
                                <i class="fas fa-inbox text-3xl text-gray-300 dark:text-gray-600"></i>
                                <p><?= $options['empty_message'] ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($data as $row): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer" 
                        onclick="<?= (strpos($_SERVER['REQUEST_URI'], 'view=instrument') !== false) ? 'viewStudentDetails' : 'viewComprehensiveStudentDetails' ?>(<?= $row['registration_id'] ?? $row['id'] ?? 0 ?>, '<?= (strpos($_SERVER['REQUEST_URI'], 'view=instrument') !== false) ? 'instruments' : 'students' ?>')">
                        <?php foreach ($columns as $key => $column): ?>
                        <td class="px-2 py-1.5 whitespace-nowrap text-xs">
                            <?php
                            if (isset($render_functions[$key]) && is_callable($render_functions[$key])) {
                                echo $render_functions[$key]($row[$key] ?? '', $row);
                            } elseif (isset($column['render']) && is_callable($column['render'])) {
                                echo $column['render']($row[$key] ?? '', $row);
                            } else {
                                echo htmlspecialchars($row[$key] ?? '');
                            }
                            ?>
                        </td>
                        <?php endforeach; ?>
                        
                        <?php if ($options['show_actions'] ?? false): ?>
                        <td class="px-2 py-1.5 whitespace-nowrap text-right text-xs">
                            <div class="flex items-center justify-end space-x-0.5">
                                <?php 
                                // Get student ID from row data
                                $student_id = $row['registration_id'] ?? $row['id'] ?? 0;
                                $table_type = (strpos($_SERVER['REQUEST_URI'], 'view=instrument') !== false) ? 'instruments' : 'students';
                                ?>
                                <button onclick="<?= $table_type === 'instruments' ? 'viewStudentDetails' : 'viewComprehensiveStudentDetails' ?>(<?= $student_id ?>, '<?= $table_type ?>'); event.stopPropagation();" 
                                        class="p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition-colors touch-target"
                                        title="View Details">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                                <button onclick="<?= $table_type === 'instruments' ? 'editInstrumentRegistration' : 'editStudent' ?>(<?= $student_id ?>, '<?= $table_type ?>'); event.stopPropagation();" 
                                        class="p-1 text-green-600 hover:text-green-800 hover:bg-green-50 dark:hover:bg-green-900/20 rounded transition-colors touch-target"
                                        title="<?= $table_type === 'instruments' ? 'Edit Instrument Registration' : 'Edit Student' ?>">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <button onclick="deleteStudent(<?= $student_id ?>, '<?= $table_type ?>'); event.stopPropagation();" 
                                        class="p-1 text-red-600 hover:text-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors touch-target"
                                        title="Delete Student">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Cards View -->
        <div id="<?= $options['table_id'] ?>-cards-view" class="lg:hidden divide-y divide-gray-200 dark:divide-gray-700">
            <?php if (empty($data)): ?>
            <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                <div class="flex flex-col items-center space-y-2">
                    <i class="fas fa-inbox text-3xl text-gray-300 dark:text-gray-600"></i>
                    <p><?= $options['empty_message'] ?></p>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($data as $row): ?>
            <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer" 
                 onclick="<?= (strpos($_SERVER['REQUEST_URI'], 'view=instrument') !== false) ? 'viewStudentDetails' : 'viewComprehensiveStudentDetails' ?>(<?= $row['registration_id'] ?? $row['id'] ?? 0 ?>, '<?= (strpos($_SERVER['REQUEST_URI'], 'view=instrument') !== false) ? 'instruments' : 'students' ?>')">
                <div class="space-y-2">
                    <!-- Main content -->
                    <div class="grid grid-cols-1 gap-1">
                        <?php foreach ($columns as $key => $column): ?>
                        <?php if (isset($column['mobile_priority']) && $column['mobile_priority'] <= 2): ?>
                        <div class="<?= $column['mobile_priority'] === 1 ? 'text-sm font-semibold text-gray-900 dark:text-white' : 'text-xs text-gray-600 dark:text-gray-400' ?>">
                            <?php if ($column['mobile_priority'] !== 1): ?>
                            <span class="text-xs text-gray-500 dark:text-gray-500 uppercase tracking-wide"><?= $column['label'] ?>:</span>
                            <?php endif; ?>
                            <span class="<?= $column['mobile_priority'] === 1 ? 'block' : 'ml-1' ?>">
                                <?php
                                if (isset($render_functions[$key]) && is_callable($render_functions[$key])) {
                                    echo $render_functions[$key]($row[$key] ?? '', $row);
                                } elseif (isset($column['render']) && is_callable($column['render'])) {
                                    echo $column['render']($row[$key] ?? '', $row);
                                } else {
                                    echo htmlspecialchars($row[$key] ?? '');
                                }
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Actions -->
                    <?php if ($options['show_actions'] ?? false): ?>
                    <div class="flex items-center justify-end space-x-0.5 pt-1 border-t border-gray-100 dark:border-gray-600">
                        <?php 
                        // Get student ID from row data
                        $student_id = $row['registration_id'] ?? $row['id'] ?? 0;
                        $table_type = (strpos($_SERVER['REQUEST_URI'], 'view=instrument') !== false) ? 'instruments' : 'students';
                        ?>
                        <button onclick="<?= $table_type === 'instruments' ? 'viewStudentDetails' : 'viewComprehensiveStudentDetails' ?>(<?= $student_id ?>, '<?= $table_type ?>'); event.stopPropagation();" 
                                class="p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors touch-target"
                                title="View Details">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                        <button onclick="<?= $table_type === 'instruments' ? 'editInstrumentRegistration' : 'editStudent' ?>(<?= $student_id ?>, '<?= $table_type ?>'); event.stopPropagation();" 
                                class="p-1 text-green-600 hover:text-green-800 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors touch-target"
                                title="<?= $table_type === 'instruments' ? 'Edit Instrument Registration' : 'Edit Student' ?>">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button onclick="deleteStudent(<?= $student_id ?>, '<?= $table_type ?>'); event.stopPropagation();" 
                                class="p-1 text-red-600 hover:text-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors touch-target"
                                title="Delete Student">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Lazy Loading Elements -->
        <?php if ($options['lazy_load']): ?>
        <div id="<?= $options['table_id'] ?>-loading" class="hidden p-4 text-center text-gray-500 dark:text-gray-400">
            <div class="inline-flex items-center space-x-2">
                <i class="fas fa-spinner fa-spin"></i>
                <span class="text-sm">Loading more...</span>
            </div>
        </div>
        <div id="<?= $options['table_id'] ?>-lazy-trigger" class="h-4"></div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($options['show_pagination'] && $options['total_records'] > $options['per_page']): ?>
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex items-center justify-between">
                <div class="text-xs text-gray-700 dark:text-gray-300">
                    Showing <?= min(($options['current_page'] - 1) * $options['per_page'] + 1, $options['total_records']) ?> 
                    to <?= min($options['current_page'] * $options['per_page'], $options['total_records']) ?> 
                    of <?= $options['total_records'] ?> results
                </div>
                <div class="flex items-center space-x-1">
                    <!-- Pagination buttons would go here -->
                    <button class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-white dark:hover:bg-gray-800 transition-colors touch-target" disabled>
                        <i class="fas fa-chevron-left text-xs"></i>
                    </button>
                    <button class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-white dark:hover:bg-gray-800 transition-colors touch-target">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    function toggleTableView(tableId, view) {
        const tableView = document.getElementById(tableId + '-table-view');
        const cardsView = document.getElementById(tableId + '-cards-view');
        const toggleButtons = document.querySelectorAll('[data-view]');
        
        // Update views
        if (view === 'table') {
            tableView.classList.remove('hidden');
            cardsView.classList.add('hidden');
        } else {
            tableView.classList.add('hidden');
            cardsView.classList.remove('hidden');
        }
        
        // Update button states
        toggleButtons.forEach(btn => {
            if (btn.dataset.view === view) {
                btn.classList.add('bg-primary-50', 'dark:bg-primary-900', 'text-primary-600', 'dark:text-primary-400');
                btn.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-700', 'dark:text-gray-300');
            } else {
                btn.classList.remove('bg-primary-50', 'dark:bg-primary-900', 'text-primary-600', 'dark:text-primary-400');
                btn.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-700', 'dark:text-gray-300');
            }
        });
        
        // Save preference
        localStorage.setItem('table-view-' + tableId, view);
    }
    
    // Initialize view based on screen size or saved preference
    document.addEventListener('DOMContentLoaded', function() {
        const tableId = '<?= $options['table_id'] ?>';
        const savedView = localStorage.getItem('table-view-' + tableId);
        const defaultView = window.innerWidth >= 1024 ? 'table' : 'cards';

        toggleTableView(tableId, savedView || defaultView);

        <?php if ($options['lazy_load'] && !empty($options['lazy_load_url'])): ?>
        // Initialize lazy loading
        initializeLazyLoading(tableId, '<?= $options['lazy_load_url'] ?>', <?= $options['lazy_load_threshold'] ?>);
        <?php endif; ?>
    });

    // Lazy loading functionality
    function initializeLazyLoading(tableId, url, threshold) {
        const container = document.getElementById(tableId + '-container');
        const loadingIndicator = document.getElementById(tableId + '-loading');
        let isLoading = false;
        let currentPage = 1;
        let hasMoreData = true;

        function loadMoreData() {
            if (isLoading || !hasMoreData) return;

            isLoading = true;
            if (loadingIndicator) loadingIndicator.style.display = 'block';

            currentPage++;

            fetch(url + '?page=' + currentPage + '&ajax=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.length > 0) {
                        appendDataToTable(tableId, data.data);
                    } else {
                        hasMoreData = false;
                    }
                })
                .catch(error => {
                    console.error('Lazy loading error:', error);
                    hasMoreData = false;
                })
                .finally(() => {
                    isLoading = false;
                    if (loadingIndicator) loadingIndicator.style.display = 'none';
                });
        }

        function appendDataToTable(tableId, newData) {
            const tbody = document.querySelector('#' + tableId + ' tbody');
            const cardContainer = document.querySelector('#' + tableId + '-cards');

            newData.forEach(item => {
                // Add to table view
                if (tbody) {
                    const row = createTableRow(item);
                    tbody.appendChild(row);
                }

                // Add to card view
                if (cardContainer) {
                    const card = createCard(item);
                    cardContainer.appendChild(card);
                }
            });
        }

        // Intersection Observer for lazy loading
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    loadMoreData();
                }
            });
        }, {
            root: container,
            threshold: 0.1
        });

        // Observe the loading trigger element
        const triggerElement = document.getElementById(tableId + '-lazy-trigger');
        if (triggerElement) {
            observer.observe(triggerElement);
        }

        // Also observe the last few items
        function observeLastItems() {
            const items = container.querySelectorAll('[data-item-id]');
            const lastItems = Array.from(items).slice(-threshold);

            lastItems.forEach(item => {
                observer.observe(item);
            });
        }

        observeLastItems();

        // Re-observe when new items are added
        const observerCallback = function(mutationsList) {
            for (let mutation of mutationsList) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    observeLastItems();
                }
            }
        };

        const observerConfig = { childList: true, subtree: true };
        const mutationObserver = new MutationObserver(observerCallback);
        mutationObserver.observe(container, observerConfig);
    }
    </script>
    
    <?php
    return ob_get_clean();
}
?>