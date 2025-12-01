<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$pdo = db();
$user = current_user();
$videoPath = '/training-media/Grok Tutorial.mp4';
$progressStmt = $pdo->prepare('SELECT part2_completed, last_video_view FROM user_progress WHERE user_id = ?');
$progressStmt->execute([$user['id']]);
$progress = $progressStmt->fetch() ?: ['part2_completed' => 0, 'last_video_view' => null];
$quizAnswered = (bool)$progress['part2_completed'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = [
        'q1' => strtoupper(trim($_POST['q1'] ?? '')),
        'q2' => strtoupper(trim($_POST['q2'] ?? '')),
        'q3' => strtoupper(trim($_POST['q3'] ?? '')),
    ];
    $correct = ['q1' => 'B', 'q2' => 'C', 'q3' => 'A'];
    foreach ($answers as $key => $value) {
        if ($value === '') {
            $errors[] = 'Answer every quiz question before submitting.';
            break;
        }
    }
    if (!$errors) {
        $allCorrect = true;
        foreach ($correct as $key => $expected) {
            if ($answers[$key] !== $expected) {
                $allCorrect = false;
                break;
            }
        }
        if ($allCorrect) {
            $stmt = $pdo->prepare(
                'INSERT INTO user_progress (user_id, part2_completed, last_video_view)
                 VALUES (:user_id, 1, NOW())
                 ON DUPLICATE KEY UPDATE part2_completed = VALUES(part2_completed), last_video_view = VALUES(last_video_view)'
            );
            $stmt->execute([':user_id' => $user['id']]);
            set_flash('Quiz complete. Offense module marked as finished.', 'success');
            redirect('/video.php');
        } else {
            $errors[] = 'Not quite right. Review the tutorial and try again.';
        }
    }
}

render_header('Deepfake Offense');
?>
<style>
.offense-grid {
    display: grid;
    gap: 1.5rem;
}
.quiz-question {
    margin-bottom: 1rem;
}
</style>
<section class="panel offense-grid">
    <h1>Deepfake Offense Module</h1>
    <article>
        <h3>1) What is Grok AI?</h3>
        <ul>
            <li>Grok is a generative AI chatbot from xAI that combines LLM chat with image/video generation, designed to be more permissive than competing models.</li>
        </ul>
    </article>
    <article>
        <h3>2) Advantages over Sora AI</h3>
        <ul>
            <li>Less filtered policies enable phishing-style prompts that Sora blocks.</li>
            <li>Can generate short scripted videos without hitting safety walls.</li>
        </ul>
    </article>
    <article>
        <h3>3) Limitations</h3>
        <ul>
            <li>Video output is less photorealistic and limited to still shots.</li>
            <li>Lower control over style/appearance; no voice cloning.</li>
        </ul>
    </article>
    <article>
        <h3>4) Tips for operators</h3>
        <ul>
            <li>Explicitly instruct the model to have the subject speak, quoting the dialogue.</li>
            <li>Keep scripts short—the clips run only a few seconds.</li>
        </ul>
    </article>
    <article>
        <h3>Grok tutorial walkthrough</h3>
        <video controls width="100%" preload="metadata">
            <source src="<?= h($videoPath) ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </article>
    <article>
        <h2>Knowledge check</h2>
        <?php if ($errors): ?>
            <div class="flash danger"><?= h(implode(' ', $errors)) ?></div>
        <?php endif; ?>
        <form method="post" class="quiz-form">
            <div class="quiz-question">
                <p>1. Which feature of Grok AI increases its misuse risk?</p>
                <?php $options1 = [
                    'A' => 'Limited ability to generate images',
                    'B' => 'Less restrictive content filters',
                    'C' => 'Lack of text-generation capability',
                    'D' => 'Inability to create video content',
                ]; ?>
                <?php foreach ($options1 as $value => $label): ?>
                    <label style="display:block;">
                        <input type="radio" name="q1" value="<?= $value ?>" required> <?= $value ?>. <?= h($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="quiz-question">
                <p>2. Why might Sora AI be less likely used for realistic phishing?</p>
                <?php $options2 = [
                    'A' => 'Sora offers lower-quality video',
                    'B' => 'Sora cannot generate spoken audio',
                    'C' => 'Sora blocks certain harmful prompts',
                    'D' => 'Sora requires advanced coding skills',
                ]; ?>
                <?php foreach ($options2 as $value => $label): ?>
                    <label style="display:block;">
                        <input type="radio" name="q2" value="<?= $value ?>" required> <?= $value ?>. <?= h($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="quiz-question">
                <p>3. Which limitation reduces Grok’s realism?</p>
                <?php $options3 = [
                    'A' => 'It only generates videos from still images',
                    'B' => 'It uses a large language model',
                    'C' => 'It can generate conversations too quickly',
                    'D' => 'It cannot generate written text',
                ]; ?>
                <?php foreach ($options3 as $value => $label): ?>
                    <label style="display:block;">
                        <input type="radio" name="q3" value="<?= $value ?>" required> <?= $value ?>. <?= h($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit"><?= $quizAnswered ? 'Resubmit answers' : 'Submit answers' ?></button>
        </form>
        <?php if ($progress['last_video_view']): ?>
            <p style="margin-top:1rem;">Last confirmed: <?= h($progress['last_video_view']) ?></p>
        <?php endif; ?>
    </article>
</section>
<?php
render_footer();

