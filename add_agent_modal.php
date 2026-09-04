<!-- Quick Add Sales Agent / Channel Partner Modal Component -->
<div id="addAgentModal" class="fixed inset-0 z-[100] hidden overflow-y-auto" aria-labelledby="agent-modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-slate-950/75 backdrop-blur-xs transition-opacity duration-300 opacity-0" id="addAgentModalBackdrop" onclick="closeAddAgentModal()"></div>

    <!-- Modal Box -->
    <div class="flex min-h-full items-center justify-center p-3 sm:p-4 text-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 w-full sm:max-w-2xl border border-slate-200 scale-95 opacity-0 duration-300" id="addAgentModalBox">
            
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-slate-800 px-6 py-4 text-white flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-600 text-slate-950 flex items-center justify-center font-black text-base shadow-lg shadow-amber-500/20">
                        <i class="fa-solid fa-handshake"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight" id="agent-modal-title">Add Sales Agent / Channel Partner</h3>
                        <p class="text-2xs text-slate-400">Configure partner profile & custom commission calculation rule</p>
                    </div>
                </div>
                <button type="button" onclick="closeAddAgentModal()" class="text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-slate-800/80">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="quickAddAgentForm" onsubmit="submitQuickAddAgent(event)" class="p-6 space-y-5 max-h-[80vh] overflow-y-auto">
                <?=csrf_field()?>
                
                <!-- Feedback Alert -->
                <div id="quickAddAgentAlert" class="hidden rounded-xl p-3 text-xs font-semibold flex items-center space-x-2"></div>

                <!-- Partner Type Switcher -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Partner Type</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative flex items-center p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-all has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/40 has-[:checked]:ring-2 has-[:checked]:ring-amber-500/20">
                            <input type="radio" name="type" value="agent" checked class="w-4 h-4 text-amber-600 border-slate-300 focus:ring-amber-500">
                            <div class="ml-3">
                                <span class="block text-xs font-extrabold text-slate-900">Direct Sales Agent</span>
                                <span class="block text-3xs text-slate-500">Internal sales rep / account manager</span>
                            </div>
                        </label>
                        <label class="relative flex items-center p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-all has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/40 has-[:checked]:ring-2 has-[:checked]:ring-amber-500/20">
                            <input type="radio" name="type" value="channel_partner" class="w-4 h-4 text-amber-600 border-slate-300 focus:ring-amber-500">
                            <div class="ml-3">
                                <span class="block text-xs font-extrabold text-slate-900">Channel Partner / Broker</span>
                                <span class="block text-3xs text-slate-500">External agency, affiliate or reseller</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Basic Profile Details -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Agent / Partner Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" id="quick_agent_name" required placeholder="e.g. Tariq Al Nuaimi / Apex Solutions" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Company / Agency Name</label>
                        <input type="text" name="company_name" placeholder="e.g. Apex Strategic Partners LLC" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Address</label>
                        <input type="email" name="email" placeholder="agent@partner.com" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Phone Number</label>
                        <input type="text" name="phone" placeholder="+971 50 000 0000" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                </div>

                <!-- Commission Structure Configuration -->
                <div class="pt-3 border-t border-slate-200">
                    <div class="flex items-center justify-between mb-2.5">
                        <label class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center">
                            <i class="fa-solid fa-calculator text-amber-500 mr-2"></i> Commission Structure & Calculation Logic
                        </label>
                    </div>

                    <!-- Calculation Base Selector -->
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 mb-4">
                        <label class="block text-2xs font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">Calculate Commission Base On</label>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="flex items-center space-x-2 text-xs font-bold text-slate-700 cursor-pointer bg-white p-2 rounded-lg border border-slate-200 hover:border-amber-400 transition-all">
                                <input type="radio" name="calculation_base" value="total" checked onchange="handleCommissionParamChange()" class="text-amber-600 focus:ring-amber-500">
                                <span>Gross Total (incl. VAT)</span>
                            </label>
                            <label class="flex items-center space-x-2 text-xs font-bold text-slate-700 cursor-pointer bg-white p-2 rounded-lg border border-slate-200 hover:border-amber-400 transition-all">
                                <input type="radio" name="calculation_base" value="subtotal" onchange="handleCommissionParamChange()" class="text-amber-600 focus:ring-amber-500">
                                <span>Subtotal (excl. VAT)</span>
                            </label>
                            <label class="flex items-center space-x-2 text-xs font-bold text-slate-700 cursor-pointer bg-white p-2 rounded-lg border border-slate-200 hover:border-amber-400 transition-all">
                                <input type="radio" name="calculation_base" value="paid_amount" onchange="handleCommissionParamChange()" class="text-amber-600 focus:ring-amber-500">
                                <span>Paid Amount (Collected)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Commission Type Pills -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button type="button" onclick="setAgentCommType('percentage')" id="btn-comm-percentage" class="agent-comm-tab-btn px-3 py-1.5 rounded-xl text-xs font-extrabold transition-all bg-amber-500 text-white shadow-xs">
                            <i class="fa-solid fa-percent mr-1.5"></i>Flat Percentage
                        </button>
                        <button type="button" onclick="setAgentCommType('slab')" id="btn-comm-slab" class="agent-comm-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all bg-slate-100 text-slate-700 hover:bg-slate-200">
                            <i class="fa-solid fa-layer-group mr-1.5"></i>Tiered / Slab Base
                        </button>
                        <button type="button" onclick="setAgentCommType('fixed')" id="btn-comm-fixed" class="agent-comm-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all bg-slate-100 text-slate-700 hover:bg-slate-200">
                            <i class="fa-solid fa-money-bill-wave mr-1.5"></i>Fixed Amount
                        </button>
                        <button type="button" onclick="setAgentCommType('milestone')" id="btn-comm-milestone" class="agent-comm-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all bg-slate-100 text-slate-700 hover:bg-slate-200">
                            <i class="fa-solid fa-bullseye mr-1.5"></i>Target Milestone
                        </button>
                        <button type="button" onclick="setAgentCommType('custom')" id="btn-comm-custom" class="agent-comm-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all bg-slate-100 text-slate-700 hover:bg-slate-200">
                            <i class="fa-solid fa-code mr-1.5"></i>Custom Formula
                        </button>
                    </div>
                    <input type="hidden" name="commission_type" id="agent_commission_type_input" value="percentage">

                    <!-- Panel: Percentage -->
                    <div id="comm-panel-percentage" class="comm-type-panel space-y-3">
                        <div class="bg-amber-50/50 p-4 rounded-xl border border-amber-200">
                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">Percentage Rate (%)</label>
                            <div class="relative max-w-xs">
                                <input type="number" step="0.01" min="0" max="100" name="rate" id="agent_rate_percentage" value="5.00" oninput="handleCommissionParamChange()" class="w-full rounded-xl border border-slate-300 bg-white pl-4 pr-10 py-2.5 text-sm font-extrabold text-slate-900 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-sm font-extrabold text-slate-400">%</span>
                            </div>
                            <p class="mt-1.5 text-2xs text-slate-500">Agent receives a flat percentage of the selected calculation base.</p>
                        </div>
                    </div>

                    <!-- Panel: Slab / Tiered -->
                    <div id="comm-panel-slab" class="comm-type-panel hidden space-y-3">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">Tiered Slab Bracket Rates</h4>
                                    <p class="text-3xs text-slate-500">Marginal brackets — each tier applies to amounts within its range</p>
                                </div>
                                <button type="button" onclick="addAgentSlabTier()" class="px-2.5 py-1 text-2xs font-extrabold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition-all flex items-center gap-1">
                                    <i class="fa-solid fa-plus"></i> Add Tier
                                </button>
                            </div>
                            
                            <div class="space-y-2" id="agent-slab-tiers-container">
                                <!-- Tiers dynamically generated via JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Panel: Fixed Amount -->
                    <div id="comm-panel-fixed" class="comm-type-panel hidden space-y-3">
                        <div class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-200">
                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">Fixed Commission Amount</label>
                            <div class="relative max-w-xs">
                                <input type="number" step="0.01" min="0" name="fixed_rate" id="agent_rate_fixed" value="500.00" oninput="handleCommissionParamChange()" class="w-full rounded-xl border border-slate-300 bg-white pl-4 pr-14 py-2.5 text-sm font-extrabold text-slate-900 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-extrabold text-slate-400">AED</span>
                            </div>
                            <p class="mt-1.5 text-2xs text-slate-500">Fixed flat fee earned per issued or closed invoice.</p>
                        </div>
                    </div>

                    <!-- Panel: Milestone -->
                    <div id="comm-panel-milestone" class="comm-type-panel hidden space-y-3">
                        <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-200">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">Base Rate (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" name="milestone_base_rate" id="agent_milestone_base_rate" value="3.00" oninput="handleCommissionParamChange()" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-extrabold text-slate-900">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">Target Threshold (AED)</label>
                                    <input type="number" step="100" min="0" name="milestone_target" id="agent_milestone_target" value="50000" oninput="handleCommissionParamChange()" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-extrabold text-slate-900">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">Achieved Rate (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" name="milestone_rate" id="agent_milestone_rate" value="7.00" oninput="handleCommissionParamChange()" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-extrabold text-slate-900">
                                </div>
                            </div>
                            <p class="mt-2 text-2xs text-slate-500">If the invoice amount reaches or exceeds the target, the higher achieved rate is granted.</p>
                        </div>
                    </div>

                    <!-- Panel: Custom Formula -->
                    <div id="comm-panel-custom" class="comm-type-panel hidden space-y-3">
                        <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-200">
                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">Formula Expression</label>
                            <input type="text" name="custom_formula" id="agent_custom_formula" value="{amount} * 0.08" oninput="handleCommissionParamChange()" placeholder="{amount} * 0.08" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-mono font-bold text-slate-900 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                            
                            <div class="mt-2 flex flex-wrap items-center gap-1.5 text-3xs">
                                <span class="text-slate-500 font-bold">Click variables to insert:</span>
                                <button type="button" onclick="insertFormulaToken('{amount}')" class="px-2 py-0.5 bg-white border border-purple-200 rounded font-mono text-purple-700 hover:bg-purple-100">{amount}</button>
                                <button type="button" onclick="insertFormulaToken('{total}')" class="px-2 py-0.5 bg-white border border-purple-200 rounded font-mono text-purple-700 hover:bg-purple-100">{total}</button>
                                <button type="button" onclick="insertFormulaToken('{subtotal}')" class="px-2 py-0.5 bg-white border border-purple-200 rounded font-mono text-purple-700 hover:bg-purple-100">{subtotal}</button>
                                <button type="button" onclick="insertFormulaToken('{paid_amount}')" class="px-2 py-0.5 bg-white border border-purple-200 rounded font-mono text-purple-700 hover:bg-purple-100">{paid_amount}</button>
                            </div>
                        </div>
                    </div>

                    <!-- Live Commission Calculator / Simulator -->
                    <div class="mt-4 bg-gradient-to-r from-slate-900 to-slate-800 rounded-xl p-3.5 text-white shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-xs">
                                <i class="fa-solid fa-flask"></i>
                            </div>
                            <div>
                                <span class="block text-2xs font-extrabold uppercase tracking-wider text-slate-400">Live Commission Simulator</span>
                                <div class="flex items-center gap-1 text-xs">
                                    <span class="text-slate-400">Test Amount:</span>
                                    <input type="number" id="agent_sim_amount" value="25000" step="1000" oninput="handleCommissionParamChange()" class="w-24 bg-slate-800 border border-slate-700 rounded px-2 py-0.5 text-xs font-extrabold text-amber-400 focus:outline-none focus:border-amber-500 text-right">
                                    <span class="text-slate-400 font-bold">AED</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right sm:border-l sm:border-slate-700 sm:pl-4">
                            <div class="text-xs text-slate-400 font-medium">Estimated Commission</div>
                            <div class="text-lg font-black text-emerald-400 tracking-tight" id="agent_sim_result">1,250.00 AED</div>
                            <div class="text-3xs text-slate-400" id="agent_sim_effective_rate">Effective Rate: 5.00%</div>
                        </div>
                    </div>
                </div>

                <!-- Notes / Commission Agreements -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Internal Notes & Partner Terms</label>
                    <textarea name="notes" rows="2" placeholder="e.g. Agreement signed on Jan 2026. Commission paid on 15th of every month." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></textarea>
                </div>

                <!-- Form Footer Actions -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-end space-x-3">
                    <button type="button" onclick="closeAddAgentModal()" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition-all">
                        Cancel
                    </button>
                    <button type="submit" id="quickAddAgentSubmitBtn" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white text-xs font-black shadow-md transition-all flex items-center space-x-2">
                        <i class="fa-solid fa-check"></i>
                        <span id="quickAddAgentBtnText">Save Agent & Select</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Floating Toast Container for Agent Add -->
<div id="quickAgentToast" class="fixed bottom-5 right-5 z-[150] transform translate-y-10 opacity-0 transition-all duration-300 pointer-events-none">
    <div class="bg-slate-900 text-white px-4 py-3 rounded-2xl shadow-2xl border border-slate-700 flex items-center space-x-3 text-xs font-bold">
        <div class="w-7 h-7 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
            <i class="fa-solid fa-check text-sm"></i>
        </div>
        <span id="quickAgentToastMsg">Agent added successfully</span>
    </div>
</div>

<script>
let lastSelectedAgentValue = '';
let currentCommType = 'percentage';

// Default initial slab tiers
let slabTiers = [
    { from: 0, to: 10000, rate: 3.0 },
    { from: 10000, to: 50000, rate: 5.0 },
    { from: 50000, to: null, rate: 8.0 }
];

function openAddAgentModal(triggerSelect = null) {
    if (triggerSelect && triggerSelect.dataset.lastSelected !== undefined) {
        lastSelectedAgentValue = triggerSelect.dataset.lastSelected;
    } else {
        const activeSel = document.querySelector('select[name="agent_id"]');
        if (activeSel) lastSelectedAgentValue = activeSel.dataset.lastSelected || activeSel.value || '';
    }

    const modal = document.getElementById("addAgentModal");
    const backdrop = document.getElementById("addAgentModalBackdrop");
    const box = document.getElementById("addAgentModalBox");
    const alertBox = document.getElementById("quickAddAgentAlert");

    alertBox.classList.add("hidden");
    alertBox.innerHTML = "";

    renderSlabTiers();
    handleCommissionParamChange();

    modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";

    setTimeout(() => {
        backdrop.classList.remove("opacity-0");
        backdrop.classList.add("opacity-100");
        box.classList.remove("scale-95", "opacity-0");
        box.classList.add("scale-100", "opacity-100");
        
        const firstInput = document.getElementById("quick_agent_name");
        if (firstInput) firstInput.focus();
    }, 20);
}

function closeAddAgentModal() {
    const modal = document.getElementById("addAgentModal");
    const backdrop = document.getElementById("addAgentModalBackdrop");
    const box = document.getElementById("addAgentModalBox");

    backdrop.classList.remove("opacity-100");
    backdrop.classList.add("opacity-0");
    box.classList.remove("scale-100", "opacity-100");
    box.classList.add("scale-95", "opacity-0");

    setTimeout(() => {
        modal.classList.add("hidden");
        document.body.style.overflow = "auto";
        document.getElementById("quickAddAgentForm").reset();
        setAgentCommType('percentage');

        // Revert any agent select that has __add_new_agent__ back to previous selection
        document.querySelectorAll('select[name="agent_id"]').forEach(sel => {
            if (sel.value === '__add_new_agent__') {
                sel.value = sel.dataset.lastSelected || '';
            }
        });
    }, 250);
}

function handleAgentSelectChange(selectElem) {
    if (selectElem.value === '__add_new_agent__') {
        openAddAgentModal(selectElem);
    } else {
        selectElem.dataset.lastSelected = selectElem.value;
        updateAgentCommissionBadgePreview();
    }
}

function setAgentCommType(type) {
    currentCommType = type;
    document.getElementById('agent_commission_type_input').value = type;

    // Toggle active tab buttons
    document.querySelectorAll('.agent-comm-tab-btn').forEach(btn => {
        btn.classList.remove('bg-amber-500', 'text-white', 'shadow-xs');
        btn.classList.add('bg-slate-100', 'text-slate-700');
    });
    const activeBtn = document.getElementById(`btn-comm-${type}`);
    if (activeBtn) {
        activeBtn.classList.remove('bg-slate-100', 'text-slate-700');
        activeBtn.classList.add('bg-amber-500', 'text-white', 'shadow-xs');
    }

    // Toggle panels
    document.querySelectorAll('.comm-type-panel').forEach(panel => panel.classList.add('hidden'));
    const activePanel = document.getElementById(`comm-panel-${type}`);
    if (activePanel) activePanel.classList.remove('hidden');

    handleCommissionParamChange();
}

function renderSlabTiers() {
    const container = document.getElementById("agent-slab-tiers-container");
    if (!container) return;
    container.innerHTML = "";

    slabTiers.forEach((tier, idx) => {
        const row = document.createElement("div");
        row.className = "flex items-center gap-2 bg-white p-2 rounded-xl border border-slate-200 text-xs";
        row.innerHTML = `
            <span class="font-extrabold text-slate-400 w-5 text-center text-2xs">#${idx + 1}</span>
            <div class="flex-1 flex items-center gap-1.5">
                <span class="text-3xs text-slate-400 font-bold uppercase">From:</span>
                <input type="number" step="100" min="0" name="slab_from[]" value="${tier.from}" oninput="updateSlabTierValue(${idx}, 'from', this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-900">
            </div>
            <div class="flex-1 flex items-center gap-1.5">
                <span class="text-3xs text-slate-400 font-bold uppercase">To:</span>
                <input type="number" step="100" min="0" name="slab_to[]" value="${tier.to !== null ? tier.to : ''}" placeholder="Above / Max" oninput="updateSlabTierValue(${idx}, 'to', this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-900">
            </div>
            <div class="w-24 flex items-center gap-1">
                <input type="number" step="0.1" min="0" max="100" name="slab_rate[]" value="${tier.rate}" oninput="updateSlabTierValue(${idx}, 'rate', this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs font-extrabold text-slate-900 text-right">
                <span class="font-bold text-slate-400">%</span>
            </div>
            <button type="button" onclick="removeAgentSlabTier(${idx})" class="text-slate-400 hover:text-rose-600 p-1 transition-colors" ${slabTiers.length <= 1 ? 'disabled style="opacity:0.3"' : ''}>
                <i class="fa-solid fa-trash-can"></i>
            </button>
        `;
        container.appendChild(row);
    });
}

function updateSlabTierValue(idx, field, val) {
    if (field === 'from' || field === 'rate') {
        slabTiers[idx][field] = parseFloat(val) || 0;
    } else if (field === 'to') {
        slabTiers[idx][field] = (val !== '' && val !== null) ? (parseFloat(val) || 0) : null;
    }
    handleCommissionParamChange();
}

function addAgentSlabTier() {
    const lastTier = slabTiers[slabTiers.length - 1];
    const newFrom = lastTier && lastTier.to ? lastTier.to : (lastTier ? lastTier.from + 50000 : 0);
    slabTiers.push({ from: newFrom, to: null, rate: 10.0 });
    renderSlabTiers();
    handleCommissionParamChange();
}

function removeAgentSlabTier(idx) {
    if (slabTiers.length <= 1) return;
    slabTiers.splice(idx, 1);
    renderSlabTiers();
    handleCommissionParamChange();
}

function insertFormulaToken(token) {
    const input = document.getElementById("agent_custom_formula");
    if (!input) return;
    input.value += (input.value ? ' ' : '') + token;
    handleCommissionParamChange();
}

function handleCommissionParamChange() {
    const simAmount = parseFloat(document.getElementById("agent_sim_amount")?.value) || 0;
    const type = currentCommType;
    let commission = 0;

    if (type === 'percentage') {
        const rate = parseFloat(document.getElementById("agent_rate_percentage")?.value) || 0;
        commission = (simAmount * rate) / 100;
    } else if (type === 'slab') {
        slabTiers.forEach(t => {
            const from = t.from || 0;
            const to = t.to !== null ? t.to : null;
            const rate = t.rate || 0;
            if (simAmount > from) {
                const slice = (to !== null) ? Math.min(simAmount, to) - from : simAmount - from;
                if (slice > 0) {
                    commission += (slice * rate) / 100;
                }
            }
        });
    } else if (type === 'fixed') {
        commission = parseFloat(document.getElementById("agent_rate_fixed")?.value) || 0;
    } else if (type === 'milestone') {
        const baseRate = parseFloat(document.getElementById("agent_milestone_base_rate")?.value) || 0;
        const target = parseFloat(document.getElementById("agent_milestone_target")?.value) || 0;
        const achievedRate = parseFloat(document.getElementById("agent_milestone_rate")?.value) || 0;
        if (target > 0 && simAmount >= target) {
            commission = (simAmount * achievedRate) / 100;
        } else {
            commission = (simAmount * baseRate) / 100;
        }
    } else if (type === 'custom') {
        let formula = document.getElementById("agent_custom_formula")?.value || '';
        formula = formula.replace(/\{amount\}|\{total\}|\{subtotal\}|\{paid_amount\}/gi, simAmount);
        try {
            // Sanitized arithmetic evaluation
            const clean = formula.replace(/[^0-9\.\+\-\*\/\(\)\s]/g, '');
            commission = clean ? (Function(`'use strict'; return (${clean})`)() || 0) : 0;
        } catch (e) {
            commission = 0;
        }
    }

    commission = Math.max(0, commission);
    const effRate = simAmount > 0 ? ((commission / simAmount) * 100).toFixed(2) : '0.00';

    const resEl = document.getElementById("agent_sim_result");
    const effEl = document.getElementById("agent_sim_effective_rate");
    if (resEl) resEl.textContent = `${commission.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} AED`;
    if (effEl) effEl.textContent = `Effective Rate: ${effRate}%`;
}

function submitQuickAddAgent(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById("quickAddAgentSubmitBtn");
    const btnText = document.getElementById("quickAddAgentBtnText");
    const alertBox = document.getElementById("quickAddAgentAlert");

    // Sync active rate value
    if (currentCommType === 'fixed') {
        const fixedVal = document.getElementById('agent_rate_fixed')?.value || '0';
        let rateInput = form.querySelector('input[name="rate"]');
        if (!rateInput) {
            rateInput = document.createElement('input');
            rateInput.type = 'hidden';
            rateInput.name = 'rate';
            form.appendChild(rateInput);
        }
        rateInput.value = fixedVal;
    } else if (currentCommType === 'milestone') {
        const baseRate = document.getElementById('agent_milestone_base_rate')?.value || '0';
        let rateInput = form.querySelector('input[name="rate"]');
        if (rateInput) rateInput.value = baseRate;
    }

    const formData = new FormData(form);

    submitBtn.disabled = true;
    submitBtn.classList.add("opacity-75", "cursor-not-allowed");
    btnText.textContent = "Saving Agent...";

    alertBox.classList.add("hidden");

    fetch("agent_save_ajax.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.classList.remove("opacity-75", "cursor-not-allowed");
        btnText.textContent = "Save Agent & Select";

        if (data.success && data.agent) {
            const newAgent = data.agent;

            // Refresh CSRF tokens across all forms on page
            if (data.csrf) {
                document.querySelectorAll('input[name="csrf"]').forEach(f => {
                    f.value = data.csrf;
                });
            }

            // Add new option to all agent_id selects on the page
            document.querySelectorAll('select[name="agent_id"]').forEach(sel => {
                let existingOpt = sel.querySelector(`option[value="${newAgent.id}"]`);
                if (!existingOpt) {
                    const opt = document.createElement("option");
                    opt.value = newAgent.id;
                    opt.textContent = `${newAgent.name} (${newAgent.type_label} • ${newAgent.badge})`;
                    opt.dataset.badge = newAgent.badge;
                    opt.dataset.commissionType = newAgent.commission_type;
                    opt.dataset.rate = newAgent.rate;
                    opt.dataset.calculationBase = newAgent.calculation_base;
                    opt.dataset.slabJson = JSON.stringify(newAgent.slab_json || []);

                    // Insert after __add_new_agent__ option if present, else append
                    const addNewOpt = sel.querySelector('option[value="__add_new_agent__"]');
                    if (addNewOpt && addNewOpt.nextSibling) {
                        sel.insertBefore(opt, addNewOpt.nextSibling);
                    } else {
                        sel.appendChild(opt);
                    }
                }

                // Auto-select newly created agent
                sel.value = newAgent.id;
                sel.dataset.lastSelected = newAgent.id;
            });

            updateAgentCommissionBadgePreview();

            // Show Toast Notification
            const toast = document.getElementById("quickAgentToast");
            const toastMsg = document.getElementById("quickAgentToastMsg");
            if (toast && toastMsg) {
                toastMsg.textContent = data.message || "Agent created successfully";
                toast.classList.remove("opacity-0", "translate-y-10", "pointer-events-none");
                toast.classList.add("opacity-100", "translate-y-0");

                setTimeout(() => {
                    toast.classList.remove("opacity-100", "translate-y-0");
                    toast.classList.add("opacity-0", "translate-y-10", "pointer-events-none");
                }, 3500);
            }

            closeAddAgentModal();
        } else {
            alertBox.className = "rounded-xl p-3 text-xs font-semibold flex items-center space-x-2 bg-rose-50 border border-rose-200 text-rose-800";
            alertBox.innerHTML = `<i class="fa-solid fa-circle-exclamation text-rose-500"></i><span>${data.message || 'Failed to save sales agent'}</span>`;
            alertBox.classList.remove("hidden");
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.classList.remove("opacity-75", "cursor-not-allowed");
        btnText.textContent = "Save Agent & Select";

        alertBox.className = "rounded-xl p-3 text-xs font-semibold flex items-center space-x-2 bg-rose-50 border border-rose-200 text-rose-800";
        alertBox.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-rose-500"></i><span>Network error. Please try again.</span>`;
        alertBox.classList.remove("hidden");
    });
}

function updateAgentCommissionBadgePreview() {
    const sel = document.querySelector('select[name="agent_id"]');
    const badgeContainer = document.getElementById('agent_commission_preview_badge');
    if (!sel || !badgeContainer) return;

    const opt = sel.options[sel.selectedIndex];
    if (!opt || !sel.value || sel.value === '__add_new_agent__') {
        badgeContainer.classList.add('hidden');
        badgeContainer.innerHTML = '';
        return;
    }

    const badge = opt.dataset.badge || 'Commission Configured';
    const commType = opt.dataset.commissionType || 'percentage';
    const rate = parseFloat(opt.dataset.rate) || 0;
    const baseType = opt.dataset.calculationBase || 'total';

    // Calculate live estimated commission on this invoice if total elements exist
    const subtotalText = document.getElementById('live_subtotal')?.textContent || '';
    const grandTotalText = document.getElementById('live_grand_total')?.textContent || '';
    const curr = document.querySelector('select[name="currency"]')?.value || 'AED';

    const parseNum = (str) => {
        const clean = str.replace(/[^0-9\.]/g, '');
        return parseFloat(clean) || 0;
    };

    let baseVal = (baseType === 'subtotal') ? parseNum(subtotalText) : parseNum(grandTotalText);
    let estCommission = 0;

    if (commType === 'percentage') {
        estCommission = (baseVal * rate) / 100;
    } else if (commType === 'slab') {
        try {
            const slabs = JSON.parse(opt.dataset.slabJson || '[]');
            slabs.forEach(t => {
                const from = t.from || 0;
                const to = (t.to !== null && t.to !== undefined && t.to !== '') ? parseFloat(t.to) : null;
                const r = parseFloat(t.rate) || 0;
                if (baseVal > from) {
                    const slice = (to !== null) ? Math.min(baseVal, to) - from : baseVal - from;
                    if (slice > 0) estCommission += (slice * r) / 100;
                }
            });
        } catch(e) {}
    } else if (commType === 'fixed') {
        estCommission = baseVal > 0 ? rate : 0;
    } else if (commType === 'milestone') {
        estCommission = (baseVal * rate) / 100;
    }

    const estText = estCommission > 0 ? ` • Est. Commission: <strong class="text-emerald-700">${estCommission.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${curr}</strong>` : '';

    badgeContainer.classList.remove('hidden');
    badgeContainer.innerHTML = `
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200 shadow-2xs">
            <i class="fa-solid fa-coins text-amber-500"></i>
            <span>${badge}</span>
            ${estText}
        </span>
    `;
}

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('select[name="agent_id"]').forEach(sel => {
        sel.dataset.lastSelected = sel.value;
        sel.addEventListener("focus", () => {
            if (sel.value !== '__add_new_agent__') {
                sel.dataset.lastSelected = sel.value;
            }
        });
        sel.addEventListener("change", () => handleAgentSelectChange(sel));
    });
    updateAgentCommissionBadgePreview();
});
</script>
