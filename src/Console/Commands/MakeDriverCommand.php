<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class MakeDriverCommand extends Command
{
    protected $signature = 'portflow:make-driver
                            {name : Driver class name, e.g. MyScale or MyScaleDriver}
                            {--namespace= : PHP namespace for the generated class (default: App\\SerialDrivers)}';

    protected $description = 'Generate a new PortFlow serial driver class';

    public function handle(): int
    {
        $raw       = (string) $this->argument('name');
        $className = Str::studly($raw);

        // Append "Driver" suffix only when it is missing.
        if (! Str::endsWith($className, 'Driver')) {
            $className .= 'Driver';
        }

        // Derive a kebab-case driver name from the class name (sans "Driver").
        $driverName = Str::kebab(Str::beforeLast($className, 'Driver'));

        $namespace = (string) ($this->option('namespace') ?: 'App\\SerialDrivers');
        $targetDir  = app_path(str_replace('App\\', '', str_replace('\\', '/', $namespace)));
        $targetFile = $targetDir.'/'.$className.'.php';

        if (file_exists($targetFile)) {
            $this->error("Driver [{$className}] already exists at [{$targetFile}].");

            return self::FAILURE;
        }

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $stub = file_get_contents($this->stubPath());

        if ($stub === false) {
            $this->error('Could not read the driver stub file.');

            return self::FAILURE;
        }

        $content = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ driverName }}'],
            [$namespace, $className, $driverName],
            $stub,
        );

        file_put_contents($targetFile, $content);

        $this->components->info("Driver [{$className}] created successfully.");
        $this->newLine();

        $this->components->twoColumnDetail('<fg=green>File</>', $targetFile);
        $this->newLine();

        $this->line('  <options=bold>Next steps:</>');
        $this->line('');
        $this->line("  1. Register the driver in <comment>config/portflow.php</comment>:");
        $this->line("     <comment>'drivers' => ['{$driverName}' => \\{$namespace}\\{$className}::class]</comment>");
        $this->newLine();
        $this->line("  2. Use it in your application:");
        $this->line("     <comment>PortFlow::ingest('{$driverName}', \$rawChunk);</comment>");
        $this->newLine();

        return self::SUCCESS;
    }

    private function stubPath(): string
    {
        // Prefer a published stub over the package default.
        $published = base_path('stubs/portflow/serial-driver.stub');

        return file_exists($published)
            ? $published
            : __DIR__.'/../../../stubs/serial-driver.stub';
    }
}
