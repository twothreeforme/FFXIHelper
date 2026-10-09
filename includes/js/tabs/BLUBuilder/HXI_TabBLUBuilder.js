/*
 * BLU Builder - UI.
 *
 * Contract (same as the Equipsets tabs): module.exports.setLinks(options) wires up the markup from
 * HXI_HTMLTabBLUBuilder.php using the 'HXI_BLUBuilder' mw.config payload. To move this into Equipsets:
 * render HXI_HTMLTabBLUBuilder with $showInputs = false inside a tab div, add the payload, call
 * setLinks({ syncUrl: false, inputs: "external" }) from HXI_Equipsets_TabsController.js, and call
 * setInputs({...}) whenever the gear set's race/jobs/levels change.
 *
 * options.syncUrl - keep the set + inputs in the address bar (standalone page only;
 *                   inside Equipsets the URL belongs to the gear set, so leave it off there).
 * options.inputs  - "form": read player inputs from the Blue Mage window (standalone)
 *                   "external": the host page supplies them through setInputs()
 *
 * Rules live in HXI_BLUBuilderModel.js. Character stats come from the server (api.php?action=blubuilder_stats,
 * the Equipsets stat calculator) so both tools always agree.
 */

var Model = require("./HXI_BLUBuilderModel.js");

const DESCR_PENDING_TITLE = "Blue magic descriptions have not been added to the wiki's data yet (known issue).";
const ATTRS = ["STR", "DEX", "VIT", "AGI", "INT", "MND", "CHR"];
const COMBAT = ["DEF", "ATT", "ACC", "EVA"];

let model = null;
let data = null;
let set = [];
let inputs = null;
let options = { syncUrl: false, inputs: "form" };
let statsRequest = 0;     // id of the newest stats request; older answers are ignored
let statsTimer = null;
let lastStats = null;
let selectedId = null;    // spell shown in Details

/* ---------- small DOM helper ---------- */

function el(tag, attrs, children) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
        if (v === null || v === undefined || v === false) continue;
        if (k === "class") node.className = v;
        else if (k === "text") node.textContent = v;
        else if (k.startsWith("on")) node.addEventListener(k.slice(2), v);
        else node.setAttribute(k, v === true ? "" : v);
    }
    for (const c of [].concat(children || [])) {
        if (c === null || c === undefined || c === false) continue;
        node.appendChild(typeof c === "string" ? document.createTextNode(c) : c);
    }
    return node;
}

function elementBadge(e) {
    const info = data.elements[e];
    if (!info || !info.icon) return null;
    return el("img", { src: info.icon, alt: info.label, title: info.label, class: "HXI_blu_elIcon", width: 16, height: 16,
        // a missing icon file becomes a neutral dot instead of a broken image
        onerror: ev => ev.currentTarget.replaceWith(el("span", { class: "HXI_blu_elIcon HXI_blu_iconMissing", title: info.label })) });
}

function pointsBadge(spell) {
    return el("span", { class: "HXI_blu_pts", title: `${spell.points} set points` }, String(spell.points));
}

function categoryLabel(c) {
    return c === null || c === undefined ? "No trait" : (data.categories[c] ? data.categories[c].label : String(c));
}

function traitName(t) {
    const name = data.traitNames[t.traitid] || categoryLabel(t.category);
    return t.rank > 1 || t.rank === 0 ? `${name} ${Model.roman(t.rank)}` : name;
}

function modLabel(modid) {
    return data.modLabels[modid] || { label: "Mod " + modid, format: "flat" };
}

function jobName(id) {
    return data.jobs[id] || String(id);
}

function meritInfo(key) {
    return data.merits.find(m => m.key === key);
}

/** 7200 -> "2:00:00", 100 -> "1:40" */
function duration(seconds) {
    const h = Math.floor(seconds / 3600), m = Math.floor(seconds % 3600 / 60), s = seconds % 60;
    const pad = n => String(n).padStart(2, "0");
    return h ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`;
}

function horizonFlag(notes) {
    if (!notes || !notes.length) return null;
    const text = "Horizon change: " + notes.join(" ");
    return el("span", { class: "HXI_blu_hz", title: text, "aria-label": text, text: "HZ" });
}

/* ---------- entry ---------- */

module.exports.setLinks = function (opts) {
    options = Object.assign({ syncUrl: false, inputs: "form" }, opts || {});
    data = mw.config.get("HXI_BLUBuilder");
    if (!data || !document.getElementById("HXI_blu")) return;

    model = new Model(data);
    inputs = JSON.parse(JSON.stringify(data.inputs));
    set = data.initial.slice();

    if (options.inputs === "form") setupInputsForm();

    document.getElementById("HXI_blu_search").addEventListener("input", renderSpellList);
    document.getElementById("HXI_blu_traitFilter").addEventListener("change", renderSpellList);
    document.getElementById("HXI_blu_fitsOnly").addEventListener("change", renderSpellList);
    document.getElementById("HXI_blu_share").addEventListener("click", copyShareLink);
    document.getElementById("HXI_blu_reset").addEventListener("click", () => {
        set = [];
        render();
    });

    render();

    // A shared link can carry spells the game wouldn't set (hand edited, or a lower level) - say so up front
    const broken = model.status(set, inputs).filter(r => r.state !== Model.OK);
    if (broken.length) notifyNotSet(broken);
};

/**
 * Player inputs from outside (Equipsets once merged, or the Blue Mage form here). Partial objects are merged:
 * setInputs({ mlvl: 50 }) leaves the rest as it is.
 */
module.exports.setInputs = function (partial) {
    if (!inputs) return;
    const before = model.status(set, inputs).filter(r => r.state !== Model.OK).map(r => r.spell.id);
    const next = Object.assign({}, inputs, partial || {});
    next.merits = Object.assign({}, inputs.merits, (partial || {}).merits || {});
    inputs = next;
    render();
    // a lower level / other job can unset spells: tell the user which ones, once
    const now = model.status(set, inputs).filter(r => r.state !== Model.OK);
    const fresh = now.filter(r => !before.includes(r.spell.id));
    if (fresh.length) notifyNotSet(fresh);
};

module.exports.getInputs = function () {
    return JSON.parse(JSON.stringify(inputs));
};

/* ---------- Blue Mage inputs form (standalone page) ---------- */

function readInputsForm() {
    const $ = id => document.getElementById(id);
    return {
        race: parseInt($("HXI_blu_selectRace").value, 10),
        mjob: parseInt($("HXI_blu_selectMJob").value, 10), mlvl: parseInt($("HXI_blu_selectMLevel").value, 10),
        sjob: parseInt($("HXI_blu_selectSJob").value, 10), slvl: parseInt($("HXI_blu_selectSLevel").value, 10),
        maxSub: $("HXI_blu_checkboxMaxSub").checked,
        merits: Object.fromEntries(data.merits.map(m => [m.key, parseInt($("HXI_blu_merit_" + m.key).value, 10)])),
    };
}

function setupInputsForm() {
    const $ = id => document.getElementById(id);
    const update = () => module.exports.setInputs(readInputsForm());

    // Same main/sub level behaviour as Equipsets (HXI_TabEquipsets.js)
    const mlvl = $("HXI_blu_selectMLevel");
    const slvl = $("HXI_blu_selectSLevel");
    const maxSub = $("HXI_blu_checkboxMaxSub");
    const maxSubLevel = () => (mlvl.value > 1) ? Math.floor(mlvl.value / 2) : 1;
    mlvl.addEventListener("change", () => {
        if (maxSub.checked) slvl.value = maxSubLevel();
        update();
    });
    slvl.addEventListener("change", () => { maxSub.checked = false; update(); });
    maxSub.addEventListener("change", () => {
        if (maxSub.checked) slvl.value = maxSubLevel();
        update();
    });
    for (const id of ["HXI_blu_selectRace", "HXI_blu_selectMJob", "HXI_blu_selectSJob"]) {
        $(id).addEventListener("change", update);
    }

    // a merit group holds 10 upgrades: a pick past that is pulled back to what's left, with a message
    for (const m of data.merits) {
        const select = $("HXI_blu_merit_" + m.key);
        select.addEventListener("change", () => {
            const wanted = parseInt(select.value, 10);
            const max = model.meritMax(Object.assign({}, inputs.merits, { [m.key]: wanted }), m.key);
            if (wanted > max) {
                select.value = max;
                mw.notify(`Group ${m.group} merits: ${data.meritLimits.group} upgrades in total. ${m.label} set to ${max}.`,
                    { type: "warn", autoHide: true, tag: "HXI_blu_meritCap" });
            }
            update();
        });
    }
}

/* ---------- set changes ---------- */

function toggle(spell) {
    selectedId = spell.id;
    if (set.includes(spell.id)) {
        set = set.filter(id => id !== spell.id);
        render();
        return;
    }
    const check = model.canAdd(set, spell, inputs);
    if (!check.ok) {
        mw.notify(`${spell.name} can't be set: ${check.reason}.`, { type: "error", autoHide: true, tag: "HXI_blu_add" });
        renderDetails();
        return;
    }
    set = set.concat(spell.id);
    render();
}

function remove(id) {
    set = set.filter(x => x !== id);
    render();
}

const STATE_TEXT = {
    level: s => `needs BLU Lv${s.level}`,
    points: () => "not enough blue magic points",
    slots: () => "no free slot",
    noBlu: () => "Blue Mage is not your main or sub job",
};

function notifyNotSet(rows) {
    const text = rows.map(r => `${r.spell.name} (${STATE_TEXT[r.state](r.spell)})`).join(", ");
    mw.notify("Not set in game: " + text, { type: "error", autoHide: true, tag: "HXI_blu_notSet" });
}

/* ---------- render ---------- */

function render() {
    renderInputsNote();
    renderSet();
    renderSpellList();
    renderTraits();
    renderAbilities();
    renderDetails();
    requestStats();
    if (options.syncUrl) syncUrl();
}

function renderInputsNote() {
    const note = document.getElementById("HXI_blu_bluNote");
    if (note) note.hidden = model.bluLevel(inputs) > 0;
    const meritNote = document.getElementById("HXI_blu_meritNote");
    if (!meritNote) return; // inputs form not rendered (Equipsets)

    // merits only count for BLU main at Lv75 - keep the values, but show they don't apply
    const on = model.meritsActive(inputs);
    meritNote.hidden = on;
    for (const table of document.querySelectorAll(".HXI_blu_meritTable")) table.classList.toggle("HXI_blu_inactive", !on);
    for (const m of data.merits) {
        const count = inputs.merits[m.key] || 0;
        const hint = document.getElementById("HXI_blu_meritFx_" + m.key);
        hint.textContent = count ? model.meritEffect(m.key, count) : "Per upgrade: " + m.effect.replace("{v}", String(m.per));
        hint.title = "Source: " + m.source;
    }
    for (const g of [1, 2]) {
        document.getElementById("HXI_blu_meritCount" + g).textContent = `${model.meritGroupUsed(inputs.merits, g)}/${data.meritLimits.group}`;
    }
}

function renderSet() {
    const rows = model.status(set, inputs);
    const slots = model.slots(inputs);
    const max = model.maxPoints(inputs);
    const used = model.pointsUsed(set, inputs);
    const active = rows.filter(r => r.state === Model.OK).length;

    document.getElementById("HXI_blu_count").textContent = slots ? `${active}/${slots}` : "";

    const pct = max ? Math.min(100, Math.round(used / max * 100)) : 0;
    document.getElementById("HXI_blu_points").replaceChildren(
        el("div", { class: "HXI_blu_meterHead" }, [
            el("span", { class: "HXI_blu_meterLabel", text: "Blue magic points" }),
            el("span", { class: "HXI_blu_meterNum", text: `${used}/${max}` }),
        ]),
        el("div", { class: "HXI_blu_meter", role: "meter", "aria-label": "Blue magic points used",
            "aria-valuemin": "0", "aria-valuemax": String(max), "aria-valuenow": String(used) },
            el("span", { class: "HXI_blu_meterFill", style: `width: ${pct}%` })),
    );

    // warning for spells the game wouldn't set
    const bad = rows.filter(r => r.state !== Model.OK);
    const box = document.getElementById("HXI_blu_warning");
    box.hidden = bad.length === 0;
    if (bad.length) {
        box.replaceChildren(el("strong", { text: "Not set in game: " }),
            bad.map(r => `${r.spell.name} (${STATE_TEXT[r.state](r.spell)})`).join(", "), el("br"),
            "They are highlighted below and don't count toward traits or stats.");
    }

    const items = rows.map((r, i) => {
        const s = r.spell;
        const ok = r.state === Model.OK;
        return el("li", { class: "HXI_blu_slot" + (ok ? "" : " HXI_blu_slotBad") + (s.id === selectedId ? " HXI_blu_selected" : "") }, [
            el("button", { type: "button", class: "HXI_blu_slotMain", title: ok ? s.name : `${s.name}: ${STATE_TEXT[r.state](s)}`,
                onclick: () => { selectedId = s.id; renderDetails(true); renderSet(); } }, [
                el("span", { class: "HXI_blu_slotNum", text: String(i + 1) }),
                elementBadge(s.element),
                el("span", { class: "HXI_blu_slotText" }, [
                    el("span", { class: "HXI_blu_slotName", text: s.name }),
                    s.category ? el("span", { class: "HXI_blu_slotTrait", text: categoryLabel(s.category) }) : null,
                ]),
                pointsBadge(s),
            ]),
            el("button", { type: "button", class: "HXI_blu_remove", "aria-label": `Remove ${s.name}`, title: "Remove", text: "×",
                onclick: () => remove(s.id) }),
        ]);
    });
    // free slots (spells that aren't set in game don't use one)
    for (let i = active; i < slots; i++) {
        items.push(el("li", { class: "HXI_blu_slot HXI_blu_slotEmpty", "aria-label": "Empty slot" },
            el("span", { class: "HXI_blu_slotNum", text: "–" })));
    }
    if (!items.length) items.push(el("li", { class: "HXI_blu_note", text: "Set Blue Mage as your main or sub job to set spells." }));
    document.getElementById("HXI_blu_slots").replaceChildren(...items);
}

function spellSummary(s) {
    const parts = [];
    if (s.category !== null) parts.push(`${categoryLabel(s.category)} ${s.weight * Model.TRAIT_POINT_SCALE}`);
    if (s.mods.length) parts.push(s.mods.map(m => `${m.label}${Model.formatValue(m.format, m.value)}`).join(" "));
    return parts.join(" · ") || "No trait or stats";
}

function renderSpellList() {
    const query = document.getElementById("HXI_blu_search").value.trim().toLowerCase();
    const filter = document.getElementById("HXI_blu_traitFilter").value;
    const fitsOnly = document.getElementById("HXI_blu_fitsOnly").checked;
    const level = model.bluLevel(inputs);

    const rows = [];
    for (const s of data.spells) {
        if (filter === "0" && s.category !== null) continue;
        if (filter !== "" && filter !== "0" && String(s.category) !== filter) continue;
        if (query && !(s.name + " " + spellSummary(s)).toLowerCase().includes(query)) continue;
        const isSet = set.includes(s.id);
        const check = isSet ? { ok: true, reason: "" } : model.canAdd(set, s, inputs);
        if (fitsOnly && !isSet && !check.ok) continue;
        const locked = s.level > level;

        rows.push(el("div", { class: "HXI_blu_spell" + (isSet ? " HXI_blu_spellSet" : "") + (check.ok ? "" : " HXI_blu_spellNo")
                + (s.id === selectedId ? " HXI_blu_selected" : "") }, [
            el("button", { type: "button", class: "HXI_blu_spellMain", "aria-pressed": isSet ? "true" : "false",
                "aria-label": `${s.name}, level ${s.level}, ${s.points} points${isSet ? ", set - remove" : check.ok ? " - set" : ": " + check.reason}`,
                onclick: () => toggle(s) }, [
                el("span", { class: "HXI_blu_lv" + (locked ? " HXI_blu_lvLocked" : ""), text: String(s.level) }),
                el("span", { class: "HXI_blu_spellText" }, [
                    el("span", { class: "HXI_blu_spellName" }, [s.name, horizonFlag(s.horizon)]),
                    el("span", { class: "HXI_blu_spellSub", text: check.ok ? spellSummary(s) : check.reason }),
                ]),
                elementBadge(s.element),
                pointsBadge(s),
                el("span", { class: "HXI_blu_mark", "aria-hidden": "true", text: isSet ? "✓" : "+" }),
            ]),
            el("button", { type: "button", class: "HXI_blu_info", "aria-label": `${s.name} details`, text: "i",
                onclick: () => { selectedId = s.id; renderDetails(true); renderSpellList(); renderSet(); } }),
        ]));
    }
    document.getElementById("HXI_blu_spellCount").textContent = `${rows.length}/${data.spells.length}`;
    if (!rows.length) rows.push(el("p", { class: "HXI_blu_note", text: "No spells match." }));
    document.getElementById("HXI_blu_spellList").replaceChildren(...rows);
}

function traitValueText(rows) {
    return rows.map(t => {
        const m = modLabel(t.modid);
        // modifiers without a display label (e.g. VIRUSRES) read better under the trait's own name
        return `${m.label || traitName(Object.assign({}, t, { rank: 1 }))} ${Model.formatValue(m.format, t.value)}`;
    }).join(", ");
}

function renderTraits() {
    const summary = model.traitSummary(set, inputs);
    const body = [];

    body.push(el("h3", { class: "HXI_blu_subtitle", text: "From blue magic" }));
    if (!summary.categories.length) {
        body.push(el("p", { class: "HXI_blu_note", text: model.bluLevel(inputs) > 0
            ? `Set spells to collect trait points (${2 * Model.TRAIT_POINT_SCALE} per tier).` : "Blue Mage is not your main or sub job." }));
    }
    for (const c of summary.categories) {
        const pts = c.points * Model.TRAIT_POINT_SCALE;
        const top = c.reached.length ? c.reached.reduce((a, b) => b.rank > a.rank ? b : a) : null;
        const pips = c.thresholds.map(p => el("span", { class: "HXI_blu_tierPip" + (c.points >= p ? " HXI_blu_tierOn" : ""),
            title: `${p * Model.TRAIT_POINT_SCALE} points` }));

        let status;
        if (c.pending) status = el("span", { class: "HXI_blu_traitState HXI_blu_pending", title: c.pending }, ["Values not published ", el("span", { class: "HXI_blu_flag", text: "⚑" })]);
        else if (!top) status = el("span", { class: "HXI_blu_traitState", text: c.next ? `${c.next * Model.TRAIT_POINT_SCALE - pts} more points` : "" });
        else if (c.beatenBy) status = el("span", { class: "HXI_blu_traitState HXI_blu_muted", text: `${jobName(c.beatenBy.job)} trait is as strong or stronger` });
        else status = el("span", { class: "HXI_blu_traitState HXI_blu_good", text: traitValueText(c.kept) });

        body.push(el("div", { class: "HXI_blu_trait" + (top && !c.beatenBy ? " HXI_blu_traitOn" : "") }, [
            el("div", { class: "HXI_blu_traitHead" }, [
                el("span", { class: "HXI_blu_traitName", text: top ? traitName(top) : c.label }),
                el("span", { class: "HXI_blu_traitPts", text: c.next ? `${pts}/${c.next * Model.TRAIT_POINT_SCALE}` : String(pts) }),
            ]),
            pips.length ? el("div", { class: "HXI_blu_tierPips" }, pips) : null,
            status,
        ]));
    }

    const own = summary.traits.filter(t => t.source !== "blu" && t.modid);
    body.push(el("h3", { class: "HXI_blu_subtitle", text: "From your jobs" }));
    if (!own.length) body.push(el("p", { class: "HXI_blu_note", text: "None at these levels." }));
    else {
        body.push(el("ul", { class: "HXI_blu_fx" }, own.map(t => el("li", {}, [
            el("span", { class: "HXI_blu_fxLabel" }, [traitName(t), el("span", { class: "HXI_blu_tag", text: jobName(t.source) })]),
            el("span", { class: "HXI_blu_fxVal", text: traitValueText([t]) }),
        ]))));
    }
    document.getElementById("HXI_blu_traitBody").replaceChildren(...body);
}

function renderAbilities() {
    const main = inputs.mjob === data.blu;
    const level = model.bluLevel(inputs);
    const items = data.abilities.map(a => {
        let state, ok = false;
        if (!level) state = "Not BLU";
        else if ((a.sp || a.merit) && !main) state = "Main job only";
        else if (a.level > level) state = `Lv${a.level}`;
        else if (a.merit && !model.merit(inputs, a.merit)) state = "Needs merit";
        else { state = "Available"; ok = true; }

        // merits that change this ability (only while they apply: BLU main Lv75)
        const cut = a.recastMerit ? model.merit(inputs, a.recastMerit) * meritInfo(a.recastMerit).per : 0;
        const extras = [];
        if (cut) extras.push(`${meritInfo(a.recastMerit).label}: -${cut}s`);
        if (a.tpMerit && model.merit(inputs, a.tpMerit)) extras.push(`${meritInfo(a.tpMerit).label}: ${model.meritEffect(a.tpMerit, model.merit(inputs, a.tpMerit))}`);
        if (a.merit && model.merit(inputs, a.merit) > 1) extras.push(model.meritEffect(a.merit, model.merit(inputs, a.merit)).split("; ").pop());

        return el("li", { class: ok ? null : "HXI_blu_locked" }, [
            el("div", { class: "HXI_blu_abilityHead" }, [
                el("span", { class: "HXI_blu_abilityName" }, [a.name, a.sp ? el("span", { class: "HXI_blu_tag", text: "SP" }) : null,
                    a.horizon ? horizonFlag([a.horizon]) : null]),
                el("span", { class: "HXI_blu_abilityState" + (ok ? " HXI_blu_good" : ""), text: state }),
            ]),
            el("span", { class: "HXI_blu_abilitySub", text: `Lv${a.level}${a.merit ? " merit" : ""} · recast ${duration(a.recast - cut)} · ${a.descr}` }),
            extras.length ? el("span", { class: "HXI_blu_abilityMerit", text: extras.join(" · ") }) : null,
        ]);
    });
    document.getElementById("HXI_blu_abilityBody").replaceChildren(el("ul", { class: "HXI_blu_abilities" }, items));
}

/* ---------- stats (server) ---------- */

function requestStats() {
    // inputs/sets change in bursts (typing in search doesn't call this); wait a moment, then ask once
    clearTimeout(statsTimer);
    statsTimer = setTimeout(fetchStats, 150);
    document.getElementById("HXI_blu_statsBody").classList.add("HXI_blu_loading");
}

function fetchStats() {
    const id = ++statsRequest;
    new mw.Api().get({
        action: "blubuilder_stats", race: inputs.race, mjob: inputs.mjob, mlvl: inputs.mlvl, sjob: inputs.sjob,
        slvl: inputs.slvl, bmerit: meritQuery(), spells: Model.encode(set),
    }).done(d => {
        if (id !== statsRequest) return; // a newer request is on its way
        lastStats = d.blubuilder;
        renderStats();
    }).fail(() => {
        if (id !== statsRequest) return;
        document.getElementById("HXI_blu_statsBody").classList.remove("HXI_blu_loading");
        mw.notify("Couldn't calculate stats. Please try again or report on our Discord.", { type: "error", autoHide: true, tag: "HXI_blu_stats" });
    });
}

function delta(base, now) {
    const d = now - base;
    if (!d) return null;
    return el("span", { class: "HXI_blu_delta" + (d < 0 ? " HXI_blu_neg" : ""), text: (d > 0 ? "+" : "") + d });
}

function statCell(key, base, now) {
    return el("div", { class: "HXI_blu_stat" + (now !== base ? " HXI_blu_changed" : "") }, [
        el("span", { class: "HXI_blu_statL", text: key }),
        el("span", { class: "HXI_blu_statV", text: String(now) }),
        delta(base, now),
    ]);
}

function renderStats() {
    const body = document.getElementById("HXI_blu_statsBody");
    body.classList.remove("HXI_blu_loading");
    if (!lastStats) return;
    const b = lastStats.base, s = lastStats.set;
    document.getElementById("HXI_blu_statsLevel").textContent =
        `${jobName(inputs.mjob)}${inputs.mlvl}` + (inputs.sjob ? `/${jobName(inputs.sjob)}${inputs.slvl}` : "");

    const vitals = el("div", { class: "HXI_blu_statGroup HXI_blu_vitals" }, [
        el("div", { class: "HXI_blu_stat HXI_blu_hp" }, [el("span", { class: "HXI_blu_statL", text: "HP" }), el("span", { class: "HXI_blu_statV", text: String(s.HP) }), delta(b.HP, s.HP)]),
        el("div", { class: "HXI_blu_stat HXI_blu_mp" }, [el("span", { class: "HXI_blu_statL", text: "MP" }), el("span", { class: "HXI_blu_statV", text: String(s.MP) }), delta(b.MP, s.MP)]),
    ]);
    const attrs = el("div", { class: "HXI_blu_statGroup HXI_blu_attrs" }, ATTRS.map(k => statCell(k, b[k], s[k])));
    const combat = el("div", { class: "HXI_blu_statGroup HXI_blu_attrs" }, COMBAT.map(k => statCell(k, b[k], s[k])));

    // other modifiers: only the ones something sets (HP/MP/attributes are shown above; the calculator's DEF
    // modifier also holds the level-based DEF, so only the DEF total above is meaningful)
    const shown = new Set([1, 2, 5, 8, 9, 10, 11, 12, 13, 14, 1095, 1096]);
    const other = Object.keys(s.mods).map(Number).filter(id => !shown.has(id) && (s.mods[id] || b.mods[id]));
    const rows = other.map(id => {
        const m = modLabel(id);
        return el("li", { class: s.mods[id] !== b.mods[id] ? "HXI_blu_changed" : null }, [
            el("span", { class: "HXI_blu_fxLabel", text: m.label }),
            el("span", { class: "HXI_blu_fxVal" }, [Model.formatValue(m.format, s.mods[id]), delta(b.mods[id], s.mods[id])]),
        ]);
    });

    body.replaceChildren(vitals, attrs, combat,
        el("h3", { class: "HXI_blu_subtitle", text: "Other bonuses" }),
        rows.length ? el("ul", { class: "HXI_blu_fx" }, rows) : el("p", { class: "HXI_blu_note", text: "None." }));
}

/* ---------- details ---------- */

function renderDetails(scroll) {
    const target = document.getElementById("HXI_blu_detailBody");
    const s = model.spell(selectedId);
    if (!s) return;

    const facts = [
        ["Level", String(s.level)],
        ["Set points", String(s.points)],
        ["Type", s.physical ? "Physical" : "Magical"],
        ["Element", data.elements[s.element] ? data.elements[s.element].label : "None"],
        ["MP", String(s.mp)],
        ["Cast / Recast", `${s.cast}s / ${s.recast}s`],
        ["Trait", s.category !== null ? `${categoryLabel(s.category)} (${s.weight * Model.TRAIT_POINT_SCALE} points)` : "None"],
    ];
    if (s.skillchains.length) facts.push(["Skillchain", s.skillchains.join(" / ")]);
    facts.push(["Stats", s.mods.length ? s.mods.map(m => `${m.label} ${Model.formatValue(m.format, m.value)}`).join(", ") : "None"]);

    // merits that change this spell when cast (BLU main Lv75 only)
    const meritFx = [s.physical ? "physicalPotency" : "magicalAccuracy", "monsterCorrelation"]
        .concat(s.physical && model.bluLevel(inputs) >= 40 ? ["enchainment"] : [])
        .filter(k => model.merit(inputs, k))
        .map(k => model.meritEffect(k, model.merit(inputs, k)));
    if (meritFx.length) facts.push(["Merits", meritFx.join("; ")]);

    const isSet = set.includes(s.id);
    const check = isSet ? null : model.canAdd(set, s, inputs);
    const body = [
        el("div", { class: "HXI_blu_detailHead" }, [
            elementBadge(s.element),
            el("span", { class: "HXI_blu_detailName", text: s.name }),
            pointsBadge(s),
        ]),
        s.descrPending
            ? el("p", { class: "HXI_blu_descr HXI_blu_descrPending" }, [s.descr + " ",
                el("span", { class: "HXI_blu_flag", title: DESCR_PENDING_TITLE, "aria-label": DESCR_PENDING_TITLE, text: "⚑" })])
            : el("p", { class: "HXI_blu_descr", text: s.descr }),
        el("dl", { class: "HXI_blu_facts" }, facts.flatMap(([k, v]) => [el("dt", { text: k }), el("dd", { text: v })])),
        s.horizon.length ? el("p", { class: "HXI_blu_hzNote" }, [el("strong", { text: "Horizon: " }), s.horizon.join(" ")]) : null,
        check && !check.ok ? el("p", { class: "HXI_blu_warning", role: "alert", text: `Can't set: ${check.reason}.` }) : null,
        el("div", { class: "HXI_blu_actions" }, el("button", { type: "button", class: "HXI_blu_btn" + (isSet ? "" : " HXI_blu_btnPrimary"),
            text: isSet ? "Remove from set" : "Set spell", onclick: () => toggle(s) })),
    ];
    target.replaceChildren(...body.filter(Boolean)); // replaceChildren() would print "null"
    if (scroll) target.closest(".HXI_blu_details").scrollIntoView({ behavior: "smooth", block: "nearest" });
}

/* ---------- share ---------- */

/** Set + inputs as query params (HXI_BLUBuild::fromRequest / HXI_BLUBuilderInputs::fromRequest). */
function query() {
    let q = `&spells=${Model.encode(set)}`;
    if (options.inputs !== "form") return q;
    q += `&race=${inputs.race}&mjob=${inputs.mjob}&mlvl=${inputs.mlvl}&sjob=${inputs.sjob}&slvl=${inputs.slvl}`;
    if (data.merits.some(m => inputs.merits[m.key])) q += "&bmerit=" + meritQuery();
    return q;
}

/** Merit upgrades as a dash list in HXI_BLUBuilderInputs::MERITS order. */
function meritQuery() {
    return data.merits.map(m => inputs.merits[m.key] || 0).join("-");
}

function syncUrl() {
    const url = mw.config.get("wgScriptPath") + "/index.php?title=Special:BLUBuilder" + query();
    history.replaceState(null, "", url);
}

function copyShareLink() {
    const url = mw.config.get("wgServer") + mw.config.get("wgScriptPath") + "/index.php?title=Special:BLUBuilder" + query();
    navigator.clipboard.writeText(url).then(function () {
        mw.notify("Copied to Clipboard !", { autoHide: true, type: "warn" });
    }, function () {
        mw.notify("Error copying to clipboard. Please report on our Discord.", { autoHide: true, type: "error" });
    });
}
