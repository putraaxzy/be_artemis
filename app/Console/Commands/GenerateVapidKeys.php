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
    protected $signature = 'vapid:keys {--force : Overwrite existing keys}';

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

            // Cek apakah key sudah ada
            $envPath = base_path('.env');
            if (file_exists($envPath)) {
                $envContent = file_get_contents($envPath);
                if (Str::contains($envContent, 'VAPID_PUBLIC_KEY=') && !$this->option('force')) {
                    if (!$this->confirm('VAPID keys sudah ada. Apakah ingin generate ulang?', false)) {
                        $this->info('Operasi dibatalkan.');
                        return self::SUCCESS;
                    }
                }
            }

            $this->info('Generating VAPID keys untuk Web Push API...');

            // Generate P-256 EC key (required for Web Push)
            $config = [
                "private_key_type" => OPENSSL_KEYTYPE_EC,
                "curve_name" => "prime256v1", // P-256 curve, required for Web Push
            ];

            $res = openssl_pkey_new($config);
            if (!$res) {
                $this->error('Gagal membuat private key: ' . openssl_error_string());
                return self::FAILURE;
            }

            // Get key details
            $details = openssl_pkey_get_details($res);
            if (!$details || !isset($details['ec'])) {
                $this->error('Gagal mendapatkan EC key details');
                return self::FAILURE;
            }

            // Extract raw public key (uncompressed format: 0x04 + x + y)
            $x = $details['ec']['x'];
            $y = $details['ec']['y'];
            
            // Pad x and y to 32 bytes each
            $x = str_pad($x, 32, "\0", STR_PAD_LEFT);
            $y = str_pad($y, 32, "\0", STR_PAD_LEFT);
            
            // Create uncompressed public key (65 bytes: 0x04 prefix + 32 bytes x + 32 bytes y)
            $rawPublicKey = "\x04" . $x . $y;
            
            // Extract raw private key (d value)
            $d = $details['ec']['d'];
            $d = str_pad($d, 32, "\0", STR_PAD_LEFT);
            
            // Encode as URL-safe base64 (no padding)
            $publicKeyBase64 = $this->base64UrlEncode($rawPublicKey);
            $privateKeyBase64 = $this->base64UrlEncode($d);

            $this->info('VAPID Keys generated successfully!');
            $this->newLine();

            // Display keys
            $this->line('<fg=cyan>Public Key (URL-safe base64, ' . strlen($publicKeyBase64) . ' chars):</fg=cyan>');
            $this->line($publicKeyBase64);
            $this->newLine();

            $this->line('<fg=cyan>Private Key (URL-safe base64, ' . strlen($privateKeyBase64) . ' chars):</fg=cyan>');
            $this->line($privateKeyBase64);
            $this->newLine();

            // Update atau create .env file
            if (!file_exists($envPath)) {
                $this->warn('.env file not found, creating new one...');
                file_put_contents($envPath, '');
            }

            $envContent = file_get_contents($envPath);

            // Replace atau append VAPID keys
            if (Str::contains($envContent, 'VAPID_PUBLIC_KEY=')) {
                $envContent = preg_replace(
                    '/VAPID_PUBLIC_KEY=.*/',
                    'VAPID_PUBLIC_KEY=' . $publicKeyBase64,
                    $envContent
                );
            } else {
                $envContent .= "\nVAPID_PUBLIC_KEY=" . $publicKeyBase64 . "\n";
            }

            if (Str::contains($envContent, 'VAPID_PRIVATE_KEY=')) {
                $envContent = preg_replace(
                    '/VAPID_PRIVATE_KEY=.*/',
                    'VAPID_PRIVATE_KEY=' . $privateKeyBase64,
                    $envContent
                );
            } else {
                $envContent .= "VAPID_PRIVATE_KEY=" . $privateKeyBase64 . "\n";
            }

            file_put_contents($envPath, $envContent);

            $this->info('VAPID keys berhasil disimpan ke .env');
            $this->newLine();

            $this->line('<fg=green;options=bold>Langkah berikutnya:</fg=green;options=bold>');
            $this->line('1. Jalankan: <fg=yellow>php artisan config:clear</fg=yellow>');
            $this->line('2. Restart server PHP');
            $this->line('3. Frontend akan otomatis menggunakan VAPID public key');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Encode string ke URL-safe base64 (tanpa padding)
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
