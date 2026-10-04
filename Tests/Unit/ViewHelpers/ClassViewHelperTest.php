<?php

declare(strict_types=1);

namespace Vendor\StylexConnector\Tests\Unit\ViewHelpers;

use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\View\TemplateView;
use Vendor\StylexConnector\Configuration\StylexRegistry;
use Vendor\StylexConnector\Service\StylexManifestService;
use Vendor\StylexConnector\ViewHelpers\ClassViewHelper;

final class ClassViewHelperTest extends TestCase
{
    private string $path;
    private StylexManifestService $service;

    protected function setUp(): void
    {
        StylexRegistry::reset();
        $this->path = tempnam(sys_get_temp_dir(), 'stylex-fluid-');
        file_put_contents($this->path, json_encode(['styles' => [
            'base' => ['className' => 'x-base'],
            'active' => ['className' => 'x-active'],
            'fallback' => ['className' => 'x-fallback'],
            'escaped' => ['className' => 'x"quoted'],
        ]]));
        StylexRegistry::registerManifest('fluid', $this->path);
        $manager = self::createStub(CacheManager::class);
        $manager->method('getCache')->willReturn(self::createStub(FrontendInterface::class));
        $this->service = new StylexManifestService($manager);
    }

    protected function tearDown(): void
    {
        unlink($this->path);
        StylexRegistry::reset();
    }

    private function render(string $template, array $variables = []): string
    {
        $context = new RenderingContext();
        $resolver = new class ($this->service) extends ViewHelperResolver {
            private readonly StylexManifestService $service;

            public function __construct(StylexManifestService $service)
            {
                $this->service = $service;
            }

            // Fluid 2 has an untyped parameter; Fluid 4/5 type it as string.
            public function createViewHelperInstanceFromClassName($viewHelperClassName): ViewHelperInterface
            {
                if ($viewHelperClassName === ClassViewHelper::class) {
                    return new ClassViewHelper($this->service);
                }
                return parent::createViewHelperInstanceFromClassName($viewHelperClassName);
            }
        };
        $resolver->addNamespace('f', 'TYPO3\\CMS\\Fluid\\ViewHelpers');
        $resolver->addNamespace('stylex', 'Vendor\\StylexConnector\\ViewHelpers');
        $context->setViewHelperResolver($resolver);
        $context->getTemplatePaths()->setTemplateSource($template);
        $view = new TemplateView($context);
        $view->assignMultiple($variables);
        return $view->render();
    }

    public function testFluidLiteralAttributeEscapingAndConditions(): void
    {
        self::assertSame(
            '<span class="x&quot;quoted x-active"></span>',
            $this->render(
                '<span class="{stylex:class(styles: \'escaped\', when: {enabled: \'active\'})}"></span>',
                ['enabled' => true]
            )
        );
        $variables = [
            'keys' => ['base'], 'conditions' => ['item.active' => 'active'], 'fallback' => ['fallback'],
            'item' => new class () {
                public function reveal(): bool
                {
                    return $this->getActive();
                }

                private function getActive(): bool
                {
                    return true;
                }
            },
        ];
        self::assertSame(
            'x-base x-fallback',
            $this->render('{stylex:class(styles: keys, when: conditions, else: fallback)}', $variables)
        );
    }

    public function testClassesInsideTypo3LinkViewHelper(): void
    {
        $html = $this->render(
            '<f:link.external uri="https://example.com" class="{stylex:class(styles: \'base\')}">Link</f:link.external>'
        );
        self::assertStringContainsString('class="x-base"', $html);
        self::assertStringContainsString('href="https://example.com"', $html);
    }

    public function testUnsupportedArrayValueHasDeveloperDiagnostic(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('strings or arrays of strings');
        $this->render('{stylex:class(styles: keys)}', ['keys' => [new \stdClass()]]);
    }
}
