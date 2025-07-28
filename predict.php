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

function computeGPA($combo, $gradePointsMap, $courseCreditValue, $remainingCredits) {
    $total = array_reduce($combo, function($sum, $grade) use ($gradePointsMap) {
        return $sum + $gradePointsMap[$grade];
    }, 0) * $courseCreditValue;
    return $total / $remainingCredits;
}

function predictClassPaths($currentCGPA, $completedCredits, $remainingCredits, $courseCreditValue,
                          $targetClass, $maxAs, $excludeAllA, $skipCGrades, $topNResults,
                          $gradePointsMap, $classThresholds) {

    $totalCredits = $completedCredits + $remainingCredits;
    $totalCourses = intval($remainingCredits / $courseCreditValue);
    $targetGPA = $classThresholds[$targetClass];

    $earnedPoints = $currentCGPA * $completedCredits;
    $requiredTotalPoints = $targetGPA * $totalCredits;
    $remainingPointsNeeded = $requiredTotalPoints - $earnedPoints;

    if ($remainingPointsNeeded / $remainingCredits > 4.0) {
        return ["Target class is mathematically impossible"];
    }

    $neededAvgGPA = $remainingPointsNeeded / $remainingCredits;

    $grades = ['A', 'B+', 'B', 'C+', 'C'];
    if ($skipCGrades) {
        $grades = array_filter($grades, fn($g) => $g !== 'C');
    }

    $simulations = 10000;
    $validCombinations = [];

    for ($i = 0; $i < $simulations; $i++) {
        $combo = [];
        $gradeCount = [];

        for ($j = 0; $j < $totalCourses; $j++) {
            $grade = $grades[array_rand($grades)];

            if ($grade === 'A' && $maxAs !== null && ($gradeCount['A'] ?? 0) >= $maxAs) {
                $grade = $grades[array_rand($grades)];
            }

            $combo[] = $grade;
            $gradeCount[$grade] = ($gradeCount[$grade] ?? 0) + 1;
        }

        if ($excludeAllA && count(array_unique($combo)) === 1 && $combo[0] === 'A') {
            continue;
        }

        $gpa = computeGPA($combo, $gradePointsMap, $courseCreditValue, $remainingCredits);
        if ($gpa >= $neededAvgGPA) {
            $validCombinations[] = [
                'grades' => $combo,
                'GPA' => round($gpa, 2),
                'breakdown' => array_count_values($combo)
            ];
        }

        if (count($validCombinations) >= $topNResults * 5) break;
    }

    if (empty($validCombinations)) {
        return ["No realistic combinations found"];
    }

    usort($validCombinations, fn($a, $b) => $b['GPA'] <=> $a['GPA']);
    return array_slice($validCombinations, 0, $topNResults);
}

$results = predictClassPaths($currentCGPA, $completedCredits, $remainingCredits, $courseCreditValue,
                           $targetClass, $maxAs, $excludeAllA, $skipCGrades, $topNResults,
                           $gradePointsMap, $classThresholds);

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

echo json_encode($response, JSON_PRETTY_PRINT);
?>
