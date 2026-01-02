<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateVapidKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vapid:keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate VAPID keys untuk push notification dan update .env';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // Cek apakah OpenSSL tersedia
            if (!extension_loaded('openssl')) {
                $this->error('OpenSSL extension tidak tersedia. Install OpenSSL terlebih dahulu.');
                return self::FAILURE;
            }

            $this->info('Generating VAPID keys...');

            // Generate EC key untuk VAPID (384 bits untuk security)
            $config = [
                "private_key_bits" => 384,
                "private_key_type" => OPENSSL_KEYTYPE_EC,
                "curve_name" => "secp384r1",
            ];

            $res = openssl_pkey_new($config);
            if (!$res) {
                $this->error('Gagal membuat private key');
                return self::FAILURE;
            }

            openssl_pkey_export($res, $privKey);
            $pubKeyDetails = openssl_pkey_get_details($res);
            $pubKey = $pubKeyDetails["key"];

            // Clean up keys (hapus PEM headers dan newlines)
            $privKeyForEnv = str_replace(["\n", "\r"], "", $privKey);
            $pubKeyForEnv = str_replace(["\n", "\r"], "", $pubKey);

            $this->info('VAPID Keys generated successfully!');
            $this->newLine();

            // Display keys
            $this->line('<fg=cyan>Public Key:</fg=cyan>');
            $this->line($pubKey);
            $this->newLine();

            $this->line('<fg=cyan>Private Key:</fg=cyan>');
            $this->line($privKey);
            $this->newLine();

            // Update atau create .env file
            $envPath = base_path('.env');
            
            if (!file_exists($envPath)) {
                $this->warn('.env file not found, creating new one...');
                file_put_contents($envPath, '');
            }

            $envContent = file_get_contents($envPath);

            // Replace atau append VAPID keys
            if (Str::contains($envContent, 'VAPID_PUBLIC_KEY')) {
                $envContent = preg_replace(
                    '/VAPID_PUBLIC_KEY=.*/',
                    'VAPID_PUBLIC_KEY="' . $pubKeyForEnv . '"',
                    $envContent
                );
            } else {
                $envContent .= "\nVAPID_PUBLIC_KEY=\"" . $pubKeyForEnv . "\"\n";
            }

            if (Str::contains($envContent, 'VAPID_PRIVATE_KEY')) {
                $envContent = preg_replace(
                    '/VAPID_PRIVATE_KEY=.*/',
                    'VAPID_PRIVATE_KEY="' . $privKeyForEnv . '"',
                    $envContent
                );
            } else {
                $envContent .= "VAPID_PRIVATE_KEY=\"" . $privKeyForEnv . "\"\n";
            }

            file_put_contents($envPath, $envContent);

            $this->info('✓ VAPID keys berhasil disimpan ke .env');
            $this->newLine();

            $this->line('<fg=green;options=bold>Langkah berikutnya:</fg=green;options=bold>');
            $this->line('1. Jalankan: <fg=yellow>php artisan migrate</fg=yellow>');
            $this->line('2. Restart aplikasi');
            $this->line('3. Frontend akan otomatis menggunakan VAPID public key');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
