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
        'show_view_toggle' => true,
        'render_mode' => 'both', // both | table | cards
        'search_mode' => 'client', // client | server
        'search_param' => 'search',
        'search_value' => '',
        'search_placeholder' => 'Search records...',
        'header_actions_html' => '',
        'actions' => [],
        'per_page' => 20,
        'current_page' => 1,
        'total_records' => 0,
        'table_id' => 'mobile-table',
        'empty_message' => 'No data available'
    ];
    
    $options = array_merge($defaults, $options);
    
    ob_start();
    ?>
    
    <div id="<?= $options['table_id'] ?>-container" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
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
                           data-search-mode="<?= htmlspecialchars((string)$options['search_mode']) ?>"
                           class="block w-full pl-8 pr-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-xs"
                           value="<?= htmlspecialchars((string)$options['search_value']) ?>"
                           placeholder="<?= htmlspecialchars($options['search_placeholder']) ?>">
                </div>
                <?php endif; ?>
                
                <!-- Filters and Actions -->
                <div class="flex items-center space-x-1">
                    <?php if (!empty($options['header_actions_html'])): ?>
                    <?= $options['header_actions_html'] ?>
                    <?php endif; ?>
                    <?php if ($options['show_filters']): ?>
                    <button class="inline-flex items-center px-2 py-1 border border-gray-300 dark:border-gray-600 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-2 focus:ring-primary-500">
                        <i class="fas fa-filter mr-1 text-xs"></i>
                        Filters
                    </button>
                    <?php endif; ?>
                    
                    <!-- View Toggle -->
                    <?php if ($options['show_view_toggle']): ?>
                    <div class="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                        <button onclick="toggleTableView('<?= $options['table_id'] ?>', 'table')" 
                                class="view-toggle px-2 py-1 text-xs bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border-r border-gray-300 dark:border-gray-600"
                                data-table-id="<?= $options['table_id'] ?>"
                                data-view="table">
                            <i class="fas fa-table text-xs"></i>
                        </button>
                        <button onclick="toggleTableView('<?= $options['table_id'] ?>', 'cards')" 
                                class="view-toggle px-2 py-1 text-xs bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                                data-table-id="<?= $options['table_id'] ?>"
                                data-view="cards">
                            <i class="fas fa-th-large text-xs"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php $renderMode = strtolower((string)($options['render_mode'] ?? 'both')); ?>
        <!-- Desktop Table View -->
        <?php if ($renderMode === 'both' || $renderMode === 'table'): ?>
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
                    <tr class="student-record hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer"
                        data-student-id="<?= $row['registration_id'] ?? $row['id'] ?? 0 ?>"
                        data-table-type="<?= (strpos($_SERVER['REQUEST_URI'], 'view=instrument') !== false) ? 'instruments' : 'students' ?>"
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
        <?php endif; ?>
        
        <!-- Mobile Cards View -->
        <?php if ($renderMode === 'both' || $renderMode === 'cards'): ?>
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
            <div class="student-record p-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer"
                 data-student-id="<?= $row['registration_id'] ?? $row['id'] ?? 0 ?>"
                 data-table-type="<?= (strpos($_SERVER['REQUEST_URI'], 'view=instrument') !== false) ? 'instruments' : 'students' ?>"
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
        const toggleButtons = document.querySelectorAll('[data-table-id="' + tableId + '"][data-view]');
        
        // Update views
        if (view === 'table') {
            if (tableView) tableView.classList.remove('hidden');
            if (cardsView) cardsView.classList.add('hidden');
        } else {
            if (tableView) tableView.classList.add('hidden');
            if (cardsView) cardsView.classList.remove('hidden');
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

    function initMobileTableSearch(tableId) {
        const searchInput = document.getElementById(tableId + '-search');
        if (!searchInput) return;
        const searchMode = '<?= htmlspecialchars((string)$options['search_mode']) ?>';
        const searchParam = '<?= htmlspecialchars((string)$options['search_param']) ?>' || 'search';

        if (searchMode === 'server') {
            let serverDebounce = null;
            searchInput.addEventListener('input', function() {
                if (serverDebounce) clearTimeout(serverDebounce);
                serverDebounce = setTimeout(function() {
                    const query = (searchInput.value || '').trim();
                    const url = new URL(window.location.href);
                    if (query) url.searchParams.set(searchParam, query);
                    else url.searchParams.delete(searchParam);
                    url.searchParams.set('page', '1');
                    window.location.assign(url.toString());
                }, 250);
            });
            return;
        }

        const tableBody = document.querySelector('#' + tableId + '-table-view tbody');
        const cardsContainer = document.getElementById(tableId + '-cards-view');
        if (!tableBody && !cardsContainer) return;

        const tableRows = tableBody ? Array.from(tableBody.querySelectorAll('tr.student-record')) : [];
        const cardItems = cardsContainer ? Array.from(cardsContainer.querySelectorAll('.student-record')) : [];
        const emptyRowId = tableId + '-search-empty-row';
        const emptyCardsId = tableId + '-search-empty-cards';

        function getSearchText(el) {
            if (!el) return '';
            if (!el.dataset.searchText) {
                el.dataset.searchText = (el.textContent || '')
                    .toLowerCase()
                    .replace(/\s+/g, ' ')
                    .trim();
            }
            return el.dataset.searchText;
        }

        function ensureTableEmptyState() {
            if (!tableBody) return null;
            let row = document.getElementById(emptyRowId);
            if (!row) {
                row = document.createElement('tr');
                row.id = emptyRowId;
                row.className = 'hidden';
                const colCount = tableBody.parentElement ? tableBody.parentElement.querySelectorAll('thead th').length : 1;
                row.innerHTML = '<td colspan="' + colCount + '" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400 text-xs">No matching records</td>';
                tableBody.appendChild(row);
            }
            return row;
        }

        function ensureCardsEmptyState() {
            if (!cardsContainer) return null;
            let box = document.getElementById(emptyCardsId);
            if (!box) {
                box = document.createElement('div');
                box.id = emptyCardsId;
                box.className = 'hidden p-6 text-center text-gray-500 dark:text-gray-400 text-xs';
                box.innerHTML = '<i class="fas fa-search text-xl opacity-50 mb-2"></i><p>No matching records</p>';
                cardsContainer.appendChild(box);
            }
            return box;
        }

        const tableEmptyState = ensureTableEmptyState();
        const cardsEmptyState = ensureCardsEmptyState();
        let debounceTimer = null;

        function applySearch() {
            const term = (searchInput.value || '').toLowerCase().trim();

            let visibleTable = 0;
            for (let i = 0; i < tableRows.length; i++) {
                const row = tableRows[i];
                const visible = !term || getSearchText(row).indexOf(term) !== -1;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleTable++;
            }

            let visibleCards = 0;
            for (let i = 0; i < cardItems.length; i++) {
                const card = cardItems[i];
                const visible = !term || getSearchText(card).indexOf(term) !== -1;
                card.style.display = visible ? '' : 'none';
                if (visible) visibleCards++;
            }

            if (tableEmptyState) {
                if (term && visibleTable === 0) tableEmptyState.classList.remove('hidden');
                else tableEmptyState.classList.add('hidden');
            }

            if (cardsEmptyState) {
                if (term && visibleCards === 0) cardsEmptyState.classList.remove('hidden');
                else cardsEmptyState.classList.add('hidden');
            }

            document.dispatchEvent(new CustomEvent("mobileTableFiltered", { detail: { tableId: tableId } }));
        }

        searchInput.addEventListener('input', function() {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applySearch, 80);
        });
    }
    
    // Initialize view based on screen size or saved preference
    document.addEventListener('DOMContentLoaded', function() {
        const tableId = '<?= $options['table_id'] ?>';
        const renderMode = '<?= htmlspecialchars($options['render_mode']) ?>';
        const savedView = localStorage.getItem('table-view-' + tableId);
        let defaultView = window.innerWidth >= 1024 ? 'table' : 'cards';
        if (renderMode === 'table') defaultView = 'table';
        if (renderMode === 'cards') defaultView = 'cards';
        
        toggleTableView(tableId, savedView || defaultView);
        initMobileTableSearch(tableId);
    });
    </script>
    
    <?php
    return ob_get_clean();
}
?>
