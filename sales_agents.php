<?php
require __DIR__ . '/bootstrap.php';
require_role(['owner', 'admin', 'accountant', 'sales']);
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Handle Status Toggle or Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    $targetId = (int)($_POST['agent_id'] ?? 0);

    if ($_POST['action'] === 'toggle_status') {
        $stToggle = $pdo->prepare("UPDATE sales_agents SET is_active = NOT is_active WHERE id = ? AND tenant_id = ?");
        $stToggle->execute([$targetId, $tid]);
        flash('success', 'Partner status updated successfully.');
        redirect('sales_agents.php');
    }

    if ($_POST['action'] === 'delete' && has_role(['owner', 'admin'])) {
        // Check if agent is assigned to invoices
        $stCheck = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE agent_id = ? AND tenant_id = ?");
        $stCheck->execute([$targetId, $tid]);
        if ($stCheck->fetchColumn() > 0) {
            // Soft deactivate instead of hard delete to preserve historical reporting
            $pdo->prepare("UPDATE sales_agents SET is_active = 0 WHERE id = ? AND tenant_id = ?")->execute([$targetId, $tid]);
            flash('warning', 'Agent has assigned invoices and cannot be deleted; account was deactivated instead.');
        } else {
            $pdo->prepare("DELETE FROM commission_rules WHERE agent_id = ? AND tenant_id = ?")->execute([$targetId, $tid]);
            $pdo->prepare("DELETE FROM sales_agents WHERE id = ? AND tenant_id = ?")->execute([$targetId, $tid]);
            flash('success', 'Agent removed successfully.');
        }
        redirect('sales_agents.php');
    }
}

$filterType = $_GET['type'] ?? 'all';

// Query Agents and their total aggregated invoice sales & commissions
$sql = "
    SELECT 
        sa.*,
        COUNT(i.id) as total_invoices,
        COALESCE(SUM(i.total), 0) as total_sales,
        COALESCE(SUM(i.paid_amount), 0) as total_collected,
        COALESCE(SUM(i.commission_amount), 0) as total_commission
    FROM sales_agents sa
    LEFT JOIN invoices i ON i.agent_id = sa.id AND i.tenant_id = sa.tenant_id AND i.status != 'cancelled'
    WHERE sa.tenant_id = ?
";
$params = [$tid];

if ($filterType === 'agent' || $filterType === 'channel_partner') {
    $sql .= " AND sa.type = ?";
    $params[] = $filterType;
}

$sql .= " GROUP BY sa.id ORDER BY sa.is_active DESC, sa.created_at DESC";

$stAgents = $pdo->prepare($sql);
$stAgents->execute($params);
$agents = $stAgents->fetchAll();

// Global metrics
$stTotalSales = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT sa.id) as active_count,
        COUNT(i.id) as total_invoices,
        COALESCE(SUM(i.total), 0) as total_revenue,
        COALESCE(SUM(i.commission_amount), 0) as total_commissions
    FROM sales_agents sa
    LEFT JOIN invoices i ON i.agent_id = sa.id AND i.tenant_id = sa.tenant_id AND i.status != 'cancelled'
    WHERE sa.tenant_id = ? AND sa.is_active = 1
");
$stTotalSales->execute([$tid]);
$stats = $stTotalSales->fetch() ?: [
    'active_count' => 0,
    'total_invoices' => 0,
    'total_revenue' => 0,
    'total_commissions' => 0
];

page_start('Sales Agents & Channel Partners');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Sales Agents & Channel Partners</h1>
        <p class="mt-1 text-sm text-slate-500">Manage internal sales executives, broker network, and commission structures for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-3">
        <button type="button" onclick="openAddAgentModal()" class="inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-extrabold rounded-xl text-xs shadow-md transition-all">
            <i class="fa-solid fa-user-plus mr-2"></i>+ Add Agent / Partner
        </button>
    </div>
</div>

<!-- Stats Summary Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Partners</span>
            <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs"><i class="fa-solid fa-users"></i></span>
        </div>
        <div class="text-2xl font-black text-slate-900 mt-2"><?=(int)$stats['active_count']?></div>
        <span class="text-xs text-slate-500">Sales reps & channel brokers</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Invoices Handled</span>
            <span class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-xs"><i class="fa-solid fa-file-invoice"></i></span>
        </div>
        <div class="text-2xl font-black text-purple-700 mt-2"><?=(int)$stats['total_invoices']?></div>
        <span class="text-xs text-slate-500">Assigned closed/sent invoices</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Partner Generated Sales</span>
            <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs"><i class="fa-solid fa-sack-dollar"></i></span>
        </div>
        <div class="text-2xl font-black text-emerald-600 mt-2"><?=money((float)$stats['total_revenue'])?></div>
        <span class="text-xs text-slate-500">Gross invoiced volume</span>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-amber-200/80 shadow-sm bg-gradient-to-br from-white to-amber-50/50">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-amber-700 uppercase tracking-wider">Accrued Commissions</span>
            <span class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-700 flex items-center justify-center text-xs"><i class="fa-solid fa-coins"></i></span>
        </div>
        <div class="text-2xl font-black text-amber-700 mt-2"><?=money((float)$stats['total_commissions'])?></div>
        <span class="text-xs text-amber-800/80">Payable commission balance</span>
    </div>
</div>

<!-- Type Filter Tabs -->
<div class="flex items-center space-x-2 mb-6">
    <a href="sales_agents.php?type=all" class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all <?=$filterType==='all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'?>">
        All Partners (<?=count($agents)?>)
    </a>
    <a href="sales_agents.php?type=agent" class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all <?=$filterType==='agent' ? 'bg-amber-500 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'?>">
        <i class="fa-solid fa-user-tie mr-1"></i>Direct Sales Agents
    </a>
    <a href="sales_agents.php?type=channel_partner" class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all <?=$filterType==='channel_partner' ? 'bg-purple-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'?>">
        <i class="fa-solid fa-handshake mr-1"></i>Channel Partners & Brokers
    </a>
</div>

<!-- Partners Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900 flex items-center">
            <i class="fa-solid fa-users-viewfinder text-amber-500 mr-2"></i> Partner Directory & Commission Breakdown
        </h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Agent / Partner</th>
                    <th class="px-6 py-3.5">Type</th>
                    <th class="px-6 py-3.5">Contact Details</th>
                    <th class="px-6 py-3.5">Commission Structure</th>
                    <th class="px-6 py-3.5 text-center">Invoices</th>
                    <th class="px-6 py-3.5 text-right">Total Revenue</th>
                    <th class="px-6 py-3.5 text-right">Commission Earned</th>
                    <th class="px-6 py-3.5 text-center">Status</th>
                    <th class="px-6 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($agents)): ?>
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-handshake-slash text-3xl mb-3 text-slate-300 block"></i>
                            No sales agents or channel partners found. Click <strong>+ Add Agent / Partner</strong> to register your first partner.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($agents as $ag): ?>
                    <?php
                    $badge = \Core\Commission::getBadge($ag);
                    $details = \Core\Commission::getDetails($ag);
                    ?>
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-extrabold text-slate-900 flex items-center gap-2">
                                <span><?=e($ag['name'])?></span>
                            </div>
                            <?php if (!empty($ag['company_name'])): ?>
                                <div class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                    <i class="fa-solid fa-building text-3xs text-slate-400"></i>
                                    <span><?=e($ag['company_name'])?></span>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="px-6 py-4">
                            <?php if ($ag['type'] === 'channel_partner'): ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-2xs font-extrabold bg-purple-50 text-purple-700 border border-purple-200 gap-1">
                                    <i class="fa-solid fa-handshake text-3xs"></i> Channel Partner
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-2xs font-extrabold bg-blue-50 text-blue-700 border border-blue-200 gap-1">
                                    <i class="fa-solid fa-user-tie text-3xs"></i> Direct Agent
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="px-6 py-4 text-xs">
                            <?php if (!empty($ag['email'])): ?>
                                <div class="text-slate-700 font-semibold flex items-center gap-1.5">
                                    <i class="fa-solid fa-envelope text-3xs text-slate-400"></i>
                                    <span><?=e($ag['email'])?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ag['phone'])): ?>
                                <div class="text-slate-500 font-medium flex items-center gap-1.5 mt-0.5">
                                    <i class="fa-solid fa-phone text-3xs text-slate-400"></i>
                                    <span><?=e($ag['phone'])?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (empty($ag['email']) && empty($ag['phone'])): ?>
                                <span class="text-slate-400 text-2xs italic">No contact info</span>
                            <?php endif; ?>
                        </td>

                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200 shadow-2xs" title="<?=e($details)?>">
                                <i class="fa-solid fa-coins text-amber-500 text-xs"></i>
                                <span><?=e($badge)?></span>
                            </span>
                            <?php if ($ag['commission_type'] === 'slab' && !empty($ag['slab_json'])): ?>
                                <?php $slabs = is_string($ag['slab_json']) ? json_decode($ag['slab_json'], true) : $ag['slab_json']; ?>
                                <div class="mt-1 text-3xs text-slate-500 space-x-1.5">
                                    <?php foreach ($slabs as $sIdx => $sl): ?>
                                        <span class="bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">
                                            <?=number_format($sl['from']/1000, 0)?>k-<?=$sl['to'] ? number_format($sl['to']/1000,0).'k' : '∞'?>: <strong><?=$sl['rate']?>%</strong>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="px-6 py-4 text-center font-bold text-slate-700">
                            <?=(int)$ag['total_invoices']?>
                        </td>

                        <td class="px-6 py-4 text-right font-extrabold text-slate-900">
                            <?=money((float)$ag['total_sales'])?>
                        </td>

                        <td class="px-6 py-4 text-right font-extrabold text-emerald-600">
                            <?=money((float)$ag['total_commission'])?>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <form method="post" class="inline">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="agent_id" value="<?=$ag['id']?>">
                                <button type="submit" class="px-2.5 py-1 rounded-full text-2xs font-extrabold transition-all <?=$ag['is_active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 border border-slate-200 hover:bg-slate-200'?>">
                                    <?=$ag['is_active'] ? 'Active' : 'Inactive'?>
                                </button>
                            </form>
                        </td>

                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end space-x-2">
                                <a href="reports_client_sales?view=agent" class="p-1.5 text-slate-400 hover:text-amber-600 rounded-lg hover:bg-slate-100 transition-colors" title="View Sales Performance">
                                    <i class="fa-solid fa-chart-line"></i>
                                </a>
                                <?php if (has_role(['owner', 'admin'])): ?>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to remove or deactivate this partner?');" class="inline">
                                        <?=csrf_field()?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="agent_id" value="<?=$ag['id']?>">
                                        <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors" title="Delete / Deactivate">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
require __DIR__ . '/add_agent_modal.php';
page_end(); 
?>
