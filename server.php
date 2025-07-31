<?php
// server.php - A simple router for the PHP built-in web server

// Get the requested URI path
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = realpath(__DIR__ . $requestUri);

// Check if the requested path corresponds to a file that exists
if ($filePath && is_file($filePath)) {
    // Prevent directory traversal attacks
    if (strpos($filePath, __DIR__ . DIRECTORY_SEPARATOR) === 0) {
        // If it's a PHP file (like predict.php), include it
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
            require $filePath;
        } else {
            // For other static files (HTML, CSS, JS), let the built-in server handle it
            // This is crucial for serving your HTML, CSS, and JS files directly
            return false;
        }
    } else {
        // Requested file is outside the document root, return 404
        http_response_code(404);
        echo "404 Not Found";
    }
} else {
    // If no specific file is requested (e.g., just /), serve the main HTML file
    // This makes http://localhost:8000/ load your main page
    require __DIR__ . '/index.html';
}
?>