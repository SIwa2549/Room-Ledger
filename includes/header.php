<?php
/**
 * ไฟล์: includes/header.php
 * ส่วนหัวของทุกหน้าหลังล็อกอิน: <head>, Sidebar (คอมพิวเตอร์) และข้อความแจ้งผล (Flash)
 * ก่อน include ให้กำหนด:
 *   $pageTitle = 'ห้องพัก';   // ชื่อหน้า
 *   $activeNav = 'rooms';     // เมนูที่กำลังเปิด
 */

$pageTitle = $pageTitle ?? 'ระบบจัดการหอพัก';
$activeNav = $activeNav ?? '';

// เมนูหลักแยกตามบทบาท: เจ้าของหอเห็นทุกเมนู ผู้เช่าเห็นเฉพาะบิลของตัวเอง
// (url = null = หน้าที่ยังไม่เปิดใช้ จะแสดงเป็นเมนูจาง ๆ กดไม่ได้)
$icon = [
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'rooms'     => '<path d="M5 21V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v17"/><path d="M3 21h18M15 12h.01"/>',
    'tenants'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
    'bills'     => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
    'rates'     => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
    'account'   => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
];
$isOwner = ($currentUser['role'] ?? 'owner') === 'owner';
$navItems = $isOwner ? [
    'dashboard' => ['label' => 'ภาพรวม',          'short' => 'ภาพรวม',  'url' => 'dashboard.php', 'icon' => $icon['dashboard']],
    'rooms'     => ['label' => 'ห้องพัก',          'short' => 'ห้องพัก', 'url' => 'rooms.php',     'icon' => $icon['rooms']],
    'tenants'   => ['label' => 'ผู้เช่า',           'short' => 'ผู้เช่า',  'url' => 'tenants.php',   'icon' => $icon['tenants']],
    'bills'     => ['label' => 'จดมิเตอร์และบิล',   'short' => 'บิล',     'url' => 'bills.php',     'icon' => $icon['bills']],
    'rates'     => ['label' => 'อัตราค่าบริการ',    'short' => 'อัตรา',   'url' => 'rates.php',     'icon' => $icon['rates']],
    'account'   => ['label' => 'บัญชีของฉัน',      'short' => 'บัญชี',   'url' => 'account.php',   'icon' => $icon['account']],
] : [
    'my_bills'  => ['label' => 'บิลของฉัน',        'short' => 'บิลของฉัน', 'url' => 'my_bills.php', 'icon' => $icon['bills']],
    'account'   => ['label' => 'บัญชีของฉัน',      'short' => 'บัญชี',     'url' => 'account.php',  'icon' => $icon['account']],
];

/** วาดลิงก์เมนู 1 อัน ใช้ทั้ง Sidebar และ Bottom Navigation */
function nav_item(string $key, array $item, string $active, string $class, bool $short = false): string
{
    $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">' . $item['icon'] . '</svg>';
    $label = e($short ? $item['short'] : $item['label']);
    if ($item['url'] === null) {
        return '<span class="' . $class . '" aria-disabled="true" title="ยังไม่เปิดใช้ (เฟสถัดไป)">' . $svg . $label . '</span>';
    }
    $current = $key === $active ? ' aria-current="page"' : '';
    return '<a class="' . $class . '" href="' . e($item['url']) . '"' . $current . '>' . $svg . $label . '</a>';
}

$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?> | ระบบจัดการหอพัก</title>

    <!-- Bootstrap 5 (CDN) ตามข้อกำหนด และ app.css ต้องโหลดหลังสุดเพื่อให้สไตล์ของเราชนะ -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="app">
    <aside class="sidebar" aria-label="เมนูหลัก">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 21V5l8-3 8 3v16"/><path d="M9 21v-5h6v5M8 8h2M14 8h2M8 12h2M14 12h2"/></svg></span>
            <span><span class="brand-name" style="display:block">จัดการหอพัก</span><span class="brand-sub" style="display:block">Dormitory System</span></span>
        </div>
        <?php foreach ($navItems as $key => $item): ?>
            <?= nav_item($key, $item, $activeNav, 'nav-link') ?>
        <?php endforeach; ?>
        <div class="sidebar-foot">
            <span><?= $isOwner ? 'เจ้าของหอ' : 'ผู้เช่า' ?></span>
            <span class="muted"><?= e($currentUser['email'] ?? '') ?></span>
            <a class="btn btn-sm" href="logout.php">ออกจากระบบ</a>
        </div>
    </aside>

    <main class="main">
        <div class="main-inner">
            <?php if ($flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
            <?php endif; ?>
