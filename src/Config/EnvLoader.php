<?php

declare(strict_types=1);

namespace Cadence\Config;

use Dotenv\Dotenv;

class EnvLoader
{
    private const ENV_MAP = [
        'CAD_INTERVAL'     => 'interval',
        'CAD_MAX_MEMORY'   => 'maxMemory',
        'CAD_MAX_RUNTIME'  => 'maxRuntime',
        'CAD_MAX_CYCLES'   => 'maxCycles',
        'CAD_LOG_FILE'     => 'logFile',
        'CAD_LOG_LEVEL'    => 'logLevel',
        'CAD_LOG_TIMEZONE' => 'logTimezone',
    ];

    public function load(?string $envPath = null, ?string $scriptPath = null): array
    {
        $path = $envPath ?? $this->findEnvFile($scriptPath);

        if ($path === null || !file_exists($path)) {
            return [];
        }

        return $this->parse($path);
    }

    public function findEnvFile(?string $scriptPath): ?string
    {
        if ($scriptPath === null) {
            return null;
        }

        $directory = dirname(realpath($scriptPath) ?: $scriptPath);
        $envPath = $directory . '/.env';

        return file_exists($envPath) ? $envPath : null;
    }

    private function parse(string $path): array
    {
        $directory = dirname($path);
        $filename = basename($path);

        // Use createArrayBacked to avoid polluting $_ENV and $_SERVER
        $dotenv = Dotenv::createArrayBacked($directory, $filename);

        $envValues = [];
        try {
            $envValues = $dotenv->load();
        } catch (\Exception) {
            return [];
        }

        return $this->mapToConfig($envValues);
    }

    private function mapToConfig(array $envValues): array
    {
        $config = [];
        if (empty($envValues)) {
            return $config;
        }

        foreach (self::ENV_MAP as $envKey => $configKey) {
            if (isset($envValues[$envKey])) {
                $value = $envValues[$envKey];
                $config[$configKey] = $this->castValue($configKey, $value);
            }
        }

        return $config;
    }

    private function castValue(string $key, string $value): mixed
    {
        if ($value === '') {
            return null;
        }

        return match ($key) {
            'interval', 'maxRuntime', 'maxCycles' => (int) $value,
            default => $value,
        };
    }
}
