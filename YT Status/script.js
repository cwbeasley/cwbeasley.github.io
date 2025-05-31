document.addEventListener('DOMContentLoaded', function() {
    const channelId = 'UCVQiTaXHIVN2jExdrKbogYA'; // Your Channel ID

    const statusDisplay = document.getElementById('live-status-display');
    const iframe = document.getElementById('youtube-live-embed');
    const refreshButton = document.getElementById('refresh-status-button');

    // Set the iframe source initially (it will auto-load the current live/upcoming for the channel)
    iframe.src = `https://www.youtube.com/embed/live_stream?channel=${channelId}`;

    function fetchAndUpdateStreamStatus() {
        statusDisplay.textContent = 'Status: Checking...';
        statusDisplay.className = ''; // Reset classes

        fetch(`get_youtube_status.php?channelId=${channelId}`) // API key is NOT sent from here
            .then(response => {
                if (!response.ok) {
                    // Try to parse error response from PHP if possible
                    return response.json().then(errorData => {
                        throw new Error(`HTTP error! status: ${response.status}, message: ${errorData.message || 'Unknown server error'}`);
                    }).catch(() => {
                        // Fallback if error response is not JSON
                        throw new Error(`HTTP error! status: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                updateStatusDisplay(data);
            })
            .catch(error => {
                console.error('Error fetching stream status:', error);
                // Ensure error.message is displayed if available
                updateStatusDisplay({ status: 'ERROR', message: error.message || 'Could not fetch status.' });
            });
    }

    function updateStatusDisplay(data) {
        let statusText = `Status: ${data.status}`;
        statusDisplay.className = ''; // Reset classes

        switch (data.status) {
            case 'LIVE':
                statusText = `Status: LIVE - Playing: ${data.title || 'Live Stream'}`;
                statusDisplay.classList.add('status-live');
                break;
            case 'UPCOMING':
                statusText = `Status: UPCOMING - ${data.title || 'Upcoming Stream'}`;
                if (data.scheduledStartTime) {
                    statusText += ` (Scheduled: ${new Date(data.scheduledStartTime).toLocaleString()})`;
                }
                statusDisplay.classList.add('status-upcoming');
                break;
            case 'OFFLINE':
                statusText = 'Status: OFFLINE - No current or upcoming public stream.';
                statusDisplay.classList.add('status-offline');
                break;
            case 'ERROR':
                statusText = `Status: ERROR - ${data.message || 'An unknown error occurred.'}`;
                statusDisplay.classList.add('status-error');
                break;
            default:
                statusText = `Status: Unknown (${data.status || 'N/A'})`;
                statusDisplay.classList.add('status-offline'); // Default to offline appearance
        }
        statusDisplay.textContent = statusText;
    }

    // Fetch status on initial load
    fetchAndUpdateStreamStatus();

    // Add event listener to the refresh button
    if (refreshButton) {
        refreshButton.addEventListener('click', fetchAndUpdateStreamStatus);
    }

    // Automatically poll for status changes every 30 seconds
    setInterval(fetchAndUpdateStreamStatus, 30000); // 30000 milliseconds = 30 seconds
});