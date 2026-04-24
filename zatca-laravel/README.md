# ZATCA Laravel

A Laravel package for **ZATCA Phase 2 e-invoicing** with a built-in **Telescope-style dashboard** for monitoring invoices, API calls, certificates, and compliance status.

<p align="center">
<a href="https://packagist.org/packages/khaledhajsalem/zatca-laravel"><img src="https://img.shields.io/packagist/v/khaledhajsalem/zatca-laravel.svg" alt="Latest Version"></a>
<a href="https://packagist.org/packages/khaledhajsalem/zatca-laravel"><img src="https://img.shields.io/packagist/dt/khaledhajsalem/zatca-laravel.svg" alt="Total Downloads"></a>
<a href="https://github.com/khaledhajsalem/zatca-laravel/blob/main/LICENSE"><img src="https://img.shields.io/packagist/l/khaledhajsalem/zatca-laravel.svg" alt="License"></a>
</p>

## Features

- **Fluent Invoice Builder** — Create, sign, and submit invoices in 3 lines of code
- **Telescope-Style Dashboard** — Real-time monitoring at `/zatca` with 8 interactive pages
- **Auto Chain Management** — ICV/PIH automatically tracked per device
- **Certificate Lifecycle** — CSR generation, compliance, production, renewal, and expiry alerts
- **Multi-Device Support** — Manage multiple EGS (POS/branch) devices
- **API Observability** — Every ZATCA API call logged with request/response, timing, and retries
- **Invoice Validation** — Validate VAT numbers, required fields, and formats before submission
- **Artisan Commands** — `zatca:install`, `zatca:generate-csr`, `zatca:onboard`, `zatca:status`, `zatca:prune`
- **Events System** — Hook into `InvoiceCreated`, `InvoiceCleared`, `InvoiceRejected`, `CertificateExpiring`, etc.
- **Gate Authorization** — Restrict dashboard access with Laravel's Gate

## Requirements

- PHP ^8.1
- Laravel ^10.0 | ^11.0 | ^12.0
- [khaledhajsalem/zatca-php](https://github.com/khaledhajsalem/zatca-php) ^1.0

## Installation

```bash
composer require khaledhajsalem/zatca-laravel
```

Run the install command:

```bash
php artisan zatca:install
```

This will:
1. Publish the config file (`config/zatca.php`)
2. Publish the dashboard assets
3. Publish the authorization service provider
4. Run database migrations (5 tables)

## Configuration

Add these to your `.env`:

```env
ZATCA_ENVIRONMENT=sandbox           # sandbox, simulation, production
ZATCA_SELLER_NAME="My Company LLC"
ZATCA_SELLER_VAT=399999999900003
ZATCA_SELLER_PARTY_ID=1010203040
ZATCA_SELLER_STREET="Main Street"
ZATCA_SELLER_BUILDING=1234
ZATCA_SELLER_CITY=Riyadh
ZATCA_SELLER_POSTAL=12345
ZATCA_SELLER_DISTRICT="Al Olaya"
```

## Quick Start

### 1. Generate CSR & Onboard

```bash
php artisan zatca:generate-csr
php artisan zatca:onboard --otp=123456
```

### 2. Create & Submit Invoices

```php
use KhaledHajSalem\ZatcaLaravel\Facades\Zatca;

// Simplified Invoice (B2C)
$result = Zatca::invoice()
    ->simplified()
    ->taxInvoice()
    ->number('INV-001')
    ->date('2025-01-15', '10:30:00')
    ->line('Product A', qty: 2, price: 100.00, tax: 15)
    ->line('Service B', qty: 1, price: 50.00, tax: 15)
    ->submit();

// Result
$result->uuid;         // Invoice UUID
$result->hash;         // Invoice hash
$result->qrCode;       // QR code (base64)
$result->status;       // 'reported', 'cleared', 'rejected'
$result->isSuccessful(); // true/false
$result->warnings();   // ZATCA warning messages
$result->errors();     // ZATCA error messages

// Standard Invoice (B2B) with buyer
$result = Zatca::invoice()
    ->standard()
    ->taxInvoice()
    ->number('INV-002')
    ->date('2025-01-15', '10:30:00')
    ->buyer(fn ($b) => $b
        ->name('ACME Corp')
        ->vat('300000000000003')
        ->address('King Fahd Rd', '5678', 'Jeddah', '54321'))
    ->line('Enterprise License', qty: 1, price: 50000.00, tax: 15)
    ->submit();

// Credit Note
$result = Zatca::invoice()
    ->simplified()
    ->creditNote()
    ->number('CN-001')
    ->date('2025-01-16', '09:00:00')
    ->billingReference('INV-001')
    ->line('Returned Product A', qty: 1, price: 100.00, tax: 15)
    ->submit();
```

### 3. Certificate Management

```php
// Check certificate status
$cert = Zatca::certificate()->info();
$cert->daysUntilExpiry();
$cert->isExpiringSoon();

// Generate CSR programmatically
$csr = Zatca::certificate()
    ->orgIdentifier('399999999900003')
    ->serialNumber('MySolution', 'Model1', 'SN001')
    ->commonName('My Company')
    ->generateCsr();
```

### 4. Device Management

```php
// List all devices
$devices = Zatca::device()->all();

// Create a new device
$device = Zatca::device()->create('POS-Branch-2', 'SN002', [
    'solution_name' => 'MyERP',
    'model'         => 'Model1',
    'environment'   => 'production',
]);
```

### 5. Chain Verification

```php
// Verify chain integrity for a device
$result = Zatca::chain()->verify($deviceId, '0200000');
$result['is_valid'];       // true/false
$result['total_invoices']; // count
$result['errors'];         // gap descriptions
```

### 6. Dashboard Stats

```php
$stats = Zatca::stats()->today();
$stats->totalInvoices;
$stats->totalCleared;
$stats->totalRejected;
$stats->totalRevenue;

$trend = Zatca::stats()->trend(7); // 7-day trend
```

## Dashboard

Access the dashboard at `/zatca` (configurable via `ZATCA_PATH`).

### Pages

| Page | Description |
|------|-------------|
| **Dashboard** | Stats cards, 7-day trend chart, status donut, recent invoices, cert health |
| **Invoices** | Filterable/searchable list with status badges, pagination |
| **Invoice Detail** | 5 tabs: Overview, Signed XML, QR Code, API Log, Chain position |
| **API Logs** | Every ZATCA API call with expandable request/response |
| **Certificates** | Lifecycle timeline, expiry progress bars, renewal buttons |
| **Devices** | EGS registry with cert status, ICV counters, invoice counts |
| **Chain** | ICV/PIH hash chain visualization with integrity verification |
| **Settings** | Read-only config, seller info, health checks |

### Authorization

Update `app/Providers/ZatcaServiceProvider.php` to control dashboard access:

```php
protected function gate(): void
{
    Gate::define('viewZatcaDashboard', function ($user) {
        return in_array($user->email, [
            'admin@example.com',
        ]);
    });
}
```

## Events

| Event | Fired When |
|-------|-----------|
| `InvoiceCreated` | Invoice record created (draft or submitted) |
| `InvoiceSubmitted` | Invoice submitted to ZATCA API |
| `InvoiceCleared` | B2B invoice cleared by ZATCA |
| `InvoiceReported` | B2C invoice reported to ZATCA |
| `InvoiceRejected` | Invoice rejected by ZATCA |
| `CertificateExpiring` | Certificate expires within configured days |
| `ApiCallFailed` | ZATCA API call failed |

```php
// In your EventServiceProvider
use KhaledHajSalem\ZatcaLaravel\Events\InvoiceRejected;

protected $listen = [
    InvoiceRejected::class => [
        \App\Listeners\NotifyAccountant::class,
    ],
];
```

## Artisan Commands

```bash
php artisan zatca:install          # Install package (config, migrations, assets)
php artisan zatca:generate-csr     # Generate Certificate Signing Request
php artisan zatca:onboard --otp=X  # Complete onboarding (CSR → compliance → production)
php artisan zatca:compliance-check # Run compliance test invoices
php artisan zatca:status           # Show integration status
php artisan zatca:prune            # Remove old records
```

## Database Tables

| Table | Purpose |
|-------|---------|
| `zatca_devices` | EGS device registry |
| `zatca_certificates` | Certificate storage (compliance + production) |
| `zatca_invoices` | Invoice records with signed XML, QR, hash, ZATCA response |
| `zatca_api_logs` | Every API call to ZATCA with full request/response |
| `zatca_invoice_chains` | ICV/PIH chain state per device |

## Pruning

Schedule automatic cleanup in `app/Console/Kernel.php`:

```php
$schedule->command('zatca:prune')->daily();
```

Configure retention in `.env`:

```env
ZATCA_PRUNE_INVOICES=365   # Keep invoices for 1 year
ZATCA_PRUNE_API_LOGS=90    # Keep API logs for 3 months
```

## Invoiceable Trait

Implement the `Invoiceable` contract on your Eloquent models:

```php
use KhaledHajSalem\ZatcaLaravel\Contracts\Invoiceable;

class Order extends Model implements Invoiceable
{
    public function toZatcaInvoice(): array
    {
        return [
            'type'     => 'simplified',
            'sub_type' => 'tax_invoice',
            'date'     => $this->created_at->format('Y-m-d'),
            'time'     => $this->created_at->format('H:i:s'),
        ];
    }

    public function getZatcaInvoiceNumber(): string
    {
        return 'INV-' . $this->id;
    }

    public function getZatcaLines(): array
    {
        return $this->items->map(fn ($item) => [
            'name'  => $item->product->name,
            'qty'   => $item->quantity,
            'price' => $item->unit_price,
            'tax'   => 15,
        ])->toArray();
    }
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
