<?php
require __DIR__ . '/bootstrap.php';
require_role(['owner', 'admin', 'accountant']);
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$id = (int)($_GET['id'] ?? 0);
$expense = null;

if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND tenant_id = ?");
    $st->execute([$id, $tid]);
    $expense = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $clientId     = (int)($_POST['client_id'] ?? 0) ?: null;
    $invoiceId    = (int)($_POST['invoice_id'] ?? 0) ?: null;
    $vendorName   = trim($_POST['vendor_name'] ?? '');
    $categoryId   = (int)($_POST['category_id'] ?? 0) ?: null;
    $expenseDate  = $_POST['expense_date'] ?? date('Y-m-d');
    $currency     = $_POST['currency'] ?? 'AED';
    $paymentMethod = $_POST['payment_method'] ?? 'Bank Transfer';
    $subtotal     = (float)($_POST['subtotal'] ?? 0);
    $taxAmount    = (float)($_POST['tax_amount'] ?? 0);
    $total        = $subtotal + $taxAmount;
    $notes        = trim($_POST['notes'] ?? '');

    // Auto-set vendor name from client if left blank
    if (!$vendorName && $clientId) {
        $stCl = $pdo->prepare("SELECT company_name FROM clients WHERE id = ? AND tenant_id = ?");
        $stCl->execute([$clientId, $tid]);
        $vendorName = $stCl->fetchColumn() ?: '';
    }

    if (!$vendorName || $total <= 0) {
        flash('error', 'Vendor name and positive amount are required.');
        redirect('expense_form.php' . ($id ? '?id='.$id : ''));
    }

    // Verify invoiceId belongs to this tenant (if provided)
    if ($invoiceId) {
        $stInvChk = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE id = ? AND tenant_id = ?");
        $stInvChk->execute([$invoiceId, $tid]);
        if ((int)$stInvChk->fetchColumn() === 0) $invoiceId = null;
    }

    if ($id > 0 && $expense) {
        $st = $pdo->prepare("UPDATE expenses SET client_id=?, invoice_id=?, category_id=?, vendor_name=?, expense_date=?, subtotal=?, tax_amount=?, total=?, currency=?, payment_method=?, notes=? WHERE id=? AND tenant_id=?");
        $st->execute([$clientId, $invoiceId, $categoryId, $vendorName, $expenseDate, $subtotal, $taxAmount, $total, $currency, $paymentMethod, $notes, $id, $tid]);
        log_audit($pdo, 'update_expense', 'expenses', $id, "Updated expense for $vendorName");
        flash('success', 'Expense record updated.');
    } else {
        $st = $pdo->prepare("INSERT INTO expenses (tenant_id, client_id, invoice_id, category_id, vendor_name, expense_date, subtotal, tax_amount, total, currency, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$tid, $clientId, $invoiceId, $categoryId, $vendorName, $expenseDate, $subtotal, $taxAmount, $total, $currency, $paymentMethod, $notes]);
        $newId = (int)$pdo->lastInsertId();
        log_audit($pdo, 'create_expense', 'expenses', $newId, "Recorded expense for $vendorName ($total $currency)");
        flash('success', 'New business expense recorded successfully.');
    }
    redirect('expenses.php');
}

$stCats = $pdo->prepare("SELECT * FROM expense_categories WHERE tenant_id = ? ORDER BY name ASC");
$stCats->execute([$tid]);
$categories = $stCats->fetchAll();

// Fetch clients for dropdown
$stClients = $pdo->prepare("SELECT id, company_name FROM clients WHERE tenant_id = ? ORDER BY company_name ASC");
$stClients->execute([$tid]);
$clients = $stClients->fetchAll();

// Fetch invoices for searchable dropdown (non-cancelled)
$stInvoices = $pdo->prepare("
    SELECT 
        i.id, 
        i.invoice_number, 
        i.invoice_date,
        i.total, 
        i.currency, 
        i.status,
        i.client_id, 
        c.company_name 
    FROM invoices i 
    LEFT JOIN clients c ON c.id = i.client_id 
    WHERE i.tenant_id = ? AND i.status != 'cancelled' 
    ORDER BY i.invoice_date DESC, i.id DESC 
    LIMIT 300
");
$stInvoices->execute([$tid]);
$invoices = $stInvoices->fetchAll();

// Build client map for JS auto-fill
$clientCompanyMap = [];
foreach ($clients as $cl) {
    $clientCompanyMap[$cl['id']] = $cl['company_name'];
}

$linkedInvoiceLabel = '';
if ($expense && !empty($expense['invoice_id'])) {
    foreach ($invoices as $inv) {
        if ($inv['id'] == $expense['invoice_id']) {
            $linkedInvoiceLabel = $inv['invoice_number'] . ($inv['company_name'] ? ' — ' . $inv['company_name'] : '');
            break;
        }
    }
}

page_start($expense ? 'Edit Expense Record' : 'Record New Expense');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight"><?=$expense ? 'Edit Expense Record' : 'Record New Expense'?></h1>
        <p class="mt-1 text-sm text-slate-500">Record corporate expenses, vendor receipts, and tax credits for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <a href="expenses.php" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
        ← Back to Expenses
    </a>
</div>

<form method="post" class="space-y-8 max-w-4xl mx-auto pb-28 lg:pb-8">
    <?=csrf_field()?>

    <!-- Section 1: Client & Invoice Linkage -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center">
            <i class="fa-solid fa-link text-amber-500 mr-2.5"></i> Client & Invoice Linkage
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Client Dropdown with live search -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Linked Client Account</label>
                    <button type="button" id="clear_client_btn" onclick="clearSelectedClient()" 
                        class="text-2xs font-extrabold text-slate-400 hover:text-rose-600 transition-colors <?=empty($expense['client_id']) ? 'hidden' : ''?>">
                        <i class="fa-solid fa-xmark mr-0.5"></i> Clear client
                    </button>
                </div>
                <div class="relative">
                    <div class="relative">
                        <input type="text" id="client_search_input" placeholder="Search client by name..." autocomplete="off"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 pl-9 pr-8 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"
                            value="<?php
                                if ($expense && $expense['client_id'] ?? null) {
                                    foreach ($clients as $cl) {
                                        if ($cl['id'] == $expense['client_id']) { echo e($cl['company_name']); break; }
                                    }
                                }
                            ?>">
                        <i class="fa-solid fa-user-tie absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <button type="button" onclick="toggleClientDropdown()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs p-1">
                            <i class="fa-solid fa-chevron-down text-3xs"></i>
                        </button>
                    </div>
                    <input type="hidden" name="client_id" id="client_id_hidden" value="<?=e((string)($expense['client_id'] ?? ''))?>">
                    <div id="client_search_dropdown" class="absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-52 overflow-y-auto hidden">
                        <div id="client_search_results"></div>
                    </div>
                </div>
                <p class="text-2xs text-slate-400 mt-1.5">Selecting a client will auto-fill the vendor name and interlink this expense with the client statement.</p>
            </div>

            <!-- Link to Invoice with Live Search -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">
                        Link to Invoice <span class="font-normal text-slate-400">(optional)</span>
                    </label>
                    <button type="button" id="clear_invoice_btn" onclick="clearSelectedInvoice()" 
                        class="text-2xs font-extrabold text-slate-400 hover:text-rose-600 transition-colors <?=empty($expense['invoice_id']) ? 'hidden' : ''?>">
                        <i class="fa-solid fa-xmark mr-0.5"></i> Clear link
                    </button>
                </div>
                <div class="relative">
                    <div class="relative">
                        <input type="text" id="invoice_search_input" placeholder="Search invoice #, client, amount..." autocomplete="off"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 pl-9 pr-8 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"
                            value="<?=e($linkedInvoiceLabel)?>">
                        <i class="fa-solid fa-file-invoice absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <button type="button" onclick="toggleInvoiceDropdown()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs p-1">
                            <i class="fa-solid fa-chevron-down text-3xs"></i>
                        </button>
                    </div>
                    <input type="hidden" name="invoice_id" id="invoice_id_hidden" value="<?=e((string)($expense['invoice_id'] ?? ''))?>">
                    <div id="invoice_search_dropdown" class="absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-60 overflow-y-auto hidden">
                        <div id="invoice_search_results"></div>
                    </div>
                </div>
                <p class="text-2xs text-slate-400 mt-1.5">Search by invoice number or client name. This expense will link directly to that invoice.</p>
            </div>
        </div>
    </div>

    <!-- Section 2: Vendor & Expense Details -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center">
            <i class="fa-solid fa-receipt text-rose-500 mr-2.5"></i> Vendor &amp; Expense Details
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Vendor / Supplier Name *</label>
                <input type="text" name="vendor_name" id="vendor_name_input" value="<?=e($expense['vendor_name'] ?? '')?>" placeholder="e.g. AWS Amazon, Dewa, Du Telecom" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                <p class="text-2xs text-slate-400 mt-1.5">Auto-filled when you select a client above, or enter manually.</p>
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Expense Category</label>
                    <button type="button" onclick="openQuickCategoryModal()" class="text-xs font-extrabold text-amber-600 hover:text-amber-700 hover:underline flex items-center cursor-pointer">
                        <i class="fa-solid fa-plus-circle mr-1 text-2xs"></i>+ New Category
                    </button>
                </div>
                <select id="expense_category_select" name="category_id" onchange="if(this.value==='__add_new_cat__'){openQuickCategoryModal(); this.value='';}" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    <option value="">-- General Expense --</option>
                    <option value="__add_new_cat__" class="font-extrabold text-amber-600 bg-amber-50">+ Add New Category...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?=$cat['id']?>" <?=($expense && $expense['category_id'] == $cat['id']) ? 'selected' : ''?>><?=e($cat['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Expense Date *</label>
                <input type="date" name="expense_date" value="<?=e($expense['expense_date'] ?? date('Y-m-d'))?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Currency &amp; Payment Method</label>
                <div class="grid grid-cols-2 gap-3">
                    <select name="currency" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-base sm:text-sm font-bold text-slate-900">
                        <?php foreach (['AED', 'USD', 'EUR', 'GBP', 'SAR', 'INR', 'CAD', 'AUD'] as $curr): ?>
                            <option value="<?=$curr?>" <?=($expense['currency'] ?? tenant()['currency'])===$curr?'selected':''?>><?=$curr?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="payment_method" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-base sm:text-sm font-bold text-slate-900">
                        <option value="Bank Transfer" <?=($expense['payment_method'] ?? '')==='Bank Transfer'?'selected':''?>>Bank Transfer</option>
                        <option value="Corporate Credit Card" <?=($expense['payment_method'] ?? '')==='Corporate Credit Card'?'selected':''?>>Credit Card</option>
                        <option value="Petty Cash" <?=($expense['payment_method'] ?? '')==='Petty Cash'?'selected':''?>>Petty Cash</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Subtotal Amount (Excl. Tax) *</label>
                <input type="number" step="0.01" name="subtotal" value="<?=e((string)(float)($expense['subtotal'] ?? 0))?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Recoverable Tax / VAT Amount</label>
                <input type="number" step="0.01" name="tax_amount" value="<?=e((string)(float)($expense['tax_amount'] ?? 0))?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Expense Notes / Memo</label>
                <textarea name="notes" rows="3" placeholder="Reference invoice or receipt description..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"><?=e($expense['notes'] ?? '')?></textarea>
            </div>
        </div>
    </div>

    <!-- Submit Action (Mobile Safe Layout) -->
    <div class="flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-4 gap-3">
        <a href="expenses.php" class="w-full sm:w-auto text-center px-6 py-3.5 rounded-xl text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-all">Cancel</a>
        <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-8 py-3.5 border border-transparent text-base font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-lg transition-all">
            <i class="fa-solid fa-floppy-disk mr-2"></i><?=$expense ? 'Update Expense Record' : 'Save Expense Record'?>
        </button>
    </div>
</form>

<!-- Quick Add Category Modal -->
<div id="quick-category-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-[200] hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md overflow-hidden transform transition-all">
        <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
            <h3 class="font-extrabold text-sm flex items-center"><i class="fa-solid fa-tags text-amber-400 mr-2"></i>Add New Expense Category</h3>
            <button type="button" onclick="closeQuickCategoryModal()" class="text-slate-400 hover:text-white font-bold text-lg">×</button>
        </div>
        <div class="p-6">
            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Category Name *</label>
            <input type="text" id="quick_cat_name_input" placeholder="e.g. Subscriptions & Software, Logistics" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            <p id="quick_cat_error" class="text-xs text-rose-600 font-bold mt-2 hidden"></p>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeQuickCategoryModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl">Cancel</button>
                <button type="button" id="save_quick_cat_btn" onclick="saveQuickCategory()" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs rounded-xl shadow-md flex items-center">
                    <i class="fa-solid fa-check mr-1.5"></i>Save Category
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ─── Client Map & Live Search ───────────────────────────────────────────────
const clientMap = <?=json_encode(array_column($clients, 'company_name', 'id'))?>;
const allClients = <?=json_encode(array_values(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['company_name']], $clients)))?>;

// ─── Invoice List & Live Search ────────────────────────────────────────────
const allInvoices = <?=json_encode(array_values(array_map(fn($i) => [
    'id'        => (int)$i['id'],
    'number'    => $i['invoice_number'],
    'company'   => $i['company_name'] ?: 'No Client Account',
    'client_id' => (int)($i['client_id'] ?? 0),
    'total'     => (float)$i['total'],
    'currency'  => $i['currency'] ?? 'AED',
    'status'    => $i['status'] ?? 'draft',
    'date'      => $i['invoice_date']
], $invoices)))?>;

const clientSearchInput  = document.getElementById('client_search_input');
const clientIdHidden     = document.getElementById('client_id_hidden');
const clientDropdown     = document.getElementById('client_search_dropdown');
const clientResults      = document.getElementById('client_search_results');
const clearClientBtn      = document.getElementById('clear_client_btn');

const invoiceSearchInput  = document.getElementById('invoice_search_input');
const invoiceIdHidden     = document.getElementById('invoice_id_hidden');
const invoiceDropdown     = document.getElementById('invoice_search_dropdown');
const invoiceResults      = document.getElementById('invoice_search_results');
const clearInvoiceBtn     = document.getElementById('clear_invoice_btn');

const vendorNameInput    = document.getElementById('vendor_name_input');

function toggleClientDropdown() {
    if (clientDropdown.classList.contains('hidden')) {
        renderClientResults(clientSearchInput.value);
    } else {
        clientDropdown.classList.add('hidden');
    }
}

function toggleInvoiceDropdown() {
    if (invoiceDropdown.classList.contains('hidden')) {
        renderInvoiceResults(invoiceSearchInput.value);
    } else {
        invoiceDropdown.classList.add('hidden');
    }
}

function renderClientResults(query) {
    const q = (query || '').trim().toLowerCase();
    const matches = q
        ? allClients.filter(c => c.name.toLowerCase().includes(q))
        : allClients;

    if (matches.length === 0) {
        clientResults.innerHTML = '<div class="px-4 py-3 text-xs text-slate-400">No clients found.</div>';
    } else {
        clientResults.innerHTML = matches.slice(0, 15).map(c =>
            `<div class="client-option px-4 py-2.5 text-sm font-bold text-slate-800 hover:bg-amber-50 cursor-pointer border-b border-slate-100 last:border-0 transition-colors" data-id="${c.id}" data-name="${c.name}">
                <i class="fa-solid fa-building text-3xs text-slate-400 mr-2"></i>${c.name}
            </div>`
        ).join('');
        // Clear option
        clientResults.innerHTML += '<div class="client-option px-4 py-2 text-xs font-bold text-slate-400 hover:bg-rose-50 hover:text-rose-700 cursor-pointer border-t border-slate-100 transition-colors" data-id="" data-name="">✕ Clear client selection</div>';
    }
    clientDropdown.classList.remove('hidden');

    // Attach click listeners
    clientResults.querySelectorAll('.client-option').forEach(el => {
        el.addEventListener('click', () => {
            const cId   = el.dataset.id;
            const cName = el.dataset.name;
            clientIdHidden.value      = cId;
            clientSearchInput.value   = cName;
            clientDropdown.classList.add('hidden');
            if (clearClientBtn) {
                if (cId) clearClientBtn.classList.remove('hidden');
                else clearClientBtn.classList.add('hidden');
            }

            // Auto-fill vendor name from client company name
            if (cName && (!vendorNameInput.value.trim() || vendorNameInput.value === '')) {
                vendorNameInput.value = cName;
            } else if (cName) {
                vendorNameInput.value = cName;
            }

            // If an invoice is currently linked from another client, clear it
            if (cId && invoiceIdHidden.value) {
                const curInv = allInvoices.find(i => i.id == invoiceIdHidden.value);
                if (curInv && curInv.client_id && curInv.client_id != cId) {
                    clearSelectedInvoice();
                }
            }
        });
    });
}

function clearSelectedClient() {
    clientIdHidden.value = '';
    clientSearchInput.value = '';
    if (clearClientBtn) clearClientBtn.classList.add('hidden');
}

function renderInvoiceResults(query = '') {
    const q = (query || '').trim().toLowerCase();
    const activeClientId = clientIdHidden.value ? parseInt(clientIdHidden.value) : null;

    let matches = allInvoices;
    if (q) {
        matches = allInvoices.filter(inv => 
            inv.number.toLowerCase().includes(q) ||
            inv.company.toLowerCase().includes(q) ||
            inv.total.toString().includes(q)
        );
    } else if (activeClientId) {
        // If no query typed yet and client selected, show this client's invoices first
        const clientInvoices = allInvoices.filter(inv => inv.client_id === activeClientId);
        if (clientInvoices.length > 0) {
            matches = clientInvoices;
        }
    }

    if (matches.length === 0) {
        invoiceResults.innerHTML = `
            <div class="px-4 py-3 text-xs text-slate-400 text-center">
                <i class="fa-solid fa-file-invoice text-slate-300 text-base mb-1 block"></i>
                No matching invoices found.
                ${activeClientId ? `<div class="mt-1"><button type="button" onclick="showAllInvoices(event)" class="text-amber-600 font-bold hover:underline">Show all invoices</button></div>` : ''}
            </div>
        `;
    } else {
        let headerHtml = '';
        if (!q && activeClientId && matches.length < allInvoices.length) {
            headerHtml = `
                <div class="px-3.5 py-1.5 bg-amber-50 text-amber-900 border-b border-amber-100 text-3xs font-extrabold flex items-center justify-between">
                    <span>Invoices for selected client (${matches.length})</span>
                    <button type="button" onclick="showAllInvoices(event)" class="text-amber-700 underline hover:text-amber-900">Show all (${allInvoices.length})</button>
                </div>
            `;
        }

        invoiceResults.innerHTML = headerHtml + matches.slice(0, 25).map(inv => {
            const isSelected = (invoiceIdHidden.value == inv.id);
            const statusColors = {
                paid: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                partially_paid: 'bg-blue-50 text-blue-700 border-blue-200',
                sent: 'bg-purple-50 text-purple-700 border-purple-200',
                draft: 'bg-slate-100 text-slate-600 border-slate-200',
                overdue: 'bg-rose-50 text-rose-700 border-rose-200'
            };
            const statusClass = statusColors[inv.status] || 'bg-slate-100 text-slate-600 border-slate-200';

            return `
                <div class="invoice-option px-4 py-2.5 hover:bg-amber-50/80 cursor-pointer border-b border-slate-100 last:border-0 transition-colors ${isSelected ? 'bg-amber-50/50' : ''}" 
                     data-id="${inv.id}" 
                     data-number="${inv.number}" 
                     data-company="${inv.company}" 
                     data-client-id="${inv.client_id}">
                    <div class="flex items-center justify-between">
                        <div class="font-extrabold text-xs text-slate-900 flex items-center gap-1.5">
                            <i class="fa-solid fa-file-invoice text-amber-500 text-xs"></i>
                            <span>${inv.number}</span>
                            <span class="px-1.5 py-0.2 rounded-full text-4xs font-bold border uppercase ${statusClass}">${inv.status.replace('_', ' ')}</span>
                        </div>
                        <span class="text-xs font-black text-slate-900 font-mono">${inv.total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} <span class="text-3xs text-slate-500 font-semibold">${inv.currency}</span></span>
                    </div>
                    <div class="text-3xs text-slate-500 flex items-center justify-between mt-1">
                        <span class="truncate max-w-[220px] font-semibold text-slate-600"><i class="fa-solid fa-building text-3xs text-slate-400 mr-1"></i>${inv.company}</span>
                        <span class="text-slate-400 font-medium"><i class="fa-regular fa-calendar text-3xs mr-1"></i>${inv.date}</span>
                    </div>
                </div>
            `;
        }).join('');

        // Clear / unlinked option
        invoiceResults.innerHTML += `
            <div class="invoice-option px-4 py-2 text-xs font-bold text-slate-400 hover:bg-rose-50 hover:text-rose-700 cursor-pointer border-t border-slate-100 transition-colors flex items-center gap-1.5" 
                 data-id="" data-number="" data-company="" data-client-id="">
                <i class="fa-solid fa-link-slash text-3xs"></i>
                <span>-- Not linked to any invoice --</span>
            </div>
        `;
    }

    invoiceDropdown.classList.remove('hidden');

    // Attach click listeners to invoice options
    invoiceResults.querySelectorAll('.invoice-option').forEach(el => {
        el.addEventListener('click', () => {
            const iId = el.dataset.id;
            const iNum = el.dataset.number;
            const iComp = el.dataset.company;
            const iClientId = el.dataset.clientId;

            selectInvoice(iId, iNum, iComp, iClientId);
        });
    });
}

function selectInvoice(id, number, company, clientId) {
    invoiceIdHidden.value = id || '';
    if (id) {
        invoiceSearchInput.value = `${number} — ${company}`;
        if (clearInvoiceBtn) clearInvoiceBtn.classList.remove('hidden');

        // Auto-link client if not selected yet
        if (clientId && clientId !== '0' && (!clientIdHidden.value || clientIdHidden.value == '0')) {
            const matchingClient = allClients.find(c => c.id == clientId);
            if (matchingClient) {
                clientIdHidden.value = matchingClient.id;
                clientSearchInput.value = matchingClient.name;
                if (clearClientBtn) clearClientBtn.classList.remove('hidden');
                if (!vendorNameInput.value.trim()) {
                    vendorNameInput.value = matchingClient.name;
                }
            }
        }
    } else {
        invoiceSearchInput.value = '';
        if (clearInvoiceBtn) clearInvoiceBtn.classList.add('hidden');
    }
    invoiceDropdown.classList.add('hidden');
}

function clearSelectedInvoice() {
    selectInvoice('', '', '', '');
}

function showAllInvoices(e) {
    if (e) e.stopPropagation();
    renderInvoiceResults(' ');
    invoiceSearchInput.focus();
}

clientSearchInput.addEventListener('input', () => renderClientResults(clientSearchInput.value));
clientSearchInput.addEventListener('focus', () => renderClientResults(clientSearchInput.value));

invoiceSearchInput.addEventListener('input', () => renderInvoiceResults(invoiceSearchInput.value));
invoiceSearchInput.addEventListener('focus', () => renderInvoiceResults(invoiceSearchInput.value));

document.addEventListener('click', (e) => {
    if (!clientSearchInput.contains(e.target) && !clientDropdown.contains(e.target)) {
        clientDropdown.classList.add('hidden');
    }
    if (!invoiceSearchInput.contains(e.target) && !invoiceDropdown.contains(e.target)) {
        invoiceDropdown.classList.add('hidden');
    }
});

// ─── Quick Category Modal ───────────────────────────────────────────────────
function openQuickCategoryModal() {
    document.getElementById('quick-category-modal').classList.remove('hidden');
    const input = document.getElementById('quick_cat_name_input');
    input.value = '';
    document.getElementById('quick_cat_error').classList.add('hidden');
    setTimeout(() => input.focus(), 100);
}
function closeQuickCategoryModal() {
    document.getElementById('quick-category-modal').classList.add('hidden');
}
async function saveQuickCategory() {
    const input  = document.getElementById('quick_cat_name_input');
    const errEl  = document.getElementById('quick_cat_error');
    const btn    = document.getElementById('save_quick_cat_btn');
    const name   = input.value.trim();

    if (!name) {
        errEl.textContent = 'Please enter a category name.';
        errEl.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>Saving...';

    // Get current CSRF token from the page form
    const csrfInput = document.querySelector('input[name="csrf"]');
    const csrfToken = csrfInput ? csrfInput.value : '';

    try {
        const res = await fetch('api_create_category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name, csrf: csrfToken })
        });
        const data = await res.json();

        // Refresh CSRF token in parent form so next save doesn't fail
        if (data.csrf && csrfInput) {
            csrfInput.value = data.csrf;
        }

        if (data.success) {
            const select = document.getElementById('expense_category_select');
            let existingOpt = select.querySelector(`option[value="${data.id}"]`);
            if (!existingOpt) {
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.name;
                select.appendChild(opt);
            }
            select.value = data.id;
            closeQuickCategoryModal();
        } else {
            errEl.textContent = data.error || 'Failed to save category.';
            errEl.classList.remove('hidden');
        }
    } catch (e) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check mr-1.5"></i>Save Category';
    }
}
</script>

<?php page_end(); ?>
