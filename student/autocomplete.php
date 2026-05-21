<?php
// student/autocomplete.php — Live search API
require_once '../includes/auth.php';
require_once '../config/db.php';
global $conn;
checkAccess(['student', 'admin']);

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) { echo json_encode([]); exit; }

$like = '%' . $q . '%';
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
    $cover = '../assets/img/book-placeholder.jpg'; // Default
    if (!empty($row['cover_image'])) {
        $dec = json_decode($row['cover_image'], true);
        if (is_array($dec) && isset($dec[0])) {
            $cover = '../' . $dec[0];
        } else {
            // It's a plain string path
            $cover = '../' . $row['cover_image'];
        }
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
