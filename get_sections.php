<?php
// Return sections based on grade level
header('Content-Type: application/json');

$grade = isset($_GET['grade']) ? intval($_GET['grade']) : 0;

// Define unique sections for each grade level
$gradeSections = [
    7 => ['Rizal', 'Bonifacio', 'Mabini', 'Luna', 'Del Pilar'],
    8 => ['Narra', 'Molave', 'Acacia', 'Mahogany', 'Ipil'],
    9 => ['Diamond', 'Emerald', 'Ruby', 'Sapphire', 'Amethyst'],
    10 => ['Einstein', 'Newton', 'Galileo', 'Darwin', 'Curie'],
    11 => ['STEM-A', 'STEM-B', 'ABM-A', 'ABM-B', 'HUMSS-A', 'HUMSS-B', 'TVL-ICT', 'TVL-HE'],
    12 => ['STEM-A', 'STEM-B', 'ABM-A', 'ABM-B', 'HUMSS-A', 'HUMSS-B', 'TVL-ICT', 'TVL-HE']
];

if ($grade >= 7 && $grade <= 12 && isset($gradeSections[$grade])) {
    echo json_encode([
        'success' => true,
        'grade' => $grade,
        'sections' => $gradeSections[$grade]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid grade level. Please select a grade between 7 and 12.',
        'sections' => []
    ]);
}
?>
