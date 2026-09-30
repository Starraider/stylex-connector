<?php

declare(strict_types=1);

defined('TYPO3') or die();

// -----------------------------------------------------------------------
// Cache configuration for the StyleX manifest
// -----------------------------------------------------------------------
// Cleared automatically when TYPO3 caches are flushed ("all" + "pages" groups)
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['stylex_manifest'] ??= [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\FileBackend::class,
    'options' => [
        'defaultLifetime' => 86400, // 24 hours; overridden to 0 in development context
    ],
    'groups' => ['all', 'pages'],
];

// -----------------------------------------------------------------------
// Register Fluid global namespace
// -----------------------------------------------------------------------
// This makes xmlns:stylex available in all Fluid templates without per-template declaration.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['stylex'] ??= [];
if (!in_array('Vendor\\StylexConnector\\ViewHelpers', $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['stylex'], true)) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['stylex'][] = 'Vendor\\StylexConnector\\ViewHelpers';
}
if (!in_array('Skom\\StylexConnector\\ViewHelpers', $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['stylex'], true)) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['stylex'][] = 'Skom\\StylexConnector\\ViewHelpers';
}

// -----------------------------------------------------------------------
// Class aliases for seamless vendor compatibility
// -----------------------------------------------------------------------
if (!class_exists(\Skom\StylexConnector\Configuration\StylexRegistry::class, false)) {
    class_alias(
        \Vendor\StylexConnector\Configuration\StylexRegistry::class,
        \Skom\StylexConnector\Configuration\StylexRegistry::class
    );
}
if (!class_exists(\Skom\StylexConnector\Service\StylexManifestService::class, false)) {
    class_alias(
        \Vendor\StylexConnector\Service\StylexManifestService::class,
        \Skom\StylexConnector\Service\StylexManifestService::class
    );
}
if (!class_exists(\Skom\StylexConnector\ViewHelpers\ClassViewHelper::class, false)) {
    class_alias(
        \Vendor\StylexConnector\ViewHelpers\ClassViewHelper::class,
        \Skom\StylexConnector\ViewHelpers\ClassViewHelper::class
    );
}
