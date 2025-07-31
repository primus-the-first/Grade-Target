<?php
// Enable error reporting for debugging during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for local development. Restrict this in production.
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS requests for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- SECURE YOUR API KEY ---
// It's best practice to store your API key outside of your web-accessible directory
// and load it from an environment variable or a secure configuration file.
// For local development, you can define it here, but be careful not to commit it to public repos.
// Example:
// define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'YOUR_ACTUAL_GEMINI_API_KEY_HERE');
define('GEMINI_API_KEY', 'AIzaSyCf3IWMndg1K6uIwT6kYDDtLrGi2-PyWIo'); // <<< REPLACE WITH YOUR ACTUAL GEMINI API KEY

if (GEMINI_API_KEY === 'YOURAIzaSyCf3IWMndg1K6uIwT6kYDDtLrGi2-PyWIo' || empty(GEMINI_API_KEY)) {
    echo json_encode(['success' => false, 'error' => 'Gemini API Key is not configured on the server.']);
    exit();
}

// Function to determine class designation (copied from frontend JS for consistency)
function getClassDesignation($cgpa) {
    if ($cgpa >= 3.60) return "First Class Honours";
    if ($cgpa >= 3.00) return "Second Class (Upper Division)";
    if ($cgpa >= 2.50) return "Second Class (Lower Division)";
    if ($cgpa >= 2.00) return "Third Class Division";
    if ($cgpa >= 1.00) return "Pass";
    return "Fail";
}

// Function to calculate final CGPA for a given average on remaining courses
function calculateFinalCGPA($currentCGPA, $completedCredits, $remainingCredits, $avgGradeRemaining) {
    if (($completedCredits + $remainingCredits) == 0) {
        return 0; // Avoid division by zero if no credits at all
    }
    return (($currentCGPA * $completedCredits) + ($avgGradeRemaining * $remainingCredits)) / ($completedCredits + $remainingCredits);
}

// --- Handle different request types ---
$request_type = $_POST['request_type'] ?? 'predict'; // Default to 'predict' if not specified

if ($request_type === 'predict') {
    // --- CGPA Prediction Logic (Existing) ---
    $currentCGPA = isset($_POST['current_cgpa']) ? floatval($_POST['current_cgpa']) : 0;
    $completedCredits = isset($_POST['completed_credits']) ? intval($_POST['completed_credits']) : 0;
    $remainingCredits = isset($_POST['remaining_credits']) ? intval($_POST['remaining_credits']) : 0;
    $programType = $_POST['program_type'] ?? '4-year';
    $topNResults = isset($_POST['top_n_results']) ? intval($_POST['top_n_results']) : 5;

    // Basic validation
    if ($currentCGPA < 0 || $currentCGPA > 4 || $completedCredits < 0 || $remainingCredits < 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid input values. Please check CGPA and credit hours.']);
        exit();
    }

    if ($completedCredits === 0 && $remainingCredits === 0) {
        echo json_encode(['success' => false, 'error' => 'Please enter some credits completed or remaining.']);
        exit();
    }

    // If remaining credits are 0, the program is completed
    if ($remainingCredits === 0) {
        $finalCGPA = ($completedCredits > 0) ? $currentCGPA : 0;
        $finalClass = getClassDesignation($finalCGPA);
        echo json_encode([
            'success' => true,
            'status' => 'completed',
            'message' => "Your program is completed with a final CGPA of " . number_format($finalCGPA, 2) . " and a class of " . $finalClass . "."
        ]);
        exit();
    }

    $classRanges = [
        "First Class Honours" => [3.60, 4.00],
        "Second Class (Upper Division)" => [3.00, 3.59],
        "Second Class (Lower Division)" => [2.50, 2.99],
        "Third Class Division" => [2.00, 2.49],
        "Pass" => [1.00, 1.99],
        "Fail" => [0.00, 0.99]
    ];

    $combinations = [];
    $possibleGrades = [4.0, 3.5, 3.0, 2.5, 2.0, 1.0, 0.0]; // A, B+, B, C+, C, D, E/F

    // Calculate highest possible final CGPA
    $maxPossibleFinalCGPA = calculateFinalCGPA($currentCGPA, $completedCredits, $remainingCredits, 4.0);
    $highestAttainableClass = getClassDesignation($maxPossibleFinalCGPA);

    // Generate scenarios
    foreach ($possibleGrades as $avgGrade) {
        $projectedFinalCGPA = calculateFinalCGPA($currentCGPA, $completedCredits, $remainingCredits, $avgGrade);
        $achievableClass = getClassDesignation($projectedFinalCGPA);

        $combinations[] = [
            'assumed_avg_cgpa_on_remaining_courses' => number_format($avgGrade, 2),
            'projected_final_cgpa' => number_format($projectedFinalCGPA, 2),
            'achievable_class_ucc' => $achievableClass
        ];
    }

    // Sort combinations by projected final CGPA in descending order
    usort($combinations, function($a, $b) {
        return $b['projected_final_cgpa'] <=> $a['projected_final_cgpa'];
    });

    // Add rank
    foreach ($combinations as $key => &$combo) {
        $combo['rank'] = $key + 1;
    }
    unset($combo); // Unset reference

    // Limit to top N results
    $combinations = array_slice($combinations, 0, $topNResults);

    $initialSummary = [
        'current_cgpa' => number_format($currentCGPA, 2),
        'current_class' => getClassDesignation($currentCGPA),
        'credits_completed' => $completedCredits,
        'credits_remaining' => $remainingCredits,
        'max_possible_final_cgpa' => number_format($maxPossibleFinalCGPA, 2),
        'highest_attainable_class' => $highestAttainableClass
    ];

    echo json_encode([
        'success' => true,
        'initial_summary' => $initialSummary,
        'combinations' => $combinations
    ]);

} elseif ($request_type === 'gemini_ai') {
    // --- Gemini AI Logic ---
    $prompt = $_POST['prompt'] ?? '';

    if (empty($prompt)) {
        echo json_encode(['success' => false, 'error' => 'AI prompt cannot be empty.']);
        exit();
    }

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=" . GEMINI_API_KEY;

    $payload = json_encode([
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ]);

    $retries = 0;
    $max_retries = 5;
    $delay = 1; // seconds

    while ($retries < $max_retries) {
        // Check if cURL extension is available
        if (!function_exists('curl_init')) {
            echo json_encode(['success' => false, 'error' => 'PHP cURL extension is not enabled. Please enable it in your php.ini.']);
            exit();
        }

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set a timeout for the request

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            // Log cURL error for debugging
            error_log("cURL Error: " . $curl_error);
            $retries++;
            if ($retries < $max_retries) {
                sleep($delay);
                $delay *= 2; // Exponential backoff
                continue;
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to connect to AI service: ' . $curl_error]);
                exit();
            }
        }

        if ($http_status === 429) { // Too Many Requests
            $retries++;
            if ($retries < $max_retries) {
                sleep($delay);
                $delay *= 2; // Exponential backoff
                continue;
            } else {
                echo json_encode(['success' => false, 'error' => 'AI service rate limit exceeded after multiple retries.']);
                exit();
            }
        }

        if ($http_status !== 200) {
            // Log non-200 HTTP status for debugging
            error_log("Gemini API HTTP Error: " . $http_status . " - " . $response);
            echo json_encode(['success' => false, 'error' => 'AI service returned an error: HTTP ' . $http_status . ' - ' . substr($response, 0, 200) . '...']);
            exit();
        }

        $responseData = json_decode($response, true);

        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            echo json_encode(['success' => true, 'text' => $responseData['candidates'][0]['content']['parts'][0]['text']]);
            exit();
        } else {
            // Log unexpected response structure
            error_log("Gemini API: Unexpected response structure - " . print_r($responseData, true));
            echo json_encode(['success' => false, 'error' => 'No content generated or unexpected response structure from AI.']);
            exit();
        }
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request type.']);
}
?>
