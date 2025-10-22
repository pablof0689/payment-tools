<?php
declare(strict_types=1);

/**
 * Render the shared template for all pages.
 */
function renderPage(string $title, callable $contentRenderer): void
{
    $menuItems = require __DIR__ . '/menu.php';
    $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($title) ?> | Payment Tools</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    </head>
    <body>
        <div class="layout">
            <header class="site-header">
                <div class="sidebar-top">
                    <div class="branding">
                        <span class="site-logo">PT</span>
                        <div class="site-meta">
                            <h1 class="site-title">Payment Tools</h1>
                            <p class="site-subtitle">Panel de utilidades para soporte de gateway</p>
                        </div>
                    </div>
                    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
                        <span class="sr-only">Abrir menú</span>
                        <span class="menu-bar"></span>
                        <span class="menu-bar"></span>
                        <span class="menu-bar"></span>
                    </button>
                </div>
                <nav id="primary-navigation" class="site-nav" aria-label="Navegación principal">
                    <ul>
                        <?php foreach ($menuItems as $item): ?>
                            <?php $isActive = $currentScript === basename($item['href']); ?>
                            <li class="nav-item<?= $isActive ? ' active' : '' ?>">
                                <a href="<?= htmlspecialchars($item['href']) ?>">
                                    <span><?= htmlspecialchars($item['label']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </header>
            <div class="layout-main">
                <main class="site-main">
                    <div class="content-wrapper">
                        <?php $contentRenderer(); ?>
                    </div>
                </main>
                <footer class="site-footer">
                    <p>Estas herramientas fueron diseñadas y desarrolladas por Pablo Fernández.
                        Visita <a href="https://pablof.uy" target="_blank" rel="noopener">pablof.uy</a> para conocer más.</p>
                </footer>
            </div>
        </div>
        <script src="assets/js/main.js"></script>
    </body>
    </html>
    <?php
}
