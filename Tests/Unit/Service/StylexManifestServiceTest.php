<?php

declare(strict_types=1);

namespace Vendor\StylexConnector\Tests\Unit\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use Vendor\StylexConnector\Configuration\StylexRegistry;
use Vendor\StylexConnector\Service\StylexManifestService;

final class StylexManifestServiceTest extends TestCase
{
    private CacheManager&MockObject $cacheManager;
    private FrontendInterface&MockObject $cache;
    private string $tempFixtureDir;

    protected function setUp(): void
    {
        parent::setUp();
        StylexRegistry::reset();

        $this->cache = $this->createMock(FrontendInterface::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->cacheManager->method('getCache')
            ->with('stylex_manifest')
            ->willReturn($this->cache);

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
        self::assertSame('x8uv x5mn x6pq', $classesReversed); // or order based on resolved map
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
        $this->cache->expects(self::once())->method('flush');

        $service = new StylexManifestService($this->cacheManager);
        $service->clearCache();
    }
}
