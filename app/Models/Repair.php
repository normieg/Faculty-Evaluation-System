<?php

declare(strict_types=1);

namespace App\Models;

use JsonException;
use RuntimeException;

class Repair
{
    private string $storageFile;

    public function __construct(?string $storageFile = null)
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $this->storageFile = $storageFile ?? $basePath . '/storage/repairs.json';
        $directory = dirname($this->storageFile);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create storage directory for repairs');
        }

        if (!file_exists($this->storageFile)) {
            $this->persist([]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $repairs = $this->read();

        usort($repairs, static fn ($a, $b) => strcmp($b['reported_at'], $a['reported_at']));

        return $repairs;
    }

    /**
     * @param array<string, string> $attributes
     * @return array{success: bool, errors?: array<string, string>, repair?: array<string, mixed>}
     */
    public function create(array $attributes): array
    {
        $attributes = $this->sanitize($attributes);
        $errors = $this->validate($attributes);

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $repairs = $this->read();
        $repair = [
            'id' => $this->nextId($repairs),
            'staff_name' => $attributes['staff_name'],
            'location' => $attributes['location'],
            'issue' => $attributes['issue'],
            'reported_at' => date('Y-m-d H:i:s'),
            'status' => 'Pending',
        ];

        $repairs[] = $repair;
        $this->persist($repairs);

        return [
            'success' => true,
            'repair' => $repair,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $repairs
     */
    private function nextId(array $repairs): int
    {
        if ($repairs === []) {
            return 1;
        }

        $ids = array_column($repairs, 'id');

        return max($ids) + 1;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(): array
    {
        $contents = file_get_contents($this->storageFile);

        if ($contents === false) {
            throw new RuntimeException('Unable to read repairs storage');
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to decode repairs storage: ' . $exception->getMessage(), 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, array<string, mixed>> $repairs
     */
    private function persist(array $repairs): void
    {
        $encoded = json_encode($repairs, JSON_PRETTY_PRINT);

        if ($encoded === false) {
            throw new RuntimeException('Unable to encode repairs for storage');
        }

        if (file_put_contents($this->storageFile, $encoded, LOCK_EX) === false) {
            throw new RuntimeException('Unable to persist repairs to storage');
        }
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, string>
     */
    private function validate(array $attributes): array
    {
        $errors = [];

        if ($attributes['staff_name'] === '') {
            $errors['staff_name'] = 'Staff name is required.';
        } elseif (mb_strlen($attributes['staff_name']) > 120) {
            $errors['staff_name'] = 'Staff name must be 120 characters or fewer.';
        }

        if ($attributes['location'] === '') {
            $errors['location'] = 'Location is required.';
        } elseif (mb_strlen($attributes['location']) > 120) {
            $errors['location'] = 'Location must be 120 characters or fewer.';
        }

        if ($attributes['issue'] === '') {
            $errors['issue'] = 'Please describe the issue.';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, string>
     */
    private function sanitize(array $attributes): array
    {
        $fields = ['staff_name', 'location', 'issue'];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $attributes)) {
                $attributes[$field] = '';
            }

            $attributes[$field] = trim($attributes[$field]);
        }

        return $attributes;
    }
}
