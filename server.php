<?php
/**
 * Simple PHP Development Server Router
 * 
 * This file helps serve the Grade Target CGPA Calculator
 * when using PHP's built-in development server.
 * 
 * Usage: php -S localhost:8000 server.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Handle static files
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    // Serve static files with appropriate headers
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'html' => 'text/html',
        'htm' => 'text/html',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'ttf' => 'font/ttf',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2'
    ];
    
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    
    return false; // Let PHP serve the file
}

// Handle API routes
if ($uri === '/calculate.php' || $uri === '/api/calculate') {
    include __DIR__ . '/calculate.php';
    return true;
}

if ($uri === '/predict.php' || $uri === '/api/predict') {
    include __DIR__ . '/predict.php';
    return true;
}

// Handle root and default routes
if ($uri === '/' || $uri === '/index.html') {
    include __DIR__ . '/index.html';
    return true;
}

// Handle 404 for unknown routes
http_response_code(404);
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>404 - Page Not Found | Grade Target</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            text-align: center;
            padding: 50px 20px;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .error-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        h1 {
            font-size: 4rem;
            margin: 0 0 20px 0;
            font-weight: 700;
        }
        h2 {
            font-size: 1.5rem;
            margin: 0 0 20px 0;
            font-weight: 400;
        }
        p {
            font-size: 1.1rem;
            margin: 0 0 30px 0;
            opacity: 0.9;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #fff;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class='error-container'>
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page you're looking for doesn't exist or has been moved.</p>
        <a href='/' class='btn'>← Back to Calculator</a>
    </div>
</body>
</html>";
return true;
?>