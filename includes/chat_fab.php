<?php
/**
 * Floating chat / message FAB (bottom-right).
 * Matches the blue circular chat icon style from the reference screenshot.
 * Include once before </body> on public pages.
 *
 * Usage: <?php require __DIR__ . '/includes/chat_fab.php'; ?>
 */
if (defined('CHAT_FAB_INCLUDED')) {
    return;
}
define('CHAT_FAB_INCLUDED', true);

$chatFabHref = isset($chatFabHref) ? $chatFabHref : 'track.php#contact-supervisor';
$chatFabTitle = isset($chatFabTitle) ? $chatFabTitle : (function_exists('t') ? t('contact supervisor') : 'Ergaa');
$chatFabBadge = isset($chatFabBadge) ? (int)$chatFabBadge : 0;
?>
<link rel="stylesheet" href="assets/chat-fab.css">
<div class="chat-fab-wrap">
  <a href="<?= htmlspecialchars($chatFabHref) ?>" class="chat-fab footer-fab footer-fab--message" aria-label="<?= htmlspecialchars($chatFabTitle) ?>" title="<?= htmlspecialchars($chatFabTitle) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    <?php if ($chatFabBadge > 0): ?>
      <span class="badge" data-count="<?= $chatFabBadge ?>"><?= $chatFabBadge > 99 ? '99+' : $chatFabBadge ?></span>
    <?php endif; ?>
  </a>
</div>
