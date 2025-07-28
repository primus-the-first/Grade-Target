<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// UCC Grade Points and Class Thresholds
$gradePointsMap = [
    'A' => 4.0,
    'B+' => 3.5,
    'B' => 3.0,
    'C+' => 2.5,
    'C' => 2.0
];

$classThresholds = [
    'first' => 3.60,
    'second_upper' => 3.00,
    'second_lower' => 2.50,
    'third' => 2.00
];

// Get form data
$currentCGPA = floatval($_POST['current_cgpa'] ?? 0);
$completedCredits = floatval($_POST['completed_credits'] ?? 0);
$remainingCredits = floatval($_POST['remaining_credits'] ?? 0);
$courseCreditValue = floatval($_POST['course_credit_value'] ?? 3);
$targetClass = trim($_POST['target_class'] ?? '');
$maxAs = isset($_POST['max_as']) && $_POST['max_as'] !== '' ? intval($_POST['max_as']) : null;
$excludeAllA = isset($_POST['exclude_all_a']) && $_POST['exclude_all_a'] === 'true';
$skipCGrades = isset($_POST['skip_c_grades']) && $_POST['skip_c_grades'] === 'true';
$topNResults = intval($_POST['top_n_results'] ?? 5);

// Validation
if (!$currentCGPA || !$completedCredits || !$remainingCredits || !$targetClass) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if (!array_key_exists($targetClass, $classThresholds)) {
    echo json_encode(['success' => false, 'error' => 'Invalid target class']);
    exit;
}

// Main prediction function
function predictClassPaths($currentCGPA, $completedCredits, $remainingCredits, $courseCreditValue,
                          $targetClass, $maxAs, $excludeAllA, $skipCGrades, $topNResults,
                          $gradePointsMap, $classThresholds) {
    
    $totalCredits = $completedCredits + $remainingCredits;
    $totalCourses = intval($remainingCredits / $courseCreditValue);
    $targetGPA = $classThresholds[$targetClass];
    
    $earnedPoints = $currentCGPA * $completedCredits;
    $requiredTotalPoints = $targetGPA * $totalCredits;
    $remainingPointsNeeded = $requiredTotalPoints - $earnedPoints;
    
    // Check if target is mathematically possible
    if ($remainingPointsNeeded / $remainingCredits > 4.0) {
        return ["Target class is mathematically impossible"];
    }
    
    $neededAvgGPA = $remainingPointsNeeded / $remainingCredits;
    $validCombinations = [];
    $grades = ['A', 'B+', 'B', 'C+', 'C'];
    
    // Backtracking function
    function backtrack($path, $totalPoints, $gradeCountMap, $depth, $totalCourses, 
                     $remainingCredits, $neededAvgGPA, $courseCreditValue, $maxAs, 
                     $skipCGrades, $gradePointsMap, $grades, &$validCombinations) {
        
        if ($depth === $totalCourses) {
            $avg = $totalPoints / $remainingCredits;
            if ($avg >= $neededAvgGPA) {
                $validCombinations[] = $path;
            }
            return;
        }
        
        foreach ($grades as $grade) {
            // Apply constraints
            if ($grade === 'A' && $maxAs !== null && ($gradeCountMap[$grade] ?? 0) >= $maxAs) {
                continue;
            }
            if ($skipCGrades && $grade === 'C') {
                continue;
            }
            
            $point = $gradePointsMap[$grade];
            $predictedPoints = $totalPoints + $point * $courseCreditValue;
            
            // Pruning: check if remaining courses can achieve target even with all A's
            $maxPossiblePoints = $predictedPoints + ($totalCourses - $depth - 1) * 4.0 * $courseCreditValue;
            if ($maxPossiblePoints / $remainingCredits < $neededAvgGPA) {
                continue;
            }
            
            $newPath = $path;
            $newPath[] = $grade;
            $newGradeCountMap = $gradeCountMap;
            $newGradeCountMap[$grade] = ($newGradeCountMap[$grade] ?? 0) + 1;
            
            backtrack($newPath, $predictedPoints, $newGradeCountMap, $depth + 1, 
                     $totalCourses, $remainingCredits, $neededAvgGPA, $courseCreditValue, 
                     $maxAs, $skipCGrades, $gradePointsMap, $grades, $validCombinations);
        }
    }
    
    backtrack([], 0, [], 0, $totalCourses, $remainingCredits, $neededAvgGPA, 
             $courseCreditValue, $maxAs, $skipCGrades, $gradePointsMap, $grades, $validCombinations);
    
    // Filter out all-A combinations if requested
    if ($excludeAllA) {
        $validCombinations = array_filter($validCombinations, function($combo) {
            return !array_reduce($combo, function($carry, $grade) {
                return $carry && $grade === 'A';
            }, true);
        });
    }
    
    if (empty($validCombinations)) {
        return ["No realistic combinations found"];
    }
    
    // Sort combinations by GPA (descending) and A count (descending)
    usort($validCombinations, function($a, $b) use ($gradePointsMap, $courseCreditValue, $remainingCredits) {
        $gpaA = computeGPA($a, $gradePointsMap, $courseCreditValue, $remainingCredits);
        $gpaB = computeGPA($b, $gradePointsMap, $courseCreditValue, $remainingCredits);
        if ($gpaA != $gpaB) return $gpaB <=> $gpaA;
        return count(array_filter($b, function($g) { return $g === 'A'; })) <=> 
               count(array_filter($a, function($g) { return $g === 'A'; }));
    });
    
    // Limit results
    $limitedCombos = array_slice($validCombinations, 0, $topNResults);
    
    return array_map(function($combo) use ($gradePointsMap, $courseCreditValue, $remainingCredits) {
        $gpa = computeGPA($combo, $gradePointsMap, $courseCreditValue, $remainingCredits);
        $breakdown = array_count_values($combo);
        
        return [
            'grades' => $combo,
            'GPA' => round($gpa, 2),
            'breakdown' => $breakdown
        ];
    }, $limitedCombos);
}

function computeGPA($combo, $gradePointsMap, $courseCreditValue, $remainingCredits) {
    $total = array_reduce($combo, function($sum, $grade) use ($gradePointsMap) {
        return $sum + $gradePointsMap[$grade];
    }, 0) * $courseCreditValue;
    return $total / $remainingCredits;
}

// Execute prediction
$results = predictClassPaths($currentCGPA, $completedCredits, $remainingCredits, $courseCreditValue,
                           $targetClass, $maxAs, $excludeAllA, $skipCGrades, $topNResults,
                           $gradePointsMap, $classThresholds);

// Prepare response
$response = [
    'success' => true,
    'results' => $results,
    'input_parameters' => [
        'currentCGPA' => $currentCGPA,
        'completedCredits' => $completedCredits,
        'remainingCredits' => $remainingCredits,
        'courseCreditValue' => $courseCreditValue,
        'targetClass' => $targetClass,
        'maxAs' => $maxAs,
        'excludeAllA' => $excludeAllA,
        'skipCGrades' => $skipCGrades,
        'topNResults' => $topNResults
    ],
    'neededAvgGPA' => round(($classThresholds[$targetClass] * ($completedCredits + $remainingCredits) - $currentCGPA * $completedCredits) / $remainingCredits, 2),
    'timestamp' => date('Y-m-d H:i:s')
];

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
?>