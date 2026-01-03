<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeWithReverb extends Command
{
    protected $signature = 'serve:full {--host=127.0.0.1} {--port=8000}';
    protected $description = 'Start Laravel server with Reverb WebSocket server';

    protected $processes = [];

    public function handle()
    {
        $host = $this->option('host');
        $port = $this->option('port');

        $this->info('Starting Laravel Development Environment...');
        $this->newLine();

        // Start Reverb in background
        $this->line('Starting Reverb WebSocket Server...');
        $reverbProcess = new Process(['php', 'artisan', 'reverb:start']);
        $reverbProcess->setWorkingDirectory(base_path());
        $reverbProcess->setTimeout(null);
        $reverbProcess->start();
        $this->processes[] = $reverbProcess;
        
        sleep(2); // Give Reverb time to start
        
        if ($reverbProcess->isRunning()) {
            $this->info('Reverb started on port 8080');
        } else {
            $this->error('Failed to start Reverb');
            $this->line($reverbProcess->getErrorOutput());
        }

        $this->newLine();
        $this->line("Starting Laravel Server on http://{$host}:{$port}");
        $this->newLine();

        // Register shutdown handler to cleanup processes
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () {
            $this->cleanup();
            exit(0);
        });
        pcntl_signal(SIGTERM, function () {
            $this->cleanup();
            exit(0);
        });

        // Start Laravel server (blocking)
        $serverProcess = new Process(['php', 'artisan', 'serve', "--host={$host}", "--port={$port}"]);
        $serverProcess->setWorkingDirectory(base_path());
        $serverProcess->setTimeout(null);
        $serverProcess->setTty(Process::isTtySupported());
        
        $serverProcess->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        $this->cleanup();
    }

    protected function cleanup()
    {
        $this->newLine();
        $this->line('Shutting down...');
        
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(3, SIGTERM);
            }
        }
        
        $this->info('Done');
    }
}
