<?php

declare(strict_types=1);

namespace Vendor\StylexConnector\Service;

/** Normalizes legacy manifests and the explicit static composition/recipe contract. */
final class ManifestReader
{
    public function read(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return ['styles' => [], 'errors' => ['Invalid JSON: ' . $exception->getMessage()], 'metadata' => []];
        }
        if (!is_array($data) || !isset($data['styles']) || !is_array($data['styles'])) {
            return ['styles' => [], 'errors' => ['The manifest must contain a styles object.'], 'metadata' => []];
        }
        $version = array_key_exists('version', $data) ? $data['version'] : '1.0';
        if (!in_array($version, ['1.0', '2.0'], true)) {
            return [
                'styles' => [],
                'errors' => ['Unsupported manifest version: ' . json_encode($version)],
                'metadata' => [],
            ];
        }
        $styles = [];
        $errors = [];
        if ($version === '2.0') {
            foreach (['producer', 'compiler'] as $field) {
                if (isset($data[$field]) && !is_array($data[$field])) {
                    $errors[] = 'Invalid metadata object: ' . $field;
                }
            }
            if (isset($data['generation']) && !is_string($data['generation'])) {
                $errors[] = 'Invalid generation identifier.';
            }
            $capabilities = $data['capabilities'] ?? [];
            if (!is_array($capabilities)) {
                return ['styles' => [], 'errors' => ['Invalid manifest capabilities.'], 'metadata' => []];
            }
            foreach ($capabilities as $capability) {
                if (!in_array($capability, ['static-conflicts', 'null-clearing'], true)) {
                    return ['styles' => [], 'errors' => ['Unsupported manifest capabilities.'], 'metadata' => []];
                }
            }
            if (isset($data['artifacts']) && !is_array($data['artifacts'])) {
                $errors[] = 'Invalid artifacts list.';
            }
        }
        foreach ($data['styles'] as $key => $entry) {
            if (!is_string($key) || trim($key) === '' || !is_array($entry)) {
                $errors[] = 'Invalid style entry: ' . $key;
                continue;
            }
            $properties = array_key_exists('properties', $entry) ? $entry['properties'] : [];
            $className = array_key_exists('className', $entry) ? $entry['className'] : '';
            $legacyKind = is_array($properties) && $properties !== [] ? 'compiled' : 'recipe';
            $kind = $version === '2.0' ? ($entry['kind'] ?? null) : $legacyKind;
            if (
                $version === '2.0' && (
                    !isset($entry['kind'])
                    || !array_key_exists('className', $entry)
                    || !in_array($kind, ['compiled', 'recipe'], true)
                    || !is_string($className) || !is_array($properties)
                    || ($kind === 'compiled' && !array_key_exists('properties', $entry))
                    || ($kind === 'recipe' && $properties !== [])
                )
            ) {
                $errors[] = 'Invalid v2 entry shape: ' . $key;
                continue;
            }
            $normalized = [];
            if (!is_array($properties)) {
                $errors[] = 'Invalid properties map: ' . $key;
                $properties = [];
            }
            foreach ($properties as $property => $value) {
                if (!is_string($property) || (!is_string($value) && $value !== null)) {
                    $errors[] = 'Invalid conflict entry: ' . $key;
                    continue;
                }
                $normalized[$property] = $value;
            }
            if (!is_string($className)) {
                $errors[] = 'Invalid className: ' . $key;
                $className = '';
            }
            // Tolerant v1 loading preserves class-only entries; strict validation still reports errors.
            $styles[$key] = ['kind' => $kind, 'className' => $className, 'properties' => $normalized];
        }
        return ['styles' => $styles, 'errors' => $errors, 'metadata' => array_diff_key($data, ['styles' => true])];
    }
}
