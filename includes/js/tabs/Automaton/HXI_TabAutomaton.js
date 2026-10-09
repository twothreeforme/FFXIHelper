/*
 * Automaton Builder - UI.
 *
 * Contract (same as the Equipsets tabs): module.exports.setLinks(options) wires up the markup from
 * HXI_HTMLTabAutomaton.php using the 'HXI_Automaton' mw.config payload. To move this into
 * Equipsets: render HXI_HTMLTabAutomaton with $showInputs = false inside a tab div, add the payload,
 * call setLinks({ syncUrl: false, inputs: "external" }) from HXI_Equipsets_TabsController.js, and call
 * setInputs({...}) whenever the gear set's jobs/levels/merits/gear change.
 *
 * options.syncUrl - keep the build + inputs in the address bar (standalone page only;
 *                   inside Equipsets the URL belongs to the gear set, so leave it off there).
 * options.inputs  - "form": read player inputs from the Puppetmaster table (standalone)
 *                   "external": the host page supplies them through setInputs()
 */

var Model = require("./HXI_AutomatonModel.js");
var Stats = require("./HXI_AutomatonStats.js");

const DESCR_PENDING_TITLE = "Automaton item descriptions have not been added to the wiki's item data yet (known issue).";
const SKILLS = ["melee", "ranged", "magic"];
const GEAR = ["elemCapacity", "melee", "ranged", "magic", "level"];

let model = null;
let stats = null;
let data = null;
let state = null;
let inputs = null;
let maneuvers = [null, null, null];
let pickerSlot = null;
let pickerElement = null; // element filter, null = all
let options = { syncUrl: false, inputs: "form" };

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

function icon(src, alt, cls) {
    // Some item icons aren't uploaded to every wiki yet - show a neutral tile instead of a broken image
    return el("img", { src: src, alt: alt || "", class: cls || "HXI_ab_icon", loading: "lazy", width: 32, height: 32,
        onerror: ev => ev.currentTarget.replaceWith(el("span", { class: "HXI_ab_icon HXI_ab_iconMissing", "aria-hidden": "true" })) });
}

function elementIcon(e) {
    const info = data.elements[e];
    return el("img", { src: info.icon, alt: info.label, title: info.label, class: "HXI_ab_elIcon", width: 16, height: 16 });
}

function costBadge(a) {
    if (a.element === null) return null;
    return el("span", { class: "HXI_ab_cost HXI_ab_el" + a.element, title: `${data.elements[a.element].label} ${a.cost}` },
        [elementIcon(a.element), String(a.cost)]);
}

/* ---------- entry ---------- */

module.exports.setLinks = function (opts) {
    options = Object.assign({ syncUrl: false, inputs: "form" }, opts || {});
    data = mw.config.get("HXI_Automaton");
    if (!data || !document.getElementById("HXI_ab")) return;

    model = new Model(data);
    stats = new Stats(data);
    inputs = JSON.parse(JSON.stringify(data.inputs));
    state = { head: data.initial.head, frame: data.initial.frame, attachments: data.initial.attachments.slice(), capacityBonus: inputs.gear.elemCapacity };

    if (options.inputs === "form") setupInputsForm();

    const headSelect = document.getElementById("HXI_ab_head");
    const frameSelect = document.getElementById("HXI_ab_frame");
    headSelect.addEventListener("change", () => changePart("head", parseInt(headSelect.value, 10)));
    frameSelect.addEventListener("change", () => changePart("frame", parseInt(frameSelect.value, 10)));

    document.getElementById("HXI_ab_headIcon").addEventListener("click", () => showDetails(model.headsById[state.head], "head"));
    document.getElementById("HXI_ab_frameIcon").addEventListener("click", () => showDetails(model.framesById[state.frame], "frame"));

    for (const select of document.querySelectorAll(".HXI_ab_maneuverSelect")) {
        for (const e of data.elements) select.appendChild(el("option", { value: e.id, text: e.label }));
        select.addEventListener("change", () => {
            const i = parseInt(select.dataset.maneuver, 10) - 1;
            maneuvers[i] = select.value === "" ? null : parseInt(select.value, 10);
            renderEffects();
        });
    }

    document.getElementById("HXI_ab_share").addEventListener("click", copyShareLink);
    document.getElementById("HXI_ab_reset").addEventListener("click", () => {
        state.attachments = state.attachments.map(() => 0);
        render();
    });

    setupPicker();
    render();

    // A shared link can carry a build that doesn't fit (e.g. hand edited) - say so up front
    if (model.overCapacity(state).length) notifyOver(model.overCapacity(state));
};

/**
 * Player inputs from outside (Equipsets once merged, or the Puppetmaster form here). Partial objects are
 * merged: setInputs({ mjob: 18, mlvl: 75 }) leaves skills/merits/gear as they are.
 */
module.exports.setInputs = function (partial) {
    if (!inputs) return;
    const next = Object.assign({}, inputs, partial || {});
    next.skills = Object.assign({}, inputs.skills, (partial || {}).skills || {});
    next.gear = Object.assign({}, inputs.gear, (partial || {}).gear || {});
    inputs = next;
    // the gear capacity bonus changes the build's limits, so treat it like a head/frame change
    changeCapacity(() => { state.capacityBonus = inputs.gear.elemCapacity || 0; });
};

module.exports.getInputs = function () {
    return JSON.parse(JSON.stringify(inputs));
};

/* ---------- state changes ---------- */

/** Apply a change that can alter capacity; warn only when it made the build go (further) over. */
function changeCapacity(apply) {
    const before = model.overCapacity(state).map(o => o.element);
    apply();
    render();
    const over = model.overCapacity(state);
    if (over.some(o => !before.includes(o.element))) notifyOver(over);
}

function changePart(part, id) {
    changeCapacity(() => { state[part] = id; });
    const item = part === "head" ? model.headsById[id] : model.framesById[id];
    showDetails(item, part);
}

/* ---------- Puppetmaster inputs form (standalone page) ---------- */

/** Highest value a gear bonus can reach with gear at/below the level cap (HXI_AutomatonData::gearCaps()). */
function gearCap(key) {
    return (data.gearCaps && data.gearCaps[key]) ? data.gearCaps[key].max : 0;
}

function intValue(input, min, max) {
    const v = parseInt(input.value, 10);
    return isNaN(v) ? min : Math.max(min, Math.min(max, v));
}

function readInputsForm() {
    const $ = id => document.getElementById(id);
    const gear = {};
    for (const k of GEAR) gear[k] = intValue($("HXI_ab_gear_" + k), 0, gearCap(k));
    const form = {
        mjob: parseInt($("HXI_ab_selectMJob").value, 10), mlvl: parseInt($("HXI_ab_selectMLevel").value, 10),
        sjob: parseInt($("HXI_ab_selectSJob").value, 10), slvl: parseInt($("HXI_ab_selectSLevel").value, 10),
        maxSub: $("HXI_ab_checkboxMaxSub").checked,
        skills: {}, merits: parseInt($("HXI_ab_merits").value, 10), gear: gear,
    };
    // your skill can't pass rank A+ at this level; no PUP (max 0) = leave the typed values alone
    const max = stats.playerSkillMax(form) || 999;
    for (const k of SKILLS) form.skills[k] = $("HXI_ab_skillCap_" + k).checked ? null : intValue($("HXI_ab_skill_" + k), 0, max);
    return form;
}

function setupInputsForm() {
    const $ = id => document.getElementById(id);
    const update = () => module.exports.setInputs(readInputsForm());

    // Same main/sub level behaviour as Equipsets (HXI_TabEquipsets.js)
    const mlvl = $("HXI_ab_selectMLevel");
    const slvl = $("HXI_ab_selectSLevel");
    const maxSub = $("HXI_ab_checkboxMaxSub");
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
    $("HXI_ab_selectMJob").addEventListener("change", update);
    $("HXI_ab_selectSJob").addEventListener("change", update);
    $("HXI_ab_merits").addEventListener("change", update);

    for (const k of SKILLS) {
        const box = $("HXI_ab_skillCap_" + k);
        const num = $("HXI_ab_skill_" + k);
        box.addEventListener("change", () => {
            num.disabled = box.checked;
            // start from the current cap so unticking doesn't drop the skill to 0
            if (!box.checked && num.value === "") {
                const s = stats.compute(state, inputs);
                const row = s ? s.skills.find(x => x.key === k) : null;
                num.value = row ? row.cap - row.bonus : 0;
            }
            update();
        });
        num.addEventListener("input", update);
        // when the box loses focus, show the clamped value too (typing 999 at Lv75 becomes 276)
        num.addEventListener("change", () => {
            const max = stats.playerSkillMax(inputs);
            const v = intValue(num, 0, max || 999);
            if (String(v) !== num.value) {
                num.value = v;
                if (max && v === max) notifySkillMax([k], max);
            }
        });
    }
    for (const k of GEAR) {
        const num = $("HXI_ab_gear_" + k);
        num.addEventListener("input", update); // readInputsForm() already clamps what's used
        // when the box loses focus, show the clamped value too (typing 9 into a +2 max box becomes 2)
        num.addEventListener("change", () => {
            const v = intValue(num, 0, gearCap(k));
            if (String(v) !== num.value) {
                num.value = v;
                mw.notify(`${num.closest("tr").querySelector("label").textContent}: max +${gearCap(k)} with gear at Lv${data.maxLevel} or below.`,
                    { type: "warn", autoHide: true, tag: "HXI_ab_gearCap" });
            }
        });
    }
}

function equip(slot, id) {
    state.attachments[slot] = id;
    render();
    if (id) showDetails(model.attachment(id), "attachment");
}

/* ---------- render ---------- */

function render() {
    renderParts();
    renderCapacity();
    renderSlots();
    renderWarning();
    renderStats();
    renderEffects();
    if (options.syncUrl) syncUrl();
}

function renderParts() {
    const head = model.headsById[state.head];   // undefined when "None" (id 0) is chosen
    const frame = model.framesById[state.frame];
    const partIcon = part => part ? icon(part.icon, part.name) : el("span", { class: "HXI_ab_icon HXI_ab_iconNone", "aria-hidden": "true" });
    document.getElementById("HXI_ab_head").value = state.head;
    document.getElementById("HXI_ab_frame").value = state.frame;
    document.getElementById("HXI_ab_headIcon").replaceChildren(partIcon(head));
    document.getElementById("HXI_ab_frameIcon").replaceChildren(partIcon(frame));
}

function renderCapacity() {
    const max = model.capacityMax(state);
    const used = model.capacityUsed(state);
    const rows = data.elements.map(e => {
        const over = used[e.id] > max[e.id];
        const pips = [];
        for (let p = 0; p < Math.max(max[e.id], used[e.id]); p++) {
            let cls = "HXI_ab_pip";
            if (p < used[e.id]) cls += p < max[e.id] ? " HXI_ab_pipUsed" : " HXI_ab_pipOver";
            pips.push(el("span", { class: cls }));
        }
        return el("div", { class: "HXI_ab_capRow HXI_ab_el" + e.id + (over ? " HXI_ab_capOver" : ""), title: `${e.label}: ${used[e.id]} of ${max[e.id]} used` }, [
            elementIcon(e.id),
            el("span", { class: "HXI_ab_pips" }, pips),
            el("span", { class: "HXI_ab_capNum", text: `${used[e.id]}/${max[e.id]}` }),
        ]);
    });
    document.getElementById("HXI_ab_capacity").replaceChildren(...rows);
}

function renderSlots() {
    const overElements = model.overCapacity(state).map(o => o.element);
    const filled = state.attachments.filter(id => id).length;
    document.getElementById("HXI_ab_count").textContent = `${filled}/${data.slots}`;

    const slots = state.attachments.map((id, slot) => {
        const a = model.attachment(id);
        if (!a) {
            return el("button", { type: "button", class: "HXI_ab_slot HXI_ab_slotEmpty", "aria-label": `Slot ${slot + 1}: empty - add attachment`,
                onclick: () => openPicker(slot) }, [el("span", { class: "HXI_ab_plus", text: "+" })]);
        }
        const over = overElements.includes(a.element);
        return el("div", { class: "HXI_ab_slotWrap" }, [
            el("button", { type: "button", class: "HXI_ab_slot" + (over ? " HXI_ab_slotOver" : ""), title: a.name,
                "aria-label": `Slot ${slot + 1}: ${a.name}${over ? " (over capacity)" : ""} - change`,
                onclick: () => openPicker(slot) }, [
                icon(a.icon, ""),
                el("span", { class: "HXI_ab_slotName", text: a.name }),
                costBadge(a),
            ]),
            el("button", { type: "button", class: "HXI_ab_info", "aria-label": `${a.name} details`, text: "i",
                onclick: () => showDetails(a, "attachment", true) }),
        ]);
    });
    document.getElementById("HXI_ab_slots").replaceChildren(...slots);
}

function overMessage(over) {
    return over.map(o => `${data.elements[o.element].label} ${o.used}/${o.max} (${o.attachments.map(a => a.name).join(", ")})`).join("; ");
}

function renderWarning() {
    const box = document.getElementById("HXI_ab_warning");
    const over = model.overCapacity(state);
    box.hidden = over.length === 0;
    if (!over.length) return;
    box.replaceChildren(
        el("strong", { text: "Over capacity: " }),
        overMessage(over),
        el("br"),
        "Remove or swap the highlighted attachments, or pick a head/frame with more capacity."
    );
}

function notifyOver(over) {
    mw.notify("Over capacity: " + overMessage(over), { type: "error", autoHide: true, tag: "HXI_ab_over" });
}

function statRow(label, value, cls) {
    return el("div", { class: "HXI_ab_stat" + (cls ? " " + cls : "") }, [
        el("span", { class: "HXI_ab_statL", text: label }),
        el("span", { class: "HXI_ab_statV", text: String(value) }),
    ]);
}

/**
 * "Your skill" boxes show the current cap as their placeholder, so a box left on "At cap" reads as that
 * value instead of blank. The cap follows head/frame/level, so this runs on every stats render.
 */
function renderSkillPlaceholders(s) {
    const max = stats.playerSkillMax(inputs);
    const lowered = [];
    for (const k of SKILLS) {
        const num = document.getElementById("HXI_ab_skill_" + k);
        if (!num) return; // inputs form not rendered (Equipsets)
        const row = s ? s.skills.find(x => x.key === k) : null;
        const cap = row ? row.cap - row.bonus : 0; // your own skill's cap, before merits/gear
        num.placeholder = row && cap > 0 ? String(cap) : "—";

        // ceiling = rank A+ at this level; a lower level pulls a typed value down with it
        if (max) num.max = max;
        const used = inputs.skills[k];
        if (used !== null && num.value !== "" && String(used) !== num.value && document.activeElement !== num) {
            num.value = used;
            lowered.push(k);
        }
    }
    if (lowered.length) notifySkillMax(lowered, max);
}

function notifySkillMax(keys, max) {
    const names = keys.map(k => k.charAt(0).toUpperCase() + k.slice(1)).join(", ");
    mw.notify(`${names} skill: max ${max} at automaton Lv${stats.level(inputs)} (rank A+).`, { type: "warn", autoHide: true, tag: "HXI_ab_skillMax" });
}

function renderStats() {
    const s = stats.compute(state, inputs);
    renderSkillPlaceholders(s);
    const hasPup = stats.level(inputs) > 0;
    const note = document.getElementById("HXI_ab_pupNote");
    if (note) note.hidden = hasPup;
    document.getElementById("HXI_ab_statsLevel").textContent = s ? `Lv${s.level}` : "";

    const body = document.getElementById("HXI_ab_statsBody");
    if (!s) {
        // every base stat comes from the frame, so "None" has nothing to show
        const why = !hasPup ? "Set Puppetmaster as your main or sub job to see automaton stats."
                            : "Choose a frame to see automaton stats.";
        body.replaceChildren(el("p", { class: "HXI_ab_note", text: why }));
        return;
    }

    const vitalTitle = (base, bonus, what) => bonus ? `${base} base + ${bonus} from ${what}` : null;
    const vitals = el("div", { class: "HXI_ab_statGroup HXI_ab_vitals" }, [
        el("div", { class: "HXI_ab_stat HXI_ab_hp", title: vitalTitle(s.hpBase, s.hpBonus, "Auto-Repair Kits") }, [
            el("span", { class: "HXI_ab_statL", text: "HP" }),
            el("span", { class: "HXI_ab_statV", text: String(s.hp) }),
            s.hpBonus ? el("span", { class: "HXI_ab_statBonus", text: `+${s.hpBonus}` }) : null,
        ]),
        el("div", { class: "HXI_ab_stat HXI_ab_mp", title: vitalTitle(s.mpBase, s.mpBonus, "Mana Tanks") }, [
            el("span", { class: "HXI_ab_statL", text: "MP" }),
            el("span", { class: "HXI_ab_statV", text: String(s.mp) }),
            s.mpBonus ? el("span", { class: "HXI_ab_statBonus", text: `+${s.mpBonus}` }) : null,
        ]),
    ]);

    const attrs = el("div", { class: "HXI_ab_statGroup HXI_ab_attrs" }, s.attrs.map(a => statRow(a.key, a.value)));

    const skillHead = el("tr", {}, ["Skill", "Rank", "Skill / Cap"].map(t => el("th", { scope: "col", text: t })));
    const skillRows = s.skills.map(k => el("tr", { class: k.cap === 0 ? "HXI_ab_noSkill" : null }, [
        el("th", { scope: "row", text: k.key.charAt(0).toUpperCase() + k.key.slice(1) }),
        el("td", { text: k.rankLabel }),
        el("td", { class: k.atCap ? "HXI_ab_capped" : null, title: k.bonus ? `includes +${k.bonus} from merits/gear` : null,
            text: k.cap === 0 ? "—" : `${k.value} / ${k.cap}` }),
    ]));
    skillRows.push(el("tr", {}, [el("th", { scope: "row", text: "Evasion" }), el("td", { text: "" }), el("td", { text: String(s.evasion) })]));
    skillRows.push(el("tr", {}, [el("th", { scope: "row", text: "Defense" }), el("td", { text: "" }), el("td", { text: String(s.defense) })]));

    const skills = el("div", { class: "HXI_ab_tableWrap" }, el("table", { class: "HXI_ab_table HXI_ab_skillTable" },
        [el("thead", {}, skillHead), el("tbody", {}, skillRows)]));

    const traits = s.frameMods.length ? [
        el("h3", { class: "HXI_ab_subtitle", text: `${model.framesById[state.frame].label} frame traits` }),
        el("ul", { class: "HXI_ab_fx" }, s.frameMods.map(m => el("li", {}, [
            el("span", { class: "HXI_ab_fxLabel", text: m.label }),
            el("span", { class: "HXI_ab_fxVal" + (m.value < 0 ? " HXI_ab_neg" : ""), text: Model.formatValue(m.format, m.value) }),
        ]))),
    ] : [];

    body.replaceChildren(vitals, attrs, skills, ...traits);
}

function renderEffects() {
    const s = stats.compute(state, inputs);
    const fx = model.effects(state, maneuvers, s ? { hp: s.hp, mp: s.mp } : null);
    const list = [];

    if (!fx.mods.length && !fx.scaling.length && !fx.special.length) {
        list.push(el("p", { class: "HXI_ab_note", text: "Equip attachments to see their combined effects." }));
    }

    if (fx.boost > 0) {
        list.push(el("p", { class: "HXI_ab_boost", text: `Optic Fiber: attachment performance +${fx.boost}% (included below)` }));
    }

    if (fx.mods.length) {
        list.push(el("ul", { class: "HXI_ab_fx" }, fx.mods.map(m => el("li", {
            title: m.sources.map(s => `${s.name}: ${Model.formatValue(m.format, s.value)}`).join("\n"),
        }, [
            el("span", { class: "HXI_ab_fxLabel", text: m.label }),
            el("span", { class: "HXI_ab_fxVal" + (m.total < 0 ? " HXI_ab_neg" : ""), text: Model.formatValue(m.format, m.total) }),
        ]))));
    }

    for (const s of fx.scaling) {
        const lines = [];
        lines.push(el("li", {}, [
            el("span", { class: "HXI_ab_fxLabel", text: `Max ${s.stat}` }),
            el("span", { class: "HXI_ab_fxVal", text: s.boostPct === null ? `none (frame has no ${s.stat} bonus)` : `+${s.boostPct}%` }),
        ]));
        const tickLabel = s.tick === "REGEN" ? "Regen" : "Refresh";
        let tickText;
        if (!s.tickBase && !s.tickPct) tickText = "none (needs maneuvers)";
        else if (s.tickValue !== null) tickText = `+${s.tickValue} ${s.stat}/tick`;
        else tickText = `+${s.tickBase} + ${s.tickPct}% of max ${s.stat}`;
        lines.push(el("li", {}, [
            el("span", { class: "HXI_ab_fxLabel", text: tickLabel }),
            el("span", { class: "HXI_ab_fxVal", text: tickText }),
        ]));
        list.push(el("ul", { class: "HXI_ab_fx", title: s.sources.join(", ") }, lines));
    }

    if (fx.special.length) {
        list.push(el("h3", { class: "HXI_ab_subtitle", text: "Special effects" }));
        list.push(el("ul", { class: "HXI_ab_special" }, fx.special.map(a => el("li", {}, [
            el("button", { type: "button", class: "HXI_ab_link", text: a.name, onclick: () => showDetails(a, "attachment", true) }),
        ]))));
    }

    document.getElementById("HXI_ab_effectList").replaceChildren(...list);
}

/* ---------- details ---------- */

function description(item) {
    if (!item.descrPending) return el("p", { class: "HXI_ab_descr", text: item.descr });
    return el("p", { class: "HXI_ab_descr HXI_ab_descrPending" }, [
        item.descr + " ",
        el("span", { class: "HXI_ab_flag", title: DESCR_PENDING_TITLE, "aria-label": DESCR_PENDING_TITLE, text: "⚑" }),
    ]);
}

function showDetails(item, kind, scroll) {
    if (!item) return;
    const body = [];

    body.push(el("div", { class: "HXI_ab_detailHead" }, [
        icon(item.icon, ""),
        el("div", {}, [
            el("div", { class: "HXI_ab_detailName", text: item.name }),
            kind === "attachment" ? costBadge(item) : el("span", { class: "HXI_ab_note", text: kind === "head" ? "Head" : "Frame" }),
        ]),
    ]));
    body.push(description(item));

    if (kind !== "attachment") {
        // capacity this part provides
        body.push(el("div", { class: "HXI_ab_partCaps" }, data.elements.map(e =>
            el("span", { class: "HXI_ab_cost HXI_ab_el" + e.id, title: `${e.label} +${item.caps[e.id]}` }, [elementIcon(e.id), "+" + item.caps[e.id]]))));
    }

    if (kind === "attachment" && (item.mods.length || item.scaling)) {
        const head = el("tr", {}, [el("th", { text: "Maneuvers" }), ...[0, 1, 2, 3].map(n => el("th", { text: String(n) }))]);
        const rows = item.mods.filter(m => m.values[0] !== null).map(m =>
            el("tr", {}, [el("th", { text: m.label }), ...m.values.map(v => el("td", { text: Model.formatValue(m.format, v) }))]));
        if (item.scaling) {
            const tick = data.scaling[item.scaling.type].tick === "REGEN" ? "Regen" : "Refresh";
            const stat = data.scaling[item.scaling.type].stat;
            rows.push(el("tr", {}, [el("th", { text: tick }), ...item.scaling.base.map((b, n) =>
                el("td", { text: n === 0 ? "—" : `${b} + ${item.scaling.multiplier[n]}% ${stat}` }))]));
        }
        body.push(el("div", { class: "HXI_ab_tableWrap" }, el("table", { class: "HXI_ab_table" }, [el("thead", {}, head), el("tbody", {}, rows)])));
        if (item.mods.some(m => m.optic)) body.push(el("p", { class: "HXI_ab_note", text: "Boosted by Optic Fiber." }));
    }
    if (kind === "attachment" && item.special) {
        body.push(el("p", { class: "HXI_ab_note", text: "Special effect - no stat modifiers. See description." }));
    }

    const target = document.getElementById("HXI_ab_detailBody");
    target.replaceChildren(...body);
    if (scroll) target.closest(".HXI_ab_details").scrollIntoView({ behavior: "smooth", block: "nearest" });
}

/* ---------- picker ---------- */

function setupPicker() {
    const dialog = document.getElementById("HXI_ab_picker");
    document.getElementById("HXI_ab_pickerClose").addEventListener("click", () => dialog.close());
    // click on the backdrop (outside the window) closes
    dialog.addEventListener("click", e => { if (e.target === dialog) dialog.close(); });
    document.getElementById("HXI_ab_pickerSearch").addEventListener("input", renderPickerList);
    document.getElementById("HXI_ab_pickerFits").addEventListener("change", renderPickerList);
    document.getElementById("HXI_ab_pickerRemove").addEventListener("click", () => {
        equip(pickerSlot, 0);
        dialog.close();
    });

    const filter = document.getElementById("HXI_ab_pickerFilter");
    const chip = (e, label) => el("button", { type: "button", class: "HXI_ab_chip" + (e === null ? " HXI_ab_chipOn" : " HXI_ab_el" + e),
        "aria-pressed": e === null ? "true" : "false", "data-element": e === null ? "" : e, title: label,
        onclick: ev => {
            pickerElement = e;
            for (const c of filter.children) {
                const on = c === ev.currentTarget;
                c.classList.toggle("HXI_ab_chipOn", on);
                c.setAttribute("aria-pressed", on ? "true" : "false");
            }
            renderPickerList();
        } }, e === null ? "All" : elementIcon(e));
    filter.replaceChildren(chip(null, "All elements"), ...data.elements.map(e => chip(e.id, e.label)));
}

function pickerMessage(text) {
    const box = document.getElementById("HXI_ab_pickerMsg");
    box.textContent = text || "";
    box.hidden = !text;
}

function openPicker(slot) {
    pickerSlot = slot;
    pickerMessage("");
    const current = model.attachment(state.attachments[slot]);
    document.getElementById("HXI_ab_pickerTitle").textContent = current ? `Slot ${slot + 1}: replace ${current.name}` : `Slot ${slot + 1}: choose attachment`;
    document.getElementById("HXI_ab_pickerRemove").hidden = !current;
    renderPickerList();
    const dialog = document.getElementById("HXI_ab_picker");
    dialog.showModal();
    document.getElementById("HXI_ab_pickerList").scrollTop = 0;
}

function summary(a) {
    if (a.special) return "Special effect";
    if (a.scaling) return `Max ${data.scaling[a.scaling.type].stat} +, ${data.scaling[a.scaling.type].tick === "REGEN" ? "Regen" : "Refresh"}`;
    return a.mods.map(m => `${m.label} ${m.values.map(v => Model.formatValue(m.format, v)).join("/")}`).join(" · ");
}

function renderPickerList() {
    const query = document.getElementById("HXI_ab_pickerSearch").value.trim().toLowerCase();
    const fitsOnly = document.getElementById("HXI_ab_pickerFits").checked;
    const currentId = state.attachments[pickerSlot];

    const rows = [];
    for (const a of data.attachments) {
        if (pickerElement !== null && a.element !== pickerElement) continue;
        if (query && !a.name.toLowerCase().includes(query)) continue;
        if (a.id === currentId) continue;
        const check = model.canEquip(state, pickerSlot, a);
        if (check.reason === "Already equipped") continue; // duplicates are hidden, not just disabled
        if (fitsOnly && !check.ok) continue;

        rows.push(el("button", { type: "button", role: "option", class: "HXI_ab_pick" + (check.ok ? "" : " HXI_ab_pickNo"),
            "aria-disabled": check.ok ? null : "true",
            onclick: () => {
                if (!check.ok) {
                    // The open <dialog> sits in the browser's top layer, above mw.notify's area, so the
                    // notification alone would be hidden behind the picker - show it inside the picker too.
                    const msg = `${a.name} doesn't fit: ${check.reason}`;
                    pickerMessage(msg);
                    mw.notify(msg, { type: "error", autoHide: true, tag: "HXI_ab_fit" });
                    return;
                }
                equip(pickerSlot, a.id);
                document.getElementById("HXI_ab_picker").close();
            } }, [
            icon(a.icon, ""),
            el("span", { class: "HXI_ab_pickText" }, [
                el("span", { class: "HXI_ab_pickName", text: a.name }),
                el("span", { class: "HXI_ab_pickSub", text: check.ok ? summary(a) : check.reason }),
            ]),
            costBadge(a),
        ]));
    }
    if (!rows.length) rows.push(el("p", { class: "HXI_ab_note", text: "No attachments match." }));
    document.getElementById("HXI_ab_pickerList").replaceChildren(...rows);
}

/* ---------- share ---------- */

/** Build + inputs as query params (HXI_Automaton::fromRequest / HXI_AutomatonInputs::fromRequest). */
function query() {
    let q = `&head=${state.head}&frame=${state.frame}&att=${Model.encodeAttachments(state.attachments)}`;
    if (options.inputs !== "form") return q;

    q += `&mjob=${inputs.mjob}&mlvl=${inputs.mlvl}&sjob=${inputs.sjob}&slvl=${inputs.slvl}`;
    // the rest only when changed from the defaults, to keep links short
    if (SKILLS.some(k => inputs.skills[k] !== null)) q += "&askill=" + SKILLS.map(k => inputs.skills[k] === null ? "c" : inputs.skills[k]).join("-");
    if (inputs.merits) q += "&amerit=" + inputs.merits;
    if (GEAR.some(k => inputs.gear[k])) q += "&agear=" + GEAR.map(k => inputs.gear[k] || 0).join("-");
    return q;
}

function syncUrl() {
    const url = mw.config.get("wgScriptPath") + "/index.php?title=Special:AutomatonBuilder" + query();
    history.replaceState(null, "", url);
}

function copyShareLink() {
    const url = mw.config.get("wgServer") + mw.config.get("wgScriptPath") + "/index.php?title=Special:AutomatonBuilder" + query();
    navigator.clipboard.writeText(url).then(function () {
        mw.notify("Copied to Clipboard !", { autoHide: true, type: "warn" });
    }, function () {
        mw.notify("Error copying to clipboard. Please report on our Discord.", { autoHide: true, type: "error" });
    });
}
