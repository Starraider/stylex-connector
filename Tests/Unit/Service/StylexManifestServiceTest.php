<?php

declare(strict_types=1);

namespace Vendor\StylexConnector\Tests\Unit\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Vendor\StylexConnector\Configuration\StylexRegistry;
use Vendor\StylexConnector\Service\ManifestReader;
use Vendor\StylexConnector\Service\StylexManifestService;

final class StylexManifestServiceTest extends TestCase
{
    private CacheManager&MockObject $cacheManager;
    private string $tempFixtureDir;

    protected function setUp(): void
    {
        parent::setUp();
        StylexRegistry::reset();

        $cache = self::createStub(FrontendInterface::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->cacheManager->method('getCache')
            ->with('stylex_manifest')
            ->willReturn($cache);

        $this->tempFixtureDir = sys_get_temp_dir() . '/stylex_test_' . uniqid();
        mkdir($this->tempFixtureDir, 0777, true);
    }

    protected function tearDown(): void
    {
        StylexRegistry::reset();
        if (is_dir($this->tempFixtureDir)) {
            $files = glob($this->tempFixtureDir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($this->tempFixtureDir);
        }
        parent::tearDown();
    }

    public function testGetClassesReturnsEmptyStringWhenNoKeysProvided(): void
    {
        $service = new StylexManifestService($this->cacheManager);
        self::assertSame('', $service->getClasses());
        self::assertSame('', $service->getClasses(''));
    }

    public function testGetClassesResolvesSingleKey(): void
    {
        $manifest = [
            'version' => '1.0',
            'styles' => [
                'Button.root' => [
                    'className' => 'x1abc x2def',
                    'properties' => [
                        'backgroundColor' => 'x1abc',
                        'padding' => 'x2def',
                    ],
                ],
            ],
        ];

        $manifestPath = $this->tempFixtureDir . '/stylex-manifest.json';
        file_put_contents($manifestPath, json_encode($manifest));

        StylexRegistry::registerManifest('test_ext', $manifestPath);

        $service = new StylexManifestService($this->cacheManager);
        $classes = $service->getClasses('Button.root');

        self::assertSame('x1abc x2def', $classes);
    }

    public function testGetClassesPerformsPropertyConflictResolution(): void
    {
        $manifest = [
            'version' => '1.0',
            'styles' => [
                'Nav.link' => [
                    'className' => 'x5mn x6pq',
                    'properties' => [
                        'color' => 'x5mn',
                        'borderBottom' => 'x6pq',
                    ],
                ],
                'Nav.linkActive' => [
                    'className' => 'x8uv',
                    'properties' => [
                        'borderBottom' => 'x8uv', // Overrides borderBottom from Nav.link
                    ],
                ],
            ],
        ];

        $manifestPath = $this->tempFixtureDir . '/stylex-manifest.json';
        file_put_contents($manifestPath, json_encode($manifest));

        StylexRegistry::registerManifest('test_ext', $manifestPath);

        $service = new StylexManifestService($this->cacheManager);

        // When Nav.linkActive comes last, its borderBottom class (x8uv) should win over x6pq
        $classes = $service->getClasses('Nav.link', 'Nav.linkActive');
        self::assertSame('x5mn x8uv', $classes);

        // When Nav.link comes last, its borderBottom class (x6pq) should win over x8uv
        $classesReversed = $service->getClasses('Nav.linkActive', 'Nav.link');
        self::assertSame('x6pq x5mn', $classesReversed);
    }

    public function testGetClassesSupportsCommaSeparatedKeys(): void
    {
        $manifest = [
            'version' => '1.0',
            'styles' => [
                'A.one' => [
                    'className' => 'x1',
                    'properties' => ['margin' => 'x1'],
                ],
                'B.two' => [
                    'className' => 'x2',
                    'properties' => ['padding' => 'x2'],
                ],
            ],
        ];

        $manifestPath = $this->tempFixtureDir . '/stylex-manifest.json';
        file_put_contents($manifestPath, json_encode($manifest));

        StylexRegistry::registerManifest('test_ext', $manifestPath);

        $service = new StylexManifestService($this->cacheManager);
        $classes = $service->getClasses('A.one, B.two');

        self::assertSame('x1 x2', $classes);
    }

    public function testMalformedEntriesDoNotBreakClassResolution(): void
    {
        $manifestPath = $this->tempFixtureDir . '/stylex-manifest.json';
        file_put_contents($manifestPath, json_encode([
            'styles' => [
                'Invalid.entry' => 'not an object',
                'Invalid.properties' => ['className' => 'x-fallback', 'properties' => 'not an object'],
                'Valid.entry' => ['className' => 'x-valid', 'properties' => ['color' => 'x-valid', 'padding' => []]],
            ],
        ]));
        StylexRegistry::registerManifest('test_ext', $manifestPath);

        $service = new StylexManifestService($this->cacheManager);
        self::assertSame(
            'x-valid x-fallback',
            $service->getClasses('Invalid.entry', 'Invalid.properties', 'Valid.entry')
        );
        self::assertSame(['Invalid.properties', 'Valid.entry'], $service->getAvailableKeys());
    }

    public function testGetAvailableKeysAndCount(): void
    {
        $manifest = [
            'version' => '1.0',
            'styles' => [
                'Button.root' => ['className' => 'c1', 'properties' => []],
                'Button.primary' => ['className' => 'c2', 'properties' => []],
                'Nav.link' => ['className' => 'c3', 'properties' => []],
            ],
        ];

        $manifestPath = $this->tempFixtureDir . '/stylex-manifest.json';
        file_put_contents($manifestPath, json_encode($manifest));

        StylexRegistry::registerManifest('test_ext', $manifestPath);

        $service = new StylexManifestService($this->cacheManager);

        self::assertSame(['Button.root', 'Button.primary', 'Nav.link'], $service->getAvailableKeys());
        self::assertSame(3, $service->getStyleCount());
    }

    public function testRegistryMergesMultipleManifestsWithLaterPrecedence(): void
    {
        $manifestA = [
            'version' => '1.0',
            'styles' => [
                'Button.root' => [
                    'className' => 'x-base',
                    'properties' => ['padding' => 'x-base'],
                ],
            ],
        ];
        $manifestB = [
            'version' => '1.0',
            'styles' => [
                'Button.root' => [
                    'className' => 'x-override',
                    'properties' => ['padding' => 'x-override'],
                ],
                'Card.root' => [
                    'className' => 'x-card',
                    'properties' => ['borderRadius' => 'x-card'],
                ],
            ],
        ];

        $pathA = $this->tempFixtureDir . '/manifest-a.json';
        $pathB = $this->tempFixtureDir . '/manifest-b.json';
        file_put_contents($pathA, json_encode($manifestA));
        file_put_contents($pathB, json_encode($manifestB));

        StylexRegistry::registerManifest('ext_a', $pathA);
        StylexRegistry::registerManifest('ext_b', $pathB);

        $service = new StylexManifestService($this->cacheManager);

        self::assertSame('x-override', $service->getClasses('Button.root'));
        self::assertSame('x-card', $service->getClasses('Card.root'));
    }

    public function testClearCacheFlushesFrontendCache(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())->method('flush');
        $cacheManager = self::createStub(CacheManager::class);
        $cacheManager->method('getCache')->willReturn($cache);

        $service = new StylexManifestService($cacheManager);
        $service->clearCache();
    }
    private function writeManifest(string $file, array $data): string
    {
        $path = $this->tempFixtureDir . '/' . $file;
        file_put_contents($path, json_encode($data));
        return $path;
    }

    public function testActualCompilerCompositionMatchesJavascript(): void
    {
        foreach (['', '-application-order'] as $suffix) {
            StylexRegistry::registerManifest(
                'fixture',
                dirname(__DIR__, 2) . '/Fixtures/compiler' . $suffix . '-manifest.json'
            );
            $service = new StylexManifestService($this->cacheManager);
            $fixture = dirname(__DIR__, 2) . '/Fixtures/compiler' . $suffix . '-parity.json';
            $cases = json_decode(file_get_contents($fixture), true);
            foreach ($cases as $case) {
                $classes = explode(' ', $service->getClasses(...$case['keys']));
                sort($classes);
                self::assertSame($case['classes'], $classes, $suffix . implode(', ', $case['keys']));
            }
        }
    }

    public function testRecipesConcatenateWithoutPropertyResolution(): void
    {
        $path = $this->writeManifest('recipes.json', ['version' => '2.0', 'styles' => [
            'red' => ['kind' => 'recipe', 'className' => 'x-red'],
            'blue' => ['kind' => 'recipe', 'className' => 'x-blue x-red'],
        ]]);
        StylexRegistry::registerManifest('recipes', $path);
        $service = new StylexManifestService($this->cacheManager);
        self::assertSame('x-red x-blue', $service->getClasses('red', 'blue'));
        self::assertTrue($service->hasStyle('red'));
        self::assertFalse($service->hasStyle('missing'));
    }

    public function testVersionAndStructuralErrorsHaveUsefulDiagnostics(): void
    {
        $reader = new ManifestReader();
        self::assertSame([], $reader->read('{"version":"999.0","styles":{}}')['styles']);
        self::assertStringContainsString('Unsupported', $reader->read('{"version":"999.0","styles":{}}')['errors'][0]);
        self::assertStringContainsString('styles object', $reader->read('{}')['errors'][0]);
        self::assertStringContainsString('Invalid JSON', $reader->read('{')['errors'][0]);
        self::assertNotEmpty($reader->read('{"version":"2.0","styles":{"bad":{"className":"x"}}}')['errors']);
        self::assertSame([], $reader->read('{"version":"2.0","capabilities":["dynamic"],"styles":{}}')['styles']);
    }

    public function testPersistentCacheTracksContentsAndRegistryAndRetriesMissingFiles(): void
    {
        $stored = new \ArrayObject();
        $cache = self::createStub(FrontendInterface::class);
        $cache->method('has')->willReturnCallback(static function ($key) use (&$stored) {
            return isset($stored[$key]);
        });
        // Capture by reference so each new service sees the writes from the previous service.
        $cache->method('get')->willReturnCallback(static function ($key) use (&$stored) {
            return $stored[$key] ?? false;
        });
        $cache->method('set')->willReturnCallback(static function ($key, $data) use (&$stored): void {
            $stored[$key] = $data;
        });
        $manager = self::createStub(CacheManager::class);
        $manager->method('getCache')->willReturn($cache);
        $path = $this->writeManifest('cache.json', ['styles' => ['key' => ['className' => 'x-old']]]);
        $mtime = filemtime($path);
        StylexRegistry::registerManifest('a', $path);
        self::assertSame('x-old', (new StylexManifestService($manager))->getClasses('key'));
        $this->writeManifest('cache.json', ['styles' => ['key' => ['className' => 'x-new']]]);
        touch($path, $mtime);
        self::assertSame('x-new', (new StylexManifestService($manager))->getClasses('key'));
        StylexRegistry::reset();
        $missing = $this->tempFixtureDir . '/missing.json';
        StylexRegistry::registerManifest('b', $missing);
        $before = count($stored);
        self::assertSame(0, (new StylexManifestService($manager))->getStyleCount());
        self::assertCount($before, $stored);
        $this->writeManifest('missing.json', ['styles' => ['other' => ['className' => 'x-other']]]);
        self::assertSame(['other'], (new StylexManifestService($manager))->getAvailableKeys());
    }

    public function testFallbackTransitionAndExplicitOverrides(): void
    {
        $path = $this->writeManifest('registered.json', ['styles' => ['key' => ['className' => 'x-registered']]]);
        $fallback = $this->writeManifest('fallback.json', ['styles' => ['key' => ['className' => 'x-fallback']]]);
        StylexRegistry::registerManifest('site', $path, true);
        $configuration = self::createStub(ExtensionConfiguration::class);
        $configuration->method('get')->willReturn(['manifestPath' => $fallback]);
        $legacy = new StylexManifestService($this->cacheManager, $configuration);
        self::assertSame('x-fallback', $legacy->getClasses('key'));
        self::assertSame('collision', $legacy->getReport()['diagnostics'][0]['category']);
        $configuration = self::createStub(ExtensionConfiguration::class);
        $configuration->method('get')->willReturn(['manifestPath' => $fallback, 'manifestPathMode' => 'fallback']);
        $service = new StylexManifestService($this->cacheManager, $configuration);
        self::assertSame('x-registered', $service->getClasses('key'));
        self::assertSame([], $service->getReport()['diagnostics']);
    }

    public function testComposerAliasesWorkWithoutExtensionBootstrap(): void
    {
        $alias = new \Skom\StylexConnector\Service\StylexManifestService($this->cacheManager);
        self::assertInstanceOf(StylexManifestService::class, $alias);
        self::assertSame(
            StylexRegistry::getRegisteredManifests(),
            \Skom\StylexConnector\Configuration\StylexRegistry::getRegisteredManifests()
        );
    }
}
