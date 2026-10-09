"""
Generates, from LandSandBoat:
  includes/helpers/data/HXI_AutomatonAttachmentMods.php  <- scripts/globals/automaton.lua
      (attachmentModifiers, repairKit, manaTank tables)
  includes/helpers/data/HXI_AutomatonFrameData.php       <- scripts/globals/pets/automaton.lua
      (frameStats, skillCaps, frameMods tables)

Usage (from the repo root):
    python tools/generate_automaton_mods.py ../LandSandBoat

Re-run whenever LSB's automaton scripts change. Horizon-specific changes do NOT go here -
put them in HXI_AutomatonData::OVERRIDES so a regenerate never wipes them.
"""
import re
import subprocess
import sys
from pathlib import Path

LSB = Path(sys.argv[1] if len(sys.argv) > 1 else "../LandSandBoat")
SRC = LSB / "scripts" / "globals" / "automaton.lua"
OUT = Path("includes/helpers/data/HXI_AutomatonAttachmentMods.php")

FRAMES = {"HARLEQUIN": 0x20, "VALOREDGE": 0x21, "SHARPSHOT": 0x22, "STORMWAKER": 0x23}

lua = SRC.read_text(encoding="utf-8")


def block(start_pattern):
    """Return the text of a Lua table starting at start_pattern, up to its matching close brace."""
    m = re.search(start_pattern, lua)
    if not m:
        sys.exit(f"pattern not found in automaton.lua: {start_pattern}")
    i = lua.index("{", m.end() - 1)
    depth = 0
    for j in range(i, len(lua)):
        if lua[j] == "{":
            depth += 1
        elif lua[j] == "}":
            depth -= 1
            if depth == 0:
                return lua[i : j + 1]
    sys.exit(f"unbalanced table: {start_pattern}")


def num(v):
    v = v.strip()
    return "null" if v == "nil" else v


def php_list(values):
    return "[" + ", ".join(values) + "]"


# --- attachmentModifiers ---------------------------------------------------
mods_src = block(r"xi\.automaton\.attachmentModifiers\s*=\s*\n?\s*\{")
entry_re = re.compile(r"\['([^']+)'\s*\]\s*=\s*\{(.*?)\},\s*\n(?=\s*(?:\[|\}))", re.S)
mod_re = re.compile(
    r"\{\s*modifier\s*=\s*xi\.mod\.(\w+),\s*values\s*=\s*\{([^}]*)\},\s*opticFiber\s*=\s*(true|false)\s*\}"
)

attachments = []
for name, body in entry_re.findall(mods_src):
    mods = []
    for mod, values, optic in mod_re.findall(body):
        mods.append((mod, [num(v) for v in values.split(",")], optic))
    if not mods:
        sys.exit(f"no modifiers parsed for {name}")
    attachments.append((name, mods))


# --- repairKit / manaTank ----------------------------------------------------
def scaling_table(var, boost_key, base_key, mult_key):
    src = block(rf"xi\.automaton\.{var}\s*=\s*\n?\s*\{{")
    div_src = re.search(r"frameDivisors\s*=\s*\{(.*?)\}", src, re.S).group(1)
    divisors = {FRAMES[f]: d for f, d in re.findall(r"frame\.(\w+)\s*\]\s*=\s*(\d+)", div_src)}
    rows = {}
    row_re = re.compile(
        rf"\['([^']+)'\s*\]\s*=\s*\{{\s*id\s*=\s*\d+,\s*{boost_key}\s*=\s*(\d+),\s*"
        rf"{base_key}\s*=\s*\{{([^}}]*)\}},\s*{mult_key}\s*=\s*\{{([^}}]*)\}}"
    )
    for name, boost, base, mult in row_re.findall(src):
        rows[name] = (boost, [num(v) for v in base.split(",")], [num(v) for v in mult.split(",")])
    if not rows:
        sys.exit(f"no rows parsed for {var}")
    return divisors, rows


repair_div, repair_rows = scaling_table("repairKit", "hpBoost", "regenBase", "regenMultiplier")
tank_div, tank_rows = scaling_table("manaTank", "mpBoost", "refreshBase", "refreshMultiplier")

lsb_rev = subprocess.run(
    ["git", "-C", str(LSB), "log", "-1", "--format=%h %cs"], capture_output=True, text=True
).stdout.strip()

# --- emit -----------------------------------------------------------------
out = [
    "<?php",
    "",
    "/**",
    " * GENERATED FILE - do not edit by hand.",
    " * Source: LandSandBoat scripts/globals/automaton.lua @ " + lsb_rev,
    " * Regenerate: python tools/generate_automaton_mods.py ../LandSandBoat",
    " * Horizon-specific changes belong in HXI_AutomatonData::OVERRIDES, not here.",
    " */",
    "class HXI_AutomatonAttachmentMods {",
    "",
    "    /**",
    "     * attachment name (item_puppet.name) => list of [mod, values by maneuver count 0..3, opticFiber]",
    "     * A null value means the amount is computed elsewhere (REGEN/REFRESH, see SCALING).",
    "     */",
    "    public const MODIFIERS = [",
]
width = max(len(n) for n, _ in attachments) + 2
for name, mods in attachments:
    rows = [f"['{m}', {php_list(v)}, {o}]" for m, v, o in mods]
    out.append(f"        {repr(name).replace(chr(34), chr(39)):<{width}} => [ " + ", ".join(rows) + " ],")
out += [
    "    ];",
    "",
    "    /**",
    "     * Auto-Repair Kits (HP/Regen) and Mana Tanks (MP/Refresh).",
    "     * Max HP/MP bonus % = sum(boost) / frameDivisors[frame] (frames missing from the divisor list get none).",
    "     * Regen/Refresh per tick = base[maneuvers] + maxHP|maxMP * multiplier[maneuvers] / 100.",
    "     */",
    "    public const SCALING = [",
]
for key, stat, tick, divisors, rows in (
    ("repair_kit", "HP", "REGEN", repair_div, repair_rows),
    ("mana_tank", "MP", "REFRESH", tank_div, tank_rows),
):
    out.append(f"        '{key}' => [")
    out.append(f"            'stat' => '{stat}',")
    out.append(f"            'tick' => '{tick}',")
    out.append(
        "            'frameDivisors' => [ "
        + ", ".join(f"0x{f:02X} => {d}" for f, d in sorted(divisors.items()))
        + " ],"
    )
    out.append("            'items' => [")
    for name, (boost, base, mult) in rows.items():
        out.append(
            f"                '{name}' => [ 'boost' => {boost}, 'base' => {php_list(base)}, 'multiplier' => {php_list(mult)} ],"
        )
    out.append("            ],")
    out.append("        ],")
out += ["    ];", "}", ""]

OUT.write_text("\n".join(out), encoding="utf-8", newline="\n")
print(f"wrote {OUT}: {len(attachments)} attachments, {len(repair_rows)} repair kits, {len(tank_rows)} mana tanks")


# =============================================================================
# Frame data: scripts/globals/pets/automaton.lua
# =============================================================================
PET_SRC = LSB / "scripts" / "globals" / "pets" / "automaton.lua"
RANK_SRC = LSB / "scripts" / "enum" / "skill_rank.lua"
OUT_FRAMES = Path("includes/helpers/data/HXI_AutomatonFrameData.php")

lua = PET_SRC.read_text(encoding="utf-8")  # block() reads the global `lua`
ranks = {n: int(v) for n, v in re.findall(r"(\w+)\s*=\s*(\d+)", RANK_SRC.read_text(encoding="utf-8"))}
HEADS = {"HARLEQUIN": 0x01, "VALOREDGE": 0x02, "SHARPSHOT": 0x03, "STORMWAKER": 0x04, "SOULSOOTHER": 0x05, "SPIRITREAVER": 0x06}
SKILLS = {"AUTOMATON_MELEE": "melee", "AUTOMATON_RANGED": "ranged", "AUTOMATON_MAGIC": "magic"}
STAT_KEYS = ["maxHP", "maxMP", "STR", "DEX", "VIT", "AGI", "INT", "MND", "CHR"]


def frame_blocks(src):
    """Split a table keyed by [xi.automaton.frame.X] / [xi.automaton.head.X] into {name: body}."""
    out_ = {}
    for m in re.finditer(r"\[xi\.automaton\.(?:frame|head)\.(\w+)\s*\]\s*=\s*\{", src):
        i = m.end() - 1
        depth = 0
        for j in range(i, len(src)):
            depth += src[j] == "{"
            depth -= src[j] == "}"
            if depth == 0:
                out_[m.group(1)] = src[i : j + 1]
                break
    return out_


# frameStats: [frame][level] = { maxHP, maxMP, STR..CHR }
stats_src = block(r"xi\.pets\.automaton\.frameStats\s*=\s*\n?\s*\{")
frame_stats = {}
for fname, body in frame_blocks(stats_src).items():
    levels = {}
    for lvl, row in re.findall(r"\[\s*(\d+)\]\s*=\s*\{([^}]*)\}", body):
        vals = dict(re.findall(r"(\w+)\s*=\s*(\d+)", row))
        levels[int(lvl)] = [vals[k] for k in STAT_KEYS]
    if sorted(levels) != list(range(1, len(levels) + 1)):
        sys.exit(f"frameStats for {fname}: levels not contiguous")
    frame_stats[FRAMES[fname]] = levels

# skillCaps: frames -> rank, heads -> rank delta
caps_src = block(r"xi\.pets\.automaton\.skillCaps\s*=\s*\n?\s*\{")
frames_src = re.search(r"frames\s*=\s*\{(.*?)\n    \},", caps_src, re.S).group(1)
heads_src = re.search(r"heads\s*=\s*\{(.*?)\n    \},", caps_src, re.S).group(1)
frame_ranks = {
    FRAMES[f]: {SKILLS[s]: ranks[r] for s, r in re.findall(r"xi\.skill\.(\w+)\s*\]\s*=\s*xi\.skillRank\.(\w+)", body)}
    for f, body in frame_blocks(frames_src).items()
}
head_ranks = {
    HEADS[h]: {SKILLS[s]: int(d) for s, d in re.findall(r"xi\.skill\.(\w+)\s*\]\s*=\s*(-?\d+)", body)}
    for h, body in frame_blocks(heads_src).items()
}

# frameMods: passive mods per frame
mods_src = block(r"xi\.pets\.automaton\.frameMods\s*=\s*\n?\s*\{")
frame_mods = {}
for fname, body in frame_blocks(mods_src).items():
    mods_part = re.search(r"\bmods\s*=\s*\{(.*?)\n\s*\},", body, re.S)
    frame_mods[FRAMES[fname]] = re.findall(r"\{\s*xi\.mod\.(\w+),\s*(-?\d+)\s*\}", mods_part.group(1)) if mods_part else []

lsb_rev_pet = subprocess.run(
    ["git", "-C", str(LSB), "log", "-1", "--format=%h %cs"], capture_output=True, text=True
).stdout.strip()


def php_map(d):
    return "[" + ", ".join(f"'{k}' => {v}" for k, v in d.items()) + "]"


fo = [
    "<?php",
    "",
    "/**",
    " * GENERATED FILE - do not edit by hand.",
    " * Source: LandSandBoat scripts/globals/pets/automaton.lua @ " + lsb_rev_pet,
    " * Regenerate: python tools/generate_automaton_mods.py ../LandSandBoat",
    " */",
    "class HXI_AutomatonFrameData {",
    "",
    "    public const STAT_KEYS = [" + ", ".join(f"'{k}'" for k in STAT_KEYS) + "];",
    "",
    "    /** frame id => level (1-99) => values in STAT_KEYS order */",
    "    public const STATS = [",
]
for fid, levels in sorted(frame_stats.items()):
    fo.append(f"        0x{fid:02X} => [")
    for lvl, vals in levels.items():
        fo.append(f"            {lvl:>2} => [" + ", ".join(f"{v:>4}" for v in vals) + "],")
    fo.append("        ],")
fo += [
    "    ];",
    "",
    "    /**",
    "     * Skill rank per frame (index into skill_caps r0-r13, lower = better; missing = no native skill).",
    "     * A head adds HEAD_RANK_BONUS; a result below 0 becomes 13 + result (LSB puppetutils::getSkillCap).",
    "     */",
    "    public const FRAME_RANKS = [",
]
for fid, r in sorted(frame_ranks.items()):
    fo.append(f"        0x{fid:02X} => {php_map(r)},")
fo += ["    ];", "", "    /** head id => rank delta per skill */", "    public const HEAD_RANK_BONUS = ["]
for hid, r in sorted(head_ranks.items()):
    fo.append(f"        0x{hid:02X} => {php_map(r)},")
fo += ["    ];", "", "    /** frame id => passive [mod, value] list */", "    public const FRAME_MODS = ["]
for fid, mods in sorted(frame_mods.items()):
    fo.append(f"        0x{fid:02X} => [" + ", ".join(f"['{m}', {v}]" for m, v in mods) + "],")
fo += ["    ];", "}", ""]

OUT_FRAMES.write_text("\n".join(fo), encoding="utf-8", newline="\n")
print(f"wrote {OUT_FRAMES}: {len(frame_stats)} frames x {len(next(iter(frame_stats.values())))} levels, "
      f"{len(frame_ranks)} frame ranks, {len(head_ranks)} head bonuses, frame mods {sum(map(len, frame_mods.values()))}")
