<?php
/**
 * Yoobi API Helper (Placeholder)
 *
 * This API integration is not yet active. Once Yoobi API access is available,
 * implement the methods below to sync logged hours from Yoobi.
 *
 * Configuration: Add these constants to your .env.php file:
 *   define('YOOBI_URL', 'https://your-yoobi-instance.com');
 *   define('YOOBI_API_KEY', 'your-yoobi-api-key-here');
 */

class YoobiAPI {
    private $baseUrl;
    private $apiKey;
    private $configured = false;

    public function __construct() {
        if (defined('YOOBI_URL') && defined('YOOBI_API_KEY')
            && YOOBI_URL !== 'https://your-yoobi-instance.com') {
            $this->baseUrl = rtrim(YOOBI_URL, '/');
            $this->apiKey = YOOBI_API_KEY;
            $this->configured = true;
        }
    }

    /**
     * Check if Yoobi is configured
     */
    public function isConfigured(): bool {
        return $this->configured;
    }

    /**
     * Make an API request to Yoobi
     * TODO: Implement when API becomes available
     */
    private function request($endpoint, $params = []) {
        if (!$this->configured) {
            throw new Exception('Yoobi API is not configured');
        }

        // TODO: Implement actual API call when Yoobi API becomes available
        // Expected structure (adjust based on actual Yoobi API documentation):
        //
        // $url = $this->baseUrl . '/api/v1' . $endpoint;
        // if (!empty($params)) {
        //     $url .= '?' . http_build_query($params);
        // }
        //
        // $ch = curl_init();
        // curl_setopt_array($ch, [
        //     CURLOPT_URL => $url,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_HTTPHEADER => [
        //         'Content-Type: application/json',
        //         'Authorization: Bearer ' . $this->apiKey
        //     ],
        //     CURLOPT_TIMEOUT => 30
        // ]);
        //
        // $response = curl_exec($ch);
        // $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close($ch);
        //
        // return json_decode($response, true);

        throw new Exception('Yoobi API is not yet implemented');
    }

    /**
     * Get logged hours for a project within a date range
     * TODO: Implement when API becomes available
     *
     * @param string $projectIdentifier Project identifier in Yoobi
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return float Total logged hours
     */
    public function getLoggedHours($projectIdentifier, $startDate, $endDate): float {
        if (!$this->configured) {
            return 0.0;
        }

        // TODO: Implement when Yoobi API becomes available
        // This should query Yoobi for all time entries within the date range
        // for the given project and return the sum of hours

        return 0.0;
    }

    /**
     * Get logged hours grouped by sprint/period
     * TODO: Implement when API becomes available
     *
     * @param string $projectIdentifier Project identifier in Yoobi
     * @param array $sprints Array of sprints with startDate and endDate
     * @return array Associative array of sprint identifier => logged hours
     */
    public function getLoggedHoursBySprints($projectIdentifier, $sprints): array {
        if (!$this->configured) {
            return [];
        }

        // TODO: Implement when Yoobi API becomes available
        // For each sprint, query Yoobi for logged hours within that date range

        $result = [];
        foreach ($sprints as $sprint) {
            $result[$sprint['id']] = 0.0;
        }
        return $result;
    }
}
