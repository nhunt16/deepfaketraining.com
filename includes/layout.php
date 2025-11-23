<?php
declare(strict_types=1);

function build_nav_link(string $href, string $label, string $currentScript, bool $matchCurrent = true): string
{
    $targetScript = basename(parse_url($href, PHP_URL_PATH) ?? '') ?: trim($href, '/');
    $isActive = $matchCurrent && $targetScript !== '' && $targetScript === $currentScript;
    $classes = ['nav-link'];
    if ($isActive) {
        $classes[] = 'nav-link-active';
    }

    return sprintf(
        '<a class="%s" href="%s">%s</a>',
        implode(' ', $classes),
        $href,
        h($label)
    );
}

function render_header(string $title = 'Deepfake Defense'): void
{
    global $config;
    $appName = $config['app']['name'] ?? 'Deepfake Defense Training';
    $user = current_user();
    $flash = get_flash();
    $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '') ?: 'index.php';
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?= h($title) ?> · <?= h($appName) ?></title>
        <link rel="stylesheet" href="/assets/styles.css" />
    </head>
    <body>
    <header>
        <div class="logo"><?= h($appName) ?></div>
        <nav>
            <?php if ($user): ?>
                <?= build_nav_link('/dashboard.php', 'Dashboard', $currentScript) ?>
                <?= build_nav_link('/game.php', 'Game', $currentScript) ?>
                <?= build_nav_link('/video.php', 'Video', $currentScript) ?>
                <?= build_nav_link('/console.php', 'Console', $currentScript) ?>
                <?php if ((int)$user['is_admin'] === 1): ?>
                    <?= build_nav_link('/admin.php', 'Admin', $currentScript) ?>
                <?php endif; ?>
                <?= build_nav_link('/logout.php', 'Logout (' . $user['username'] . ')', $currentScript, false) ?>
            <?php else: ?>
                <?= build_nav_link('/login.php', 'Sign In', $currentScript) ?>
                <?= build_nav_link('/register.php', 'Create Account', $currentScript) ?>
            <?php endif; ?>
        </nav>
    </header>
    <main>
        <?php if ($flash): ?>
            <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    </body>
    </html>
    <?php
}

