<?php
/**
 * Asset Optimization Script
 * Combines and minifies CSS/JS files for better performance
 */

class AssetOptimizer {
    private $jsFiles = [];
    private $cssFiles = [];
    private $outputDir;
    
    public function __construct($outputDir = 'assets/') {
        $this->outputDir = $outputDir;
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }
    
    public function addJSFile($file) {
        if (file_exists($file)) {
            $this->jsFiles[] = $file;
        }
    }
    
    public function addCSSFile($file) {
        if (file_exists($file)) {
            $this->cssFiles[] = $file;
        }
    }
    
    public function minifyJS($js) {
        // Remove comments
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
        // Remove single line comments (but not URLs)
        $js = preg_replace('!//[^\\n]*!', '', $js);
        // Remove whitespace
        $js = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $js);
        return $js;
    }
    
    public function minifyCSS($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        // Remove space after colons
        $css = str_replace(': ', ':', $css);
        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
        return $css;
    }
    
    public function combineJS() {
        $combined = '';
        foreach ($this->jsFiles as $file) {
            $combined .= file_get_contents($file) . ";\n";
        }
        return $this->minifyJS($combined);
    }
    
    public function combineCSS() {
        $combined = '';
        foreach ($this->cssFiles as $file) {
            $combined .= file_get_contents($file) . "\n";
        }
        return $this->minifyCSS($combined);
    }
    
    public function saveOptimizedAssets() {
        $jsContent = $this->combineJS();
        $cssContent = $this->combineCSS();
        
        $jsResult = file_put_contents($this->outputDir . 'app.min.js', $jsContent);
        $cssResult = file_put_contents($this->outputDir . 'app.min.css', $cssContent);
        
        return [
            'js' => $jsResult !== false,
            'css' => $cssResult !== false,
            'js_size' => strlen($jsContent),
            'css_size' => strlen($cssContent)
        ];
    }
}
?>