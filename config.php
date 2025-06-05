<?php
// Configuration for Torrent API

// Prevent multiple inclusions
if (defined('TORRENT_API_CONFIG_LOADED')) {
    return;
}
define('TORRENT_API_CONFIG_LOADED', true);

// Site configuration
define('ANIMETIME_BASE_URL', 'https://animetime.cc');
define('API_VERSION', '1.0.0');
define('MAX_REQUESTS_PER_MINUTE', 60);

// User agent for web scraping
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

// Request timeouts
if (!defined('REQUEST_TIMEOUT')) {
    define('REQUEST_TIMEOUT', 30);
}
if (!defined('CONNECTION_TIMEOUT')) {
    define('CONNECTION_TIMEOUT', 10);
}

// Caching settings
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600); // 1 hour in seconds
define('CACHE_DIR', __DIR__ . '/cache');

// AniList API settings
define('ANILIST_API_URL', 'https://graphql.anilist.co');
define('ANILIST_RATE_LIMIT', 90); // requests per minute

// Torrent streaming settings
define('WEBTORRENT_ENABLED', true);
define('MAX_CONNECTIONS', 55);
define('DOWNLOAD_LIMIT', -1); // -1 for unlimited
define('UPLOAD_LIMIT', -1);   // -1 for unlimited

// Error reporting
define('DEBUG_MODE', true);
define('LOG_ERRORS', true);
define('LOG_FILE', __DIR__ . '/logs/api.log');

// Create necessary directories
if (!file_exists(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

if (!file_exists(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

// Helper function for logging
function logError($message, $context = []) {
    if (LOG_ERRORS) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' Context: ' . json_encode($context) : '';
        $logMessage = "[$timestamp] $message$contextStr" . PHP_EOL;
        file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// Helper function for caching
function getCacheKey($key) {
    return CACHE_DIR . '/' . md5($key) . '.cache';
}

function getCache($key) {
    if (!CACHE_ENABLED) return null;
    
    $cacheFile = getCacheKey($key);
    if (!file_exists($cacheFile)) return null;
    
    $cacheData = file_get_contents($cacheFile);
    $data = unserialize($cacheData);
    
    if ($data['expires'] < time()) {
        unlink($cacheFile);
        return null;
    }
    
    return $data['content'];
}

function setCache($key, $content) {
    if (!CACHE_ENABLED) return;
    
    $cacheFile = getCacheKey($key);
    $data = [
        'content' => $content,
        'expires' => time() + CACHE_DURATION
    ];
    
    file_put_contents($cacheFile, serialize($data), LOCK_EX);
}

// Rate limiting helper
class RateLimiter {
    private static $requests = [];
    
    public static function checkLimit($identifier, $limit = MAX_REQUESTS_PER_MINUTE) {
        $now = time();
        $minute = floor($now / 60);
        
        if (!isset(self::$requests[$identifier])) {
            self::$requests[$identifier] = [];
        }
        
        // Clean old requests
        self::$requests[$identifier] = array_filter(
            self::$requests[$identifier],
            function($timestamp) use ($minute) {
                return floor($timestamp / 60) >= $minute;
            }
        );
        
        if (count(self::$requests[$identifier]) >= $limit) {
            return false;
        }
        
        self::$requests[$identifier][] = $now;
        return true;
    }
}
?>
