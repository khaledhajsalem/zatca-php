<?php

namespace KhaledHajSalem\ZatcaLaravel\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'zatca:install';

    protected $description = 'Install the ZATCA Laravel package';

    public function handle(): int
    {
        $this->info('Installing ZATCA Laravel...');
        $this->newLine();

        // Publish config
        $this->info('Publishing configuration...');
        $this->call('vendor:publish', ['--tag' => 'zatca-config']);

        // Publish assets
        $this->info('Publishing assets...');
        $this->call('vendor:publish', ['--tag' => 'zatca-assets', '--force' => true]);

        // Publish service provider
        $this->info('Publishing service provider...');
        $this->call('vendor:publish', ['--tag' => 'zatca-provider']);

        // Run migrations
        if ($this->confirm('Run database migrations?', true)) {
            $this->info('Running migrations...');
            $this->call('migrate');
        }

        // Create storage directory
        $storagePath = storage_path('zatca');
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
            $this->info("Created storage directory: {$storagePath}");
        }

        $this->newLine();
        $this->info('ZATCA Laravel installed successfully!');
        $this->newLine();

        $this->line('Next steps:');
        $this->line('  1. Configure your .env file with ZATCA_ variables');
        $this->line('  2. Update app/Providers/ZatcaServiceProvider.php with authorized emails');
        $this->line('  3. Run: php artisan zatca:generate-csr');
        $this->line('  4. Run: php artisan zatca:onboard --otp=YOUR_OTP');
        $this->line('  5. Visit: ' . url(config('zatca.path', 'zatca')));

        return self::SUCCESS;
    }
}
