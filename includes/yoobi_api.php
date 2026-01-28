<?php
/**
 * Yoobi API Client
 *
 * Implements OAuth 2.0 Client Credentials authentication and provides
 * methods for interacting with the Yoobi API.
 *
 * Configuration: Add these constants to your .env.php file:
 *   define('YOOBI_CLIENT_ID', 'your-client-id');
 *   define('YOOBI_CLIENT_SECRET', 'your-client-secret');
 */

class YoobiAPI {
    // Default URLs - can be overridden via .env.php
    private const DEFAULT_TOKEN_URL = 'https://api.yoobi.nl/yoobioauth2/token';
    private const DEFAULT_API_BASE_URL = 'https://api.yoobi.nl/api/v2';

    private $tokenUrl;
    private $apiBaseUrl;
    private $clientId;
    private $clientSecret;
    private $configured = false;
    private $accessToken = null;
    private $tokenExpiry = null;
    private $lastError = null;

    public function __construct() {
        if (defined('YOOBI_CLIENT_ID') && defined('YOOBI_CLIENT_SECRET')
            && YOOBI_CLIENT_ID !== 'your-client-id') {
            $this->clientId = YOOBI_CLIENT_ID;
            $this->clientSecret = YOOBI_CLIENT_SECRET;
            $this->configured = true;

            // Allow custom base URL (e.g., https://yourcompany.yoobi.nl/api/v2)
            if (defined('YOOBI_API_URL')) {
                $this->apiBaseUrl = rtrim(YOOBI_API_URL, '/');
            } else {
                $this->apiBaseUrl = self::DEFAULT_API_BASE_URL;
            }

            // Allow custom token URL
            if (defined('YOOBI_TOKEN_URL')) {
                $this->tokenUrl = YOOBI_TOKEN_URL;
            } else {
                $this->tokenUrl = self::DEFAULT_TOKEN_URL;
            }
        }
    }

    /**
     * Check if Yoobi is configured
     */
    public function isConfigured(): bool {
        return $this->configured;
    }

    /**
     * Get the last error message
     */
    public function getLastError(): ?string {
        return $this->lastError;
    }

    /**
     * Obtain an access token using OAuth 2.0 Client Credentials flow
     */
    private function authenticate(): bool {
        if (!$this->configured) {
            $this->lastError = 'Yoobi API is not configured';
            return false;
        }

        // Check if we already have a valid token
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return true;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret
            ]),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->lastError = "cURL error: $curlError";
            return false;
        }

        if ($httpCode !== 200) {
            $this->lastError = "Authentication failed with HTTP $httpCode: $response";
            return false;
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['access_token'])) {
            $this->lastError = "Invalid token response: $response";
            return false;
        }

        $this->accessToken = $data['access_token'];
        // Set token expiry (default 1 hour if not specified, with 60 second buffer)
        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
        $this->tokenExpiry = time() + $expiresIn - 60;

        return true;
    }

    /**
     * Make an authenticated API request
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint (e.g., '/employees')
     * @param array|null $data Request body for POST/PUT requests
     * @param array $queryParams Query parameters for GET requests
     * @return array|null Response data or null on error
     */
    public function request(string $method, string $endpoint, ?array $data = null, array $queryParams = []): ?array {
        if (!$this->authenticate()) {
            return null;
        }

        $url = $this->apiBaseUrl . $endpoint;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $ch = curl_init();
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $curlOpts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $curlOpts[CURLOPT_POST] = true;
                if ($data !== null) {
                    $curlOpts[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'PUT':
                $curlOpts[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($data !== null) {
                    $curlOpts[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'DELETE':
                $curlOpts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            case 'GET':
            default:
                break;
        }

        curl_setopt_array($ch, $curlOpts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->lastError = "cURL error: $curlError";
            return null;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = '';
            if (isset($result['error']['message']) && is_string($result['error']['message'])) {
                $errorMsg = $result['error']['message'];
            } elseif (isset($result['message']['msg']) && is_string($result['message']['msg'])) {
                // Yoobi validation error format
                $errorMsg = $result['message']['msg'];
                if (isset($result['message']['errors']) && is_array($result['message']['errors'])) {
                    $errorMsg .= ' - ' . json_encode($result['message']['errors']);
                }
            } elseif (isset($result['message']) && is_string($result['message'])) {
                $errorMsg = $result['message'];
            } elseif (is_array($result)) {
                $errorMsg = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } elseif (!empty($response)) {
                $errorMsg = $response;
            } else {
                $errorMsg = '(empty response)';
            }
            $this->lastError = "API error (HTTP $httpCode): $errorMsg [URL: $url]";
            return null;
        }

        $this->lastError = null;
        return $result;
    }

    /**
     * Get insight report data
     *
     * @param array $query The insight report query
     * @return array|null Report data or null on error
     */
    public function getInsightReport(array $query): ?array {
        return $this->request('POST', '/insightReport', $query);
    }

    /**
     * Refresh an insight source to create a static copy of the data
     * This must be called before querying with insightReport if the source hasn't been refreshed recently
     *
     * @param string $sourceName The insight source name (e.g., 'declarations')
     * @return array|null Refresh result or null on error
     */
    public function refreshInsightSource(string $sourceName = 'declarations'): ?array {
        return $this->request('POST', '/insightSourceRefresh', ['sourceName' => $sourceName]);
    }

    /**
     * Get logged hours for a specific project within a date range using the insight API
     * Assumes the insight source is already refreshed.
     *
     * @param string $projectName Project name in Yoobi (used for filtering)
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return float Total logged hours
     */
    public function getProjectLoggedHours(string $projectName, string $startDate, string $endDate): float {
        if (!$this->configured) {
            return 0.0;
        }

        $query = [
            'type' => 'select',
            'index' => 'employeeelastic',
            'query' => [
                'aggs' => [
                    'by' => [
                        'rows' => [
                            ['uniqueName' => 'f_projectcode_f'],
                            ['uniqueName' => 'f_activitycode_f']
                        ]
                    ],
                    'values' => [
                        ['func' => 'sum', 'field' => ['uniqueName' => 'f_decltime_f']]
                    ]
                ],
                'filter' => [
                    [
                        'field' => ['uniqueName' => 'f_project_f'],
                        'include' => [['member' => $projectName]]
                    ]
                ]
            ],
            'querytype' => 'select',
            'page' => 0,
            'requestHeaders' => new \stdClass(),
            'periodstart' => $startDate,
            'periodend' => $endDate,
            'lang' => 'nl_NL'
        ];

        $report = $this->getInsightReport($query);
        if (!$report || !isset($report['DATA'])) {
            return 0.0;
        }

        // Find the Uren column index
        $columns = $report['COLUMNS'] ?? [];
        $hoursIdx = array_search('Uren', $columns);
        if ($hoursIdx === false) {
            return 0.0;
        }

        // Sum all hours from the DATA array
        $totalHours = 0.0;
        foreach ($report['DATA'] as $row) {
            if (isset($row[$hoursIdx]) && is_numeric($row[$hoursIdx])) {
                $totalHours += (float)$row[$hoursIdx];
            }
        }

        return $totalHours;
    }
}
