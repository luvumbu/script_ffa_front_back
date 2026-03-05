<?php
$key = $_GET['bk_key'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') { http_response_code(403); die('Interdit'); }
require_once __DIR__ . '/../core/db.php';
$r = $conn->query("SELECT id_user, email, LENGTH(picture) as pic_len, picture FROM users WHERE picture IS NOT NULL");
$out = [];
while ($row = $r->fetch_assoc()) {
    $out[] = [
        'email' => $row['email'],
        'pic_len' => $row['pic_len'],
        'url_ends_with' => substr($row['picture'], -20),
        'works' => (bool)@file_get_contents($row['picture'], false, stream_context_create(['http' => ['timeout' => 5]])),
    ];
}
$conn->close();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
unlink(__FILE__);
