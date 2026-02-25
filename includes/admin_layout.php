<?php
// Admin Layout Component
if (!isset($_SESSION)) {
    session_start();
}

// Get current page name for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Define navigation items
$nav_items = [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'fa-chart-line',
        'url' => 'dashboard.php',
        'badge' => ''
    ],
    'students' => [
        'title' => 'Students',
        'icon' => 'fa-users',
        'url' => 'students.php',
        'badge' => ''
    ],
    'allocation' => [
        'title' => 'Allocation',
        'icon' => 'fa-sliders',
        'url' => 'allocation.php',
        'badge' => ''
    ],
    'data_quality' => [
        'title' => 'Data Quality',
        'icon' => 'fa-clipboard-check',
        'url' => 'data_quality.php',
        'badge' => ''
    ],
    'results' => [
        'title' => 'Results',
        'icon' => 'fa-check-double',
        'url' => 'results.php',
        'badge' => ''
    ],
    'result_summary_mvp' => [
        'title' => 'Result Summary MVP',
        'icon' => 'fa-square-poll-horizontal',
        'url' => 'result_summary_mvp.php',
        'badge' => ''
    ],
    'classes' => [
        'title' => 'Classes',
        'icon' => 'fa-chalkboard',
        'url' => 'classes.php',
        'badge' => '',
        'submenu' => [
            'class_list' => [
                'title' => 'Class List',
                'url' => 'classes.php'
            ],
            'section_management' => [
                'title' => 'Section Management',
                'url' => 'classes.php#sections'
            ],
            'auto_allocation' => [
                'title' => 'Auto Allocation',
                'url' => 'classes.php#auto-allocate'
            ],
            'drag_drop_courses' => [
                'title' => 'Drag & Drop Courses',
                'url' => 'drag_drop_courses.php'
            ]
        ]
    ],
    'courses' => [
        'title' => 'Courses',
        'icon' => 'fa-book',
        'url' => 'courses.php',
        'badge' => '',
        'submenu' => [
            'course_list' => [
                'title' => 'Course List',
                'url' => 'courses.php'
            ],
            'advanced_dashboard' => [
                'title' => 'Advanced Dashboard',
                'url' => 'advanced_course_dashboard.php'
            ]
        ]
    ],
    'teachers' => [
        'title' => 'Teachers',
        'icon' => 'fa-chalkboard-teacher',
        'url' => 'teachers.php',
        'badge' => ''
    ],
    'reports' => [
        'title' => 'Analytics',
        'icon' => 'fa-chart-bar',
        'url' => 'report.php',
        'badge' => ''
    ],
    'database' => [
        'title' => 'Database',
        'icon' => 'fa-database',
        'url' => 'database.php',
        'badge' => ''
    ],
    'optimization' => [
        'title' => 'Optimization',
        'icon' => 'fa-rocket',
        'url' => 'optimization_tools.php',
        'badge' => ''
    ]
    ,
    'registration' => [
        'title' => 'Registration Management',
        'icon' => 'fa-toggle-on',
        'url' => 'registration_management.php',
        'badge' => ''
    ]
    ,
    'youth_categorization' => [
        'title' => 'Youth Categorization (17+)',
        'icon' => 'fa-user-graduate',
        'url' => 'youth_categorization.php',
        'badge' => ''
    ]
];

function renderAdminLayout($title = 'Admin Dashboard', $content = '', $page_script = '') {
    global $nav_items, $current_page;
    
    // Determine active page
    $active_page = '';
    if ($current_page === 'students' || strpos($current_page, 'student') !== false) {
        $active_page = 'students';
    } elseif ($current_page === 'classes' || $current_page === 'class') {
        $active_page = 'classes';
    } elseif ($current_page === 'teachers' || $current_page === 'teacher') {
        $active_page = 'teachers';
    } elseif ($current_page === 'dashboard') {
        $active_page = 'dashboard';
    } elseif ($current_page === 'report') {
        $active_page = 'reports';
    } elseif ($current_page === 'database') {
        $active_page = 'database';
    } elseif ($current_page === 'optimization_tools') {
        $active_page = 'optimization';
    } elseif ($current_page === 'allocation') {
        $active_page = 'allocation';
    } elseif ($current_page === 'data_quality') {
        $active_page = 'data_quality';
    } elseif ($current_page === 'results') {
        $active_page = 'results';
    } elseif ($current_page === 'result_summary_mvp') {
        $active_page = 'result_summary_mvp';
    } elseif ($current_page === 'youth_categorization') {
        $active_page = 'youth_categorization';
    }
    
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Music School Admin</title>
    
    <!-- Tailwind CSS - Preconnect for faster loading -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif']
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        }
                    },
                    animation: {
                        'slide-in': 'slideIn 0.2s ease-out',
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'scale-in': 'scaleIn 0.2s ease-out'
                    },
                    keyframes: {
                        slideIn: {
                            '0%': { transform: 'translateX(-100%)' },
                            '100%': { transform: 'translateX(0)' }
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        scaleIn: {
                            '0%': { transform: 'scale(0.95)', opacity: '0' },
                            '100%': { transform: 'scale(1)', opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Alpine.js - Deferred for performance -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    
    <!-- Font Awesome - CDN with integrity -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Google Fonts - Optimized loading -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DNS Prefetch for common resources -->
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    
    <style>
        [x-cloak] { display: none !important; }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(156, 163, 175, 0.5);
            border-radius: 2px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(156, 163, 175, 0.8);
        }
        
        /* Mobile-first responsive table */
        .mobile-table-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.5rem;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        @media (min-width: 768px) {
            .mobile-table-row {
                display: table-row;
                padding: 0;
                border-bottom: none;
            }
        }
        
        /* Advanced sidebar responsive handling */
        .sidebar-collapsed {
            width: 4rem !important;
        }
        
        .sidebar-collapsed .sidebar-text {
            display: none;
        }
        
        .sidebar-collapsed .nav-icon {
            margin: 0 auto;
        }
        
        /* Enhanced mobile touch targets */
        .touch-target {
            min-height: 44px;
            min-width: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Glassmorphism effect */
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .dark .glass {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Enhanced scrollbars */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(156, 163, 175, 0.8);
        }
        
        /* Smooth transitions for all interactive elements */
        * {
            transition-property: background-color, border-color, color, fill, stroke, transform, box-shadow;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }
        
        /* Enhanced hover effects */
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* Mobile-first responsive design utilities */
        @media (max-width: 640px) {
            .mobile-hide { display: none !important; }
            .mobile-full { width: 100% !important; }
            .mobile-text-center { text-align: center !important; }
        }
        
        /* Advanced animations */
        @keyframes slideInFromLeft {
            0% { transform: translateX(-100%); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideInFromRight {
            0% { transform: translateX(100%); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .animate-slide-in-left { animation: slideInFromLeft 0.3s ease-out; }
        .animate-slide-in-right { animation: slideInFromRight 0.3s ease-out; }
        .animate-bounce-in { animation: bounceIn 0.5s ease-out; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-200" 
      x-data="adminApp()" 
      x-init="initApp()"
      x-cloak>
      
    <!-- Mobile Backdrop -->
    <div x-show="sidebarOpen" 
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-600 bg-opacity-75 z-30 lg:hidden"
         @click="sidebarOpen = false">
    </div>

    <!-- Main Layout Container -->
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Enhanced Sidebar with Advanced Responsive Behavior -->
        <div class="sidebar bg-white dark:bg-gray-800 shadow-xl transition-all duration-300 ease-in-out"
             :class="{
                 'fixed inset-y-0 left-0 z-40 w-48 transform -translate-x-full lg:translate-x-0 lg:static lg:inset-0': true,
                 'translate-x-0': sidebarOpen && isMobile,
                 'w-14': sidebarCollapsed && !isMobile,
                 'w-48': !sidebarCollapsed || isMobile
             }"
             x-show="sidebarOpen || !isMobile"
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full">
         
        <!-- Enhanced Logo Section -->
        <div class="flex items-center justify-between h-12 px-2 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary-50 to-primary-100 dark:from-gray-700 dark:to-gray-800">
            <div class="flex items-center space-x-1.5" :class="sidebarCollapsed && !isMobile ? 'justify-center' : ''">
                <div class="w-6 h-6 bg-gradient-to-br from-primary-500 to-primary-600 rounded-md flex items-center justify-center shadow-lg hover-lift">
                    <i class="fas fa-graduation-cap text-white text-xs"></i>
                </div>
                <span class="sidebar-text text-md font-bold text-gray-900 dark:text-white" x-show="!sidebarCollapsed || isMobile">Admin</span>
            </div>
            <!-- Mobile Close Button -->
            <button @click="sidebarOpen = false" 
                    class="lg:hidden p-0.5 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors touch-target">
                <i class="fas fa-times text-xs"></i>
            </button>
            <!-- Desktop Collapse Button -->
            <button @click="toggleSidebar()" 
                    class="hidden lg:block p-0.5 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors touch-target"
                    data-tooltip="Toggle Sidebar">
                <i class="fas fa-chevron-left transition-transform duration-200 text-xs" :class="sidebarCollapsed ? 'rotate-180' : ''"></i>
            </button>
        </div>

        <!-- Enhanced Navigation -->
        <nav class="flex-1 px-2 py-2 space-y-1.5 overflow-y-auto custom-scrollbar">
            <?php foreach ($nav_items as $key => $item): ?>
            <a href="<?= $item['url'] ?>" 
               class="group flex items-center gap-2.5 md:gap-3 justify-start text-left w-full px-2 py-2 md:px-2.5 md:py-2.5 text-xs leading-snug font-medium rounded-md transition-all duration-200 touch-target hover-lift focus:outline-none focus:ring-2 focus:ring-primary-400/50
                      <?= $active_page === $key ? 
                          'bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900/50 dark:to-primary-800/50 text-primary-700 dark:text-primary-300 border-r-3 border-primary-500 shadow-sm' : 
                          'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white' ?>"
               data-tooltip="<?= $item['title'] ?>">
                <i class="nav-icon fas <?= $item['icon'] ?> flex-shrink-0 w-4 h-4 transition-colors duration-200
                         <?= $active_page === $key ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300' ?>"></i>
                <span class="sidebar-text truncate" x-show="!sidebarCollapsed || isMobile"><?= $item['title'] ?></span>
                <?php if ($item['badge']): ?>
                <span class="sidebar-text ml-auto bg-primary-100 dark:bg-primary-900 text-primary-600 dark:text-primary-400 text-xs px-1.5 py-0.5 rounded-full" 
                      x-show="!sidebarCollapsed || isMobile">
                    <?= $item['badge'] ?>
                </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>

        <!-- Enhanced Main Content Area -->
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
        <!-- Enhanced Top Header -->
        <header class="bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm shadow-sm border-b border-gray-200 dark:border-gray-700 sticky top-0 z-20">
            <div class="flex items-center justify-between h-12 px-2 sm:px-3 lg:px-4">
                <!-- Left side -->
                <div class="flex items-center space-x-3">
                    <button @click="toggleSidebar()" 
                            class="p-1.5 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors touch-target lg:hidden"
                            data-tooltip="Open Menu">
                        <i class="fas fa-bars text-sm"></i>
                    </button>
                    <div class="flex items-center space-x-1.5">
                        <h1 class="text-sm sm:text-md font-medium text-gray-900 dark:text-white truncate max-w-xs sm:max-w-sm">
                            <?= htmlspecialchars($title) ?>
                        </h1>
                        <!-- Breadcrumb indicator -->
                        <div class="hidden sm:flex items-center space-x-1 text-xs text-gray-500 dark:text-gray-400">
                            <i class="fas fa-chevron-right text-xs"></i>
                            <span class="capitalize truncate max-w-20"><?= $active_page ?></span>
                        </div>
                    </div>
                </div>

                <!-- Right side -->
                <div class="flex items-center space-x-1 sm:space-x-2">
                    <!-- Search (hidden on mobile) -->
                    <div class="hidden lg:block relative">
                        <input type="text" 
                               id="global-search"
                               placeholder="Search..."
                               class="w-36 pl-6 pr-2 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-xs transition-all"
                               @keydown.enter="performGlobalSearch($event.target.value)">
                        <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400 text-xs"></i>
                        </div>
                    </div>
                    
                    <!-- Dark mode toggle -->
                    <button @click="toggleDarkMode()" 
                            class="p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors touch-target"
                            data-tooltip="Toggle Dark Mode">
                        <i class="fas transition-transform duration-200 text-xs" :class="darkMode ? 'fa-sun text-yellow-500' : 'fa-moon'"></i>
                    </button>

                    <!-- Notifications -->
                    <button class="relative p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors touch-target"
                            data-tooltip="Notifications"
                            @click="showToast('No new notifications', 'info')">
                        <i class="fas fa-bell text-xs"></i>
                        <span class="absolute top-0 right-0 w-1.5 h-1.5 bg-red-400 rounded-full animate-pulse"></span>
                    </button>
                    
                    <!-- Profile dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" 
                                class="flex items-center space-x-1 p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors touch-target"
                                data-tooltip="Profile & Settings">
                            <img class="w-6 h-6 rounded-full ring-1 ring-primary-200 dark:ring-primary-700" 
                                 src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['admin_username'] ?? 'Admin') ?>&background=3b82f6&color=fff&size=24" 
                                 alt="Profile">
                            <i class="fas fa-chevron-down text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" 
                             @click.away="open = false"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-1 w-56 bg-white dark:bg-gray-800 rounded-md shadow-lg border border-gray-200 dark:border-gray-700 z-50">
                            
                            <!-- Profile Card -->
                            <div class="p-2 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <img class="w-8 h-8 rounded-full ring-1 ring-primary-200 dark:ring-primary-700" 
                                         src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['admin_username'] ?? 'Admin') ?>&background=3b82f6&color=fff&size=32" 
                                         alt="Profile">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium mb-0.5">Profile</div>
                                        <p class="text-xs font-medium text-gray-900 dark:text-white truncate">
                                            <?= htmlspecialchars($_SESSION['admin_username'] ?? 'admin') ?>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Administrator</p>
                                    </div>
                                </div>
                                
                                <!-- Status Badge -->
                                <div class="mt-1.5 flex items-center justify-between">
                                    <div class="flex items-center space-x-1">
                                        <div class="w-1.5 h-1.5 bg-green-500 rounded-full"></div>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Online</span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        <?= date('M j') ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Settings Menu -->
                            <div class="py-0.5">
                                <a href="#" class="flex items-center px-2 py-1 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-user-cog mr-1.5 w-3 text-gray-400"></i> Profile Settings
                                </a>
                                <a href="#" class="flex items-center px-2 py-1 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-bell mr-1.5 w-3 text-gray-400"></i> Notifications
                                </a>
                                <a href="#" class="flex items-center px-2 py-1 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-shield-alt mr-1.5 w-3 text-gray-400"></i> Privacy & Security
                                </a>
                                <a href="#" class="flex items-center px-2 py-1 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-question-circle mr-1.5 w-3 text-gray-400"></i> Help & Support
                                </a>
                            </div>
                            
                            <!-- Logout Section -->
                            <div class="border-t border-gray-100 dark:border-gray-700 p-0.5">
                                <a href="logout.php" class="flex items-center w-full px-2 py-1 text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors">
                                    <i class="fas fa-sign-out-alt mr-1.5 w-3"></i> Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Enhanced Page Content -->
        <main class="flex-1 overflow-auto bg-gray-50 dark:bg-gray-900">
            <div class="p-2 sm:p-3 lg:p-4 max-w-7xl mx-auto">
                <?= $content ?>
            </div>
        </main>
    </div>
</div>

    <!-- Global Scripts -->
    <script>
        function adminApp() {
            return {
                sidebarOpen: false,
                sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
                darkMode: localStorage.getItem('darkMode') === 'true',
                isMobile: window.innerWidth < 1024,
                
                initApp() {
                    // Apply dark mode
                    this.applyDarkMode();
                    
                    // Apply sidebar state
                    this.applySidebarState();
                    
                    // Watch for screen size changes
                    window.addEventListener('resize', () => {
                        const wasMobile = this.isMobile;
                        this.isMobile = window.innerWidth < 1024;
                        
                        if (this.isMobile && !wasMobile) {
                            // Switched to mobile
                            this.sidebarOpen = false;
                        } else if (!this.isMobile && wasMobile) {
                            // Switched to desktop
                            this.sidebarOpen = false;
                        }
                    });
                    
                    // Close sidebar on navigation (mobile)
                    document.addEventListener('click', (e) => {
                        if (e.target.tagName === 'A' && this.isMobile) {
                            this.sidebarOpen = false;
                        }
                    });
                    
                    // Keyboard shortcuts
                    this.setupKeyboardShortcuts();
                    
                    // Initialize advanced features
                    this.initAdvancedFeatures();
                },
                
                toggleSidebar() {
                    if (this.isMobile) {
                        this.sidebarOpen = !this.sidebarOpen;
                    } else {
                        this.sidebarCollapsed = !this.sidebarCollapsed;
                        localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
                        this.applySidebarState();
                    }
                },
                
                toggleDarkMode() {
                    this.darkMode = !this.darkMode;
                    localStorage.setItem('darkMode', this.darkMode);
                    this.applyDarkMode();
                    
                    // Show toast notification
                    this.showToast(
                        `${this.darkMode ? 'Dark' : 'Light'} mode enabled`,
                        'success'
                    );
                },
                
                applyDarkMode() {
                    if (this.darkMode) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                },
                
                applySidebarState() {
                    // Flexbox layout handles positioning automatically
                    // No manual padding adjustments needed
                },
                
                setupKeyboardShortcuts() {
                    document.addEventListener('keydown', (e) => {
                        // Toggle sidebar with Ctrl/Cmd + B
                        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                            e.preventDefault();
                            this.toggleSidebar();
                        }
                        
                        // Toggle dark mode with Ctrl/Cmd + D
                        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                            e.preventDefault();
                            this.toggleDarkMode();
                        }
                        
                        // Close sidebar with Escape (mobile)
                        if (e.key === 'Escape' && this.isMobile && this.sidebarOpen) {
                            this.sidebarOpen = false;
                        }
                    });
                },
                
                initAdvancedFeatures() {
                    // Initialize tooltips
                    this.initTooltips();
                    
                    // Initialize smooth scrolling
                    this.initSmoothScrolling();
                    
                    // Initialize performance monitoring
                    this.initPerformanceMonitoring();
                },
                
                initTooltips() {
                    document.querySelectorAll('[data-tooltip]').forEach(el => {
                        el.addEventListener('mouseenter', this.showTooltip.bind(this));
                        el.addEventListener('mouseleave', this.hideTooltip.bind(this));
                    });
                },
                
                initSmoothScrolling() {
                    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                        anchor.addEventListener('click', function (e) {
                            e.preventDefault();
                            const target = document.querySelector(this.getAttribute('href'));
                            if (target) {
                                target.scrollIntoView({ behavior: 'smooth' });
                            }
                        });
                    });
                },
                
                initPerformanceMonitoring() {
                    // Monitor page load performance
                    window.addEventListener('load', () => {
                        const loadTime = performance.now();
                        if (loadTime > 3000) {
                            console.warn('Page load time exceeded 3 seconds:', loadTime);
                        }
                    });
                },
                
                showTooltip(e) {
                    const text = e.target.getAttribute('data-tooltip');
                    if (!text) return;
                    
                    const tooltip = document.createElement('div');
                    tooltip.className = 'fixed z-50 px-2 py-1 text-xs text-white bg-gray-900 dark:bg-gray-700 rounded shadow-lg pointer-events-none animate-fade-in';
                    tooltip.textContent = text;
                    
                    const rect = e.target.getBoundingClientRect();
                    tooltip.style.left = (rect.left + rect.width / 2) + 'px';
                    tooltip.style.top = (rect.top - 30) + 'px';
                    tooltip.style.transform = 'translateX(-50%)';
                    
                    document.body.appendChild(tooltip);
                    e.target._tooltip = tooltip;
                },
                
                hideTooltip(e) {
                    if (e.target._tooltip) {
                        e.target._tooltip.remove();
                        e.target._tooltip = null;
                    }
                },
                
                showToast(message, type = 'info', duration = 3000) {
                    // Remove existing toasts
                    document.querySelectorAll('.toast-notification').forEach(toast => toast.remove());
                    
                    const toast = document.createElement('div');
                    toast.className = `toast-notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white max-w-sm animate-slide-in-right`;
                    
                    const colors = {
                        success: 'bg-green-500',
                        error: 'bg-red-500',
                        warning: 'bg-yellow-500',
                        info: 'bg-blue-500'
                    };
                    
                    const icons = {
                        success: 'fa-check-circle',
                        error: 'fa-exclamation-circle',
                        warning: 'fa-exclamation-triangle',
                        info: 'fa-info-circle'
                    };
                    
                    toast.className += ` ${colors[type] || colors.info}`;
                    
                    toast.innerHTML = `
                        <div class="flex items-center space-x-2">
                            <i class="fas ${icons[type] || icons.info}"></i>
                            <span class="flex-1">${message}</span>
                            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 hover:text-gray-200 focus:outline-none">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    
                    document.body.appendChild(toast);
                    
                    // Auto remove after duration
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.style.animation = 'slideOutRight 0.3s ease-in forwards';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, duration);
                    
                    return toast;
                }
            }
        }
        
        // Global utility functions
        function showToast(message, type = 'info') {
            if (window.Alpine && window.Alpine.store) {
                const app = window.Alpine.store('app');
                if (app && app.showToast) {
                    return app.showToast(message, type);
                }
            }
            
            // Fallback implementation
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
        
        function performGlobalSearch(query) {
            if (query && query.trim().length > 1) {
                window.location.href = `students.php?search=${encodeURIComponent(query.trim())}`;
            }
        }
        
        // Initialize global features
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states
            document.addEventListener('click', function(e) {
                if (e.target.tagName === 'A' && !e.target.href.includes('#')) {
                    const target = e.target;
                    target.style.opacity = '0.7';
                    target.style.pointerEvents = 'none';
                }
            });
            
            // Enhanced keyboard navigation
            document.addEventListener('keydown', function(e) {
                // Alt + number shortcuts for quick navigation
                if (e.altKey && !isNaN(e.key) && e.key >= 1 && e.key <= 6) {
                    e.preventDefault();
                    const links = document.querySelectorAll('nav a[href]');
                    const index = parseInt(e.key) - 1;
                    if (links[index]) {
                        links[index].click();
                    }
                }
            });
            
            // Performance monitoring
            if ('performance' in window) {
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        const perfData = performance.getEntriesByType('navigation')[0];
                        if (perfData && perfData.loadEventEnd - perfData.loadEventStart > 3000) {
                            console.warn('Page load time exceeded 3 seconds');
                        }
                    }, 0);
                });
            }
        });
        
        // Additional animations
        const additionalStyles = document.createElement('style');
        additionalStyles.textContent = `
            @keyframes slideOutRight {
                0% { transform: translateX(0); opacity: 1; }
                100% { transform: translateX(100%); opacity: 0; }
            }
            
            .animate-slide-out-right {
                animation: slideOutRight 0.3s ease-in forwards;
            }
            
            /* Enhanced loading states */
            .loading {
                position: relative;
                pointer-events: none;
            }
            
            .loading::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 16px;
                height: 16px;
                margin: -8px 0 0 -8px;
                border: 2px solid transparent;
                border-top: 2px solid currentColor;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(additionalStyles);
    </script>
    
    <?= $page_script ?>
</body>
</html>
    <?php
    return ob_get_clean();
}
?>
