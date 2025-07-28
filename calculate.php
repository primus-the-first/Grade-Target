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

// UCC Grading Scale
$gradeValues = [
    'A' => 4.0,
    'B+' => 3.5,
    'B' => 3.0,
    'C+' => 2.5,
    'C' => 2.0,
    'D' => 1.0,
    'E' => 0.0,
    'F' => 0.0
];

// Get form data
$courseNames = $_POST['course_name'] ?? [];
$creditHours = $_POST['credit_hours'] ?? [];
$grades = $_POST['grade'] ?? [];

// Validation
if (empty($courseNames) || empty($creditHours) || empty($grades)) {
    echo json_encode(['success' => false, 'error' => 'Missing course data']);
    exit;
}

if (count($courseNames) !== count($creditHours) || count($courseNames) !== count($grades)) {
    echo json_encode(['success' => false, 'error' => 'Mismatched course data']);
    exit;
}

$courses = [];
$totalGradePoints = 0;
$totalCredits = 0;
$errors = [];

// Process each course
for ($i = 0; $i < count($courseNames); $i++) {
    $courseName = trim($courseNames[$i]);
    $credits = floatval($creditHours[$i]);
    $grade = trim($grades[$i]);
    
    // Validate course data
    if (empty($courseName)) {
        $errors[] = "Course name cannot be empty for course " . ($i + 1);
        continue;
    }
    
    if ($credits <= 0 || $credits > 6) {
        $errors[] = "Credit hours must be between 1 and 6 for course: $courseName";
        continue;
    }
    
    if (!array_key_exists($grade, $gradeValues)) {
        $errors[] = "Invalid grade '$grade' for course: $courseName";
        continue;
    }
    
    $gradePoint = $gradeValues[$grade];
    $courseGradePoints = $credits * $gradePoint;
    
    $courses[] = [
        'name' => $courseName,
        'credits' => $credits,
        'grade' => $grade,
        'gradePoint' => $gradePoint,
        'courseGradePoints' => round($courseGradePoints, 2)
    ];
    
    $totalCredits += $credits;
    $totalGradePoints += $courseGradePoints;
}

// Check for errors
if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Calculate CGPA
$cgpa = $totalCredits > 0 ? $totalGradePoints / $totalCredits : 0;

// Determine class classification
function getClassification($cgpa) {
    if ($cgpa >= 3.6) {
        return [
            'name' => 'First Class',
            'color' => '#ffd700',
            'icon' => 'fas fa-crown',
            'description' => 'Outstanding academic achievement!'
        ];
    } elseif ($cgpa >= 3.0) {
        return [
            'name' => 'Second Class Upper',
            'color' => '#00c851',
            'icon' => 'fas fa-medal',
            'description' => 'Excellent academic performance!'
        ];
    } elseif ($cgpa >= 2.5) {
        return [
            'name' => 'Second Class Lower',
            'color' => '#39c0ed',
            'icon' => 'fas fa-award',
            'description' => 'Good academic performance!'
        ];
    } elseif ($cgpa >= 2.0) {
        return [
            'name' => 'Third Class',
            'color' => '#ffbb33',
            'icon' => 'fas fa-certificate',
            'description' => 'Satisfactory academic performance.'
        ];
    } elseif ($cgpa >= 1.0) {
        return [
            'name' => 'Pass',
            'color' => '#ff8800',
            'icon' => 'fas fa-check',
            'description' => 'Minimum passing requirement met.'
        ];
    } else {
        return [
            'name' => 'Fail (No Award)',
            'color' => '#dc3545',
            'icon' => 'fas fa-times',
            'description' => 'Below minimum passing requirement.'
        ];
    }
}

// Get target advice
function getTargetAdvice($cgpa) {
    if ($cgpa < 3.6) {
        $requiredImprovement = 3.6 - $cgpa;
        return [
            'show' => true,
            'message' => "To achieve First Class (3.6+ CGPA), you need to improve your CGPA by " . number_format($requiredImprovement, 2) . " points. Focus on getting higher grades in upcoming courses!",
            'target' => 'First Class',
            'improvement' => $requiredImprovement
        ];
    } elseif ($cgpa >= 3.6 && $cgpa < 4.0) {
        return [
            'show' => true,
            'message' => "Congratulations! You're already in First Class range. Keep up the excellent work to maintain or improve your position!",
            'target' => 'First Class Maintenance',
            'improvement' => 0
        ];
    } else {
        return [
            'show' => true,
            'message' => "Perfect! You've achieved the maximum CGPA. Outstanding academic excellence!",
            'target' => 'Perfect Score',
            'improvement' => 0
        ];
    }
}

$classification = getClassification($cgpa);
$targetAdvice = getTargetAdvice($cgpa);

// Calculate statistics
$highestGrade = '';
$lowestGrade = '';
$averageCredits = $totalCredits / count($courses);

if (!empty($courses)) {
    $gradeOrder = ['A', 'B+', 'B', 'C+', 'C', 'D', 'E', 'F'];
    $courseGrades = array_column($courses, 'grade');
    
    // Find highest and lowest grades
    foreach ($gradeOrder as $grade) {
        if (in_array($grade, $courseGrades)) {
            if (empty($highestGrade)) $highestGrade = $grade;
            $lowestGrade = $grade;
        }
    }
}

// Prepare response
$response = [
    'success' => true,
    'cgpa' => number_format($cgpa, 2),
    'classification' => $classification,
    'totalCredits' => $totalCredits,
    'totalGradePoints' => number_format($totalGradePoints, 2),
    'courses' => $courses,
    'targetAdvice' => $targetAdvice,
    'statistics' => [
        'courseCount' => count($courses),
        'averageCredits' => number_format($averageCredits, 1),
        'highestGrade' => $highestGrade,
        'lowestGrade' => $lowestGrade
    ],
    'message' => "CGPA calculated successfully!",
    'timestamp' => date('Y-m-d H:i:s')
];

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
?>