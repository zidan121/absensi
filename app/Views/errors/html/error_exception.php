<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Application Error') ?></title>
    <style>
        body{font-family:system-ui,Arial,sans-serif;margin:0;background:#f4f6f8;color:#172033}
        main{max-width:1000px;margin:32px auto;padding:24px;background:#fff;border-radius:12px}
        h1{font-size:22px;color:#b42318}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f6f7f9;padding:16px;border-radius:8px}
        .muted{color:#667085;font-size:14px}
    </style>
</head>
<body>
<main>
    <h1><?= esc($title ?? 'Application Error') ?></h1>
    <p><?= nl2br(esc($message ?? 'An unexpected error occurred.')) ?></p>
    <?php if (isset($file, $line)) : ?>
        <p class="muted"><?= esc(clean_path($file)) ?> : <?= esc((string) $line) ?></p>
    <?php endif; ?>
    <?php if (isset($exception)) : ?>
        <?php $previous = $exception->getPrevious(); ?>
        <?php if ($previous !== null) : ?>
            <h2>Underlying error</h2>
            <pre><?= esc($previous::class . ': ' . $previous->getMessage() . "
" . clean_path($previous->getFile()) . ':' . $previous->getLine()) ?></pre>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (defined('SHOW_DEBUG_BACKTRACE') && SHOW_DEBUG_BACKTRACE && isset($trace)) : ?>
        <h2>Backtrace</h2>
        <pre><?php foreach ($trace as $index => $item) : ?>#<?= $index + 1 ?> <?= esc(($item['file'] ?? '[internal]') . ':' . ($item['line'] ?? '') . ' ' . ($item['class'] ?? '') . ($item['type'] ?? '') . ($item['function'] ?? '')) . "\n" ?><?php endforeach; ?></pre>
    <?php endif; ?>
</main>
</body>
</html>
