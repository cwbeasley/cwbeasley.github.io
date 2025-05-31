<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For development; restrict in production

// --- CONFIGURATION ---
$apiKey = 'AIzaSyDwCheNUNffXxXRZgHUq8oJDfqdFXGcXfE'; // YOUR YOUTUBE DATA API v3 KEY
// IMPORTANT: In a production environment, store your API key more securely!
// For example, use an environment variable: $apiKey = getenv('YOUTUBE_API_KEY');
// Or include it from a file outside the web root.

$channelId = isset($_GET['channelId']) ? $_GET['channelId'] : null;

if (empty($apiKey)) {
    echo json_encode(['status' => 'ERROR', 'message' => 'API key is not configured on the server.']);
    exit;
}

if (empty($channelId)) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Channel ID not provided.']);
    exit;
}

function fetchYouTubeAPI($url) {
    $context = stream_context_create([
        'http' => [
            'ignore_errors' => true // Allows reading error response body
        ]
    ]);
    $response = @file_get_contents($url, false, $context); // Use @ to suppress warnings on failure, handled by check below
    if ($response === false) {
        // Could be a network issue, DNS problem, or server misconfiguration
        $error = error_get_last();
        return ['error' => true, 'message' => 'Failed to connect to YouTube API: ' . ($error['message'] ?? 'Unknown connection error')];
    }

    $data = json_decode($response, true);

    // Check for YouTube API specific errors in the response
    if (isset($data['error'])) {
        return ['error' => true, 'message' => 'YouTube API Error: ' . ($data['error']['message'] ?? 'Unknown API error'), 'details' => $data['error']];
    }
    
    // Check for non-200 HTTP status codes that might not be caught by $data['error']
    // file_get_contents with ignore_errors populates $http_response_header
    if (isset($http_response_header)) {
        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
        if (isset($match[1]) && $match[1] >= 400) {
             return ['error' => true, 'message' => 'YouTube API HTTP Error: ' . $match[1] . ' - ' . ($data['error']['message'] ?? 'See details'), 'details' => $data];
        }
    }

    return $data;
}

$output = ['status' => 'OFFLINE']; // Default status

// 1. Check for LIVE streams
$liveApiUrl = sprintf(
    'https://studio.youtube.com/channel/UCVQiTaXHIVN2jExdrKbogYA/livestreaming1?key=%s&channelId=%s&part=snippet&type=video&eventType=live&maxResults=1',
    $apiKey,
    $channelId
);

$liveData = fetchYouTubeAPI($liveApiUrl);

if (isset($liveData['error'])) {
    echo json_encode(['status' => 'ERROR', 'message' => $liveData['message'], 'details' => $liveData['details'] ?? null]);
    exit;
}

if (!empty($liveData['items']) && isset($liveData['items'][0]['snippet']['liveBroadcastContent']) && $liveData['items'][0]['snippet']['liveBroadcastContent'] == 'live') {
    $video = $liveData['items'][0];
    $output = [
        'status' => 'LIVE',
        'videoId' => $video['id']['videoId'],
        'title' => $video['snippet']['title'],
        'publishedAt' => $video['snippet']['publishedAt']
    ];
} else {
    // 2. If no LIVE stream, check for UPCOMING streams
    $upcomingApiUrl = sprintf(
        'https://studio.youtube.com/channel/UCVQiTaXHIVN2jExdrKbogYA/livestreaming2&order=date&maxResults=1',
        $apiKey,
        $channelId
    );
    // Note: order=date attempts to get the soonest upcoming if multiple exist.

    $upcomingData = fetchYouTubeAPI($upcomingApiUrl);

    if (isset($upcomingData['error'])) {
        // If checking for live was successful, but upcoming failed, we might still want to report based on live check.
        // However, for simplicity here, if upcoming check fails, we error out.
        // A more robust solution might differentiate this.
        echo json_encode(['status' => 'ERROR', 'message' => 'Error fetching upcoming streams: ' . $upcomingData['message'], 'details' => $upcomingData['details'] ?? null]);
        exit;
    }

    if (!empty($upcomingData['items']) && isset($upcomingData['items'][0]['snippet']['liveBroadcastContent']) && $upcomingData['items'][0]['snippet']['liveBroadcastContent'] == 'upcoming') {
        $video = $upcomingData['items'][0];
        $output = [
            'status' => 'UPCOMING',
            'videoId' => $video['id']['videoId'],
            'title' => $video['snippet']['title'],
            'publishedAt' => $video['snippet']['publishedAt'],
            'scheduledStartTime' => $video['snippet']['scheduledStartTime'] ?? null
        ];
    }
    // If neither live nor upcoming, $output remains ['status' => 'OFFLINE']
}

echo json_encode($output);
?>