<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$links = [
    ['name' => 'FreeSound', 'url' => 'https://freesound.org/'],
    ['name' => 'NotebookLM', 'url' => 'https://notebooklm.google.com/'],
    ['name' => 'Grok', 'url' => 'https://grok.com/'],
    ['name' => 'Sora', 'url' => 'https://openai.com/sora/'],
];

render_header('Appendix');
?>
<section class="panel">
    <h1>Appendix</h1>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Link</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($links as $link): ?>
                <tr>
                    <td><?= h($link['name']) ?></td>
                    <td><a href="<?= h($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($link['url']) ?></a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php
render_footer();

