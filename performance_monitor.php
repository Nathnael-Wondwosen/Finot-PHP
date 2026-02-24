<?php
/**
 * Performance Monitoring Script
 * Tracks page load times and identifies bottlenecks
 */

class PerformanceMonitor {
    private $startTime;
    private $checkpoints = [];
    
    public function __construct() {
        $this->startTime = microtime(true);
    }
    
    public function checkpoint($name) {
        $this->checkpoints[$name] = microtime(true);
    }
    
    public function getExecutionTime() {
        return microtime(true) - $this->startTime;
    }
    
    public function getCheckpointTime($name) {
        if (isset($this->checkpoints[$name])) {
            return $this->checkpoints[$name] - $this->startTime;
        }
        return null;
    }
    
    public function getReport() {
        $report = [
            'total_execution_time' => $this->getExecutionTime(),
            'checkpoints' => []
        ];
        
        foreach ($this->checkpoints as $name => $time) {
            $report['checkpoints'][$name] = $time - $this->startTime;
        }
        
        return $report;
    }
}

// Initialize performance monitor
$perfMonitor = new PerformanceMonitor();

// Function to log performance data
function logPerformanceData($page, $executionTime, $memoryUsage) {
    $logFile = __DIR__ . '/performance.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $page - Execution: {$executionTime}s - Memory: {$memoryUsage}MB\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Function to get formatted performance report
function getPerformanceReport($perfMonitor) {
    $report = $perfMonitor->getReport();
    $memoryUsage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
    
    $html = "<div class='bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4'>";
    $html .= "<h3 class='text-lg font-semibold text-blue-800 mb-2'>Performance Report</h3>";
    $html .= "<p class='text-sm text-blue-700'><strong>Total Execution Time:</strong> " . round($report['total_execution_time'] * 1000, 2) . " ms</p>";
    $html .= "<p class='text-sm text-blue-700'><strong>Peak Memory Usage:</strong> {$memoryUsage} MB</p>";
    
    if (!empty($report['checkpoints'])) {
        $html .= "<h4 class='text-md font-medium text-blue-800 mt-3 mb-1'>Checkpoints:</h4>";
        $html .= "<ul class='text-sm text-blue-700'>";
        foreach ($report['checkpoints'] as $name => $time) {
            $html .= "<li>{$name}: " . round($time * 1000, 2) . " ms</li>";
        }
        $html .= "</ul>";
    }
    
    $html .= "</div>";
    
    return $html;
}

// Usage example:
// $perfMonitor->checkpoint('database_query');
// $perfMonitor->checkpoint('data_processing');
// echo getPerformanceReport($perfMonitor);
// logPerformanceData('students_page', $perfMonitor->getExecutionTime(), round(memory_get_peak_usage(true) / 1024 / 1024, 2));
?>