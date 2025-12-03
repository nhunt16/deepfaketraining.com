<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$links = [
    ['name' => 'FreeSound', 'url' => 'https://freesound.org/', 'label' => 'Audio assets'],
    ['name' => 'NotebookLM', 'url' => 'https://notebooklm.google.com/', 'label' => 'Gen-AI Audio'],
    ['name' => 'Google Chirp 3', 'url' => 'https://docs.cloud.google.com/speech-to-text/docs/models/chirp-3', 'label' => 'Gen-AI Audio'],
    ['name' => 'Google WaveNet', 'url' => 'https://deepmind.google/research/wavenet/', 'label' => 'Gen-AI Audio'],
    ['name' => 'Google Neural2 ', 'url' => 'https://docs.cloud.google.com/text-to-speech/docs/list-voices-and-types#neural2_voices', 'label' => 'Gen-AI Audio'],
    ['name' => 'Grok', 'url' => 'https://grok.com/', 'label' => 'Gen-AI Video'],
    ['name' => 'Sora', 'url' => 'https://openai.com/sora/', 'label' => 'Gen-AI Video'],
    ['name' => 'Mitek IDLive Voice', 'url' => 'https://www.miteksystems.com/products/voice-liveness-detection', 'label' => 'Deepfake Detection Tools'],
    ['name' => 'Veridas Voice Shield', 'url' => 'https://veridas.com/en/voice-shield/', 'label' => 'Deepfake Detection Tools'],
    ['name' => 'Hive AI', 'url' => 'https://thehive.ai/', 'label' => 'Deepfake Detection Tools'],
    ['name' => 'McAfee Deepfake Detector', 'url' => 'https://www.mcafee.com/ai/deepfake-detector/', 'label' => 'Deepfake Detection Tools'],
    ['name' => 'Truepic', 'url' => 'https://www.truepic.com/', 'label' => 'Deepfake Detection Tools'],
    ['name' => 'Reality Defender', 'url' => 'https://www.realitydefender.com/', 'label' => 'Deepfake Detection Tools'],
    ['name' => 'Sensity AI', 'url' => 'https://sensity.ai/', 'label' => 'Deepfake Detection Tools'],
    ['name' => 'Resemble Detect', 'url' => 'https://www.resemble.ai/detect/', 'label' => 'Deepfake Detection Tools'],
    ['name' => 'Pindrop', 'url' => 'https://www.pindrop.com/', 'label' => 'Deepfake Detection Tools'],
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
                <th>Label</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($links as $link): ?>
                <tr>
                    <td><?= h($link['name']) ?></td>
                    <td><a href="<?= h($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($link['url']) ?></a></td>
                    <td><?= h($link['label'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<div class="intro-scale-note" style="margin-top:1rem; display:flex; justify-content:center;">
    <span role="img" aria-label="warning">⚠️</span>
    deepfaketraining.net code and content developed with the assistance of Gen-AI, including chatgpt.com and grok.com.
</div>
<?php
render_footer();

