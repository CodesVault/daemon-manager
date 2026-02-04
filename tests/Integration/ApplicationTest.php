<?php

declare(strict_types=1);

use Cadence\Console\Application;

test('shows help', function () {
    $app = new Application();

    ob_start();
    $exitCode = $app->run(['cadence', '--help']);
    $output = ob_get_clean();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Cadence');
    expect($output)->toContain('Usage:');
    expect($output)->toContain('Options:');
});

test('requires script path', function () {
    $app = new Application(stderr: nullStream());

    ob_start();
    $exitCode = $app->run(['cadence']);
    ob_end_clean();

    expect($exitCode)->toBe(1);
});

test('shows config with valid script', function () {
    $app = new Application();

    ob_start();
    $exitCode = $app->run(['cadence', fixturesPath() . '/success_script.php', '--config']);
    $output = ob_get_clean();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Default Configuration:');
    expect($output)->toContain('interval:');
});

test('parses interval option', function () {
    $app = new Application();

    ob_start();
    $exitCode = $app->run([
        'cadence',
        fixturesPath() . '/success_script.php',
        '--interval', '30',
        '--config',
    ]);
    $output = ob_get_clean();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('interval: 30');
});

test('parses short options', function () {
    $app = new Application();

    ob_start();
    $exitCode = $app->run([
        'cadence',
        fixturesPath() . '/success_script.php',
        '-i', '15',
        '--config',
    ]);
    $output = ob_get_clean();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('interval: 15');
});

test('validates script exists', function () {
    $app = new Application(stderr: nullStream());

    ob_start();
    $exitCode = $app->run(['cadence', fixturesPath() . '/nonexistent-script.php']);
    ob_end_clean();

    expect($exitCode)->toBe(1);
});

test('handles unknown options', function () {
    $app = new Application(stderr: nullStream());

    ob_start();
    $exitCode = $app->run(['cadence', '--unknown-option']);
    ob_end_clean();

    expect($exitCode)->toBe(1);
});

test('runs ticker with max cycles', function () {
    $app = new Application();

    ob_start();
    $exitCode = $app->run([
        'cadence',
        fixturesPath() . '/success_script.php',
        '--max-cycles', '1',
        '--interval', '1',
        '--quiet',
    ]);
    ob_end_clean();

    expect($exitCode)->toBe(0);
});

test('runs cli command instead of php script', function () {
    $app = new Application();

    ob_start();
    $exitCode = $app->run([
        'cadence',
        'echo hello',
        '--max-cycles', '1',
        '--interval', '1',
        '--quiet',
    ]);
    ob_end_clean();

    expect($exitCode)->toBe(0);
});

test('detects cli command correctly', function () {
    $app = new Application();

    ob_start();
    $exitCode = $app->run([
        'cadence',
        'echo hello',
        '--max-cycles', '1',
        '--interval', '1',
        '--config',
    ]);
    $output = ob_get_clean();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Default Configuration:');
});

test('cli command does not validate file existence', function () {
    $app = new Application(stderr: nullStream());

    ob_start();
    $exitCode = $app->run([
        'cadence',
        'curl -s https://example.com',
        '--max-cycles', '1',
        '--interval', '1',
        '--config',
    ]);
    ob_end_clean();

    expect($exitCode)->toBe(0);
});

test('php script with .php extension validates file existence', function () {
    $app = new Application(stderr: nullStream());

    ob_start();
    $exitCode = $app->run([
        'cadence',
        '/nonexistent/path/script.php',
    ]);
    ob_end_clean();

    expect($exitCode)->toBe(1);
});
