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

try {
    // --- SECURE YOUR API KEY ---
    // NOTE: For a production environment, you should use environment variables or a secure configuration file
    // to store your API key, not hard-code it in the script.
    define('GEMINI_API_KEY', 'AIzaSyCf3IWMndg1K6uIwT6kYDDtLrGi2-PyWIo');

    if (GEMINI_API_KEY === 'YOUR_ACTUAL_GEMINI_API_KEY_HERE' || empty(GEMINI_API_KEY)) {
        throw new Exception('Gemini API Key is not configured on the server.');
    }

    // Function to determine class designation with specific rounding rules
    function getRoundedClassDesignation($cgpa) {
        if ($cgpa >= 3.55) return "First Class Honours";
        if ($cgpa >= 2.95) return "Second Class (Upper Division)";
        if ($cgpa >= 2.45) return "Second Class (Lower Division)";
        if ($cgpa >= 1.95) return "Third Class Division";
        if ($cgpa >= 1.00) return "Pass";
        return "Fail";
    }

    // Function to calculate final CGPA
    function calculateFinalCGPA($currentCGPA, $completedCredits, $remainingCredits, $avgGradeRemaining) {
        if (($completedCredits + $remainingCredits) == 0) {
            return 0;
        }
        return (($currentCGPA * $completedCredits) + ($avgGradeRemaining * $remainingCredits)) / ($completedCredits + $remainingCredits);
    }

    // Define UCC Class Boundaries and Grade Point Map
    $classRanges = [
        "First Class Honours" => [3.55, 4.00],
        "Second Class (Upper Division)" => [2.95, 3.54],
        "Second Class (Lower Division)" => [2.45, 2.94],
        "Third Class Division" => [1.95, 2.44],
        "Pass" => [1.00, 1.94],
        "Fail" => [0.00, 0.99]
    ];
    
    $gradePointsMap = [
        'A' => 4.0, 'B+' => 3.5, 'B' => 3.0, 'C+' => 2.5, 'C' => 2.0
    ];
    
    // Sort grades by points in descending order for recursive generation
    arsort($gradePointsMap);
    
    /**
     * Recursively generates all possible grade combinations for the remaining courses.
     * This function is the core of the new prediction logic.
     *
     * @param array $allCombinations Reference to an array to store valid combinations.
     * @param array $currentCombo The combination being built in the current recursive call.
     * @param int $remainingCourses The number of courses left to assign a grade.
     * @param float $minRequiredAvgGrade The minimum average grade needed to achieve the target class.
     * @param array $gradePointsMap Map of grades to points.
     */
    function generateAllCombinationsRecursive(&$allCombinations, $currentCombo, $remainingCourses, $minRequiredAvgGrade, $gradePointsMap) {
        // Base case: If no courses are left, check if the combo is valid and store it.
        if ($remainingCourses === 0) {
            $totalPoints = 0;
            foreach ($currentCombo as $grade) {
                $totalPoints += $gradePointsMap[$grade];
            }
            $avgPoints = count($currentCombo) > 0 ? $totalPoints / count($currentCombo) : 0;
            
            // Check if this combination meets the minimum average grade required
            if ($avgPoints >= $minRequiredAvgGrade) {
                $allCombinations[] = $currentCombo;
            }
            return;
        }

        // Recursive step: Try each grade for the current course
        foreach ($gradePointsMap as $grade => $points) {
            // To prevent duplicates and keep combinations tidy, only allow grades that are
            // less than or equal to the previous grade.
            if (empty($currentCombo) || $points <= $gradePointsMap[end($currentCombo)]) {
                $newCombo = array_merge($currentCombo, [$grade]);
                generateAllCombinationsRecursive($allCombinations, $newCombo, $remainingCourses - 1, $minRequiredAvgGrade, $gradePointsMap);
            }
        }
    }
    
    // --- Handle different request types ---
    $request_type = $_POST['request_type'] ?? 'predict';

    if ($request_type === 'predict') {
        $currentCGPA = isset($_POST['current_cgpa']) ? floatval($_POST['current_cgpa']) : 0;
        $completedCredits = isset($_POST['completed_credits']) ? intval($_POST['completed_credits']) : 0;
        $remainingCourses = isset($_POST['remaining_courses']) ? intval($_POST['remaining_courses']) : 0;
        $creditsPerCourse = isset($_POST['credits_per_course']) ? intval($_POST['credits_per_course']) : 3; // Default to 3
        $targetClass = isset($_POST['target_class']) ? $_POST['target_class'] : "Any Attainable Class";
        $numPathsToShow = isset($_POST['num_paths_to_show']) ? intval($_POST['num_paths_to_show']) : 6;

        $remainingCredits = $remainingCourses * $creditsPerCourse;

        // Basic validation
        if ($currentCGPA < 0 || $currentCGPA > 4 || $completedCredits < 0 || $remainingCourses < 0 || $creditsPerCourse < 1) {
            throw new Exception('Invalid input values. Please check CGPA and credit hours.');
        }

        if ($completedCredits === 0 && $remainingCredits === 0) {
            throw new Exception('Please enter some credits completed or remaining.');
        }

        // If remaining credits are 0, the program is completed
        if ($remainingCredits === 0) {
            $finalCGPA = ($completedCredits > 0) ? $currentCGPA : 0;
            $finalClass = getRoundedClassDesignation($finalCGPA);
            echo json_encode([
                'success' => true,
                'status' => 'completed',
                'message' => "Your program is completed with a final CGPA of " . number_format($finalCGPA, 2) . " and a class of " . $finalClass . "."
            ]);
            exit();
        }

        // Calculate highest possible final CGPA
        $maxPossibleFinalCGPA = calculateFinalCGPA($currentCGPA, $completedCredits, $remainingCredits, 4.0);
        $highestAttainableClass = getRoundedClassDesignation($maxPossibleFinalCGPA);
        
        $initialSummary = [
            'current_cgpa' => number_format($currentCGPA, 2),
            'current_class' => getRoundedClassDesignation($currentCGPA),
            'credits_completed' => $completedCredits,
            'credits_remaining' => $remainingCredits,
            'max_possible_final_cgpa' => number_format($maxPossibleFinalCGPA, 2),
            'highest_attainable_class' => $highestAttainableClass
        ];

        // Determine which boundaries to consider based on the user's target class
        $boundariesToConsider = [];
        if ($targetClass === "Any Attainable Class") {
            foreach ($classRanges as $class => $range) {
                // Only consider classes that are attainable
                if ($classRanges[$highestAttainableClass][0] <= $range[1]) {
                    $boundariesToConsider[$class] = $range[0];
                }
            }
        } else {
            if (isset($classRanges[$targetClass])) {
                $boundariesToConsider[$targetClass] = $classRanges[$targetClass][0];
            } else {
                throw new Exception('Invalid target class specified.');
            }
        }
        
        // New structure to hold combinations, grouped by the class they achieve
        $gradeCombinationsByClass = [];

        foreach ($boundariesToConsider as $targetClassKey => $boundary) {
            $totalProgramCredits = $completedCredits + $remainingCredits;
            $currentPoints = $currentCGPA * $completedCredits;
            
            $requiredTotalPoints = $boundary * $totalProgramCredits;
            $pointsNeededFuture = $requiredTotalPoints - $currentPoints;
            $minAvgGradePointsFuture = ($remainingCredits > 0) ? $pointsNeededFuture / $remainingCredits : 0;

            if ($minAvgGradePointsFuture > 4.01) { // Check if this class is even attainable with 'A' grades
                continue;
            }

            // Generate all valid grade combinations
            $allCombinations = [];
            generateAllCombinationsRecursive($allCombinations, [], $remainingCourses, $minAvgGradePointsFuture, $gradePointsMap);
            
            // Sort combinations by average grade points in descending order
            usort($allCombinations, function($a, $b) use ($gradePointsMap, $remainingCourses) {
                $totalPointsA = 0;
                foreach($a as $grade) $totalPointsA += $gradePointsMap[$grade];
                $avgA = $totalPointsA / $remainingCourses;

                $totalPointsB = 0;
                foreach($b as $grade) $totalPointsB += $gradePointsMap[$grade];
                $avgB = $totalPointsB / $remainingCourses;

                return $avgB <=> $avgA;
            });
            
            // Select high, mid, and low paths
            $totalValid = count($allCombinations);
            if ($totalValid > 0) {
                // Select unique paths from high, mid, and low ranges
                $paths = [];
                // Add the top few
                for ($i = 0; $i < min($totalValid, $numPathsToShow / 3); $i++) {
                    $paths[] = $allCombinations[$i];
                }
                // Add a few from the middle
                for ($i = floor($totalValid / 2) - floor($numPathsToShow / 3 / 2); $i < min($totalValid, floor($totalValid / 2) + ceil($numPathsToShow / 3 / 2)); $i++) {
                    if (!in_array($allCombinations[$i], $paths)) {
                        $paths[] = $allCombinations[$i];
                    }
                }
                // Add the bottom few
                for ($i = max(0, $totalValid - floor($numPathsToShow / 3)); $i < $totalValid; $i++) {
                    if (!in_array($allCombinations[$i], $paths)) {
                        $paths[] = $allCombinations[$i];
                    }
                }
                $paths = array_unique($paths, SORT_REGULAR);

                $pathsForClass = [];
                foreach ($paths as $path) {
                    $distribution = array_count_values($path);
                    ksort($distribution);
                    
                    $totalPoints = 0;
                    foreach ($distribution as $grade => $count) {
                        $totalPoints += $gradePointsMap[$grade] * $count;
                    }
                    $avgGradeOnRemaining = ($remainingCourses > 0) ? $totalPoints / $remainingCourses : 0;
                    $overallFinalCGPA = calculateFinalCGPA($currentCGPA, $completedCredits, $remainingCredits, $avgGradeOnRemaining);

                    $pathsForClass[] = [
                        'distribution' => $distribution,
                        'avg_grade_on_remaining_courses' => number_format($avgGradeOnRemaining, 2),
                        'overall_final_cgpa_with_this_distribution' => number_format($overallFinalCGPA, 2),
                        'rounded_final_class' => getRoundedClassDesignation($overallFinalCGPA)
                    ];
                }

                if (!empty($pathsForClass)) {
                    $gradeCombinationsByClass[$targetClassKey] = $pathsForClass;
                }
            }
        }
        
        // Check if any combinations were found at all
        if (empty($gradeCombinationsByClass)) {
            echo json_encode([
                'success' => true,
                'status' => 'no_combinations_found',
                'initial_summary' => $initialSummary,
                'message' => 'No realistic combinations found based on your inputs. Try adjusting your current CGPA or remaining credits.'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'initial_summary' => $initialSummary,
                'grade_combinations_by_class' => $gradeCombinationsByClass
            ]);
        }
    } elseif ($request_type === 'gemini_ai') {
        // --- Gemini AI Logic (Unchanged) ---
        $prompt = $_POST['prompt'] ?? '';
        if (empty($prompt)) {
            throw new Exception('AI prompt cannot be empty.');
        }

        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=" . GEMINI_API_KEY;
        $payload = json_encode(["contents" => [["role" => "user", "parts" => [["text" => $prompt]]]]]);
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
                echo json_encode(['success' => true, 'text' => $responseData['candidates'][0]['content']['parts'][0]['text']]);
                exit();
            } else {
                error_log("Gemini API: Unexpected response structure - " . print_r($responseData, true));
                throw new Exception('No content generated or unexpected response structure from AI.');
            }
        }
    } else {
        throw new Exception('Invalid request type.');
    }

} catch (Exception $e) {
    // This is the safety net that ensures a JSON response is always sent
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
}
?>
