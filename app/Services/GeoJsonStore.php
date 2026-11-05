<?php

declare(strict_types=1);

namespace Plantons\Services;

use RuntimeException;

final class GeoJsonStore
{
    public function read(string $file): array
    {
        if (!is_file($file) || filesize($file) === 0) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || ($data['type'] ?? null) !== 'FeatureCollection') {
            throw new RuntimeException('Le fichier GeoJSON est invalide.');
        }

        return $data;
    }

    public function appendFeature(string $file, array $feature): void
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de créer le répertoire de données.');
        }

        $handle = fopen($file, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('Impossible de verrouiller le fichier de données.');
        }

        try {
            $content = stream_get_contents($handle);
            $collection = $content === '' ? ['type' => 'FeatureCollection', 'features' => []] : json_decode($content, true);
            if (!is_array($collection) || ($collection['type'] ?? null) !== 'FeatureCollection') {
                throw new RuntimeException('Le fichier de propositions est invalide.');
            }
            $collection['features'][] = $feature;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param list<array<string,mixed>> $features */
    public function replaceFeatures(string $file, array $features): void
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de créer le répertoire de données.');
        }

        $handle = fopen($file, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('Impossible de verrouiller le fichier de données.');
        }

        try {
            $collection = ['type' => 'FeatureCollection', 'features' => array_values($features)];
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param array<string,mixed> $properties */
    public function updateFeature(string $file, string $id, array $properties, ?array $coordinates = null): void
    {
        $handle = fopen($file, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('Impossible de verrouiller le fichier de données.');
        }

        try {
            $content = stream_get_contents($handle);
            $collection = $content === '' ? ['type' => 'FeatureCollection', 'features' => []] : json_decode($content, true);
            if (!is_array($collection) || ($collection['type'] ?? null) !== 'FeatureCollection') {
                throw new RuntimeException('Le fichier de propositions est invalide.');
            }
            $found = false;
            foreach ($collection['features'] as &$feature) {
                if (($feature['properties']['id'] ?? null) !== $id) { continue; }
                $feature['properties'] = array_replace($feature['properties'] ?? [], $properties);
                if ($coordinates !== null) { $feature['geometry'] = ['type' => 'Point', 'coordinates' => $coordinates]; }
                $found = true;
                break;
            }
            unset($feature);
            if (!$found) { throw new RuntimeException('Proposition introuvable.'); }
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function deleteFeature(string $file, string $id): array
    {
        $handle = fopen($file, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('Impossible de verrouiller le fichier de données.');
        }

        try {
            $content = stream_get_contents($handle);
            $collection = $content === '' ? ['type' => 'FeatureCollection', 'features' => []] : json_decode($content, true);
            if (!is_array($collection) || ($collection['type'] ?? null) !== 'FeatureCollection') {
                throw new RuntimeException('Le fichier de propositions est invalide.');
            }
            foreach ($collection['features'] as $index => $feature) {
                if (($feature['properties']['id'] ?? null) !== $id) {
                    continue;
                }
                array_splice($collection['features'], $index, 1);
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return $feature;
            }
            throw new RuntimeException('Proposition introuvable.');
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param array<int,array<string,mixed>> $features */
    public function mergeFeatures(string $file, array $features): int
    {
        $collection = $this->read($file);
        $knownIds = [];
        foreach ($collection['features'] as $feature) { $knownIds[(string) ($feature['properties']['id'] ?? '')] = true; }
        $added = 0;
        foreach ($features as $feature) {
            $id = (string) ($feature['properties']['id'] ?? '');
            if ($id === '' || isset($knownIds[$id]) || ($feature['type'] ?? null) !== 'Feature') { continue; }
            $collection['features'][] = $feature;
            $knownIds[$id] = true;
            $added++;
        }
        if ($added > 0) {
            $temporary = $file . '.tmp';
            if (file_put_contents($temporary, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false || !rename($temporary, $file)) {
                throw new RuntimeException('Impossible d’enregistrer les données importées.');
            }
        }
        return $added;
    }
}
