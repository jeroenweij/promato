<?php
/**
 * OpenProject API Helper
 *
 * Configuration: Add these constants to your .env.php file:
 *   define('OPENPROJECT_URL', 'https://your-openproject-instance.com');
 *   define('OPENPROJECT_API_KEY', 'your-api-key-here');
 */

class OpenProjectAPI {
    private $baseUrl;
    private $apiKey;

    public function __construct() {
        if (!defined('OPENPROJECT_URL') || !defined('OPENPROJECT_API_KEY')) {
            throw new Exception('OpenProject configuration missing. Add OPENPROJECT_URL and OPENPROJECT_API_KEY to .env.php');
        }
        $this->baseUrl = rtrim(OPENPROJECT_URL, '/');
        $this->apiKey = OPENPROJECT_API_KEY;
    }

    /**
     * Make an API request to OpenProject
     */
    private function request($endpoint, $params = []) {
        $url = $this->baseUrl . '/api/v3' . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/hal+json'
            ],
            CURLOPT_USERPWD => 'apikey:' . $this->apiKey,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: $error");
        }

        if ($httpCode >= 400) {
            throw new Exception("API error (HTTP $httpCode): $response");
        }

        return json_decode($response, true);
    }

    /**
     * Get all projects from OpenProject
     */
    public function getProjects() {
        $result = $this->request('/projects', ['pageSize' => 1000]);
        return $result['_embedded']['elements'] ?? [];
    }

    /**
     * Get a single project by identifier (string slug)
     * Returns null if not found
     */
    public function getProjectByIdentifier($identifier) {
        try {
            return $this->request("/projects/$identifier");
        } catch (Exception $e) {
            // Project not found or other error
            return null;
        }
    }

    /**
     * Find a project by name (case-insensitive)
     * Returns the project if found, null otherwise
     */
    public function findProjectByName($name) {
        $projects = $this->getProjects();
        $nameLower = strtolower(trim($name));

        foreach ($projects as $project) {
            if (strtolower(trim($project['name'])) === $nameLower) {
                return $project;
            }
        }
        return null;
    }

    /**
     * Get versions (sprints) for a project
     * $projectIdOrIdentifier can be numeric ID or string identifier
     */
    public function getVersions($projectIdOrIdentifier) {
        $result = $this->request("/projects/$projectIdOrIdentifier/versions", ['pageSize' => 100]);
        return $result['_embedded']['elements'] ?? [];
    }

    /**
     * Get work packages for a specific version
     */
    public function getWorkPackagesByVersion($versionId) {
        $filters = json_encode([
            ['version' => ['operator' => '=', 'values' => [(string)$versionId]]]
        ]);

        $result = $this->request('/work_packages', [
            'filters' => $filters,
            'pageSize' => 1000
        ]);

        return $result['_embedded']['elements'] ?? [];
    }

    /**
     * Get work packages for a project
     */
    public function getWorkPackagesByProject($projectId) {
        $filters = json_encode([
            ['project' => ['operator' => '=', 'values' => [(string)$projectId]]]
        ]);

        $result = $this->request('/work_packages', [
            'filters' => $filters,
            'pageSize' => 1000
        ]);

        return $result['_embedded']['elements'] ?? [];
    }

    /**
     * Parse ISO 8601 duration (PT2H30M) to hours
     */
    public static function durationToHours($duration) {
        if (empty($duration)) return 0;

        $hours = 0;
        $minutes = 0;

        // Match hours
        if (preg_match('/(\d+)H/', $duration, $matches)) {
            $hours = (int)$matches[1];
        }
        // Match minutes
        if (preg_match('/(\d+)M/', $duration, $matches)) {
            $minutes = (int)$matches[1];
        }
        // Match days (assuming 8h workday)
        if (preg_match('/(\d+)D/', $duration, $matches)) {
            $hours += (int)$matches[1] * 8;
        }

        return $hours + ($minutes / 60);
    }

    /**
     * Get aggregated hours for a version
     * Returns: [estimatedHours, remainingHours, spentHours]
     */
    public function getVersionHoursSummary($versionId) {
        $workPackages = $this->getWorkPackagesByVersion($versionId);

        $estimated = 0;
        $remaining = 0;
        $spent = 0;

        foreach ($workPackages as $wp) {
            $estimated += self::durationToHours($wp['estimatedTime'] ?? null);
            $remaining += self::durationToHours($wp['remainingTime'] ?? null);
            $spent += self::durationToHours($wp['spentTime'] ?? null);
        }

        return [
            'estimated' => $estimated,
            'remaining' => $remaining,
            'spent' => $spent,
            'workPackageCount' => count($workPackages)
        ];
    }
}
