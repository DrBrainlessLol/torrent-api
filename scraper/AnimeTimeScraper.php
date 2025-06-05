<?php
class AnimeTimeScraper {
    private $baseUrl;
    private $userAgent;
    
    public function __construct() {
        $this->baseUrl = ANIMETIME_BASE_URL;
        $this->userAgent = USER_AGENT;
    }
    
    /**
     * Get latest torrents from the main page
     */
    public function getLatestTorrents($page = 1, $limit = 25) {
        $cacheKey = "latest_torrents_page_{$page}_limit_{$limit}";
        $cached = getCache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $url = $this->baseUrl . '/anime';
        if ($page > 1) {
            $url .= '?page=' . $page;
        }
        
        $html = $this->fetchPage($url);
        $torrents = $this->parseAnimeList($html);
        
        // Apply limit
        $torrents = array_slice($torrents, 0, $limit);
        
        setCache($cacheKey, $torrents);
        return $torrents;
    }
      /**
     * Search for torrents by query
     */
    public function searchTorrents($query, $page = 1) {
        $cacheKey = "search_torrents_" . md5($query) . "_page_{$page}";
        $cached = getCache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Use /search endpoint with query parameter based on the form we saw
        $url = $this->baseUrl . '/search?query=' . urlencode($query);
        if ($page > 1) {
            $url .= '&page=' . $page;
        }
        
        $html = $this->fetchPage($url);
        $torrents = $this->parseAnimeList($html);
        
        setCache($cacheKey, $torrents);
        return $torrents;
    }
    
    /**
     * Get detailed torrent information
     */
    public function getTorrentDetails($torrentId) {
        $cacheKey = "torrent_details_{$torrentId}";
        $cached = getCache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Use /view/{id} instead of /anime/{id}
        $url = $this->baseUrl . '/view/' . $torrentId;
        $html = $this->fetchPage($url);
        $details = $this->parseAnimeDetails($html);
        
        if ($details) {
            setCache($cacheKey, $details);
        }
        
        return $details;
    }    /**
     * Fetch a web page with proper headers and error handling
     */
    private function fetchPage($url) {
        // Check rate limit
        if (!RateLimiter::checkLimit('scraper')) {
            throw new Exception('Rate limit exceeded');
        }
        
        // Use cURL instead of file_get_contents for better HTTPS support
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: identity',
                'Cache-Control: no-cache',
                'Connection: close'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '', // Let cURL handle encoding automatically
            CURLOPT_VERBOSE => false
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($html === false || !empty($error)) {
            logError("Failed to fetch page: $url", ['curl_error' => $error, 'http_code' => $httpCode]);
            throw new Exception("Failed to fetch page: $error");
        }
        
        if ($httpCode >= 400) {
            logError("HTTP error for page: $url", ['http_code' => $httpCode]);
            throw new Exception("HTTP error $httpCode for page: $url");
        }
        
        // Check if we got a valid response
        if (empty($html) || strlen($html) < 100) {
            logError("Empty or suspicious response from: $url", ['response_length' => strlen($html), 'http_code' => $httpCode]);
            throw new Exception("Empty or invalid response from server");
        }
        
        return $html;
    }
      /**
     * Parse the anime list from HTML (search/listing page)
     */
    private function parseAnimeList($html) {
        $torrents = [];
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        
        // Find all torrent cards - they are in div containers with class "p-2 space-y-2"
        $cards = $xpath->query('//div[contains(@class, "p-2") and contains(@class, "space-y-2")]');
        
        foreach ($cards as $card) {
            // Find the title link with href="/view/{id}"
            $titleLink = $xpath->query('.//a[contains(@href, "/view/")]', $card)->item(0);
            if (!$titleLink) continue;
            
            $href = $titleLink->getAttribute('href');
            $id = $this->extractIdFromUrl($href);
            $title = trim($titleLink->textContent);
            
            if (empty($title) || strlen($title) < 2) continue;
            
            // Parse badges for category, tags, size
            $category = null;
            $tags = [];
            $size = null;
            
            $badgeSpans = $xpath->query('.//span[contains(@class, "badge")]', $card);
            foreach ($badgeSpans as $span) {
                $badgeText = trim($span->textContent);
                $badgeClass = $span->getAttribute('class');
                
                if (strpos($badgeClass, 'badge-primary') !== false) {
                    $category = $badgeText; // Primary badge is usually category (anime, books, etc.)
                } elseif (strpos($badgeClass, 'badge-secondary') !== false) {
                    $tags[] = $badgeText; // Secondary badges are tags
                } elseif (strpos($badgeClass, 'badge-ghost') !== false) {
                    $size = $badgeText; // Ghost badge is usually size
                }
            }
            
            // Find magnet and torrent download links
            $magnet = null;
            $torrent = null;
            
            $magnetLink = $xpath->query('.//a[starts-with(@href, "magnet:")]', $card)->item(0);
            if ($magnetLink) {
                $magnet = $magnetLink->getAttribute('href');
            }
            
            $torrentLink = $xpath->query('.//a[contains(@href, "/download/")]', $card)->item(0);
            if ($torrentLink) {
                $torrent = $torrentLink->getAttribute('href');
                // Make torrent URL absolute if it's relative
                if (strpos($torrent, 'http') !== 0) {
                    $torrent = $this->baseUrl . $torrent;
                }
            }
            
            // Parse timestamp if available
            $timestamp = null;
            $timestampElement = $xpath->query('.//span[@data-tip]', $card)->item(0);
            if ($timestampElement) {
                $timestamp = $timestampElement->getAttribute('data-tip');
            }
            
            $torrents[] = [
                'id' => $id,
                'title' => $title,
                'url' => strpos($href, 'http') === 0 ? $href : $this->baseUrl . $href,
                'category' => $category,
                'tags' => $tags,
                'size' => $size,
                'magnet' => $magnet,
                'torrent' => $torrent,
                'timestamp' => $timestamp,
                'scraped_at' => date('Y-m-d H:i:s')
            ];
        }
        
        return array_values($torrents);
    }
    
    /**
     * Parse individual anime card
     */
    private function parseAnimeCard($card, $xpath) {
        $link = $xpath->query('.//a[contains(@href, "/anime/")]', $card)->item(0);
        if (!$link) return null;
        
        $href = $link->getAttribute('href');
        $id = $this->extractIdFromUrl($href);
        
        // Extract title
        $title = '';
        $titleElement = $xpath->query('.//h3 | .//h4 | .//h5 | .//h6 | .//*[contains(@class, "title")]', $card)->item(0);
        if ($titleElement) {
            $title = trim($titleElement->textContent);
        } else {
            $title = trim($link->textContent);
        }
        
        // Extract image
        $image = '';
        $imgElement = $xpath->query('.//img', $card)->item(0);
        if ($imgElement) {
            $image = $imgElement->getAttribute('src');
            if ($image && !filter_var($image, FILTER_VALIDATE_URL)) {
                $image = $this->baseUrl . '/' . ltrim($image, '/');
            }
        }
        
        // Extract additional info
        $info = $this->extractCardInfo($card, $xpath);
        
        return [
            'id' => $id,
            'title' => $title,
            'url' => $this->baseUrl . $href,
            'image' => $image,
            'type' => $info['type'] ?? 'anime',
            'status' => $info['status'] ?? 'unknown',
            'episodes' => $info['episodes'] ?? null,
            'year' => $info['year'] ?? null,
            'genres' => $info['genres'] ?? [],
            'description' => $info['description'] ?? '',
            'scraped_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Parse anime link (fallback method)
     */
    private function parseAnimeLink($link, $xpath) {
        $href = $link->getAttribute('href');
        $id = $this->extractIdFromUrl($href);
        $title = trim($link->textContent);
        
        if (empty($title) || strlen($title) < 2) {
            return null;
        }
        
        return [
            'id' => $id,
            'title' => $title,
            'url' => $this->baseUrl . $href,
            'image' => '',
            'type' => 'anime',
            'status' => 'unknown',
            'episodes' => null,
            'year' => null,
            'genres' => [],
            'description' => '',
            'scraped_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Extract additional info from card
     */
    private function extractCardInfo($card, $xpath) {
        $info = [];
        
        // Try to extract episodes
        $episodeText = $xpath->query('.//*[contains(text(), "Episode") or contains(text(), "Ep")]', $card)->item(0);
        if ($episodeText) {
            preg_match('/(\d+)/', $episodeText->textContent, $matches);
            if (isset($matches[1])) {
                $info['episodes'] = (int)$matches[1];
            }
        }
        
        // Try to extract year
        $yearText = $xpath->query('.//*[contains(text(), "20")]', $card)->item(0);
        if ($yearText) {
            preg_match('/(20\d{2})/', $yearText->textContent, $matches);
            if (isset($matches[1])) {
                $info['year'] = (int)$matches[1];
            }
        }
        
        // Try to extract status
        $statusText = $xpath->query('.//*[contains(@class, "status") or contains(text(), "Ongoing") or contains(text(), "Completed")]', $card)->item(0);
        if ($statusText) {
            $status = strtolower(trim($statusText->textContent));
            if (strpos($status, 'ongoing') !== false) {
                $info['status'] = 'ongoing';
            } elseif (strpos($status, 'completed') !== false) {
                $info['status'] = 'completed';
            }
        }
        
        return $info;
    }
      /**
     * Parse detailed anime page (torrent detail page)
     */
    private function parseAnimeDetails($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        
        // Title from <h1> in the center
        $title = '';
        $h1 = $xpath->query('//h1[contains(@class, "text-xl") and contains(@class, "text-center")]')->item(0);
        if (!$h1) {
            $h1 = $xpath->query('//h1')->item(0);
        }
        if ($h1) {
            $title = trim($h1->textContent);
        }
        
        // Category from badge-primary
        $category = null;
        $categorySpan = $xpath->query('//span[contains(@class, "badge-primary")]')->item(0);
        if ($categorySpan) {
            $category = trim($categorySpan->textContent);
        }
        
        // Tags from badge-secondary
        $tags = [];
        $tagSpans = $xpath->query('//span[contains(@class, "badge-secondary")]');
        foreach ($tagSpans as $span) {
            $tagText = trim($span->textContent);
            if (!empty($tagText)) {
                $tags[] = $tagText;
            }
        }
        
        // Size from badge-ghost
        $size = null;
        $sizeSpan = $xpath->query('//span[contains(@class, "badge-ghost")]')->item(0);
        if ($sizeSpan) {
            $size = trim($sizeSpan->textContent);
        }
        
        // Created/Updated timestamps from tooltip elements
        $created_at = null;
        $updated_at = null;
        
        $timestampElements = $xpath->query('//span[@data-tip]');
        foreach ($timestampElements as $i => $element) {
            $timestamp = $element->getAttribute('data-tip');
            if ($i === 0) {
                $created_at = $timestamp;
            } elseif ($i === 1) {
                $updated_at = $timestamp;
            }
        }
        
        // Magnet link
        $magnet = null;
        $magnetLink = $xpath->query('//a[starts-with(@href, "magnet:")]')->item(0);
        if ($magnetLink) {
            $magnet = $magnetLink->getAttribute('href');
        }
        
        // Torrent download link
        $torrent = null;
        $torrentLink = $xpath->query('//a[contains(@href, "/download/")]')->item(0);
        if ($torrentLink) {
            $torrent = $torrentLink->getAttribute('href');
            // Make torrent URL absolute if it's relative
            if (strpos($torrent, 'http') !== 0) {
                $torrent = $this->baseUrl . $torrent;
            }
        }
        
        // Description (if available)
        $description = '';
        $descElement = $xpath->query('//div[contains(@class, "description") or contains(@class, "summary")]')->item(0);
        if ($descElement) {
            $description = trim($descElement->textContent);
        }
        
        return [
            'title' => $title,
            'category' => $category,
            'tags' => $tags,
            'size' => $size,
            'description' => $description,
            'created_at' => $created_at,
            'updated_at' => $updated_at,
            'magnet' => $magnet,
            'torrent' => $torrent,
            'scraped_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Extract title from detail page
     */
    private function extractDetailTitle($xpath) {
        // Try different selectors for title
        $titleSelectors = [
            '//h1',
            '//h2',
            '//*[contains(@class, "title")]',
            '//*[contains(@class, "name")]'
        ];
        
        foreach ($titleSelectors as $selector) {
            $element = $xpath->query($selector)->item(0);
            if ($element && !empty(trim($element->textContent))) {
                return trim($element->textContent);
            }
        }
        
        return '';
    }
    
    /**
     * Extract description from detail page
     */
    private function extractDescription($xpath) {
        $descSelectors = [
            '//*[contains(@class, "description")]',
            '//*[contains(@class, "summary")]',
            '//*[contains(@class, "synopsis")]',
            '//p[string-length(text()) > 50]'
        ];
        
        foreach ($descSelectors as $selector) {
            $element = $xpath->query($selector)->item(0);
            if ($element && !empty(trim($element->textContent))) {
                return trim($element->textContent);
            }
        }
        
        return '';
    }
    
    /**
     * Extract image from detail page
     */
    private function extractDetailImage($xpath) {
        $img = $xpath->query('//img[contains(@class, "poster") or contains(@class, "cover") or contains(@src, "poster") or contains(@src, "cover")]')->item(0);
        
        if (!$img) {
            $img = $xpath->query('//img')->item(0);
        }
        
        if ($img) {
            $src = $img->getAttribute('src');
            if ($src && !filter_var($src, FILTER_VALIDATE_URL)) {
                $src = $this->baseUrl . '/' . ltrim($src, '/');
            }
            return $src;
        }
        
        return '';
    }
    
    /**
     * Extract detailed info from page
     */
    private function extractDetailInfo($xpath) {
        $info = [];
        
        // Extract all text content and look for patterns
        $bodyText = $xpath->query('//body')->item(0)->textContent;
        
        // Extract year
        preg_match('/(20\d{2})/', $bodyText, $yearMatches);
        if (isset($yearMatches[1])) {
            $info['year'] = (int)$yearMatches[1];
        }
        
        // Extract episodes
        preg_match('/(\d+)\s*(?:episodes?|eps?)/i', $bodyText, $episodeMatches);
        if (isset($episodeMatches[1])) {
            $info['episodes'] = (int)$episodeMatches[1];
        }
        
        // Extract genres
        $genreElements = $xpath->query('//*[contains(@class, "genre") or contains(text(), "Genre")]');
        $genres = [];
        foreach ($genreElements as $element) {
            $text = $element->textContent;
            $extractedGenres = preg_split('/[,\s]+/', $text);
            foreach ($extractedGenres as $genre) {
                $genre = trim($genre);
                if (strlen($genre) > 2 && !in_array($genre, $genres)) {
                    $genres[] = $genre;
                }
            }
        }
        $info['genres'] = array_slice($genres, 0, 10); // Limit to 10 genres
        
        return $info;
    }
    
    /**
     * Extract quality from text
     */
    private function extractQualityFromText($text) {
        $qualities = ['4K', '2160p', '1440p', '1080p', '720p', '480p', '360p'];
        foreach ($qualities as $quality) {
            if (stripos($text, $quality) !== false) {
                return $quality;
            }
        }
        return 'unknown';
    }
    
    /**
     * Extract file size from text
     */
    private function extractSizeFromText($text) {
        preg_match('/(\d+(?:\.\d+)?)\s*(GB|MB|KB)/i', $text, $matches);
        if (isset($matches[1]) && isset($matches[2])) {
            return $matches[1] . ' ' . strtoupper($matches[2]);
        }
        return null;
    }
    
    /**
     * Extract ID from URL
     */
    private function extractIdFromUrl($url) {
        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        return end($segments);
    }
}
?>
