<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()
    ->in(__DIR__)
    ->exclude(['vendor', 'public', 'Documentation-GENERATED-temp', '.Build', 'var', 'typo3temp'])
;

return $config;
