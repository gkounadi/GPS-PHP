<?php
$course_id = 1; // Fixed to Riverside for now
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM holes WHERE course_id = ? ORDER BY hole_number");
$stmt->execute([$course_id]);
$holes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$course['holes_data'] = $holes;
echo json_encode($course);