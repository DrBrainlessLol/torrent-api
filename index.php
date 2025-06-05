<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';
require_once 'scraper/AnimeTimeScraper.php';
require_once 'mapping/AniListMapper.php';

$scraper = new AnimeTimeScraper();
$mapper = new AniListMapper();

// Parse request
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$query = parse_url($request, PHP_URL_QUERY);
parse_str($query ?? '', $params);

// Remove base path if running in subdirectory
$basePath = '/torrent-api';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Remove index.php if present
if ($path === '/index.php' || $path === '/index.php/') {
    $path = '/';
} elseif (strpos($path, '/index.php/') === 0) {
    $path = substr($path, 10); // Remove '/index.php'
}

// Route handling
switch ($path) {
    case '/':
    case '/health':
        echo json_encode([
            'status' => 'healthy',
            'service' => 'Torrent API',
            'version' => '1.0.0',
            'endpoints' => [
                '/torrents' => 'Get latest torrents',
                '/search' => 'Search torrents by query',
                '/torrent/{id}' => 'Get torrent details',
                '/anilist/search' => 'Search AniList for mapping',
                '/anilist/map' => 'Map torrent to AniList entry'
            ]
        ]);
        break;

    case '/torrents':
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 25;
        
        try {
            $torrents = $scraper->getLatestTorrents($page, $limit);
            echo json_encode([
                'success' => true,
                'data' => $torrents,
                'pagination' => [
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'total' => count($torrents)
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;    case '/search':
        $query = $params['query'] ?? '';
        $page = $params['page'] ?? 1;
        
        if (empty($query)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Query parameter "query" is required'
            ]);
            break;
        }
        
        try {
            $torrents = $scraper->searchTorrents($query, $page);
            echo json_encode([
                'success' => true,
                'data' => $torrents,
                'query' => $query,
                'page' => (int)$page
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case (preg_match('/^\/torrent\/(.+)$/', $path, $matches) ? true : false):
        $torrentId = $matches[1];
        
        try {
            $torrent = $scraper->getTorrentDetails($torrentId);
            if ($torrent) {
                echo json_encode([
                    'success' => true,
                    'data' => $torrent
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Torrent not found'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case '/anilist/search':
        $query = $params['q'] ?? '';
        
        if (empty($query)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Query parameter "q" is required'
            ]);
            break;
        }
        
        try {
            $results = $mapper->searchAniList($query);
            echo json_encode([
                'success' => true,
                'data' => $results,
                'query' => $query
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case '/anilist/map':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $torrentTitle = $input['torrent_title'] ?? '';
        $anilistId = $input['anilist_id'] ?? null;
        
        if (empty($torrentTitle)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'torrent_title is required'
            ]);
            break;
        }
        
        try {
            $mapping = $mapper->mapTorrentToAniList($torrentTitle, $anilistId);
            echo json_encode([
                'success' => true,
                'data' => $mapping
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    case '/stream/generate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
            break;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $magnetLink = $input['magnet_link'] ?? '';
        
        if (empty($magnetLink)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'magnet_link is required'
            ]);
            break;
        }
        
        try {
            // This would integrate with a torrent streaming service
            // For now, return the magnet link formatted for streaming
            $streamData = [
                'magnet_link' => $magnetLink,
                'stream_url' => null, // Would be generated by streaming service
                'player_config' => [
                    'type' => 'torrent',
                    'source' => $magnetLink,
                    'provider' => 'webtorrent'
                ]
            ];
            
            echo json_encode([
                'success' => true,
                'data' => $streamData
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint not found'
        ]);
        break;
}
?>
