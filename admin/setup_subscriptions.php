<?php
/**
 * admin/setup_subscriptions.php — Migration BDD pour les abonnements Stripe
 *
 * À ouvrir UNE FOIS dans le navigateur : https://bokonzi.com/admin/setup_subscriptions.php
 * (ou en local : http://localhost/BK/admin/setup_subscriptions.php)
 *
 * Crée :
 *   - table `subscriptions`   : abonnement courant par utilisateur (source de vérité locale)
 *   - table `stripe_events`   : idempotence des webhooks (un event traité une seule fois)
 *   - colonne `users.stripe_customer_id`
 *
 * 100 % idempotent : peut être relancé sans risque.
 */

require_once __DIR__ . '/../core/db.php'; // fournit $conn

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">';
echo '<title>Setup abonnements — BOKONZI</title>';
echo '<style>body{font-family:system-ui,Arial,sans-serif;background:#0d1117;color:#c9d1d9;padding:32px;line-height:1.6;}';
echo 'h1{color:#a78bfa;} .ok{color:#34d399;} .skip{color:#8b949e;} .err{color:#f85149;}';
echo 'code{background:#161b22;padding:2px 6px;border-radius:4px;}</style></head><body>';
echo '<h1>⚙️ Setup abonnements BOKONZI</h1>';

function step($conn, $label, $sql) {
    echo '<p>';
    if ($conn->query($sql)) {
        echo '<span class="ok">✔</span> ' . htmlspecialchars($label);
    } else {
        echo '<span class="err">✘</span> ' . htmlspecialchars($label) . ' — ' . htmlspecialchars($conn->error);
    }
    echo '</p>';
}

// ── 1) Table subscriptions ────────────────────────────────────────────────
step($conn, 'Table `subscriptions`', "
    CREATE TABLE IF NOT EXISTS `subscriptions` (
        `id_subscription`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_user`                INT UNSIGNED NOT NULL,
        `stripe_customer_id`     VARCHAR(255) DEFAULT NULL,
        `stripe_subscription_id` VARCHAR(255) DEFAULT NULL,
        `plan`                   VARCHAR(20)  NOT NULL DEFAULT '',
        `status`                 VARCHAR(30)  NOT NULL DEFAULT '',
        `billing_period`         VARCHAR(10)  DEFAULT '',
        `current_period_end`     DATETIME     DEFAULT NULL,
        `cancel_at_period_end`   TINYINT(1)   NOT NULL DEFAULT 0,
        `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_sub_user` (`id_user`),
        UNIQUE KEY `uk_sub_stripe` (`stripe_subscription_id`),
        KEY `idx_sub_status` (`status`),
        KEY `idx_sub_customer` (`stripe_customer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// FK subscriptions → users (ignore l'erreur si déjà posée)
$fkExists = $conn->query("
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions'
      AND CONSTRAINT_NAME = 'fk_sub_user'
");
if ($fkExists && $fkExists->num_rows === 0) {
    step($conn, 'Clé étrangère `subscriptions.id_user` → `users`',
        "ALTER TABLE `subscriptions`
         ADD CONSTRAINT `fk_sub_user` FOREIGN KEY (`id_user`)
         REFERENCES `users`(`id_user`) ON DELETE CASCADE ON UPDATE CASCADE");
} else {
    echo '<p><span class="skip">→</span> Clé étrangère `fk_sub_user` déjà présente</p>';
}

// ── 2) Table stripe_events (idempotence des webhooks) ────────────────────
step($conn, 'Table `stripe_events`', "
    CREATE TABLE IF NOT EXISTS `stripe_events` (
        `id`          VARCHAR(255) NOT NULL PRIMARY KEY,
        `type`        VARCHAR(100) DEFAULT '',
        `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── 3) Colonne users.stripe_customer_id ──────────────────────────────────
$col = $conn->query("SHOW COLUMNS FROM `users` LIKE 'stripe_customer_id'");
if ($col && $col->num_rows === 0) {
    step($conn, 'Colonne `users.stripe_customer_id`',
        "ALTER TABLE `users` ADD COLUMN `stripe_customer_id` VARCHAR(255) DEFAULT NULL AFTER `oauth_provider`");
    step($conn, 'Index `users.stripe_customer_id`',
        "ALTER TABLE `users` ADD INDEX `idx_users_stripe` (`stripe_customer_id`)");
} else {
    echo '<p><span class="skip">→</span> Colonne `users.stripe_customer_id` déjà présente</p>';
}

echo '<h2 class="ok">✅ Terminé</h2>';
echo '<p>Tu peux maintenant remplir <code>core/stripe_config.php</code> avec tes clés Stripe ';
echo 'et tes identifiants de prix, puis tester la page <code>/tarifs</code>.</p>';
echo '<p style="color:#8b949e;font-size:13px;">Ce script est idempotent — inutile de le relancer, ';
echo 'mais ce n\'est pas grave si tu le fais.</p>';
echo '</body></html>';

$conn->close();
