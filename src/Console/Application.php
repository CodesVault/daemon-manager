<?php

declare(strict_types=1);

namespace DaemonManager\Console;

use DaemonManager\App\Logger;
use DaemonManager\App\Ticker;
use DaemonManager\Config\Config;
use DaemonManager\Config\EnvLoader;

class Application
{
    public const VERSION = '1.0.0';
    public const NAME = 'Daemon Manager';

    private ?Config $config = null;

    /** @var resource */
    private $stderr;

    public function __construct(
        private ArgumentParser $parser = new ArgumentParser(),
        private EnvLoader $envLoader = new EnvLoader(),
        mixed $stderr = null
    ) {
        $this->stderr = $stderr ?? STDERR;
    }

    public function run(array $argv): int
    {
        $this->parser->parse($argv);

        if ($this->parser->hasErrors()) {
            $this->printErrors($this->parser->getErrors());
            return 1;
        }

        if ($this->parser->wantsHelp()) {
            $this->printHelp();
            return 0;
        }

        if ($this->parser->wantsVersion()) {
            $this->printVersion();
            return 0;
        }

        if ($this->parser->wantsConfigs()) {
            $this->config = $this->buildConfig();
            $this->printConfig();
            return 0;
        }

        if ($this->parser->getScript() === null) {
            $this->printError('Error: script path or command is required. Run \'dm --help\' for usage.');
            $this->printUsage();
            return 1;
        }

        // Build config
        $this->config = $this->buildConfig();

        // Validate config
        $errors = $this->config->validate();
        if (!empty($errors) && is_array($errors)) {
            $this->printErrors($errors);
            return 1;
        }

        // Show config in verbose mode
        if ($this->parser->isVerbose()) {
            $this->printConfig();
        }

        // Start the process
        return $this->startTicker();
    }

    private function buildConfig(): Config
    {
        $cliConfig = $this->parser->toConfigArray();
        $envConfig = $this->envLoader->load(
            $this->parser->getEnvPath(),
            $this->parser->getScript()
        );

        return Config::fromMerged([], $envConfig, $cliConfig);
    }

    private function startTicker(): int
    {
        $logger = new Logger(
            $this->config->getLogLevel(),
            $this->config->getLogFile(),
            null,
            $this->config->getLogTimezone()
        );

        $ticker = new Ticker($this->config, $logger);

        return $ticker->run();
    }

    public function getConfig(): ?Config
    {
        return $this->config;
    }

    private function printHelp(): void
    {
        $commandList = new CommandList();

        $this->printVersion();
        echo "\n";

        // Usage
        echo "Usage:\n";
        echo "  dm <script.php> [options]\n";
        echo "  dm '<command>'  [options]\n\n";

        // Arguments
        echo "Arguments:\n";
        foreach ($commandList->arguments() as $arg) {
            echo sprintf("  %-26s %s\n", "<{$arg['name']}>", $arg['desc']);
        }
        echo "\n";

        // Options
        echo "Options:\n";

        // Build option strings first to calculate max length
        $optionLines = [];
        foreach ($commandList->options() as $opt) {
            $short = $opt['short'] ? "-{$opt['short']}" : '  ';
            $long = "--{$opt['long']}";

            if ($opt['type'] !== 'bool') {
                $long .= ' <' . strtoupper($opt['type']) . '>';
            }

            $optionLines[] = [
                'short' => $short,
                'long'  => $long,
                'desc'  => $opt['desc'],
            ];
        }

        // Find max length for alignment
        $maxShortLen = max(array_map(fn ($o) => strlen($o['short']), $optionLines));
        $maxLongLen = max(array_map(fn ($o) => strlen($o['long']), $optionLines));

        foreach ($optionLines as $line) {
            $shortPadded = str_pad($line['short'], $maxShortLen);
            $longPadded = str_pad($line['long'], $maxLongLen);
            echo "  {$shortPadded}  {$longPadded}   {$line['desc']}\n";
        }
        echo "\n";

        // Examples
        echo "Examples:\n";
        foreach ($commandList->examples() as $example) {
            echo "  {$example}\n";
        }
        echo "\n";

        // Environment Variables
        echo "Environment Variables (.env):\n";
        echo '  ' . implode(', ', $commandList->envVariables()) . "\n";
    }

    private function printVersion(): void
    {
        echo self::NAME . ' v' . self::VERSION . "\n";
    }

    private function printUsage(): void
    {
        echo "Usage:\n  dm <script.php> [options]\n";
        echo "  dm '<command>'  [options]\n\n";
        echo "Run 'dm --help' for more information.\n";
    }

    private function printConfig(): void
    {
        echo "Default Configuration:\n\n";
        foreach ($this->config->toArray() as $key => $value) {
            if ($key === 'script') {
                continue;
            }
            $display = $value ?? 'null';
            echo "  {$key}: {$display}\n";
        }
        echo "\n";
    }

    private function printError(string $message): void
    {
        fwrite($this->stderr, $message . "\n");
    }

    private function printErrors(array $errors): void
    {
        foreach ($errors as $error) {
            $this->printError("Error: {$error}");
        }
    }
}
