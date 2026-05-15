<?php
/**
 * admin/subscriptions.php — Gestion des abonnements (Super Admin)
 *
 *  - Accorder manuellement un accès (offre + durée) à un utilisateur
 *  - Retirer un accès
 *  - Vérifier / synchroniser un paiement directement depuis Stripe
 *  - Voir tous les abonnements en cours
 */

require_once __DIR__ . '/../core/db.php';            // $conn
require_once __DIR__ . '/../core/stripe_config.php'; // $BK_PLANS
require_once __DIR__ . '/../core/subscription.php';  // bkSyncFromStripe(), helpers

// ── Auth Super Admin (même mécanisme que panel.php) ──────────────────────
function isSuperAdmin() {
    if (empty($_COOKIE['bk_sa_token'])) return false;
    $token  = $_COOKIE['bk_sa_token'];
    $saFile = __DIR__ . '/../logs/.sa_sessions.php';
    if (!file_exists($saFile)) return false;
    $raw = file_get_contents($saFile);
    $pos = strpos($raw, "\n");
    if ($pos === false) return false;
    $sessions = json_decode(substr($raw, $pos + 1), true) ?: [];
    return isset($sessions[$token]) && ($sessions[$token]['expires'] ?? 0) > time();
}
if (!isSuperAdmin()) {
    header('Location: ../login.php');
    exit;
}

$msg = ''; $msgType = 'ok';

// ── Traitement des actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'grant') {
        $email    = trim($_POST['email'] ?? '');
        $plan     = $_POST['plan'] ?? '';
        $duration = (int)($_POST['duration'] ?? 0); // mois ; 0 = illimité

        if (!isset($BK_PLANS[$plan])) {
            $msg = "Offre inconnue."; $msgType = 'err';
        } elseif ($email === '') {
            $msg = "Indique l'email de l'utilisateur."; $msgType = 'err';
        } else {
            $st = $conn->prepare("SELECT id_user, prenom, nom FROM users WHERE email = ? LIMIT 1");
            $st->bind_param("s", $email);
            $st->execute();
            $u = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$u) {
                $msg = "Aucun compte BOKONZI avec l'email « " . htmlspecialchars($email) . " ». La personne doit d'abord créer un compte (connexion Google).";
                $msgType = 'err';
            } else {
                $idUser    = (int)$u['id_user'];
                $periodEnd = $duration > 0 ? date('Y-m-d H:i:s', strtotime("+$duration months")) : null;
                $sql = "INSERT INTO subscriptions
                            (id_user, plan, status, billing_period, current_period_end, cancel_at_period_end, stripe_subscription_id)
                        VALUES (?, ?, 'active', 'manuel', ?, 0, NULL)
                        ON DUPLICATE KEY UPDATE
                            plan = VALUES(plan), status = 'active', billing_period = 'manuel',
                            current_period_end = VALUES(current_period_end), cancel_at_period_end = 0";
                $st = $conn->prepare($sql);
                $st->bind_param("iss", $idUser, $plan, $periodEnd);
                if ($st->execute()) {
                    $until = $duration > 0 ? "jusqu'au " . date('d/m/Y', strtotime($periodEnd)) : "à vie (illimité)";
                    $msg = "Accès « BOKONZI " . ucfirst($plan) . " » accordé à " . htmlspecialchars(trim($u['prenom'] . ' ' . $u['nom']) ?: $email) . " ($until).";
                    $msgType = 'ok';
                } else {
                    $msg = "Erreur SQL : " . htmlspecialchars($conn->error); $msgType = 'err';
                }
                $st->close();
            }
        }
    } elseif ($action === 'revoke') {
        $idUser = (int)($_POST['id_user'] ?? 0);
        if ($idUser > 0) {
            $st = $conn->prepare("UPDATE subscriptions SET status = 'canceled', cancel_at_period_end = 1, current_period_end = NOW() WHERE id_user = ?");
            $st->bind_param("i", $idUser);
            $st->execute();
            $st->close();
            $msg = "Accès retiré pour l'utilisateur #$idUser.";
            $msgType = 'ok';
        }
    } elseif ($action === 'sync') {
        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            $msg = "Indique l'email à vérifier."; $msgType = 'err';
        } else {
            $st = $conn->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
            $st->bind_param("s", $email);
            $st->execute();
            $u = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$u) {
                $msg = "Aucun compte avec cet email."; $msgType = 'err';
            } else {
                $res = bkSyncFromStripe($conn, (int)$u['id_user']);
                $msg = "Vérification Stripe : " . htmlspecialchars($res['message']);
                $msgType = ($res['ok'] && $res['active']) ? 'ok' : ($res['ok'] ? 'warn' : 'err');
            }
        }
    }
}

// ── Liste des abonnements ────────────────────────────────────────────────
$rows = [];
$res = $conn->query(
    "SELECT s.id_user, s.plan, s.status, s.billing_period, s.current_period_end,
            s.cancel_at_period_end, s.stripe_subscription_id, s.updated_at,
            u.email, u.prenom, u.nom
     FROM subscriptions s
     JOIN users u ON u.id_user = s.id_user
     ORDER BY s.updated_at DESC"
);
if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; }

$activeStatuses = ['active', 'trialing', 'past_due'];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Gestion des abonnements — BOKONZI Admin</title>
<style>
  *{box-sizing:border-box;}
  body{margin:0;background:#0d1117;color:#c9d1d9;font-family:Inter,system-ui,Arial,sans-serif;padding:28px 20px 60px;}
  a{color:#a78bfa;}
  h1{color:#fff;font-size:22px;margin:0 0 4px;}
  .sub{color:#8b949e;font-size:13px;margin:0 0 22px;}
  .card{background:#161b22;border:1px solid #1e2a3a;border-radius:14px;padding:20px 22px;margin-bottom:18px;max-width:980px;}
  .card h2{color:#fff;font-size:15px;margin:0 0 14px;}
  .msg{max-width:980px;margin:0 0 18px;padding:12px 16px;border-radius:10px;font-size:14px;font-weight:600;}
  .msg.ok{background:#10b98118;border:1px solid #10b98150;color:#34d399;}
  .msg.err{background:#f8514918;border:1px solid #f8514950;color:#ff7b72;}
  .msg.warn{background:#f59e0b18;border:1px solid #f59e0b50;color:#fbbf24;}
  form.row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
  .fg{display:flex;flex-direction:column;gap:5px;}
  .fg label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#8b949e;}
  input,select{background:#0d1117;border:1px solid #1e2a3a;color:#c9d1d9;border-radius:8px;padding:9px 12px;font-size:14px;font-family:inherit;}
  input:focus,select:focus{outline:none;border-color:#6c5ce7;}
  .btn{border:none;border-radius:8px;padding:10px 18px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;}
  .btn-grant{background:linear-gradient(135deg,#6c5ce7,#ec4899);color:#fff;}
  .btn-sync{background:#1f6feb;color:#fff;}
  .btn-revoke{background:transparent;border:1px solid #f8514955;color:#ff7b72;padding:5px 12px;font-size:12px;}
  .btn-revoke:hover{background:#f8514915;}
  table{width:100%;border-collapse:collapse;margin-top:6px;}
  th,td{text-align:left;padding:9px 10px;font-size:13px;border-bottom:1px solid #1e2a3a;}
  th{color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:.5px;}
  .badge{display:inline-block;padding:2px 9px;border-radius:100px;font-size:11px;font-weight:700;}
  .b-active{background:#10b98120;color:#34d399;}
  .b-dead{background:#f8514920;color:#ff7b72;}
  .b-manuel{background:#6c5ce720;color:#a78bfa;}
  .b-stripe{background:#1f6feb20;color:#79c0ff;}
  .muted{color:#5a6580;font-size:12px;}
</style>
</head>
<body>
  <h1>&#127941; Gestion des abonnements</h1>
  <p class="sub"><a href="panel.php">&larr; Retour au panel</a> &nbsp;·&nbsp; Accorder, retirer ou vérifier un accès premium.</p>

  <?php if ($msg): ?><div class="msg <?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

  <!-- Accorder un accès -->
  <div class="card">
    <h2>&#10133; Donner un accès manuellement</h2>
    <form class="row" method="post">
      <input type="hidden" name="action" value="grant">
      <div class="fg">
        <label>Email de l'utilisateur</label>
        <input type="email" name="email" placeholder="personne@email.com" required style="min-width:240px;">
      </div>
      <div class="fg">
        <label>Offre</label>
        <select name="plan">
          <?php foreach ($BK_PLANS as $pk => $p): ?>
          <option value="<?= $pk ?>"><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>Durée</label>
        <select name="duration">
          <option value="1">1 mois</option>
          <option value="3">3 mois</option>
          <option value="6">6 mois</option>
          <option value="12">12 mois</option>
          <option value="0">Illimité (à vie)</option>
        </select>
      </div>
      <button type="submit" class="btn btn-grant">Accorder l'accès</button>
    </form>
    <p class="muted" style="margin:12px 0 0;">La personne doit déjà avoir un compte BOKONZI (connexion Google). L'accès est immédiat.</p>
  </div>

  <!-- Vérifier un paiement -->
  <div class="card">
    <h2>&#128269; Vérifier un paiement auprès de Stripe</h2>
    <form class="row" method="post">
      <input type="hidden" name="action" value="sync">
      <div class="fg">
        <label>Email de l'utilisateur</label>
        <input type="email" name="email" placeholder="personne@email.com" required style="min-width:240px;">
      </div>
      <button type="submit" class="btn btn-sync">Vérifier &amp; synchroniser</button>
    </form>
    <p class="muted" style="margin:12px 0 0;">Interroge Stripe directement et met à jour la base — utile si un paiement n'apparaît pas (webhook en retard, email différent…).</p>
  </div>

  <!-- Liste -->
  <div class="card" style="max-width:980px;">
    <h2>&#128203; Abonnements en cours (<?= count($rows) ?>)</h2>
    <table>
      <tr><th>Utilisateur</th><th>Offre</th><th>Statut</th><th>Source</th><th>Échéance</th><th>MàJ</th><th></th></tr>
      <?php if (empty($rows)): ?>
      <tr><td colspan="7" class="muted">Aucun abonnement pour le moment.</td></tr>
      <?php else: foreach ($rows as $r):
        $isActive = in_array($r['status'], $activeStatuses, true)
                 && (empty($r['current_period_end']) || strtotime($r['current_period_end']) >= time());
        $isManuel = ($r['billing_period'] === 'manuel');
      ?>
      <tr>
        <td>
          <?= htmlspecialchars(trim($r['prenom'] . ' ' . $r['nom']) ?: '—') ?><br>
          <span class="muted"><?= htmlspecialchars($r['email']) ?></span>
        </td>
        <td><?= htmlspecialchars(ucfirst($r['plan'] ?: '—')) ?></td>
        <td><span class="badge <?= $isActive ? 'b-active' : 'b-dead' ?>"><?= $isActive ? 'Actif' : htmlspecialchars($r['status'] ?: 'inactif') ?></span></td>
        <td><span class="badge <?= $isManuel ? 'b-manuel' : 'b-stripe' ?>"><?= $isManuel ? 'Manuel' : 'Stripe' ?></span></td>
        <td><?= $r['current_period_end'] ? date('d/m/Y', strtotime($r['current_period_end'])) : '<span class="muted">illimité</span>' ?></td>
        <td class="muted"><?= $r['updated_at'] ? date('d/m/Y H:i', strtotime($r['updated_at'])) : '—' ?></td>
        <td>
          <?php if ($isActive): ?>
          <form method="post" onsubmit="return confirm('Retirer l\'accès de cet utilisateur ?');" style="margin:0;">
            <input type="hidden" name="action" value="revoke">
            <input type="hidden" name="id_user" value="<?= (int)$r['id_user'] ?>">
            <button type="submit" class="btn btn-revoke">Retirer l'accès</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </table>
  </div>
</body>
</html>
<?php $conn->close(); ?>
