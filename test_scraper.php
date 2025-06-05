<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the required files
require_once 'config.php';
require_once 'includes/cache.php';
require_once 'includes/rate_limiter.php';
require_once 'includes/logger.php';
require_once 'scraper/AnimeTimeScraper.php';

echo "Testing AnimeTime Scraper...\n";

try {
    $scraper = new AnimeTimeScraper();
    echo "Scraper created successfully.\n";
    
    echo "Testing latest torrents...\n";
    $torrents = $scraper->getLatestTorrents(1, 5);
    echo "Found " . count($torrents) . " torrents\n";
    
    if (count($torrents) > 0) {
        echo "First torrent:\n";
        print_r($torrents[0]);
    }
    
    echo "\nTesting search...\n";
    $searchResults = $scraper->searchTorrents('naruto', 1);
    echo "Found " . count($searchResults) . " search results\n";
    
    if (count($searchResults) > 0) {
        echo "First search result:\n";
        print_r($searchResults[0]);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
