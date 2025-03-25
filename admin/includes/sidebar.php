<!-- Αλλαγή στο dropdown -->
<li class="nav-item nav-dropdown">
    <a href="#" class="nav-link dropdown-toggle <?= strpos($_SERVER['PHP_SELF'], '/test/') !== false ? 'active' : '' ?>">
        <i class="nav-icon">🧩</i>Διαχείριση Τεστ
    </a>
    <ul class="dropdown-menu">
        <li>
            <a href="<?= $config['base_url'] ?>/admin/test/quizzes.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'quizzes.php') !== false ? 'active' : '' ?>">
                <i class="nav-icon">📋</i>Διαχείριση Τεστ
            </a>
        </li>
        <li>
            <a href="<?= $config['base_url'] ?>/admin/test/manage_questions.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'manage_questions.php') !== false ? 'active' : '' ?>">
                <i class="nav-icon">❓</i>Ερωτήσεις
            </a>
        </li>
        <li>
            <a href="<?= $config['base_url'] ?>/admin/test/bulk_import.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'bulk_import.php') !== false ? 'active' : '' ?>">
                <i class="nav-icon">📥</i>Μαζική Εισαγωγή
            </a>
        </li>
        <li>
            <a href="<?= $config['base_url'] ?>/admin/test/manage_subcategories.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'manage_subcategories.php') !== false ? 'active' : '' ?>">
                <i class="nav-icon">📑</i>Υποκατηγορίες
            </a>
        </li>
        <li>
            <a href="<?= $config['base_url'] ?>/admin/test/manage_chapters.php" class="dropdown-item <?= strpos($_SERVER['PHP_SELF'], 'manage_chapters.php') !== false ? 'active' : '' ?>">
                <i class="nav-icon">📚</i>Κεφάλαια
            </a>
        </li>
    </ul>
</li>