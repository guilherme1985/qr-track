<?php
/**
 * Layout base.
 *
 * @var string $title
 * @var string $bodyContent  HTML já renderizado para o <body>
 */
$pageTitle = $title ?? 'Arkham Files';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> · Arkham Files</title>
    <link rel="stylesheet" href="/assets/css/arkham.css">
</head>
<body>
<?= $bodyContent ?? '' ?>
</body>
</html>
