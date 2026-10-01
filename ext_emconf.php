<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'StyleX Connector',
    'description' => 'Bridges StyleX build-time CSS authoring with TYPO3 Fluid templates. Provides the StylexManifestService and {stylex:class()} ViewHelper.',
    'category' => 'fe',
    'author' => 'Sven Kalbhenn',
    'author_email' => 'sven@skom.de',
    'author_company' => '',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-8.4.99',
            'typo3' => '12.4.0-14.99.99',
            'fluid' => '12.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'bootstrap_package' => '',
            'vite_asset_collector' => '',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Vendor\\StylexConnector\\' => 'Classes/',
            'Skom\\StylexConnector\\' => 'Classes/',
        ],
    ],
];
