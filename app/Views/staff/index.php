<?php
/** @var array<int, array<string, mixed>> $repairs */
/** @var string|null $flash */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Repairs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <style>
        body { padding: 2rem; }
        table { width: 100%; }
    </style>
</head>
<body>
<main class="container">
    <header>
        <h1>Repair Requests</h1>
        <p><a href="/repairs/new">Submit a new repair request</a></p>
    </header>

    <?php if ($flash): ?>
        <article class="success">
            <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
        </article>
    <?php endif; ?>

    <?php if ($repairs === []): ?>
        <p>No repair requests have been submitted yet.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Staff Member</th>
                <th>Location</th>
                <th>Issue</th>
                <th>Reported</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($repairs as $repair): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $repair['id'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($repair['staff_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($repair['location'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= nl2br(htmlspecialchars($repair['issue'], ENT_QUOTES, 'UTF-8')) ?></td>
                    <td><?= htmlspecialchars($repair['reported_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($repair['status'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>
</body>
</html>
