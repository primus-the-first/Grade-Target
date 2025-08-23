<?php
// Enable error reporting for debugging during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

header('Content-Type: application/json');

// Basic validation
if (!isset($_POST['question']) || empty(trim($_POST['question']))) {
    echo json_encode(['success' => false, 'error' => 'Question is missing.']);
    exit;
}

$question = $_POST['question'];
$learningLevel = $_POST['learningLevel'] ?? 'Understanding';
$contextText = '';

if (isset($_FILES['contextFile']) && $_FILES['contextFile']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['contextFile']['tmp_name'];
    $fileName = $_FILES['contextFile']['name'];
    $fileSize = $_FILES['contextFile']['size'];
    $fileType = $_FILES['contextFile']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    $allowedExtensions = ['pdf', 'docx', 'txt'];

    if (!in_array($fileExtension, $allowedExtensions)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PDF, DOCX, and TXT files are accepted.']);
        exit;
    }

    // Reading the file content
    try {
        if ($fileExtension === 'txt') {
            $contextText = file_get_contents($fileTmpPath);
        } elseif ($fileExtension === 'pdf') {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($fileTmpPath);
            $contextText = $pdf->getText();
        } elseif ($fileExtension === 'docx') {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($fileTmpPath);
            $text = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . ' ';
                    }
                }
            }
            $contextText = $text;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error processing file: ' . $e->getMessage()]);
        exit;
    }
}

function formatResponse($text) {
    // Convert markdown-like syntax to HTML
    $text = htmlspecialchars($text);

    // Headers
    $text = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $text);
    $text = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $text);

    // Bold and Italic
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);

    // Unordered lists
    $text = preg_replace('/^\* (.*)$/m', '<ul><li>$1</li></ul>', $text);
    $text = preg_replace('/<\/ul>\n<ul>/', '', $text);

    // Ordered lists
    $text = preg_replace('/^[0-9]+\. (.*)$/m', '<ol><li>$1</li></ol>', $text);
    $text = preg_replace('/<\/ol>\n<ol>/', '', $text);

    // Paragraphs
    $text = '<p>' . str_replace("\n\n", "</p><p>", $text) . '</p>';
    $text = str_replace("\n", "<br>", $text);

    return $text;
}

try {
    // NOTE: For a production environment, use environment variables or a secure configuration file.
    define('GEMINI_API_KEY', 'AIzaSyCf3IWMndg1K6uIwT6kYDDtLrGi2-PyWIo');

    if (GEMINI_API_KEY === 'YOUR_ACTUAL_GEMINI_API_KEY_HERE' || empty(GEMINI_API_KEY)) {
        throw new Exception('Gemini API Key is not configured on the server.');
    }

    // Construct the prompt for the AI
    $prompt = "You are an AI Tutor. A student has asked a question with a specific learning level in mind, based on Bloom's Taxonomy. Please provide a comprehensive answer that is appropriate for the selected level.\n\n";
    $prompt .= "Student's Question: " . $question . "\n\n";
    $prompt .= "Desired Learning Level: " . $learningLevel . "\n\n";

    if (!empty($contextText)) {
        $prompt .= "The student has provided the following context from a file. Use this information to tailor your answer:\n---\n" . $contextText . "\n---\n\n";
    }

    $prompt .= "Please structure your response clearly. If the learning level is 'Remembering', focus on definitions and facts. If it's 'Understanding', explain the concepts. If 'Applying', provide examples or solve a problem. For 'Analyzing', break down the components and relationships. For 'Evaluating', ask the student to critique or make a judgment. For 'Creating', challenge the student to produce something new based on the information.";

    // Call the Gemini API
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=" . GEMINI_API_KEY;
    $payload = json_encode(["contents" => [[ "role" => "user", "parts" => [[ "text" => $prompt ]]]]]); 
    $retries = 0;
    $max_retries = 5;
    $delay = 1;

    while ($retries < $max_retries) {
        if (!function_exists('curl_init')) {
            throw new Exception('PHP cURL extension is not enabled. Please enable it in your php.ini.');
        }

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            error_log("cURL Error: " . $curl_error);
            $retries++;
            if ($retries < $max_retries) {
                sleep($delay);
                $delay *= 2;
                continue;
            } else {
                throw new Exception('Failed to connect to AI service after multiple retries: ' . $curl_error);
            }
        }
        if ($http_status === 429) {
            $retries++;
            if ($retries < $max_retries) {
                sleep($delay);
                $delay *= 2;
                continue;
            } else {
                throw new Exception('AI service rate limit exceeded after multiple retries.');
            }
        }
        if ($http_status !== 200) {
            error_log("Gemini API HTTP Error: " . $http_status . " - " . $response);
            throw new Exception('AI service returned an error: HTTP ' . $http_status . ' - ' . substr($response, 0, 200) . '...');
        }

        $responseData = json_decode($response, true);
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            $answer = $responseData['candidates'][0]['content']['parts'][0]['text'];
            $formattedAnswer = formatResponse($answer);
            echo json_encode(['success' => true, 'answer' => $formattedAnswer]);
            exit();
        } else {
            error_log("Gemini API: Unexpected response structure - " . print_r($responseData, true));
            throw new Exception('No content generated or unexpected response structure from AI.');
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
}
