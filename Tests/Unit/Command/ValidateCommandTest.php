<?php

declare(strict_types=1);

namespace Vendor\StylexConnector\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use Vendor\StylexConnector\Command\ValidateCommand;
use Vendor\StylexConnector\Configuration\StylexRegistry;
use Vendor\StylexConnector\Service\StylexManifestService;

final class ValidateCommandTest extends TestCase
{
    public function testStrictValidationChecksRequiredKeysCollisionsAndPairedArtifacts(): void
    {
        $directory = sys_get_temp_dir() . '/stylex-command-' . uniqid();
        mkdir($directory);
        $css = $directory . '/styles.css';
        $path = $directory . '/manifest.json';
        try {
            file_put_contents($css, '.x-root { color: red }');
            file_put_contents($path, json_encode([
                'version' => '2.0',
                'styles' => ['root' => ['kind' => 'recipe', 'className' => 'x-root']],
                'artifacts' => [['path' => 'styles.css', 'sha256' => hash_file('sha256', $css)]],
            ]));
            StylexRegistry::reset();
            StylexRegistry::registerManifest('site', $path);
            $manager = self::createStub(CacheManager::class);
            $manager->method('getCache')->willReturn(self::createStub(FrontendInterface::class));
            $tester = new CommandTester(new ValidateCommand(new StylexManifestService($manager)));
            self::assertSame(0, $tester->execute(['--required-key' => ['root']]));
            self::assertSame(1, $tester->execute(['--required-key' => ['missing']]));
            file_put_contents($css, '.x-changed {}');
            self::assertSame(1, $tester->execute([]));
            self::assertStringContainsString('mismatched', $tester->getDisplay());
            StylexRegistry::registerManifest('collision', $path);
            $tester = new CommandTester(new ValidateCommand(new StylexManifestService($manager)));
            self::assertSame(1, $tester->execute(['--report' => true]));
            self::assertStringContainsString('collision', $tester->getDisplay());
        } finally {
            StylexRegistry::reset();
            unlink($css);
            unlink($path);
            rmdir($directory);
        }
    }
}
