<?php
// Turn off error reporting for users (but still log errors internally)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Custom error handler for exceptions
set_exception_handler(function ($e) {
    // Log the actual error for debugging
    error_log("Exception: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " on line " . $e->getLine());

    // Set HTTP response code
    http_response_code(500);

    // Show custom error page
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>500 - Internal Server Error</title>
    <style>
        body {
            background-color: #f4f4f4;
            font-family: Arial, sans-serif;
            color: #333;
            text-align: center;
            padding: 100px;
        }
        .error-box {
            background: white;
            display: inline-block;
            padding: 40px 60px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 48px;
            margin: 0;
            color: #d9534f;
        }
        p {
            font-size: 18px;
            margin-top: 10px;
        }
        .code {
            margin-top: 30px;
            font-size: 14px;
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>500</h1>
        <p>Oops! Something went wrong on our end.</p>
        <p>Please try again later or contact support.</p>
        <div class="code">Error Code: 500 - Internal Server Error</div>
    </div>
</body>
</html>
HTML;
    exit;
});

// Simulate an error (for testing)
throw new Exception("Simulated server failure.");  // Remove in production
?>
