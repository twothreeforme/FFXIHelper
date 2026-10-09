/*
 * Automaton Builder - automaton stats from the build + the player's inputs (no DOM).
 *
 * Mirrors LSB:
 *  - level           petutils::CalculateAutomatonStats  PUP main: mlvl + gear level bonus, PUP sub: slvl
 *  - HP/MP/STR..CHR  petutils::LoadAutomatonStats       frameStats[frame][min(level, 99)]
 *                    + Auto-Repair Kit / Mana Tank: max += max * tier / frameDivisor (integer)
 *  - skill caps      puppetutils::getSkillCap           frame rank + head bonus, below 0 -> 13 + rank,
 *                                                       cap = skill_caps[level][rank]
 *  - shown skill     0x044 extended job packet          min(cap, your skill) + gear bonus + merits
 *                                                       (merits: +2 each, PUP main Lv75+ only)
 *  - evasion / DEF   petutils::LoadAutomatonStats       skill_caps[level][fixed rank per frame]
 *
 * `inputs` is the HXI_AutomatonInputs shape:
 *   { mjob, mlvl, sjob, slvl, maxSub, skills: {melee, ranged, magic} (null = at cap), merits, gear: {elemCapacity, melee, ranged, magic, level} }
 */

const SKILLS = ["melee", "ranged", "magic"];

function Stats(data) {
    this.data = data;          // full 'HXI_Automaton' payload
    this.s = data.stats;       // stats section (HXI_AutomatonData::statsPayload())
}

/** Automaton level, or 0 when Puppetmaster is neither main nor sub job. */
Stats.prototype.level = function (inputs) {
    if (inputs.mjob === this.s.pup && inputs.mlvl > 0) return inputs.mlvl + (inputs.gear.level || 0);
    if (inputs.sjob === this.s.pup && inputs.slvl > 0) return inputs.slvl;
    return 0;
};

Stats.prototype.meritBonus = function (inputs) {
    return inputs.mjob === this.s.pup && inputs.mlvl >= 75 ? inputs.merits * this.s.meritValue : 0;
};

Stats.prototype.skillCap = function (rank, level) {
    const row = this.s.skillCaps[Math.min(level, 99)];
    return rank > 0 && row ? row[rank] : 0;
};

/**
 * Highest automaton skill the PLAYER can have: rank A+ at the automaton's level
 * (LSB charutils: "A+ capped down to the Automaton's rating"). 0 when PUP isn't main/sub.
 */
Stats.prototype.playerSkillMax = function (inputs) {
    const level = this.level(inputs);
    return level ? this.skillCap(this.s.playerRank, level) : 0;
};

Stats.prototype.skillRank = function (state, skill) {
    let rank = (this.s.frameRanks[state.frame] || {})[skill] || 0;
    const bonus = (this.s.headRankBonus[state.head] || {})[skill];
    if (bonus) {
        rank += bonus;
        if (rank < 0) rank = 13 + rank; // frame has no native skill: head bonus grants rank F
    }
    return rank;
};

/** Sum of the scaling tier (Auto-Repair Kit hpBoost / Mana Tank mpBoost) of equipped attachments. */
Stats.prototype.scalingTier = function (state, type) {
    let tier = 0;
    for (const id of state.attachments) {
        const a = this.data.attachments.find(x => x.id === id);
        if (a && a.scaling && a.scaling.type === type) tier += a.scaling.boost;
    }
    return tier;
};

/**
 * @return null when PUP isn't main/sub, else {
 *   level, hp, hpBase, hpBonus, mp, mpBase, mpBonus, attrs: [{key, value}],
 *   skills: [{ key, rank, rankLabel, cap, value, bonus, atCap }], evasion, defense, frameMods
 * }
 */
Stats.prototype.compute = function (state, inputs) {
    const level = this.level(inputs);
    if (!level) return null;

    const statsLevel = Math.min(level, 99);
    const row = (this.s.frameStats[state.frame] || {})[statsLevel];
    if (!row) return null;

    const keys = this.s.statKeys; // maxHP, maxMP, STR..CHR
    const hpBase = row[keys.indexOf("maxHP")];
    const mpBase = row[keys.indexOf("maxMP")];

    const repairDiv = this.data.scaling.repair_kit.frameDivisors[state.frame];
    const hpBonus = repairDiv ? Math.floor(hpBase * this.scalingTier(state, "repair_kit") / repairDiv) : 0;

    // Mana Tanks only do anything on frames that have MP (and a divisor)
    const tankDiv = this.data.scaling.mana_tank.frameDivisors[state.frame];
    const mpBonus = mpBase > 0 && tankDiv ? Math.floor(mpBase * this.scalingTier(state, "mana_tank") / tankDiv) : 0;

    const merit = this.meritBonus(inputs);
    const skills = SKILLS.map(key => {
        const rank = this.skillRank(state, key);
        const cap = this.skillCap(rank, level);
        const yours = inputs.skills[key] === null || inputs.skills[key] === undefined ? cap : inputs.skills[key];
        const bonus = (inputs.gear[key] || 0) + merit;
        return {
            key: key, rank: rank, rankLabel: this.s.rankLabels[rank] || "-",
            cap: cap + bonus, value: Math.min(cap, yours) + bonus, bonus: bonus, atCap: yours >= cap,
        };
    });

    const defEva = this.s.defEvaRanks[state.frame] || { evasion: 0, defense: 0 };

    return {
        level: level,
        hp: hpBase + hpBonus, hpBase: hpBase, hpBonus: hpBonus,
        mp: mpBase + mpBonus, mpBase: mpBase, mpBonus: mpBonus,
        attrs: keys.slice(2).map((k, i) => ({ key: k, value: row[i + 2] })),
        skills: skills,
        evasion: this.skillCap(defEva.evasion, level),
        defense: this.skillCap(defEva.defense, level),
        frameMods: this.s.frameMods[state.frame] || [],
    };
};

module.exports = Stats;
