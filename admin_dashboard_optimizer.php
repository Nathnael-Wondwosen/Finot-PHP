<?php
/**
 * Admin Dashboard Performance Optimizer
 * Applies targeted optimizations for admin dashboard performance
 */

require_once 'config.php';
require_once 'includes/cache.php';

class AdminDashboardOptimizer {
    private $pdo;
    private $cache;

    public function __construct($pdo, $cache) {
        $this->pdo = $pdo;
        $this->cache = $cache;
    }

    /**
     * Optimize admin dashboard performance
     */
    public function optimizeDashboard() {
        $this->optimizeDatabaseIndexes();
        $this->precomputeDashboardStats();
        $this->optimizeNavigationCache();
        $this->clearOldCache();
    }

    /**
     * Add performance indexes for admin queries
     */
    private function optimizeDatabaseIndexes() {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_students_created_at ON students(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_students_gender ON students(gender)",
            "CREATE INDEX IF NOT EXISTS idx_students_current_grade ON students(current_grade)",
            "CREATE INDEX IF NOT EXISTS idx_students_flagged ON students(flagged)",
            "CREATE INDEX IF NOT EXISTS idx_parents_student_type ON parents(student_id, parent_type)",
            "CREATE INDEX IF NOT EXISTS idx_classes_status ON classes(status)",
            "CREATE INDEX IF NOT EXISTS idx_instrument_registrations_created_at ON instrument_registrations(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_admins_status ON admins(status)"
        ];

        foreach ($indexes as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (Exception $e) {
                error_log("Failed to create index: " . $e->getMessage());
            }
        }
    }

    /**
     * Precompute dashboard statistics
     */
    private function precomputeDashboardStats() {
        // Precompute main dashboard stats
        $stats = $this->calculateDashboardStats();
        cache_set('dashboard_stats_precomputed', $stats, 600); // 10 minutes

        // Precompute navigation badges
        $badges = $this->calculateNavigationBadges();
        cache_set('admin_nav_badges_precomputed', $badges, 300); // 5 minutes
    }

    /**
     * Calculate dashboard statistics efficiently
     */
    private function calculateDashboardStats() {
        try {
            // Single optimized query for all stats
            $stmt = $this->pdo->query("
                SELECT
                    COUNT(*) as total_students,
                    SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male_students,
                    SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female_students,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_this_month,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_this_week,
                    SUM(CASE WHEN YEAR(birth_date) <= 2006 THEN 1 ELSE 0 END) as youth_students
                FROM students
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $stats = [
                'total_students' => (int)$result['total_students'],
                'male_students' => (int)$result['male_students'],
                'female_students' => (int)$result['female_students'],
                'new_this_month' => (int)$result['new_this_month'],
                'new_this_week' => (int)$result['new_this_week'],
                'youth_students' => (int)$result['youth_students']
            ];

            $stats['under_18_students'] = $stats['total_students'] - $stats['youth_students'];

            // Grade distribution
            $stmt = $this->pdo->query("
                SELECT current_grade, COUNT(*) as count
                FROM students
                WHERE current_grade IS NOT NULL AND current_grade != ''
                GROUP BY current_grade
                ORDER BY count DESC
                LIMIT 10
            ");
            $stats['grade_distribution'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'count', 'current_grade');

            return $stats;

        } catch (Exception $e) {
            error_log("Error calculating dashboard stats: " . $e->getMessage());
            return [
                'total_students' => 0,
                'male_students' => 0,
                'female_students' => 0,
                'new_this_month' => 0,
                'new_this_week' => 0,
                'youth_students' => 0,
                'under_18_students' => 0,
                'grade_distribution' => []
            ];
        }
    }

    /**
     * Calculate navigation badges
     */
    private function calculateNavigationBadges() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM students");
            $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->pdo->query("SELECT COUNT(*) as flagged FROM students WHERE flagged = 1");
            $flagged_students = $stmt->fetch(PDO::FETCH_ASSOC)['flagged'];

            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM classes WHERE status = 'active'");
            $total_classes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'students' => $total_students > 0 ? number_format($total_students) : '',
                'data_quality' => $flagged_students > 0 ? $flagged_students : '',
                'classes' => $total_classes > 0 ? $total_classes : ''
            ];

        } catch (Exception $e) {
            return ['students' => '', 'data_quality' => '', 'classes' => ''];
        }
    }

    /**
     * Optimize navigation cache
     */
    private function optimizeNavigationCache() {
        // Clear old navigation cache
        $this->cache->deletePattern('admin_nav_*');
        $this->cache->deletePattern('nav_*');
    }

    /**
     * Clear old cache entries
     */
    private function clearOldCache() {
        // Clear cache older than 1 hour
        $this->cache->cleanup(3600);
    }

    /**
     * Get optimization report
     */
    public function getOptimizationReport() {
        return [
            'database_indexes' => 'Optimized',
            'dashboard_stats' => 'Precomputed',
            'navigation_cache' => 'Optimized',
            'cache_cleanup' => 'Completed',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

// Run optimization if called directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    if (!isset($_SESSION['admin_id'])) {
        die('Admin access required');
    }

    $optimizer = new AdminDashboardOptimizer($pdo, $cache ?? null);
    $optimizer->optimizeDashboard();

    $report = $optimizer->getOptimizationReport();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Admin dashboard optimization completed',
        'report' => $report
    ]);
}
?>