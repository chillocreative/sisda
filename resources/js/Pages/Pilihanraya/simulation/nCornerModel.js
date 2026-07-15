// Pure N-corner election simulation math for the Simulasi Pilihanraya table.
//
// Generalises the SIMULASI-2026 workbook from a fixed PH-vs-BN 1-lawan-1 model
// to a contest of N parties/coalitions (N = 2..6). For each kaum the first N-1
// parties take an explicit "% Sokongan"; the last party is the RESIDUAL of the
// turnout (the workbook's "baki % = BN"). With N=2 and parties [PH, BN] this
// reproduces the workbook exactly (Undi BN = Undi Keluar − Undi PH).

export const KAUM_KEYS = ['melayu', 'cina', 'india', 'lain'];

// Sum of the explicit (non-residual) support fractions for a kaum, i.e. every
// slot except the last. The residual party's implied share is 1 − this.
export function explicitSupportSum(sokonganRow, partyCount) {
    let sum = 0;
    for (let i = 0; i < partyCount - 1; i += 1) {
        sum += Number(sokonganRow?.[i]) || 0;
    }
    return sum;
}

/**
 * Run the simulation.
 *
 * @param {Object}   pengundi  { melayu, cina, india, lain } voter counts
 * @param {Object}   andaian   per-kaum { turnout, sokongan: number[] } where
 *                             sokongan[i] is party i's support fraction (0..1);
 *                             only indices 0..N-2 are read (last is residual).
 * @param {Object[]} parties   [{ kod, nama }] length N, in contest order
 * @returns {Object} { perKaum, totals, parties, winner, majoriti, status,
 *                     keluar, pengundiTotal, perlu, turnoutAll }
 */
export function simulate(pengundi, andaian, parties) {
    const N = parties.length;

    const perKaum = KAUM_KEYS.map((k) => {
        const voters = Number(pengundi?.[k]) || 0;
        const row = andaian?.[k] || {};
        const turnout = clamp01(Number(row.turnout) || 0);
        const keluar = voters * turnout;

        // Explicit shares for parties 0..N-2, clamped so the residual ≥ 0.
        const explicit = [];
        let used = 0;
        for (let i = 0; i < N - 1; i += 1) {
            const share = Math.max(0, Number(row.sokongan?.[i]) || 0);
            explicit.push(share);
            used += share;
        }
        const overflow = used > 1;
        // If the explicit shares exceed 100%, scale them down proportionally so
        // the maths stays coherent (residual becomes 0) while we flag overflow.
        const scale = overflow && used > 0 ? 1 / used : 1;

        const shares = [];
        let residual = 1;
        for (let i = 0; i < N - 1; i += 1) {
            const s = explicit[i] * scale;
            shares.push(s);
            residual -= s;
        }
        shares.push(Math.max(0, residual)); // last party = baki

        const undi = shares.map((s) => keluar * s);

        return { key: k, voters, turnout, keluar, shares, undi, overflow };
    });

    // Column totals per party.
    const undiTotals = parties.map((_, p) => perKaum.reduce((s, r) => s + r.undi[p], 0));
    const keluar = perKaum.reduce((s, r) => s + r.keluar, 0);
    const pengundiTotal = perKaum.reduce((s, r) => s + r.voters, 0);
    const perlu = keluar > 0 ? Math.floor(keluar / 2) + 1 : 0;

    // Winner + majoriti (top1 − top2). Ties resolve to the earlier-listed party.
    const ranked = parties
        .map((party, p) => ({ ...party, votes: undiTotals[p], index: p }))
        .sort((a, b) => b.votes - a.votes);
    const winner = ranked[0] || null;
    const runnerUp = ranked[1] || null;
    const majoriti = winner && runnerUp ? winner.votes - runnerUp.votes : (winner?.votes ?? 0);

    return {
        parties,
        perKaum,
        undiTotals,
        keluar,
        pengundiTotal,
        perlu,
        turnoutAll: pengundiTotal > 0 ? keluar / pengundiTotal : 0,
        winner,
        runnerUp,
        majoriti,
        status: winner && keluar > 0 ? `${winner.kod} MENANG` : '—',
        hasOverflow: perKaum.some((r) => r.overflow),
    };
}

function clamp01(n) {
    if (Number.isNaN(n)) return 0;
    return Math.min(1, Math.max(0, n));
}
