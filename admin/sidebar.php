<?php
/**
 * Admin Sidebar Navigation
 * Included by all admin pages
 */
$currentPhpSelf = serverString('PHP_SELF');
$currentPage = basename($currentPhpSelf);
$currentDir  = basename(dirname($currentPhpSelf));
?>
<aside class="admin-sidebar">
  <div style="padding:1rem 1.25rem; border-bottom:1px solid var(--border); margin-bottom:0.5rem;">
    <a href="/" style="display:flex;align-items:center;gap:0.5rem;">
      <img src="<?= e(getLogoAssetUrl()) ?>" alt="RedWater" style="height:32px;" onerror="this.style.display='none'">
      <span style="font-family:var(--font-head);font-size:0.85rem;"><span style="color:var(--red);">Red</span><span style="color:var(--blue);">Water</span></span>
    </a>
  </div>

  <p class="admin-sidebar-heading">Dashboard</p>
  <a href="/admin/" class="admin-nav-link <?= ($currentPage === 'index.php' && $currentDir === 'admin') ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Overview
  </a>

  <p class="admin-sidebar-heading">Site Content</p>
  <a href="/admin/tickets.php" class="admin-nav-link <?= $currentPage === 'tickets.php' ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/></svg>
    Tickets
  </a>
  <a href="/admin/policies.php" class="admin-nav-link <?= $currentPage === 'policies.php' ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
    Policies
  </a>
  <a href="/admin/gallery.php" class="admin-nav-link <?= $currentPage === 'gallery.php' ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    Gallery
  </a>
  <a href="/admin/sponsors.php" class="admin-nav-link <?= $currentPage === 'sponsors.php' ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Sponsors
  </a>
  <a href="/admin/contact.php" class="admin-nav-link <?= $currentPage === 'contact.php' ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    Contact Settings
  </a>

  <p class="admin-sidebar-heading">Users</p>
  <a href="/admin/members.php" class="admin-nav-link <?= $currentPage === 'members.php' ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Members
  </a>
  <a href="/admin/profile.php" class="admin-nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    My Profile
  </a>

  <div style="padding:1rem 1.25rem;margin-top:auto;border-top:1px solid var(--border);margin-top:2rem;">
    <a href="/logout.php" class="admin-nav-link" style="color:var(--text-muted);">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </a>
  </div>
</aside>
