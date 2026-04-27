<?php
// student/autocomplete.php — Live search API
require_once '../includes/auth.php';
require_once '../config/db.php';
checkAccess(['student', 'admin']);

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) { echo json_encode([]); exit; }

$like = '%' . $conn->real_escape_string($q) . '%';
$stmt = $conn->prepare(
    "SELECT id, title, author, category, cover_image, publication_date
     FROM books
     WHERE title LIKE ? OR author LIKE ? OR category LIKE ? OR publication_date LIKE ?
     ORDER BY
       CASE WHEN title LIKE ? THEN 0 ELSE 1 END,
       title ASC
     LIMIT 8"
);
$stmt->bind_param('sssss', $like, $like, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$books = [];
while ($row = $result->fetch_assoc()) {
    // Resolve correct cover image
    $cover = null;
    if (!empty($row['cover_image'])) {
        $dec = json_decode($row['cover_image'], true);
        $cover = is_array($dec) ? ('../' . $dec[0]) : ('../' . $row['cover_image']);
    }
    $books[] = [
        'id'       => $row['id'],
        'title'    => $row['title'],
        'author'   => $row['author'],
        'category' => $row['category'],
        'cover'    => $cover,
        'year'     => $row['publication_date'],
    ];
}

echo json_encode($books);
