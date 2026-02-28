<?php
/**
 * api/send_profile_email.php — Envoie la fiche athlete par email
 *
 * POST : { email, athlete_id }
 * Recupere les donnees athlete depuis la BDD et envoie un email HTML complet.
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Methode POST requise'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    jsonResponse(['success' => false, 'error' => 'JSON invalide'], 400);
}

$email     = trim($input['email'] ?? '');
$athleteId = trim($input['athlete_id'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'Email invalide'], 400);
}
if ($athleteId === '') {
    jsonResponse(['success' => false, 'error' => 'athlete_id requis'], 400);
}

// --- Recuperer l'athlete ---
$idEsc = $conn->real_escape_string($athleteId);
$res = $conn->query("SELECT * FROM athletes WHERE athlete_id_externe = '$idEsc' LIMIT 1");
if (!$res || $res->num_rows === 0) {
    jsonResponse(['success' => false, 'error' => 'Athlete non trouve'], 404);
}
$athlete = $res->fetch_assoc();
$id = (int)$athlete['id_athlete'];
$nom = $athlete['nom_complet_athlete'];

// Ville de naissance
$villeNaissance = '';
if (!empty($athlete['id_ville_naissance'])) {
    $r = $conn->query("SELECT nom_ville FROM villes WHERE id_ville = " . (int)$athlete['id_ville_naissance']);
    if ($r && $r->num_rows > 0) $villeNaissance = $r->fetch_assoc()['nom_ville'];
}

// Clubs
$clubs = [];
$res = $conn->query("SELECT c.nom_club, ac.annee_debut, ac.annee_fin FROM athlete_clubs ac JOIN clubs c ON c.id_club = ac.id_club WHERE ac.id_athlete = $id ORDER BY ac.annee_debut DESC");
if ($res) while ($row = $res->fetch_assoc()) $clubs[] = $row;

// Medailles
$medailles = [];
$res = $conn->query("SELECT am.type_medaille, am.annee_medaille, e.nom_epreuve, co.nom_competition FROM athlete_medailles am LEFT JOIN epreuves e ON e.id_epreuve = am.id_epreuve LEFT JOIN competitions co ON co.id_competition = am.id_competition WHERE am.id_athlete = $id ORDER BY am.annee_medaille DESC");
if ($res) while ($row = $res->fetch_assoc()) $medailles[] = $row;

// Records
$records = [];
$res = $conn->query("SELECT r.performance_brut_record, r.date_record, e.nom_epreuve, v.nom_ville FROM athlete_records r LEFT JOIN epreuves e ON e.id_epreuve = r.id_epreuve LEFT JOIN villes v ON v.id_ville = r.id_ville WHERE r.id_athlete = $id");
if ($res) while ($row = $res->fetch_assoc()) $records[] = $row;

// Progressions (top 20)
$progressions = [];
$res = $conn->query("SELECT p.annee_progression, p.performance_brut_progression, e.nom_epreuve, v.nom_ville FROM athlete_progressions p LEFT JOIN epreuves e ON e.id_epreuve = p.id_epreuve LEFT JOIN villes v ON v.id_ville = p.id_ville WHERE p.id_athlete = $id ORDER BY p.annee_progression DESC LIMIT 20");
if ($res) while ($row = $res->fetch_assoc()) $progressions[] = $row;

// Selections
$selections = [];
$res = $conn->query("SELECT s.date_selection, co.nom_competition, e.nom_epreuve FROM athlete_selections s LEFT JOIN competitions co ON co.id_competition = s.id_competition LEFT JOIN epreuves e ON e.id_epreuve = s.id_epreuve WHERE s.id_athlete = $id ORDER BY s.date_selection DESC");
if ($res) while ($row = $res->fetch_assoc()) $selections[] = $row;

// --- Construire le HTML de l'email ---
$h = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0;">';
$html .= '<div style="max-width:700px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.1);">';

// Header
$html .= '<div style="background:linear-gradient(135deg,#1a1a2e,#16213e);padding:30px 40px;color:#fff;">';
$html .= '<h1 style="margin:0 0 8px;font-size:28px;">' . $h($nom) . '</h1>';
$infos = [];
if ($athlete['sexe_athlete']) $infos[] = $athlete['sexe_athlete'] === 'M' ? 'Homme' : 'Femme';
if ($athlete['categorie_athlete']) $infos[] = $h($athlete['categorie_athlete']);
if ($athlete['nationalite_athlete']) $infos[] = $h($athlete['nationalite_athlete']);
if ($athlete['date_naissance_athlete']) $infos[] = $h($athlete['date_naissance_athlete']);
if ($villeNaissance) $infos[] = $h($villeNaissance);
if (!empty($infos)) $html .= '<p style="margin:0;opacity:0.85;font-size:15px;">' . implode(' &middot; ', $infos) . '</p>';
$html .= '</div>';

$html .= '<div style="padding:30px 40px;">';

// Clubs
if (!empty($clubs)) {
    $html .= '<h2 style="color:#1a1a2e;border-bottom:2px solid #3b82f6;padding-bottom:8px;font-size:20px;">Clubs</h2>';
    $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
    $html .= '<tr style="background:#f0f4ff;"><th style="text-align:left;padding:8px 12px;font-size:14px;">Club</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Periode</th></tr>';
    foreach ($clubs as $c) {
        $periode = ($c['annee_debut'] ?? '?') . ' - ' . ($c['annee_fin'] ?? '...');
        $html .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h(rtrim($c['nom_club'], '* ')) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($periode) . '</td></tr>';
    }
    $html .= '</table>';
}

// Medailles
if (!empty($medailles)) {
    $medalEmoji = ['or' => '&#129351;', 'argent' => '&#129352;', 'bronze' => '&#129353;'];
    $html .= '<h2 style="color:#1a1a2e;border-bottom:2px solid #f59e0b;padding-bottom:8px;font-size:20px;">Medailles (' . count($medailles) . ')</h2>';
    $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
    $html .= '<tr style="background:#fffbeb;"><th style="text-align:left;padding:8px 12px;font-size:14px;">Type</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Epreuve</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Competition</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Annee</th></tr>';
    foreach ($medailles as $m) {
        $emoji = $medalEmoji[strtolower($m['type_medaille'])] ?? '';
        $html .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $emoji . ' ' . $h($m['type_medaille']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($m['nom_epreuve']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($m['nom_competition']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($m['annee_medaille']) . '</td></tr>';
    }
    $html .= '</table>';
}

// Records
if (!empty($records)) {
    $html .= '<h2 style="color:#1a1a2e;border-bottom:2px solid #10b981;padding-bottom:8px;font-size:20px;">Records personnels (' . count($records) . ')</h2>';
    $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
    $html .= '<tr style="background:#ecfdf5;"><th style="text-align:left;padding:8px 12px;font-size:14px;">Epreuve</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Performance</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Date</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Lieu</th></tr>';
    foreach ($records as $r) {
        $html .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($r['nom_epreuve']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($r['performance_brut_record']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($r['date_record']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($r['nom_ville']) . '</td></tr>';
    }
    $html .= '</table>';
}

// Selections
if (!empty($selections)) {
    $html .= '<h2 style="color:#1a1a2e;border-bottom:2px solid #8b5cf6;padding-bottom:8px;font-size:20px;">Selections (' . count($selections) . ')</h2>';
    $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
    $html .= '<tr style="background:#f5f3ff;"><th style="text-align:left;padding:8px 12px;font-size:14px;">Competition</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Epreuve</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Date</th></tr>';
    foreach ($selections as $s) {
        $html .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($s['nom_competition']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($s['nom_epreuve']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($s['date_selection']) . '</td></tr>';
    }
    $html .= '</table>';
}

// Progressions
if (!empty($progressions)) {
    $html .= '<h2 style="color:#1a1a2e;border-bottom:2px solid #06b6d4;padding-bottom:8px;font-size:20px;">Progressions (top 20)</h2>';
    $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
    $html .= '<tr style="background:#ecfeff;"><th style="text-align:left;padding:8px 12px;font-size:14px;">Epreuve</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Performance</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Annee</th><th style="text-align:left;padding:8px 12px;font-size:14px;">Lieu</th></tr>';
    foreach ($progressions as $p) {
        $html .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($p['nom_epreuve']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($p['performance_brut_progression']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($p['annee_progression']) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-size:14px;">' . $h($p['nom_ville']) . '</td></tr>';
    }
    $html .= '</table>';
}

// Footer
$url = 'https://bokonzi.com/?page=profil&id=' . urlencode($athleteId);
$html .= '<div style="margin-top:32px;padding-top:20px;border-top:1px solid #e5e7eb;text-align:center;color:#6b7280;font-size:13px;">';
$html .= '<p>Voir le profil complet : <a href="' . $h($url) . '" style="color:#3b82f6;">' . $h($url) . '</a></p>';
$html .= '<p>Genere par <strong>Bokonzi</strong> — <a href="https://bokonzi.com" style="color:#3b82f6;">bokonzi.com</a></p>';
$html .= '</div>';

$html .= '</div></div></body></html>';

// --- Envoyer l'email ---
$subject = 'Fiche athlete : ' . $nom . ' — Bokonzi';

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Bokonzi <noreply@bokonzi.com>\r\n";
$headers .= "Reply-To: noreply@bokonzi.com\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$sent = @mail($email, $subject, $html, $headers);

if ($sent) {
    // Enregistrer aussi dans email_subscribers
    $emailEsc = $conn->real_escape_string($email);
    $detailEsc = $conn->real_escape_string(substr($nom . ' (id:' . $athleteId . ')', 0, 255));
    $conn->query("INSERT IGNORE INTO email_subscribers (email, source, detail) VALUES ('$emailEsc', 'pdf', '$detailEsc')");

    jsonResponse(['success' => true, 'message' => 'Email envoye a ' . $email]);
} else {
    jsonResponse(['success' => false, 'error' => 'Echec envoi email. Verifiez la configuration SMTP du serveur.'], 500);
}
