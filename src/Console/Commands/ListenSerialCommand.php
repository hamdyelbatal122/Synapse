<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Console\Commands;

use Hamzi\PortFlow\Facades\PortFlow;
use Illuminate\Console\Command;

final class ListenSerialCommand extends Command
{
    protected $signature = 'portflow:listen
        {device : Serial device path like /dev/ttyUSB0 or COM3}
        {--driver= : PortFlow driver name (default from config)}
        {--baud=9600 : Serial baud rate}
        {--parity=none : none|even|odd}
        {--data-bits=8 : 5|6|7|8}
        {--stop-bits=1 : 1|2}
        {--flow-control=none : none|software|hardware}
        {--configure-device=1 : Configure UART settings before listening (1=yes, 0=no)}
        {--show-data=0 : Print received serial chunk in console (1=yes, 0=no)}
        {--show-data-format=auto : auto|raw|plain|json|hex|base64}
        {--show-data-max=512 : Max bytes displayed per chunk when --show-data=1}
        {--chunk-bytes= : Bytes per read operation}
        {--sleep-us= : Microseconds sleep when no data is available}
        {--context= : JSON context merged into each frame context}';

    protected $description = 'Listen to a backend serial port and ingest chunks via PortFlow drivers';

    private bool $running = true;

    public function handle(): int
    {
        $rawDevice = $this->argumentString('device');
        $device = $this->normalizeDevice($rawDevice);

        if (! $this->isValidDevicePath($device)) {
            $this->error('Invalid serial device path. Supported formats: /dev/* (Linux/macOS) or COMx / \\.\COMx (Windows).');

            return self::FAILURE;
        }

        if (! $this->isDeviceAllowed($device)) {
            $this->error('Device is not allowed by portflow.backend.allowed_devices.');

            return self::FAILURE;
        }

        if (! $this->deviceExists($device)) {
            $this->error("Device [{$device}] does not exist.");

            return self::FAILURE;
        }

        $driver = $this->optionString('driver') ?: (string) config('portflow.default_driver', 'raw-json');
        $baud = (int) $this->option('baud');
        $parity = strtolower($this->optionString('parity', 'none'));
        $dataBits = (int) $this->option('data-bits');
        $stopBits = (int) $this->option('stop-bits');
        $flowControl = strtolower($this->optionString('flow-control', 'none'));
        $configureDevice = filter_var($this->optionString('configure-device', '1'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $showData = filter_var($this->optionString('show-data', '0'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $showDataFormat = strtolower($this->optionString('show-data-format', 'auto'));
        $showDataMax = (int) ($this->option('show-data-max') ?? 512);
        $chunkBytes = (int) ($this->option('chunk-bytes') ?: config('portflow.backend.default_chunk_bytes', 256));
        $sleepUs = (int) ($this->option('sleep-us') ?: config('portflow.backend.default_read_sleep_us', 20000));

        if ($configureDevice === null) {
            $this->error('Invalid --configure-device value. Use 1/0 or true/false.');

            return self::FAILURE;
        }

        if ($showData === null) {
            $this->error('Invalid --show-data value. Use 1/0 or true/false.');

            return self::FAILURE;
        }

        if (! in_array($showDataFormat, ['auto', 'raw', 'plain', 'json', 'hex', 'base64'], true)) {
            $this->error('Invalid --show-data-format value. Allowed: auto, raw, plain, json, hex, base64.');

            return self::FAILURE;
        }

        if (! in_array($parity, ['none', 'even', 'odd'], true)) {
            $this->error('Invalid parity. Allowed: none, even, odd.');

            return self::FAILURE;
        }

        if (! in_array($dataBits, [5, 6, 7, 8], true)) {
            $this->error('Invalid data bits. Allowed: 5, 6, 7, 8.');

            return self::FAILURE;
        }

        if (! in_array($stopBits, [1, 2], true)) {
            $this->error('Invalid stop bits. Allowed: 1, 2.');

            return self::FAILURE;
        }

        if (! in_array($flowControl, ['none', 'software', 'hardware'], true)) {
            $this->error('Invalid flow-control. Allowed: none, software, hardware.');

            return self::FAILURE;
        }

        if ($baud <= 0 || $chunkBytes <= 0 || $sleepUs < 0 || $showDataMax <= 0) {
            $this->error('baud, chunk-bytes and show-data-max must be > 0. sleep-us must be >= 0.');

            return self::FAILURE;
        }

        $extraContext = $this->parseContextOption($this->optionString('context'));
        if ($extraContext === null) {
            $this->error('Invalid --context JSON.');

            return self::FAILURE;
        }

        if ($configureDevice && ! $this->configureDevice($device, $baud, $parity, $dataBits, $stopBits, $flowControl)) {
            $this->error('Failed to configure serial device parameters.');

            return self::FAILURE;
        }

        $stream = @fopen($this->streamDevicePath($device), 'rb');
        if (! is_resource($stream)) {
            $this->error("Unable to open serial device [{$device}].");

            return self::FAILURE;
        }

        stream_set_blocking($stream, false);

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function (): void {
                $this->running = false;
            });
            pcntl_signal(SIGTERM, function (): void {
                $this->running = false;
            });
        }

        $this->components->info('PortFlow backend serial listener started.');
        $this->line("  Device: <comment>{$device}</comment>");
        $this->line("  OS: <comment>{$this->osFamily()}</comment>");
        $this->line("  Driver: <comment>{$driver}</comment>");
        $this->line("  UART: <comment>{$baud} {$dataBits}{$this->paritySuffix($parity)}{$stopBits}</comment>");
        $this->line("  Flow: <comment>{$flowControl}</comment>");
        $this->line('  Configure: <comment>'.($configureDevice ? 'enabled' : 'disabled').'</comment>');
        if ($showData) {
            $this->line("  Show data: <comment>enabled ({$showDataFormat}, max {$showDataMax} bytes)</comment>");
        }
        $this->line('Press Ctrl+C to stop.');

        $showDataBuffer = '';

        while ($this->running) {
            $chunk = fread($stream, $chunkBytes);

            if ($chunk === false || $chunk === '') {
                if ($sleepUs > 0) {
                    usleep($sleepUs);
                }

                continue;
            }

            if ($showData) {
                if (in_array($showDataFormat, ['auto', 'plain', 'json'], true)) {
                    $this->printLineBufferedChunk($chunk, $showDataFormat, $showDataMax, $showDataBuffer);
                } else {
                    $this->printChunkPreview($chunk, $showDataFormat, $showDataMax);
                }
            }

            try {
                $frames = PortFlow::ingest($driver, $chunk, array_merge($extraContext, [
                    'source' => 'backend-serial',
                    'device' => $device,
                    'baud_rate' => $baud,
                    'session_id' => 'backend:'.sha1($device),
                ]));

                $count = count($frames);
                if ($count > 0) {
                    $this->line("[ingest] frames={$count} bytes=".strlen($chunk));
                }
            } catch (\Throwable $e) {
                $this->warn('[ingest-error] '.$e->getMessage());
            }
        }

        if ($showData && $showDataBuffer !== '' && in_array($showDataFormat, ['auto', 'plain', 'json'], true)) {
            $this->printDisplayText($showDataBuffer, $showDataFormat, $showDataMax);
        }

        fclose($stream);
        $this->components->info('PortFlow backend serial listener stopped.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseContextOption(string $json): ?array
    {
        if ($json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function isValidDevicePath(string $device): bool
    {
        if ($this->isWindows()) {
            return (bool) preg_match('/^(COM[1-9][0-9]{0,2}|\\\\\.\\COM[1-9][0-9]{0,2})$/i', $device);
        }

        if (! str_starts_with($device, '/dev/')) {
            return false;
        }

        // Reject path traversal and whitespace
        if (str_contains($device, '..') || preg_match('/\s/', $device)) {
            return false;
        }

        // Only allow actual character/block device node names — no subdirectory nesting
        // beyond known /dev/ttyXxx or /dev/serial/by-* patterns
        return (bool) preg_match('#^/dev/(tty[A-Za-z0-9]+|serial/by-id/[A-Za-z0-9._-]+|serial/by-path/[A-Za-z0-9._:-]+)$#', $device);
    }

    private function normalizeDevice(string $device): string
    {
        $device = trim($device);

        if (! $this->isWindows()) {
            return $device;
        }

        $normalized = strtoupper($device);

        if (str_starts_with($normalized, '\\\\.\\COM')) {
            return '\\\\.\\'.substr($normalized, 4);
        }

        return $normalized;
    }

    private function deviceExists(string $device): bool
    {
        if ($this->isWindows()) {
            return true;
        }

        return file_exists($device);
    }

    private function streamDevicePath(string $device): string
    {
        if (! $this->isWindows()) {
            return $device;
        }

        if (preg_match('/^COM([1-9][0-9]{0,2})$/i', $device, $matches) === 1) {
            $number = (int) $matches[1];

            return $number >= 10 ? '\\\\.\\COM'.$number : 'COM'.$number;
        }

        return $device;
    }

    private function isDeviceAllowed(string $device): bool
    {
        $allowed = (array) config('portflow.backend.allowed_devices', []);

        if ($allowed === []) {
            return true;
        }

        foreach ($allowed as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }
            if ($this->isWindows()) {
                if (fnmatch(strtoupper($pattern), strtoupper($device))) {
                    return true;
                }

                continue;
            }

            if (fnmatch($pattern, $device)) {
                return true;
            }
        }

        return false;
    }

    private function configureDevice(
        string $device,
        int $baud,
        string $parity,
        int $dataBits,
        int $stopBits,
        string $flowControl,
    ): bool {
        if ($this->isWindows()) {
            return $this->configureWindowsDevice($device, $baud, $parity, $dataBits, $stopBits, $flowControl);
        }

        return $this->configurePosixDevice($device, $baud, $parity, $dataBits, $stopBits, $flowControl);
    }

    private function configurePosixDevice(
        string $device,
        int $baud,
        string $parity,
        int $dataBits,
        int $stopBits,
        string $flowControl,
    ): bool {
        $flags = [
            (string) $baud,
            'raw',
            '-echo',
            '-icanon',
            'min',
            '1',
            'time',
            '1',
            'cs'.$dataBits,
        ];

        if ($stopBits === 2) {
            $flags[] = 'cstopb';
        } else {
            $flags[] = '-cstopb';
        }

        if ($parity === 'none') {
            $flags[] = '-parenb';
        } elseif ($parity === 'even') {
            $flags[] = 'parenb';
            $flags[] = '-parodd';
        } else {
            $flags[] = 'parenb';
            $flags[] = 'parodd';
        }

        if ($flowControl === 'hardware') {
            $flags[] = 'crtscts';
            $flags[] = '-ixon';
            $flags[] = '-ixoff';
        } elseif ($flowControl === 'software') {
            $flags[] = '-crtscts';
            $flags[] = 'ixon';
            $flags[] = 'ixoff';
        } else {
            $flags[] = '-crtscts';
            $flags[] = '-ixon';
            $flags[] = '-ixoff';
        }

        $fileFlag = $this->osFamily() === 'Darwin' ? '-f' : '-F';
        $command = 'stty '.$fileFlag.' '.escapeshellarg($device).' '.implode(' ', $flags).' 2>&1';
        $output = [];
        $code = 0;
        @exec($command, $output, $code);

        if ($code !== 0) {
            $this->warn('stty output: '.implode(' ', $output));

            return false;
        }

        return true;
    }

    private function configureWindowsDevice(
        string $device,
        int $baud,
        string $parity,
        int $dataBits,
        int $stopBits,
        string $flowControl,
    ): bool {
        $port = strtoupper($device);
        if (str_starts_with($port, '\\\\.\\COM')) {
            $port = substr($port, 4);
        }

        $parityLetter = match ($parity) {
            'even' => 'E',
            'odd' => 'O',
            default => 'N',
        };

        $xon = $flowControl === 'software' ? 'on' : 'off';
        $octs = $flowControl === 'hardware' ? 'on' : 'off';

        if ($flowControl === 'hardware' && $this->output->isVerbose()) {
            $this->line('Using Windows hardware flow-control approximation (octs/odsr).');
        }

        $command = 'mode '.escapeshellarg($port)
            .' BAUD='.$baud
            .' PARITY='.$parityLetter
            .' DATA='.$dataBits
            .' STOP='.$stopBits
            .' xon='.$xon
            .' octs='.$octs
            .' odsr='.$octs
            .' 2>&1';

        $output = [];
        $code = 0;
        @exec($command, $output, $code);

        if ($code !== 0) {
            $this->warn('mode output: '.implode(' ', $output));

            return false;
        }

        return true;
    }

    private function osFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    private function isWindows(): bool
    {
        return $this->osFamily() === 'Windows';
    }

    private function paritySuffix(string $parity): string
    {
        return match ($parity) {
            'even' => 'E',
            'odd' => 'O',
            default => 'N',
        };
    }

    private function printChunkPreview(string $chunk, string $format, int $maxBytes): void
    {
        [$preview, $omittedBytes] = $this->limitChunkBytes($chunk, $maxBytes);
        $actualFormat = $format === 'auto'
            ? ($this->isPrintableChunk($preview) ? 'plain' : 'hex')
            : $format;

        if ($actualFormat === 'raw') {
            $this->output->write('[data] ');
            $this->output->write($preview);

            if (! str_ends_with($preview, "\n")) {
                $this->newLine();
            }
        } else {
            $rendered = match ($actualFormat) {
                'hex' => strtoupper(implode(' ', str_split(bin2hex($preview), 2))),
                'base64' => base64_encode($preview),
                default => str_replace(["\r", "\n", "\t"], ['\\r', '\\n', '\\t'], $preview),
            };

            $this->line("[data:{$actualFormat}] {$rendered}");
        }

        if ($omittedBytes > 0) {
            $this->line("[data] ... truncated {$omittedBytes} bytes");
        }
    }

    /**
     * @return array{string, int}
     */
    private function limitChunkBytes(string $chunk, int $maxBytes): array
    {
        $length = strlen($chunk);
        if ($length <= $maxBytes) {
            return [$chunk, 0];
        }

        return [substr($chunk, 0, $maxBytes), $length - $maxBytes];
    }

    private function isPrintableChunk(string $chunk): bool
    {
        $length = strlen($chunk);

        for ($index = 0; $index < $length; $index++) {
            $byte = ord($chunk[$index]);

            if ($byte === 9 || $byte === 10 || $byte === 13) {
                continue;
            }

            if ($byte < 32 || $byte > 126) {
                return false;
            }
        }

        return true;
    }

    private function optionString(string $name, string $default = ''): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : $default;
    }

    private function argumentString(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    private function printLineBufferedChunk(string $chunk, string $format, int $maxBytes, string &$buffer): void
    {
        $buffer .= $chunk;
        $normalized = str_replace(["\r\n", "\r"], "\n", $buffer);
        $lines = explode("\n", $normalized);

        /** @var string $lastSegment */
        $lastSegment = array_pop($lines);
        $buffer = $lastSegment;

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $this->printDisplayText($line, $format, $maxBytes);
        }
    }

    private function printDisplayText(string $text, string $format, int $maxBytes): void
    {
        [$preview, $omittedBytes] = $this->limitChunkBytes($text, $maxBytes);

        $actualFormat = $format;
        if ($format === 'auto') {
            $decoded = json_decode($preview, true);
            $actualFormat = json_last_error() === JSON_ERROR_NONE ? 'json' : ($this->isPrintableChunk($preview) ? 'plain' : 'hex');
        }

        if ($actualFormat === 'json') {
            $decoded = json_decode($preview, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rendered = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $this->line('[data:json] '.$rendered);
            } else {
                $escaped = str_replace(["\r", "\n", "\t"], ['\\r', '\\n', '\\t'], $preview);
                $this->line('[data:plain] '.$escaped);
            }
        } elseif ($actualFormat === 'plain') {
            $escaped = str_replace(["\r", "\n", "\t"], ['\\r', '\\n', '\\t'], $preview);
            $this->line('[data:plain] '.$escaped);
        } elseif ($actualFormat === 'hex') {
            $this->line('[data:hex] '.strtoupper(implode(' ', str_split(bin2hex($preview), 2))));
        } else {
            $this->printChunkPreview($preview, $actualFormat, $maxBytes);

            return;
        }

        if ($omittedBytes > 0) {
            $this->line("[data] ... truncated {$omittedBytes} bytes");
        }
    }
}
