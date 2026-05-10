<?php
/**
 * Layout HTML base. Usado pela welcome page, error page e qualquer
 * página que não use os layouts especializados (admin/public).
 *
 * @var string $title
 * @var string $bodyContent
 */
$pageTitle = $title ?? 'Arkham Files';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($pageTitle) ?> · Arkham Files</title>
    <link rel="stylesheet" href="/assets/css/arkham-fonts.css">
    <link rel="stylesheet" href="/assets/css/arkham.css">
    <?php if (!empty($extraCss) && is_array($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?= e($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<?= \ArkhamFiles\Icon::sprite() ?>
<?= $bodyContent ?? '' ?>
</body>
</html>
