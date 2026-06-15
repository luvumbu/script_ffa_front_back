<?php
/**
 * core/theme.php — Themes globaux du site
 *
 * Fichier de stockage : logs/.global_style.php (JSON protege par die())
 * Helpers :
 *   bkAllThemes()         -> liste des themes
 *   bkGetThemeId()        -> id du theme actif (lit le fichier)
 *   bkGetTheme()          -> array du theme actif
 *   bkRenderThemeHead()   -> injecte <link> Google Fonts + <style> CSS variables
 *   bkSaveTheme($id)      -> sauvegarde le choix
 */

if (!function_exists('bkAllThemes')) {

/**
 * Catalogue de polices Google Fonts disponibles pour le mode personnalise.
 * key = id court, value = [label, font-family CSS, URL google (vide = systeme)]
 */
function bkAllFonts() {
    return [
        'system'      => ['Police systeme', "-apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif", ''],
        'inter'       => ['Inter', "'Inter', sans-serif", 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap'],
        'poppins'     => ['Poppins (arrondi)', "'Poppins', sans-serif", 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap'],
        'roboto'      => ['Roboto', "'Roboto', sans-serif", 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap'],
        'lato'        => ['Lato', "'Lato', sans-serif", 'https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap'],
        'montserrat'  => ['Montserrat (geometrique)', "'Montserrat', sans-serif", 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap'],
        'open_sans'   => ['Open Sans', "'Open Sans', sans-serif", 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap'],
        'raleway'     => ['Raleway (fin)', "'Raleway', sans-serif", 'https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700&display=swap'],
        'nunito'      => ['Nunito (doux)', "'Nunito', sans-serif", 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap'],
        'oswald'      => ['Oswald (condense)', "'Oswald', sans-serif", 'https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&display=swap'],
        'bebas'       => ['Bebas Neue (titres)', "'Bebas Neue', sans-serif", 'https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap'],
        'playfair'    => ['Playfair Display (serif)', "'Playfair Display', serif", 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&display=swap'],
        'merriweather'=> ['Merriweather (serif lisible)', "'Merriweather', serif", 'https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap'],
        'cormorant'   => ['Cormorant Garamond (serif elegant)', "'Cormorant Garamond', serif", 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;700&display=swap'],
        'lora'        => ['Lora (serif moderne)', "'Lora', serif", 'https://fonts.googleapis.com/css2?family=Lora:wght@400;600;700&display=swap'],
        'cinzel'      => ['Cinzel (capitales)', "'Cinzel', serif", 'https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&display=swap'],
        'fira_code'   => ['Fira Code (monospace)', "'Fira Code', monospace", 'https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&display=swap'],
        'space_mono'  => ['Space Mono', "'Space Mono', monospace", 'https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&display=swap'],
        'orbitron'    => ['Orbitron (futuriste)', "'Orbitron', sans-serif", 'https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&display=swap'],
        'rajdhani'    => ['Rajdhani (techno condense)', "'Rajdhani', sans-serif", 'https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&display=swap'],
    ];
}

function bkAllThemes() {
    return [
        'default' => [
            'id'          => 'default',
            'nom'         => 'Bokonzi (par defaut)',
            'description' => 'Police Inter sobre, accent violet — l\'identite originale.',
            'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
            'font_google' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap',
            'primary'     => '#6c5ce7',
            'accent'      => '#a78bfa',
            'radius'      => '10px',
            'body_size'   => '14px',
            'heading_family' => null,
        ],
        'editorial' => [
            'id'          => 'editorial',
            'nom'         => 'Editorial',
            'description' => 'Playfair Display elegant — magazine premium, titres serif.',
            'font_family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
            'font_google' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap',
            'primary'     => '#d4af37',
            'accent'      => '#f59e0b',
            'radius'      => '6px',
            'body_size'   => '15px',
            'heading_family' => "'Playfair Display', Georgia, serif",
        ],
        'modern' => [
            'id'          => 'modern',
            'nom'         => 'Modern Tech',
            'description' => 'Poppins arrondi, accent cyan — look startup techno.',
            'font_family' => "'Poppins', -apple-system, BlinkMacSystemFont, sans-serif",
            'font_google' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap',
            'primary'     => '#06b6d4',
            'accent'      => '#22d3ee',
            'radius'      => '14px',
            'body_size'   => '14px',
            'heading_family' => null,
        ],
        'sport' => [
            'id'          => 'sport',
            'nom'         => 'Sport',
            'description' => 'Oswald athletique en titres, accent rouge — esprit competition.',
            'font_family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
            'font_google' => 'https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&display=swap',
            'primary'     => '#ef4444',
            'accent'      => '#fb923c',
            'radius'      => '6px',
            'body_size'   => '14px',
            'heading_family' => "'Oswald', Impact, sans-serif",
        ],
        'minimal' => [
            'id'          => 'minimal',
            'nom'         => 'Minimal',
            'description' => 'Police systeme, accent vert sobre — minimaliste et rapide.',
            'font_family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif",
            'font_google' => '',
            'primary'     => '#10b981',
            'accent'      => '#34d399',
            'radius'      => '4px',
            'body_size'   => '14px',
            'heading_family' => null,
        ],
        'premium' => [
            'id'          => 'premium',
            'nom'         => 'Premium',
            'description' => 'Cormorant Garamond raffine, accent bleu marine — luxe classique.',
            'font_family' => "'Cormorant Garamond', Georgia, serif",
            'font_google' => 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;700&family=Cinzel:wght@600&display=swap',
            'primary'     => '#1e3a8a',
            'accent'      => '#3b82f6',
            'radius'      => '2px',
            'body_size'   => '16px',
            'heading_family' => "'Cinzel', Georgia, serif",
        ],
        'cyber' => [
            'id'          => 'cyber',
            'nom'         => 'Black Mirror (futuriste)',
            'description' => 'Orbitron sci-fi, neon cyan/violet, jeux de lumiere — s\'adapte en mode jour (Tron light) et nuit (deep space).',
            'font_family' => "'Rajdhani', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
            'font_google' => 'https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Rajdhani:wght@400;500;600;700&display=swap',
            'primary'     => '#00e5ff',
            'accent'      => '#b14cff',
            'radius'      => '4px',
            'body_size'   => '15px',
            'heading_family' => "'Orbitron', 'Segoe UI', sans-serif",
            'effects'     => 'cyber',
        ],
    ];
}

function bkThemeFile() {
    return __DIR__ . '/../logs/.global_style.php';
}

function bkLoadConfig() {
    $f = bkThemeFile();
    if (!file_exists($f)) return ['theme' => 'default'];
    $raw = @file_get_contents($f);
    if (!$raw) return ['theme' => 'default'];
    $pos = strpos($raw, "\n");
    if ($pos === false) return ['theme' => 'default'];
    $data = json_decode(substr($raw, $pos + 1), true);
    if (!is_array($data)) return ['theme' => 'default'];
    return $data;
}

function bkGetThemeId() {
    $cfg = bkLoadConfig();
    $id = isset($cfg['theme']) ? (string)$cfg['theme'] : 'default';
    if ($id === 'custom') return 'custom';
    $all = bkAllThemes();
    if (!isset($all[$id])) return 'default';
    return $id;
}

function bkGetTheme() {
    $cfg = bkLoadConfig();
    $id = isset($cfg['theme']) ? (string)$cfg['theme'] : 'default';
    $all = bkAllThemes();

    // Mode custom : merge defaults + valeurs saisies par l'admin
    if ($id === 'custom') {
        $fonts = bkAllFonts();
        $c = is_array($cfg['custom'] ?? null) ? $cfg['custom'] : [];
        $bodyFontKey    = isset($c['body_font']) && isset($fonts[$c['body_font']]) ? $c['body_font'] : 'inter';
        $headingFontKey = isset($c['heading_font']) && isset($fonts[$c['heading_font']]) ? $c['heading_font'] : $bodyFontKey;
        $bodyFont    = $fonts[$bodyFontKey];
        $headingFont = $fonts[$headingFontKey];

        // Concatener les URLs Google si differentes
        $googleUrls = [];
        if (!empty($bodyFont[2])) $googleUrls[$bodyFont[2]] = true;
        if (!empty($headingFont[2]) && $headingFont[2] !== $bodyFont[2]) $googleUrls[$headingFont[2]] = true;

        $primary = isset($c['primary']) && preg_match('/^#[0-9a-fA-F]{6}$/', $c['primary']) ? $c['primary'] : '#6c5ce7';
        $accent  = isset($c['accent'])  && preg_match('/^#[0-9a-fA-F]{6}$/', $c['accent'])  ? $c['accent']  : '#a78bfa';
        $radius  = isset($c['radius'])  ? max(0, min(40, (int)$c['radius'])) . 'px' : '10px';
        $bodySize = isset($c['body_size']) ? max(11, min(22, (int)$c['body_size'])) . 'px' : '14px';

        return [
            'id'             => 'custom',
            'nom'            => 'Personnalise',
            'description'    => 'Configuration personnalisee',
            'font_family'    => $bodyFont[1],
            'font_google'    => implode('|', array_keys($googleUrls)),
            'heading_family' => $headingFont[1],
            'primary'        => $primary,
            'accent'         => $accent,
            'radius'         => $radius,
            'body_size'      => $bodySize,
        ];
    }

    return $all[$id] ?? $all['default'];
}

function bkGetCustomConfig() {
    $cfg = bkLoadConfig();
    return is_array($cfg['custom'] ?? null) ? $cfg['custom'] : [];
}

function bkSaveTheme($id, $custom = null) {
    if ($id !== 'custom') {
        $all = bkAllThemes();
        if (!isset($all[$id])) return false;
    }
    $f = bkThemeFile();
    $payload = ['theme' => $id, 'updated_at' => date('Y-m-d H:i:s')];
    if ($id === 'custom' && is_array($custom)) {
        // Sanitize
        $clean = [];
        $fonts = bkAllFonts();
        if (isset($custom['body_font']) && isset($fonts[$custom['body_font']])) $clean['body_font'] = $custom['body_font'];
        if (isset($custom['heading_font']) && isset($fonts[$custom['heading_font']])) $clean['heading_font'] = $custom['heading_font'];
        if (isset($custom['primary']) && preg_match('/^#[0-9a-fA-F]{6}$/', $custom['primary'])) $clean['primary'] = $custom['primary'];
        if (isset($custom['accent']) && preg_match('/^#[0-9a-fA-F]{6}$/', $custom['accent'])) $clean['accent'] = $custom['accent'];
        if (isset($custom['radius'])) $clean['radius'] = max(0, min(40, (int)$custom['radius']));
        if (isset($custom['body_size'])) $clean['body_size'] = max(11, min(22, (int)$custom['body_size']));
        $payload['custom'] = $clean;
    } else {
        // Conserver une config custom existante meme si on revient sur un theme preset
        $prev = bkLoadConfig();
        if (!empty($prev['custom']) && is_array($prev['custom'])) $payload['custom'] = $prev['custom'];
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    return @file_put_contents($f, "<?php die(); ?>\n" . $json, LOCK_EX) !== false;
}

/**
 * CSS des effets de lumiere du theme "Black Mirror / futuriste".
 * DEUX versions adaptatives :
 *   - NUIT  (body:not(.p2-light)) : deep space, neon cyan/violet, halos intenses
 *   - JOUR  (body.p2-light)       : futuriste clair "Tron light", glow subtil, texte fonce lisible
 * Pilote par la classe body.p2-light (toggle jour/nuit du site).
 * Selecteurs cibles (pas de `*`) pour rester performant sur l'accueil.
 */
function bkCyberEffectsCss($pri, $acc) {
    // $pri = cyan neon (#00e5ff), $acc = violet neon (#b14cff)
    $priDay = '#0e7490'; // cyan profond, lisible sur fond clair
    $accDay = '#7c3aed'; // violet profond, lisible sur fond clair

    $c  = "\n/* ===== Black Mirror : jeux de lumiere (jour + nuit) ===== */\n";

    // ---- Keyframes communs ----
    $c .= "@keyframes bkCyberGrid { from { background-position: 0 0, 0 0; } to { background-position: 46px 46px, 46px 46px; } }\n";
    $c .= "@keyframes bkCyberScan { 0% { transform: translateY(-160px); opacity:0; } 25% { opacity:.55; } 60% { opacity:.35; } 100% { transform: translateY(105vh); opacity:0; } }\n";

    // ---- Couches de lumiere communes (positionnement / animation) ----
    // IMPORTANT : les couches sont en z-index NEGATIF (derriere tout le contenu)
    // + pointer-events:none -> elles ne couvrent JAMAIS les boutons/liens et
    // n'interceptent aucun clic. On ne touche PAS au positionnement des conteneurs
    // du site (nav sticky, modales fixed) pour ne rien casser.
    $c .= "body::before { content:''; position: fixed; inset: 0; z-index: -2; pointer-events: none; background-size: 46px 46px;\n";
    $c .= "  mask-image: radial-gradient(ellipse 80% 70% at 50% 30%, #000 40%, transparent 100%);\n";
    $c .= "  -webkit-mask-image: radial-gradient(ellipse 80% 70% at 50% 30%, #000 40%, transparent 100%);\n";
    $c .= "  animation: bkCyberGrid 24s linear infinite; }\n";
    $c .= "body::after { content:''; position: fixed; left:0; right:0; top:0; height: 140px; z-index: -1; pointer-events: none; filter: blur(2px);\n";
    $c .= "  animation: bkCyberScan 7s ease-in-out infinite; }\n";
    $c .= ".badge-cat, .tag { box-shadow: 0 0 10px {$pri}33, inset 0 0 6px {$pri}22 !important; }\n";
    $c .= "input:focus, select:focus, textarea:focus { outline: none !important; }\n";

    // ====================== NUIT (defaut) ======================
    $c .= "/* --- nuit : deep space neon --- */\n";
    $c .= "html { background: #04060f !important; }\n";
    $c .= "body:not(.p2-light) {\n";
    $c .= "  --bg-body:#04060f!important; --bg-card:#0a0f20cc!important; --bg-card-hover:#101830!important; --bg-surface:#070b16!important; --bg-input:#060912!important; --bg-nav:#060a14ee!important;\n";
    $c .= "  --border:{$pri}33!important; --border-light:{$pri}55!important; --text-primary:#dff6ff!important; --text-secondary:#7d93b8!important; --text-muted:#45567a!important; --text-white:#f4fbff!important; --brand:{$pri}!important; --brand-light:{$acc}!important;\n";
    $c .= "  background:\n";
    $c .= "    radial-gradient(900px 600px at 12% -8%, {$pri}1f, transparent 60%),\n";
    $c .= "    radial-gradient(900px 700px at 110% 8%, {$acc}1c, transparent 55%),\n";
    $c .= "    radial-gradient(1200px 800px at 50% 120%, {$pri}14, transparent 60%),\n";
    $c .= "    #04060f !important; }\n";
    $c .= "body:not(.p2-light)::before { background-image: linear-gradient({$pri}0d 1px, transparent 1px), linear-gradient(90deg, {$pri}0d 1px, transparent 1px); }\n";
    $c .= "body:not(.p2-light)::after { background: linear-gradient(180deg, {$pri}22, transparent); opacity:.5; }\n";
    // Titres neon
    $c .= "body:not(.p2-light) h1, body:not(.p2-light) h2, body:not(.p2-light) .profil-hero h1, body:not(.p2-light) .profil-header .name, body:not(.p2-light) .stat-card-num, body:not(.p2-light) .stat-num { text-shadow: 0 0 6px {$pri}cc, 0 0 18px {$pri}66 !important; letter-spacing: 1px !important; }\n";
    // Cartes neon
    $c .= "body:not(.p2-light) .bk-table, body:not(.p2-light) .profil-header, body:not(.p2-light) .profil-hero, body:not(.p2-light) .section, body:not(.p2-light) .section-card, body:not(.p2-light) .chart-card, body:not(.p2-light) .stat-card, body:not(.p2-light) .search-box, body:not(.p2-light) .tools-card { border:1px solid {$pri}40 !important; box-shadow: 0 0 0 1px {$pri}10, 0 8px 30px rgba(0,0,0,.55), inset 0 1px 0 {$pri}14 !important; backdrop-filter: blur(2px); transition: box-shadow .25s, border-color .25s; }\n";
    $c .= "body:not(.p2-light) .stat-card:hover, body:not(.p2-light) .chart-card:hover, body:not(.p2-light) .section-card:hover { border-color:{$pri} !important; box-shadow: 0 0 18px {$pri}55, 0 10px 36px rgba(0,0,0,.6) !important; }\n";
    // Boutons / liens / nav / focus neon
    $c .= "body:not(.p2-light) .btn-msg, body:not(.p2-light) .btn-cmp-add, body:not(.p2-light) .btn-follow, body:not(.p2-light) .btn-pdf, body:not(.p2-light) .btn-share, body:not(.p2-light) .btn-dashboard, body:not(.p2-light) .btn-ffa { box-shadow: 0 0 12px {$pri}66, inset 0 0 8px {$pri}30 !important; text-shadow: 0 0 6px {$pri}aa; }\n";
    $c .= "body:not(.p2-light) .btn-msg:hover, body:not(.p2-light) .btn-cmp-add:hover, body:not(.p2-light) .btn-follow:hover, body:not(.p2-light) .btn-pdf:hover, body:not(.p2-light) .btn-share:hover, body:not(.p2-light) .btn-dashboard:hover { box-shadow: 0 0 22px {$pri}, inset 0 0 12px {$pri}55 !important; }\n";
    $c .= "body:not(.p2-light) a { text-shadow: 0 0 8px {$acc}55; }\n";
    $c .= "body:not(.p2-light) .nav, body:not(.p2-light) nav { box-shadow: 0 1px 0 {$pri}40, 0 6px 24px rgba(0,0,0,.5) !important; }\n";
    $c .= "body:not(.p2-light) .nav a.active, body:not(.p2-light) nav a.active, body:not(.p2-light) .nav-link.active { text-shadow: 0 0 10px {$pri}; }\n";
    $c .= "body:not(.p2-light) input:focus, body:not(.p2-light) select:focus, body:not(.p2-light) textarea:focus { box-shadow: 0 0 0 2px {$pri}55, 0 0 14px {$pri}44 !important; }\n";

    // ====================== JOUR (.p2-light) ======================
    $c .= "/* --- jour : futuriste clair (Tron light) --- */\n";
    $c .= "html:has(body.p2-light) { background: #e7edfb !important; }\n";
    $c .= "body.p2-light {\n";
    $c .= "  --bg-body:#e9eefb!important; --bg-card:#ffffffcc!important; --bg-card-hover:#ffffff!important; --bg-surface:#eef3fe!important; --bg-input:#ffffff!important; --bg-nav:#ffffffe6!important;\n";
    $c .= "  --border:{$priDay}33!important; --border-light:{$priDay}66!important; --text-primary:#0a1830!important; --text-secondary:#41557a!important; --text-muted:#8094ba!important; --text-white:#06283d!important; --brand:{$priDay}!important; --brand-light:{$accDay}!important;\n";
    $c .= "  background:\n";
    $c .= "    radial-gradient(900px 600px at 12% -8%, {$pri}26, transparent 60%),\n";
    $c .= "    radial-gradient(900px 700px at 110% 8%, {$acc}1f, transparent 55%),\n";
    $c .= "    radial-gradient(1200px 800px at 50% 120%, {$pri}1a, transparent 60%),\n";
    $c .= "    #e9eefb !important; }\n";
    $c .= "body.p2-light::before { background-image: linear-gradient({$priDay}14 1px, transparent 1px), linear-gradient(90deg, {$priDay}14 1px, transparent 1px); }\n";
    $c .= "body.p2-light::after { background: linear-gradient(180deg, {$pri}30, transparent); opacity:.4; }\n";
    // Titres : cyan profond lisible + glow leger (surcharge la couleur cyan vif du bloc generique)
    $c .= "body.p2-light h1, body.p2-light h2, body.p2-light .profil-hero h1, body.p2-light .profil-header .name { color:{$priDay} !important; -webkit-text-fill-color:{$priDay} !important; text-shadow: 0 0 1px {$pri}66, 0 2px 10px {$pri}22 !important; letter-spacing: 1px !important; }\n";
    $c .= "body.p2-light .stat-card-num, body.p2-light .stat-num { color:{$priDay} !important; -webkit-text-fill-color:{$priDay} !important; text-shadow: 0 1px 8px {$pri}33 !important; }\n";
    // Cartes : verre blanc, bord cyan, ombre douce
    $c .= "body.p2-light .bk-table, body.p2-light .profil-header, body.p2-light .profil-hero, body.p2-light .section, body.p2-light .section-card, body.p2-light .chart-card, body.p2-light .stat-card, body.p2-light .search-box, body.p2-light .tools-card { border:1px solid {$priDay}33 !important; box-shadow: 0 0 0 1px {$pri}1a, 0 8px 30px rgba(20,40,80,.10), inset 0 1px 0 #ffffffcc !important; backdrop-filter: blur(2px); transition: box-shadow .25s, border-color .25s; }\n";
    $c .= "body.p2-light .stat-card:hover, body.p2-light .chart-card:hover, body.p2-light .section-card:hover { border-color:{$priDay} !important; box-shadow: 0 0 16px {$pri}40, 0 10px 32px rgba(20,40,80,.16) !important; }\n";
    // Boutons : degrade electrique lisible (texte blanc), glow doux
    $c .= "body.p2-light .btn-msg, body.p2-light .btn-cmp-add, body.p2-light .btn-follow, body.p2-light .btn-pdf, body.p2-light .btn-share, body.p2-light .btn-dashboard { background: linear-gradient(135deg, {$priDay}, {$accDay}) !important; border-color:{$priDay} !important; color:#fff !important; box-shadow: 0 0 12px {$pri}40, 0 4px 14px rgba(20,40,80,.18) !important; text-shadow:none; }\n";
    $c .= "body.p2-light .btn-msg:hover, body.p2-light .btn-cmp-add:hover, body.p2-light .btn-follow:hover, body.p2-light .btn-pdf:hover, body.p2-light .btn-share:hover, body.p2-light .btn-dashboard:hover { box-shadow: 0 0 22px {$pri}66, 0 6px 18px rgba(20,40,80,.22) !important; }\n";
    // Liens : violet profond lisible
    $c .= "body.p2-light a { color:{$accDay} !important; text-shadow:none; }\n";
    $c .= "body.p2-light a:hover { color:{$priDay} !important; }\n";
    $c .= "body.p2-light .badge-cat, body.p2-light .tag { box-shadow: 0 0 8px {$priDay}22, inset 0 0 4px {$priDay}14 !important; }\n";
    $c .= "body.p2-light .nav, body.p2-light nav { box-shadow: 0 1px 0 {$priDay}33, 0 6px 22px rgba(20,40,80,.08) !important; }\n";
    $c .= "body.p2-light .nav a.active, body.p2-light nav a.active, body.p2-light .nav-link.active { color:{$priDay} !important; text-shadow: 0 0 8px {$pri}55; }\n";
    $c .= "body.p2-light input:focus, body.p2-light select:focus, body.p2-light textarea:focus { box-shadow: 0 0 0 2px {$priDay}44, 0 0 12px {$pri}33 !important; }\n";

    // ---- Accessibilite : couper les animations si demande ----
    $c .= "@media (prefers-reduced-motion: reduce) { body::before, body::after { animation: none !important; } }\n";

    return $c;
}

function bkRenderThemeHead() {
    static $cached = null;
    if ($cached !== null) { echo $cached; return; }

    $t = bkGetTheme();
    if (!$t || $t['id'] === 'default') {
        $cached = '';
        return;
    }
    $out = "\n<!-- BK theme: " . htmlspecialchars($t['id']) . " -->\n";

    // Chargement Google Fonts NON-bloquant. font_google peut contenir 1 URL ou plusieurs separees par |
    if (!empty($t['font_google'])) {
        $urls = explode('|', $t['font_google']);
        $hasGoogle = false;
        foreach ($urls as $u) {
            $u = trim($u);
            if ($u === '') continue;
            if (!$hasGoogle) { $out .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"; $hasGoogle = true; }
            $eu = htmlspecialchars($u);
            $out .= '<link rel="preload" as="style" href="' . $eu . '" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
            $out .= '<noscript><link rel="stylesheet" href="' . $eu . '"></noscript>' . "\n";
        }
    }

    $ff   = $t['font_family'];
    $hf   = $t['heading_family'] ?: $ff;
    $pri  = $t['primary'];
    $acc  = $t['accent'];
    $rad  = $t['radius'];
    $bs   = $t['body_size'];
    $css  = "<style id=\"bk-theme-override\">\n";
    $css .= ":root { --bk-theme-primary: {$pri}; --bk-theme-accent: {$acc}; --bk-theme-radius: {$rad}; --bk-theme-font: {$ff}; --bk-theme-heading: {$hf}; }\n";

    // Police body : ciblage selecteur unique sur body (heritage CSS s'occupe du reste).
    // PAS de selecteur * (recalcul prohibitif sur l'accueil avec milliers d'elements).
    $css .= "body, input, textarea, select, button { font-family: {$ff} !important; font-size: {$bs}; }\n";

    // Headings : police speciale + couleur primary
    $css .= "h1, h2, h3, h4, h5, h6, .name, .profil-hero h1, .stat-card-num, .stat-num { font-family: {$hf} !important; letter-spacing: 0.3px; }\n";
    $css .= "h1, h2, .profil-hero h1, .profil-header .name { color: {$pri} !important; -webkit-text-fill-color: {$pri} !important; }\n";

    // Boutons violets historiques -> primary
    $css .= ".btn-msg, .btn-cmp-add, .btn-follow, .btn-pdf, .btn-share, .btn-dashboard { background: {$pri} !important; color: #fff !important; border-color: {$pri} !important; }\n";

    // Liens
    $css .= "a { color: {$acc} !important; }\n";
    $css .= "a:hover { color: {$pri} !important; }\n";

    // Bordures et radius : cards principales
    $css .= ".bk-table, .profil-header, .profil-hero, .section, .section-card, .chart-card, .stat-card, .search-box, .tools-card, input, textarea, select, .badge-cat, .btn-msg, button { border-radius: {$rad} !important; }\n";

    // Badges tags
    $css .= ".badge-cat, .tag { background: {$pri}25 !important; color: {$acc} !important; border: 1px solid {$pri}55 !important; }\n";

    // Nav active
    $css .= ".nav a.active, nav a.active, .nav-link.active { color: {$pri} !important; }\n";

    // Stat cards
    $css .= ".stat-card { border-color: {$pri}40 !important; }\n";
    $css .= ".stat-card .num, .stat-card-num, .stat-num { color: {$pri} !important; -webkit-text-fill-color: {$pri} !important; }\n";

    // --- Effets de lumiere par theme (ex: Black Mirror / futuriste) ---
    if (($t['effects'] ?? '') === 'cyber') {
        $css .= bkCyberEffectsCss($pri, $acc);
    }

    $css .= "</style>\n";
    $out .= $css;
    $cached = $out;
    echo $out;
}

} // fin if (!function_exists)
