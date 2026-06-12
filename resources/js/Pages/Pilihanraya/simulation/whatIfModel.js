/**
 * Deterministic what-if projection model. Runs entirely client-side on
 * the baseline aggregates from /pilihanraya/api/baseline — zero server
 * round-trips while dragging sliders.
 *
 * Delta-based: scenario effects are computed as deviations from the
 * neutral scenario and added to the seat's baseline shares, so neutral
 * sliders reproduce the baseline EXACTLY and movement is continuous.
 *
 * The seat-probability math (logistic k=12, confidence shrink of
 * min(1, coverage/0.30)) MUST stay in sync with
 * ElectionForecastService::fallbackForecast() on the PHP side.
 */

export const DEFAULT_SLIDERS = {
    malaySwing: 0,        // -20..+20 percentage points toward PH
    chineseSwing: 0,
    indianSwing: 0,
    youthTurnout: 70,     // 40..95 (%) — voters aged 18-29
    midTurnout: 80,       // 30-49 (fixed default)
    seniorTurnout: 80,    // 40..95 (%) — voters aged 50+
    fenceConversion: 0,   // 0..100 (%) of kelabu mobilised to a side
    campaignEffectiveness: 50, // 0..100 (%) of converted kelabu going putih
};

const ETHNIC_SLIDER = { melayu: 'malaySwing', cina: 'chineseSwing', india: 'indianSwing' };
const AGE_TURNOUT = { b18_29: 'youthTurnout', b30_49: 'midTurnout', b50plus: 'seniorTurnout' };

const clamp01 = (v) => Math.max(0, Math.min(1, v));

function normaliseTriple({ putih, hitam, kelabu }) {
    const p = Math.max(0, putih);
    const h = Math.max(0, hitam);
    const k = Math.max(0, kelabu);
    const total = p + h + k;
    if (total <= 0) return { putih: 0, hitam: 0, kelabu: 1 };

    return { putih: p / total, hitam: h / total, kelabu: k / total };
}

/** Shift `swing` (fraction toward PH) from hitam to putih (or back). */
function applySwing(triple, swing) {
    if (!swing) return triple;
    const moved = swing > 0 ? Math.min(swing, triple.hitam) : -Math.min(-swing, triple.putih);

    return normaliseTriple({
        putih: triple.putih + moved,
        hitam: triple.hitam - moved,
        kelabu: triple.kelabu,
    });
}

/**
 * Ethnic-swing delta: roll-share-weighted difference between each
 * group's swung mix and its baseline mix. Groups without canvass data
 * contribute via the seat's overall mix instead, so the lever still
 * works in seats with sparse bangsa capture.
 */
function ethnicDelta(seat, sliders, baseline) {
    const delta = { putih: 0, hitam: 0, kelabu: 0 };

    Object.entries(ETHNIC_SLIDER).forEach(([group, sliderKey]) => {
        const swing = (sliders[sliderKey] ?? 0) / 100;
        if (!swing) return;
        const info = seat.byEthnic?.[group];
        const rollShare = info?.rollShare ?? 0;
        if (rollShare <= 0) return;

        const base = info?.shares ? normaliseTriple(info.shares) : baseline;
        const swung = applySwing(base, swing);
        delta.putih += rollShare * (swung.putih - base.putih);
        delta.hitam += rollShare * (swung.hitam - base.hitam);
        delta.kelabu += rollShare * (swung.kelabu - base.kelabu);
    });

    return delta;
}

/** Turnout-weighted mix across age bands for a given slider set. */
function ageView(seat, sliders, baseline) {
    let weighted = { putih: 0, hitam: 0, kelabu: 0 };
    let weight = 0;

    Object.entries(AGE_TURNOUT).forEach(([band, sliderKey]) => {
        const info = seat.byAge?.[band];
        const rollShare = info?.rollShare ?? 0;
        if (rollShare <= 0) return;
        const turnout = (sliders[sliderKey] ?? DEFAULT_SLIDERS[sliderKey]) / 100;
        const mix = info?.shares ? normaliseTriple(info.shares) : baseline;
        const w = rollShare * turnout;
        weighted.putih += w * mix.putih;
        weighted.hitam += w * mix.hitam;
        weighted.kelabu += w * mix.kelabu;
        weight += w;
    });

    if (weight <= 0) return baseline;

    return { putih: weighted.putih / weight, hitam: weighted.hitam / weight, kelabu: weighted.kelabu / weight };
}

/** Move `conversion` of kelabu out, split putih/hitam by effectiveness. */
function applyFenceConversion(triple, conversion, effectiveness) {
    if (!conversion) return triple;
    const moved = triple.kelabu * conversion;

    return normaliseTriple({
        putih: triple.putih + moved * effectiveness,
        hitam: triple.hitam + moved * (1 - effectiveness),
        kelabu: triple.kelabu - moved,
    });
}

/**
 * Project a single seat under the slider scenario.
 */
export function projectSeat(seat, sliders) {
    const baseline = normaliseTriple(seat.shares);

    // 1. Ethnic swing — delta vs neutral, weighted by roll composition
    const eDelta = ethnicDelta(seat, sliders, baseline);

    // 2. Turnout — delta between scenario turnout view and default view
    const scenarioAge = ageView(seat, sliders, baseline);
    const defaultAge = ageView(seat, DEFAULT_SLIDERS, baseline);

    let shares = normaliseTriple({
        putih: clamp01(baseline.putih + eDelta.putih + (scenarioAge.putih - defaultAge.putih)),
        hitam: clamp01(baseline.hitam + eDelta.hitam + (scenarioAge.hitam - defaultAge.hitam)),
        kelabu: clamp01(baseline.kelabu + eDelta.kelabu + (scenarioAge.kelabu - defaultAge.kelabu)),
    });

    // 3. Fence-sitter conversion split by campaign effectiveness
    shares = applyFenceConversion(
        shares,
        (sliders.fenceConversion ?? 0) / 100,
        (sliders.campaignEffectiveness ?? 50) / 100
    );

    // 4. Win probability — logistic on margin, shrunk by coverage
    const margin = shares.putih - shares.hitam;
    const pRaw = 1 / (1 + Math.exp(-12 * margin));
    const confidence = Math.min(1, (seat.coverage || 0) / 0.30);
    const pWin = 0.5 + (pRaw - 0.5) * confidence;

    return {
        name: seat.name,
        parlimen: seat.parlimen,
        rollTotal: seat.rollTotal,
        lowData: seat.lowData,
        baselineShares: {
            putih: Math.round(baseline.putih * 1000) / 10,
            hitam: Math.round(baseline.hitam * 1000) / 10,
            kelabu: Math.round(baseline.kelabu * 1000) / 10,
        },
        projectedShares: {
            putih: Math.round(shares.putih * 1000) / 10,
            hitam: Math.round(shares.hitam * 1000) / 10,
            kelabu: Math.round(shares.kelabu * 1000) / 10,
        },
        margin: Math.round(margin * 1000) / 10,
        pWin: Math.round(pWin * 1000) / 10,
    };
}

/** Project all seats and roll up national numbers. */
export function projectAll(baseline, sliders) {
    const seats = (baseline?.seats || []).map((seat) => projectSeat(seat, sliders));
    const total = seats.length;
    const wins = seats.filter((s) => s.pWin > 50).length;
    const expectedSeats = seats.reduce((sum, s) => sum + s.pWin / 100, 0);
    const rollTotal = seats.reduce((sum, s) => sum + (s.rollTotal || 0), 0) || 1;
    const overallPh = seats.reduce((sum, s) => sum + (s.projectedShares.putih * (s.rollTotal || 0)), 0) / rollTotal;
    const overallHitam = seats.reduce((sum, s) => sum + (s.projectedShares.hitam * (s.rollTotal || 0)), 0) / rollTotal;

    return {
        seats: [...seats].sort((a, b) => Math.abs(a.pWin - 50) - Math.abs(b.pWin - 50)),
        total,
        wins,
        losses: total - wins,
        expectedSeats: Math.round(expectedSeats * 10) / 10,
        majority: wins - (total - wins),
        overallPh: Math.round(overallPh * 10) / 10,
        overallHitam: Math.round(overallHitam * 10) / 10,
    };
}
