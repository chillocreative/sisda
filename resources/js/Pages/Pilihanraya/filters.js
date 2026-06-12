// Shared filter plumbing for the Pilihanraya pages — one definition of
// the filter shape so WarRoom, Simulasi and FilterBar can't drift.

export const EMPTY_FILTERS = {
    negeri_id: '',
    parlimen_id: '',
    kadun_id: '',
    tarikh_dari: '',
    tarikh_hingga: '',
};

export function cleanParams(filters) {
    return Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v != null));
}
