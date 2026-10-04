<?php

declare(strict_types=1);

namespace Vendor\StylexConnector\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Vendor\StylexConnector\Service\StylexManifestService;

/**
 * Resolves StyleX style keys to atomic CSS class names.
 *
 * Designed for inline use as an attribute value in Fluid templates.
 * Composes static conflict maps and looks up complete recipes.
 *
 * The Fluid namespace is registered globally via ext_localconf.php.
 * No per-template xmlns declaration needed if global registration is active.
 *
 * -----------------------------------------------------------------
 * Usage patterns:
 *
 * Pattern A -- comma-separated string:
 *   class="{stylex:class(styles: 'Nav.link, Nav.linkActive')}"
 *
 * Pattern B -- Fluid array:
 *   class="{stylex:class(styles: {0: 'Nav.link', 1: 'Nav.linkActive'})}"
 *
 * Pattern C -- conditional composition with when map:
 *   class="{stylex:class(styles: 'Nav.link', when: {isActive: 'Nav.linkActive'})}"
 *   The style key is included if the named template variable is truthy.
 *   Note: For nested object properties, assign <f:variable name="isActive" value="{item.active}" />
 *   first, as Fluid's inline parser cannot parse dotted keys in literal dictionaries.
 *
 * Pattern D -- conditional composition with when and else fallback:
 *   class="{stylex:class(styles: 'Button.root', when: {isActive: 'Button.secondary'}, else: 'Button.outline')}"
 *   The fallback style keys are included if none of the "when" conditions evaluate as truthy.
 *
 * Pattern E -- tag syntax:
 *   <stylex:class styles="Button.root, Button.primary" />
 *   <span class="<stylex:class styles='Button.root' />">Button</span>
 *
 * -----------------------------------------------------------------
 * Works inside any Fluid tag that accepts a class argument:
 *   <f:link.page class="{stylex:class(styles: 'Nav.link')}" pageUid="1">
 *   <f:link.action class="{stylex:class(styles: 'Button.root')}" action="show">
 *   <f:image class="{stylex:class(styles: 'Media.image')}" image="{file}">
 *   <f:form.textfield class="{stylex:class(styles: 'Form.input')}" property="email">
 */
final class ClassViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(
        private ?StylexManifestService $stylexService = null
    ) {
        $this->stylexService ??= GeneralUtility::makeInstance(StylexManifestService::class);
    }

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'styles',
            'mixed',
            'Base style keys: comma-separated string ("Nav.link, Nav.linkActive") '
            . 'or Fluid array ({0: "Nav.link", 1: "Nav.linkActive"})',
            false,
            ''
        );
        $this->registerArgument(
            'when',
            'array',
            'Conditional styles: associative array of [templateVariableName => styleKey]. '
            . 'The style key is included if the named template variable evaluates as truthy.',
            false,
            []
        );
        $this->registerArgument(
            'else',
            'mixed',
            'Fallback style keys: comma-separated string ("Button.outline") '
            . 'or Fluid array ({0: "Button.outline"}). '
            . 'Included if "when" is provided and none of its conditions evaluate to truthy.',
            false,
            ''
        );
    }

    public function render(): string
    {
        // --- Collect base style keys ---
        $styles = $this->arguments['styles'];
        if ($styles === '' || $styles === null) {
            $children = (string)$this->renderChildren();
            if (trim($children) !== '') {
                $styles = $children;
            }
        }

        $keys = $this->normalizeKeys($styles);

        // --- Apply conditional styles ---
        $when = $this->arguments['when'] ?? [];
        $whenMatched = false;
        if (is_array($when) && !empty($when)) {
            $templateVars = $this->renderingContext->getVariableProvider()->getAll();

            foreach ($when as $conditionVar => $styleKey) {
                $conditionalKeys = $this->normalizeKeys($styleKey);
                if ($conditionalKeys === []) {
                    continue;
                }

                // Check if condition key is an explicit boolean or evaluated number
                if ($conditionVar === 1 || $conditionVar === '1') {
                    $keys = array_merge($keys, $conditionalKeys);
                    $whenMatched = true;
                    continue;
                }

                if ($conditionVar === 0 || $conditionVar === '0') {
                    continue;
                }

                $conditionVarStr = (string)$conditionVar;
                $value = $this->resolveVariable($conditionVarStr, $templateVars);

                if (!empty($value)) {
                    $keys = array_merge($keys, $conditionalKeys);
                    $whenMatched = true;
                }
            }
        }

        // --- Apply fallback styles if when conditions were not met ---
        if (!empty($when) && !$whenMatched) {
            $keys = array_merge($keys, $this->normalizeKeys($this->arguments['else'] ?? ''));
        }

        if (empty($keys)) {
            return '';
        }

        return htmlspecialchars(
            $this->stylexService->getClasses(...$keys),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    private function normalizeKeys(mixed $input): array
    {
        if ($input === null || $input === '') {
            return [];
        }
        $values = is_array($input) ? $input : [$input];
        $keys = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException('StyleX keys must be strings or arrays of strings.', 1791060001);
            }
            foreach (explode(',', $value) as $key) {
                $key = trim($key);
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }
        return $keys;
    }

    /**
     * Resolves a variable name or dotted path (e.g. 'page.active') from template variables.
     */
    private function resolveVariable(string $path, array $variables): mixed
    {
        if (!str_contains($path, '.')) {
            return $variables[$path] ?? null;
        }

        $segments = explode('.', $path);
        $current = $variables;

        foreach ($segments as $segment) {
            if (is_array($current) && isset($current[$segment])) {
                $current = $current[$segment];
            } elseif (is_object($current)) {
                $getter = 'get' . ucfirst($segment);
                $isser = 'is' . ucfirst($segment);
                $hasser = 'has' . ucfirst($segment);

                if (is_callable([$current, $getter])) {
                    $current = $current->$getter();
                } elseif (is_callable([$current, $isser])) {
                    $current = $current->$isser();
                } elseif (is_callable([$current, $hasser])) {
                    $current = $current->$hasser();
                } elseif (isset($current->$segment)) {
                    $current = $current->$segment;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $current;
    }
}
