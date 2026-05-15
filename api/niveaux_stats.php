<?php
/**
 * api/niveaux_stats.php — API publique read-only des stats niveaux
 *
 * Lit le fichier sauvegarde par admin/panel.php (action=niveaux)
 * Retourne :
 *   - total_athletes
 *   - total_avec_niv (BDD + calcule via bareme)
 *   - sans_niveau
 *   - par_niveau (counts par code IA/IB/IE/IR/.../D8)
 *   - par_niveau_bdd / par_niveau_calc (split BDD vs calcule)
 *   - hierarchy (ordre des codes)
 *   - computed_at (timestamp dernier calcul admin)
 *
 * Si aucun calcul n'a ete fait : { success: false, error: 'never_computed' }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600'); // 1h

$statsFile = __DIR__ . '/../logs/.niveaux_stats.php';

if (!file_exists($statsFile)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => 'never_computed',
        'message' => 'Aucun calcul n\'a ete effectue. L\'admin doit lancer le calcul depuis le panel.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = @file_get_contents($statsFile);
if ($raw === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'read_error',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$pos = strpos($raw, "\n");
if ($pos === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'invalid_format',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(substr($raw, $pos + 1), true);
if (!is_array($data)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'json_parse_error',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Filtrer : ne pas exposer publiquement la liste detaillee des epreuves problematiques
// (info admin uniquement). Garder juste le count.
unset($data['epreuves_problemes']);

// Ajouter age du calcul (en heures)
if (!empty($data['computed_at'])) {
    $ts = strtotime($data['computed_at']);
    if ($ts) {
        $data['age_seconds'] = time() - $ts;
        $data['age_hours']   = round((time() - $ts) / 3600, 1);
    }
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
