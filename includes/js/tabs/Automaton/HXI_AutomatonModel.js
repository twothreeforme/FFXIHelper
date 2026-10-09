/*
 * Automaton Builder - pure build logic (no DOM).
 *
 * Same rules as LSB puppetutils.cpp setAttachment() and HXI_Automaton.php:
 *  - an attachment can be equipped once
 *  - per element, attachment cost total <= head capacity + frame capacity
 * Effect math follows LSB scripts/globals/automaton.lua updateAttachmentModifier():
 *  - each attachment reads the number of active maneuvers of ITS element (capped at 3)
 *  - Optic Fiber boosts opticFiber-flagged modifiers by floor(value * (1 + boost / 100)),
 *    boost = sum of equipped Optic Fibers' value at the current Light maneuver count
 *
 * `data` is the 'HXI_Automaton' mw.config payload (HXI_AutomatonData::clientPayload()).
 * `state` is { head, frame, attachments: [12 attachment ids, 0 = empty], capacityBonus }.
 *   capacityBonus = gear "Elemental Capacity +N", added to every element (LSB setAutomatonElementalCapacityBonus).
 */

function Model(data) {
    this.data = data;
    this.attachmentsById = {};
    for (const a of data.attachments) this.attachmentsById[a.id] = a;
    this.headsById = {};
    for (const h of data.heads) this.headsById[h.id] = h;
    this.framesById = {};
    for (const f of data.frames) this.framesById[f.id] = f;
}

Model.prototype.attachment = function (id) {
    return this.attachmentsById[id] || null;
};

Model.prototype.equipped = function (state) {
    const list = [];
    for (const id of state.attachments) {
        const a = this.attachment(id);
        if (a) list.push(a);
    }
    return list;
};

/** Capacity per element provided by head + frame (+ gear capacity bonus). */
Model.prototype.capacityMax = function (state) {
    const bonus = state.capacityBonus || 0;
    const max = [bonus, bonus, bonus, bonus, bonus, bonus, bonus, bonus];
    for (const part of [this.headsById[state.head], this.framesById[state.frame]]) {
        if (!part) continue;
        for (let e = 0; e < 8; e++) max[e] += part.caps[e];
    }
    return max;
};

/** Capacity per element used by equipped attachments (optionally ignoring one slot). */
Model.prototype.capacityUsed = function (state, ignoreSlot) {
    const used = [0, 0, 0, 0, 0, 0, 0, 0];
    state.attachments.forEach((id, slot) => {
        const a = this.attachment(id);
        if (!a || slot === ignoreSlot || a.element === null) return;
        used[a.element] += a.cost;
    });
    return used;
};

/**
 * Elements over capacity, each with the equipped attachments that use that element.
 * @return [{ element, used, max, attachments: [attachment] }]  (empty = legal build)
 */
Model.prototype.overCapacity = function (state) {
    const used = this.capacityUsed(state);
    const max = this.capacityMax(state);
    const over = [];
    for (let e = 0; e < 8; e++) {
        if (used[e] <= max[e]) continue;
        over.push({ element: e, used: used[e], max: max[e], attachments: this.equipped(state).filter(a => a.element === e) });
    }
    return over;
};

/**
 * Can attachment `a` go into `slot`? (whatever is in that slot now is treated as removed)
 * @return { ok: bool, reason: string }
 */
Model.prototype.canEquip = function (state, slot, a) {
    const elsewhere = state.attachments.some((id, s) => id === a.id && s !== slot);
    if (elsewhere) return { ok: false, reason: "Already equipped" };
    if (a.element === null) return { ok: true, reason: "" };

    const used = this.capacityUsed(state, slot)[a.element];
    const max = this.capacityMax(state)[a.element];
    if (used + a.cost > max) {
        const label = this.data.elements[a.element].label;
        return { ok: false, reason: `${label} ${used}/${max}, needs ${a.cost}` };
    }
    return { ok: true, reason: "" };
};

/** Active maneuvers per element from the 3 maneuver picks (element ids or null). */
Model.prototype.maneuverCounts = function (maneuvers) {
    const counts = [0, 0, 0, 0, 0, 0, 0, 0];
    for (const m of maneuvers) if (m !== null && m !== undefined) counts[m] = Math.min(3, counts[m] + 1);
    return counts;
};

Model.prototype.performanceBoost = function (state, counts) {
    let boost = 0;
    for (const a of this.equipped(state)) {
        if (a.optic && a.mods.length) boost += a.mods[0].values[counts[a.element]];
    }
    return boost;
};

/**
 * Combined effects of the build at the given maneuvers.
 * @param vitals optional { hp, mp } final max HP/MP (HXI_AutomatonStats). With it, Regen/Refresh are real
 *               numbers (LSB getRegenModValue/getRefreshModValue); without it they stay a formula.
 * @return {
 *   mods:     [{ key, label, format, total, sources: [{ name, value }] }],
 *   scaling:  [{ type, stat, tick, boostPct|null, tickBase, tickPct, tickValue|null, sources: [name] }],
 *   special:  [attachment],
 *   boost:    Optic Fiber performance boost %
 * }
 */
Model.prototype.effects = function (state, maneuvers, vitals) {
    const counts = this.maneuverCounts(maneuvers);
    const boost = this.performanceBoost(state, counts);
    const byKey = {};
    const order = [];
    const scaling = {};
    const special = [];

    for (const a of this.equipped(state)) {
        const n = a.element === null ? 0 : counts[a.element];

        if (a.special) special.push(a);

        for (const mod of a.mods) {
            let value = mod.values[n];
            if (value === null) continue; // REGEN/REFRESH - handled by scaling below
            if (mod.optic) value = Math.floor(value * (1 + boost / 100));

            if (!byKey[mod.key]) {
                byKey[mod.key] = { key: mod.key, label: mod.label, format: mod.format, total: 0, sources: [] };
                order.push(mod.key);
            }
            byKey[mod.key].total += value;
            byKey[mod.key].sources.push({ name: a.name, value: value });
        }

        if (a.scaling) {
            const meta = this.data.scaling[a.scaling.type];
            if (!scaling[a.scaling.type]) {
                scaling[a.scaling.type] = { type: a.scaling.type, stat: meta.stat, tick: meta.tick, boostTier: 0, tickBase: 0, tickPct: 0,
                    tickValue: vitals ? 0 : null, sources: [] };
            }
            const s = scaling[a.scaling.type];
            s.boostTier += a.scaling.boost;
            s.tickBase += a.scaling.base[n];
            s.tickPct += a.scaling.multiplier[n];
            s.sources.push(a.name);

            if (vitals) {
                // per attachment, like LSB updateAttachmentModifier(): raw = base + max * multiplier / 100,
                // Optic Fiber floors the raw value; otherwise it's truncated when stored as an integer mod
                const max = meta.stat === "HP" ? vitals.hp : vitals.mp;
                const raw = a.scaling.base[n] + max * a.scaling.multiplier[n] / 100;
                const tickMod = a.mods.find(m => m.values[0] === null);
                s.tickValue += tickMod && tickMod.optic ? Math.floor(raw * (1 + boost / 100)) : Math.trunc(raw);
            }
        }
    }

    const scalingList = Object.values(scaling).map(s => {
        const divisor = this.data.scaling[s.type].frameDivisors[state.frame];
        // LSB: maxhp += maxhp * tier / divisor  (frames without a divisor get no bonus)
        s.boostPct = divisor ? Math.round(s.boostTier / divisor * 1000) / 10 : null;
        s.tickPct = Math.round(s.tickPct * 1000) / 1000;
        return s;
    });

    return { mods: order.map(k => byKey[k]), scaling: scalingList, special: special, boost: boost };
};

/** "+5", "+12%", "-5%", "x1.25" */
Model.formatValue = function (format, value) {
    const sign = v => (v > 0 ? "+" : v < 0 ? "−" : "") + Math.abs(v);
    switch (format) {
        case "pct": return sign(value) + "%";
        case "pct100": return sign(Math.round(value) / 100) + "%";
        case "mult100": return "×" + (value / 100);
        default: return sign(value);
    }
};

/** [1, 34, 0, 0] -> "1-34" (trailing empty slots dropped to keep links short) */
Model.encodeAttachments = function (attachments) {
    const list = attachments.slice();
    while (list.length && !list[list.length - 1]) list.pop();
    return list.join("-");
};

module.exports = Model;
