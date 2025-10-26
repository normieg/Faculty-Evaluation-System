<?php
/** @var array<string, string> $errors */
/** @var array<string, string> $old */
/** @var string $action */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Repair Request</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <style>
        body { padding: 2rem; }
        form { max-width: 40rem; margin: 0 auto; }
        .error { color: #9d0208; font-size: 0.9rem; margin-top: .25rem; }
    </style>
</head>
<body>
<main class="container">
    <header>
        <h1>Submit a repair request</h1>
        <p><a href="/">Back to repairs</a></p>
    </header>

    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
        <label for="staff_name">Staff name</label>
        <input type="text"
               id="staff_name"
               name="staff_name"
               value="<?= htmlspecialchars($old['staff_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               required>
        <?php if (isset($errors['staff_name'])): ?>
            <p class="error"><?= htmlspecialchars($errors['staff_name'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <label for="location">Location</label>
        <input type="text"
               id="location"
               name="location"
               value="<?= htmlspecialchars($old['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               required>
        <?php if (isset($errors['location'])): ?>
            <p class="error"><?= htmlspecialchars($errors['location'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <label for="issue">Issue description</label>
        <textarea id="issue" name="issue" rows="6" required><?php
            echo htmlspecialchars($old['issue'] ?? '', ENT_QUOTES, 'UTF-8');
        ?></textarea>
        <?php if (isset($errors['issue'])): ?>
            <p class="error"><?= htmlspecialchars($errors['issue'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <button type="submit">Submit request</button>
    </form>
</main>
</body>
</html>
