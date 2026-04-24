/* ============================================
   ZATCA Laravel Dashboard — App JS
   ============================================ */

// ── Theme Toggle ──
const html = document.documentElement;
const themeBtn = document.getElementById('themeToggle');
const saved = localStorage.getItem('zatca-theme');
if (saved) html.dataset.theme = saved;
themeBtn.addEventListener('click', () => {
  const next = html.dataset.theme === 'dark' ? 'light' : 'dark';
  html.dataset.theme = next;
  localStorage.setItem('zatca-theme', next);
});

// ── Sidebar Mobile ──
const sidebar = document.getElementById('sidebar');
const menuBtn = document.getElementById('menuBtn');
const sidebarClose = document.getElementById('sidebarClose');
menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
sidebarClose.addEventListener('click', () => sidebar.classList.remove('open'));

// ── Navigation ──
const navItems = document.querySelectorAll('.nav-item');
const pageTitle = document.getElementById('pageTitle');
const content = document.getElementById('content');

const pageTitles = {
  'dashboard': 'Dashboard',
  'invoices': 'Invoices',
  'invoice-detail': 'Invoice Detail',
  'api-logs': 'API Logs',
  'certificates': 'Certificates',
  'devices': 'Devices (EGS)',
  'chain': 'Invoice Chain',
  'settings': 'Settings'
};

function navigate(page) {
  navItems.forEach(n => n.classList.remove('active'));
  document.querySelector(`[data-page="${page}"]`).classList.add('active');
  pageTitle.textContent = pageTitles[page] || page;
  content.innerHTML = pages[page]();
  sidebar.classList.remove('open');
  if (page === 'dashboard') initCharts();
  if (page === 'invoice-detail') initTabs();
  if (page === 'api-logs') initExpand();
}

navItems.forEach(n => {
  n.addEventListener('click', e => {
    e.preventDefault();
    navigate(n.dataset.page);
  });
});

// ── Page Renderers ──
const pages = {};

// ═══════════════════════════
//  DASHBOARD PAGE
// ═══════════════════════════
pages['dashboard'] = () => `
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon green-bg">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
    </div>
    <div class="stat-label">Today's Invoices</div>
    <div class="stat-value">23</div>
    <div class="stat-sub">+12% from yesterday</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue-bg">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    </div>
    <div class="stat-label">Cleared (B2B)</div>
    <div class="stat-value green">12</div>
    <div class="stat-sub">SAR 45,230.00</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow-bg">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
    </div>
    <div class="stat-label">Reported (B2C)</div>
    <div class="stat-value blue">9</div>
    <div class="stat-sub">SAR 3,450.50</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red-bg">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
    </div>
    <div class="stat-label">Rejected</div>
    <div class="stat-value red">2</div>
    <div class="stat-sub">Needs attention</div>
  </div>
</div>

<div class="grid-3">
  <div class="card">
    <div class="card-header">
      <h2>Invoice Trend (7 Days)</h2>
      <span style="font-size:.75rem;color:var(--text-muted)">Cleared vs Reported</span>
    </div>
    <div class="card-body">
      <div class="chart-bars" id="trendChart">
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
          <div style="display:flex;align-items:flex-end;gap:3px">
            <div class="chart-bar green" style="height:60px;width:16px" title="Cleared: 8"></div>
            <div class="chart-bar blue" style="height:40px;width:16px" title="Reported: 5"></div>
          </div>
          <span class="chart-bar-label" style="position:static">Mon</span>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
          <div style="display:flex;align-items:flex-end;gap:3px">
            <div class="chart-bar green" style="height:90px;width:16px"></div>
            <div class="chart-bar blue" style="height:55px;width:16px"></div>
          </div>
          <span class="chart-bar-label" style="position:static">Tue</span>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
          <div style="display:flex;align-items:flex-end;gap:3px">
            <div class="chart-bar green" style="height:70px;width:16px"></div>
            <div class="chart-bar blue" style="height:35px;width:16px"></div>
          </div>
          <span class="chart-bar-label" style="position:static">Wed</span>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
          <div style="display:flex;align-items:flex-end;gap:3px">
            <div class="chart-bar green" style="height:110px;width:16px"></div>
            <div class="chart-bar blue" style="height:60px;width:16px"></div>
          </div>
          <span class="chart-bar-label" style="position:static">Thu</span>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
          <div style="display:flex;align-items:flex-end;gap:3px">
            <div class="chart-bar green" style="height:50px;width:16px"></div>
            <div class="chart-bar blue" style="height:30px;width:16px"></div>
          </div>
          <span class="chart-bar-label" style="position:static">Fri</span>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
          <div style="display:flex;align-items:flex-end;gap:3px">
            <div class="chart-bar green" style="height:85px;width:16px"></div>
            <div class="chart-bar blue" style="height:45px;width:16px"></div>
          </div>
          <span class="chart-bar-label" style="position:static">Sat</span>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
          <div style="display:flex;align-items:flex-end;gap:3px">
            <div class="chart-bar green" style="height:95px;width:16px"></div>
            <div class="chart-bar blue" style="height:65px;width:16px"></div>
          </div>
          <span class="chart-bar-label" style="position:static">Sun</span>
        </div>
      </div>
      <div style="display:flex;gap:16px;justify-content:center;margin-top:20px">
        <span class="legend-item"><span class="legend-dot" style="background:var(--green);width:10px;height:10px;border-radius:3px;display:inline-block"></span> Cleared</span>
        <span class="legend-item"><span class="legend-dot" style="background:var(--blue);width:10px;height:10px;border-radius:3px;display:inline-block"></span> Reported</span>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h2>Status Breakdown</h2></div>
    <div class="card-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;">
      <div class="donut-placeholder"></div>
      <div class="donut-legend">
        <div class="legend-item"><span class="legend-dot" style="background:var(--green)"></span> Cleared — 52%</div>
        <div class="legend-item"><span class="legend-dot" style="background:var(--blue)"></span> Reported — 39%</div>
        <div class="legend-item"><span class="legend-dot" style="background:var(--yellow)"></span> Warning — 5%</div>
        <div class="legend-item"><span class="legend-dot" style="background:var(--red)"></span> Rejected — 4%</div>
      </div>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <h2>Recent Invoices</h2>
      <a href="#" class="card-link" onclick="event.preventDefault();navigate('invoices')">View All &rarr;</a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>#</th><th>Number</th><th>Type</th><th>Amount</th><th>Status</th><th>Time</th></tr></thead>
        <tbody>
          <tr><td>23</td><td>INV-023</td><td><span class="badge badge-blue">B2C</span></td><td>SAR 115.00</td><td><span class="badge badge-green"><span class="badge-dot green"></span>Reported</span></td><td>2m ago</td></tr>
          <tr><td>22</td><td>INV-022</td><td><span class="badge badge-green">B2B</span></td><td>SAR 5,750.00</td><td><span class="badge badge-green"><span class="badge-dot green"></span>Cleared</span></td><td>15m ago</td></tr>
          <tr><td>21</td><td>INV-021</td><td><span class="badge badge-blue">B2C</span></td><td>SAR 57.50</td><td><span class="badge badge-red"><span class="badge-dot red"></span>Rejected</span></td><td>1h ago</td></tr>
          <tr><td>20</td><td>CN-003</td><td><span class="badge badge-yellow">CN</span></td><td>SAR -500.00</td><td><span class="badge badge-yellow"><span class="badge-dot yellow"></span>Warning</span></td><td>2h ago</td></tr>
          <tr><td>19</td><td>INV-019</td><td><span class="badge badge-green">B2B</span></td><td>SAR 12,340.00</td><td><span class="badge badge-green"><span class="badge-dot green"></span>Cleared</span></td><td>3h ago</td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h2>Certificate Status</h2></div>
    <div class="card-body">
      <div style="margin-bottom:20px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <span style="font-weight:600;font-size:.9rem">POS-01</span>
          <span class="badge badge-green">Active</span>
        </div>
        <div style="font-size:.8rem;color:var(--text-secondary);margin-bottom:4px">Production Certificate</div>
        <div class="cert-progress"><div class="cert-progress-fill" style="width:75%;background:var(--green)"></div></div>
        <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-muted)">
          <span>Issued: Jan 1, 2025</span><span>Expires in 89 days</span>
        </div>
      </div>
      <div style="margin-bottom:20px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <span style="font-weight:600;font-size:.9rem">POS-02</span>
          <span class="badge badge-yellow">Pending</span>
        </div>
        <div style="font-size:.8rem;color:var(--text-secondary);margin-bottom:4px">Compliance Certificate</div>
        <div class="cert-progress"><div class="cert-progress-fill" style="width:33%;background:var(--yellow)"></div></div>
        <div style="font-size:.72rem;color:var(--text-muted)">Awaiting production certificate</div>
      </div>
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <span style="font-weight:600;font-size:.9rem">ERP-Main</span>
          <span class="badge badge-green">Active</span>
        </div>
        <div style="font-size:.8rem;color:var(--text-secondary);margin-bottom:4px">Production Certificate</div>
        <div class="cert-progress"><div class="cert-progress-fill" style="width:90%;background:var(--green)"></div></div>
        <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-muted)">
          <span>Issued: Oct 15, 2024</span><span>Expires in 180 days</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>Avg API Response Time</h2>
    <span style="font-size:.75rem;color:var(--text-muted)">Last 7 days</span>
  </div>
  <div class="card-body">
    <div class="chart-bars" style="height:100px">
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <div class="chart-bar accent" style="height:50px;width:24px"></div>
        <span style="font-size:.65rem;color:var(--text-muted)">245ms</span>
      </div>
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <div class="chart-bar accent" style="height:35px;width:24px"></div>
        <span style="font-size:.65rem;color:var(--text-muted)">189ms</span>
      </div>
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <div class="chart-bar accent" style="height:70px;width:24px"></div>
        <span style="font-size:.65rem;color:var(--text-muted)">412ms</span>
      </div>
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <div class="chart-bar accent" style="height:45px;width:24px"></div>
        <span style="font-size:.65rem;color:var(--text-muted)">220ms</span>
      </div>
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <div class="chart-bar accent" style="height:30px;width:24px"></div>
        <span style="font-size:.65rem;color:var(--text-muted)">165ms</span>
      </div>
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <div class="chart-bar accent" style="height:55px;width:24px"></div>
        <span style="font-size:.65rem;color:var(--text-muted)">310ms</span>
      </div>
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
        <div class="chart-bar accent" style="height:40px;width:24px"></div>
        <span style="font-size:.65rem;color:var(--text-muted)">198ms</span>
      </div>
    </div>
  </div>
</div>
`;

// ═══════════════════════════
//  INVOICES PAGE
// ═══════════════════════════
pages['invoices'] = () => `
<div class="filters-bar">
  <div class="search-input">
    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
    <input type="text" placeholder="Search invoices...">
  </div>
  <select class="filter-select">
    <option>All Statuses</option>
    <option>Cleared</option>
    <option>Reported</option>
    <option>Rejected</option>
    <option>Warning</option>
    <option>Draft</option>
  </select>
  <select class="filter-select">
    <option>All Types</option>
    <option>Standard (B2B)</option>
    <option>Simplified (B2C)</option>
    <option>Credit Note</option>
    <option>Debit Note</option>
  </select>
  <select class="filter-select">
    <option>All Devices</option>
    <option>POS-01</option>
    <option>POS-02</option>
    <option>ERP-Main</option>
  </select>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Invoice Number</th><th>Type</th><th>Buyer</th><th>Amount</th><th>ZATCA Status</th><th>Environment</th><th>Time</th>
        </tr>
      </thead>
      <tbody>
        <tr style="cursor:pointer" onclick="navigate('invoice-detail')">
          <td>23</td><td><strong>INV-023</strong></td>
          <td><span class="badge badge-blue">B2C</span></td>
          <td>Cash Customer</td>
          <td>SAR 115.00</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>PASS</span></td>
          <td><span class="badge badge-gray">prod</span></td>
          <td style="color:var(--text-muted)">2 min ago</td>
        </tr>
        <tr style="cursor:pointer" onclick="navigate('invoice-detail')">
          <td>22</td><td><strong>INV-022</strong></td>
          <td><span class="badge badge-green">B2B</span></td>
          <td>ACME Corp</td>
          <td>SAR 5,750.00</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>PASS</span></td>
          <td><span class="badge badge-gray">prod</span></td>
          <td style="color:var(--text-muted)">15 min ago</td>
        </tr>
        <tr style="cursor:pointer" onclick="navigate('invoice-detail')">
          <td>21</td><td><strong>INV-021</strong></td>
          <td><span class="badge badge-blue">B2C</span></td>
          <td>Walk-in</td>
          <td>SAR 57.50</td>
          <td><span class="badge badge-red"><span class="badge-dot red"></span>ERROR</span></td>
          <td><span class="badge badge-gray">prod</span></td>
          <td style="color:var(--text-muted)">1 hour ago</td>
        </tr>
        <tr style="cursor:pointer" onclick="navigate('invoice-detail')">
          <td>20</td><td><strong>CN-003</strong></td>
          <td><span class="badge badge-yellow">Credit Note</span></td>
          <td>ACME Corp</td>
          <td>SAR -500.00</td>
          <td><span class="badge badge-yellow"><span class="badge-dot yellow"></span>WARNING</span></td>
          <td><span class="badge badge-gray">sim</span></td>
          <td style="color:var(--text-muted)">2 hours ago</td>
        </tr>
        <tr style="cursor:pointer" onclick="navigate('invoice-detail')">
          <td>19</td><td><strong>INV-019</strong></td>
          <td><span class="badge badge-green">B2B</span></td>
          <td>Saudi Telecom</td>
          <td>SAR 12,340.00</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>PASS</span></td>
          <td><span class="badge badge-gray">prod</span></td>
          <td style="color:var(--text-muted)">3 hours ago</td>
        </tr>
        <tr style="cursor:pointer" onclick="navigate('invoice-detail')">
          <td>18</td><td><strong>DN-001</strong></td>
          <td><span class="badge badge-yellow">Debit Note</span></td>
          <td>Al Rajhi</td>
          <td>SAR 250.00</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>PASS</span></td>
          <td><span class="badge badge-gray">prod</span></td>
          <td style="color:var(--text-muted)">5 hours ago</td>
        </tr>
        <tr style="cursor:pointer" onclick="navigate('invoice-detail')">
          <td>17</td><td><strong>INV-017</strong></td>
          <td><span class="badge badge-blue">B2C</span></td>
          <td>Cash Customer</td>
          <td>SAR 89.70</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>PASS</span></td>
          <td><span class="badge badge-gray">prod</span></td>
          <td style="color:var(--text-muted)">6 hours ago</td>
        </tr>
        <tr style="cursor:pointer" onclick="navigate('invoice-detail')">
          <td>16</td><td><strong>INV-016</strong></td>
          <td><span class="badge badge-green">B2B</span></td>
          <td>Aramco Services</td>
          <td>SAR 98,500.00</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>PASS</span></td>
          <td><span class="badge badge-gray">prod</span></td>
          <td style="color:var(--text-muted)">Yesterday</td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="pagination">
    <button class="page-btn">&laquo;</button>
    <button class="page-btn active">1</button>
    <button class="page-btn">2</button>
    <button class="page-btn">3</button>
    <button class="page-btn">&raquo;</button>
  </div>
</div>
`;

// ═══════════════════════════
//  INVOICE DETAIL PAGE
// ═══════════════════════════
pages['invoice-detail'] = () => `
<div class="detail-header">
  <button class="back-btn" onclick="navigate('invoices')">&larr; Back</button>
  <span class="detail-title">INV-023</span>
  <div class="detail-badges">
    <span class="badge badge-green"><span class="badge-dot green"></span>REPORTED</span>
    <span class="badge badge-green">PASS</span>
  </div>
</div>

<div class="tabs">
  <button class="tab active" data-tab="overview">Overview</button>
  <button class="tab" data-tab="xml">Signed XML</button>
  <button class="tab" data-tab="qr">QR Code</button>
  <button class="tab" data-tab="apilog">API Log</button>
  <button class="tab" data-tab="chainpos">Chain</button>
</div>

<div class="tab-content active" id="tab-overview">
  <div class="detail-grid">
    <div class="detail-field"><div class="detail-label">UUID</div><div class="detail-value" style="font-family:'JetBrains Mono',monospace;font-size:.8rem">a1b2c3d4-e5f6-4789-abcd-ef0123456789</div></div>
    <div class="detail-field"><div class="detail-label">Invoice Number</div><div class="detail-value">INV-023</div></div>
    <div class="detail-field"><div class="detail-label">Type</div><div class="detail-value">Simplified Tax Invoice (388 / 0200000)</div></div>
    <div class="detail-field"><div class="detail-label">Date / Time</div><div class="detail-value">2025-01-15 &nbsp; 10:30:00</div></div>
    <div class="detail-field"><div class="detail-label">Currency</div><div class="detail-value">SAR</div></div>
    <div class="detail-field"><div class="detail-label">ICV</div><div class="detail-value">23</div></div>
    <div class="detail-field"><div class="detail-label">Invoice Hash</div><div class="detail-value" style="font-family:'JetBrains Mono',monospace;font-size:.78rem;word-break:break-all">NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2...</div></div>
    <div class="detail-field"><div class="detail-label">Device</div><div class="detail-value">POS-01</div></div>
  </div>

  <div class="party-grid">
    <div class="party-card">
      <h4>Seller</h4>
      <p><strong>My Company LLC</strong></p>
      <p><span class="label">VAT:</span> 399999999900003</p>
      <p><span class="label">CRN:</span> 1010203020</p>
      <p><span class="label">Address:</span> Main Street 1234, Riyadh 12345, SA</p>
    </div>
    <div class="party-card">
      <h4>Buyer</h4>
      <p><strong>Cash Customer</strong></p>
      <p><span class="label">VAT:</span> 300000000000003</p>
      <p><span class="label">CRN:</span> 1010203030</p>
      <p><span class="label">Address:</span> Customer Street 4567, Jeddah 54321, SA</p>
    </div>
  </div>

  <div class="card" style="margin-bottom:20px">
    <div class="card-header"><h2>Line Items</h2></div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Tax %</th><th>Tax Amount</th><th>Total</th></tr></thead>
        <tbody>
          <tr><td>1</td><td>Product 1</td><td>2</td><td>SAR 100.00</td><td>15%</td><td>SAR 30.00</td><td>SAR 230.00</td></tr>
          <tr><td>2</td><td>Service Fee</td><td>1</td><td>SAR 50.00</td><td>15%</td><td>SAR 7.50</td><td>SAR 57.50</td></tr>
        </tbody>
        <tfoot>
          <tr style="font-weight:700;background:var(--bg-input)">
            <td colspan="3">Totals</td><td>SAR 250.00</td><td></td><td>SAR 37.50</td><td>SAR 287.50</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>ZATCA Response</h2></div>
    <div class="card-body">
      <div class="alert alert-green" style="margin-bottom:12px">
        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
        Invoice successfully reported to ZATCA
      </div>
      <div class="json-viewer">{
  <span class="json-key">"reportingStatus"</span>: <span class="json-string">"REPORTED"</span>,
  <span class="json-key">"validationResults"</span>: {
    <span class="json-key">"status"</span>: <span class="json-string">"PASS"</span>,
    <span class="json-key">"infoMessages"</span>: [
      {
        <span class="json-key">"type"</span>: <span class="json-string">"INFO"</span>,
        <span class="json-key">"code"</span>: <span class="json-string">"XSD_ZATCA_VALID"</span>,
        <span class="json-key">"message"</span>: <span class="json-string">"Compliant with ZATCA UBL 2.1 Schema"</span>
      }
    ],
    <span class="json-key">"warningMessages"</span>: [],
    <span class="json-key">"errorMessages"</span>: []
  }
}</div>
    </div>
  </div>
</div>

<div class="tab-content" id="tab-xml">
  <div class="card">
    <div class="card-header">
      <h2>Signed Invoice XML</h2>
      <button class="btn btn-outline" onclick="alert('Copied!')">Copy XML</button>
    </div>
    <div class="card-body">
      <div class="code-block"><span class="comment">&lt;?xml version="1.0" encoding="UTF-8"?&gt;</span>
<span class="tag">&lt;Invoice</span> <span class="attr">xmlns</span>=<span class="val">"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"</span>
         <span class="attr">xmlns:cac</span>=<span class="val">"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"</span>
         <span class="attr">xmlns:cbc</span>=<span class="val">"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"</span>
         <span class="attr">xmlns:ext</span>=<span class="val">"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"</span><span class="tag">&gt;</span>
  <span class="tag">&lt;ext:UBLExtensions&gt;</span>
    <span class="tag">&lt;ext:UBLExtension&gt;</span>
      <span class="tag">&lt;ext:ExtensionURI&gt;</span>urn:oasis:names:specification:ubl:dsig:enveloped:xades<span class="tag">&lt;/ext:ExtensionURI&gt;</span>
      <span class="tag">&lt;ext:ExtensionContent&gt;</span>
        <span class="comment">&lt;!-- Digital Signature Content --&gt;</span>
        <span class="tag">&lt;sig:UBLDocumentSignatures&gt;</span>...<span class="tag">&lt;/sig:UBLDocumentSignatures&gt;</span>
      <span class="tag">&lt;/ext:ExtensionContent&gt;</span>
    <span class="tag">&lt;/ext:UBLExtension&gt;</span>
  <span class="tag">&lt;/ext:UBLExtensions&gt;</span>
  <span class="tag">&lt;cbc:ProfileID&gt;</span>reporting:1.0<span class="tag">&lt;/cbc:ProfileID&gt;</span>
  <span class="tag">&lt;cbc:ID&gt;</span>INV-023<span class="tag">&lt;/cbc:ID&gt;</span>
  <span class="tag">&lt;cbc:UUID&gt;</span>a1b2c3d4-e5f6-4789-abcd-ef0123456789<span class="tag">&lt;/cbc:UUID&gt;</span>
  <span class="tag">&lt;cbc:IssueDate&gt;</span>2025-01-15<span class="tag">&lt;/cbc:IssueDate&gt;</span>
  <span class="tag">&lt;cbc:IssueTime&gt;</span>10:30:00<span class="tag">&lt;/cbc:IssueTime&gt;</span>
  <span class="tag">&lt;cbc:InvoiceTypeCode</span> <span class="attr">name</span>=<span class="val">"0200000"</span><span class="tag">&gt;</span>388<span class="tag">&lt;/cbc:InvoiceTypeCode&gt;</span>
  <span class="comment">&lt;!-- ... remaining invoice XML ... --&gt;</span>
<span class="tag">&lt;/Invoice&gt;</span></div>
    </div>
  </div>
</div>

<div class="tab-content" id="tab-qr">
  <div class="card">
    <div class="card-header"><h2>QR Code</h2></div>
    <div class="card-body qr-container">
      <div class="qr-code">
        <div class="qr-pattern" id="qrPattern"></div>
      </div>
      <p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:16px">Scan to verify invoice authenticity</p>
      <div style="text-align:left;max-width:500px;margin:0 auto">
        <div class="detail-label" style="margin-bottom:8px">TLV Data (Decoded)</div>
        <div class="json-viewer" style="font-size:.75rem">Tag 1 (Seller):      My Company LLC
Tag 2 (VAT):         399999999900003
Tag 3 (Timestamp):   2025-01-15T10:30:00Z
Tag 4 (Total):       287.50
Tag 5 (Tax):         37.50
Tag 6 (Hash):        NWZlY2ViNjZmZmM4...
Tag 7 (Signature):   MEUCIQC+8hR0mMn...
Tag 8 (Public Key):  BF3VmAsMbvhJuKF...
Tag 9 (Cert Sig):    MEQCIGGz7xzHaE...</div>
      </div>
    </div>
  </div>
</div>

<div class="tab-content" id="tab-apilog">
  <div class="card">
    <div class="card-header"><h2>API Request / Response</h2></div>
    <div class="card-body">
      <div class="detail-grid" style="margin-bottom:16px">
        <div class="detail-field"><div class="detail-label">Endpoint</div><div class="detail-value" style="font-family:'JetBrains Mono',monospace;font-size:.8rem">POST /invoices/reporting/single</div></div>
        <div class="detail-field"><div class="detail-label">HTTP Status</div><div class="detail-value"><span class="badge badge-green">200 OK</span></div></div>
        <div class="detail-field"><div class="detail-label">Duration</div><div class="detail-value">245ms</div></div>
        <div class="detail-field"><div class="detail-label">Retries</div><div class="detail-value">0</div></div>
      </div>
      <div class="detail-label" style="margin-bottom:8px">Request Headers</div>
      <div class="json-viewer" style="margin-bottom:16px;max-height:150px">{
  <span class="json-key">"Accept-Version"</span>: <span class="json-string">"V2"</span>,
  <span class="json-key">"Accept"</span>: <span class="json-string">"application/json"</span>,
  <span class="json-key">"Accept-Language"</span>: <span class="json-string">"en"</span>,
  <span class="json-key">"Authorization"</span>: <span class="json-string">"Basic ••••••••••••••"</span>
}</div>
      <div class="detail-label" style="margin-bottom:8px">Request Body</div>
      <div class="json-viewer" style="margin-bottom:16px;max-height:150px">{
  <span class="json-key">"invoiceHash"</span>: <span class="json-string">"NWZlY2ViNjZmZmM4NmYz..."</span>,
  <span class="json-key">"uuid"</span>: <span class="json-string">"a1b2c3d4-e5f6-4789-abcd-ef0123456789"</span>,
  <span class="json-key">"invoice"</span>: <span class="json-string">"PD94bWwgdmVyc2lvbj0i..."</span>
}</div>
      <div class="detail-label" style="margin-bottom:8px">Response Body</div>
      <div class="json-viewer" style="max-height:200px">{
  <span class="json-key">"reportingStatus"</span>: <span class="json-string">"REPORTED"</span>,
  <span class="json-key">"validationResults"</span>: {
    <span class="json-key">"status"</span>: <span class="json-string">"PASS"</span>,
    <span class="json-key">"infoMessages"</span>: [...],
    <span class="json-key">"warningMessages"</span>: [],
    <span class="json-key">"errorMessages"</span>: []
  }
}</div>
    </div>
  </div>
</div>

<div class="tab-content" id="tab-chainpos">
  <div class="card">
    <div class="card-header"><h2>Chain Position</h2></div>
    <div class="card-body">
      <div class="alert alert-green" style="margin-bottom:16px">
        <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
        Chain integrity verified &mdash; this invoice is at position ICV 23
      </div>
      <div class="detail-grid">
        <div class="detail-field"><div class="detail-label">ICV (Counter)</div><div class="detail-value">23</div></div>
        <div class="detail-field"><div class="detail-label">Previous Invoice</div><div class="detail-value">INV-022 (ICV 22)</div></div>
        <div class="detail-field"><div class="detail-label">PIH (Previous Hash)</div><div class="detail-value" style="font-family:'JetBrains Mono',monospace;font-size:.78rem;word-break:break-all">f8a3b2c1d4e5f6a7b8c9d0e1f2a3b4c5...</div></div>
        <div class="detail-field"><div class="detail-label">This Invoice Hash</div><div class="detail-value" style="font-family:'JetBrains Mono',monospace;font-size:.78rem;word-break:break-all">NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZj...</div></div>
      </div>
    </div>
  </div>
</div>
`;

// ═══════════════════════════
//  API LOGS PAGE
// ═══════════════════════════
pages['api-logs'] = () => `
<div class="filters-bar">
  <div class="search-input">
    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
    <input type="text" placeholder="Search API logs...">
  </div>
  <select class="filter-select">
    <option>All Endpoints</option>
    <option>/compliance</option>
    <option>/compliance/invoices</option>
    <option>/invoices/clearance/single</option>
    <option>/invoices/reporting/single</option>
    <option>/production/csids</option>
  </select>
  <select class="filter-select">
    <option>All Statuses</option>
    <option>Success</option>
    <option>Error</option>
    <option>Warning</option>
    <option>Timeout</option>
  </select>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead><tr><th></th><th>Status</th><th>Endpoint</th><th>HTTP</th><th>Invoice</th><th>Duration</th><th>Time</th></tr></thead>
      <tbody>
        <tr class="expand-row" onclick="toggleExpand(this)">
          <td><span class="expand-toggle">&#9654;</span></td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>Success</span></td>
          <td style="font-family:'JetBrains Mono',monospace;font-size:.78rem">/invoices/reporting/single</td>
          <td><span class="badge badge-green">200</span></td>
          <td>INV-023</td>
          <td>245ms</td>
          <td style="color:var(--text-muted)">2 min ago</td>
        </tr>
        <tr><td colspan="7"><div class="expand-detail">
          <div class="detail-grid">
            <div><div class="detail-label">Request Body</div><div class="json-viewer" style="max-height:120px;font-size:.72rem">{ "invoiceHash": "NWZlY2Vi...", "uuid": "a1b2c3d4...", "invoice": "PD94bWw..." }</div></div>
            <div><div class="detail-label">Response Body</div><div class="json-viewer" style="max-height:120px;font-size:.72rem">{ "reportingStatus": "REPORTED", "validationResults": { "status": "PASS" } }</div></div>
          </div>
        </div></td></tr>

        <tr class="expand-row" onclick="toggleExpand(this)">
          <td><span class="expand-toggle">&#9654;</span></td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>Success</span></td>
          <td style="font-family:'JetBrains Mono',monospace;font-size:.78rem">/invoices/clearance/single</td>
          <td><span class="badge badge-green">200</span></td>
          <td>INV-022</td>
          <td>1.2s</td>
          <td style="color:var(--text-muted)">15 min ago</td>
        </tr>
        <tr><td colspan="7"><div class="expand-detail">
          <div class="detail-grid">
            <div><div class="detail-label">Request Body</div><div class="json-viewer" style="max-height:120px;font-size:.72rem">{ "invoiceHash": "f8a3b2c1...", "uuid": "b2c3d4e5...", "invoice": "PD94bWw..." }</div></div>
            <div><div class="detail-label">Response Body</div><div class="json-viewer" style="max-height:120px;font-size:.72rem">{ "clearanceStatus": "CLEARED", "clearedInvoice": "PD94bWw...", "validationResults": { "status": "PASS" } }</div></div>
          </div>
        </div></td></tr>

        <tr class="expand-row" onclick="toggleExpand(this)">
          <td><span class="expand-toggle">&#9654;</span></td>
          <td><span class="badge badge-red"><span class="badge-dot red"></span>Error</span></td>
          <td style="font-family:'JetBrains Mono',monospace;font-size:.78rem">/invoices/reporting/single</td>
          <td><span class="badge badge-red">400</span></td>
          <td>INV-021</td>
          <td>890ms</td>
          <td style="color:var(--text-muted)">1 hour ago</td>
        </tr>
        <tr><td colspan="7"><div class="expand-detail">
          <div class="detail-grid">
            <div><div class="detail-label">Request Body</div><div class="json-viewer" style="max-height:120px;font-size:.72rem">{ "invoiceHash": "91b2a3c4...", "uuid": "c3d4e5f6...", "invoice": "PD94bWw..." }</div></div>
            <div><div class="detail-label">Response Body</div><div class="json-viewer" style="max-height:120px;font-size:.72rem">{ "validationResults": { "status": "ERROR", "errorMessages": [{ "code": "BR-KSA-31", "message": "VAT registration number does not match" }] } }</div></div>
          </div>
        </div></td></tr>

        <tr class="expand-row" onclick="toggleExpand(this)">
          <td><span class="expand-toggle">&#9654;</span></td>
          <td><span class="badge badge-yellow"><span class="badge-dot yellow"></span>Warning</span></td>
          <td style="font-family:'JetBrains Mono',monospace;font-size:.78rem">/compliance/invoices</td>
          <td><span class="badge badge-green">202</span></td>
          <td>CN-003</td>
          <td>3.1s</td>
          <td style="color:var(--text-muted)">2 hours ago</td>
        </tr>
        <tr><td colspan="7"><div class="expand-detail">
          <div class="detail-grid">
            <div><div class="detail-label">Request Body</div><div class="json-viewer" style="max-height:120px;font-size:.72rem">{ "invoiceHash": "7c4ed5f6...", "uuid": "d4e5f6a7...", "invoice": "PD94bWw..." }</div></div>
            <div><div class="detail-label">Response Body</div><div class="json-viewer" style="max-height:120px;font-size:.72rem">{ "validationResults": { "status": "WARNING", "warningMessages": [{ "code": "BT-31", "message": "Buyer VAT optional for simplified" }] } }</div></div>
          </div>
        </div></td></tr>
      </tbody>
    </table>
  </div>
</div>
`;

// ═══════════════════════════
//  CERTIFICATES PAGE
// ═══════════════════════════
pages['certificates'] = () => `
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <h2>POS-01 — Production Certificate</h2>
    <span class="badge badge-green"><span class="badge-dot green"></span>Active</span>
  </div>
  <div class="card-body">
    <div class="detail-grid" style="margin-bottom:16px">
      <div class="detail-field"><div class="detail-label">Serial Number</div><div class="detail-value" style="font-family:'JetBrains Mono',monospace;font-size:.8rem">1-MySolution|2-Model1|3-SN001</div></div>
      <div class="detail-field"><div class="detail-label">Issuer</div><div class="detail-value">ZATCA CA</div></div>
      <div class="detail-field"><div class="detail-label">Valid From</div><div class="detail-value">Jan 1, 2025</div></div>
      <div class="detail-field"><div class="detail-label">Valid Until</div><div class="detail-value">Jan 1, 2026 <span style="color:var(--green);font-size:.78rem">(89 days remaining)</span></div></div>
      <div class="detail-field"><div class="detail-label">Environment</div><div class="detail-value">Production</div></div>
      <div class="detail-field"><div class="detail-label">Organization</div><div class="detail-value">My Company LLC</div></div>
    </div>
    <div class="cert-progress"><div class="cert-progress-fill" style="width:75%;background:var(--green)"></div></div>
    <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-muted);margin-bottom:20px">
      <span>Issued: Jan 1, 2025</span>
      <span>Today</span>
      <span>Expires: Jan 1, 2026</span>
    </div>

    <div class="detail-label" style="margin-bottom:12px">Certificate Lifecycle</div>
    <div class="timeline">
      <div class="timeline-item">
        <div class="timeline-dot green"></div>
        <div class="timeline-date">Jan 1, 2025 — 09:15 AM</div>
        <div class="timeline-title">Production Certificate Issued</div>
        <div class="timeline-desc">Certificate activated for production invoicing</div>
      </div>
      <div class="timeline-item">
        <div class="timeline-dot green"></div>
        <div class="timeline-date">Jan 1, 2025 — 09:10 AM</div>
        <div class="timeline-title">Compliance Tests Passed</div>
        <div class="timeline-desc">All 6 compliance invoices submitted successfully</div>
      </div>
      <div class="timeline-item">
        <div class="timeline-dot blue"></div>
        <div class="timeline-date">Jan 1, 2025 — 09:00 AM</div>
        <div class="timeline-title">Compliance Certificate Received</div>
        <div class="timeline-desc">CSR submitted to ZATCA with OTP</div>
      </div>
      <div class="timeline-item">
        <div class="timeline-dot gray"></div>
        <div class="timeline-date">Jan 1, 2025 — 08:45 AM</div>
        <div class="timeline-title">CSR Generated</div>
        <div class="timeline-desc">Private key and CSR created via zatca:generate-csr</div>
      </div>
    </div>
    <div style="margin-top:20px;display:flex;gap:8px">
      <button class="btn btn-primary">Renew Certificate</button>
      <button class="btn btn-outline">Download CSR</button>
      <button class="btn btn-outline">View PEM</button>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>POS-02 — Compliance Certificate</h2>
    <span class="badge badge-yellow"><span class="badge-dot yellow"></span>Pending Production</span>
  </div>
  <div class="card-body">
    <div class="detail-grid" style="margin-bottom:16px">
      <div class="detail-field"><div class="detail-label">Serial Number</div><div class="detail-value" style="font-family:'JetBrains Mono',monospace;font-size:.8rem">1-MySolution|2-Model1|3-SN002</div></div>
      <div class="detail-field"><div class="detail-label">Status</div><div class="detail-value" style="color:var(--yellow)">Awaiting compliance tests</div></div>
    </div>
    <div class="cert-progress"><div class="cert-progress-fill" style="width:33%;background:var(--yellow)"></div></div>
    <div style="display:flex;gap:8px;margin-top:16px">
      <button class="btn btn-primary">Run Compliance Tests</button>
      <button class="btn btn-outline">View Details</button>
    </div>
  </div>
</div>
`;

// ═══════════════════════════
//  DEVICES PAGE
// ═══════════════════════════
pages['devices'] = () => `
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <p style="color:var(--text-secondary);font-size:.85rem">Manage your EGS (E-invoice Generation Solutions) devices</p>
  <button class="btn btn-primary">+ Add Device</button>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Name</th><th>Serial Number</th><th>Environment</th><th>Certificate</th><th>Last ICV</th><th>Invoices</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <tr>
          <td><strong>POS-01</strong><br><span style="font-size:.72rem;color:var(--text-muted)">Main Point of Sale</span></td>
          <td style="font-family:'JetBrains Mono',monospace;font-size:.78rem">SN001</td>
          <td><span class="badge badge-green">production</span></td>
          <td><span style="color:var(--green)">Active</span><br><span style="font-size:.72rem;color:var(--text-muted)">89 days left</span></td>
          <td><strong>42</strong></td>
          <td>1,205</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>Active</span></td>
          <td><button class="btn btn-outline" style="padding:4px 10px;font-size:.75rem">Manage</button></td>
        </tr>
        <tr>
          <td><strong>POS-02</strong><br><span style="font-size:.72rem;color:var(--text-muted)">Branch POS</span></td>
          <td style="font-family:'JetBrains Mono',monospace;font-size:.78rem">SN002</td>
          <td><span class="badge badge-yellow">simulation</span></td>
          <td><span style="color:var(--yellow)">Compliance</span><br><span style="font-size:.72rem;color:var(--text-muted)">Pending production</span></td>
          <td><strong>0</strong></td>
          <td>0</td>
          <td><span class="badge badge-yellow"><span class="badge-dot yellow"></span>Setup</span></td>
          <td><button class="btn btn-outline" style="padding:4px 10px;font-size:.75rem">Manage</button></td>
        </tr>
        <tr>
          <td><strong>ERP-Main</strong><br><span style="font-size:.72rem;color:var(--text-muted)">ERP System Integration</span></td>
          <td style="font-family:'JetBrains Mono',monospace;font-size:.78rem">SN003</td>
          <td><span class="badge badge-green">production</span></td>
          <td><span style="color:var(--green)">Active</span><br><span style="font-size:.72rem;color:var(--text-muted)">180 days left</span></td>
          <td><strong>1,205</strong></td>
          <td>15,420</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>Active</span></td>
          <td><button class="btn btn-outline" style="padding:4px 10px;font-size:.75rem">Manage</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
`;

// ═══════════════════════════
//  CHAIN PAGE
// ═══════════════════════════
pages['chain'] = () => `
<div class="filters-bar">
  <select class="filter-select">
    <option>Device: POS-01</option>
    <option>Device: POS-02</option>
    <option>Device: ERP-Main</option>
  </select>
  <select class="filter-select">
    <option>Type: All</option>
    <option>Standard (0100000)</option>
    <option>Simplified (0200000)</option>
  </select>
</div>

<div class="alert alert-green" style="margin-bottom:16px">
  <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
  Chain Integrity: Verified &mdash; 42 invoices, no gaps, no hash mismatches
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>ICV</th><th>Invoice</th><th>PIH (from prev)</th><th></th><th>This Hash</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <tr>
          <td><strong>42</strong></td>
          <td><strong>INV-042</strong></td>
          <td class="chain-hash">f8a3b2c1...</td>
          <td class="chain-arrow">&rarr;</td>
          <td class="chain-hash">NWZlY2Vi...</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>Reported</span></td>
          <td style="color:var(--text-muted)">Jan 15</td>
        </tr>
        <tr>
          <td><strong>41</strong></td>
          <td><strong>INV-041</strong></td>
          <td class="chain-hash">91b2a3c4...</td>
          <td class="chain-arrow">&rarr;</td>
          <td class="chain-hash">f8a3b2c1...</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>Reported</span></td>
          <td style="color:var(--text-muted)">Jan 15</td>
        </tr>
        <tr>
          <td><strong>40</strong></td>
          <td><strong>CN-003</strong></td>
          <td class="chain-hash">7c4ed5f6...</td>
          <td class="chain-arrow">&rarr;</td>
          <td class="chain-hash">91b2a3c4...</td>
          <td><span class="badge badge-yellow"><span class="badge-dot yellow"></span>Warning</span></td>
          <td style="color:var(--text-muted)">Jan 14</td>
        </tr>
        <tr>
          <td><strong>39</strong></td>
          <td><strong>INV-039</strong></td>
          <td class="chain-hash">2d5fa1b3...</td>
          <td class="chain-arrow">&rarr;</td>
          <td class="chain-hash">7c4ed5f6...</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>Cleared</span></td>
          <td style="color:var(--text-muted)">Jan 14</td>
        </tr>
        <tr>
          <td><strong>38</strong></td>
          <td><strong>INV-038</strong></td>
          <td class="chain-hash">e8f9a0b1...</td>
          <td class="chain-arrow">&rarr;</td>
          <td class="chain-hash">2d5fa1b3...</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>Reported</span></td>
          <td style="color:var(--text-muted)">Jan 13</td>
        </tr>
        <tr>
          <td><strong>37</strong></td>
          <td><strong>INV-037</strong></td>
          <td class="chain-hash">c6d7e8f9...</td>
          <td class="chain-arrow">&rarr;</td>
          <td class="chain-hash">e8f9a0b1...</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>Cleared</span></td>
          <td style="color:var(--text-muted)">Jan 13</td>
        </tr>
        <tr style="color:var(--text-muted);font-style:italic">
          <td colspan="7" style="text-align:center;padding:16px">... 35 more invoices ...</td>
        </tr>
        <tr>
          <td><strong>1</strong></td>
          <td><strong>INV-001</strong></td>
          <td class="chain-hash" style="color:var(--accent)">MA== (genesis)</td>
          <td class="chain-arrow">&rarr;</td>
          <td class="chain-hash">a1b2c3d4...</td>
          <td><span class="badge badge-green"><span class="badge-dot green"></span>Reported</span></td>
          <td style="color:var(--text-muted)">Jan 1</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
`;

// ═══════════════════════════
//  SETTINGS PAGE
// ═══════════════════════════
pages['settings'] = () => `
<div class="grid-2">
  <div class="card">
    <div class="card-header"><h2>General Configuration</h2></div>
    <div class="card-body">
      <div class="settings-list">
        <div class="settings-item"><span class="settings-key">Environment</span><span class="settings-val"><span class="badge badge-green">production</span></span></div>
        <div class="settings-item"><span class="settings-key">Dashboard Path</span><span class="settings-val">/zatca</span></div>
        <div class="settings-item"><span class="settings-key">Auto Chain</span><span class="settings-val"><span class="check-icon">Enabled</span></span></div>
        <div class="settings-item"><span class="settings-key">Store XML</span><span class="settings-val"><span class="check-icon">Enabled</span></span></div>
        <div class="settings-item"><span class="settings-key">API Timeout</span><span class="settings-val">30s</span></div>
        <div class="settings-item"><span class="settings-key">Retry Attempts</span><span class="settings-val">3</span></div>
        <div class="settings-item"><span class="settings-key">Retry Delay</span><span class="settings-val">1000ms</span></div>
        <div class="settings-item"><span class="settings-key">SSL Verification</span><span class="settings-val"><span class="check-icon">Enabled</span></span></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Seller Information</h2></div>
    <div class="card-body">
      <div class="settings-list">
        <div class="settings-item"><span class="settings-key">Company Name</span><span class="settings-val">My Company LLC</span></div>
        <div class="settings-item"><span class="settings-key">Arabic Name</span><span class="settings-val" style="font-family:inherit" dir="rtl">شركتي ذ.م.م</span></div>
        <div class="settings-item"><span class="settings-key">VAT Number</span><span class="settings-val">399999999900003</span></div>
        <div class="settings-item"><span class="settings-key">Party ID (CRN)</span><span class="settings-val">1010203020</span></div>
        <div class="settings-item"><span class="settings-key">Street</span><span class="settings-val">Main Street</span></div>
        <div class="settings-item"><span class="settings-key">Building</span><span class="settings-val">1234</span></div>
        <div class="settings-item"><span class="settings-key">City</span><span class="settings-val">Riyadh</span></div>
        <div class="settings-item"><span class="settings-key">Postal Code</span><span class="settings-val">12345</span></div>
        <div class="settings-item"><span class="settings-key">Country</span><span class="settings-val">SA</span></div>
      </div>
    </div>
  </div>
</div>

<div class="grid-2" style="margin-top:0">
  <div class="card">
    <div class="card-header"><h2>Pruning</h2></div>
    <div class="card-body">
      <div class="settings-list">
        <div class="settings-item"><span class="settings-key">Invoices</span><span class="settings-val">Keep 365 days</span></div>
        <div class="settings-item"><span class="settings-key">API Logs</span><span class="settings-val">Keep 90 days</span></div>
        <div class="settings-item"><span class="settings-key">Last Pruned</span><span class="settings-val" style="color:var(--text-muted)">2 days ago</span></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Health Check</h2></div>
    <div class="card-body">
      <div class="settings-list">
        <div class="settings-item"><span class="settings-key">ZATCA API</span><span class="settings-val"><span class="badge badge-green">Reachable (198ms)</span></span></div>
        <div class="settings-item"><span class="settings-key">Certificate</span><span class="settings-val"><span class="badge badge-green">Valid (89 days)</span></span></div>
        <div class="settings-item"><span class="settings-key">Database</span><span class="settings-val"><span class="badge badge-green">Connected</span></span></div>
        <div class="settings-item"><span class="settings-key">Storage</span><span class="settings-val"><span class="badge badge-green">Writable</span></span></div>
      </div>
      <div style="margin-top:16px;display:flex;gap:8px">
        <button class="btn btn-primary">Run Health Check</button>
        <button class="btn btn-outline">Test API Connection</button>
      </div>
    </div>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-header"><h2>Notifications</h2></div>
  <div class="card-body">
    <div class="settings-list">
      <div class="settings-item"><span class="settings-key">Certificate Expiry Alert</span><span class="settings-val">30 days before</span></div>
      <div class="settings-item"><span class="settings-key">Channels</span><span class="settings-val">mail</span></div>
      <div class="settings-item"><span class="settings-key">Recipients</span><span class="settings-val">admin@example.com</span></div>
      <div class="settings-item"><span class="settings-key">Invoice Rejection Alerts</span><span class="settings-val"><span class="check-icon">Enabled</span></span></div>
    </div>
  </div>
</div>
`;

// ── Tab Switching ──
function initTabs() {
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
  });
  // Generate QR pattern
  const qr = document.getElementById('qrPattern');
  if (qr) {
    const pattern = [1,1,1,1,1,1,1,0,1,0,1, 1,0,0,0,0,0,1,0,0,1,0, 1,0,1,1,1,0,1,0,1,0,1, 1,0,1,1,1,0,1,0,0,1,1, 1,0,1,1,1,0,1,0,1,1,0, 1,0,0,0,0,0,1,0,0,0,1, 1,1,1,1,1,1,1,0,1,0,1, 0,0,0,0,0,0,0,0,1,1,0, 1,0,1,0,1,1,1,1,0,0,1, 0,1,0,1,0,0,0,1,1,0,1, 1,1,0,1,1,0,1,0,1,1,1];
    pattern.forEach(v => {
      const cell = document.createElement('div');
      cell.className = 'qr-cell ' + (v ? 'dark' : 'light');
      qr.appendChild(cell);
    });
  }
}

// ── Expand/Collapse for API Logs ──
function toggleExpand(row) {
  const detail = row.nextElementSibling.querySelector('.expand-detail');
  const toggle = row.querySelector('.expand-toggle');
  detail.classList.toggle('open');
  toggle.classList.toggle('open');
}
function initExpand() {
  // already handled by onclick
}

// ── Chart Animations (simple) ──
function initCharts() {
  // Animate bars on load
  document.querySelectorAll('.chart-bar').forEach(bar => {
    const h = bar.style.height;
    bar.style.height = '0px';
    requestAnimationFrame(() => {
      requestAnimationFrame(() => { bar.style.height = h; });
    });
  });
}

// ── Init ──
navigate('dashboard');
