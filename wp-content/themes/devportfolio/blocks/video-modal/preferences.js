/**
 * What the visitor chose in this page session (memory only, never storage), shared by the desktop and the
 * mobile viewer. Only a button press or slider move writes it; the automatic muted fallback does not.
 */
export const preferences = { muted: false, volume: 1, rate: 1, fit: 'fit' };

/** The mobile fit button's modes, in the order it cycles through them. */
export const FIT_MODES = [ 'fit', 'zoom', 'stretch' ];
