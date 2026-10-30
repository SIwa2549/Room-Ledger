<?php
/**
 * ไฟล์: includes/footer.php
 * ปิดเนื้อหาหลัก + Bottom Navigation (มือถือ) ใช้คู่กับ includes/header.php
 */
?>
        </div>
    </main>

    <nav class="bottom-nav" aria-label="เมนูหลัก">
        <?php foreach ($navItems as $key => $item): ?>
            <?= nav_item($key, $item, $activeNav, 'bottom-link', true) ?>
        <?php endforeach; ?>
    </nav>
</div>
</body>
</html>
