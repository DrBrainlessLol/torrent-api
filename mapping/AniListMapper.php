<?php
class AniListMapper {
    private $apiUrl;
    private $rateLimiter;
    
    public function __construct() {
        $this->apiUrl = ANILIST_API_URL;
        $this->rateLimiter = ANILIST_RATE_LIMIT;
    }
    
    /**
     * Search AniList for anime by title
     */
    public function searchAniList($query) {
        $cacheKey = "anilist_search_" . md5($query);
        $cached = getCache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Check rate limit
        if (!RateLimiter::checkLimit('anilist', $this->rateLimiter)) {
            throw new Exception('AniList API rate limit exceeded');
        }
        
        $graphqlQuery = '
            query ($search: String) {
                Page(page: 1, perPage: 10) {
                    media(search: $search, type: ANIME) {
                        id
                        title {
                            romaji
                            english
                            native
                        }
                        format
                        status
                        episodes
                        season
                        seasonYear
                        genres
                        studios {
                            nodes {
                                name
                            }
                        }
                        coverImage {
                            large
                            medium
                        }
                        description
                        averageScore
                        popularity
                        synonyms
                        startDate {
                            year
                            month
                            day
                        }
                        endDate {
                            year
                            month
                            day
                        }
                    }
                }
            }
        ';
        
        $variables = [
            'search' => $query
        ];
        
        $response = $this->makeAniListRequest($graphqlQuery, $variables);
        
        if (isset($response['data']['Page']['media'])) {
            $results = $this->formatAniListResults($response['data']['Page']['media']);
            setCache($cacheKey, $results);
            return $results;
        }
        
        return [];
    }      /**
     * Map a torrent title to an AniList entry
     */
    public function mapTorrentToAniList($torrentTitle, $anilistId = null) {
        // If AniList ID is provided, get specific anime info
        if ($anilistId) {
            return $this->getAniListById($anilistId);
        }
        
        $searchResults = [];
        
        // Strategy 1: Clean torrent title for better matching
        $cleanTitle = $this->cleanTorrentTitle($torrentTitle);
        if (!empty($cleanTitle)) {
            $searchResults = $this->searchAniList($cleanTitle);
        }
        
        // Strategy 2: Try with base title only if first search fails
        if (empty($searchResults)) {
            $baseTitle = $this->extractBaseTitleFromTorrent($torrentTitle);
            if (!empty($baseTitle) && $baseTitle !== $cleanTitle) {
                $searchResults = $this->searchAniList($baseTitle);
            }
        }
        
        // Strategy 3: Try with just the main anime name (before S0X or Season)
        if (empty($searchResults)) {
            $mainTitle = preg_replace('/\s+(S\d+|Season\s*\d+).*$/i', '', $cleanTitle);
            if (!empty($mainTitle) && $mainTitle !== $cleanTitle && $mainTitle !== $baseTitle) {
                $searchResults = $this->searchAniList($mainTitle);
            }
        }
        
        // Strategy 4: Try with original title if still no results
        if (empty($searchResults)) {
            $searchResults = $this->searchAniList($torrentTitle);
        }
        
        // Strategy 5: Try extracting just the first part before any special characters
        if (empty($searchResults)) {
            if (preg_match('/^([A-Za-z0-9\s]+?)(?:\s+S\d+|\s+Season|\s*[\[\(])/i', $torrentTitle, $matches)) {
                $basicTitle = trim($matches[1]);
                if (strlen($basicTitle) > 3) {
                    $searchResults = $this->searchAniList($basicTitle);
                }
            }
        }
        
        // Strategy 6: Try extracting anime name from parentheses
        if (empty($searchResults) && preg_match('/\(([^)]*(?:dake|Level|Ken|no|wo|ga)[^)]*)\)/i', $torrentTitle, $matches)) {
            $alternativeTitle = trim($matches[1]);
            // Clean the Japanese title 
            $alternativeTitle = preg_replace('/,\s*(Dual-Audio|Multi-Subs|DUAL).*$/i', '', $alternativeTitle);
            if (strlen($alternativeTitle) > 3) {
                $searchResults = $this->searchAniList($alternativeTitle);
            }
        }
        
        if (!empty($searchResults)) {
            // Find best match using title similarity
            $bestMatch = $this->findBestMatch($torrentTitle, $searchResults);
            
            if ($bestMatch) {
                return [
                    'torrent_title' => $torrentTitle,
                    'anilist_match' => $bestMatch,
                    'confidence' => $this->calculateConfidence($torrentTitle, $bestMatch),
                    'matched_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        return [
            'torrent_title' => $torrentTitle,
            'anilist_match' => null,
            'confidence' => 0,
            'matched_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get AniList anime by ID
     */
    public function getAniListById($id) {
        $cacheKey = "anilist_anime_{$id}";
        $cached = getCache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Check rate limit
        if (!RateLimiter::checkLimit('anilist', $this->rateLimiter)) {
            throw new Exception('AniList API rate limit exceeded');
        }
        
        $graphqlQuery = '
            query ($id: Int) {
                Media(id: $id, type: ANIME) {
                    id
                    title {
                        romaji
                        english
                        native
                    }
                    format
                    status
                    episodes
                    season
                    seasonYear
                    genres
                    studios {
                        nodes {
                            name
                        }
                    }
                    coverImage {
                        large
                        medium
                    }
                    description
                    averageScore
                    popularity
                    synonyms
                    startDate {
                        year
                        month
                        day
                    }
                    endDate {
                        year
                        month
                        day
                    }
                    relations {
                        edges {
                            node {
                                id
                                title {
                                    romaji
                                    english
                                }
                            }
                            relationType
                        }
                    }
                }
            }
        ';
        
        $variables = [
            'id' => (int)$id
        ];
        
        $response = $this->makeAniListRequest($graphqlQuery, $variables);
        
        if (isset($response['data']['Media'])) {
            $result = $this->formatAniListResult($response['data']['Media']);
            setCache($cacheKey, $result);
            return $result;
        }
        
        return null;
    }
    
    /**
     * Make request to AniList GraphQL API
     */
    private function makeAniListRequest($query, $variables = []) {
        $postData = json_encode([
            'query' => $query,
            'variables' => $variables
        ]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: ' . USER_AGENT
                ],
                'content' => $postData,
                'timeout' => REQUEST_TIMEOUT
            ]
        ]);
        
        $response = @file_get_contents($this->apiUrl, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            logError("Failed to make AniList request", ['error' => $error, 'query' => $query]);
            throw new Exception("Failed to make AniList request: " . $error['message']);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            logError("Invalid JSON response from AniList", ['response' => $response]);
            throw new Exception("Invalid JSON response from AniList");
        }
        
        if (isset($decodedResponse['errors'])) {
            $errors = $decodedResponse['errors'];
            logError("AniList API errors", ['errors' => $errors]);
            throw new Exception("AniList API error: " . $errors[0]['message']);
        }
        
        return $decodedResponse;
    }
    
    /**
     * Format AniList search results
     */
    private function formatAniListResults($mediaList) {
        $results = [];
        
        foreach ($mediaList as $media) {
            $results[] = $this->formatAniListResult($media);
        }
        
        return $results;
    }
    
    /**
     * Format single AniList result
     */
    private function formatAniListResult($media) {
        return [
            'id' => $media['id'],
            'title' => [
                'romaji' => $media['title']['romaji'] ?? '',
                'english' => $media['title']['english'] ?? '',
                'native' => $media['title']['native'] ?? ''
            ],
            'format' => $media['format'] ?? '',
            'status' => strtolower($media['status'] ?? ''),
            'episodes' => $media['episodes'],
            'season' => $media['season'] ?? '',
            'year' => $media['seasonYear'] ?? ($media['startDate']['year'] ?? null),
            'genres' => $media['genres'] ?? [],
            'studios' => array_map(function($studio) {
                return $studio['name'];
            }, $media['studios']['nodes'] ?? []),
            'cover_image' => [
                'large' => $media['coverImage']['large'] ?? '',
                'medium' => $media['coverImage']['medium'] ?? ''
            ],
            'description' => $this->cleanDescription($media['description'] ?? ''),
            'score' => $media['averageScore'],
            'popularity' => $media['popularity'],
            'synonyms' => $media['synonyms'] ?? [],
            'start_date' => $this->formatDate($media['startDate'] ?? null),
            'end_date' => $this->formatDate($media['endDate'] ?? null),
            'relations' => $this->formatRelations($media['relations']['edges'] ?? [])
        ];
    }    /**
     * Clean torrent title for better matching
     */
    private function cleanTorrentTitle($title) {
        $cleaned = $title;
        
        // First, extract the main anime title before any brackets or quality info
        // Look for pattern: "Title SXX" or "Title Season X" at the beginning
        if (preg_match('/^([^[\(]+?)(?:\s+(?:S\d+|Season\s*\d+))?\s*(?:\d{3,4}p|[\[\(])/i', $cleaned, $matches)) {
            $mainTitle = trim($matches[1]);
            
            // Extract season info if present
            $seasonInfo = '';
            if (preg_match('/\b(S\d+|Season\s*\d+)\b/i', $title, $seasonMatches)) {
                $seasonInfo = ' ' . $seasonMatches[1];
            }
            
            // Check if there's a Japanese title in parentheses we should preserve
            $japaneseTitle = '';
            if (preg_match('/\(([^)]*(?:dake|Level|Ken|no|wo|ga|ni|de|to|wa|ka)[^)]*)\)/i', $title, $jpMatches)) {
                // Only keep the Japanese title if it's different from the main title
                $jpTitle = trim($jpMatches[1]);
                $jpTitle = preg_replace('/,\s*(Dual-Audio|Multi-Subs|DUAL).*$/i', '', $jpTitle); // Remove technical info
                if (stripos($mainTitle, $jpTitle) === false && strlen($jpTitle) > 3) {
                    $japaneseTitle = ' ' . $jpTitle;
                }
            }
            
            $cleaned = $mainTitle . $seasonInfo . $japaneseTitle;
        } else {
            // Fallback: clean the title step by step
            // Remove quality and technical info
            $patterns = [
                '/\b\d{3,4}p\b/i', // Remove resolution
                '/\b(WEB-DL|HDTV|BluRay|Blu-ray|BD-?Rip|DVD-?Rip)\b/i', // Remove source
                '/\bx26[45]\b/i', // Remove codec
                '/\bHEVC\b/i', // Remove codec  
                '/\bH\.?26[45]\b/i', // Remove video codec
                '/\b(AAC|FLAC|MP3)[\d.]*\b/i', // Remove audio codec
                '/\b(DUAL|Dual)(-?Audio)?\b/i', // Remove dual audio
                '/\b(Multi-?Subs?|English-?Sub)\b/i', // Remove subtitle info
                '/\b(Batch|Complete|Collection)\b/i', // Remove batch indicators
                '/\b[A-Z]{2,4}-[A-Z]+\b/', // Remove release groups like CR-VARYG
                '/\[[^\]]*\]/', // Remove [SubGroup] brackets
                '/\b(Episode|Ep|E)\s*\d+\b/i', // Remove episode numbers
                '/\b\d+\.\d+\s?(GB|MB)\b/i', // Remove file sizes
            ];
            
            foreach ($patterns as $pattern) {
                $cleaned = preg_replace($pattern, ' ', $cleaned);
            }
            
            // Clean parentheses content but preserve Japanese titles
            $cleaned = preg_replace_callback('/\(([^)]*)\)/', function($matches) {
                $content = trim($matches[1]);
                // Keep if it contains Japanese anime terms
                if (preg_match('/\b(dake|Level|Ken|no|wo|ga|ni|de|to|wa|ka|Ore)\b/i', $content)) {
                    // Remove technical info from Japanese title
                    $content = preg_replace('/,\s*(Dual-Audio|Multi-Subs|DUAL).*$/i', '', $content);
                    return ' ' . trim($content);
                }
                return '';
            }, $cleaned);
        }
        
        // Final cleanup
        $cleaned = preg_replace('/\s+/', ' ', $cleaned); // Multiple spaces to single
        $cleaned = trim($cleaned);
        
        return $cleaned;
    }
      /**
     * Find best match from search results
     */
    private function findBestMatch($torrentTitle, $searchResults) {
        $bestMatch = null;
        $highestSimilarity = 0;
        
        $cleanTorrentTitle = strtolower($this->cleanTorrentTitle($torrentTitle));
        
        // Extract season info from torrent title
        $torrentSeason = $this->extractSeasonFromTitle($torrentTitle);
        
        foreach ($searchResults as $result) {
            $similarities = [];
            
            // Compare with romaji title
            if (!empty($result['title']['romaji'])) {
                $similarities[] = $this->calculateSimilarity($cleanTorrentTitle, strtolower($result['title']['romaji']));
            }
            
            // Compare with english title
            if (!empty($result['title']['english'])) {
                $similarities[] = $this->calculateSimilarity($cleanTorrentTitle, strtolower($result['title']['english']));
            }
            
            // Compare with synonyms
            foreach ($result['synonyms'] as $synonym) {
                $similarities[] = $this->calculateSimilarity($cleanTorrentTitle, strtolower($synonym));
            }
            
            $maxSimilarity = !empty($similarities) ? max($similarities) : 0;
            
            // Boost similarity if season matches
            if ($torrentSeason && $this->matchesSeason($result, $torrentSeason)) {
                $maxSimilarity *= 1.3; // 30% boost for season match
            }
            
            // Boost if it's an exact base title match
            $baseTorrentTitle = $this->extractBaseTitleFromTorrent($torrentTitle);
            $baseAnimeTitle = $this->extractBaseTitleFromAnime($result);
            
            if ($this->calculateSimilarity(strtolower($baseTorrentTitle), strtolower($baseAnimeTitle)) > 0.85) {
                $maxSimilarity *= 1.2; // 20% boost for base title match
            }
            
            if ($maxSimilarity > $highestSimilarity) {
                $highestSimilarity = $maxSimilarity;
                $bestMatch = $result;
            }
        }
          // Lower threshold to 0.4 for better matches, and allow season 2 matches even without perfect season matching
        if ($highestSimilarity > 0.4) {
            return $bestMatch;
        }
        
        // If no good match found, try a very lenient search for base titles
        $baseTorrentTitle = $this->extractBaseTitleFromTorrent($torrentTitle);
        foreach ($searchResults as $result) {
            $baseAnimeTitle = $this->extractBaseTitleFromAnime($result);
            if ($this->calculateSimilarity(strtolower($baseTorrentTitle), strtolower($baseAnimeTitle)) > 0.7) {
                return $result;
            }
        }
        
        return null;
    }
    
    /**
     * Extract season number from torrent title
     */
    private function extractSeasonFromTitle($title) {
        if (preg_match('/\b(?:S|Season\s*)(\d+)\b/i', $title, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }
    
    /**
     * Check if anime result matches the season
     */
    private function matchesSeason($result, $season) {
        $title = strtolower($result['title']['english'] ?? $result['title']['romaji'] ?? '');
        
        // Check for season number in title
        if (preg_match('/\b(?:season\s*|s)(\d+)\b/i', $title, $matches)) {
            return (int)$matches[1] === $season;
        }
        
        // Season 1 usually doesn't have season indicators
        if ($season === 1 && !preg_match('/\b(?:season|s\d+|2nd|3rd|second|third)\b/i', $title)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Extract base title from torrent (without season/episode info)
     */
    private function extractBaseTitleFromTorrent($title) {
        $base = $title;
        $base = preg_replace('/\b(S\d+|Season\s*\d+)\b/i', '', $base);
        $base = preg_replace('/\b(E\d+|Episode\s*\d+)\b/i', '', $base);
        $base = preg_replace('/\[.*?\]/', '', $base);
        $base = preg_replace('/\(.*?\)/', '', $base);
        $base = preg_replace('/\b\d{3,4}p\b/', '', $base);
        $base = trim(preg_replace('/\s+/', ' ', $base));
        return $base;
    }
    
    /**
     * Extract base title from anime result
     */
    private function extractBaseTitleFromAnime($result) {
        $title = $result['title']['english'] ?? $result['title']['romaji'] ?? '';
        $base = preg_replace('/\b(Season\s*\d+|Part\s*\d+|2nd|3rd|Second|Third)\b/i', '', $title);
        $base = preg_replace('/[-:]\s*\w+/', '', $base); // Remove subtitle after colon/dash
        return trim($base);
    }
    
    /**
     * Calculate similarity between two strings
     */
    private function calculateSimilarity($str1, $str2) {
        // Use multiple similarity algorithms and take the best result
        $levenshtein = 1 - (levenshtein($str1, $str2) / max(strlen($str1), strlen($str2)));
        $jaro = $this->jaroWinkler($str1, $str2);
        
        similar_text($str1, $str2, $percent);
        $similarText = $percent / 100;
        
        return max($levenshtein, $jaro, $similarText);
    }
    
    /**
     * Calculate confidence score for a match
     */
    private function calculateConfidence($torrentTitle, $anilistMatch) {
        $cleanTorrentTitle = strtolower($this->cleanTorrentTitle($torrentTitle));
        
        $similarities = [];
        
        if (!empty($anilistMatch['title']['romaji'])) {
            $similarities[] = $this->calculateSimilarity($cleanTorrentTitle, strtolower($anilistMatch['title']['romaji']));
        }
        
        if (!empty($anilistMatch['title']['english'])) {
            $similarities[] = $this->calculateSimilarity($cleanTorrentTitle, strtolower($anilistMatch['title']['english']));
        }
        
        foreach ($anilistMatch['synonyms'] as $synonym) {
            $similarities[] = $this->calculateSimilarity($cleanTorrentTitle, strtolower($synonym));
        }
        
        return !empty($similarities) ? max($similarities) : 0;
    }
    
    /**
     * Jaro-Winkler similarity algorithm
     */    private function jaroWinkler($str1, $str2, $prefix = 0.1) {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if ($len1 === 0) return $len2 === 0 ? 1 : 0;
        if ($len2 === 0) return 0;
        
        // Convert strings to character arrays to avoid string offset warnings
        $str1Chars = str_split($str1);
        $str2Chars = str_split($str2);
        
        $matchWindow = floor(max($len1, $len2) / 2) - 1;
        if ($matchWindow < 0) $matchWindow = 0;
        
        $str1Matches = array_fill(0, $len1, false);
        $str2Matches = array_fill(0, $len2, false);
        
        $matches = 0;
        $transpositions = 0;
        
        // Find matches
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchWindow);
            $end = min($i + $matchWindow + 1, $len2);
            
            for ($j = $start; $j < $end; $j++) {
                if ($str2Matches[$j] || $str1Chars[$i] !== $str2Chars[$j]) continue;
                
                $str1Matches[$i] = true;
                $str2Matches[$j] = true;
                $matches++;
                break;
            }
        }
        
        if ($matches === 0) return 0;
        
        // Find transpositions
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (!$str1Matches[$i]) continue;
            
            while (!$str2Matches[$k]) $k++;
            
            if ($str1Chars[$i] !== $str2Chars[$k]) $transpositions++;
            $k++;
        }
        
        $jaro = ($matches / $len1 + $matches / $len2 + ($matches - $transpositions / 2) / $matches) / 3;
        
        // Jaro-Winkler
        $prefixLength = 0;
        for ($i = 0; $i < min($len1, $len2, 4); $i++) {
            if ($str1Chars[$i] === $str2Chars[$i]) {
                $prefixLength++;
            } else {
                break;
            }
        }
        
        return $jaro + ($prefixLength * $prefix * (1 - $jaro));
    }
    
    /**
     * Clean HTML description
     */
    private function cleanDescription($description) {
        if (empty($description)) return '';
        
        // Remove HTML tags
        $cleaned = strip_tags($description);
        
        // Remove extra whitespace
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        return trim($cleaned);
    }
    
    /**
     * Format date array to string
     */
    private function formatDate($dateArray) {
        if (!$dateArray || !isset($dateArray['year'])) {
            return null;
        }
        
        $year = $dateArray['year'];
        $month = $dateArray['month'] ?? 1;
        $day = $dateArray['day'] ?? 1;
        
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    
    /**
     * Format relations
     */
    private function formatRelations($relations) {
        $formatted = [];
        
        foreach ($relations as $relation) {
            $formatted[] = [
                'id' => $relation['node']['id'],
                'title' => $relation['node']['title']['romaji'] ?? $relation['node']['title']['english'] ?? '',
                'type' => strtolower($relation['relationType'] ?? '')
            ];
        }
        
        return $formatted;
    }
}
?>
