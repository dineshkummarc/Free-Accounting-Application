<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$view      = $_GET['view'] ?? 'client'; // 'client' or 'agent'

// ─── Client Sales Query ─────────────────────────────────────────────────────
$stClient = $pdo->prepare("
    SELECT 
        c.id,
        c.company_name,
        c.contact_name,
        c.email,
        COUNT(i.id) as total_invoices,
        COALESCE(SUM(i.total), 0) as total_revenue,
        COALESCE(SUM(i.paid_amount), 0) as total_paid,
        COALESCE(SUM(i.total - i.paid_amount), 0) as total_outstanding
    FROM clients c
    LEFT JOIN invoices i ON i.client_id = c.id AND i.tenant_id = ? AND i.status != 'cancelled' AND i.invoice_date BETWEEN ? AND ?
    WHERE c.tenant_id = ?
    GROUP BY c.id, c.company_name, c.contact_name, c.email
    ORDER BY total_revenue DESC
");
$stClient->execute([$tid, $startDate, $endDate, $tid]);
$clientSales = $stClient->fetchAll();

$totalRevAll  = array_sum(array_column($clientSales, 'total_revenue'));
$totalPaidAll = array_sum(array_column($clientSales, 'total_paid'));
$totalDueAll  = array_sum(array_column($clientSales, 'total_outstanding'));

// ─── Agent / Salesperson Sales Query ─────────────────────────────────────────
$stAgent = $pdo->prepare("
    SELECT 
        u.id agent_id,
        u.name agent_name,
        ut.role agent_role,
        COUNT(i.id) as total_invoices,
        COALESCE(SUM(i.total), 0) as total_revenue,
        COALESCE(SUM(i.paid_amount), 0) as total_paid,
        COALESCE(SUM(i.total - i.paid_amount), 0) as total_outstanding
    FROM users u
    JOIN user_tenants ut ON ut.user_id = u.id AND ut.tenant_id = ?
    LEFT JOIN invoices i ON i.agent_id = u.id AND i.tenant_id = ? AND i.status != 'cancelled' AND i.invoice_date BETWEEN ? AND ?
    GROUP BY u.id, u.name, ut.role
    ORDER BY total_revenue DESC
");
$stAgent->execute([$tid, $tid, $startDate, $endDate]);
$agentSales = $stAgent->fetchAll();

$totalAgentRev  = array_sum(array_column($agentSales, 'total_revenue'));
$totalAgentPaid = array_sum(array_column($agentSales, 'total_paid'));
$totalAgentDue  = array_sum(array_column($agentSales, 'total_outstanding'));

page_start('Client & Agent Sales Report');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Sales & Revenue Analysis</h1>
        <p class="mt-1 text-sm text-slate-500">Revenue breakdown by client and sales agent for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-3">
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2"></i>Print Report
        </button>
    </div>
</div>

<!-- Date Filter + View Toggle -->
<div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm mb-8">
    <form method="get" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Start Date</label>
            <input type="date" name="start_date" value="<?=e($startDate)?>" class="rounded-xl border-slate-300 text-sm py-1.5 px-3 border focus:ring-2 focus:ring-amber-500">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">End Date</label>
            <input type="date" name="end_date" value="<?=e($endDate)?>" class="rounded-xl border-slate-300 text-sm py-1.5 px-3 border focus:ring-2 focus:ring-amber-500">
        </div>
        <div class="pt-5 flex gap-2">
            <button type="submit" name="view" value="client" class="px-4 py-2 <?=$view==='client'?'bg-slate-900 text-white':'bg-slate-100 text-slate-700 hover:bg-slate-200'?> rounded-xl text-xs font-bold transition-all">
                <i class="fa-solid fa-building mr-1"></i>By Client
            </button>
            <button type="submit" name="view" value="agent" class="px-4 py-2 <?=$view==='agent'?'bg-amber-500 text-white':'bg-slate-100 text-slate-700 hover:bg-slate-200'?> rounded-xl text-xs font-bold transition-all">
                <i class="fa-solid fa-user-tie mr-1"></i>By Agent
            </button>
        </div>
    </form>
</div>

<?php if ($view === 'client'): ?>

<!-- Summary Cards — Client View -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Sales Revenue</span>
        <div class="text-2xl font-extrabold text-blue-600 mt-1"><?=money($totalRevAll)?></div>
        <span class="text-xs text-slate-500">Gross Invoiced Revenue</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Collected</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($totalPaidAll)?></div>
        <span class="text-xs text-slate-500">Payments Received</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Uncollected Balance</span>
        <div class="text-2xl font-extrabold text-amber-600 mt-1"><?=money($totalDueAll)?></div>
        <span class="text-xs text-slate-500">Outstanding A/R</span>
    </div>
</div>

<!-- Client Sales Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="text-base font-bold text-slate-900"><i class="fa-solid fa-building text-slate-400 mr-2"></i>Revenue by Client Account</h2>
    </div>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Client Company</th>
                <th class="px-6 py-3.5 text-center">Invoices</th>
                <th class="px-6 py-3.5 text-right">Total Revenue</th>
                <th class="px-6 py-3.5 text-right">Collected</th>
                <th class="px-6 py-3.5 text-right">Outstanding</th>
                <th class="px-6 py-3.5 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <?php if (empty($clientSales)): ?>
                <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">No invoices found for this date range.</td></tr>
            <?php endif; ?>
            <?php foreach ($clientSales as $c): ?>
                <tr>
                    <td class="px-6 py-3.5 font-bold text-slate-900">
                        <?=e($c['company_name'])?>
                        <div class="text-xs font-normal text-slate-400"><?=e($c['contact_name'])?> (<?=e($c['email'])?>)</div>
                    </td>
                    <td class="px-6 py-3.5 text-center font-bold text-slate-700"><?=$c['total_invoices']?></td>
                    <td class="px-6 py-3.5 text-right font-extrabold text-slate-900"><?=money($c['total_revenue'])?></td>
                    <td class="px-6 py-3.5 text-right font-bold text-emerald-600"><?=money($c['total_paid'])?></td>
                    <td class="px-6 py-3.5 text-right font-bold text-amber-600"><?=money($c['total_outstanding'])?></td>
                    <td class="px-6 py-3.5 text-center">
                        <a href="client_statement?client_id=<?=$c['id']?>" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-bold transition-all">
                            <i class="fa-solid fa-file-invoice mr-1"></i>Statement
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php else: ?>

<!-- Summary Cards — Agent View -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-amber-200/80 shadow-sm bg-gradient-to-br from-white to-amber-50">
        <span class="text-xs font-bold text-amber-600 uppercase tracking-wider">Total Agent Revenue</span>
        <div class="text-2xl font-extrabold text-amber-700 mt-1"><?=money($totalAgentRev)?></div>
        <span class="text-xs text-slate-500">Gross across all agents</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-emerald-200/80 shadow-sm">
        <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Total Collected</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($totalAgentPaid)?></div>
        <span class="text-xs text-slate-500">Payments Received</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Outstanding</span>
        <div class="text-2xl font-extrabold text-slate-700 mt-1"><?=money($totalAgentDue)?></div>
        <span class="text-xs text-slate-500">Unpaid A/R</span>
    </div>
</div>

<!-- Agent Sales Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="text-base font-bold text-slate-900"><i class="fa-solid fa-user-tie text-amber-500 mr-2"></i>Revenue by Sales Agent / Account Manager</h2>
    </div>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Agent Name</th>
                <th class="px-6 py-3.5">Role</th>
                <th class="px-6 py-3.5 text-center">Invoices</th>
                <th class="px-6 py-3.5 text-right">Total Revenue</th>
                <th class="px-6 py-3.5 text-right">Collected</th>
                <th class="px-6 py-3.5 text-right">Outstanding</th>
                <th class="px-6 py-3.5 text-right">Collection %</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <?php if (empty($agentSales)): ?>
                <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400">No agents found or no invoices assigned to agents in this date range.</td></tr>
            <?php endif; ?>
            <?php foreach ($agentSales as $ag): ?>
                <?php
                $collRate = $ag['total_revenue'] > 0 ? round(($ag['total_paid'] / $ag['total_revenue']) * 100, 1) : 0;
                $isTopAgent = $ag['total_revenue'] == max(array_column($agentSales, 'total_revenue')) && $ag['total_revenue'] > 0;
                ?>
                <tr class="<?=$isTopAgent ? 'bg-amber-50/40' : ''?>">
                    <td class="px-6 py-3.5 font-bold text-slate-900 flex items-center gap-2">
                        <?php if ($isTopAgent): ?>
                            <span class="text-amber-500" title="Top Performer"><i class="fa-solid fa-star text-xs"></i></span>
                        <?php endif; ?>
                        <?=e($ag['agent_name'])?>
                    </td>
                    <td class="px-6 py-3.5">
                        <span class="px-2 py-0.5 rounded-full text-2xs font-bold uppercase
                            <?php echo match($ag['agent_role']) {
                                'owner'  => 'bg-purple-50 text-purple-700 border border-purple-200',
                                'admin'  => 'bg-blue-50 text-blue-700 border border-blue-200',
                                'sales'  => 'bg-amber-50 text-amber-700 border border-amber-200',
                                default  => 'bg-slate-100 text-slate-600'
                            }; ?>"><?=e($ag['agent_role'])?></span>
                    </td>
                    <td class="px-6 py-3.5 text-center font-bold text-slate-700"><?=$ag['total_invoices']?></td>
                    <td class="px-6 py-3.5 text-right font-extrabold text-slate-900"><?=money($ag['total_revenue'])?></td>
                    <td class="px-6 py-3.5 text-right font-bold text-emerald-600"><?=money($ag['total_paid'])?></td>
                    <td class="px-6 py-3.5 text-right font-bold text-amber-600"><?=money($ag['total_outstanding'])?></td>
                    <td class="px-6 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <div class="w-16 bg-slate-200 rounded-full h-1.5">
                                <div class="h-1.5 rounded-full <?=$collRate >= 80 ? 'bg-emerald-500' : ($collRate >= 50 ? 'bg-amber-500' : 'bg-rose-500')?>" style="width:<?=min(100,$collRate)?>%"></div>
                            </div>
                            <span class="text-xs font-bold text-slate-700"><?=$collRate?>%</span>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php page_end(); ?>
