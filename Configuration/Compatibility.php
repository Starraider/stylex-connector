<?php

declare(strict_types=1);

// Also runs for Composer-only tools, before TYPO3 extension bootstrap.
$aliases = [
    'Configuration\\StylexRegistry',
    'Service\\StylexManifestService',
    'ViewHelpers\\ClassViewHelper',
];
foreach ($aliases as $class) {
    if (!class_exists('Skom\\StylexConnector\\' . $class, false)) {
        class_alias('Vendor\\StylexConnector\\' . $class, 'Skom\\StylexConnector\\' . $class);
    }
}
