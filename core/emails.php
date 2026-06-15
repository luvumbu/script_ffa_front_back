<?php
/**
 * core/emails.php — Emails transactionnels « propres » (HTML brandé BOKONZI)
 *
 * Pour l'instant : l'email de bienvenue / remerciement envoyé à un membre
 * qui vient de souscrire une offre payante.
 *
 * Usage :
 *   require_once __DIR__ . '/emails.php';
 *   bkSendSubscriptionWelcome($conn, $idUser, 'bronze');        // envoi (1 seule fois)
 *   bkSendSubscriptionWelcome($conn, $idUser, 'bronze', true);  // force le renvoi
 *
 * L'envoi réel passe par bkMail() (core/mailer.php → SMTP Hostinger, fallback mail()).
 * L'anti-doublon est géré par un petit fichier JSON (logs/.sub_welcome_sent.php),
 * pour ne pas toucher au schéma de la base.
 */

require_once __DIR__ . '/mailer.php';        // bkMail()
require_once __DIR__ . '/stripe_config.php'; // $BK_PLANS
require_once __DIR__ . '/paths.php';         // BK_URL()

/** Fichier de suivi des bienvenues déjà envoyées (clé "u<idUser>:<plan>"). */
function _bkWelcomeLogFile() {
    return __DIR__ . '/../logs/.sub_welcome_sent.php';
}

/** Lit le journal des bienvenues envoyées. */
function _bkWelcomeLogRead() {
    $f = _bkWelcomeLogFile();
    if (!file_exists($f)) return [];
    $raw = file_get_contents($f);
    $pos = strpos($raw, "\n");
    if ($pos === false) return [];
    return json_decode(substr($raw, $pos + 1), true) ?: [];
}

/** Écrit le journal des bienvenues envoyées. */
function _bkWelcomeLogWrite($data) {
    $f = _bkWelcomeLogFile();
    file_put_contents($f, "<?php die(); ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * Construit le corps HTML de l'email de bienvenue / remerciement.
 *
 * @param string $prenom     prénom du membre (peut être vide)
 * @param array  $plan       entrée de $BK_PLANS (name, color, features, tagline…)
 * @return string            HTML complet
 */
function bkSubscriptionWelcomeHtml($prenom, array $plan) {
    $name     = htmlspecialchars($plan['name'] ?? 'Premium');
    $color    = $plan['color'] ?? '#6c5ce7';
    $tagline  = htmlspecialchars($plan['tagline'] ?? '');
    $features = is_array($plan['features'] ?? null) ? $plan['features'] : [];
    $hello    = trim($prenom) !== '' ? ('Bonjour ' . htmlspecialchars(trim($prenom))) : 'Bonjour';
    $site     = BK_URL('/');

    // Avantages universels + ceux propres au palier
    $bullets = array_merge([
        'Accès à plus de 330 000 athlètes français',
        'Recherche avancée et comparateur',
    ], $features);

    $li = '';
    foreach ($bullets as $b) {
        $li .= '<tr><td style="padding:6px 0;vertical-align:top;width:26px;">'
             . '<span style="display:inline-block;width:18px;height:18px;line-height:18px;text-align:center;'
             . 'border-radius:50%;background:' . $color . ';color:#fff;font-size:12px;font-weight:700;">&#10003;</span>'
             . '</td><td style="padding:6px 0;color:#2d3340;font-size:15px;line-height:1.45;">'
             . htmlspecialchars($b) . '</td></tr>';
    }

    return '<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f1f2f6;font-family:Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f2f6;padding:32px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(20,24,40,.08);">

        <!-- Bandeau -->
        <tr><td style="background:linear-gradient(135deg,#6c5ce7,#a855f7);padding:34px 32px 28px;text-align:center;">
          <div style="color:#fff;font-size:26px;font-weight:800;letter-spacing:1px;">BOKONZI</div>
          <div style="color:#ffffffcc;font-size:13px;margin-top:4px;">Base de données de l\'athlétisme français</div>
        </td></tr>

        <!-- Corps -->
        <tr><td style="padding:34px 32px 8px;">
          <h1 style="margin:0 0 6px;color:#1b1f2a;font-size:22px;">' . $hello . ', et bienvenue&nbsp;! &#127881;</h1>
          <p style="margin:0 0 18px;color:#4a5161;font-size:15px;line-height:1.55;">
            Un grand <b>merci</b> pour ta confiance. Ton abonnement
            <b style="color:' . $color . ';">BOKONZI ' . $name . '</b> est <b>activé</b>'
            . ($tagline !== '' ? ' — ' . $tagline . '.' : '.') . '
          </p>

          <!-- Badge offre -->
          <div style="text-align:center;margin:8px 0 22px;">
            <span style="display:inline-block;padding:8px 20px;border-radius:100px;background:' . $color . '1a;color:' . $color . ';font-size:14px;font-weight:700;border:1px solid ' . $color . '55;">
              &#127941; Offre ' . $name . '
            </span>
          </div>

          <p style="margin:0 0 10px;color:#1b1f2a;font-size:15px;font-weight:700;">Ce que ton abonnement débloque&nbsp;:</p>
          <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 22px;">' . $li . '</table>

          <!-- CTA -->
          <div style="text-align:center;margin:6px 0 8px;">
            <a href="' . htmlspecialchars($site) . '" style="display:inline-block;background:#6c5ce7;color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;padding:14px 34px;border-radius:10px;">
              Accéder à mon espace
            </a>
          </div>
        </td></tr>

        <!-- Aide -->
        <tr><td style="padding:8px 32px 28px;">
          <p style="margin:18px 0 0;color:#6b7280;font-size:13px;line-height:1.55;border-top:1px solid #eceef3;padding-top:18px;">
            Une question, un souci avec ton accès ? Réponds simplement à cet email
            ou écris-nous à <a href="mailto:contact@bokonzi.com" style="color:#6c5ce7;">contact@bokonzi.com</a> — on te répond vite.
          </p>
        </td></tr>

        <!-- Pied -->
        <tr><td style="background:#0d1117;padding:20px 32px;text-align:center;">
          <div style="color:#8b949e;font-size:12px;">BOKONZI · bokonzi.com</div>
          <div style="color:#5a6580;font-size:11px;margin-top:6px;">Tu reçois cet email car tu viens de souscrire un abonnement BOKONZI.</div>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body></html>';
}

/**
 * Envoie l'email de bienvenue / remerciement à un membre abonné.
 *
 * @param mysqli      $conn
 * @param int         $idUser
 * @param string|null $planKey  clé du plan ('bronze'…). Si null → détecté via getUserPlan().
 * @param bool        $force    true = renvoie même si déjà envoyé
 * @return array  ['ok'=>bool, 'sent'=>bool, 'skipped'=>bool, 'reason'=>string, 'to'=>string, 'plan'=>string]
 */
function bkSendSubscriptionWelcome($conn, $idUser, $planKey = null, $force = false) {
    global $BK_PLANS;
    $idUser = (int)$idUser;
    $res = ['ok' => false, 'sent' => false, 'skipped' => false, 'reason' => '', 'to' => '', 'plan' => ''];
    if ($idUser <= 0) { $res['reason'] = 'id_user invalide'; return $res; }

    // Récupère le membre
    $st = $conn->prepare("SELECT email, prenom FROM users WHERE id_user = ? LIMIT 1");
    $st->bind_param("i", $idUser);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$u || empty($u['email'])) { $res['reason'] = 'membre ou email introuvable'; return $res; }

    $email  = $u['email'];
    $prenom = $u['prenom'] ?? '';
    $res['to'] = $email;

    // Détermine le plan
    if (!$planKey) {
        if (function_exists('getUserPlan')) $planKey = getUserPlan($conn, $idUser);
    }
    if (!$planKey || !isset($BK_PLANS[$planKey])) {
        // Repli : on prend une présentation neutre « Premium »
        $plan = ['name' => 'Premium', 'color' => '#6c5ce7', 'tagline' => '', 'features' => []];
        $planKey = $planKey ?: 'premium';
    } else {
        $plan = $BK_PLANS[$planKey];
    }
    $res['plan'] = $planKey;

    // Anti-doublon
    $logKey = 'u' . $idUser . ':' . $planKey;
    $log = _bkWelcomeLogRead();
    if (!$force && isset($log[$logKey])) {
        $res['ok'] = true;
        $res['skipped'] = true;
        $res['reason'] = 'déjà envoyé le ' . $log[$logKey];
        return $res;
    }

    // Construit + envoie
    $subject = 'Bienvenue sur BOKONZI ' . ($plan['name'] ?? '') . ' — merci pour ton abonnement !';
    $html    = bkSubscriptionWelcomeHtml($prenom, $plan);
    $sent    = bkMail($email, $subject, $html, 'contact@bokonzi.com');

    if ($sent) {
        $log[$logKey] = date('Y-m-d H:i:s');
        _bkWelcomeLogWrite($log);
        $res['ok'] = true;
        $res['sent'] = true;
        $res['reason'] = 'envoyé';
        // Prévient l'admin (contact@bokonzi.com) qu'un nouvel abonné est arrivé.
        $res['admin_notified'] = bkNotifyAdminNewSubscription($conn, $idUser, $planKey);
    } else {
        $res['reason'] = 'échec bkMail() (vérifier mot de passe SMTP / config mail)';
    }
    return $res;
}

/**
 * Notifie l'admin (contact@bokonzi.com) qu'un membre vient de souscrire.
 *
 * @param mysqli      $conn
 * @param int         $idUser
 * @param string|null $planKey
 * @return bool  true si l'email admin est parti
 */
function bkNotifyAdminNewSubscription($conn, $idUser, $planKey = null) {
    global $BK_PLANS;
    $idUser = (int)$idUser;
    if ($idUser <= 0) return false;

    $st = $conn->prepare("SELECT email, prenom, nom FROM users WHERE id_user = ? LIMIT 1");
    $st->bind_param("i", $idUser);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$u) return false;

    $planName = ($planKey && isset($BK_PLANS[$planKey])) ? $BK_PLANS[$planKey]['name'] : ($planKey ?: '—');
    $color    = ($planKey && isset($BK_PLANS[$planKey])) ? $BK_PLANS[$planKey]['color'] : '#6c5ce7';
    $nomComplet = trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?: '(nom non renseigné)';
    $email    = $u['email'] ?? '';
    $when     = date('d/m/Y à H:i');

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;background:#f1f2f6;font-family:Helvetica,Arial,sans-serif;padding:28px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(20,24,40,.08);">'
        . '<tr><td style="background:' . $color . ';padding:22px 28px;color:#fff;font-size:18px;font-weight:800;">&#128176; Nouvel abonnement BOKONZI</td></tr>'
        . '<tr><td style="padding:24px 28px;color:#2d3340;font-size:15px;line-height:1.7;">'
        . '<p style="margin:0 0 14px;">Un membre vient de souscrire une offre payante :</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;">'
        . '<tr><td style="padding:6px 0;color:#6b7280;width:120px;">Offre</td><td style="padding:6px 0;font-weight:700;color:' . $color . ';">BOKONZI ' . htmlspecialchars($planName) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6b7280;">Nom</td><td style="padding:6px 0;">' . htmlspecialchars($nomComplet) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6b7280;">Email</td><td style="padding:6px 0;"><a href="mailto:' . htmlspecialchars($email) . '" style="color:#6c5ce7;">' . htmlspecialchars($email) . '</a></td></tr>'
        . '<tr><td style="padding:6px 0;color:#6b7280;">Compte</td><td style="padding:6px 0;">#' . $idUser . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#6b7280;">Date</td><td style="padding:6px 0;">' . $when . '</td></tr>'
        . '</table>'
        . '<p style="margin:18px 0 0;"><a href="' . htmlspecialchars(BK_URL('/admin/subscriptions.php')) . '" style="display:inline-block;background:#6c5ce7;color:#fff;text-decoration:none;font-weight:700;padding:11px 22px;border-radius:8px;font-size:14px;">Voir les abonnements</a></p>'
        . '</td></tr>'
        . '<tr><td style="background:#0d1117;padding:14px 28px;text-align:center;color:#8b949e;font-size:12px;">Notification automatique · bokonzi.com</td></tr>'
        . '</table></td></tr></table></body></html>';

    $subject = '💰 Nouvel abonné BOKONZI ' . $planName . ' — ' . $nomComplet;
    // Reply-To = email du client pour pouvoir lui répondre directement.
    return bkMail(BK_SMTP_FROM_EMAIL, $subject, $html, $email);
}
