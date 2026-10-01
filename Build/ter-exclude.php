<?php

declare(strict_types=1);

use TYPO3\Tailor\Service\VersionService;

$tailorDirectory = dirname((new ReflectionClass(VersionService::class))->getFileName(), 3);
$exclusions = require $tailorDirectory . '/conf/ExcludeFromPackaging.php';

$exclusions['directories'] = array_merge($exclusions['directories'], [
    'Documentation-GENERATED-temp',
    '\\.phpunit\\.cache',
    '\\.cache',
    'var',
    'typo3temp',
]);
$exclusions['files'][] = 'ter-exclude\\.php';
$exclusions['files'][] = 'env\\..*';

return $exclusions;
