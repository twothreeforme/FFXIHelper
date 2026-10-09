/*
 * BLU Builder - pure rules (no DOM). Mirrors HXI_BLUBuild.php / HXI_BLUBuilderInputs.php, which follow
 * LSB src/map/utils/blueutils.cpp:
 *  - slots  = clamp((level - 1) / 10 * 2 + 6, 6, 20)                         GetTotalSlots
 *  - points = clamp((level - 1) / 10 * 5 + 10, 0, 55) (+ Assimilation, Lv75 main)  GetTotalBlueMagicPoints
 *  - merits: 0-5 each, 10 per group, only for BLU main at Lv75                 merit.cpp GetMeritValue / meritCatInfo
 *  - level-locked spells, then spells over the points, then spells past the last slot are not set
 *                                                                             ValidateBlueSpells
 *  - traits: weights add up per category; a reached tier replaces lower tiers of the same trait + modifier
 *                                                                             CalculateTraits
 *  - a blue trait never stacks with the same trait from the jobs: the higher rank wins
 *                                                                             CalculateTraits + battleutils::AddTraits
 *
 * `data` is the 'HXI_BLUBuilder' mw.config payload (HXI_BLUBuilderData::clientPayload()).
 * `inputs` is { race, mjob, mlvl, sjob, slvl, maxSub, merits: { key: upgrades } } (HXI_BLUBuilderInputs::toArray()).
 * A set is an array of spell ids in set order.
 */

const OK = "ok", LEVEL = "level", POINTS = "points", SLOTS = "slots", NO_BLU = "noBlu";

/** The Horizon wiki counts trait points 8 per tier; LSB stores the same thing as 2 per tier. */
const TRAIT_POINT_SCALE = 4;

function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

function Model(data) {
    this.data = data;
    this.spellsById = {};
    for (const s of data.spells) this.spellsById[s.id] = s;
    this.tiersByCategory = {};
    for (const t of data.tiers) (this.tiersByCategory[t.category] = this.tiersByCategory[t.category] || []).push(t);
}

Model.OK = OK; Model.LEVEL = LEVEL; Model.POINTS = POINTS; Model.SLOTS = SLOTS; Model.NO_BLU = NO_BLU;
Model.TRAIT_POINT_SCALE = TRAIT_POINT_SCALE;

Model.prototype.spell = function (id) {
    return this.spellsById[id] || null;
};

/* ---------- level, slots, points ---------- */

Model.prototype.bluLevel = function (inputs) {
    if (inputs.mjob === this.data.blu) return inputs.mlvl;
    if (inputs.sjob === this.data.blu) return inputs.slvl;
    return 0;
};

Model.prototype.slots = function (inputs) {
    const level = this.bluLevel(inputs);
    return level > 0 ? clamp(Math.floor((level - 1) / 10) * 2 + 6, 6, 20) : 0;
};

Model.prototype.maxPoints = function (inputs) {
    const level = this.bluLevel(inputs);
    if (level <= 0) return 0;
    return clamp(Math.floor((level - 1) / 10) * 5 + 10, 0, 55) + this.merit(inputs, "assimilation");
};

/* ---------- merits (HXI_BLUBuilderInputs::MERITS / setMerits()) ---------- */

/** Job merits only count for the main job at Lv75+ (LSB merit.cpp GetMeritValue). */
Model.prototype.meritsActive = function (inputs) {
    return inputs.mjob === this.data.blu && inputs.mlvl >= this.data.meritLimits.level;
};

/** Upgrades of one merit that apply (0 when not BLU main Lv75). */
Model.prototype.merit = function (inputs, key) {
    return this.meritsActive(inputs) ? ((inputs.merits || {})[key] || 0) : 0;
};

/** Upgrades spent in a merit group (whether or not they apply). */
Model.prototype.meritGroupUsed = function (merits, group) {
    return this.data.merits.filter(m => m.group === group).reduce((sum, m) => sum + ((merits || {})[m.key] || 0), 0);
};

/** Highest value a merit can take without its group passing 10 (and never past 5). */
Model.prototype.meritMax = function (merits, key) {
    const info = this.data.merits.find(m => m.key === key);
    const others = this.meritGroupUsed(merits, info.group) - ((merits || {})[key] || 0);
    return Math.max(0, Math.min(this.data.meritLimits.each, this.data.meritLimits.group - others));
};

/** Effect text for `count` upgrades of a merit (MERIT_INFO 'effect'; Diffusion's first upgrade unlocks it). */
Model.prototype.meritEffect = function (key, count) {
    const info = this.data.merits.find(m => m.key === key);
    if (!count) return info.effect.replace("{v}", "0");
    const v = info.per * (key === "diffusion" ? count - 1 : count);
    return info.effect.replace("{v}", String(v));
};

/* ---------- set legality ---------- */

/**
 * [{ spell, state }] in set order. Same order of checks as LSB ValidateBlueSpells (HXI_BLUBuild::status()).
 */
Model.prototype.status = function (set, inputs) {
    const level = this.bluLevel(inputs), maxPoints = this.maxPoints(inputs), maxSlots = this.slots(inputs);
    let points = 0, count = 0;
    const rows = [];
    for (const id of set) {
        const spell = this.spell(id);
        if (!spell) continue;
        let state;
        if (level <= 0) state = NO_BLU;
        else if (spell.level > level) state = LEVEL;
        else if (points + spell.points > maxPoints) state = POINTS;
        else {
            points += spell.points;
            state = count < maxSlots ? OK : SLOTS;
            if (state === OK) count++;
        }
        rows.push({ spell: spell, state: state });
    }
    return rows;
};

Model.prototype.active = function (set, inputs) {
    return this.status(set, inputs).filter(r => r.state === OK).map(r => r.spell);
};

Model.prototype.pointsUsed = function (set, inputs) {
    return this.active(set, inputs).reduce((sum, s) => sum + s.points, 0);
};

/**
 * Can `spell` be added to the end of the set? { ok, reason } - same checks the game makes when setting a spell
 * (IsSpellSet, CheckSpellLevels, HasEnoughSetPoints, slot count).
 */
Model.prototype.canAdd = function (set, spell, inputs) {
    const level = this.bluLevel(inputs);
    if (level <= 0) return { ok: false, reason: "Blue Mage is not your main or sub job" };
    if (set.includes(spell.id)) return { ok: false, reason: "Already set" };
    if (spell.level > level) return { ok: false, reason: `Needs BLU Lv${spell.level} (yours: ${level})` };
    const active = this.active(set, inputs);
    if (active.length >= this.slots(inputs)) return { ok: false, reason: `All ${this.slots(inputs)} slots are full` };
    const free = this.maxPoints(inputs) - this.pointsUsed(set, inputs);
    if (spell.points > free) return { ok: false, reason: `Needs ${spell.points} points, ${free} left` };
    return { ok: true, reason: "" };
};

/* ---------- traits ---------- */

/**
 * LSB CalculateTraits up to the comparison with the jobs' own traits (HXI_BLUBuild::blueTraits()).
 * @return { points: {category: total}, traits: [tier rows] }
 */
Model.prototype.blueTraits = function (spells) {
    const points = {};
    for (const s of spells) {
        if (s.category === null || s.category === undefined) continue;
        points[s.category] = (points[s.category] || 0) + s.weight;
    }
    let eligible = [];
    for (const category of Object.keys(points)) {
        const total = points[category];
        for (const t of this.tiersByCategory[category] || []) {
            if (total < t.points || t.jpOnly) continue;
            eligible = eligible.filter(e => !(e.traitid === t.traitid && e.rank <= t.rank && e.modid === t.modid));
            eligible.push(t);
        }
    }
    return { points: points, traits: eligible };
};

/**
 * The jobs' own traits (LSB battleutils::AddTraits for main then sub): per trait id the highest rank wins;
 * merit-only traits are left out (Assimilation shows as blue magic points instead).
 * @return [{ traitid, job, level, rank, modid, value }]
 */
Model.prototype.jobTraits = function (inputs) {
    const list = [];
    const add = (job, level) => {
        if (!job || !level) return;
        for (const t of this.data.jobTraits) {
            if (t.job !== job || t.level > level || t.merit) continue;
            const same = list.filter(e => e.traitid === t.traitid);
            if (same.some(e => e.rank > t.rank || (e.rank === t.rank && e.modid === t.modid))) continue;
            for (const e of same.filter(e => e.rank < t.rank)) list.splice(list.indexOf(e), 1);
            list.push(t);
        }
    };
    add(inputs.mjob, inputs.mlvl);
    add(inputs.sjob, inputs.slvl);
    return list;
};

/**
 * Everything the traits window shows.
 *  categories: one row per trait category with points, the tier reached, the next tier, and what happened
 *              to the blue trait (applies / a job trait is as strong or stronger / no tier values yet)
 *  traits:     the final trait list (job + blue, after the no-stacking rule), each { traitid, rank, modid,
 *              value, source: "blu" | job id }
 */
Model.prototype.traitSummary = function (set, inputs) {
    const blu = this.bluLevel(inputs) > 0;
    const blue = blu ? this.blueTraits(this.active(set, inputs)) : { points: {}, traits: [] };
    const job = this.jobTraits(inputs);

    // CalculateTraits: drop blue traits a job trait matches or beats; drop job traits a blue trait beats
    const blueKept = blue.traits.filter(b => !job.some(j => j.traitid === b.traitid && j.modid === b.modid && j.rank >= b.rank));
    const jobKept = job.filter(j => !blueKept.some(b => b.traitid === j.traitid && b.modid === j.modid && j.rank < b.rank));

    const categories = Object.keys(blue.points).map(Number).sort((a, b) => a - b).map(c => {
        const tiers = (this.tiersByCategory[c] || []).filter(t => !t.jpOnly);
        const reached = blue.traits.filter(t => t.category === c);
        const kept = blueKept.filter(t => t.category === c);
        const thresholds = [...new Set(tiers.map(t => t.points))].sort((a, b) => a - b);
        const next = thresholds.find(p => p > blue.points[c]);
        const beatenBy = reached.length && !kept.length
            ? job.find(j => reached.some(r => r.traitid === j.traitid && r.modid === j.modid)) : null;
        return {
            category: c,
            label: this.data.categories[c] ? this.data.categories[c].label : String(c),
            pending: this.data.categories[c] ? this.data.categories[c].pending : null,
            points: blue.points[c],
            thresholds: thresholds,
            next: next === undefined ? null : next,
            reached: reached,
            kept: kept,
            beatenBy: beatenBy || null,
        };
    });

    const traits = jobKept.map(t => Object.assign({ source: t.job }, t))
        .concat(blueKept.map(t => Object.assign({ source: "blu" }, t)));
    return { categories: categories, traits: traits };
};

/* ---------- formatting / share ---------- */

Model.formatValue = function (format, value) {
    const sign = value > 0 ? "+" : "";
    if (format === "pct") return `${sign}${value}%`;
    if (format === "tick") return `${sign}${value}/tick`;
    return `${sign}${value}`;
};

Model.encode = function (set) {
    return set.join("-");
};

const ROMAN = ["0", "I", "II", "III", "IV", "V", "VI"];
Model.roman = function (rank) {
    return ROMAN[rank] || String(rank);
};

module.exports = Model;
