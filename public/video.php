<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$pdo = db();
$user = current_user();
$grokVideoPath = '/static_media/GrokTutorial.mp4';
$soraVideoPath = '/static_media/SoraTutorial.mp4';
$offenseStmt = $pdo->prepare('SELECT offense_modules FROM user_progress WHERE user_id = ?');
$offenseStmt->execute([$user['id']]);
$offenseRaw = $offenseStmt->fetchColumn();
$moduleState = $offenseRaw ? json_decode($offenseRaw, true) : [];
if (!is_array($moduleState)) {
    $moduleState = [];
}
$moduleState += ['module1' => null, 'module2' => null];
foreach (array_keys($moduleState) as $key) {
    if ($moduleState[$key] === true) {
        $moduleState[$key] = null;
    }
}
$errors = [];
$moduleErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $module = $_POST['module'] ?? '';
        $quizMap = [
            'module1' => ['q1' => 'B', 'q2' => 'C', 'q3' => 'A'],
            'module2' => ['q1' => 'B', 'q2' => 'C', 'q3' => 'D', 'q4' => 'B', 'q5' => 'B', 'q6' => 'C'],
        ];
    if (!isset($quizMap[$module])) {
        $errors[] = 'Unknown module submission.';
    } else {
        $answers = [];
        foreach ($quizMap[$module] as $key => $_) {
            $answers[$key] = strtoupper(trim($_POST[$key] ?? ''));
        }
        foreach ($answers as $value) {
            if ($value === '') {
                $moduleErrors[$module] = 'Answer every quiz question before submitting.';
                break;
            }
        }
        if (empty($moduleErrors[$module])) {
            $allCorrect = true;
            foreach ($quizMap[$module] as $key => $expected) {
                if ($answers[$key] !== $expected) {
                    $allCorrect = false;
                    break;
                }
            }
            if ($allCorrect) {
                $moduleState[$module] = date('Y-m-d H:i:s');
                $stateJson = json_encode($moduleState);
                $stmt = $pdo->prepare(
                    'INSERT INTO user_progress (user_id, offense_modules) VALUES (:user_id, :state)
                     ON DUPLICATE KEY UPDATE offense_modules = VALUES(offense_modules)'
                );
                $stmt->execute([':user_id' => $user['id'], ':state' => $stateJson]);
                set_flash('Quiz complete. Offense module marked as finished.', 'success');
                redirect('/video.php');
            } else {
                $moduleErrors[$module] = 'Not quite right. Review the tutorial and try again.';
            }
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
.task-status-anchor {
    position: relative;
}
.task-status-chip {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.6rem;
    font-size: 0.85rem;
    font-weight: 500;
    border-radius: 999px;
    background: rgba(5, 6, 10, 0.65);
    border: 1px solid currentColor;
}
.task-status-chip[data-state="completed"] {
    color: #14b886;
}
.task-status-chip[data-state="pending"] {
    color: #f97316;
}
</style>
<section class="panel offense-grid task-status-anchor">
    <div class="task-status-chip" data-state="<?= !empty($moduleState['module1']) ? 'completed' : 'pending' ?>">
        <span class="task-status-text"><?= !empty($moduleState['module1']) ? 'Completed' : 'Pending' ?></span>
        <?php if (!empty($moduleState['module1'])): ?>
            <small><?= h(date('M j, Y g:i a T', strtotime($moduleState['module1']))) ?></small>
        <?php endif; ?>
    </div>
    <h1>Deepfake Offense Module I · Grok AI</h1>
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
            <source src="<?= h($grokVideoPath) ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </article>
    <article>
        <h2>Knowledge check</h2>
        <?php if (!empty($moduleErrors['module1'])): ?>
            <div class="flash danger"><?= h($moduleErrors['module1']) ?></div>
        <?php endif; ?>
        <form method="post" class="quiz-form">
            <input type="hidden" name="module" value="module1">
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
            <button type="submit"><?= $moduleState['module1'] ? 'Resubmit answers' : 'Submit answers' ?></button>
        </form>
    </article>
    </section>

<section class="panel offense-grid task-status-anchor" style="margin-top:2rem;">
    <div class="task-status-chip" data-state="<?= !empty($moduleState['module2']) ? 'completed' : 'pending' ?>">
        <span class="task-status-text"><?= !empty($moduleState['module2']) ? 'Completed' : 'Pending' ?></span>
        <?php if (!empty($moduleState['module2'])): ?>
            <small><?= h(date('M j, Y g:i a T', strtotime($moduleState['module2']))) ?></small>
        <?php endif; ?>
    </div>
    <h1>Deepfake Offense Module II · Sora AI</h1>
    <article>
        <h3>1) What is phishing?</h3>
        <p>Phishing is a cyberattack where adversaries impersonate trusted entities to trick victims into surrendering credentials, financial data, or system access via email, SMS, phone, or social media.</p>
    </article>
    <article>
        <h3>2) What is Sora.AI?</h3>
        <p>Sora is OpenAI’s text-to-video system that generates cinematic clips with realistic motion, lighting, and scenes. It’s heavily safeguarded to block harmful prompts.</p>
    </article>
    <article>
        <h3>3) How AI video aids phishing</h3>
        <ul>
            <li>Use deepfake-style videos to impersonate executives or customers and boost credibility.</li>
            <li>Embed short Sora clips in emails or chats to make malicious links seem authentic.</li>
            <li>Create synthetic accident/disaster footage to drive urgent scam responses.</li>
        </ul>
    </article>
    <article>
        <h3>Sora limitations & policy constraints</h3>
        <p>OpenAI blocks prompts involving real people without consent, explicit/violent content, illegal activity, political misinformation, and hyper-real impersonations. Attackers must stay within policy while pairing videos with other phishing levers.</p>
    </article>
    <article>
        <h3>Sora tutorial walkthrough</h3>
        <video controls width="100%" preload="metadata">
            <source src="<?= h($soraVideoPath) ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </article>
    <article>
        <h2>Module II knowledge check</h2>
        <?php if (!empty($moduleErrors['module2'])): ?>
            <div class="flash danger"><?= h($moduleErrors['module2']) ?></div>
        <?php endif; ?>
        <form method="post" class="quiz-form">
            <input type="hidden" name="module" value="module2">
            <?php
            $module2Questions = [
                'q1' => [
                    'question' => '1. What is phishing?',
                    'options' => [
                        'A' => 'A harmless marketing technique',
                        'B' => 'A cyberattack designed to steal sensitive information',
                        'C' => 'A way to improve video quality',
                        'D' => 'A method for updating software',
                    ],
                ],
                'q2' => [
                    'question' => '2. Sora.AI is best described as:',
                    'options' => [
                        'A' => 'A password-stealing tool',
                        'B' => 'A deepfake detector',
                        'C' => 'An AI video generator created by OpenAI',
                        'D' => 'A cybersecurity scanner',
                    ],
                ],
                'q3' => [
                    'question' => '3. Which request violates Sora’s safety guidelines?',
                    'options' => [
                        'A' => 'A fictional character in a fantasy world',
                        'B' => 'A landscape scene with mountains',
                        'C' => 'A cartoon cat playing guitar',
                        'D' => 'A real person without their consent',
                    ],
                ],
                'q4' => [
                    'question' => '4. Why might phishing attackers use AI-generated videos?',
                    'options' => [
                        'A' => 'Because the videos automatically block all scams',
                        'B' => 'To make scam messages seem more realistic and trustworthy',
                        'C' => 'To replace all written phishing attempts',
                        'D' => 'To encrypt user data',
                    ],
                ],
                'q5' => [
                    'question' => '5. Which of the following violates Sora safety guidelines?',
                    'options' => [
                        'A' => 'A video of a harmless conversation',
                        'B' => 'A video of copyrighted characters like Spider-Man',
                        'C' => 'A video of a robot cooking',
                        'D' => 'A video describing a fictional planet',
                    ],
                ],
                'q6' => [
                    'question' => '6. How can Sora-generated videos assist phishing without breaking policy?',
                    'options' => [
                        'A' => 'By generating explicit impersonations of private individuals',
                        'B' => 'By creating realistic videos of real harmful events',
                        'C' => 'By adding legitimacy to scam emails or fake profiles without violating guidelines',
                        'D' => 'By generating political misinformation',
                    ],
                ],
            ];
            ?>
            <?php foreach ($module2Questions as $name => $payload): ?>
                <div class="quiz-question">
                    <p><?= h($payload['question']) ?></p>
                    <?php foreach ($payload['options'] as $value => $label): ?>
                        <label style="display:block;">
                            <input type="radio" name="<?= h($name) ?>" value="<?= $value ?>" required> <?= $value ?>. <?= h($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit"><?= $moduleState['module2'] ? 'Resubmit answers' : 'Submit answers' ?></button>
        </form>
    </article>
</section>
<?php
render_footer();

