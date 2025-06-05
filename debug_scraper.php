<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once 'config.php';
require_once 'scraper/AnimeTimeScraper.php';

echo "<h1>Scraper Debug Test</h1>\n";

try {
    $scraper = new AnimeTimeScraper();
    
    echo "<h2>Testing Latest Torrents</h2>\n";
    $torrents = $scraper->getLatestTorrents(1, 5);
    echo "<pre>Latest Torrents Result:\n";
    print_r($torrents);
    echo "</pre>\n";
    
    echo "<h2>Testing Search</h2>\n";
    $searchResults = $scraper->searchTorrents('solo leveling', 1);
    echo "<pre>Search Results:\n";
    print_r($searchResults);
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<h2>Error:</h2>\n";
    echo "<pre>" . $e->getMessage() . "</pre>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

echo "<h2>PHP Info</h2>\n";
echo "cURL enabled: " . (extension_loaded('curl') ? 'Yes' : 'No') . "\n<br>";
echo "OpenSSL enabled: " . (extension_loaded('openssl') ? 'Yes' : 'No') . "\n<br>";
echo "User Agent: " . USER_AGENT . "\n<br>";
echo "Base URL: " . ANIMETIME_BASE_URL . "\n<br>";
?>
