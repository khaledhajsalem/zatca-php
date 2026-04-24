# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2025-01-15

### Added
- Initial release
- Fluent Invoice Builder with `Zatca::invoice()` Facade
- Telescope-style dashboard with 8 pages (Dashboard, Invoices, Invoice Detail, API Logs, Certificates, Devices, Chain, Settings)
- Database migrations for 5 tables (devices, certificates, invoices, api_logs, invoice_chains)
- Eloquent models with relationships and scopes
- Auto chain management (ICV/PIH per device)
- Certificate lifecycle management (CSR → compliance → production)
- API call logging with request/response and timing
- Invoice validation (VAT numbers, required fields, formats)
- Artisan commands: `zatca:install`, `zatca:generate-csr`, `zatca:onboard`, `zatca:compliance-check`, `zatca:status`, `zatca:prune`
- Events: `InvoiceCreated`, `InvoiceSubmitted`, `InvoiceCleared`, `InvoiceReported`, `InvoiceRejected`, `CertificateExpiring`, `ApiCallFailed`
- Gate-based dashboard authorization
- Dark/light mode toggle
- Responsive mobile layout
- `Invoiceable` contract for Eloquent model integration
