import { $ as e, $a as t, $i as n, A as r, Ai as i, Ar as a, At as o, B as s, Ba as c, Bi as l, Bn as u, Bt as d, C as f, Ca as p, Ci as m, Cn as h, Cr as g, Ct as _, D as v, Di as y, Dt as b, E as x, Ei as S, Et as C, F as w, Fa as T, Fi as E, Ft as D, G as O, Ga as k, Gi as A, Gt as j, H as ee, Ha as te, Hi as M, Ht as N, I as ne, Ia as P, Ii as re, It as ie, J as F, Ja as I, Ji as ae, Jt as oe, K as se, Ka as ce, Ki as L, Kt as R, L as z, La as le, Li as ue, Ln as de, Lt as fe, M as pe, Ma as me, Mi as he, Mr as ge, N as _e, Na as B, Ni as ve, Nr as ye, Nt as be, O as xe, Oi as V, Or as Se, Ot as Ce, P as we, Pa as Te, Pi as Ee, Pt as H, Q as De, Qa as Oe, Qi as ke, Qt as Ae, R as je, Ra as Me, Ri as Ne, Rn as Pe, Rr as Fe, Rt as Ie, Sa as Le, Si as Re, Sn as ze, St as Be, T as Ve, Ta as He, Ti as U, Tt as Ue, U as We, Ua as Ge, V as Ke, Va as qe, Vi as Je, Vt as Ye, W as Xe, Wa as Ze, Wi as Qe, X as W, Xa as $e, Xn as G, Xt as et, Y as tt, Yt as nt, Z as K, Za as q, Zi as J, Zr as rt, Zt as it, _ as at, _a as ot, _i as st, _t as ct, ai as lt, ar as ut, at as dt, ba as ft, bi as pt, bn as mt, bt as Y, ca as ht, ci as gt, cn as _t, ct as vt, da as yt, di as bt, dn as xt, dr as St, dt as Ct, ea as wt, eo as Tt, er as Et, et as Dt, fa as Ot, fi as kt, ft as At, ga as jt, gi as Mt, gt as Nt, ha as Pt, hi as Ft, hn as It, ht as Lt, ia as Rt, in as zt, io as Bt, it as Vt, j as Ht, ja as Ut, ji as Wt, jr as Gt, k as Kt, ka as qt, ki as Jt, kt as Yt, l as Xt, la as Zt, li as Qt, ln as $t, lt as en, m as tn, ma as nn, mi as rn, mt as an, n as on, na as sn, no as cn, nt as ln, o as un, oa as dn, oi as fn, on as pn, ot as mn, pa as hn, pi as gn, pt as _n, q as vn, qa as yn, qi as bn, qt as xn, r as Sn, ra as Cn, ri as wn, ro as Tn, rt as En, s as Dn, sa as On, si as kn, sn as An, sr as jn, st as Mn, t as Nn, ta as Pn, to as Fn, tt as In, ua as Ln, ui as Rn, un as zn, ut as Bn, v as Vn, va as Hn, vi as Un, vn as Wn, vo as Gn, vt as Kn, w as qn, wa as Jn, wi as Yn, wn as Xn, wt as Zn, xa as Qn, xi as $n, xn as er, xt as tr, y as nr, yi as X, yn as Z, yo as Q, yt as rr, z as ir, za as $, zi as ar, zn as or, zr as sr, zt as cr } from "./plot-area-frame-D3JtHvov.js";
import { c as lr, f as ur, l as dr, o as fr, s as pr, u as mr } from "./pixel-budget-DI82Hn3R.js";
import { a as hr, i as gr, r as _r, t as vr } from "./tiff-contract-DEd8CGuz.js";
import { n as yr } from "./raster-target-Ca3tyA0L.js";
import { a as br, d as xr, f as Sr, i as Cr, l as wr, m as Tr, n as Er, o as Dr, p as Or, u as kr, v as Ar, y as jr } from "./three-d-ByHGsYrX.js";
var Mr = 7;
function Nr(e) {
	let t = /* @__PURE__ */ new Map();
	for (let n of e.dataPointOverrides ?? []) t.has(n.idx) || t.set(n.idx, n);
	return t;
}
function Pr(e, t) {
	return e.chartexFormatIdx ?? t;
}
function Fr(e) {
	return e != null && Number.isFinite(e) && e !== 0;
}
function Ir(e) {
	return e === "line" || e === "stackedLine" || e === "stackedLinePct" || e === "scatter" || e === "radar" || e === "stock";
}
function Lr(e, n, r = 4 / 3, a, o = !1) {
	if (o || e.series.length === 0) return null;
	let s = kn(e), c = V(e), l = Wt(e, !0), u = e.series.some((t, n) => gt(e.chartType, s[n]) === "scatter" && (t.categories ?? e.categories).some((e) => Number.isFinite(Number.parseFloat(e)))), d = e.dataTable?.showKeys === !0 && Rn(e.chartType) && (e.categories.length > 0 || e.series.some((e) => (e.categories?.length ?? e.values.length) > 0)), f = 0, p = (e, t = a?.w ?? 1, i = a?.h ?? 1) => {
		if (e == null) return !0;
		let o = e.fillType === "image" ? Dn(e, n, t, i, r) : Un(e);
		return e.fillType === "gradient" && o > 4096 || o > 1048576 - f ? !1 : (f += o, !0);
	}, m = (t, n, r, i, o = "dataPoint", s = o === "dataPoint", c = a?.w ?? 1, l = a?.h ?? 1) => (!s || p(me(e, t, n, r, i), c, l)) && p(B(e, o, t, n, r).paint, c, l), h = (t, n, i, a, o) => {
		let s = Rt(e.dataTable?.fontSizeHpt, r) ?? 9 * r, c = Rt(t.seriesDataLabels?.fontSizeHpt ?? e.dataLabelFontSizeHpt, r) ?? 10 * r;
		for (let e of t.dataLabelOverrides ?? []) c = Math.max(c, Rt(e.fontSizeHpt, r) ?? c);
		let l = a === "table" ? s : a === "label" ? c : Rt(e.legendFontSizeHpt, r) ?? 10 * r;
		if (a === "table") return {
			width: Math.max(12 * r, l * 1.7),
			height: l
		};
		if (o !== "dataPointLine") {
			let e = Mr * r;
			return {
				width: e,
				height: e
			};
		}
		let u = B(e, o, t, n, i), d = nt(u.widthEmu, r), f = sr(u.dash ?? "solid", d), p = f.length > 0 ? f.reduce((e, t) => e + t, 0) + f[0] : 0;
		return {
			width: Math.max(l * 1.6, p),
			height: l
		};
	};
	for (let n = 0; n < e.series.length; n++) {
		let r = e.series[n], o = s[n], f = gt(r.seriesType ?? e.chartType, o);
		o?.kind === "bar3D" || o?.kind === "area3D" || o?.kind === "line3D" ? f = e.chartType : o?.kind === "pie3D" && (f = "pie");
		let p = Nr(r), g = t(e, n), _ = Pr(r, n), v = (e) => g ? e : _, y = o?.kind === "bar" || o?.kind === "bar3D" || o == null && (f === "clusteredBar" || f === "clusteredBarH" || f === "stackedBar" || f === "stackedBarH" || f === "stackedBarPct" || f === "stackedBarHPct"), b = o?.kind === "pie" || o?.kind === "pie3D" || o?.kind === "doughnut" || o?.kind === "ofPie" || o == null && (f === "pie" || f === "doughnut" || f === "ofPie"), x = o?.kind === "area" || o?.kind === "area3D" || o == null && (f === "area" || f === "stackedArea" || f === "stackedAreaPct"), S = f === "radar" && (o?.radarStyle ?? e.radarStyle) === "filled";
		if (y || b) {
			let t = Math.max(1, r.values.length, e.categories.length), n = o?.barDirection === "bar" || f.endsWith("BarH") || f.endsWith("BarHPct"), i = y && !n ? (a?.w ?? 1) / t : a?.w ?? 1, s = y && n ? (a?.h ?? 1) / t : a?.h ?? 1;
			for (let e = 0; e < r.values.length; e++) if (Fr(r.values[e]) && !m(r, p.get(e), v(e), e, "dataPoint", !0, i, s)) return pn + 1;
			if (f === "ofPie") {
				let t = lt(e.ofPie, r.values), n = t == null ? void 0 : [...t].find((e) => Fr(r.values[e]));
				if (n != null && !m(r, p.get(n), v(n), n, "dataPoint", !0, a?.w ?? 1, a?.h ?? 1)) return pn + 1;
			}
		} else if ((x || S) && (S ? r.values.length >= 3 && r.values.every((e) => e != null && Number.isFinite(e)) : r.values.length > 0) && !m(r, void 0, _, void 0)) return pn + 1;
		let C = o?.kind === "bubble" || o == null && e.chartType === "bubble", w = f !== "scatter" || !C;
		if (Ir(f) && !S && w) {
			if (g && f !== "stock") {
				let t = f === "stackedLine" || f === "stackedLinePct" || o?.kind === "line" && (o.grouping === "stacked" || o.grouping === "percentStacked") || f === "line" && e.dispBlanksAs === "zero", n = !t && e.dispBlanksAs === "span", i = [], a = () => {
					for (let e = 1; e < i.length; e++) {
						let t = i[e];
						if (!m(r, p.get(t), t, t, "dataPointLine", !1)) return !1;
					}
					if (f === "radar" && i.length > 1) {
						let e = i[0];
						if (!m(r, p.get(e), e, e, "dataPointLine", !1)) return !1;
					}
					return i = [], !0;
				};
				for (let e = 0; e < r.values.length; e++) {
					if (r.sourceHidden?.[e] === !0) {
						if (!a()) return pn + 1;
						continue;
					}
					let o = r.values[e];
					if (o != null && Number.isFinite(o) || t) i.push(e);
					else if (!n && !a()) return pn + 1;
				}
				if (!a()) return pn + 1;
			} else if (!m(r, void 0, _, void 0, "dataPointLine", !1)) return pn + 1;
		}
		let T = b || g, E = gn(e, r, f, Math.max(r.values.length, r.categories?.length ?? 0, e.categories.length), u, {
			chartType: gt(e.chartType, o),
			bubbleScale: o?.bubbleScale ?? e.bubbleScale,
			showNegativeBubbles: o?.showNegativeBubbles ?? e.showNegativeBubbles
		}, n), D = ve(l, c, n);
		if (f !== "bubble" && (e.showLegend && D || d || E > 0)) {
			let t = Ir(f) ? "dataPointLine" : "dataPoint", a = t === "dataPoint", s = (e, n, i, o) => {
				let s = h(r, n, i, e, t);
				return m(r, n, i, o, t, a, s.width, s.height);
			};
			if (T) {
				let t = Math.max(r.values.length, r.categories?.length ?? 0, e.categories.length), a = new Map((r.dataLabelOverrides ?? []).map((e) => [e.idx, e]));
				for (let d = 0; d < t; d++) {
					let t = v(d), m = p.get(d), h = a.get(d), g = !ue(r.seriesDataLabels, h) && (h?.showLegendKey ?? r.seriesDataLabels?.showLegendKey ?? !1) === !0 && kt(e, r, f, d, u, {
						chartType: gt(e.chartType, o),
						bubbleScale: o?.bubbleScale ?? e.bubbleScale,
						showNegativeBubbles: o?.showNegativeBubbles ?? e.showNegativeBubbles
					});
					if (e.showLegend && i(l, c, n, d) && !s("legend", m, t, d) || g && !s("label", m, t, d)) return pn + 1;
				}
				let m = p.get(0);
				if (d && !s("table", m, v(0), 0)) return pn + 1;
			} else {
				if (e.showLegend && D && !s("legend", void 0, _, void 0) || d && !s("table", void 0, _, void 0)) return pn + 1;
				for (let e = 0; e < E; e++) if (!s("label", void 0, _, void 0)) return pn + 1;
			}
		}
	}
	let g = (t, n) => {
		let r = t[0], i = t.at(-1);
		if (!r || !i) return !0;
		let o = Math.max(r.values.length, i.values.length);
		for (let t = 0; t < o; t++) {
			let s = r.values[t], c = i.values[t];
			if (s == null || c == null || !Number.isFinite(s) || !Number.isFinite(c) || s === c) continue;
			let l = c > s ? "upBar" : "downBar", u = c > s ? n.up : n.down, d = e.chartType.endsWith("BarH") || e.chartType.endsWith("BarHPct"), f = d ? a?.w ?? 1 : (a?.w ?? 1) / o, m = d ? (a?.h ?? 1) / o : a?.h ?? 1;
			if (!p(P(e, u, l), f, m)) return !1;
		}
		return !0;
	};
	for (let t of e.lineGroupDecorations ?? []) {
		if (!t.upDownBars) continue;
		let n = e.series.filter((e) => e.lineGroupIndex === t.groupIndex);
		if (n.length === 0 && t.groupIndex === 0 && (n = e.series), !g(n, t.upDownBars)) return pn + 1;
	}
	if (e.stockUpDownBars) {
		let t = e.plotGroups?.find((e) => e.kind === "stock");
		if (!g(t ? e.series.slice(t.seriesStart, t.seriesStart + t.seriesCount) : e.series, e.stockUpDownBarStyle ?? {
			gapWidthPercent: 150,
			up: {},
			down: {}
		})) return pn + 1;
	}
	return f;
}
function Rr(e, t, n = 4 / 3, r, i = !1) {
	return Tn(() => yn(() => Lr(e, t, n, r, i)));
}
var zr = class e extends Error {
	codec;
	code = "ooxml-optional-image-codec-unavailable";
	constructor(t) {
		super(`${t.toUpperCase()} image requires an optional codec`), this.codec = t, this.name = "OptionalImageCodecUnavailableError", Object.setPrototypeOf(this, e.prototype);
	}
};
function Br(e, t) {
	if (typeof e != "object" || !e) return !1;
	try {
		let n = e;
		return n.code === "ooxml-optional-image-codec-unavailable" && n.codec === "tiff" && (t === void 0 || n.codec === t);
	} catch {
		return !1;
	}
}
var Vr = {
	tiff: "TIFF image unavailable",
	pict: "PICT image unsupported",
	equation: "Equation unavailable"
};
function Hr(e, t, n, r) {
	if (![
		n.x,
		n.y,
		n.width,
		n.height
	].every(Number.isFinite) || n.width <= 0 || n.height <= 0) return;
	let i = (n, r, i, a) => {
		e.save();
		try {
			e.fillStyle = "#888", e.font = "11px sans-serif", e.textAlign = "center", e.textBaseline = "middle", e.fillText(Vr[t], n + i / 2, r + a / 2, i);
		} finally {
			e.restore();
		}
	};
	if (!r) {
		i(n.x, n.y, n.width, n.height);
		return;
	}
	let a = g(n.width, n.height, null, r.transform);
	Se(e, n.x, n.y, a, i);
}
function Ur(e) {
	let t = e.close;
	typeof t == "function" && t.call(e);
}
function Wr(e, t) {
	return e[t] << 8 | e[t + 1];
}
function Gr(e, t) {
	return (e[t] << 24 | e[t + 1] << 16 | e[t + 2] << 8 | e[t + 3]) >>> 0;
}
function Kr(e, t) {
	return e[t] | e[t + 1] << 8;
}
function qr(e, t) {
	return e[t] | e[t + 1] << 8 | e[t + 2] << 16 | e[t + 3] << 24 | 0;
}
function Jr(e) {
	let t = e.length;
	return t >= 24 && e[0] === 137 && e[1] === 80 && e[2] === 78 && e[3] === 71 && e[4] === 13 && e[5] === 10 && e[6] === 26 && e[7] === 10 ? e[12] === 73 && e[13] === 72 && e[14] === 68 && e[15] === 82 ? {
		width: Gr(e, 16),
		height: Gr(e, 20)
	} : null : t >= 10 && e[0] === 71 && e[1] === 73 && e[2] === 70 && e[3] === 56 && (e[4] === 55 || e[4] === 57) && e[5] === 97 ? {
		width: Kr(e, 6),
		height: Kr(e, 8)
	} : t >= 26 && e[0] === 66 && e[1] === 77 ? Yr(e, 14) === 12 ? {
		width: Kr(e, 18),
		height: Kr(e, 20)
	} : {
		width: Math.abs(qr(e, 18)),
		height: Math.abs(qr(e, 22))
	} : t >= 16 && e[0] === 82 && e[1] === 73 && e[2] === 70 && e[3] === 70 && e[8] === 87 && e[9] === 69 && e[10] === 66 && e[11] === 80 ? Xr(e) : t >= 4 && e[0] === 255 && e[1] === 216 ? ei(e) : t >= 4 && (e[0] === 73 && e[1] === 73 || e[0] === 77 && e[1] === 77) ? hr(e) : null;
}
function Yr(e, t) {
	return (e[t] | e[t + 1] << 8 | e[t + 2] << 16 | e[t + 3] << 24) >>> 0;
}
function Xr(e) {
	let t = e.length, n = e[12], r = e[13], i = e[14], a = e[15];
	if (n === 86 && r === 80 && i === 56 && a === 32) return t < 30 ? null : {
		width: Kr(e, 26) & 16383,
		height: Kr(e, 28) & 16383
	};
	if (n === 86 && r === 80 && i === 56 && a === 76) {
		if (t < 25 || e[20] !== 47) return null;
		let n = Yr(e, 21);
		return {
			width: (n & 16383) + 1,
			height: (n >>> 14 & 16383) + 1
		};
	}
	return n === 86 && r === 80 && i === 56 && a === 88 ? t < 30 ? null : {
		width: (e[24] | e[25] << 8 | e[26] << 16) + 1,
		height: (e[27] | e[28] << 8 | e[29] << 16) + 1
	} : null;
}
function Zr(e) {
	return e.length >= 6 && e[0] === 69 && e[1] === 120 && e[2] === 105 && e[3] === 102 && e[4] === 0 && e[5] === 0;
}
function Qr(e) {
	if (e.length < 14 || !Zr(e)) return null;
	let t = e[6] === 73 && e[7] === 73, n = e[6] === 77 && e[7] === 77;
	if (!t && !n) return null;
	let r = new DataView(e.buffer, e.byteOffset, e.byteLength);
	if (r.getUint16(8, t) !== 42) return null;
	let i = r.getUint32(10, t), a = 6 + i;
	if (i < 8 || a + 2 > e.length) return null;
	let o = r.getUint16(a, t), s = a + 2, c = Math.min(o, Math.floor((e.length - s) / 12));
	for (let e = 0; e < c; e++) {
		let n = s + e * 12;
		if (r.getUint16(n, t) !== 274 || r.getUint16(n + 2, t) !== 3 || r.getUint32(n + 4, t) !== 1) continue;
		let i = r.getUint16(n + 8, t);
		if (i >= 1 && i <= 8) return i;
	}
	return null;
}
function $r(e, t) {
	return t !== null && t >= 5 && t <= 8 ? {
		width: e.height,
		height: e.width
	} : e;
}
function ei(e) {
	let t = e.length, n = 2, r = null, i = !1, a = null;
	for (; n + 1 < t;) {
		if (e[n] !== 255) {
			n += 1;
			continue;
		}
		let o = e[n + 1];
		if (o === 255) {
			n += 1;
			continue;
		}
		if (o === 216 || o === 1 || o >= 208 && o <= 215) {
			n += 2;
			continue;
		}
		if (o === 217 || o === 218) return a ? $r(a, r) : null;
		if (n + 3 >= t) return null;
		let s = Wr(e, n + 2);
		if (o >= 192 && o <= 207 && o !== 196 && o !== 200 && o !== 204) {
			if (s < 7 || n + 8 >= t) return null;
			let r = Wr(e, n + 5), i = Wr(e, n + 7);
			a ??= {
				width: i,
				height: r
			};
		}
		if (s < 2) return null;
		let c = n + 2 + s;
		if (c > t) return a ? $r(a, r) : null;
		if (o === 225 && !i) {
			let t = e.subarray(n + 4, c);
			Zr(t) && (i = !0, r = Qr(t));
		}
		n = c;
	}
	return a ? $r(a, r) : null;
}
function ti(e) {
	let { width: t, height: n } = e;
	return !Number.isFinite(t) || !Number.isFinite(n) || t <= 0 || n <= 0 || t > 32767 || n > 32767 ? !0 : t * n > pr;
}
function ni(e) {
	let { width: t, height: n } = e;
	return !Number.isFinite(t) || !Number.isFinite(n) || t <= 0 || n <= 0 || t > 134217728 || n > 134217728 ? !0 : t * n > dr;
}
function ri(e) {
	switch (e.split(";", 1)[0]?.trim().toLowerCase()) {
		case "image/png":
		case "image/jpeg":
		case "image/jpg":
		case "image/gif":
		case "image/bmp":
		case "image/x-ms-bmp":
		case "image/webp": return !0;
		default: return !1;
	}
}
function ii(e, t = !1) {
	return e !== null && e !== "wmf" && e !== "emf" && (e !== "tiff" || t);
}
var ai = 64 * 1024;
function oi(e) {
	return Gt(e) ? "wmf" : a(e) ? "emf" : e.length >= 8 && e[0] === 137 && e[1] === 80 && e[2] === 78 && e[3] === 71 && e[4] === 13 && e[5] === 10 && e[6] === 26 && e[7] === 10 ? "png" : e.length >= 2 && e[0] === 255 && e[1] === 216 ? "jpeg" : e.length >= 6 && e[0] === 71 && e[1] === 73 && e[2] === 70 && e[3] === 56 && (e[4] === 55 || e[4] === 57) && e[5] === 97 ? "gif" : e.length >= 2 && e[0] === 66 && e[1] === 77 ? "bmp" : e.length >= 12 && e[0] === 82 && e[1] === 73 && e[2] === 70 && e[3] === 70 && e[8] === 87 && e[9] === 69 && e[10] === 66 && e[11] === 80 ? "webp" : _r(e) ? "tiff" : null;
}
function si(e) {
	return e >= 192 && e <= 207 && e !== 196 && e !== 200 && e !== 204;
}
async function ci(e) {
	let t = e.slice(2).stream().getReader(), n = !1, r = -1, i = -1, a = 0, o = null, s = 0, c = 0, l = null, u = null, d = 0, f = null, p = !1;
	try {
		for (;;) {
			let { value: e, done: m } = await t.read();
			if (m) return l ? $r(l, f) : null;
			for (let m of e) {
				if (o) {
					if (o[s++] = m, s === o.length) {
						let e = o[1] << 8 | o[2], n = o[3] << 8 | o[4];
						if (l ??= {
							width: n,
							height: e
						}, o = null, s = 0, p) return await t.cancel(), $r(l, f);
						a = c, c = 0;
					}
					continue;
				}
				if (u) {
					if (u[d++] = m, d === u.length && (Zr(u) && (p = !0, f = Qr(u)), u = null, d = 0, p && l)) return await t.cancel(), $r(l, f);
					continue;
				}
				if (a > 0) {
					a--;
					continue;
				}
				if (r >= 0) {
					if (i < 0) {
						i = m;
						continue;
					}
					let e = i << 8 | m;
					if (e < 2) return null;
					if (si(r)) {
						if (e < 7) return null;
						l ? a = e - 2 : (o = new Uint8Array(5), s = 0, c = e - 7);
					} else r === 225 && !p && e > 2 ? (u = new Uint8Array(e - 2), d = 0) : a = e - 2;
					r = -1, i = -1;
					continue;
				}
				if (!n) {
					m === 255 && (n = !0);
					continue;
				}
				if (m !== 255 && (n = !1, m !== 0)) {
					if (m === 217 || m === 218) return await t.cancel(), l ? $r(l, f) : null;
					m === 216 || m === 1 || m >= 208 && m <= 215 || (r = m);
				}
			}
		}
	} finally {
		t.releaseLock();
	}
}
async function li(e, t) {
	if (t.length < 8 || !_r(t)) return null;
	let n = t[0] === 73, r = new DataView(t.buffer, t.byteOffset, t.byteLength).getUint32(4, n);
	if (!Number.isSafeInteger(r) || r < 8 || r > e.size - 2) return null;
	let i = new Uint8Array(await e.slice(r, r + 2).arrayBuffer());
	if (i.length !== 2) return null;
	let a = new DataView(i.buffer, i.byteOffset, 2).getUint16(0, n), o = a * 12;
	if (r + 2 + o > e.size) return null;
	let s = new Uint8Array(await e.slice(r + 2, r + 2 + o).arrayBuffer()), c = new DataView(s.buffer, s.byteOffset, s.byteLength), l, u;
	for (let e = 0; e < a; e++) {
		let t = e * 12, r = c.getUint16(t, n);
		if (r !== 256 && r !== 257) continue;
		let i = c.getUint16(t + 2, n);
		if (c.getUint32(t + 4, n) !== 1 || i !== 1 && i !== 3 && i !== 4) return null;
		let a = i === 1 ? c.getUint8(t + 8) : i === 3 ? c.getUint16(t + 8, n) : c.getUint32(t + 8, n);
		r === 256 ? l = a : u = a;
	}
	return l === void 0 || u === void 0 ? null : {
		width: l,
		height: u
	};
}
async function ui(e, t) {
	let n = t ?? new Uint8Array(await e.slice(0, ai).arrayBuffer()), r = oi(n);
	if (!r) return {
		format: null,
		dimensions: null
	};
	let i = Jr(n);
	return r === "jpeg" ? {
		format: r,
		dimensions: await ci(e) ?? i
	} : i ? {
		format: r,
		dimensions: i
	} : r === "tiff" ? {
		format: r,
		dimensions: await li(e, n)
	} : {
		format: r,
		dimensions: null
	};
}
var di = () => import("./emf-DgUBx9BU.js"), fi = class e extends Error {
	format;
	unsupported;
	code = "ooxml-incomplete-metafile";
	constructor(t, n) {
		super(`OOXML ${t} metafile could not be fully played: ${n.join(", ")}`), this.format = t, this.unsupported = n, this.name = "OoxmlIncompleteMetafileError", Object.setPrototypeOf(this, e.prototype);
	}
};
function pi(e) {
	if (!e || typeof e != "object") return !1;
	try {
		let t = e;
		return t.code === "ooxml-incomplete-metafile" && Array.isArray(t.unsupported);
	} catch {
		return !1;
	}
}
var mi = /* @__PURE__ */ new WeakMap();
function hi(e, t) {
	if (!e || !t || e === t) return;
	let n = mi.get(e);
	n && mi.set(t, n);
}
function gi(e, t, n, r = "emf") {
	if (t.length === 0) return e;
	if (n === "reject") throw e && Ur(e), new fi(r, t);
	return e && mi.set(e, {
		format: r,
		unsupported: t
	}), e;
}
function _i(e, t) {
	return e.width <= 0 || e.height <= 0 || e.width > 32767 || e.height > 32767 || e.width * e.height > t;
}
function vi(e) {
	let t = e.split(";", 1)[0]?.trim().toLowerCase();
	return t === "image/tiff" || t === "image/x-tiff";
}
function yi(e, t, n, r = !1) {
	let i = {
		retainedDimensions: e,
		resizeOptions: null
	}, a = yr(e, typeof t == "number" && Number.isFinite(t) && t > 0 ? t : void 0, typeof n == "number" && Number.isFinite(n) && n > 0 ? n : void 0, r);
	return a ? {
		retainedDimensions: a,
		resizeOptions: {
			resizeWidth: a.width,
			resizeHeight: a.height,
			resizeQuality: "high"
		}
	} : i;
}
function bi(e, t, n) {
	let r = Math.max(e.width, e.height);
	if (!Number.isFinite(r) || r > t) return new mr("image-dimension", t, Number.isFinite(r) ? r : 2 ** 53 - 1);
	let i = e.width * e.height;
	return new mr("image-pixels", n, Number.isSafeInteger(i) && i >= 0 ? i : 2 ** 53 - 1);
}
async function xi(e, t = {}) {
	return Si(e, t);
}
async function Si(e, t = {}, n) {
	let { widthPt: r = 0, heightPt: i = 0, tiff: o, targetWidthPx: s, targetHeightPx: c, maxRetainedPixels: l = pr, incompleteMetafile: u = "draw-supported" } = t, d = Number.isSafeInteger(l) && l > 0 ? Math.min(l, pr) : pr, f = new Uint8Array(await e.slice(0, 64 * 1024).arrayBuffer());
	if (Gt(f)) {
		let { w: t, h: n } = ye(r, i), a = [];
		return Ci(gi(await ge(new Uint8Array(await e.arrayBuffer()), t, n, (e) => {
			a = e;
		}), a, u, "wmf"), d);
	}
	if (a(f)) {
		let { w: t, h: n } = ye(r, i), { renderEmfToBitmap: a } = await di(), o = await a(new Uint8Array(await e.arrayBuffer()), t, n);
		return Ci(gi(o.bitmap, o.unsupported, u), d);
	}
	let p = (n ?? await ui(e, f)).dimensions;
	if (p && ni(p)) throw bi(p, lr, dr);
	let m = _r(f) || vi(e.type), h = p ? yi(p, s, c, m) : null;
	if (h && _i(h.retainedDimensions, d)) throw bi(h.retainedDimensions, fr, d);
	if (m) {
		if (!o) throw new zr("tiff");
		let t;
		try {
			t = await o.render(new Uint8Array(await e.arrayBuffer()), {
				targetWidthPx: s,
				targetHeightPx: c,
				maxRetainedPixels: d
			});
		} catch (e) {
			throw ur(e) || gr(e) ? e : new vr("TIFF codec failed to decode the image", { cause: e });
		}
		if (!t) throw new vr("TIFF codec failed to decode the image");
		return Ci(t, d);
	}
	return Ci(h?.resizeOptions ? await createImageBitmap(e, h.resizeOptions) : await createImageBitmap(e), d);
}
function Ci(e, t = pr) {
	if (!e) return null;
	let n = {
		width: Number(e.width),
		height: Number(e.height)
	};
	if (!_i(n, t)) return e;
	throw Ur(e), bi(n, fr, t);
}
function wi(e) {
	return e instanceof Gn || e instanceof Q || ur(e) || gr(e) || Br(e) || pi(e) ? e : new Gn("unsupported-image-effect", "Image effect preparation or evaluation failed");
}
var Ti = An, Ei = pn, Di = Ti, Oi = Ei, ki = new Set([
	"pie",
	"line",
	"stackedLine",
	"stackedLinePct",
	"area",
	"stackedArea",
	"stackedAreaPct",
	"clusteredBar",
	"clusteredBarH",
	"stackedBar",
	"stackedBarH",
	"stackedBarPct",
	"stackedBarHPct"
]);
function Ai(e, t, n, r, i) {
	return le(t, n, r);
}
function ji(e, t, n, r) {
	if (!t.length) return null;
	let i = e.chartexColorStyleMethod;
	return i === "withinLinear" || i === "acrossLinear" || i === "withinLinearReversed" || i === "acrossLinearReversed" ? t[i === "withinLinear" || i === "withinLinearReversed" ? 0 : n % t.length] ?? null : t[n % t.length] ?? null;
}
function Mi(e, t, n) {
	return (e.chartexColorPalette ? ji(e, e.chartexColorPalette, t, n) : null) ?? e.chartexAccents?.[t % (e.chartexAccents.length || 1)] ?? H[t % H.length];
}
function Ni(e, t, n, r) {
	return Ai(e, r, "fill", t, n) ?? Ai(e, e.chartexDataPointStyle, "fill", t, n) ?? Mi(e, t, n);
}
function Pi(e, t, n, r) {
	return k(t, n);
}
function Fi(e, t, n, r) {
	return Ge(t, n);
}
function Ii(e, t, n, r, i, a) {
	let o = a === e.chartexDataPointMarkerStyle ? "dataPointMarker" : a === e.chartexDataPointStyle ? "dataPoint" : Object.entries(e.chartStyleRoles ?? {}).find(([, e]) => e === a)?.[0], s = Me(r, o ? cn(e, o) ?? (e.classicChartStyleRoles == null ? a : void 0) : a, t);
	if (s !== void 0) return s;
	if (i) return {
		fillType: "solid",
		color: i
	};
	let c = Fi(e, a, t, n);
	return c === void 0 ? {
		fillType: "solid",
		color: Mi(e, t, n)
	} : c;
}
function Li(e, t, n, r, i, a = e.chartexDataPointStyle) {
	let o = Ge(r, t);
	if (o !== void 0) return o;
	if (r && i || i) return {
		fillType: "solid",
		color: i
	};
	let s = Ge(a, t);
	return s === void 0 ? {
		fillType: "solid",
		color: Mi(e, t, n)
	} : s;
}
function Ri(e, t, n, r, i, a, o, s = 0) {
	return t.fillType === "solid" ? t.color.startsWith("#") ? t.color : `#${t.color}` : jn(t, e, n, r, i, a, s) ?? o;
}
function zi(e, t, n, r, i, a = 0) {
	if (t === null) return !1;
	if (t?.fillType === "image") {
		e.save(), e.clip();
		let r = Vn(e, t, n.x, n.y, n.w, n.h, i, a);
		return e.restore(), r;
	}
	return e.fillStyle = t ? Ri(e, t, n.x, n.y, n.w, n.h, r, a) : r, e.fill(), !0;
}
function Bi(e, t, n, r, i, a = 0) {
	return t === null || !(n.w > 0) || !(n.h > 0) ? !1 : t?.fillType === "image" ? (e.beginPath(), e.rect(n.x, n.y, n.w, n.h), zi(e, t, n, r, i, a)) : (e.fillStyle = t ? Ri(e, t, n.x, n.y, n.w, n.h, r, a) : r, e.fillRect(n.x, n.y, n.w, n.h), !0);
}
function Vi(e, t, n, r, i, a, o = {}) {
	let s = n?.chartexStyle, c = k(s, r), l = n?.lineHidden === !0 ? null : void 0, u = n?.lineColor != null || l !== void 0, d = k(t, r), f = c === void 0 ? u ? l === void 0 ? {
		fillType: "solid",
		color: n?.lineColor ?? a
	} : l : d === void 0 ? void 0 : d : c, p = f === void 0 && t?.lineNoStyle === !0 && o.linkedNoStyleFallback !== !0;
	return {
		semanticFallback: f === void 0,
		visible: f !== null && !p,
		color: f?.fillType === "solid" ? f.color : a,
		paint: f?.fillType === "solid" ? void 0 : f,
		widthEmu: s?.lineWidthEmu ?? n?.lineWidthEmu ?? t?.lineWidthEmu ?? null,
		dash: s?.lineCustomDash == null ? s?.lineDash ?? t?.lineDash ?? null : null,
		customDash: s?.lineCustomDash ?? t?.lineCustomDash ?? null,
		cap: s?.lineCap ?? t?.lineCap ?? null,
		join: s?.lineJoin ?? t?.lineJoin ?? null
	};
}
var Hi = 9525;
function Ui(e, t, n, r, i, a = e.chartexDataPointStyle) {
	let o = T(e, t, n, r, a);
	return o === void 0 ? {
		fillType: "solid",
		color: Mi(e, r, i)
	} : o;
}
function Wi(e) {
	return e.paint != null && e.paint.fillType !== "solid";
}
function Gi(e, t) {
	let n = e?.chartexStyle, r = k(n, t);
	return !e || !r || r.fillType === "solid" ? e : {
		...e,
		lineColor: null,
		lineHidden: null,
		chartexStyle: {
			...n,
			linePaints: null,
			lineColors: null,
			lineColorIndex: null,
			linePaintAuthored: null,
			lineHidden: null
		}
	};
}
function Ki(e, t, n, r, i, a, o, s = {}) {
	t = Gi(t, i);
	let c = Vi(e, n[0], t, i, a, o, s);
	for (let t of n.slice(1)) {
		if (!c.semanticFallback) break;
		let n = Vi(e, t, null, i, a, o, s);
		n.semanticFallback || (c = n);
	}
	let l = t?.chartexStyle, u = l?.lineDash != null || l?.lineCustomDash != null, d = (c.semanticFallback ? n : r).find((e) => e != null && (e.lineWidthEmu != null || e.lineCap != null || e.lineJoin != null || e.lineDash != null || e.lineCustomDash != null || e.linePaintAuthored === !0 && e.lineNoStyle !== !0)), f = (e) => d ? e(d) ?? null : null, p = d;
	return {
		...c,
		widthEmu: l?.lineWidthEmu ?? t?.lineWidthEmu ?? f((e) => e.lineWidthEmu),
		dash: u ? l?.lineCustomDash == null ? l?.lineDash ?? null : null : p?.lineCustomDash == null ? p?.lineDash ?? null : null,
		customDash: u ? l?.lineCustomDash ?? null : p?.lineCustomDash ?? null,
		cap: l?.lineCap ?? f((e) => e.lineCap),
		join: l?.lineJoin ?? f((e) => e.lineJoin)
	};
}
function qi(e, t, n, r, i, a, o = e.chartexDataPointStyle, s = {}) {
	let c = o === e.chartexDataPointStyle ? [o, e.chartexDataPointLineStyle] : [o];
	if (n = Gi(n, r), !Te(n)) return Ki(e, t, [o], c, r, i, a, s);
	let l = n?.chartexStyle, u = t?.chartexStyle, d = l?.lineDash != null || l?.lineCustomDash != null, f = n?.lineColor != null || n?.lineHidden === !0 || l?.linePaintAuthored === !0 || l?.lineHidden === !0 || l?.linePaints?.some((e) => e != null) === !0 || l?.lineColors?.some((e) => e != null) === !0 ? {
		lineColor: n?.lineColor,
		lineHidden: n?.lineHidden,
		style: l
	} : {
		lineColor: t?.lineColor,
		lineHidden: t?.lineHidden,
		style: u
	};
	return Ki(e, {
		lineColor: f.lineColor,
		lineHidden: f.lineHidden,
		lineWidthEmu: n?.lineWidthEmu ?? t?.lineWidthEmu,
		chartexStyle: {
			...l,
			linePaints: f.style?.linePaints,
			lineColors: f.style?.lineColors,
			lineColorIndex: f.style?.lineColorIndex,
			linePaintAuthored: f.style?.linePaintAuthored,
			lineHidden: f.style?.lineHidden,
			lineNoStyle: f.style?.lineNoStyle,
			lineWidthEmu: l?.lineWidthEmu ?? n?.lineWidthEmu ?? u?.lineWidthEmu ?? t?.lineWidthEmu,
			lineDash: d ? l?.lineDash : u?.lineDash,
			lineCustomDash: d ? l?.lineCustomDash : u?.lineCustomDash,
			lineCap: l?.lineCap ?? u?.lineCap,
			lineJoin: l?.lineJoin ?? u?.lineJoin
		}
	}, [o], c, r, i, a, s);
}
function Ji(e, t, n, r, i = 0) {
	if (!t.visible) return !1;
	if (t.paint && t.paint.fillType !== "solid" && r) {
		let n = jn(t.paint, e, r.x, r.y, r.w, r.h, i);
		if (!n) return !1;
		e.strokeStyle = n;
	} else e.strokeStyle = t.color.startsWith("#") ? t.color : `#${t.color}`;
	return e.lineWidth = t.widthEmu == null ? t.semanticFallback ? 1 : nt(Hi, n) : nt(t.widthEmu, n), e.setLineDash(Zn(t.customDash, t.dash, e.lineWidth)), e.lineCap = t.cap === "rnd" ? "round" : t.cap === "sq" ? "square" : "butt", e.lineJoin = t.join === "round" || t.join === "bevel" ? t.join : "miter", !0;
}
function Yi(e, t, n, r, i, a, o, s, c = {}) {
	return Ji(e, Vi(t, n, r, i, a, o, c), s);
}
function Xi(e, t, n, r, i, a, o, s = !1, c = !0, l = !1) {
	let u = Ki(e, n, [r], r === e.chartexDataPointStyle ? [r, e.chartexDataPointLineStyle] : [r], i, a, o, { linkedNoStyleFallback: s }), d = c && !(l && Wi(u));
	return {
		...f(t, n ?? void 0),
		color: o.replace(/^#/, ""),
		lineHidden: !d || !u.visible,
		lineColor: d && u.visible ? u.color.replace(/^#/, "") : null,
		lineWidthEmu: d ? u.widthEmu ?? (u.visible && !u.semanticFallback ? 9525 : null) : null,
		chartexStyle: {
			linePaints: d && u.paint !== void 0 ? [u.paint] : null,
			linePaintAuthored: d && u.paint !== void 0 ? !0 : null,
			lineDash: d ? u.dash : null,
			lineCustomDash: d ? u.customDash : null,
			lineCap: d ? u.cap : null,
			lineJoin: d ? u.join : null
		}
	};
}
var Zi = .8;
function Qi(e, t, n, r, i) {
	let a = e.chartexDataPointStyle, o = Gi(t, n), s = qi(e, o, void 0, n, r, i, a, { linkedNoStyleFallback: !0 }), c = o != null && (o.lineColor != null || o.lineHidden === !0 || k(o.chartexStyle, n) !== void 0), l = k(a, n), u = Ge(a, n);
	if (c || !s.visible || s.paint != null || l?.fillType !== "solid" || u?.fillType !== "solid") return {
		line: s,
		darkened: !1
	};
	let d = (e) => e.replace(/^#/, "").toUpperCase(), f = d(l.color);
	if (!/^[0-9A-F]{6}$/.test(f) || f !== d(u.color)) return {
		line: s,
		darkened: !1
	};
	let p = (e) => Math.round(parseInt(f.slice(e, e + 2), 16) * Zi).toString(16).padStart(2, "0");
	return {
		line: {
			...s,
			color: `#${p(0)}${p(2)}${p(4)}`.toUpperCase()
		},
		darkened: !0
	};
}
function $i(e, t, n, r) {
	return Mt(e, t, n, r);
}
function ea(e, n, r, i, a, o, s = Qt(n, r)) {
	let l = n.bubbleSizes?.[i];
	if (l != null && Number.isFinite(l) && l < 0) return n.invertedFillHidden === !0 ? {
		color: "00000000",
		paint: null
	} : n.invertedFill ? {
		color: n.invertedFill.fillType === "solid" ? n.invertedFill.color : o,
		paint: n.invertedFill
	} : s ? {
		color: "FFFFFF",
		paint: void 0
	} : {
		color: "00000000",
		paint: null
	};
	let u = cn(e, "dataPoint"), d = Me(r?.chartexStyle, u, i);
	if (d !== void 0) return {
		color: d?.fillType === "solid" ? d.color : o,
		paint: d
	};
	let f = Math.max(0, Oe(e, n)), p = I(e, "dataPoint", f), m = t(e, f) ? i : a;
	if (r?.fillHidden === !0) {
		let e = c(u);
		if (e !== void 0) return {
			color: "00000000",
			paint: e
		};
	}
	if (r?.color != null) return {
		color: r.color,
		paint: void 0
	};
	let h = n.dataPointColors?.[i];
	if (h != null) return {
		color: h,
		paint: void 0
	};
	let g = Me(n.chartexStyle, u, a);
	if (g !== void 0) return {
		color: g?.fillType === "solid" ? g.color : o,
		paint: g
	};
	if (n.color != null) return {
		color: n.color,
		paint: void 0
	};
	let _ = Fi(e, p, m, n.values.length);
	return _ === void 0 ? {
		color: $i(n, r, i, o),
		paint: st(n, r, i)
	} : {
		color: _?.fillType === "solid" ? _.color : o,
		paint: _
	};
}
function ta(e, n, r, i, a) {
	let o = r?.chartexStyle, s = n.chartexStyle, c = Math.max(0, Oe(e, n)), l = I(e, "dataPoint", c), u = cn(e, "dataPoint"), d = t(e, c) ? i : a, f = l, p = [
		o,
		s,
		f
	], m = r?.lineDash, h;
	if (m == null) {
		for (let e of p) if (e?.lineDash != null || e?.lineCustomDash != null || e?.lineDashAuthored === !0) {
			m = e.lineDash, h = e.lineCustomDash ?? void 0;
			break;
		}
	}
	let g = {
		widthEmu: r?.lineWidthEmu ?? o?.lineWidthEmu ?? n.lineWidthEmu ?? s?.lineWidthEmu ?? f?.lineWidthEmu ?? r?.markerLineWidthEmu ?? n.markerLineWidthEmu,
		dash: m,
		customDash: h,
		cap: o?.lineCap ?? s?.lineCap ?? f?.lineCap,
		join: o?.lineJoin ?? s?.lineJoin ?? f?.lineJoin
	}, _ = $(o, u, i);
	if (_ !== void 0) return {
		color: _?.fillType === "solid" ? _.color : r?.lineColor ?? null,
		paint: _,
		...g
	};
	if (r?.lineHidden === !0) {
		let e = qe(u);
		if (e !== void 0) return {
			color: null,
			paint: e,
			...g
		};
	}
	if (r?.lineColor != null) return {
		color: r.lineColor,
		paint: void 0,
		...g
	};
	let v = $(s, u, a);
	if (v !== void 0) return {
		color: v?.fillType === "solid" ? v.color : n.lineColor ?? null,
		paint: v,
		...g
	};
	if (n.lineHidden === !0) {
		let e = qe(u);
		if (e !== void 0) return {
			color: null,
			paint: e,
			...g
		};
	}
	if (n.lineColor != null) return {
		color: n.lineColor,
		paint: void 0,
		...g
	};
	let y = Pi(e, l, d, n.values.length);
	if (y !== void 0) return {
		color: y?.fillType === "solid" ? y.color : null,
		paint: y,
		...g
	};
	let b = n.bubbleSizes?.[i], x = b != null && Number.isFinite(b) && b < 0 && Qt(n, r) ? "000000" : null;
	return {
		color: r?.markerLine ?? n.markerLine ?? n.lineColor ?? x,
		paint: void 0,
		...g
	};
}
function na(e, t, n, r) {
	let i = e.globalCompositeOperation, a = e.fillStyle;
	e.save(), e.clip();
	let o = (i) => {
		e.globalCompositeOperation = "source-atop", e.fillStyle = i, e.fillRect(t - r / 2, n - r / 2, r, r);
	}, s = t - r * .08, c = n - r * .17, l = e.createRadialGradient(s, c, 0, s, c, r * .55);
	l.addColorStop(0, "rgba(255,255,255,0.72)"), l.addColorStop(.14, "rgba(255,255,255,0.48)"), l.addColorStop(.38, "rgba(255,255,255,0.1)"), l.addColorStop(1, "rgba(255,255,255,0)"), o(l);
	let u = t - r * .08, d = n - r * .18, f = e.createRadialGradient(u, d, 0, u, d, r * .78);
	f.addColorStop(0, "rgba(0,0,0,0)"), f.addColorStop(.3, "rgba(0,0,0,0)"), f.addColorStop(.46, "rgba(0,0,0,0.22)"), f.addColorStop(.66, "rgba(0,0,0,0.48)"), f.addColorStop(1, "rgba(0,0,0,0.62)"), o(f);
	let p = t - r * .2, m = n - r * .45, h = e.createRadialGradient(p, m, 0, p, m, r);
	h.addColorStop(0, "rgba(255,255,255,0)"), h.addColorStop(.76, "rgba(255,255,255,0)"), h.addColorStop(.82, "rgba(255,255,255,0.05)"), h.addColorStop(.87, "rgba(255,255,255,0.12)"), h.addColorStop(.95, "rgba(255,255,255,0.28)"), h.addColorStop(1, "rgba(255,255,255,0)"), o(h), e.globalCompositeOperation = i, e.fillStyle = a, e.restore();
}
function ra(e, n, r, i, a, o, s, c, l, u, d, f, p, m, h, g = void 0, _ = void 0, v = void 0, y = void 0, b = void 0, x = !1, S = !1) {
	let C = S ? de(i?.chartexStyle) : de(i?.markerStyle, i?.chartexStyle), w = de(S ? r.chartexStyle : r.markerStyle), T = C ?? w, E = Math.max(0, Oe(n, r)), D = r.chartexFormatIdx ?? E, O = t(n, E), k = C ? a : D, A = I(n, "dataPointMarker", E), j = cn(n, "dataPointMarker"), ee = O ? a : D, M = Me(i?.markerStyle, j, a), N = Me(r.markerStyle, j, D), ne = M !== void 0 || i?.markerFill != null || i?.markerFillPaintAuthored === !0 && i.markerStyle?.fillHidden !== !0, P = N !== void 0 || r.markerFill != null || r.markerFillPaintAuthored === !0 && r.markerStyle?.fillHidden !== !0, re = u, ie = m;
	if (!S && !ne && !P) {
		let e = te(A, j, ee, i?.markerStyle?.shapePropertiesPresent === !0 ? i.markerStyle : r.markerStyle);
		e === null ? (re = "00000000", ie = null) : e?.fillType === "solid" ? (re = e.color, ie = void 0) : e !== void 0 && (ie = e);
	}
	let F = $(i?.markerStyle, j, a), ae = $(r.markerStyle, j, D), oe = d, se = g;
	if (!S && se === void 0) if (F !== void 0) se = F?.fillType === "solid" ? void 0 : F;
	else if (i?.markerLine != null) se = void 0;
	else if (i?.markerLinePaintAuthored === !0 && i.markerStyle?.lineHidden !== !0 && (i.markerLine == null || i.markerLine === "00000000")) se = null;
	else if (ae !== void 0) se = ae?.fillType === "solid" ? void 0 : ae;
	else if (r.markerLine != null) se = void 0;
	else if (r.markerLinePaintAuthored === !0 && r.markerStyle?.lineHidden !== !0 && (r.markerLine == null || r.markerLine === "00000000")) se = null;
	else {
		let e = Ze(A, j, ee, i?.markerStyle?.shapePropertiesPresent === !0 ? i.markerStyle : r.markerStyle);
		e?.fillType === "solid" ? (oe = e.color, se = void 0) : (se = e, e === null && (oe = null));
	}
	let ce = S ? void 0 : A, L = p ?? (() => {
		let e = i?.markerStyle?.lineWidthEmu ?? r.markerStyle?.lineWidthEmu ?? ce?.lineWidthEmu;
		return e == null ? void 0 : nt(e, f);
	})(), R = Tt(_ != null || v != null ? {
		lineDash: _,
		lineCustomDash: v,
		lineDashAuthored: !0
	} : void 0, i?.markerStyle, r.markerStyle, ce), z = R?.lineDash, le = R?.lineCustomDash, ue = y ?? i?.markerStyle?.lineCap ?? r.markerStyle?.lineCap ?? ce?.lineCap, fe = b ?? i?.markerStyle?.lineJoin ?? r.markerStyle?.lineJoin ?? ce?.lineJoin, pe = S ? I(n, x ? "dataPoint3D" : "dataPoint", E) : A, me = S && t(n, E) || O ? a : D;
	aa(e, o, s, c, l, re, oe, f, L, ie, h, se, z, le, ue, fe, x, T, pe, k, me);
}
function ia(e, t, n = Math.max(0, Oe(e, t))) {
	return Re(t) || I(e, "dataPointMarker", n) != null;
}
function aa(e, t, n, r, i, a, o, s, c = 1, l = void 0, u = 0, d = void 0, f = void 0, p = void 0, m = void 0, h = void 0, g = !1, _ = void 0, v = void 0, y = 0, b = y) {
	let x = Math.max(2, i * s), S = x / 2;
	if (_ !== void 0 || v !== void 0) {
		Pe(e, _, v, y, {
			x: t - S,
			y: n - S,
			w: x,
			h: x
		}, s, (e) => aa(e, t, n, r, i, a, o, s, c, l, u, d, f, p, m, h, g), b);
		return;
	}
	let C = a.startsWith("#") ? a : `#${a}`, w = o ? o.startsWith("#") ? o : `#${o}` : null;
	e.save(), e.fillStyle = l === void 0 ? C : l == null ? "rgba(0,0,0,0)" : jn(l, e, t - S, n - S, x, x, u) ?? "rgba(0,0,0,0)";
	let T = d === void 0 ? w : d == null ? null : jn(d, e, t - S, n - S, x, x, u), E = T != null;
	T && (e.strokeStyle = T, e.lineWidth = c, e.setLineDash(Zn(p, f, c)), e.lineCap = m === "rnd" ? "round" : m === "sq" ? "square" : "butt", e.lineJoin = h === "round" || h === "bevel" ? h : "miter");
	let D = l?.fillType === "image" ? l : void 0, O = () => {
		if (!D) {
			l !== null && e.fill();
			return;
		}
		e.save(), e.clip(), Vn(e, D, t - S, n - S, x, x, s, u), e.restore();
	}, k = () => {
		g && l !== null && na(e, t, n, x);
	};
	switch (r) {
		case "square":
			D || g ? (e.beginPath(), e.rect(t - S, n - S, x, x), O(), k()) : l !== null && e.fillRect(t - S, n - S, x, x), E && e.strokeRect(t - S, n - S, x, x);
			break;
		case "diamond":
			e.beginPath(), e.moveTo(t, n - S), e.lineTo(t + S, n), e.lineTo(t, n + S), e.lineTo(t - S, n), e.closePath(), O(), k(), E && e.stroke();
			break;
		case "triangle":
			e.beginPath(), e.moveTo(t, n - S), e.lineTo(t + S, n + S), e.lineTo(t - S, n + S), e.closePath(), O(), k(), E && e.stroke();
			break;
		case "x":
			e.strokeStyle = T ?? e.fillStyle, e.lineWidth = Math.max(1, x * .18), e.beginPath(), e.moveTo(t - S, n - S), e.lineTo(t + S, n + S), e.moveTo(t - S, n + S), e.lineTo(t + S, n - S), e.stroke();
			break;
		case "plus":
			e.strokeStyle = T ?? e.fillStyle, e.lineWidth = Math.max(1, x * .18), e.beginPath(), e.moveTo(t - S, n), e.lineTo(t + S, n), e.moveTo(t, n - S), e.lineTo(t, n + S), e.stroke();
			break;
		case "star":
			e.beginPath();
			for (let r = 0; r < 10; r++) {
				let i = r % 2 == 0 ? S : S * .45, a = -Math.PI / 2 + r * Math.PI / 5, o = t + Math.cos(a) * i, s = n + Math.sin(a) * i;
				r === 0 ? e.moveTo(o, s) : e.lineTo(o, s);
			}
			e.closePath(), O(), k(), E && e.stroke();
			break;
		case "dot":
			e.beginPath(), e.ellipse(t, n, x * .25, x * .1, 0, 0, Math.PI * 2), O(), k(), E && e.stroke();
			break;
		case "dash": {
			let r = x * .2;
			D || g ? (e.beginPath(), e.rect(t - S, n - r / 2, x, r), O(), k()) : l !== null && e.fillRect(t - S, n - r / 2, x, r), E && e.strokeRect(t - S, n - r / 2, x, r);
			break;
		}
		case "picture":
			e.beginPath(), e.rect(t - S, n - S, x, x), D && Vn(e, D, t - S, n - S, x, x, s, u), k(), E && e.strokeRect(t - S, n - S, x, x), e.restore();
			return;
		default:
			e.beginPath(), e.arc(t, n, S, 0, Math.PI * 2), O(), k(), E && e.stroke();
			break;
	}
	e.restore();
}
function oa(e, t, n, r, i, a, o) {
	if (![
		n,
		r,
		i
	].every(Number.isFinite) || n <= 0 || r <= 0 || t.w <= 0 || t.h <= 0) return null;
	let s = Math.max(4, i * .5), c = Math.min(n, Math.max(0, t.w - s * 2)), l = Math.min(r, t.h), u = t.x + t.w * .75, d = {
		x: o ? Math.max(t.x, Math.min(t.x + t.w - c, u - c)) : Math.max(t.x, t.x + t.w - s - c),
		y: o ? Math.max(t.y, Math.min(t.y + t.h - l, o.y - l + i * .25)) : Math.min(t.y + t.h - l, t.y + s),
		w: c,
		h: l
	};
	if (a) {
		let t = ht(a, e, d);
		if (t) return {
			...t,
			automatic: !1
		};
	}
	return {
		...d,
		automatic: !0
	};
}
function sa(e, t) {
	return t && t.trim().toLowerCase() !== "general" ? h(e, t) : ze(Number(e.toPrecision(6)));
}
function ca(e, t, n) {
	if (e.labelText) return e.labelText.split(/\r?\n/);
	if (!t) return [];
	let r = e.labelFormatSourceLinked === !0 ? n : e.labelFormatCode, i = [];
	if (e.dispEq) {
		let e = t.intercept < 0 ? "−" : "+";
		i.push(`y = ${sa(t.slope, r)}x ${e} ${sa(Math.abs(t.intercept), r)}`);
	}
	return e.dispRSqr && i.push(`R² = ${sa(t.rSquared, r)}`), i;
}
function la(e, t, n, r, i, a) {
	if (!i) return;
	let o = ca(t, n, a);
	if (o.length === 0) return;
	let { chart: s, chartRect: c, plotRect: u } = i, d = Rt(t.labelFontSizeHpt, r) ?? Rt(s.dataLabelFontSizeHpt, r) ?? 10 * r, f = Y(s, t.labelFontFace ?? s.dataLabelFontFace, "minor"), p = t.labelFontBold ?? s.dataLabelFontBold ?? !1, m = t.labelFontItalic ?? !1;
	e.font = rr(d, f, p, m);
	let h = d * 1.2, g = t.labelFontColor ?? s.dataLabelFontColor, _ = t.labelRichRuns?.length ? jr(e, {
		runs: t.labelRichRuns,
		ptToPx: r,
		fontFamily: f,
		fallbackBold: p,
		fallbackItalic: m,
		fallbackBaseline: t.labelFontBaseline ?? void 0,
		fallbackColorHidden: t.labelFontPaintAuthored === !0 && (t.labelFontHidden === !0 || t.labelFontColor == null),
		fontFamilyForFace: (e) => Y(s, e, "minor")
	}, d, g ? `#${g}` : "#595959") : null, v = _?.width ?? Math.max(...o.map((t) => e.measureText(t).width)), y = {
		fontColor: t.labelFontColor ?? void 0,
		fontItalic: m,
		fontPaintAuthored: t.labelFontPaintAuthored ?? void 0,
		fontHidden: t.labelFontHidden ?? void 0,
		fontLanguage: t.labelFontLanguage ?? void 0,
		fontBaseline: t.labelFontBaseline ?? void 0,
		textRotation: t.labelTextRotation ?? void 0,
		textWrap: t.labelTextWrap ?? void 0,
		textVerticalAnchor: t.labelTextVerticalAnchor ?? void 0,
		textVerticalMode: t.labelTextVerticalMode ?? void 0,
		textLInsEmu: t.labelTextLInsEmu ?? void 0,
		textTInsEmu: t.labelTextTInsEmu ?? void 0,
		textRInsEmu: t.labelTextRInsEmu ?? void 0,
		textBInsEmu: t.labelTextBInsEmu ?? void 0,
		textBodyAuthored: t.labelTextBodyAuthored ?? void 0
	}, b = re(y, r), x = v + b.left + b.right, S = (_?.height ?? o.length * h) + b.top + b.bottom, C = l(x, S, t.labelTextRotation ?? void 0, t.labelTextVerticalMode ?? void 0), w = oa(c, u, C.w, C.h, d, t.labelManualLayout, i.automaticAnchor);
	if (!w) return;
	e.save(), w.automatic && (e.beginPath(), e.rect(u.x, u.y, u.w, u.h), e.clip());
	let T = w.x + w.w / 2, E = w.y + w.h / 2, D = t.labelTextBodyAuthored === !0 || t.labelTextRotation != null || t.labelTextWrap != null || t.labelTextVerticalAnchor != null || t.labelTextVerticalMode != null || t.labelTextLInsEmu != null || t.labelTextTInsEmu != null || t.labelTextRInsEmu != null || t.labelTextBInsEmu != null, O = w.automatic ? {
		x: T - x / 2,
		y: E - S / 2,
		w: x,
		h: S
	} : {
		x: w.x,
		y: w.y,
		w: w.w,
		h: w.h
	}, k = t.labelBox;
	Z(e, k, O, r, i.shapeRotationDeg ?? 0);
	let A = t.labelTextAlign;
	e.textAlign = A === "r" ? "right" : A === "ctr" ? "center" : "left", e.textBaseline = "top", e.fillStyle = g ? `#${g}` : "#595959";
	let j = Math.max(0, O.w - b.left - b.right), ee = Math.max(0, O.h - b.top - b.bottom), te = w.automatic && C.radians === 0 && w.w === C.w && w.h === C.h && (t.labelTextWrap == null || t.labelTextWrap === "none"), M = _ ? [] : D && !te ? ar(o.join("\n"), j, ee, h, (t) => e.measureText(t).width, y) : o;
	if (!_ && M.length === 0) {
		e.restore();
		return;
	}
	let N = D ? e.textAlign === "right" ? O.x + O.w - b.right : e.textAlign === "center" ? O.x + (O.w + b.left - b.right) / 2 : O.x + b.left : e.textAlign === "right" ? w.x + w.w : e.textAlign === "center" ? w.x + w.w / 2 : w.x, ne = (t.labelFontBaseline ?? 0) * d, P = D ? t.labelTextVerticalAnchor === "b" ? O.y + O.h - b.bottom - (_?.height ?? M.length * h) : t.labelTextVerticalAnchor === "ctr" ? O.y + (O.h - (_?.height ?? M.length * h) + b.top - b.bottom) / 2 : O.y + b.top : w.y, ie = w.automatic ? M.length : Math.min(M.length, Math.floor(ee / h));
	if (C.radians !== 0 && (e.translate(T, E), e.rotate(C.radians), e.translate(-T, -E)), _) Ar(e, _, N, P, e.textAlign, "top", Math.max(_.width, j));
	else if (!(t.labelFontPaintAuthored === !0 && (t.labelFontHidden === !0 || t.labelFontColor == null))) for (let t = 0; t < ie; t++) e.fillText(D && y.textWrap === "none" ? M[t] : Hn(e, M[t], Math.max(0, j || v)), N, P + t * h - ne);
	e.restore();
}
function ua(e, t, n, r, i, a, s, c, l) {
	let u = t.trendLines;
	if (!u || u.length === 0) return;
	let d = [], f = [];
	for (let e = 0; e < t.values.length; e++) {
		let n = t.values[e], r = s ? s[e] : e;
		n != null && r != null && Number.isFinite(n) && Number.isFinite(r) && (d.push(r), f.push(n));
	}
	if (d.length < 2) return;
	let p = e.getLineDash ? e.getLineDash() : [];
	for (let s of u) {
		let u = Yt(d, f, s.trendlineType, {
			period: s.period,
			order: s.order,
			intercept: s.intercept,
			forward: s.forward,
			backward: s.backward
		});
		if (u.xs.length < 2 || ![...u.xs, ...u.ys].every(Number.isFinite)) continue;
		let p = s.trendlineType === "linear" ? o(d, f, s.intercept) : null, m = p && [
			p.slope,
			p.intercept,
			p.rSquared
		].every(Number.isFinite) ? p : null, h = u.xs, g = u.ys;
		if (s.trendlineType === "linear") {
			let e = (u.ys[1] - u.ys[0]) / (u.xs[1] - u.xs[0] || 1), t = s.backward ?? 0, n = s.forward ?? 0;
			h = [u.xs[0] - t, u.xs[1] + n], g = [u.ys[0] - e * t, u.ys[1] + e * n];
		}
		if (![...h, ...g].every(Number.isFinite)) continue;
		let _ = h.map((e, t) => l ? l(e, g[t]) : {
			x: r(e),
			y: i(g[t])
		});
		if (_.every((e) => Number.isFinite(e.x) && Number.isFinite(e.y))) {
			if (!s.lineHidden && (s.linePaintAuthored !== !0 || s.lineColor != null)) {
				c?.clipLineToPlot && (e.save(), e.beginPath(), e.rect(c.plotRect.x, c.plotRect.y, c.plotRect.w, c.plotRect.h), e.clip()), e.strokeStyle = s.lineColor ? `#${s.lineColor}` : n, e.lineWidth = s.lineWidthEmu ? nt(s.lineWidthEmu, a) : 1.5, e.setLineDash(Ue(s.lineDash ?? void 0, e.lineWidth)), e.beginPath();
				for (let t = 0; t < _.length; t++) {
					let { x: n, y: r } = _[t];
					t === 0 ? e.moveTo(n, r) : e.lineTo(n, r);
				}
				e.stroke(), c?.clipLineToPlot && e.restore();
			}
			la(e, s, m, a, c ? {
				...c,
				automaticAnchor: _.at(-1)
			} : void 0, t.valFormatCode);
		}
	}
	e.setLineDash(p);
}
function da(e) {
	let t = (e) => {
		switch (e) {
			case "exp": return "Exponential";
			case "log": return "Logarithmic";
			case "poly": return "Polynomial";
			case "power": return "Power";
			case "movingAvg": return "Moving Average";
			default: return "Linear";
		}
	}, n = [];
	for (let r of e) for (let e of r.trendLines ?? []) {
		if (e.lineHidden === !0 || e.linePaintAuthored === !0 && e.lineColor == null) continue;
		let i = r.lineColor ?? r.color;
		n.push({
			name: e.name ?? `${t(e.trendlineType)} (${r.name || "Series"})`,
			color: e.lineColor ?? i,
			lineColor: e.lineColor ?? i,
			lineWidthEmu: e.lineWidthEmu,
			lineHidden: !1,
			chartexStyle: { lineDash: e.lineDash },
			values: [],
			seriesType: "line",
			showMarker: !1
		});
	}
	return n;
}
function fa(e) {
	return he(e.chartType, e.series.length, e.varyColors !== !1) || y(e) ? e.series : e.series.flatMap((e) => [e, ...da([e])]);
}
function pa(e) {
	return e && (e === "line" || e === "stackedLine" || e === "stackedLinePct" || e === "radar" || e === "scatter" || e === "stock") ? "line" : "fill";
}
function ma(e, t, n) {
	if (e !== "scatter") return !1;
	let r = t ?? "marker";
	return (r === "marker" || r === "line" || r === "lineMarker" || r === "lineNoMarker" || r === "smooth" || r === "smoothMarker" || r === "smoothNoMarker") && n.lineHidden !== !0;
}
function ha(e, n, r, i, a, o) {
	let s = i[a];
	if (!s) return null;
	let c = o ? Math.max(0, Oe(o, s)) : a, l = o ? q(o, c) : void 0, u = o && l ? gt(o.chartType, l) : s.seriesType ?? e, d = l?.scatterStyle ?? n, f = l?.radarStyle ?? r, p = l?.kind === "bubble" ? "bubble" : u, h = p === "stock", g = p === "line" || p === "stackedLine" || p === "stackedLinePct" || p === "radar" || h, _ = p === "bubble", v = p === "scatter" || _;
	if (!g && !v || !m(u, d, l ? {
		...s,
		seriesType: u
	} : s, f)) return null;
	let y = s.markerSymbol ?? s.automaticMarkerSymbol ?? (h ? "none" : "circle"), b = fe(c, s), x = Yn(s, b.replace(/^#/, "")), S = _ ? !1 : v ? ma("scatter", d, s) : s.lineHidden !== !0;
	if (_ && o) {
		let e = Ie(s, c), n = ea(o, s, void 0, 0, e, b), r = ta(o, s, void 0, 0, e);
		return {
			symbol: "circle",
			fill: n.color,
			fillPaint: n.paint,
			line: r.color,
			lineWidthEmu: r.widthEmu ?? null,
			linePaint: r.paint,
			lineDash: r.dash,
			lineCustomDash: r.customDash,
			lineCap: r.cap,
			lineJoin: r.join,
			bubble3D: Qt(s, void 0),
			withLine: !1,
			directEffect: de(s.chartexStyle),
			fallbackEffect: I(o, Qt(s, void 0) ? "dataPoint3D" : "dataPoint", c),
			directEffectIndex: e,
			fallbackEffectIndex: t(o, c) ? 0 : e
		};
	}
	return o ? _a(o, s, void 0, 0, c, p, d, f) : {
		symbol: y,
		fill: x,
		fillPaint: U(s),
		line: s.markerLine ?? null,
		lineWidthEmu: s.markerLineWidthEmu ?? null,
		withLine: S
	};
}
function ga(e, n, r, i) {
	let a = Math.max(0, Oe(e, n)), o = fe(a, n), s = Ie(n, a), c = ea(e, n, r, i, s, o), l = ta(e, n, r, i, s), u = de(r?.chartexStyle), d = de(n.chartexStyle), f = Qt(n, r);
	return {
		symbol: "circle",
		fill: c.color,
		fillPaint: c.paint,
		line: l.color,
		lineWidthEmu: l.widthEmu ?? null,
		linePaint: l.paint,
		lineDash: l.dash,
		lineCustomDash: l.customDash,
		lineCap: l.cap,
		lineJoin: l.join,
		bubble3D: f,
		withLine: !1,
		directEffect: u ?? d,
		fallbackEffect: I(e, f ? "dataPoint3D" : "dataPoint", a),
		directEffectIndex: u ? i : s,
		fallbackEffectIndex: t(e, a) ? i : s
	};
}
function _a(e, n, r, i, a, o, s, c) {
	let l = o === "line" || o === "stackedLine" || o === "stackedLinePct" || o === "radar" || o === "stock";
	if (!l && o !== "scatter") return null;
	let u = rn(n, r, "circle", m(o, s, n, c));
	if (u === "none") return null;
	let d = fe(a, n).replace(/^#/, ""), f = n.chartexFormatIdx ?? a, p = I(e, "dataPointMarker", a), h = cn(e, "dataPointMarker"), g = t(e, a) ? i : f, _ = Me(r?.markerStyle, h, i), v = Me(n.markerStyle, h, f), y = _ !== void 0 || r?.markerFill != null || r?.markerFillPaint !== void 0 || r?.markerFillPaintAuthored === !0 && r.markerStyle?.fillHidden !== !0, b = v !== void 0 || n.markerFill != null || n.markerFillPaint !== void 0 || n.markerFillPaintAuthored === !0 && n.markerStyle?.fillHidden !== !0, x = r ? Mt(n, r, i, d) : Yn(n, d), S = r ? st(n, r, i) : U(n);
	if (_ === null || _ === void 0 && v === null) x = "00000000", S = null;
	else if (!y && !b) {
		let e = Ge(p, g);
		e === null ? (x = "00000000", S = null) : e?.fillType === "solid" ? (x = e.color, S = void 0) : e !== void 0 && (S = e);
	}
	let C = $(r?.markerStyle, h, i), w = $(n.markerStyle, h, f), T = r?.markerLine ?? n.markerLine ?? null, E;
	if (C !== void 0) C?.fillType === "solid" ? T = C.color : E = C;
	else if (r?.markerLinePaintAuthored === !0 && r.markerStyle?.lineHidden !== !0 && r.markerLine == null) E = null;
	else if (w !== void 0) w?.fillType === "solid" ? T = w.color : E = w;
	else if (n.markerLinePaintAuthored === !0 && n.markerStyle?.lineHidden !== !0 && n.markerLine == null) E = null;
	else if (r?.markerLine == null && n.markerLine == null) {
		let e = k(p, g);
		e?.fillType === "solid" ? T = e.color : (E = e, e === null && (T = null));
	}
	let D = p, O = Tt(r?.markerStyle, n.markerStyle, D), A = de(r?.markerStyle, r?.chartexStyle), j = de(n.markerStyle);
	return {
		symbol: u,
		fill: x,
		fillPaint: S,
		line: T,
		linePaint: E,
		lineWidthEmu: r?.markerLineWidthEmu ?? r?.markerStyle?.lineWidthEmu ?? n.markerLineWidthEmu ?? n.markerStyle?.lineWidthEmu ?? D?.lineWidthEmu ?? null,
		lineDash: O?.lineDash,
		lineCustomDash: O?.lineCustomDash,
		lineCap: r?.markerStyle?.lineCap ?? n.markerStyle?.lineCap ?? D?.lineCap,
		lineJoin: r?.markerStyle?.lineJoin ?? n.markerStyle?.lineJoin ?? D?.lineJoin,
		withLine: l || ma("scatter", s, n),
		directEffect: A ?? j,
		fallbackEffect: p,
		directEffectIndex: A ? i : f,
		fallbackEffectIndex: g
	};
}
function va(e, t, n, r, i, a, o, s = null, c = void 0, l = void 0, u = null, d = null, f = null, p = void 0, m = null, h = null, g = 1, _ = 0, v, y, b = 0, x = b) {
	if (t !== "none") {
		if (s && !s.withLine) {
			aa(e, r + a / 2, i + o / 2, s.symbol, o * .58 / g, s.fill, s.line, g, s.lineWidthEmu == null ? void 0 : nt(s.lineWidthEmu, g), s.fillPaint, _, s.linePaint, s.lineDash, s.lineCustomDash, s.lineCap, s.lineJoin, s.bubble3D, s.directEffect, s.fallbackEffect, s.directEffectIndex, s.fallbackEffectIndex);
			return;
		}
		Pe(e, v, y, b, {
			x: r,
			y: i,
			w: a,
			h: o
		}, g, (e) => ya(e, t, n, r, i, a, o, c, l, u, d, f, p, m, h, g, _), x), s && aa(e, r + a / 2, i + o / 2, s.symbol, o * .58 / g, s.fill, s.line, g, s.lineWidthEmu == null ? void 0 : nt(s.lineWidthEmu, g), s.fillPaint, _, s.linePaint, s.lineDash, s.lineCustomDash, s.lineCap, s.lineJoin, s.bubble3D, s.directEffect, s.fallbackEffect, s.directEffectIndex, s.fallbackEffectIndex);
	}
}
function ya(e, t, n, r, i, a, o, s, c, l, u, d, f, p, m, h, g) {
	if (e.fillStyle = n, t === "line") {
		if (!(c !== void 0 || l != null || u != null || d != null || p != null || m != null)) {
			e.strokeStyle = n;
			let t = e.lineWidth;
			e.lineWidth = Math.max(1.5, o * .15), e.beginPath();
			let s = i + o / 2;
			e.moveTo(r, s), e.lineTo(r + a, s), e.stroke(), e.lineWidth = t;
			return;
		}
		if (c === null) return;
		e.save();
		let t = c ? jn(c, e, r, i, a, o, g) : l ? `#${l}` : n;
		if (!t) {
			e.restore();
			return;
		}
		e.strokeStyle = t, e.lineWidth = u == null ? Math.max(1.5, o * .15) : nt(u, h), e.setLineDash(Fe(f, d, e.lineWidth)), e.lineCap = p === "rnd" ? "round" : p === "sq" ? "square" : "butt", e.lineJoin = m === "round" || m === "bevel" ? m : "miter", e.beginPath();
		let s = i + o / 2;
		e.moveTo(r, s), e.lineTo(r + a, s), e.stroke(), e.restore();
	} else {
		Bi(e, s, {
			x: r,
			y: i,
			w: a,
			h: o
		}, n, h, g);
		let t = c === null ? null : c ? jn(c, e, r, i, a, o, g) : l ? `#${l}` : null;
		if (t) {
			let n = nt(u, h);
			e.save(), e.strokeStyle = t, e.lineWidth = n, e.setLineDash(Fe(f, d, e.lineWidth)), e.lineCap = p === "rnd" ? "round" : p === "sq" ? "square" : "butt", e.lineJoin = m === "round" || m === "bevel" ? m : "miter", e.strokeRect(r + n / 2, i + n / 2, Math.max(0, a - n), Math.max(0, o - n)), e.restore();
		}
	}
}
function ba(e, t) {
	if (t.length === 0) return [...e];
	let n = /* @__PURE__ */ new Map();
	for (let e of t) n.set(e.idx, e);
	let r = [];
	for (let t = 0; t < e.length; t++) {
		let i = n.get(t);
		i?.deleted !== !0 && r.push({
			...e[t],
			textOverride: i ?? null
		});
	}
	return r;
}
function xa(e, n, r, i = !1, a = [], o = [], s = !0, c = [], l, u) {
	let d = he(n, e.length, s);
	if (i || d) {
		let t = e[0], f = t ? t.values.length : 0, p = t?.categories ?? a, m = u && t ? Math.max(0, Oe(u, t)) : 0, h = u ? q(u, m) : void 0, g = h?.kind === "bubble" ? "bubble" : t?.seriesType ?? n, _ = h?.scatterStyle ?? r, v = h?.radarStyle ?? l, y = new Map(t?.dataPointOverrides?.map((e) => [e.idx, e]) ?? []);
		return ba(Array.from({ length: f }, (r, a) => {
			let c = y.get(a), l = u && t ? Ye(u, t, m, a) : a, f = pa(g), h = u && t ? B(u, f === "line" ? "dataPointLine" : "dataPoint", t, c, l) : void 0, b = a < o.length ? o[a] : u && t ? me(u, t, c, l, a) : c?.fillHidden === !0 ? null : c?.color || t?.dataPointColors?.[a] ? void 0 : t?.fillPattern ?? void 0, x = (g === "bubble" && u && t ? ga(u, t, c, a) : null) ?? (u && t ? _a(u, t, c, a, m, g, _, v) : null), S = de(c?.chartexStyle), C = de(t?.chartexStyle), w = u && t ? I(u, f === "line" ? "dataPointLine" : "dataPoint", m) : void 0, T = d && t ? N(a, t, s, 0) : za(n, e, a, i, s);
			return {
				label: (p[a] ?? `Item ${a + 1}`).toString(),
				color: b?.fillType === "solid" ? b.color.startsWith("#") ? b.color : `#${b.color}` : T,
				marker: x,
				swatchStyle: f,
				fillPaint: b,
				outlinePaint: h?.paint?.fillType === "solid" ? void 0 : h?.paint,
				outlineColor: h?.paint?.fillType === "solid" ? h.paint.color : c?.lineColor ?? t?.lineColor ?? null,
				outlineWidthEmu: h?.widthEmu ?? null,
				outlineDash: h?.dash ?? null,
				outlineCustomDash: h?.customDash,
				outlineCap: h?.cap ?? null,
				outlineJoin: h?.join ?? null,
				directEffect: S ?? C,
				fallbackEffect: w,
				directEffectIndex: S ? a : t ? Ie(t, m) : l,
				fallbackEffectIndex: l,
				textOverride: null
			};
		}), c);
	}
	return u && e.some((e) => {
		let n = Oe(u, e), r = q(u, n);
		return n >= 0 && r?.kind !== "pie" && r?.kind !== "pie3D" && r?.kind !== "doughnut" && r?.kind !== "ofPie" && t(u, n);
	}) ? ba(e.flatMap((e) => {
		let i = Oe(u, e), o = q(u, i), c = o?.kind === "bubble" ? "bubble" : e.seriesType ?? n;
		return i >= 0 && o?.kind !== "pie" && o?.kind !== "pie3D" && o?.kind !== "doughnut" && o?.kind !== "ofPie" && t(u, i) ? xa([e], c, o?.scatterStyle ?? r, !0, e.categories ?? a, [], s, [], o?.radarStyle ?? l, u) : xa([e], c, o?.scatterStyle ?? r, !1, e.categories ?? a, [], s, [], o?.radarStyle ?? l, u);
	}), c) : ba(e.map((t, i) => {
		let a = u ? Oe(u, t, i) : i, c = (u ? q(u, a) : void 0)?.kind === "bubble" ? "bubble" : t.seriesType ?? n, d = t.lineHidden !== !0, f = d ? t.lineColor ?? null : null, p = ha(n, r, l, e, i, u), m = c === "stock" && !d && !p ? "none" : pa(c), h = Ie(t, a >= 0 ? a : i), g = i < o.length ? o[i] : u ? me(u, t, void 0, h) : Ge(t.chartexStyle, t.chartexFormatIdx ?? i) ?? t.fillPattern ?? void 0, _ = u ? B(u, m === "line" ? "dataPointLine" : "dataPoint", t, void 0, h) : void 0, v = u ? I(u, m === "line" ? "dataPointLine" : "dataPoint", a) : void 0;
		return {
			label: t.name || `Series ${i + 1}`,
			color: m === "line" && _?.paint?.fillType === "solid" ? _.paint.color.startsWith("#") ? _.paint.color : `#${_.paint.color}` : m === "line" && f ? `#${f}` : g?.fillType === "solid" ? g.color.startsWith("#") ? g.color : `#${g.color}` : za(n, e, i, !1, s),
			marker: p,
			swatchStyle: m,
			fillPaint: g,
			outlinePaint: _?.paint?.fillType === "solid" ? void 0 : _?.paint,
			outlineColor: _?.paint?.fillType === "solid" ? _.paint.color : f,
			outlineWidthEmu: _?.widthEmu ?? (d ? t.lineWidthEmu ?? null : null),
			outlineDash: _?.dash ?? (d ? t.chartexStyle?.lineDash ?? null : null),
			outlineCustomDash: _?.customDash,
			outlineCap: _?.cap ?? (d ? t.chartexStyle?.lineCap ?? null : null),
			outlineJoin: _?.join ?? (d ? t.chartexStyle?.lineJoin ?? null : null),
			directEffect: de(t.chartexStyle),
			fallbackEffect: v,
			directEffectIndex: h,
			fallbackEffectIndex: h,
			textOverride: null
		};
	}), c);
}
function Sa(e, n, r = 0) {
	let i = y(e) || he(e.chartType, e.series.length, e.varyColors !== !1), a = xa(e.series, e.chartType, e.scatterStyle, y(e), e.categories, [], e.varyColors !== !1, [], e.radarStyle, e), o = Wt(e);
	return (s, c) => {
		let l = i || t(e, s), u = i ? c : Jt(o, s, l ? c : 0), d = u == null ? void 0 : a[u];
		return d ? {
			entry: d,
			ptToPx: n,
			shapeRotationDeg: r
		} : void 0;
	};
}
function Ca(e, t, n, r) {
	if (!n) return t;
	let i = tr(e, n.fontFace) ?? n.fontFace;
	return {
		fontFamily: i ? Kn(i) : t.fontFamily,
		color: n.fontColor ? `#${n.fontColor}` : t.color,
		bold: n.fontBold ?? t.bold,
		italic: n.fontItalic ?? t.italic,
		sizePx: Rt(n.fontSizeHpt, r) ?? t.sizePx
	};
}
function wa(e, t, n) {
	let r = Aa(t, n);
	return e.font = `${t.italic ? "italic " : ""}${t.bold ? "bold " : ""}${r}px ${t.fontFamily}`, r;
}
var Ta = {
	fontFamily: "sans-serif",
	color: "#333",
	bold: !1,
	italic: !1,
	sizePx: null
}, Ea = 12, Da = 4, Oa = 8, ka = .01;
function Aa(e, t) {
	return e.sizePx ?? 10 * t;
}
function ja(e, t, n) {
	return e.map((e) => {
		if (e.swatchStyle === "fill") return 7 * n;
		let r = t * 1.6;
		if (e.swatchStyle !== "line" || !e.outlineDash) return r;
		let i = e.outlineWidthEmu == null ? Math.max(1.5, t * .15) : nt(e.outlineWidthEmu, n), a = Ue(e.outlineDash, i), o = a.length > 0 ? a.reduce((e, t) => e + t, 0) + a[0] : 0;
		return Math.max(r, o);
	});
}
function Ma(e, t) {
	return Math.max(0, ...t.map((t, n) => Ot(e[n]?.label ?? "") * t)) + 4;
}
function Na(e, t, n) {
	return e.swatchStyle === "fill" ? 7 * n : t;
}
function Pa(e, t, n) {
	if (!(n > 0)) return [];
	let r = hn(t);
	if (r.length > 1) return r.flatMap((t) => Pa(e, t, n));
	let i = vt(e, t, n);
	return i.length <= 2 ? i : [i[0], Hn(e, i.slice(1).join(" "), n)];
}
function Fa(e, t, n, r, i) {
	if (!t.showLegend) return null;
	let a = La(t, i), o = xa(fa(t), t.chartType, t.scatterStyle, y(t), t.categories, [], t.varyColors !== !1, t.legendEntries ?? [], t.radarStyle, t), s = o.map((e) => Ca(t, a, e.textOverride, i)), c = s.map((e) => Aa(e, i)), l = o.map((e, t) => ja([e], c[t], i)[0]);
	e.save();
	let u = o.map((t, n) => (wa(e, s[n], i), l[n] + 4 + ot(e, t.label)));
	e.restore();
	let d = t.legendPos ?? "r", f = d === "t" || d === "b", p = sn(t, n, r, {
		itemWidths: u,
		rowHeight: Ma(o, c),
		itemGap: Ea,
		horizontalPadding: f ? 8 : Oa,
		verticalPadding: Da
	});
	return p ? {
		...p,
		measuredLabels: o.map((e) => e.label),
		entryStyles: s,
		fontSizes: c,
		swatches: l,
		itemWidths: u
	} : null;
}
function Ia(e, t, n, r, i, a, o = "vertical", s, c = Ta, l, u = !1, d = [], f = 1, p = [], m = 0, h = !0, g, _) {
	let v = xa(t, s, l, u, d, p, h, g?.legendEntries ?? [], g?.radarStyle, g), y = _ != null && _.measuredLabels.length === v.length && _.measuredLabels.every((e, t) => e === v[t].label), b = y ? _.entryStyles : v.map((e) => g ? Ca(g, c, e.textOverride, f) : c), x = y ? _.fontSizes : b.map((e) => Aa(e, f));
	b[0] && wa(e, b[0], f), e.textBaseline = "middle";
	let S = Ma(v, x), C = y ? _.swatches : v.map((e, t) => ja([e], x[t], f)[0]), w = y ? _.itemWidths : v.map((t, n) => (wa(e, b[n], f), C[n] + 4 + ot(e, t.label)));
	if (o === "horizontal") {
		let t = On(w, i, Ea).slice(0, Math.max(0, Math.floor((a - Da) / S))), o = r + Da / 2;
		for (let r = 0; r < t.length; r++) {
			let a = t[r], s = a.map((e) => Math.min(i, w[e])), c = s.reduce((e, t) => e + t, 0) + Ea * Math.max(0, a.length - 1), l = n + Math.max(0, (i - c) / 2), u = o + r * S + S / 2;
			for (let t = 0; t < a.length; t++) {
				let n = a[t], r = C[n], i = s[t];
				if (i < r) {
					l += i + Ea;
					continue;
				}
				let o = Math.max(0, i - r - 4 + ka);
				wa(e, b[n], f);
				let c = Na(v[n], x[n], f);
				va(e, v[n].swatchStyle, v[n].color, l, u - c / 2, r, c, v[n].marker, v[n].fillPaint, v[n].outlinePaint, v[n].outlineColor, v[n].outlineWidthEmu, v[n].outlineDash, v[n].outlineCustomDash, v[n].outlineCap, v[n].outlineJoin, f, m, v[n].directEffect, v[n].fallbackEffect, v[n].directEffectIndex, v[n].fallbackEffectIndex), e.fillStyle = b[n].color, e.textAlign = "left", Pt(e, v[n].label, l + r + 4, u, x[n], o), l += i + Ea;
			}
		}
		return;
	}
	let T = i - Math.max(...C, 0) - 4, E = !u && !he(s, t.length, h), D = v.map((t, n) => (wa(e, b[n], f), E ? Pa(e, t.label, T) : hn(t.label).map((t) => Hn(e, t, T)))), O = D.map((e, t) => e.length * x[t] + 4), k = 0, A = 0;
	for (; k < v.length && A + O[k] <= a;) A += O[k], k++;
	let j = k === v.length ? r + (a - A) / 2 : r;
	for (let t = 0; t < k; t++) {
		let r = C[t], a = O[t];
		if (i < r) {
			j += a;
			continue;
		}
		let o = Na(v[t], x[t], f);
		va(e, v[t].swatchStyle, v[t].color, n, j + (a - o) / 2, r, o, v[t].marker, v[t].fillPaint, v[t].outlinePaint, v[t].outlineColor, v[t].outlineWidthEmu, v[t].outlineDash, v[t].outlineCustomDash, v[t].outlineCap, v[t].outlineJoin, f, m, v[t].directEffect, v[t].fallbackEffect, v[t].directEffectIndex, v[t].fallbackEffectIndex), wa(e, b[t], f), e.fillStyle = b[t].color, e.textAlign = "left", D[t].forEach((i, a) => e.fillText(i, n + r + 4, j + x[t] * (a + .5))), j += a;
	}
}
function La(e, t) {
	let n = tr(e, e.legendFontFace) ?? e.themeMinorFontLatin;
	return {
		fontFamily: n ? Kn(n) : "sans-serif",
		color: e.legendFontColor ? `#${e.legendFontColor}` : "#333",
		bold: e.legendFontBold ?? !1,
		italic: e.legendFontItalic ?? !1,
		sizePx: Rt(e.legendFontSizeHpt, t)
	};
}
function Ra(e, t, n, r, i, a, o, s, c, l, u, d, f, p = [], m = 0) {
	if (!n) return;
	let h = La(t, f), g = fa(t), _ = y(t), v = Math.min(Oa / 2, Math.max(0, n.reserveW) / 2), b = Math.max(0, n.reserveW - v * 2), x = n.side === "r" ? {
		x: r + a - n.reserveW + v,
		y: c,
		w: b,
		h: u
	} : n.side === "l" ? {
		x: r + v,
		y: c,
		w: b,
		h: u
	} : n.side === "t" ? {
		x: r + 4,
		y: i + d,
		w: Math.max(0, a - 8),
		h: n.reserveH
	} : {
		x: r + 4,
		y: i + o - n.reserveH,
		w: Math.max(0, a - 8),
		h: n.reserveH
	}, S = n.side === "t" || n.side === "b" ? "horizontal" : "vertical", C = t.legendManualLayout, w = C ? ht(C, {
		x: r,
		y: i,
		w: a,
		h: o
	}, x) : null;
	if (w) {
		let r = w.w >= w.h ? "horizontal" : "vertical";
		xn(e, t, w, f, m), Ia(e, g, w.x, w.y, w.w, w.h, r, t.chartType, h, t.scatterStyle, _, t.categories, f, p, m, t.varyColors !== !1, t, n);
		return;
	}
	xn(e, t, x, f, m), Ia(e, g, x.x, x.y, x.w, x.h, S, t.chartType, h, t.scatterStyle, _, t.categories, f, p, m, t.varyColors !== !1, t, n);
}
function za(e, t, n, r = !1, i = !0) {
	if (r || he(e, t.length, i)) {
		let e = t[0];
		return e ? N(n, e, i, 0) : `#${D[n % D.length]}`;
	}
	return fe(n, t[n]);
}
function Ba(e) {
	return e.dataTable != null && Rn(e.chartType);
}
function Va(e) {
	return e.catAxisIsDate !== !0 || e.chartType === "clusteredBarH" || e.chartType === "stackedBarH" || e.chartType === "stackedBarHPct";
}
function Ha(e, t) {
	return !Ba(e) || Va(e) ? 0 : e.catAxisHidden || e.catAxisTickLabelPos === "none" || e.catAxisTickLabelPos === "high" ? t : ke(t, e.catAxisLabelOffsetPercent, e.cartesianAutoLayoutProfile);
}
function Ua(e, t, n) {
	return !Va(e) && !e.plotAreaManualLayout ? t + n : t;
}
function Wa(e) {
	let t = e.chartType === "clusteredBarH" || e.chartType === "stackedBarH" || e.chartType === "stackedBarHPct", n = e.series.map((e, t) => ({
		series: e,
		sourceIndex: t
	}));
	return t ? n.reverse() : n;
}
function Ga(e, t, n) {
	let r = t.values[n];
	return r == null ? "" : h(r, t.valFormatCode, e.date1904);
}
function Ka(e, t) {
	let n = Ba(e) ? e.dataTable : null;
	if (!n) return 0;
	let r = Rt(n.fontSizeHpt, t) ?? 9 * t, i = Math.max(1, r * 1.2) + 4 * t;
	return (e.series.length + 1) * i;
}
function qa(e, t, n) {
	let r = Ba(t) ? t.dataTable : null;
	if (!r) return 0;
	let i = Rt(r.fontSizeHpt, n) ?? 9 * n, a = Y(t, r.fontFace, "minor");
	e.save(), e.font = rr(i, a, r.fontBold ?? !1, r.fontItalic ?? !1);
	let o = t.series.reduce((t, n) => Math.max(t, ot(e, n.name)), 0);
	e.restore();
	let s = r.showKeys ? Math.max(12 * n, i * 1.7) : 0, c = r.showKeys ? 4 * n : 0;
	return o + s + c + 6 * n;
}
function Ja(e, t, n, r) {
	let i = Ba(t) ? t.dataTable : null;
	if (!i) return null;
	let a = Rt(i.fontSizeHpt, r) ?? 9 * r, o = Math.max(1, a * 1.2), s = o + 4 * r, c = Y(t, i.fontFace, "minor");
	e.save(), e.font = rr(a, c, i.fontBold ?? !1, i.fontItalic ?? !1);
	let l = t.series.find((e) => e.catFormatCode)?.catFormatCode ?? t.catAxisFormatCode, u = t.series.find((e) => e.catFormatBuiltinId != null)?.catFormatBuiltinId, d = Jn(t).map((i) => {
		let a = i.trim() === "" ? NaN : Number(i);
		return vt(e, u === 14 && Number.isFinite(a) ? Xn(a, t.date1904) : er(i, l, t.date1904), Math.max(1, n - 4 * r));
	});
	e.restore();
	let f = Math.max(1, ...d.map((e) => e.length)) * o + 4 * r, p = Wa(t), m = Jn(t).length, h = p.map(({ series: e }) => {
		let n = Ot(e.name);
		for (let r = 0; r < m; r++) n = Math.max(n, Ot(Ga(t, e, r)));
		return (n - 1) * o;
	}), g = h.reduce((e, t) => e + t, 0);
	return {
		fontPx: a,
		lineHeight: o,
		headerLines: d,
		headerHeight: f,
		rowHeight: s,
		rowExtraHeights: h,
		totalHeight: f + p.length * s + g
	};
}
function Ya(e, t, n, r, i, a, o, s) {
	let c = t.dataTable;
	if (!c || !n) return;
	let l = Jn(t);
	if (l.length === 0) return;
	let u = a / l.length, d = Y(t, c.fontFace, "minor"), f = rr(n.fontPx, d, c.fontBold ?? !1, c.fontItalic ?? !1), p = c.showKeys ? Math.max(12 * s, n.fontPx * 1.7) : 0, m = c.showKeys ? 4 * s : 0;
	e.save(), e.font = f;
	let h = t.series.reduce((t, n) => Math.max(t, ot(e, n.name)), 0) + p + m + 6 * s, g = Math.min(Math.max(0, r - o), h), _ = r - g, v = g + a, y = i + n.totalHeight, b = Wa(t), x = xa(t.series, t.chartType, t.scatterStyle, !1, t.categories, [], !0, [], t.radarStyle, t), S = Wt(t), C = c.fillColor ?? null;
	e.beginPath(), e.rect(_, i, v, n.totalHeight), e.clip();
	let w = c.fontHidden === !0 || c.fontPaintAuthored === !0 && c.fontColor == null ? "transparent" : c.fontColor ? `#${c.fontColor}` : "#000000", T = (e, t, r) => {
		let i = hn(e);
		if (i.length > 1) {
			let e = r - (i.length - 1) * n.lineHeight / 2;
			i.forEach((r, i) => E(r, t, e + i * n.lineHeight));
			return;
		}
		E(i[0], t, r);
	}, E = (t, r, i) => {
		if (C && t !== "") {
			let a = e.measureText(t).width;
			e.fillStyle = `#${C}`, e.fillRect(r - a / 2, i - n.lineHeight / 2, a, n.lineHeight);
		}
		e.fillStyle = w, e.textAlign = "center", e.fillText(t, r, i);
	};
	e.fillStyle = w, e.textAlign = "center", e.textBaseline = "middle";
	for (let e = 0; e < l.length; e++) {
		let t = r + (e + .5) * u, a = n.headerLines[e] ?? [""], o = a.length * n.lineHeight, s = i + (n.headerHeight - o) / 2 + n.lineHeight / 2;
		a.forEach((e, r) => {
			T(e, t, s + r * n.lineHeight);
		});
	}
	let D = 0;
	for (let a = 0; a < b.length; a++) {
		let { series: o, sourceIndex: d } = b[a], f = n.rowExtraHeights[a] ?? 0, h = i + n.headerHeight + a * n.rowHeight + D + (n.rowHeight + f) / 2;
		if (D += f, g > 0) {
			let t = _ + 3 * s + p + m;
			if (e.textAlign = "left", Pt(e, o.name, t, h, n.lineHeight, Math.max(0, r - t - 2 * s)), c.showKeys && p > 0) {
				let t = _ + 3 * s, r = Jt(S, d), i = r == null ? void 0 : x[r];
				if (i) {
					let r = Math.min(n.fontPx, n.rowHeight - 2 * s);
					va(e, i.swatchStyle, i.color, t, h - r / 2, p, r, i.marker, i.fillPaint, i.outlinePaint, i.outlineColor, i.outlineWidthEmu, i.outlineDash, i.outlineCustomDash, i.outlineCap, i.outlineJoin, s, 0, i.directEffect, i.fallbackEffect, i.directEffectIndex, i.fallbackEffectIndex);
				}
				e.fillStyle = w;
			}
		}
		for (let e = 0; e < l.length; e++) T(Ga(t, o, e), r + (e + .5) * u, h);
	}
	if (c.lineHidden !== !0 && (c.linePaintAuthored !== !0 || c.lineColor != null)) {
		if (e.strokeStyle = c.lineColor ? `#${c.lineColor}` : "#808080", e.lineWidth = c.lineWidthEmu == null ? Math.max(.5, s * .75) : nt(c.lineWidthEmu, s), e.setLineDash(Ue(c.lineDash ?? void 0, e.lineWidth)), c.showHorizontalBorder) {
			let t = i + n.headerHeight;
			for (let r = 0; r < b.length; r++) e.beginPath(), e.moveTo(_, t), e.lineTo(_ + v, t), e.stroke(), t += n.rowHeight + (n.rowExtraHeights[r] ?? 0);
		}
		if (c.showVerticalBorder) {
			e.beginPath(), e.moveTo(r, i), e.lineTo(r, y), e.stroke();
			for (let t = 1; t < l.length; t++) {
				let n = r + t * u;
				e.beginPath(), e.moveTo(n, i), e.lineTo(n, y), e.stroke();
			}
		}
		if (c.showOutline) {
			let t = e.lineWidth / 2;
			e.strokeRect(_ + t, i + t, Math.max(0, v - e.lineWidth), Math.max(0, n.totalHeight - e.lineWidth));
		}
	}
	e.restore();
}
function Xa(e, t, n) {
	if (n) return t + 1;
	let r = e[t];
	if (r == null) return null;
	let i = parseFloat(r);
	return Number.isNaN(i) ? null : i;
}
function Za(e, t) {
	return e.bubbleSizeRepresents === "w" ? t : Math.sqrt(t);
}
function Qa(e, t, n, r, i, a, o, s) {
	if (n.hidden === !0 || n.linePaintAuthored === !0 && n.color == null) return;
	e.save(), e.strokeStyle = n.color ? `#${n.color}` : s, e.lineWidth = n.lineWidthEmu ? Math.max(.5, n.lineWidthEmu / qt) : 1, e.setLineDash(Ue(n.dash, e.lineWidth));
	let c = n.barType === "plus" || n.barType === "both", l = n.barType === "minus" || n.barType === "both", u = n.dir === "x", d = e.lineWidth / 2;
	for (let s = 0; s < t.values.length; s++) {
		let f = t.values[s];
		if (f == null) continue;
		let p = Xa(r, s, i);
		if (p == null) continue;
		let m = a(p), h = o(f), g = (t) => {
			let r = m, i = h;
			u ? r = a(p + t) : i = o(f + t), e.beginPath(), e.moveTo(m, h), e.lineTo(r, i), e.stroke(), n.noEndCap || (e.save(), e.setLineDash([]), e.beginPath(), u ? (e.moveTo(r, i - d), e.lineTo(r, i + d)) : (e.moveTo(r - d, i), e.lineTo(r + d, i)), e.stroke(), e.restore());
		};
		if (c) {
			let e = n.plus[s];
			e != null && g(e);
		}
		if (l) {
			let e = n.minus[s];
			e != null && g(-e);
		}
	}
	e.restore();
}
function $a(e, t, n, r, i, a, o, s) {
	if (n.hidden === !0 || n.linePaintAuthored === !0 && n.color == null || n.dir === "x") return;
	let c = n.barType === "plus" || n.barType === "both", l = n.barType === "minus" || n.barType === "both";
	e.save(), e.strokeStyle = n.color ? `#${n.color}` : s, e.lineWidth = n.lineWidthEmu ? Math.max(.5, n.lineWidthEmu / qt) : 1, e.setLineDash(Ue(n.dash, e.lineWidth));
	let u = e.lineWidth / 2;
	for (let s = 0; s < r; s++) {
		if (t.values[s] == null) continue;
		let r = o(s), d = i(s), f = a(r), p = (t) => {
			let i = a(r + t);
			e.beginPath(), e.moveTo(d, f), e.lineTo(d, i), e.stroke(), n.noEndCap || (e.save(), e.setLineDash([]), e.beginPath(), e.moveTo(d - u, i), e.lineTo(d + u, i), e.stroke(), e.restore());
		};
		if (c) {
			let e = n.plus[s];
			e != null && p(e);
		}
		if (l) {
			let e = n.minus[s];
			e != null && p(-e);
		}
	}
	e.restore();
}
function eo(e, t, n) {
	return e.showDataLabelsOverMax === !0 || !Number.isFinite(n) || t <= n;
}
function to(e, t, n, r, i, a, o, s, c = !1, l = "sans-serif", u = "r", d = {
	x: -1e6,
	y: -1e6,
	w: 2e6,
	h: 2e6
}, f = d, p, m, g, _, v = 0, y) {
	let b = t.dataLabelOverrides ?? [], x = cr(b);
	if (b.length === 0 && !t.seriesDataLabels) return;
	let S = t.seriesDataLabels;
	for (let b = 0; b < t.values.length; b++) {
		let C = t.values[b];
		if (C == null || _ && !_(C)) continue;
		let w = Xa(n, b, r);
		if (w == null) continue;
		let T = x.get(b);
		if (ue(S, T)) continue;
		let E = T?.showCatName ?? S?.showCatName, D = T?.showSerName ?? S?.showSerName, O = T?.showVal ?? S?.showVal, k = T?.showBubbleSize ?? S?.showBubbleSize, A = T?.showLegendKey ?? S?.showLegendKey ?? !1, j = mt({
			customText: T?.text,
			showCategory: E,
			showSeries: D,
			showValue: O,
			showBubbleSize: k,
			category: r ? er((n[b] ?? String(w)).toString(), t.catFormatCodes?.[b] ?? t.catFormatCode ?? null, c) : h(w, t.catFormatCodes?.[b] ?? t.catFormatCode ?? null, c),
			seriesName: t.name,
			sourceValue: C,
			bubbleSize: t.bubbleSizes?.[b] ?? void 0,
			valueDivisor: vn(m),
			formatCode: T?.formatCode ?? S?.formatCode ?? null,
			date1904: c,
			separator: T?.separator ?? S?.separator
		}), ee = A ? g?.(b) : void 0;
		if (!j && !ee) continue;
		let te = T?.position ?? S?.position ?? u, M = Rt(T?.fontSizeHpt ?? S?.fontSizeHpt, s) ?? Math.max(9, Math.min(11, o / 25)), N = T?.fontColor ?? S?.fontColor, ne = T?.fontBold ?? S?.fontBold ?? !1, P = T?.fontFace ?? S?.fontFace, re = P && p ? p(P) : l;
		no(e, i(w), a(C), j, te, M, N, ne, re, y?.(b) ?? 0, d, T?.manualLayout, f, T?.richRuns, s, p, ee, Ne(T, S), Wn(T?.labelBox, S?.labelBox), v);
	}
}
function no(e, t, n, r, i, a, o, s, c = "sans-serif", l = 0, u = {
	x: -1e6,
	y: -1e6,
	w: 2e6,
	h: 2e6
}, d, f = u, p, m = 1, h, g, _, v, y = 0) {
	e.save(), e.font = `${_?.fontItalic ? "italic " : ""}${s ? "bold " : ""}${a}px ${c}`, ao(e, r, {
		kind: "point",
		x: t,
		y: n,
		position: i,
		markerGap: l
	}, u, a, o ? `#${o}` : "#333", d, f, p && p.length > 0 ? {
		runs: p,
		ptToPx: m,
		fontFamily: c,
		fallbackBold: s,
		fallbackItalic: _?.fontItalic,
		fallbackBaseline: _?.fontBaseline,
		fallbackColorHidden: _?.fontPaintAuthored === !0 && (_.fontHidden === !0 || _.fontColor == null),
		fontFamilyForFace: h
	} : void 0, g, _, m, v, y), e.restore();
}
function ro(e, t, n, r, i, a) {
	if (!(!t?.text || !t.richRuns || t.richRuns.length === 0)) return io(e, t.richRuns, n, r, i, a);
}
function io(e, t, n, r, i, a) {
	if (!(!t || t.length === 0)) return {
		runs: t,
		ptToPx: n,
		fontFamily: r,
		fallbackBold: i,
		fallbackItalic: a?.fontItalic,
		fallbackBaseline: a?.fontBaseline,
		fallbackColorHidden: a?.fontPaintAuthored === !0 && (a.fontHidden === !0 || a.fontColor == null),
		fontFamilyForFace: (t) => Y(e, t, "minor")
	};
}
function ao(e, t, n, r, i, a, o, s = r, c, u, d, f = 1, p, m = 0) {
	if (!t && !u || !Number.isFinite(i) || i <= 0) return;
	if (u) {
		oo(e, t, n, r, i, a, o, s, c, u, d, p);
		return;
	}
	if (c) {
		let t = jr(e, c, i, a);
		if (!t) return;
		let u = re(d, f), h = l(t.width + u.left + u.right, t.height + u.top + u.bottom, d?.textRotation, d?.textVerticalMode), g = Qe(n, r, {
			w: h.w,
			h: h.h
		}, i, o, s);
		if (!g) return;
		e.save(), e.beginPath(), e.rect(g.clip.x, g.clip.y, g.clip.w, g.clip.h), e.clip(), Z(e, p, g.rect, f, m);
		let _ = E(d, g.textAlign), v = Ee(g.x, g.y, g.rect, t.height + u.top + u.bottom, d, o != null, _, g.textAlign, t.width + u.left + u.right, h.radians), y = Je(e, v.x, v.y, h.radians, _, g.textBaseline, u);
		Ar(e, t, y.x, y.y, _, g.textBaseline, o ? Math.max(0, g.rect.w - u.left - u.right) : t.width), e.restore();
		return;
	}
	let h = i * 1.15, g = M(t).value.split(/\r?\n/), _ = g.reduce((t, n) => Math.max(t, e.measureText(n).width), 0), v = Math.max(h, g.length * h), y = re(d, f), b = l(_ + y.left + y.right, v + y.top + y.bottom, d?.textRotation, d?.textVerticalMode), x = Qe(n, r, {
		w: b.w,
		h: b.h
	}, i, o, s);
	if (!x) return;
	let S = (t) => e.measureText(t).width, C = ar(t, x.maxWidth, x.maxHeight, h, S, d);
	if (C.length === 0) return;
	let w = C.reduce((e, t) => Math.max(e, S(t)), 0), T = C.length * h, D = l(w + y.left + y.right, T + y.top + y.bottom, d?.textRotation, d?.textVerticalMode);
	if (x = Qe(n, r, {
		w: D.w,
		h: D.h
	}, i, o, s), !x) return;
	e.save(), e.beginPath(), e.rect(x.clip.x, x.clip.y, x.clip.w, x.clip.h), e.clip(), Z(e, p, x.rect, f, m);
	let O = d?.fontPaintAuthored === !0 && (d.fontHidden === !0 || d.fontColor == null);
	e.fillStyle = a;
	let k = E(d, x.textAlign);
	e.textAlign = k, e.textBaseline = x.textBaseline;
	let A = Ee(x.x, x.y, x.rect, T + y.top + y.bottom, d, o != null, k, x.textAlign, w + y.left + y.right, D.radians), j = Je(e, A.x, A.y, D.radians, k, x.textBaseline, y), ee = (d?.fontBaseline ?? 0) * i, te = x.textBaseline === "middle" ? j.y - (C.length - 1) * h / 2 : x.textBaseline === "bottom" ? j.y - (C.length - 1) * h : j.y;
	if (!O) for (let t = 0; t < C.length; t++) e.fillText(C[t], j.x, te + t * h - ee);
	e.restore();
}
function oo(e, t, n, r, i, a, o, s, c, u, d, f) {
	let { entry: p, ptToPx: m, shapeRotationDeg: h } = u, g = ja([p], i, m)[0] ?? 0, _ = Na(p, i, m), v = t ? 4 : 0, y = t && c ? jr(e, c, i, a) : null;
	if (t && c && !y) return;
	let b = i * 1.15, x = t && !y ? M(t).value.split(/\r?\n/) : [], S = y?.width ?? x.reduce((t, n) => Math.max(t, e.measureText(n).width), 0), C = y?.height ?? (x.length > 0 ? Math.max(b, x.length * b) : 0), w = re(d, m), T = l(g + v + S + w.left + w.right, Math.max(_, C) + w.top + w.bottom, d?.textRotation, d?.textVerticalMode), D = Qe(n, r, {
		w: T.w,
		h: T.h
	}, i, o, s);
	if (!D) return;
	let O = x;
	if (t && !y && (O = ar(t, Math.max(0, D.maxWidth - g - v), D.maxHeight, b, (t) => e.measureText(t).width, d), O.length === 0)) return;
	let k = y?.width ?? O.reduce((t, n) => Math.max(t, e.measureText(n).width), 0), A = y?.height ?? O.length * b, j = g + v + k, ee = Math.max(_, A), te = j + w.left + w.right, N = ee + w.top + w.bottom, ne = l(te, N, d?.textRotation, d?.textVerticalMode);
	if (D = Qe(n, r, {
		w: ne.w,
		h: ne.h
	}, i, o, s), !D) return;
	let P = D.textAlign === "left" ? D.x + ne.w / 2 : D.textAlign === "right" ? D.x - ne.w / 2 : D.x, ie = D.textBaseline === "top" ? D.y + ne.h / 2 : D.textBaseline === "bottom" ? D.y - ne.h / 2 : D.y;
	if (o) {
		let e = E(d, "center"), t = Ee(P, ie, D.rect, N, d, !0, e);
		P = e === "left" ? t.x + te / 2 : e === "right" ? t.x - te / 2 : t.x, ie = t.y;
	}
	let F = P - te / 2 + w.left, I = ie - N / 2 + w.top;
	if (e.save(), e.beginPath(), e.rect(D.clip.x, D.clip.y, D.clip.w, D.clip.h), e.clip(), Z(e, f, D.rect, m, h), ne.radians !== 0 && (e.translate(P, ie), e.rotate(ne.radians), e.translate(-P, -ie)), va(e, p.swatchStyle, p.color, F, I + (ee - _) / 2, g, _, p.marker, p.fillPaint, p.outlinePaint, p.outlineColor, p.outlineWidthEmu, p.outlineDash, p.outlineCustomDash, p.outlineCap, p.outlineJoin, m, h, p.directEffect, p.fallbackEffect, p.directEffectIndex, p.fallbackEffectIndex), t) {
		let t = F + g + v;
		if (y) Ar(e, y, t, I + (ee - A) / 2, "left", "top");
		else if (!(d?.fontPaintAuthored === !0 && (d.fontHidden === !0 || d.fontColor == null))) {
			e.fillStyle = a, e.textAlign = "left", e.textBaseline = "top";
			let n = (d?.fontBaseline ?? 0) * i, r = I + (ee - A) / 2 - n;
			for (let n = 0; n < O.length; n++) e.fillText(O[n], t, r + n * b);
		}
	}
	e.restore();
}
function so(e, t, n, r, i, a, o, s, c, l, u, d = "sans-serif", f = "t", p = {
	x: -1e6,
	y: -1e6,
	w: 2e6,
	h: 2e6
}, m = p, h, g, _, v, y, b, x = 0) {
	let S = t.dataLabelOverrides ?? [], C = cr(S), w = t.seriesDataLabels;
	if (S.length === 0 && !w) return !1;
	for (let S = 0; S < r; S++) {
		if (t.sourceHidden?.[S] === !0 || t.values[S] == null && !u) continue;
		let r = o(S);
		if (b && !b(r)) continue;
		let T = t.values[S] ?? 0, E = C.get(S);
		if (ue(w, E)) continue;
		let D = E?.showCatName ?? w?.showCatName, O = E?.showSerName ?? w?.showSerName, k = E?.showVal ?? w?.showVal, A = E?.showPercent ?? w?.showPercent, j = E?.showLegendKey ?? w?.showLegendKey ?? !1, ee = mt({
			customText: E?.text,
			showCategory: D,
			showSeries: O,
			showValue: k,
			showPercent: A,
			category: n[S] ?? "",
			seriesName: t.name,
			sourceValue: T,
			valueDivisor: vn(v),
			percentRatio: h?.(S),
			formatCode: E?.formatCode ?? w?.formatCode ?? null,
			date1904: l,
			separator: E?.separator ?? w?.separator
		}), te = j ? y?.(S) : void 0;
		if (!ee && !te) continue;
		let M = E?.position ?? w?.position ?? f, N = Rt(E?.fontSizeHpt ?? w?.fontSizeHpt, c) ?? Math.max(9, Math.min(11, s / 25)), ne = E?.fontColor ?? w?.fontColor, P = E?.fontBold ?? w?.fontBold ?? !1, re = E?.fontFace ?? w?.fontFace, ie = re && _ ? _(re) : d;
		no(e, i(S), a(r), ee, M, N, ne, P, ie, g?.(S) ?? 0, p, E?.manualLayout, m, E?.richRuns, c, _, te, Ne(E, w), Wn(E?.labelBox, w?.labelBox), x);
	}
	return !0;
}
function co(e, t, n, r, i) {
	let a = 0;
	for (let o of [e?.fillPaint, e?.borderFill]) {
		if (!o) continue;
		let e = o.fillType === "image" ? Dn(o, t, n, r, i) : Un(o);
		if (o.fillType === "gradient" && e > Di) return null;
		a += e;
	}
	return a;
}
function lo(e, t, n, r) {
	let i = t.seriesDataLabels;
	return ue(i, r) ? !1 : !!(r?.text || (r?.showVal ?? i?.showVal ?? e.showDataLabels) || (r?.showCatName ?? i?.showCatName) || (r?.showSerName ?? i?.showSerName) || (r?.showPercent ?? i?.showPercent) || (r?.showBubbleSize ?? i?.showBubbleSize) || (r?.showLegendKey ?? i?.showLegendKey)) && n < Math.max(t.values.length, t.categories?.length ?? 0, e.categories.length);
}
function uo(e, t, n, r = Ut, i) {
	if (e.chartexSunburst || e.chartexTreemap) return null;
	let a = 0, o = Math.max(1, i?.w ?? 32 * r), s = Math.max(1, i?.h ?? 16 * r), c = (e) => {
		let t = co(e, n, o, s, r);
		return t == null || t > Oi - a ? !1 : (a += t, !0);
	}, l = e.threeD != null && t != null && ki.has(e.chartType), u = e.series.some((t) => (t.seriesType ?? e.chartType) === "scatter" && (t.categories ?? e.categories).some((e) => Number.isFinite(Number.parseFloat(e))));
	for (let t = 0; t < e.series.length; t++) {
		let n = e.series[t], r = cr(n.dataLabelOverrides), i = n.seriesType ?? e.chartType, a = Math.max(n.values.length, n.categories?.length ?? 0, e.categories.length);
		for (let o = 0; o < a; o++) {
			let a = n.values[o];
			if (l) {
				if (a == null || !Number.isFinite(a)) continue;
				if (e.showDataLabelsOverMax !== !0) {
					let t = n.useSecondaryAxis ? e.secondaryValAxis?.max : e.valMax;
					if (t != null && Number.isFinite(t) && a > t) continue;
				}
			} else if (!bt(e, n, i, o, u, t)) continue;
			let s = r.get(o);
			if (!lo(e, n, o, s)) continue;
			let d = Wn(s?.labelBox, n.seriesDataLabels?.labelBox);
			if (d && !c(d)) return Oi + 1;
		}
		if (!l) {
			for (let e of n.trendLines ?? []) if ((e.dispEq === !0 || e.dispRSqr === !0 || e.labelText || e.labelRichRuns?.some((e) => e.text.length > 0) === !0) && e.labelBox && !c(e.labelBox)) return Oi + 1;
		}
	}
	return a;
}
function fo(e, t, n) {
	return {
		series: t,
		seriesIndex: n,
		fallbackColor: fe(n, t),
		cats: t.categories ?? e.categories,
		pointOverrides: new Map((t.dataPointOverrides ?? []).map((e) => [e.idx, e]))
	};
}
function po(e, t, n, r, i) {
	let a = _(e.bubbleScale ?? 100, 0, 300);
	if (a <= 0) return 0;
	let o = 0;
	for (let { series: r, cats: i, pointOverrides: a } of t) if (!(r.showMarker === !1 || r.markerSymbol === "none")) for (let t = 0; t < r.values.length; t++) {
		if (r.values[t] == null || Xa(i, t, n) == null || a.get(t)?.markerSymbol === "none") continue;
		let s = S(e, r.bubbleSizes?.[t]);
		s != null && (o = Math.max(o, Za(e, s)));
	}
	return o <= 0 ? 0 : Math.min(r, i) * a / (300 + a) / o;
}
function mo(e, n, r, i, a, o, s, c, l, u, f, p, m, h, g, _, v, y = 0, b) {
	let x = h === "line" || h === "lineMarker" || h === "lineNoMarker", C = h === "smooth" || h === "smoothMarker" || h === "smoothNoMarker", w = pt("scatter", n.chartType, h, n.radarStyle), T = r.map(({ series: e, index: t }) => fo(n, e, t)), E = Sa(n, p), D = b ?? n, O = m ? po(D, T, i, u, f) : 0;
	for (let { series: t, fallbackColor: r, cats: s } of T) for (let c of t.errBars ?? []) Qa(e, t, At(n, c), s, i, a, o, r);
	for (let { series: r, seriesIndex: s, fallbackColor: g, cats: _ } of T) {
		let v = h === "marker" && R(r), b = v || x, S = !v && h === "marker" && !m || C;
		if (b || S) {
			let m = [];
			for (let e = 0; e < r.values.length; e++) {
				let t = r.values[e];
				if (t == null) continue;
				let n = Xa(_, e, i);
				n != null && m.push({
					x: a(n),
					y: o(t),
					index: e
				});
			}
			if (m.length >= 2) {
				let i = Ie(r, s), a = {
					x: c,
					y: l,
					w: u,
					h: f
				};
				if (t(n, s)) {
					d(e, n, r, [m], S, !1, g, 1.5, p, a, y, !0);
					continue;
				}
				Pe(e, de(r.chartexStyle), n.chartStyleRoles?.dataPointLine, i, a, p, (e) => {
					e.save();
					let t = ie(e, n, "dataPointLine", r, void 0, i, g, 1.5, p, a, y, !0, !1);
					if (t && v && r.dataPointColors?.some(Boolean)) {
						e.lineWidth = 1.5;
						for (let t = 1; t < m.length; t++) e.strokeStyle = `#${r.dataPointColors[t] ?? r.color ?? g.replace(/^#/, "")}`, e.beginPath(), e.moveTo(m[t - 1].x, m[t - 1].y), e.lineTo(m[t].x, m[t].y), e.stroke();
					} else if (t) {
						if (e.beginPath(), e.moveTo(m[0].x, m[0].y), S && m.length >= 3) for (let t = 0; t < m.length - 1; t++) {
							let n = m[t - 1] ?? m[t], r = m[t], i = m[t + 1], a = m[t + 2] ?? i;
							e.bezierCurveTo(r.x + (i.x - n.x) / 6, r.y + (i.y - n.y) / 6, i.x - (a.x - r.x) / 6, i.y - (a.y - r.y) / 6, i.x, i.y);
						}
						else for (let t = 1; t < m.length; t++) e.lineTo(m[t].x, m[t].y);
						e.stroke();
					}
					e.restore();
				});
			}
		}
	}
	for (let { series: t, seriesIndex: r, fallbackColor: s, cats: c, pointOverrides: l } of T) {
		let u = !w && t.showMarker !== !1 && t.markerSymbol !== "none";
		if (u || !w && Ft(t)) for (let d = 0; d < t.values.length; d++) {
			let f = t.values[d];
			if (f == null) continue;
			let h = Xa(c, d, i);
			if (h == null) continue;
			let g = l.get(d), _ = rn(t, g, m ? "circle" : t.automaticMarkerSymbol ?? "circle", u);
			if (_ === "none") continue;
			let v = g?.markerSize ?? t.markerSize ?? 5;
			if (m) {
				if (O <= 0) continue;
				let e = S(D, t.bubbleSizes?.[d]);
				if (e == null) continue;
				v = Za(D, e) * O / p;
			}
			let b = m ? ea(n, t, g, d, Ie(t, r), s) : null, x = b?.color ?? $i(t, g, d, s), C = m ? ta(n, t, g, d, Ie(t, r)) : null, w = m ? C.color : g?.markerLine ?? t.markerLine ?? null, T = g?.markerLineWidthEmu ?? t.markerLineWidthEmu, E = C?.widthEmu, k = m ? E : T, A = k == null ? void 0 : nt(k, p), j = m ? Qt(t, g) : !1;
			ra(e, n, t, g, d, a(h), o(f), _, v, x, w, p, A, m ? b.paint : st(t, g, d), y, m ? C.paint : void 0, m ? C.dash : void 0, m ? C.customDash : void 0, m ? C.cap : void 0, m ? C.join : void 0, j, m);
		}
	}
	for (let { series: t, seriesIndex: r, cats: c, pointOverrides: u } of T) to(e, t, c, i, a, o, f, p, n.date1904, Y(n, n.dataLabelFontFace, "minor"), n.dataLabelPosition ?? "r", {
		x: s.x,
		y: l,
		w: s.w,
		h: f
	}, g, (e) => Y(n, e, "minor"), v, (e) => E(r, e), (e) => eo(n, e, _), y, (e) => {
		if (w) return 0;
		let n = t.showMarker !== !1 && t.markerSymbol !== "none", r = u.get(e);
		if (rn(t, r, "circle", n) === "none") return 0;
		let i = r?.markerSize ?? t.markerSize ?? 5;
		if (m) {
			if (O <= 0) return 0;
			let n = S(D, t.bubbleSizes?.[e]);
			if (n == null) return 0;
			i = Za(D, n) * O / p;
		}
		return Math.max(0, i * p / 2);
	});
	for (let { series: t, fallbackColor: r, cats: d } of T) ua(e, t, r, a, o, p, t.values.map((e, t) => Xa(d, t, i)), {
		chart: n,
		chartRect: s,
		plotRect: {
			x: c,
			y: l,
			w: u,
			h: f
		},
		clipLineToPlot: !0,
		shapeRotationDeg: y
	});
}
function ho(e, t, n) {
	let r = Rt(e.legendFontSizeHpt, n) ?? 10 * n, i = Rt(e.dataTable?.fontSizeHpt, n) ?? 9 * n, a = Rt(t.seriesDataLabels?.fontSizeHpt ?? e.dataLabelFontSizeHpt, n) ?? 10 * n;
	for (let e of t.dataLabelOverrides ?? []) a = Math.max(a, Rt(e.fontSizeHpt, n) ?? a);
	return {
		legend: Math.max(2, (2 * r + 4) * .58),
		table: Math.max(2, i),
		labels: Math.max(2, a)
	};
}
function go(e, t, n = Ut, r) {
	let a = zn(e.chartType), o = e.chartexBox != null;
	if (!a && !o) return null;
	let s = kn(e), c = e.series.some((t, n) => {
		let r = s[n];
		return (r?.kind === "bubble" || r?.kind === "scatter" ? "scatter" : t.seriesType ?? (e.chartType === "bubble" ? "scatter" : e.chartType)) === "scatter" ? (t.categories ?? e.categories).some((e) => Number.isFinite(Number.parseFloat(e))) : !1;
	}), l = Ba(e) && Jn(e).length > 0 && e.dataTable?.showKeys === !0, u = V(e), d = Wt(e, !0), f = e.chartType === "bubble" && r ? po(e, e.series.map((t, n) => fo(e, t, n)), !c, r.w, r.h) : 0, p = /* @__PURE__ */ new Map();
	if (r) for (let t of e.plotGroups ?? []) {
		if (t.kind !== "bubble" || t.seriesCount === 0) continue;
		let n = e.series.slice(t.seriesStart, t.seriesStart + t.seriesCount).map((n, r) => fo(e, n, t.seriesStart + r));
		p.set(t, po({
			bubbleScale: t.bubbleScale ?? e.bubbleScale,
			bubbleSizeRepresents: t.bubbleSizeRepresents ?? e.bubbleSizeRepresents,
			showNegativeBubbles: t.showNegativeBubbles ?? e.showNegativeBubbles
		}, n, !c, r.w, r.h));
	}
	let h = 0, g = (e, t = 1) => t <= 0 || e <= 0 ? !0 : !Number.isSafeInteger(t) || e > Math.floor((Ei - h) / t) ? !1 : (h += e * t, !0), _ = (e, r = 1, i = Math.max(2, 5 * n)) => {
		if (r <= 0 || e == null) return !0;
		let a = e.fillType === "image" ? Dn(e, t, i, i, n) : Un(e);
		return e.fillType === "gradient" && a > Ti ? !1 : g(a, r);
	};
	if (a) for (let t = 0; t < e.series.length; t++) {
		let a = e.series[t], o = s[t], h = o?.kind === "bubble" || o == null && e.chartType === "bubble", v = o?.kind === "bubble" || o?.kind === "scatter" ? "scatter" : a.seriesType ?? (e.chartType === "bubble" ? "scatter" : e.chartType), y = gt(e.chartType, o), b = o?.scatterStyle ?? e.scatterStyle, x = o?.radarStyle ?? e.radarStyle, C = {
			chartType: y,
			bubbleScale: o?.bubbleScale ?? e.bubbleScale,
			showNegativeBubbles: o?.showNegativeBubbles ?? e.showNegativeBubbles
		}, w = h ? {
			bubbleScale: C.bubbleScale,
			bubbleSizeRepresents: o?.bubbleSizeRepresents ?? e.bubbleSizeRepresents,
			showNegativeBubbles: C.showNegativeBubbles
		} : void 0, T = o?.kind === "bubble" ? p.get(o) ?? 0 : f;
		if (!(v === "line" || v === "stackedLine" || v === "stackedLinePct" || v === "area" || v === "stackedArea" || v === "stackedAreaPct" || v === "scatter" || v === "radar" || v === "stock") || pt(v, y, b, x)) continue;
		let E = v === "area" || v === "stackedArea" || v === "stackedAreaPct" ? (a.showMarker === !0 || Re(a)) && a.markerSymbol !== "none" : v === "stock" ? a.markerSymbol != null && a.markerSymbol !== "none" : a.showMarker !== !1 && a.markerSymbol !== "none";
		if (!E && !Ft(a)) continue;
		let D = Math.max(a.values.length, a.categories?.length ?? 0, e.categories.length), O = cr(a.dataPointOverrides), k = new Map((a.dataLabelOverrides ?? []).map((e) => [e.idx, e])), A = ho(e, a, n), j = d[t]?.pointDriven === !0;
		for (let o = 0; o < D; o++) {
			let s = kt(e, a, v, o, c, C) && (!h || T > 0 && S(w, a.bubbleSizes?.[o]) != null), f = j && e.showLegend && i(d, u, t, o), p = k.get(o), m = j && s && !ue(a.seriesDataLabels, p) && (p?.showLegendKey ?? a.seriesDataLabels?.showLegendKey ?? !1) === !0, y = j && l && o === 0;
			if (!s && !f && !m && !y || h && s && (T <= 0 || S(w, a.bubbleSizes?.[o]) == null)) continue;
			let D = O.get(o), ee = rn(a, D, "circle", E);
			if (ee === "none") continue;
			let te = h ? ga(e, a, D, o) : _a(e, a, D, o, t, v, b, x), M = te ? te.fillPaint : h ? void 0 : st(a, D, o), N = te?.linePaint, ne = X(te?.symbol ?? ee), P = (e) => (!ne || _(M, 1, e)) && _(N, 1, e) && (!h || te?.bubble3D !== !0 || M === null || g(15)), re = Math.max(2, (D?.markerSize ?? a.markerSize ?? 5) * n);
			if (v === "scatter" && h) {
				let e = S(w, a.bubbleSizes?.[o]);
				re = e == null ? 0 : Za(w, e) * T;
			} else v === "radar" && D?.markerSize == null && a.markerSize == null && r && (re = Math.max(4 * n, Math.min(r.w, r.h) * .025));
			if (s && !P(re) || f && !P(A.legend) || y && !P(A.table) || m && !P(A.labels)) return Ei + 1;
		}
		let ee = a.markerSymbol ?? (v === "stock" ? "none" : "circle");
		if (!j && X(ee) && m(y, b, a, x)) {
			let n = i(d, u, t), r = gn(e, a, v, D, c, C), o = h ? ea(e, a, void 0, t, Ie(a, t), fe(t, a)) : null, s = h ? o.paint : U(a), f = h ? ta(e, a, void 0, t, Ie(a, t)) : null, p = (e, t) => _(s, e, t) && (!h || _(f.paint, e, t)) && (!h || !Qt(a, void 0) || o.paint === null || g(15, e));
			if (e.showLegend && n && !p(1, A.legend) || l && !p(1, A.table) || !p(r, A.labels)) return Ei + 1;
		}
	}
	let v = e.chartexBox;
	if (v) {
		let t = v.series.length, r = e.chartexDataPointMarkerStyle ?? e.chartexDataPointStyle;
		if (X(e.chartStyleMarkerSymbol ?? e.chartexMarkerSymbol ?? "circle")) for (let i = 0; i < t; i++) {
			let a = v.series[i];
			if (!a.showNonoutliers && !a.showOutliers) continue;
			let o = 0;
			for (let e of a.valuesByCategory) {
				let t = Et(e, a.quartileMethod);
				t && (o += G(t, a.showNonoutliers, a.showOutliers));
			}
			if (!_(Ii(e, Ie(a, i), t, a.chartexStyle, a.color, r), o, Math.max(2, 3 * n))) return Ei + 1;
		}
	}
	return h;
}
function _o(e, t) {
	if (!e.threeD || !t || !ki.has(e.chartType)) return null;
	let n = 0;
	for (let t of e.series) {
		let r = Math.max(1, t.values.length, t.categories?.length ?? 0), i = t.threeDShape ?? e.threeD.shape ?? "box", a = e.chartType === "pie" ? 36 : e.chartType.toLowerCase().includes("bar") ? i === "box" ? 4 : 36 : e.chartType.toLowerCase().includes("area") ? 4 : t.smooth === !0 ? 25 : 3;
		if (!Number.isSafeInteger(r) || r > Math.floor(1e4 / a) || (n += r * a, n > 1e4)) return zt + 1;
	}
	return n;
}
function vo(e, t, n) {
	return n <= 1e4 ? !1 : (e.fillStyle = "#888", e.font = "12px sans-serif", e.textAlign = "center", e.textBaseline = "middle", e.fillText("(too many data points)", t.x + t.w / 2, t.y + t.h / 2), !0);
}
function yo(e, t, n, r) {
	let i = t.chartTextBoxes;
	if (i?.length) for (let a of i) {
		let i = n.x + a.x * n.w, o = n.y + a.y * n.h, s = a.w * n.w, c = a.h * n.h;
		if (!(s > 0 && c > 0)) continue;
		let l = i + (a.lIns ?? 91440) / qt * r, u = o + (a.tIns ?? 45720) / qt * r, d = i + s - (a.rIns ?? 91440) / qt * r, f = o + c - (a.bIns ?? 45720) / qt * r, p = d - l, m = f - u;
		if (!(p > 0 && m > 0)) continue;
		let h = (e, t) => {
			let n = Math.max(1, ...t.map((e) => e.fontPx));
			return {
				paragraph: e,
				runs: t,
				width: t.reduce((e, t) => e + t.width, 0),
				height: n * 1.2,
				baseline: n * .9
			};
		}, g = (e) => {
			if (!e.runs.some((e) => /[\r\n]/.test(e.text))) return [e.runs];
			let t = [[]], n = [void 0];
			for (let r of e.runs) r.text.split(/\r\n|\r|\n/).forEach((e, i) => {
				i > 0 && (t.push([]), n.push(void 0)), e ? t[t.length - 1].push({
					...r,
					text: e
				}) : n[t.length - 1] ??= r;
			});
			return t.map((t, r) => t.length ? t : [{
				...n[r] ?? e.runs[0],
				text: ""
			}]);
		}, _ = a.paragraphs.flatMap((e) => g(e).map((t) => ({
			paragraph: e,
			runs: t
		}))).flatMap(({ paragraph: n, runs: i }) => {
			let o = i.map((n) => {
				let i = Math.max(1, (n.fontSizeHpt ?? 1e3) / 100 * r), a = `${n.bold ? "bold " : ""}${i}px ${Y(t, n.fontFace, "minor")}`;
				return e.font = a, {
					run: n,
					text: n.text,
					fontPx: i,
					font: a,
					width: e.measureText(n.text).width
				};
			}), s = o.reduce((e, t) => e + t.width, 0);
			if (a.wrap === "none" || s <= p) return [h(n, o)];
			let c = [], l = [], u = 0, d = () => {
				l.length && (c.push(h(n, l)), l = [], u = 0);
			};
			for (let t of o) {
				let n = t.text.match(/\s+|\S+/g) ?? [];
				for (let r of n) {
					let n = /^\s+$/.test(r);
					e.font = t.font;
					let i = e.measureText(r).width;
					l.length && u + i > p && d(), !(n && !l.length) && (l.push({
						...t,
						text: r,
						width: i
					}), u += i);
				}
			}
			return d(), c.length ? c : [h(n, o)];
		}), v = _.reduce((e, t) => e + t.height, 0), y = a.verticalAnchor === "b" ? f - v : a.verticalAnchor === "ctr" ? u + (m - v) / 2 : u;
		e.save(), e.beginPath(), e.rect(i, o, s, c), e.clip(), e.textAlign = "left", e.textBaseline = "alphabetic";
		let b = y;
		for (let t of _) {
			let n = t.paragraph.align, r = n === "ctr" ? l + (p - t.width) / 2 : n === "r" ? d - t.width : l;
			for (let n of t.runs) e.font = n.font, e.fillStyle = n.run.color ? `#${n.run.color}` : "#000000", e.fillText(n.text, r, b + t.baseline), r += n.width;
			b += t.height;
		}
		e.restore();
	}
}
function bo(e, t, n, r, i, a) {
	let o = Math.max(0, Math.min(a, r / 2, i / 2));
	e.beginPath(), e.moveTo(t + o, n), e.lineTo(t + r - o, n), e.quadraticCurveTo(t + r, n, t + r, n + o), e.lineTo(t + r, n + i - o), e.quadraticCurveTo(t + r, n + i, t + r - o, n + i), e.lineTo(t + o, n + i), e.quadraticCurveTo(t, n + i, t, n + i - o), e.lineTo(t, n + o), e.quadraticCurveTo(t, n, t + o, n), e.closePath();
}
function xo(e, t, n) {
	let r = n / (e.titleFontSizeHpt != null && e.titleFontSizeHpt >= 100 && e.titleFontSizeHpt <= 4e5 ? e.titleFontSizeHpt / 100 : 14), i = Rt(t.fontSizeHpt, r) ?? n, a = tr(e, t.fontFace ?? e.titleFontFace);
	return {
		font: rr(i, a ? Kn(a) : ct, t.bold ?? e.titleFontBold ?? !1, t.italic ?? e.titleFontItalic ?? !1),
		fontSize: i,
		color: t.colorPaintAuthored === !0 ? t.colorHidden === !0 || !t.color ? "transparent" : `#${t.color}` : t.color ? `#${t.color}` : e.titleFontPaintAuthored === !0 ? e.titleFontColor ? `#${e.titleFontColor}` : "transparent" : e.titleFontColor ? `#${e.titleFontColor}` : "#333"
	};
}
function So(e, t, n, r) {
	let i = t.titleRichRuns?.length ? t.titleRichRuns : t.title ? [{ text: t.title }] : [], a = [{
		pieces: [],
		width: 0,
		height: r
	}], o = () => {
		let e = {
			pieces: [],
			width: 0,
			height: r
		};
		return a.push(e), e;
	}, s = a[0];
	for (let a of i) {
		let i = xo(t, a, r);
		for (let t of a.text.split(/(\n|[\t ]+)/).filter((e) => e.length > 0)) {
			if (t === "\n") {
				s = o();
				continue;
			}
			e.font = i.font;
			let r = e.measureText(t).width, a = /^[\t ]+$/.test(t);
			!a && s.pieces.length > 0 && s.width + r > n && (s = o()), !(a && s.pieces.length === 0) && (s.pieces.push({
				text: t,
				width: r,
				font: i.font,
				color: i.color
			}), s.width += r, s.height = Math.max(s.height, i.fontSize));
		}
	}
	return a;
}
function Co(e, t, n, r, i) {
	let a = J(t, r, i);
	if (a.bandH === 0 || !t.titleRichRuns?.length) return a;
	let o = e.font, s = So(e, t, Math.max(1, n), a.fontPx);
	e.font = o;
	let c = s.reduce((e, t) => e + t.height, 0);
	return {
		...a,
		bandH: a.topPad + c + a.bottomPad
	};
}
function wo(e, t, n, r, i, a) {
	if (!t.title) return;
	let o = Nt(t, t.titleStyle ? { style: t.titleStyle } : void 0, t.chartStyleRoles?.title, cn(t, "title"), !0), s = a / Math.max(1, (t.titleFontSizeHpt ?? 1400) / 100);
	if (!t.titleRichRuns?.length) {
		let c = tr(t, t.titleFontFace);
		e.font = rr(a, c ? Kn(c) : ct, t.titleFontBold ?? !0, t.titleFontItalic ?? !1), e.fillStyle = t.titleFontColor ? `#${t.titleFontColor}` : "#333", e.textAlign = "center", e.textBaseline = "top";
		let l = Math.min(i, ot(e, t.title));
		Z(e, o, {
			x: n + (i - l) / 2,
			y: r,
			w: l,
			h: a * 1.2 + Zt(t.title, a)
		}, s), Pt(e, t.title, n + i / 2, r, a);
		return;
	}
	e.save();
	let c = So(e, t, Math.max(1, i), a), l = Math.min(i, Math.max(...c.map((e) => e.width), 0)), u = c.reduce((e, t) => e + t.height, 0);
	Z(e, o, {
		x: n + (i - l) / 2,
		y: r,
		w: l,
		h: u
	}, s), e.textAlign = "left", e.textBaseline = "top";
	let d = r;
	for (let t of c) {
		let r = n + (i - t.width) / 2;
		for (let n of t.pieces) e.font = n.font, e.fillStyle = n.color, e.fillText(n.text, r, d), r += n.width;
		d += t.height;
	}
	e.restore();
}
function To(e, t, n, r, i, a, o, s) {
	if (!t.title) return;
	let c = t.titleManualLayout;
	if (c) {
		let l = tr(t, t.titleFontFace);
		e.font = rr(s, l ? Kn(l) : ct, t.titleFontBold ?? !0, t.titleFontItalic ?? !1);
		let u = So(e, t, Math.max(1, i), s), d = Math.min(i, Math.max(...u.map((e) => e.width), 0)), f = {
			x: n + (i - d) / 2,
			y: o,
			w: d,
			h: s + (t.titleRichRuns?.length ? 0 : Zt(t.title, s))
		}, p = ht({
			...c,
			w: void 0,
			h: void 0
		}, {
			x: n,
			y: r,
			w: i,
			h: a
		}, f);
		if (p) {
			wo(e, t, p.x, p.y, p.w, s);
			return;
		}
	}
	wo(e, t, n, o, i, s);
}
function Eo(e, t, n, r, i, a, o, s, c, l, u, d = 0) {
	let f = Number.isFinite(c.gapWidthPercent) && c.gapWidthPercent >= 0 ? c.gapWidthPercent : 150, p = Math.max(0, s / (1 + f / 100));
	for (let s = 0; s < r; s++) {
		let r = t(s), f = n(s);
		if (r == null || f == null || !Number.isFinite(r) || !Number.isFinite(f)) continue;
		let m = a(r), h = o(f), g = Math.abs(h - m);
		if (!(p > 0) || !(g > 0) || !Number.isFinite(g)) continue;
		let _ = f >= r ? c.up : c.down, v = _.fillPaintAuthored === !0 || _.fill != null || _.fillColor != null || _.fillHidden === !0 ? void 0 : f >= r ? u?.upFillColor : u?.downFillColor, y = _.fillColor ?? v, b = i(s) - p / 2, x = Math.min(m, h), S = _.linePaintAuthored === !0 || _.lineColor != null || _.lineHidden === !0, C = _.lineColor ?? (S ? void 0 : u?.lineColor), w = _.lineWidthEmu ?? (S ? void 0 : u?.lineWidthEmu);
		Pe(e, _.style, void 0, s, {
			x: b,
			y: x,
			w: p,
			h: g
		}, l, (e) => {
			if (!_.fillHidden && (_.fill != null || y != null) && Bi(e, _.fill ?? (y ? {
				fillType: "solid",
				color: y
			} : null), {
				x: b,
				y: x,
				w: p,
				h: g
			}, y ? `#${y}` : "rgba(0,0,0,0)", l, d), !_.lineHidden && (_.linePaintAuthored !== !0 || C != null) && (C != null || w != null)) {
				let t = e.getLineDash(), n = e.lineCap, r = e.lineJoin;
				e.strokeStyle = `#${C ?? "000000"}`, e.lineWidth = w == null ? Math.max(1, .75 * l) : nt(w, l), e.setLineDash(Ue(_.lineDash ?? void 0, e.lineWidth)), e.lineCap = _.lineCap === "rnd" ? "round" : _.lineCap === "sq" ? "square" : "butt", e.lineJoin = _.lineJoin === "round" || _.lineJoin === "bevel" ? _.lineJoin : "miter", e.strokeRect(b, x, p, g), e.setLineDash(t), e.lineCap = n, e.lineJoin = r;
			}
		});
	}
}
function Do(e, t, n, r, i, a, o, s, c, l, u) {
	for (let d of t.lineGroupDecorations ?? []) {
		let f = t.series.filter((e) => e.lineGroupIndex === d.groupIndex);
		if (f.length === 0 && d.groupIndex === 0 && [
			"line",
			"stackedLine",
			"stackedLinePct"
		].includes(t.chartType) && (f = t.series.filter((e) => e.seriesType == null || e.seriesType === "line")), f.length === 0) continue;
		if (u === "foreground" && d.upDownBars && f.length >= 2) {
			let a = f[0], u = f[f.length - 1], p = t.legacyChartStyle === 2 ? {
				lineColor: "000000",
				lineWidthEmu: 9525,
				upFillColor: "FFFFFF",
				downFillColor: "000000"
			} : void 0, m = {
				...d.upDownBars,
				up: Ct(t, d.upDownBars.up, "upBar", p),
				down: Ct(t, d.upDownBars.down, "downBar", p)
			};
			Eo(e, (e) => o(a, e), (e) => o(u, e), n, r, i(a), i(u), s, m, c, void 0, l);
		}
		if (u === "foreground") continue;
		let p = d.dropLines ? an(t, d.dropLines, "dropLine") : null;
		p && en(e, p, c) && Lt(e, f, n, r, i, a, o);
		let m = d.hiLowLines ? an(t, d.hiLowLines, "hiLoLine") : null;
		if (m && f.length >= 2 && en(e, m, c)) {
			let t = i(f[0]);
			for (let i = 0; i < n; i++) {
				let n = Infinity, a = -Infinity;
				for (let e of f) {
					let t = o(e, i);
					t == null || !Number.isFinite(t) || (n = Math.min(n, t), a = Math.max(a, t));
				}
				!Number.isFinite(n) || !Number.isFinite(a) || (e.beginPath(), e.moveTo(r(i), t(n)), e.lineTo(r(i), t(a)), e.stroke());
			}
		}
	}
}
function Oo(e, t, n, r) {
	return e != null && Number.isFinite(e) ? _(e, n, r) : t === "max" ? r : t === "min" ? n : _(0, n, r);
}
function ko(e, t, n) {
	return Oo(e.catAxisCrossesAt, e.catAxisCrosses, t, n);
}
function Ao(e) {
	return 7 * e;
}
function jo(i, a, o, s, l = {}, u = 0) {
	let { x: f, y: p, w: m, h } = o, g = a.chartType === "clusteredBarH" || a.chartType === "stackedBarH" || a.chartType === "stackedBarHPct", b = a.chartType.startsWith("stacked"), S = a.chartType === "stackedBarPct" || a.chartType === "stackedBarHPct", C = a.series.filter((e) => e.seriesType !== "line" && e.seriesType !== "scatter" && e.seriesType !== "area"), T = (e) => e.barGroupDirection == null ? g : e.barGroupDirection === "bar", E = C, D = (e) => e.useSecondaryAxis === !0 ? "secondary-default" : "primary-default", j = (e) => e.barGroupIndex == null ? D(e) : `group-${e.barGroupIndex}`, te = (e) => e.barGroupGrouping ?? (S ? "percentStacked" : b ? "stacked" : "clustered"), M = (e) => {
		let t = te(e);
		return t === "stacked" || t === "percentStacked";
	}, P = (e) => te(e) === "percentStacked", re = a.series.filter((e) => e.seriesType === "line"), oe = a.series.filter((e) => e.seriesType === "area"), se = a.series.filter((e) => e.seriesType === "scatter"), ce = new Map(a.series.map((e, t) => [e, t])), R = /* @__PURE__ */ new Map();
	for (let e of a.plotGroups ?? []) for (let t = e.seriesStart; t < e.seriesStart + e.seriesCount; t++) {
		let n = a.series[t];
		n && R.set(n, e);
	}
	let le = /* @__PURE__ */ new Map();
	for (let e of a.plotGroups ?? []) {
		if (e.seriesCount === 0) continue;
		let t = le.get(e.valueAxis) ?? {
			count: 0,
			percentCount: 0
		};
		t.count++, e.grouping === "percentStacked" && t.percentCount++, le.set(e.valueAxis, t);
	}
	let ue = (e) => {
		let t = le.get(e.valueAxis);
		return t != null && t.count === t.percentCount;
	}, he = Sa(a, s), ge = a.series.some((e) => e.useSecondaryAxis === !0), B = !g && a.secondaryValAxis && ge ? a.secondaryValAxis : null, ve = !g && a.chartexPrimaryAxisRight === !0, ye = B ? E.filter((e) => e.useSecondaryAxis === !0) : [], V = B ? E.filter((e) => e.useSecondaryAxis !== !0) : E, Se = ye.length > 0 ? a.secondaryCatAxis : null, Te = ye[0]?.categories?.length ? ye[0].categories : a.categories, Ee = Jn(a), H = Ee.length;
	if (H === 0) return;
	let Oe = /* @__PURE__ */ new Map();
	for (let e of E) {
		let t = j(e), n = Oe.get(t);
		n ? n.push(e) : Oe.set(t, [e]);
	}
	let Ae = (e) => Oe.get(j(e)) ?? [e], Ne = /* @__PURE__ */ new Map();
	for (let [e, t] of Oe) {
		let n = Array(H).fill(0);
		for (let e of t) for (let t = 0; t < H; t++) n[t] += Math.abs(e.values[t] ?? 0);
		Ne.set(e, n);
	}
	let Fe = (e, t) => Ne.get(j(e))?.[t] || 1, Le = (e) => {
		let t = R.get(e);
		return t == null || ue(t) ? 100 : 1;
	}, ze = (e, t) => P(e) ? Le(e) / Fe(e, t) : 1, U = /* @__PURE__ */ new Map(), Ue = /* @__PURE__ */ new Map();
	for (let e of [...re, ...oe]) U.set(e, Array.from({ length: H }, (t, n) => e.values[n] ?? 0)), Ue.set(e, Array(H).fill(0));
	for (let e of a.plotGroups ?? []) {
		if (e.kind !== "line" && e.kind !== "area") continue;
		let t = a.series.slice(e.seriesStart, e.seriesStart + e.seriesCount), n = e.grouping === "stacked" || e.grouping === "percentStacked", r = e.grouping === "percentStacked", i = r && ue(e) ? 100 : 1;
		for (let e = 0; e < H; e++) {
			let a = r && t.reduce((t, n) => t + Math.abs(n.values[e] ?? 0), 0) || 1, o = 0;
			for (let s of t) {
				let t = s.values[e] ?? 0, c = r ? t / a * i : t, l = Ue.get(s), u = U.get(s);
				l == null || u == null || (l[e] = n ? o : 0, o = n ? o + c : c, u[e] = o);
			}
		}
	}
	let Ge = (e, t) => U.get(e)?.[t] ?? e.values[t] ?? 0, qe = (e, t) => Ue.get(e)?.[t] ?? 0, Je = (e) => P(e) ? (e.errBars ?? []).map((t) => ({
		...t,
		plus: t.plus.map((t, n) => t == null ? t : t * ze(e, n)),
		minus: t.minus.map((t, n) => t == null ? t : t * ze(e, n))
	})) : e.errBars ?? [], Ye = (e) => {
		let t = R.get(e);
		return t?.kind === "bar" || t?.kind === "bar3D" ? t.seriesCount === 1 && t.varyColors === !0 : y(a);
	}, Ze = E.map((e) => new Map((e.dataPointOverrides ?? []).map((e) => [e.idx, e]))), Qe = E.map((e) => new Map((e.dataLabelOverrides ?? []).map((e) => [e.idx, e]))), G = E.map((e, t) => Ie(e, t)), K = $e(a), q = K && v(a, E), J = /* @__PURE__ */ new Map();
	K && E.forEach((e, t) => {
		let n = G[t], r = e.color ?? Ni(a, n, E.length, e.chartexStyle);
		J.set(e, Xi(a, e.name, e, a.chartexDataPointStyle, n, E.length, r, !1, !0, !0));
	});
	let rt = E.findIndex(Ye), at = rt >= 0 ? E[rt] : void 0, ct = {
		...a,
		series: (K ? E : a.series).map((e) => J.get(e) ?? e)
	}, lt = Co(i, a, m, h, s), ut = lt.fontPx, dt = lt.topPad, pt = lt.bandH, mt = je(a.catAxisFontSizeHpt, h, s), ht = je(a.valAxisFontSizeHpt, h, s), gt = !g && !Ba(a) && a.catAxisNoMultiLevelLabels !== !0 && (a.categoryLevels?.length ?? 0) > 1 ? a.categoryLevels : null, _t = (e, t) => yt((gt?.[e] ?? []).filter((e) => (e ?? "") !== ""), nn(t)), bt = gt ? (gt.length - 1) * (mt + 4) : 0;
	for (let e = 1; e < (gt?.length ?? 0); e++) bt += _t(e, mt);
	let xt = Ba(a), St = xt && Va(a), Ct = Ha(a, mt), Tt = Ka(a, s), Et = qa(i, a, s), Ot = Fa(i, ct, m, h, s), { legRightW: kt, legLeftW: Nt, legTopH: Lt, legBottomH: zt } = Pn(Ot, a.legendOverlay === !0), Bt = wt(a, m, h, s), Ut = Bt.catFontPx, Wt = Bt.valFontPx, Gt = g ? a.valAxisTitle ? Wt + ae(h) + 4 + L(a.valAxisTitle, Wt, "horizontal", a.valAxisTitleRotation, a.valAxisTitleVerticalMode) : 0 : Bt.catBandH, qt = g ? a.catAxisTitle ? Ut + ae(m) + 4 + L(a.catAxisTitle, Ut, "left", a.catAxisTitleRotation, a.catAxisTitleVerticalMode) : 0 : Bt.valBandW, Jt = Rt(Se?.fontSizeHpt, s) ?? 9 * s, Yt = Se && !Se.hidden && Se.tickLabelPos !== "none" ? Jt + z(Te, Se, a, Jt) + Qn(n(Jt), Se.labelOffsetPercent) + 2 : 0, Xt = Se?.title ? bn(Se.titleFontSizeHpt, s) + 6 + L(Se.title, bn(Se.titleFontSizeHpt, s), "horizontal", Se.titleRotation, Se.titleVerticalMode) : 0, Zt = pt + Lt + ht / 2 + 2 + Yt + Xt, Qt = g ? (a.valAxisHidden ? h * .02 : ke(ht)) + Tt + Gt + zt : (xt ? 0 : ke(mt, a.catAxisLabelOffsetPercent, a.cartesianAutoLayoutProfile)) + bt + Tt + Ct + Gt + zt, $t = h - Zt - Qt, tn = 0;
	if (g && !a.catAxisHidden && O(a)) {
		let e = a.catAxisFontSizeHpt == null ? Math.max(8, Math.min(11, $t / H * .5)) : mt;
		i.save(), i.font = rr(e, Y(a, a.catAxisFontFace, "minor"), a.catAxisFontBold ?? !1, a.catAxisFontItalic ?? !1);
		for (let e of Ee) tn = Math.max(tn, ot(i, er(e, a.catAxisFormatCode, a.date1904)));
		i.restore(), tn += Qn(a.catAxisFontSizeHpt == null ? 4 : Ln(e), a.catAxisLabelOffsetPercent) + A * s;
	}
	let on = Math.min(tn, Math.max(0, m / 2 - qt - Nt)), sn = g ? m - ((a.catAxisHidden ? m * .03 : on) + qt + Nt) - (kt + m * .03) : 0, un = a.valAxisHidden ? void 0 : (g ? sn : $t) / s, fn = (e, t) => {
		let n = E[e], r = n?.values[t] ?? 0;
		if (!n || !M(n)) return r;
		let i = Ae(n), a = P(n), o = 1;
		a && (o = i.reduce((e, n) => e + Math.abs(n.values[t] ?? 0), 0) || 1);
		let s = a ? ze(n, t) * o : 1, c = a ? r / o * s : r, l = 0, u = i.indexOf(n);
		for (let e = 0; e <= u; e++) {
			let n = i[e]?.values[t] ?? 0, r = a ? n / o * s : n;
			c < 0 == r < 0 && (l += r);
		}
		return l;
	}, pn = (a.plotGroups ?? []).filter((e) => e.seriesCount > 0 && e.valueAxis !== "secondary"), gn = pn.length > 0 ? pn.every((e) => e.grouping === "percentStacked") : V.some(P), _n = 0, vn = 0;
	for (let e = 0; e < H; e++) {
		let t = /* @__PURE__ */ new Map();
		for (let e of V) {
			let n = j(e), r = t.get(n);
			r ? r.push(e) : t.set(n, [e]);
		}
		for (let n of t.values()) {
			let t = n[0], r = M(t), i = P(t), a = i && n.reduce((t, n) => t + Math.abs(n.values[e] ?? 0), 0) || 1, o = 0, s = 0;
			for (let t of n) {
				let n = t.values[e] ?? 0, c = i ? n / a * Le(t) : n;
				r ? c >= 0 ? o += c : s += c : (_n = Math.max(_n, c), vn = Math.min(vn, c));
			}
			r && (_n = Math.max(_n, o), vn = Math.min(vn, s));
		}
	}
	for (let e of [...re, ...oe]) if (!(B && e.useSecondaryAxis === !0)) for (let t = 0; t < H; t++) {
		if (e.values[t] == null) continue;
		let n = Ge(e, t);
		_n = Math.max(_n, n), vn = Math.min(vn, n);
	}
	for (let e of V) {
		let t = E.indexOf(e);
		for (let n of Je(e)) w({
			...e,
			errBars: [n]
		}, g ? "x" : "y", (n) => e.values[n] == null ? null : fn(t, n), (e) => {
			_n = Math.max(_n, e), vn = Math.min(vn, e);
		});
	}
	for (let e of [...re, ...oe]) B && e.useSecondaryAxis === !0 || w(e, "y", (t) => e.values[t] ?? null, (e) => {
		let t = e;
		_n = Math.max(_n, t), vn = Math.min(vn, t);
	});
	gn && (V.some((e) => e.values.some((e) => e != null && e > 0)) && (_n = Math.max(_n, 100)), V.some((e) => e.values.some((e) => e != null && e < 0)) && (vn = Math.min(vn, -100))), a.valMax != null && (_n = gn ? a.valMax * 100 : a.valMax), a.valMin != null && (vn = gn ? a.valMin * 100 : a.valMin), _n === 0 && vn === 0 && (_n = 1);
	let yn = q ? Kt(a, V, H, un) : ln(a, vn, _n, un, gn, g ? "horizontal" : "vertical"), { step: xn } = yn, Sn = new Set(E), wn = new Set(C), Tn = (a.plotGroups ?? []).filter((e) => e.seriesCount > 0 && e.valueAxis === "secondary"), Dn = Tn.length > 0 ? Tn.every((e) => e.grouping === "percentStacked") : ye.some(P), On = a.series.filter((e) => !wn.has(e) || Sn.has(e)).map((e) => {
		if (e.useSecondaryAxis !== !0) return e;
		if (Sn.has(e)) {
			let t = E.indexOf(e);
			return {
				...e,
				values: e.values.map((e, n) => e == null ? e : fn(t, n)),
				errBars: Je(e)
			};
		}
		return Dn ? {
			...e,
			values: e.values.map((e) => e == null ? e : e * 100),
			errBars: (e.errBars ?? []).map((e) => ({
				...e,
				plus: e.plus.map((e) => e == null ? e : e * 100),
				minus: e.minus.map((e) => e == null ? e : e * 100)
			}))
		} : e;
	});
	if (Dn && ye[0]) {
		let e = [];
		ye.some((e) => e.values.some((e) => e != null && e > 0)) && e.push(100), ye.some((e) => e.values.some((e) => e != null && e < 0)) && e.push(-100), On.push({
			...ye[0],
			values: e,
			errBars: []
		});
	}
	let kn = Ht(B, On, $t / s, g ? "x" : "y", Dn, ye.length > 0), An = Math.max(8, Math.min(11, h / 20)), jn = a.valAxisFontSizeHpt == null ? Math.max(8, Math.min(11, $t / 20)) : ht, Fn = i.font, Rn = 0, zn = 0;
	if (!g && !a.valAxisHidden) {
		i.font = rr(jn, Y(a, a.valAxisFontFace, "minor"), a.valAxisFontBold ?? !1, a.valAxisFontItalic ?? !1);
		let e = 0;
		for (let t of yn.majorLines) {
			let n = In(a, t, gn);
			e = Math.max(e, ot(i, n));
		}
		Rn = e, zn = Rn + 16;
	}
	let Bn = Rt(B?.fontSizeHpt, s) ?? An, Vn = 0;
	if (B && !B.hidden) {
		i.font = `${Bn}px ${Y(a, B.fontFace, "minor")}`;
		let e = 0;
		for (let t of kn?.majorLines ?? []) e = Math.max(e, ot(i, Dt(Dn ? t / 100 : t, B.formatCode ?? null, a.date1904, B.displayUnits)));
		Vn = e + 18;
	}
	i.font = Fn;
	let Hn = a.valAxisHidden || a.valAxisTickLabelPos === "none" ? 0 : yt(yn.majorLines.map((e) => In(a, e, gn)), nn(jn)), Un = B && !B.hidden && B.tickLabelPos !== "none" && !g ? yt((kn?.majorLines ?? []).map((e) => Dt(Dn ? e / 100 : e, B.formatCode ?? null, a.date1904, B.displayUnits)), nn(Bn)) : 0, Gn = g ? 0 : Math.max(Hn, Un) / 2, Kn = g ? Hn : 0;
	Zt += Gn;
	let Yn = B && B.title ? ne(B, s) : 0, Xn = {
		t: Zt,
		r: kt + m * .03 + (ve ? zn + qt : Vn + Yn),
		b: Qt + Kn,
		l: g ? Nt + Math.max((a.catAxisHidden ? m * .03 : on) + qt, Et) : Nt + Math.max(ve ? Vn + Yn : qt + zn, Et)
	};
	Xn.t = Fo(a, Ot, f, p, m, h, pt, Xn.t);
	let Zn = g ? {
		t: 0,
		r: a.valAxisHidden ? 0 : jn / 2,
		b: a.valAxisHidden ? 0 : jn + Kn + Gt,
		l: a.catAxisHidden ? 0 : tn + qt
	} : Cn({
		valAxisHidden: a.valAxisHidden,
		catAxisHidden: a.catAxisHidden,
		valLabelWidth: Rn,
		valLabelFontPx: jn,
		catLabelFontPx: mt,
		valLabelGapPx: a.valAxisFontSizeHpt == null ? 12 : Ln(jn),
		catLabelGapPx: a.catAxisFontSizeHpt == null ? Qn(3, a.catAxisLabelOffsetPercent) : Qn(n(mt), a.catAxisLabelOffsetPercent),
		outerTextMarginPx: A * s,
		valTitleBandW: qt,
		catTitleBandH: Gt,
		secondaryBandW: Vn + Yn,
		valLabelExtraTopPx: Gn
	}), tr = dn(a, f, p, m, h, s, {
		titleBand: lt,
		legendReserve: Ot,
		pad: Xn,
		honorPlotAreaManualLayout: !0,
		manualOuterInsets: Zn
	}), nr = Co(i, a, tr.plotRect.pw, h, s);
	Math.abs(nr.bandH - lt.bandH) > .01 && (lt = nr, ut = lt.fontPx, dt = lt.topPad, pt = lt.bandH, Zt = pt + Lt + ht / 2 + 2 + Yt + Xt + Gn, Xn.t = Fo(a, Ot, f, p, m, h, pt, Zt), tr = dn(a, f, p, m, h, s, {
		titleBand: lt,
		legendReserve: Ot,
		pad: Xn,
		honorPlotAreaManualLayout: !0,
		manualOuterInsets: Zn
	}));
	let { px0: X, py0: Z, pw: Q } = tr.plotRect, { ph: $ } = tr.plotRect;
	if (To(i, a, a.titleManualLayout || !a.titleRichRuns?.length ? f : X, p, a.titleManualLayout || !a.titleRichRuns?.length ? m : Q, h, p + dt, ut), Q <= 0 || $ <= 0) return;
	let ar = r(a, Ee, g ? !Ke(a) : Ke(a)), or = q ? xe(a, E, ar) : null, sr = Xe(a), lr = [], ur = 0, dr = 0;
	if (!St && !g && !ar && !a.catAxisHidden && O(a) && sr === 0) {
		let e = Q / H, t = a.catAxisFontSizeHpt == null ? Math.max(8, Math.min(11, e * .5)) : mt;
		i.save(), i.font = rr(t, Y(a, a.catAxisFontFace, "minor"), a.catAxisFontBold ?? !1, a.catAxisFontItalic ?? !1);
		for (let n of Ee) {
			let r = er(n, a.catAxisFormatCode, a.date1904);
			lr.push(vt(i, r, Math.max(1, e), Po(r, t)));
		}
		i.restore();
		let n = Math.max(1, ...lr.map((e) => e.length));
		n > 1 && (ur = (n - 1) * (t + 2)), dr = yt(Ee.map((e) => er(e, a.catAxisFormatCode, a.date1904)), nn(t));
	} else if (!St && !g && !a.catAxisHidden && O(a)) {
		let e = a.catAxisFontSizeHpt == null ? Math.max(8, Math.min(11, Q / H * .5)) : mt;
		ur = yt(ar ? ar.majorTicks.map((e) => er(String(e.serial), a.catAxisFormatCode, a.date1904)) : Ee.map((e) => er(e.toString(), a.catAxisFormatCode, a.date1904)), nn(e), sr), dr = ur;
	}
	let fr = a.plotAreaManualLayout?.layoutTarget === "inner" && a.plotAreaManualLayout.w != null && a.plotAreaManualLayout.h != null;
	xt && (Ct += ur), !fr && ur > 0 && ($ = Math.max(1, $ - ur));
	let pr = Ua(a, Q, ve ? 0 : Vn + Yn), mr = xt ? Ja(i, a, pr / H, s) : null;
	mr && mr.totalHeight > Tt && ($ = Math.max(1, $ - (mr.totalHeight - Tt))), Nn(i, a, X, Z, Q, $, s, u);
	let hr = (e) => Z + $ - yn.frac(e) * $, gr = (e) => X + yn.frac(e) * Q, _r = hr(0), vr = gr(0), yr = (e) => hr(e), br = kn ? kn.makeToY(Z, $) : hr, xr = (e) => br(e), Sr = mn(a, s);
	i.textBaseline = "middle";
	let Cr = a.valAxisFontSizeHpt == null ? Math.max(8, Math.min(11, $ / 20)) : ht;
	i.font = rr(Cr, Y(a, a.valAxisFontFace, "minor"), a.valAxisFontBold ?? !1, a.valAxisFontItalic ?? !1);
	let wr = a.valAxisFontColor ? `#${a.valAxisFontColor}` : "#555";
	if (i.fillStyle = wr, !a.valAxisHidden) {
		let t = Mn(a, s);
		for (let e of yn.minorLines) if (!g) Vt(i, X, Q, hr(e), !1, t);
		else {
			let n = gr(e);
			i.strokeStyle = t.color, i.lineWidth = t.width;
			let r = t.dash.length > 0 && i.getLineDash ? i.getLineDash() : [];
			t.dash.length > 0 && i.setLineDash(t.dash), i.beginPath(), i.moveTo(n, Z), i.lineTo(n, Z + $), i.stroke(), t.dash.length > 0 && i.setLineDash(r);
		}
		let r = e(a), o = a.valAxisTickLabelPos !== "none";
		for (let e of yn.majorLines) {
			let t = Math.abs(e) < xn * 1e-9, c = In(a, e, gn);
			if (g) {
				let s = gr(e);
				if (r) {
					i.strokeStyle = Sr.explicit ? Sr.color : t ? "#aaa" : Sr.color, i.lineWidth = Sr.explicit ? Sr.width : t ? 1 : Sr.width;
					let e = Sr.dash.length > 0 && i.getLineDash ? i.getLineDash() : [];
					Sr.dash.length > 0 && i.setLineDash(Sr.dash), i.beginPath(), i.moveTo(s, Z), i.lineTo(s, Z + $), i.stroke(), Sr.dash.length > 0 && i.setLineDash(e);
				}
				if (o) {
					i.textAlign = "center";
					let e = a.valAxisFontSizeHpt == null ? 10 : n(Cr);
					jt(i, c, s, Z + $ + e, nn(Cr));
				}
			} else {
				let n = hr(e);
				if (r && Vt(i, X, Q, n, t, Sr), o) {
					i.textAlign = ve ? "left" : "right";
					let e = l.gapPolicy === "chartex" ? Ao(s) : a.valAxisFontSizeHpt == null ? 12 : Ln(Cr);
					Pt(i, c, ve ? X + Q + e : X - e, n, nn(Cr));
				}
			}
		}
	}
	if (B && kn && we(i, B, kn, br, X, Q, s), !a.catAxisHidden && W(a)) {
		let e = ee(a, s);
		i.strokeStyle = e.color, i.lineWidth = e.width;
		let t = e.dash.length > 0 && i.getLineDash ? i.getLineDash() : [];
		e.dash.length > 0 && i.setLineDash(e.dash);
		let n = ar ? ar.majorTicks.map((e) => e.fraction) : We(a, H);
		for (let e of n) {
			if (i.beginPath(), g) {
				let t = Z + e * $;
				i.moveTo(X, t), i.lineTo(X + Q, t);
			} else {
				let t = X + e * Q;
				i.moveTo(t, Z), i.lineTo(t, Z + $);
			}
			i.stroke();
		}
		e.dash.length > 0 && i.setLineDash(t);
	}
	let { color: Tr, width: Er } = it(a.catAxisLineColor, a.catAxisLineWidthEmu, s), { color: Dr, width: Or } = it(a.valAxisLineColor, a.valAxisLineWidthEmu, s), kr = !a.catAxisHidden && !a.catAxisLineHidden, Ar = !a.valAxisHidden && !a.valAxisLineHidden && a.valAxisLineColor != null, jr = ko(a, yn.min, yn.max), Mr = g ? Z + $ : hr(jr), Nr = g ? gr(jr) : X, Pr = !g && (a.catAxisTickLabelPos ?? "nextTo") === "nextTo" ? Mr : Z + $, Fr = Math.max(1, Math.floor(a.catAxisTickMarkSkip ?? 1)), Ir = !g && gt != null && ar == null && !a.catAxisLineHidden && et(a) && Math.abs(Pr - Mr) < .01, Lr = () => {
		if (g) kr && En(i, Nr, Z, Nr, Z + $, Tr, Er, a.catAxisLineDash), Ar && En(i, X, Z + $, X + Q, Z + $, Dr, Or, a.valAxisLineDash);
		else if (kr && En(i, X, Mr, X + Q, Mr, Tr, Er, a.catAxisLineDash), Ar) {
			let e = ve ? X + Q : X;
			En(i, e, Z, e, Z + $, Dr, Or, a.valAxisLineDash);
		}
		if (!a.valAxisHidden && a.valAxisMajorTickMark && a.valAxisMajorTickMark !== "none") for (let e of yn.majorLines) g ? F(i, a.valAxisMajorTickMark, "cat", Z + $, gr(e), Dr, Or, !1, a.valAxisLineHidden, "major", s, a.valAxisLineDash) : F(i, a.valAxisMajorTickMark, "val", ve ? X + Q : X, hr(e), Dr, Or, ve, a.valAxisLineHidden, "major", s, a.valAxisLineDash);
		if (!a.valAxisHidden && a.valAxisMinorTickMark && a.valAxisMinorTickMark !== "none") for (let e of yn.minorTicks) g ? F(i, a.valAxisMinorTickMark, "cat", Z + $, gr(e), Dr, Or, !1, a.valAxisLineHidden, "minor", s, a.valAxisLineDash) : F(i, a.valAxisMinorTickMark, "val", ve ? X + Q : X, hr(e), Dr, Or, ve, a.valAxisLineHidden, "minor", s, a.valAxisLineDash);
		if (!a.catAxisHidden && a.catAxisMajorTickMark && a.catAxisMajorTickMark !== "none") {
			let e = Ir ? [] : We(a, H), t = ar ? ar.majorTicks.map((e) => e.fraction) : e.filter((e, t) => t % Fr === 0);
			for (let e of t) g ? F(i, a.catAxisMajorTickMark, "val", Nr, Z + e * $, Tr, Er, !1, a.catAxisLineHidden, "major", s, a.catAxisLineDash) : F(i, a.catAxisMajorTickMark, "cat", Mr, X + e * Q, Tr, Er, !1, a.catAxisLineHidden, "major", s, a.catAxisLineDash);
		}
		if (!a.catAxisHidden && a.catAxisMinorTickMark && a.catAxisMinorTickMark !== "none") {
			let e = et(a) ? Array.from({ length: H }, (e, t) => (t + .5) / H) : Array.from({ length: Math.max(0, H - 1) }, (e, t) => (t + .5) / (H - 1)), t = ar ? ar.minorTicks.map((e) => e.fraction) : e;
			for (let e of t) g ? F(i, a.catAxisMinorTickMark, "val", Nr, Z + e * $, Tr, Er, !1, a.catAxisLineHidden, "minor", s, a.catAxisLineDash) : F(i, a.catAxisMinorTickMark, "cat", Mr, X + e * Q, Tr, Er, !1, a.catAxisLineHidden, "minor", s, a.catAxisLineDash);
		}
	}, Rr = (e) => e ? $ / H : Q / H, zr = Rr(g), Br = Ke(a), Vr = (e, t) => t ? Br ? e : H - 1 - e : Br ? H - 1 - e : e, Hr = (e, t = g) => ar ? ar.categoryBandFractions[e] * (t ? $ : Q) : Rr(t), Ur = (e, t = g) => ar ? (t ? Z : X) + ar.positions[e] * (t ? $ : Q) - Hr(e, t) / 2 : (t ? Z : X) + Vr(e, t) * Rr(t), Wr = (e) => ar ? X + ar.positions[e] * Q : X + Vr(e, !1) * Rr(!1) + Rr(!1) / 2, Gr = (e, t) => {
		let n = e[0], r = n ? M(n) : b, i = r ? 1 : Math.max(1, e.length), o = n?.barGroupOverlap ?? a.barOverlap ?? 0, s = r || !Number.isFinite(o) ? 0 : Math.max(-100, Math.min(100, o)), c = He(n?.barGroupGapWidth ?? a.barGapWidth, l.gapPolicy ?? "legacy"), u = t / (1 + (i - 1) * (1 - s / 100) + c / 100), d = r ? 0 : u * (1 - s / 100);
		return {
			barW: u,
			clusterGap: d,
			catStart: (t - (u + (i - 1) * d)) / 2
		};
	}, Kr = (a.barGroupDecorations ?? []).some((e) => e.seriesLines?.length === 1) ? E.map(() => Array(H).fill(null)) : null;
	for (let e = 0; e < oe.length; e++) {
		let t = oe[e], n = cr(t.dataPointOverrides), r = ce.get(t) ?? e, o = Ie(t, r), c = fe(r, t), l = me(a, t, void 0, o), d = B && t.useSecondaryAxis === !0 ? xr : yr, f = a.dispBlanksAs ?? "zero", p = [], m = () => {
			if (p.length !== 0) {
				ar && (i.save(), i.beginPath(), i.rect(X, Z, Q, $), i.clip()), i.beginPath(), i.moveTo(p[0].x, p[0].baseY), i.lineTo(p[0].x, p[0].y), Be(i, p, !1);
				for (let e = p.length - 1; e >= 0; e--) i.lineTo(p[e].x, p[e].baseY);
				i.closePath(), zi(i, l, {
					x: p[0].x,
					y: Z,
					w: Math.max(1, p[p.length - 1].x - p[0].x),
					h: $
				}, c, s, u), ie(i, a, "dataPoint", t, void 0, o, c, 1.5, s, {
					x: X,
					y: Z,
					w: Q,
					h: $
				}, u) && i.stroke(), ar && i.restore(), p = [];
			}
		};
		for (let e = 0; e < H; e++) {
			if (t.sourceHidden?.[e] === !0) {
				m();
				continue;
			}
			t.values[e] == null && (f === "gap" && m(), f !== "zero") || p.push({
				x: Wr(e),
				y: d(Ge(t, e)),
				baseY: d(qe(t, e))
			});
		}
		m();
		let h = ia(a, t, r), g = (t.showMarker === !0 || Re(t)) && t.markerSymbol !== "none";
		if (g || Ft(t)) {
			let e = Math.max(2, 2.5 * s);
			for (let r = 0; r < H; r++) {
				if (t.sourceHidden?.[r] === !0 || t.values[r] == null) continue;
				let o = n.get(r), l = rn(t, o, "circle", g);
				if (l === "none") continue;
				let f = Wr(r), p = d(Ge(t, r));
				if (h || $n(o)) {
					let e = o?.markerLineWidthEmu ?? t.markerLineWidthEmu;
					ra(i, a, t, o, r, f, p, l, o?.markerSize ?? t.markerSize ?? 5, Mt(t, o, r, c), o?.markerLine ?? t.markerLine ?? null, s, e == null ? void 0 : nt(e, s), st(t, o, r), u);
				} else i.fillStyle = c, i.beginPath(), i.arc(f, p, e, 0, Math.PI * 2), i.fill();
			}
		}
	}
	for (let e = 0; e < H; e++) {
		let t = /* @__PURE__ */ new Map(), n = /* @__PURE__ */ new Map();
		for (let r = 0; r < E.length; r++) {
			let o = E[r], l = T(o), d = Hr(e, l), g = B != null && o.useSecondaryAxis === !0, v = Ae(o), y = j(o), b = M(o), S = P(o), C = S && v.reduce((t, n) => t + Math.abs(n.values[e] ?? 0), 0) || 1, w = t.get(y) ?? 0, D = n.get(y) ?? 0, O = Math.max(0, v.indexOf(o)), { barW: A, clusterGap: ee, catStart: te } = Gr(v, d), ne = g && kn ? kn.makeToY(Z, $) : hr, re = kn ? Se?.crossesAt != null && Number.isFinite(Se.crossesAt) ? Math.max(kn.min, Math.min(kn.max, Se.crossesAt)) : Se?.crosses === "max" ? kn.max : Se?.crosses === "min" ? kn.min : Math.max(kn.min, Math.min(kn.max, 0)) : 0, F = g ? ne(re) : _r, ae = o.values[e] ?? 0, oe = S ? ae / C * Le(o) : ae, se = oe < 0, L = g && kn ? kn.max : yn.max, R = se && (o.invertIfNegative === !0 || o.automaticNegativeStyle === !0), z = Ze[r].get(e), le = z?.color ?? o.dataPointColors?.[e], ue = Ye(o), pe = K ? x(a, o, r, e) : ue ? e : G[r], ge = me(a, o, z, pe, e), _e = ge?.fillType === "solid" ? ge.color.startsWith("#") ? ge.color : `#${ge.color}` : le ? `#${le}` : ue ? N(e, o) : fe(r, o), ve = R ? o.automaticNegativeStyle === !0 || o.invertedFillHidden === !0 ? null : o.invertedFill : void 0, ye = cn(a, "dataPoint"), be = Me(z?.chartexStyle, ye, pe), xe = z?.fillHidden === !0 ? c(ye) : void 0, V = be !== void 0 || xe !== void 0 || z?.color != null, Ce = K ? Ui(a, o, z, pe, E.length) : void 0, we = Ce === void 0 ? V || ve === void 0 ? ge : ve : Ce, Te = (e, t) => {
				if (K) {
					let t = qi(a, o, z, pe, E.length, _e);
					return t.paint && t.paint.fillType !== "solid" ? !1 : Ji(e, t, s);
				}
				if (z?.lineHidden != null || z?.lineColor != null || z?.lineWidthEmu != null || z?.lineDash != null || z?.chartexStyle?.linePaintAuthored === !0 || z?.chartexStyle?.lineHidden === !0) return ie(e, a, "dataPoint", o, z, pe, _e, 1, s, t, u, !1);
				if (R && (o.invertedLineHidden != null || o.invertedLineColor != null || o.invertedLineWidthEmu != null)) return o.invertedLineHidden ? !1 : (e.strokeStyle = `#${o.invertedLineColor ?? "000000"}`, e.lineWidth = nt(o.invertedLineWidthEmu, s), e.setLineDash([]), !0);
				if (o.automaticNegativeStyle === !0) return e.strokeStyle = "#000000", e.lineWidth = .75 * s, e.setLineDash([]), !0;
				let n = R && a.chartType === "clusteredBar" && a.legacyChartStyle === 2 && o.invertedFillAuthored === !0 && o.invertedFill != null && o.invertedLineAuthored === !1, i = o.lineHidden === !0 || o.lineColor != null || o.lineWidthEmu != null;
				if (n && i) return o.lineHidden || !o.lineColor ? !1 : (e.strokeStyle = `#${o.lineColor}`, e.lineWidth = nt(o.lineWidthEmu, s), e.setLineDash([]), !0);
				if (n) return e.strokeStyle = "#000000", e.lineWidth = .75 * s, e.setLineDash([]), !0;
				let c = I(a, "dataPoint", ce.get(o) ?? r) ?? a.chartexDataPointStyle, l = k(z?.chartexStyle, pe), d = l === void 0 ? k(o.chartexStyle, pe) : l;
				return !c && !K && d === void 0 && !o.lineColor && o.lineHidden !== !0 ? !1 : ie(e, a, "dataPoint", o, z, pe, _e, 1, s, t, u, !1);
			}, H = de(z?.chartexStyle, o.chartexStyle), De = (e, t, n, r, i) => {
				if (Bi(e, we === void 0 ? o.fillPattern : we, {
					x: t,
					y: n,
					w: r,
					h: i
				}, _e, s, u), r > 0 && i > 0 && Te(e, {
					x: t,
					y: n,
					w: r,
					h: i
				})) {
					let a = e.lineWidth;
					e.strokeRect(t + a / 2, n + a / 2, Math.max(0, r - a), Math.max(0, i - a));
				}
			};
			if (l) {
				let t = O, n = b ? Ur(e, !0) + te : Ur(e, !0) + te + t * ee, c = b ? gr(se ? D : w) : vr, l = gr(b ? (se ? D : w) + oe : oe), d = _(Math.min(c, l), X, X + Q), g = _(Math.max(c, l), X, X + Q), v = Math.max(0, g - d);
				Kr && o.values[e] != null && (Kr[r][e] = {
					categoryStart: n,
					categoryEnd: n + A,
					valueEnd: _(l, X, X + Q)
				}), (!K || qn({
					categoryStart: n,
					categoryEnd: n + A,
					valueStart: c,
					valueEnd: l,
					clipCategory: !1,
					valueClipStart: X,
					valueClipEnd: X + Q
				})) && Pe(i, H, I(a, "dataPoint", ce.get(o) ?? r), pe, {
					x: d,
					y: n,
					w: v,
					h: A
				}, s, (e) => De(e, d, n, v, A));
				let y = o.seriesDataLabels, x = It(a, o, e, o.categories?.[e] ?? Ee[e] ?? "", ae, {
					visible: a.showDataLabels,
					showVal: a.showDataLabels && !S,
					showPercent: a.showDataLabels && S,
					showCatName: !1
				}, Qe[r], S ? oe / 100 : void 0, o.useSecondaryAxis && B ? B.displayUnits : a.valAxisDisplayUnits);
				if (x && eo(a, ae, L)) {
					let t = Rt(x.fontSizeHpt ?? a.dataLabelFontSizeHpt, s) ?? Math.max(7, Math.min(11, A * .6)), c = Qe[r].get(e), l = x.fontBold || y?.fontBold == null && c?.fontBold == null, g = Y(a, x.fontFace ?? a.dataLabelFontFace, "minor");
					i.font = `${x.textStyle.fontItalic ? "italic " : ""}${l ? "bold " : ""}${t}px ${g}`, Mo(i, x.text, d, n, v, A, "horizontal", x.position ?? a.dataLabelPosition ?? (b ? "ctr" : null), o.dataLabelColors?.[e] ?? x.fontColor ?? o.labelColor ?? a.dataLabelFontColor ?? null, t, {
						x: X,
						y: Z,
						w: Q,
						h: $
					}, {
						x: f,
						y: p,
						w: m,
						h
					}, c?.manualLayout, se, ro(a, c, s, g, l, x.textStyle), x.showLegendKey ? he(ce.get(o) ?? r, e) : void 0, x.textStyle, s, Wn(c?.labelBox, y?.labelBox), u);
				}
			} else {
				let t = b ? Ur(e, !1) + te : Ur(e, !1) + te + O * ee;
				if (!Ve(or, r, e) || t + A <= X || t >= X + Q) continue;
				let n = b ? ne(se ? D : w) : F, c = ne(b ? (se ? D : w) + oe : oe), l = _(Math.min(n, c), Z, Z + $), d = _(Math.max(n, c), Z, Z + $), g = Math.max(0, d - l);
				Kr && o.values[e] != null && (Kr[r][e] = {
					categoryStart: t,
					categoryEnd: t + A,
					valueEnd: _(c, Z, Z + $)
				}), (!K || qn({
					categoryStart: t,
					categoryEnd: t + A,
					valueStart: n,
					valueEnd: c,
					clipCategory: !0,
					categoryClipStart: X,
					categoryClipEnd: X + Q,
					valueClipStart: Z,
					valueClipEnd: Z + $
				})) && Pe(i, H, I(a, "dataPoint", ce.get(o) ?? r), pe, {
					x: t,
					y: l,
					w: A,
					h: g
				}, s, (e) => De(e, t, l, A, g));
				let v = o.seriesDataLabels, y = It(a, o, e, o.categories?.[e] ?? Ee[e] ?? "", ae, {
					visible: a.showDataLabels,
					showVal: a.showDataLabels && !S,
					showPercent: a.showDataLabels && S,
					showCatName: !1
				}, Qe[r], S ? oe / 100 : void 0, o.useSecondaryAxis && B ? B.displayUnits : a.valAxisDisplayUnits);
				if (y && eo(a, ae, L)) {
					let n = Rt(y.fontSizeHpt ?? a.dataLabelFontSizeHpt, s) ?? Math.max(7, Math.min(11, A * .6)), c = Qe[r].get(e), d = y.fontBold || v?.fontBold == null && c?.fontBold == null, _ = Y(a, y.fontFace ?? a.dataLabelFontFace, "minor");
					i.font = `${y.textStyle.fontItalic ? "italic " : ""}${d ? "bold " : ""}${n}px ${_}`, Mo(i, y.text, t, l, g, A, "vertical", y.position ?? a.dataLabelPosition ?? (b ? "ctr" : null), o.dataLabelColors?.[e] ?? y.fontColor ?? o.labelColor ?? a.dataLabelFontColor ?? null, n, {
						x: X,
						y: Z,
						w: Q,
						h: $
					}, {
						x: f,
						y: p,
						w: m,
						h
					}, c?.manualLayout, se, ro(a, c, s, _, d, y.textStyle), y.showLegendKey ? he(ce.get(o) ?? r, e) : void 0, y.textStyle, s, Wn(c?.labelBox, v?.labelBox), u);
				}
			}
			b && (se ? n.set(y, D + oe) : t.set(y, w + oe));
		}
	}
	if (Kr) {
		let e = /* @__PURE__ */ new Map();
		for (let t = 0; t < E.length; t++) {
			let n = E[t].barGroupIndex ?? 0, r = e.get(n);
			r ? r.push(t) : e.set(n, [t]);
		}
		for (let t of a.barGroupDecorations ?? []) if (t.seriesLines?.length === 1) {
			if (i.save(), !en(i, an(a, t.seriesLines[0], "seriesLine"), s)) {
				i.restore();
				continue;
			}
			i.beginPath(), i.rect(X, Z, Q, $), i.clip();
			for (let n of e.get(t.groupIndex) ?? []) {
				let e = Kr[n], t = T(E[n]);
				for (let n = 0; n + 1 < H; n++) {
					let r = e[n], a = e[n + 1];
					if (!r || !a) continue;
					let o = (r.categoryStart + r.categoryEnd) / 2, s = (a.categoryStart + a.categoryEnd) / 2 >= o;
					i.beginPath(), t ? (i.moveTo(r.valueEnd, s ? r.categoryEnd : r.categoryStart), i.lineTo(a.valueEnd, s ? a.categoryStart : a.categoryEnd)) : (i.moveTo(s ? r.categoryEnd : r.categoryStart, r.valueEnd), i.lineTo(s ? a.categoryStart : a.categoryEnd, a.valueEnd)), i.stroke();
				}
			}
			i.restore();
		}
	}
	let qr = (e, t) => {
		let n = Ae(e), r = Math.max(0, n.indexOf(e)), i = T(e), a = Gr(n, Hr(t, i)), o = Ur(t, i) + a.catStart;
		return M(e) ? o + a.barW / 2 : o + r * a.clusterGap + a.barW / 2;
	}, Jr = (e, t) => {
		if (Number.isInteger(t) && t >= 0 && t < H) return qr(e, t);
		let n = Ae(e), r = Math.max(0, n.indexOf(e)), i = T(e), a = Rr(i), o = Gr(n, a), s = i ? Br ? t : H - 1 - t : Br ? H - 1 - t : t;
		return (i ? Z : X) + s * a + o.catStart + (M(e) ? 0 : r) * o.clusterGap + o.barW / 2;
	};
	for (let e = 0; e < E.length; e++) {
		let t = E[e], n = T(t), r = B != null && t.useSecondaryAxis === !0, c = n ? gr : r && kn ? kn.makeToY(Z, $) : hr, l = fe(e, t), d = (t) => fn(e, t);
		for (let e of Je(t)) No(i, t, At(a, e), H, n, (e) => qr(t, e), c, d, l, s);
		ua(i, t, l, (e) => Jr(t, e - 1), c, s, t.values.map((e, t) => t + 1), {
			chart: a,
			chartRect: o,
			plotRect: {
				x: X,
				y: Z,
				w: Q,
				h: $
			},
			shapeRotationDeg: u
		}, (e, r) => n ? {
			x: c(r),
			y: Jr(t, e - 1)
		} : {
			x: Jr(t, e - 1),
			y: c(r)
		});
	}
	if ((!St || g) && !a.catAxisHidden && O(a)) {
		i.fillStyle = a.catAxisFontColor ? `#${a.catAxisFontColor}` : "#555";
		let e = a.catAxisFontSizeHpt == null ? Math.max(8, Math.min(11, zr * .5)) : mt;
		i.font = rr(e, Y(a, a.catAxisFontFace, "minor"), a.catAxisFontBold ?? !1, a.catAxisFontItalic ?? !1);
		let t = zr - 4, r = X - 4 - (f + Nt + qt), o = sr, c = ar ? ar.majorTicks.map((e) => ({
			raw: er(String(e.serial), a.catAxisFormatCode, a.date1904),
			fraction: e.fraction,
			categoryIndex: -1
		})) : Ee.map((e, t) => ({
			raw: er(e.toString(), a.catAxisFormatCode, a.date1904),
			fraction: null,
			categoryIndex: t
		}));
		for (let s of c) {
			let { raw: c } = s;
			if (g) {
				let t = s.fraction == null ? Z + Vr(s.categoryIndex, !0) * zr + zr / 2 : Z + s.fraction * $, n = Qn(a.catAxisFontSizeHpt == null ? 4 : Ln(e), a.catAxisLabelOffsetPercent), o = f + Nt + qt, l = X - n, u = a.catAxisLabelAlignment, d = u === "l" ? o : u === "ctr" ? (o + l) / 2 : l;
				i.textAlign = u === "l" ? "left" : u === "ctr" ? "center" : "right", i.textBaseline = "middle", Pt(i, c, d, t, nn(e), r);
			} else {
				let r = s.fraction != null || s.categoryIndex < 0 ? {
					fraction: s.fraction ?? .5,
					textAlign: "center"
				} : ft(s.categoryIndex, H, et(a), Br, a.catAxisLabelAlignment), l = X + r.fraction * Q;
				i.textAlign = r.textAlign, i.textBaseline = "top";
				let u = o === 0 ? t : $ * .4, d = Qn(a.catAxisFontSizeHpt == null ? 3 : n(e), a.catAxisLabelOffsetPercent);
				o === 0 ? (s.categoryIndex >= 0 ? lr[s.categoryIndex] ?? hn(c) : hn(c)).forEach((t, n) => {
					i.fillText(t, l, Pr + d + n * (e + 2));
				}) : De(i, c, l, Pr + d, o, nn(e), u);
			}
		}
		if (!g && gt) {
			let t = Qn(a.catAxisFontSizeHpt == null ? 3 : n(e), a.catAxisLabelOffsetPercent), r = e + 4;
			i.textAlign = "center", i.textBaseline = "top", i.strokeStyle = Tr, i.lineWidth = Er, i.setLineDash([]);
			let o = (e) => {
				if (!Ir || e % Fr !== 0) return Pr;
				let t = ir("major", Er, s);
				return a.catAxisMajorTickMark === "cross" ? Pr - t / 2 : a.catAxisMajorTickMark === "in" ? Pr - t : Pr;
			}, c = /* @__PURE__ */ new Set();
			for (let e = 1; e < gt.length; e++) {
				let t = gt[e] ?? [];
				for (let e = 0; e < H; e++) (t[e] ?? "") !== "" && c.add(e);
				c.add(H);
			}
			let l = dr, u = gt.map((t, n) => n === 0 ? l : _t(n, e)), d = (e) => {
				let n = Pr + t + e * r;
				for (let t = 0; t < e; t++) n += u[t];
				return n;
			}, f = Pr + t + e + 2 + l;
			for (let e = 0; e <= H; e++) {
				if (c.has(e)) continue;
				let t = X + e / H * Q;
				i.beginPath(), i.moveTo(t, o(e)), i.lineTo(t, f), i.stroke();
			}
			let p = /* @__PURE__ */ new Map();
			for (let t = 1; t < gt.length; t++) {
				let n = gt[t] ?? [], r = [];
				for (let e = 0; e < H; e++) (n[e] ?? "") !== "" && r.push(e);
				for (let o = 0; o < r.length; o++) {
					let s = r[o], c = r[o + 1] ?? H, l = n[s] ?? "", f = X + s / H * Q, m = X + c / H * Q, h = d(t), g = a.catAxisLabelAlignment, _ = g === "l" ? f : g === "r" ? m : (f + m) / 2;
					i.textAlign = g === "l" ? "left" : g === "r" ? "right" : "center", Pt(i, l, _, h, nn(e), Math.max(0, m - f - 4));
					let v = h + e + 2 + u[t];
					for (let e of [s, c]) p.set(e, Math.max(p.get(e) ?? Pr, v));
				}
			}
			for (let [e, t] of p) {
				let n = X + e / H * Q;
				i.beginPath(), i.moveTo(n, o(e)), i.lineTo(n, t), i.stroke();
			}
		}
	}
	if (re.length > 0 && !g) {
		Do(i, a, H, Wr, (e) => B && e.useSecondaryAxis === !0 ? xr : yr, () => Mr, (e, t) => e.values[t] == null ? null : Ge(e, t), zr, s, u, "background"), ar && (i.save(), i.beginPath(), i.rect(X, Z, Q, $), i.clip());
		for (let e = 0; e < re.length; e++) {
			let n = re[e], r = cr(n.dataPointOverrides), c = fe(E.length + e, n), f = B && n.useSecondaryAxis === !0 ? xr : yr, p = ce.get(n) ?? e, m = t(a, p), h = Ie(n, p), g = n.chartexStyle != null || n.lineHidden != null || n.lineColor != null || n.lineWidthEmu != null || a.chartexDataPointLineStyle != null, _ = n.smooth === !0, v = a.dispBlanksAs ?? "gap", y = [], b = [], x = () => {
				b.length !== 0 && (y.push(b), b = []);
			};
			for (let e = 0; e < H; e++) {
				let t = n.values[e];
				if (n.sourceHidden?.[e] === !0) {
					x();
					continue;
				}
				if (t == null && (v === "gap" && x(), v !== "zero")) continue;
				let r = Wr(e);
				b.push({
					x: r,
					y: f(Ge(n, e)),
					index: e
				});
			}
			if (x(), m) d(i, a, n, y, _, !1, c, 2, s, {
				x: X,
				y: Z,
				w: Q,
				h: $
			}, u, l.semanticLineNoStyleFallback !== !1);
			else {
				let e = (e) => {
					e.beginPath();
					for (let t of y) e.moveTo(t[0].x, t[0].y), Be(e, t, _);
					e.stroke();
				};
				Pe(i, de(n.chartexStyle), a.chartStyleRoles?.dataPointLine, h, {
					x: X,
					y: Z,
					w: Q,
					h: $
				}, s, (t) => {
					if (K) {
						let r = [a.chartexDataPointStyle, a.chartexDataPointLineStyle];
						if (!Ji(t, {
							...Ki(a, Gi(n, h), r, r, h, re.length, `#${Ni(a, h, re.length, n.chartexStyle)}`, { linkedNoStyleFallback: !0 }),
							semanticFallback: !1
						}, s)) return;
						e(t);
						return;
					}
					(!g || Yi(t, a, a.chartexDataPointLineStyle, n, h, re.length, c, s, { linkedNoStyleFallback: l.semanticLineNoStyleFallback })) && (g || (t.strokeStyle = c, t.lineWidth = 2, t.setLineDash([])), e(t));
				});
			}
			let S = n.showMarker !== !1 && n.markerSymbol !== "none", C = S || Ft(n), w = ia(a, n, p);
			if (C) for (let e = 0; e < H; e++) {
				if (n.sourceHidden?.[e] === !0 || n.values[e] == null) continue;
				let t = Wr(e), o = f(Ge(n, e)), l = r.get(e), d = rn(n, l, "circle", S);
				if (d !== "none") if (w || $n(l)) {
					let r = l?.markerLineWidthEmu ?? n.markerLineWidthEmu;
					ra(i, a, n, l, e, t, o, d, l?.markerSize ?? n.markerSize ?? 5, Mt(n, l, e, c), l?.markerLine ?? n.markerLine ?? null, s, r == null ? void 0 : nt(r, s), st(n, l, e), u);
				} else i.fillStyle = c, i.beginPath(), i.arc(t, o, 3, 0, Math.PI * 2), i.fill();
			}
			ua(i, n, c, (e) => Wr(e), f, s, void 0, {
				chart: a,
				chartRect: o,
				plotRect: {
					x: X,
					y: Z,
					w: Q,
					h: $
				},
				shapeRotationDeg: u
			});
		}
		Do(i, a, H, Wr, (e) => B && e.useSecondaryAxis === !0 ? xr : yr, () => Mr, (e, t) => e.values[t] == null ? null : Ge(e, t), zr, s, u, "foreground"), ar && i.restore();
	}
	if (se.length > 0) {
		let e = [], t = [];
		for (let n of se) {
			let r = n.categories ?? [];
			for (let i = 0; i < n.values.length; i++) {
				let a = Xa(r, i, !1), o = n.values[i];
				a == null || o == null || (e.push(a), t.push(o));
			}
		}
		if (e.length && t.length) {
			let n = a.secondaryCatAxis, r = a.secondaryValAxis, c = Ce(e), l = Ce(t), d = (e) => e?.minorGridlines === !0 || e?.minorTickMark != null && e.minorTickMark !== "none", g = be({
				dataMin: c.min,
				dataMax: c.max,
				explicitMin: n?.min,
				explicitMax: n?.max,
				axisLenPt: Q / s,
				axisOrientation: "horizontal",
				majorUnit: n?.majorUnit,
				minorUnit: n?.minorUnit,
				needMinor: d(n),
				logBase: n?.logBase,
				reversed: n?.orientation === "maxMin"
			}), _ = be({
				dataMin: l.min,
				dataMax: l.max,
				explicitMin: r?.min,
				explicitMax: r?.max,
				axisLenPt: $ / s,
				axisOrientation: "vertical",
				majorUnit: r?.majorUnit,
				minorUnit: r?.minorUnit,
				needMinor: d(r),
				logBase: r?.logBase,
				reversed: r?.orientation === "maxMin"
			});
			mo(i, a, se.map((e, t) => ({
				series: e,
				index: ce.get(e) ?? t
			})), !1, (e) => X + g.fraction(e) * Q, (e) => Z + $ - _.fraction(e) * $, o, X, Z, Q, $, s, !1, a.scatterStyle ?? "marker", {
				x: f,
				y: p,
				w: m,
				h
			}, _.max, void 0, u);
		}
	}
	Lr(), Se && !g && pe(i, a, Se, Te, o, X, Z, Q, s), B && kn && _e(i, a, B, kn, br, o, X, Z, Q, $, s, Bn, Vn, wr, a.date1904, Dn, ve ? "left" : "right"), mr && Ya(i, a, mr, X, Z + $ + (g ? a.valAxisHidden ? h * .02 : ke(ht) : Ct), pr, f + Nt, s);
	let Yr = at && a.series.length === 1 ? at.values.map((e, t) => me(a, at, Ze[rt].get(t), t, t)) : K ? E.flatMap((e, t) => [Li(a, G[t], E.length, e.chartexStyle, e.color), ...da([e]).map(() => void 0)]) : [];
	Ra(i, ct, Ot, f, p, m, h, X, Z, Q, $, pt + 2, s, Yr), tt(i, a, f, p, m, h, X, Z, Q, $, Nt, zt, Ut, Wt, g);
}
function Mo(e, t, n, r, i, a, o, s, c, l, u, d, f, p = !1, m, h, g, _ = 1, v, y = 0) {
	ao(e, t, {
		kind: "bar",
		rect: o === "vertical" ? {
			x: n,
			y: r,
			w: a,
			h: i
		} : {
			x: n,
			y: r,
			w: i,
			h: a
		},
		orientation: o,
		negative: p,
		position: s ?? "outEnd"
	}, u, l, c ? `#${c}` : "#333", f, d, m, h, g, _, v, y);
}
function No(e, t, n, r, i, a, o, s, c, l) {
	if (n.hidden === !0 || n.linePaintAuthored === !0 && n.color == null || !i && n.dir === "x" || i && n.dir === "y") return;
	let u = n.barType === "plus" || n.barType === "both", d = n.barType === "minus" || n.barType === "both";
	e.save(), e.strokeStyle = n.color ? `#${n.color}` : c, e.lineWidth = n.lineWidthEmu ? Math.max(.5, n.lineWidthEmu / qt * l) : Math.max(.5, l * .75), e.setLineDash(Ue(n.dash, e.lineWidth));
	let f = Math.max(e.lineWidth / 2, 2 * l);
	for (let c = 0; c < r; c++) {
		if (t.values[c] == null) continue;
		let r = s(c), l = a(c), p = o(r), m = (t) => {
			let a = o(r + t);
			e.beginPath(), i ? (e.moveTo(p, l), e.lineTo(a, l)) : (e.moveTo(l, p), e.lineTo(l, a)), e.stroke(), n.noEndCap || (e.save(), e.setLineDash([]), e.beginPath(), i ? (e.moveTo(a, l - f), e.lineTo(a, l + f)) : (e.moveTo(l - f, a), e.lineTo(l + f, a)), e.stroke(), e.restore());
		};
		if (u) {
			let e = n.plus[c];
			e != null && m(e);
		}
		if (d) {
			let e = n.minus[c];
			e != null && m(-e);
		}
	}
	e.restore();
}
function Po(e, t) {
	return /^[+-]?(?:\d+(?:[.,]\d*)?|[.,]\d+)%?$/.test(e) ? t * .15 : 0;
}
function Fo(e, t, n, r, i, a, o, s) {
	if (!t || t.side !== "t" || e.legendOverlay === !0 || e.legendManualLayout == null) return s;
	let c = {
		x: n + 4,
		y: r + o + 2,
		w: Math.max(0, i - 8),
		h: t.reserveH
	}, l = ht(e.legendManualLayout, {
		x: n,
		y: r,
		w: i,
		h: a
	}, c);
	if (!l) return s;
	let u = Math.max(0, r + s - (c.y + c.h));
	return Math.max(s, l.y + l.h - r + u);
}
function Io(i, a, o, s, c = 0) {
	let { x: l, y: u, w: f, h: p } = o, m = Jn(a), h = m.length;
	if (h === 0) return;
	let g = Sa(a, s), _ = a.chartType === "stackedLinePct" ? "percentStacked" : a.chartType === "stackedLine" ? "stacked" : "standard", v = a.plotGroups?.filter((e) => e.kind === "line") ?? [{
		kind: "line",
		seriesStart: 0,
		seriesCount: a.series.length,
		categoryAxis: "primary",
		valueAxis: "primary",
		seriesAxis: "none",
		grouping: _
	}], y = Array(a.series.length).fill(!1), b = Array(a.series.length).fill(!1), x = Array(a.series.length).fill(!1), S = Array(a.series.length).fill(null), C = a.series.map(() => Array(h).fill(0)), T = /* @__PURE__ */ new Map();
	for (let e of v) {
		let t = e.valueAxis;
		T.set(t, (T.get(t) ?? !0) && e.grouping === "percentStacked");
	}
	for (let e of v) {
		let t = e.grouping ?? "standard", n = t === "stacked" || t === "percentStacked", r = t === "percentStacked", i = a.series.slice(e.seriesStart, e.seriesStart + e.seriesCount), o = r && T.get(e.valueAxis) === !0 ? 100 : 1, s = r ? m.map((e, t) => i.reduce((e, n) => e + Math.abs(n.values[t] ?? 0), 0) || 1) : null;
		for (let t = 0; t < i.length; t++) {
			let a = e.seriesStart + t;
			y[a] = n, b[a] = r, x[a] = e.valueAxis === "secondary", S[a] = s;
			for (let e = 0; e < h; e++) {
				let c = i[t].values[e] ?? 0;
				if (!n) {
					C[a][e] = c;
					continue;
				}
				let l = t === 0 ? 0 : C[a - 1][e], u = r && s ? c / s[e] * o : c;
				C[a][e] = l + u;
			}
		}
	}
	let E = (e, t) => C[e]?.[t] ?? 0, D = v.filter((e) => e.valueAxis !== "secondary"), k = D.length > 0 && D.every((e) => e.grouping === "percentStacked"), j = a.dispBlanksAs ?? "gap", te = v.filter((e) => e.valueAxis === "secondary"), M = te.length > 0 && te.every((e) => e.grouping === "percentStacked"), N = a.secondaryValAxis && a.series.some((e, t) => x[t] || a.plotGroups == null && e.useSecondaryAxis === !0) ? a.secondaryValAxis : null, P = new Map(a.series.map((e, t) => [e, t])), re = (e) => {
		let t = P.get(e) ?? -1;
		return N != null && (x[t] || a.plotGroups == null && e.useSecondaryAxis === !0);
	}, I = Infinity, ae = -Infinity;
	for (let e = 0; e < h; e++) for (let t = 0; t < a.series.length; t++) {
		if (re(a.series[t]) || !y[t] && a.series[t].values[e] == null) continue;
		let n = E(t, e);
		I = Math.min(I, n), ae = Math.max(ae, n);
	}
	for (let e = 0; e < a.series.length; e++) {
		let t = a.series[e];
		re(t) || w(t, "y", (n) => t.values[n] == null ? null : E(e, n), (e) => {
			I = Math.min(I, e), ae = Math.max(ae, e);
		});
	}
	isFinite(I) || (I = 0, ae = 1);
	let oe = a.valAxisLogBase != null && a.valAxisLogBase >= 2;
	a.valMin == null ? k && I > 0 && !oe && (I = 0) : I = k ? a.valMin * 100 : a.valMin, a.valMax == null ? k && ae < 0 && (ae = 0) : ae = k ? a.valMax * 100 : a.valMax;
	let se = Co(i, a, f, p, s), ce = se.fontPx, L = se.topPad, R = se.bandH, z = Fa(i, a, f, p, s), { legRightW: le, legLeftW: ue, legTopH: pe, legBottomH: me } = Pn(z, a.legendOverlay === !0), he = je(a.catAxisFontSizeHpt, p, s), ge = je(a.valAxisFontSizeHpt, p, s), B = wt(a, f, p, s), ve = B.catFontPx, ye = B.valFontPx, be = B.catBandH, xe = B.valBandW, V = Ba(a), Se = V && Va(a), Ce = Ha(a, he), Te = Ka(a, s), Ee = qa(i, a, s), H = R + pe + ge / 2 + 2, Oe = (V ? Te + Ce : ke(he, a.catAxisLabelOffsetPercent)) + be + me, Ae = p - H - Oe, Me = Ht(N, a.series, Ae / s, "y", M, !1, (e, t) => x[t] || a.plotGroups == null && a.series[t].useSecondaryAxis === !0, (e, t, n) => !y[n] && e.values[t] == null ? null : E(n, t)), Ne = Math.max(8, Math.min(11, p / 20)), Fe = Rt(N?.fontSizeHpt, s) ?? Ne, Le = 0;
	if (N && Me && !N.hidden) {
		let e = i.font;
		i.font = rr(Fe, Y(a, N.fontFace, "minor"), !1, N.fontItalic ?? !1);
		let t = 0;
		for (let e of Me.majorLines) t = Math.max(t, ot(i, Dt(e, N.formatCode ?? null, a.date1904, N.displayUnits)));
		Le = t + 18, i.font = e;
	}
	let Re = N && N.title ? ne(N, s) : 0, ze = ue + Math.max(ge * 2.2 + 10 + xe, Ee), Ve = le + f * .05 + Le + Re, He = ln(a, I, ae, Ae / s, k), U = 0;
	if (!a.valAxisHidden && a.valAxisTickLabelPos !== "none" && (a.chartexParetoSortDescending != null || a.plotAreaManualLayout != null && a.plotAreaManualLayout.layoutTarget !== "inner")) {
		let e = i.font;
		i.font = rr(ge, Y(a, a.valAxisFontFace, "minor"), a.valAxisFontBold ?? !1, a.valAxisFontItalic ?? !1);
		for (let e of He.majorLines) U = Math.max(U, ot(i, In(a, e, k)));
		i.font = e;
	}
	a.chartexParetoSortDescending != null && (ze = Math.max(ze, ue + U + 10 + xe));
	let Ue = Math.max(a.valAxisHidden || a.valAxisTickLabelPos === "none" ? 0 : yt(He.majorLines.map((e) => In(a, e, k)), nn(ge)), N && Me && !N.hidden && N.tickLabelPos !== "none" ? yt(Me.majorLines.map((e) => Dt(e, N.formatCode ?? null, a.date1904, N.displayUnits)), nn(Fe)) : 0) / 2;
	H += Ue;
	let Ge = {
		t: H,
		r: Ve,
		b: Oe,
		l: ze
	}, qe = Cn({
		valAxisHidden: a.valAxisHidden,
		catAxisHidden: a.catAxisHidden,
		valLabelWidth: U,
		valLabelFontPx: ge,
		catLabelFontPx: he,
		valLabelGapPx: a.valAxisFontSizeHpt == null ? 6 : Ln(ge),
		catLabelGapPx: a.catAxisFontSizeHpt == null ? Qn(5, a.catAxisLabelOffsetPercent) : Qn(n(he), a.catAxisLabelOffsetPercent),
		outerTextMarginPx: A * s,
		valTitleBandW: xe,
		catTitleBandH: be,
		secondaryBandW: Le + Re,
		valLabelExtraTopPx: Ue
	}), Je = dn(a, l, u, f, p, s, {
		titleBand: se,
		legendReserve: z,
		pad: Ge,
		honorPlotAreaManualLayout: !0,
		manualOuterInsets: qe
	}), Ye = Co(i, a, Je.plotRect.pw, p, s);
	Math.abs(Ye.bandH - se.bandH) > .01 && (se = Ye, ce = se.fontPx, L = se.topPad, R = se.bandH, H = R + pe + ge / 2 + 2 + Ue, Ge.t = H, Je = dn(a, l, u, f, p, s, {
		titleBand: se,
		legendReserve: z,
		pad: Ge,
		honorPlotAreaManualLayout: !0,
		manualOuterInsets: qe
	}));
	let { px0: Ze, py0: Qe, pw: $e } = Je.plotRect, { ph: G } = Je.plotRect;
	if (To(i, a, a.titleManualLayout || !a.titleRichRuns?.length ? l : Ze, u, a.titleManualLayout || !a.titleRichRuns?.length ? f : $e, p, u + L, ce), $e <= 0 || G <= 0) return;
	let K = Ua(a, $e, Le + Re), q = V ? Ja(i, a, K / h, s) : null;
	q && q.totalHeight > Te && (G = Math.max(1, G - (q.totalHeight - Te)));
	let J = r(a, m), rt = Xe(a), at = Math.max(1, Math.floor(a.catAxisTickLabelSkip ?? 1)), ct = () => J ? J.majorTicks.map((e) => er(String(e.serial), a.catAxisFormatCode, a.date1904)) : Array.from({ length: Math.ceil(h / at) }, (e, t) => er((m[t * at] ?? "").toString(), a.catAxisFormatCode, a.date1904));
	if (!a.catAxisHidden && !Se && O(a) && a.catAxisTickLabelPos !== "high") {
		let e = a.plotAreaManualLayout?.layoutTarget === "inner" && a.plotAreaManualLayout.w != null && a.plotAreaManualLayout.h != null, t = yt(ct(), nn(he), rt);
		V && (Ce += t), !e && t > 0 && (G = Math.max(1, G - t));
	}
	Nn(i, a, Ze, Qe, $e, G, s, c);
	let lt = ln(a, I, ae, G / s, k);
	if (lt.max - lt.min === 0) return;
	let ut = (e) => Qe + G - lt.frac(e) * G, dt = Me ? Me.makeToY(Qe, G) : ut, pt = (e) => re(e) ? dt : ut, ht = ut(ko(a, lt.min, lt.max)), gt = N && Me ? dt(Oo(a.secondaryCatAxis?.crossesAt, a.secondaryCatAxis?.crosses, Me.min, Me.max)) : ht, _t = (e) => re(e) ? gt : ht, vt = it(a.catAxisLineColor, a.catAxisLineWidthEmu, s), bt = it(a.valAxisLineColor, a.valAxisLineWidthEmu, s), xt = a.catAxisLineColor == null ? void 0 : vt.color, St = a.catAxisLineWidthEmu == null ? void 0 : vt.width, Ct = a.valAxisLineColor == null ? void 0 : bt.color, Tt = a.valAxisLineWidthEmu == null ? void 0 : bt.width, Et = et(a), Ot = Ke(a), kt = J ? (e) => Ze + J.positions[e] * $e : Et ? (e) => Ze + ((Ot ? h - 1 - e : e) + .5) / h * $e : (e) => {
		let t = Ot ? h - 1 - e : e;
		return Ze + (h === 1 ? $e / 2 : t / (h - 1) * $e);
	};
	if (!a.valAxisHidden) {
		i.font = rr(ge, Y(a, a.valAxisFontFace, "minor"), a.valAxisFontBold ?? !1, a.valAxisFontItalic ?? !1), i.textBaseline = "middle";
		let t = mn(a, s), n = Mn(a, s);
		for (let e of lt.minorLines) Vt(i, Ze, $e, ut(e), !1, n);
		let r = e(a), o = a.valAxisTickLabelPos !== "none";
		for (let e of lt.majorLines) {
			let n = ut(e);
			if (r && Vt(i, Ze, $e, n, e === 0, t), F(i, a.valAxisMajorTickMark, "val", Ze, n, Ct, Tt, !1, a.valAxisLineHidden, "major", s, a.valAxisLineDash), o) {
				i.fillStyle = a.valAxisFontColor ? `#${a.valAxisFontColor}` : "#555", i.textAlign = "right";
				let t = a.valAxisFontSizeHpt == null ? 6 : Ln(ge);
				Pt(i, In(a, e, k), Ze - t, n, nn(ge));
			}
		}
		if (a.valAxisMinorTickMark && a.valAxisMinorTickMark !== "none") for (let e of lt.minorTicks) F(i, a.valAxisMinorTickMark, "val", Ze, ut(e), Ct, Tt, !1, a.valAxisLineHidden, "minor", s, a.valAxisLineDash);
	}
	if (N && Me && we(i, N, Me, dt, Ze, $e, s), !a.catAxisHidden && W(a)) {
		let e = ee(a, s);
		i.strokeStyle = e.color, i.lineWidth = e.width;
		let t = e.dash.length > 0 && i.getLineDash ? i.getLineDash() : [];
		e.dash.length > 0 && i.setLineDash(e.dash);
		let n = J ? J.majorTicks.map((e) => e.fraction) : We(a, h);
		for (let e of n) {
			let t = Ze + e * $e;
			i.beginPath(), i.moveTo(t, Qe), i.lineTo(t, Qe + G), i.stroke();
		}
		e.dash.length > 0 && i.setLineDash(t);
	}
	!a.catAxisHidden && !a.catAxisLineHidden && En(i, Ze, ht, Ze + $e, ht, vt.color, vt.width, a.catAxisLineDash), !a.valAxisHidden && !a.valAxisLineHidden && En(i, Ze, Qe, Ze, Qe + G, bt.color, bt.width, a.valAxisLineDash);
	let jt = J ? (J.categoryBandFractions[0] ?? 0) * $e : Et ? $e / h : h > 1 ? $e / (h - 1) : $e, Nt = new Map(a.series.map((e, t) => [e, t]));
	Do(i, a, h, kt, pt, _t, (e, t) => {
		let n = Nt.get(e);
		return n == null ? null : E(n, t);
	}, jt, s, c, "background");
	let It = Math.max(1, 2.25 * s), Lt = Math.max(2, 2.5 * s), zt = je(a.dataLabelFontSizeHpt, p, s), Bt = [];
	for (let e = 0; e < a.series.length; e++) {
		let n = a.series[e], r = y[e], _ = S[e], v = cr(n.dataPointOverrides), x = fe(e, n), C = Ie(n, e), w = pt(n), T = n.smooth === !0, D = [], O = [], k = () => {
			O.length > 0 && D.push(O), O = [];
		};
		for (let t = 0; t < h; t++) {
			if (n.sourceHidden?.[t] === !0) {
				k();
				continue;
			}
			if (!r && n.values[t] == null) {
				if (j === "gap") {
					k();
					continue;
				}
				if (j === "span") continue;
			}
			O.push({
				x: kt(t),
				y: w(E(e, t)),
				index: t
			});
		}
		k();
		let A = {
			x: Ze,
			y: Qe,
			w: $e,
			h: G
		};
		t(a, e) ? d(i, a, n, D, T, !1, x, It, s, A, c) : Pe(i, de(n.chartexStyle), a.chartStyleRoles?.dataPointLine, C, A, s, (e) => {
			if (ie(e, a, "dataPointLine", n, void 0, C, x, It, s, A, c)) {
				e.beginPath();
				for (let t of D) t.length !== 0 && (e.moveTo(t[0].x, t[0].y), Be(e, t, T));
				e.stroke();
			}
		});
		let ee = (t) => E(e, t);
		for (let e of n.errBars ?? []) $a(i, n, At(a, e), h, kt, w, ee, x);
		i.fillStyle = x;
		let te = n.showMarker !== !1 && n.markerSymbol !== "none", M = te || Ft(n), ne = ia(a, n, e), P = (n.dataLabelOverrides?.length ?? 0) > 0 || n.seriesDataLabels != null;
		P && Bt.push(() => {
			so(i, n, m, h, kt, w, ee, G, s, a.date1904 ?? !1, r || j === "zero", Y(a, a.dataLabelFontFace, "minor"), a.dataLabelPosition ?? "r", {
				x: l,
				y: Qe,
				w: f,
				h: G
			}, {
				x: l,
				y: u,
				w: f,
				h: p
			}, b[e] && _ ? (e) => (n.values[e] ?? 0) / _[e] : void 0, (e) => {
				if (!M) return 0;
				let t = v.get(e);
				return !ne && !$n(t) ? Lt : rn(n, t, "circle", te) === "none" ? 0 : (t?.markerSize ?? n.markerSize ?? 5) / 2 * s;
			}, (e) => Y(a, e, "minor"), re(n) ? N?.displayUnits : a.valAxisDisplayUnits, (t) => g(e, t), (e) => eo(a, e, re(n) && Me ? Me.max : lt.max), c);
		});
		for (let t = 0; t < h; t++) {
			if (n.sourceHidden?.[t] === !0 || !r && n.values[t] == null && j !== "zero") continue;
			let o = E(e, t);
			if (M) {
				let e = v.get(t);
				if (ne || $n(e)) {
					let r = rn(n, e, "circle", te);
					if (r !== "none") {
						let l = e?.markerSize ?? n.markerSize ?? 5, u = Mt(n, e, t, x), d = e?.markerLine ?? n.markerLine ?? null, f = e?.markerLineWidthEmu ?? n.markerLineWidthEmu;
						ra(i, a, n, e, t, kt(t), w(o), r, l, u, d, s, f == null ? void 0 : nt(f, s), st(n, e, t), c);
					}
				} else i.beginPath(), i.arc(kt(t), w(o), Lt, 0, Math.PI * 2), i.fill();
			}
		}
		a.showDataLabels && !P && Bt.push(() => {
			for (let t = 0; t < h; t++) {
				if (n.sourceHidden?.[t] === !0 || !r && n.values[t] == null && j !== "zero") continue;
				let o = E(e, t), s = mt({
					showValue: !0,
					sourceValue: n.values[t] ?? 0,
					valueDivisor: vn(re(n) ? N?.displayUnits : a.valAxisDisplayUnits),
					formatCode: a.dataLabelFormatCode ?? n.valFormatCode ?? null,
					date1904: a.date1904
				});
				no(i, kt(t), w(o), s, a.dataLabelPosition ?? "r", zt, a.dataLabelFontColor ?? void 0, a.dataLabelFontBold ?? !1, Y(a, a.dataLabelFontFace, "minor"), M ? Lt + 1 : 2, {
					x: Ze,
					y: Qe,
					w: $e,
					h: G
				});
			}
		}), ua(i, n, x, kt, w, s, void 0, {
			chart: a,
			chartRect: o,
			plotRect: {
				x: Ze,
				y: Qe,
				w: $e,
				h: G
			},
			shapeRotationDeg: c
		});
	}
	Do(i, a, h, kt, pt, _t, (e, t) => {
		let n = Nt.get(e);
		return n == null ? null : E(n, t);
	}, jt, s, c, "foreground");
	for (let e of Bt) e();
	if (!a.catAxisHidden) {
		let e = a.catAxisFontColor ? `#${a.catAxisFontColor}` : "#555";
		i.fillStyle = e, i.textAlign = "center", i.textBaseline = "top", i.font = rr(he, Y(a, a.catAxisFontFace, "minor"), a.catAxisFontBold ?? !1, a.catAxisFontItalic ?? !1);
		let t = Math.max(1, Math.floor(a.catAxisTickMarkSkip ?? 1)), r = J ? J.majorTicks.map((e) => Ze + e.fraction * $e) : Array.from({ length: Math.ceil(h / t) }, (e, n) => kt(n * t));
		for (let e of r) F(i, a.catAxisMajorTickMark, "cat", ht, e, xt, St, !1, a.catAxisLineHidden, "major", s, a.catAxisLineDash);
		if (a.catAxisMinorTickMark && a.catAxisMinorTickMark !== "none" && J) for (let e of J.minorTicks) F(i, a.catAxisMinorTickMark, "cat", ht, Ze + e.fraction * $e, xt, St, !1, a.catAxisLineHidden, "minor", s, a.catAxisLineDash);
		let o = !Se && O(a), c = at, l = rt, u = J ? J.majorTicks.map((e) => ({
			label: er(String(e.serial), a.catAxisFormatCode, a.date1904),
			x: Ze + e.fraction * $e,
			categoryIndex: -1
		})) : Array.from({ length: Math.ceil(h / c) }, (e, t) => {
			let n = t * c;
			return {
				label: er((m[n] ?? "").toString(), a.catAxisFormatCode, a.date1904),
				x: kt(n),
				categoryIndex: n
			};
		});
		for (let t of u) {
			let r = t.categoryIndex < 0 ? null : ft(t.categoryIndex, h, et(a), Ke(a), a.catAxisLabelAlignment), s = r ? Ze + r.fraction * $e : t.x;
			if (!o) continue;
			i.textAlign = r?.textAlign ?? "center", i.fillStyle = e;
			let c = t.label;
			if (!c) continue;
			let u = Qn(a.catAxisFontSizeHpt == null ? 5 : n(he), a.catAxisLabelOffsetPercent), d = a.catAxisTickLabelPos ?? "nextTo";
			De(i, c, s, (d === "nextTo" ? ht : d === "high" ? Qe : Qe + G) + u, l, nn(he));
		}
	}
	if (N && Me) {
		let e = a.valAxisFontColor ? `#${a.valAxisFontColor}` : "#555";
		_e(i, a, N, Me, dt, o, Ze, Qe, $e, G, s, Fe, Le, e, a.date1904);
	}
	q && Ya(i, a, q, Ze, Qe + G + Ce, K, l + ue, s), Ra(i, a, z, l, u, f, p, Ze, Qe, $e, G, R + 2, s), tt(i, a, l, u, f, p, Ze, Qe, $e, G, ue, me, ve, ye);
}
function Lo(n, i, a, o, s = 0) {
	let { x: c, y: l, w: u, h: f } = a, p = Jn(i), m = p.length;
	if (m === 0) return;
	let h = Sa(i, o), g = i.plotGroups?.find((e) => e.kind === "stock"), _ = g ? i.series.slice(g.seriesStart, g.seriesStart + g.seriesCount) : i.series, v = i.plotGroups == null ? [] : i.plotGroups.filter((e) => e.kind === "line").flatMap((e) => i.series.slice(e.seriesStart, e.seriesStart + e.seriesCount)), y = [..._, ...v], b = new Map(i.series.map((e, t) => [e, t])), x = _.length >= 4, S = x ? 0 : -1, C = +!!x, T = x ? 2 : 1, E = x ? 3 : 2, D = _[C], k = _[T], A = _[E], j = S >= 0 ? _[S] : void 0, ee = _[0], te = _.at(-1), M = i.secondaryValAxis && y.some((e) => e.useSecondaryAxis === !0) ? i.secondaryValAxis : null, N = (e) => M != null && e.useSecondaryAxis === !0, P = Co(n, i, u, f, o), re = P.fontPx, I = P.topPad, ae = P.bandH, oe = Fa(n, i, u, f, o), { legRightW: se, legLeftW: ce, legBottomH: L, legTopH: R } = Pn(oe, i.legendOverlay === !0), z = je(i.catAxisFontSizeHpt, f, o), le = je(i.valAxisFontSizeHpt, f, o), ue = wt(i, u, f, o), pe = ue.catFontPx, me = ue.valFontPx, he = ue.catBandH, ge = ue.valBandW, B = Ba(i), ve = B && Va(i), ye = Ha(i, z), be = Ka(i, o), xe = qa(n, i, o), V = ae + R + le / 2 + 2, Se = (B ? be + ye : ke(z, i.catAxisLabelOffsetPercent)) + he + L, Ce = f - V - Se, Te = Ht(M, y, Ce / o), Ee = Math.max(8, Math.min(11, f / 20)), H = Rt(M?.fontSizeHpt, o) ?? Ee, Oe = 0;
	if (M && Te && !M.hidden) {
		let e = n.font;
		n.font = rr(H, Y(i, M.fontFace, "minor"), M.fontBold ?? !1, M.fontItalic ?? !1);
		let t = 0;
		for (let e of Te.majorLines) t = Math.max(t, ot(n, Dt(e, M.formatCode ?? null, i.date1904, M.displayUnits)));
		Oe = t + 18, n.font = e;
	}
	let Ae = M?.title ? ne(M, o) : 0, Me = Infinity, Ne = -Infinity;
	for (let e of y) if (!N(e)) for (let t = 0; t < m; t++) {
		let n = e.values[t];
		n != null && (Me = Math.min(Me, n), Ne = Math.max(Ne, n));
	}
	for (let e of y) N(e) || w(e, "y", (t) => e.values[t] ?? null, (e) => {
		Me = Math.min(Me, e), Ne = Math.max(Ne, e);
	});
	isFinite(Me) || (Me = 0, Ne = 1), i.valMin != null && (Me = i.valMin), i.valMax != null && (Ne = i.valMax);
	let Fe = {
		t: V + Math.max(i.valAxisHidden || i.valAxisTickLabelPos === "none" ? 0 : yt(ln(i, Me, Ne, Ce / o).majorLines.map((e) => In(i, e, !1)), nn(le)), M && Te && !M.hidden && M.tickLabelPos !== "none" ? yt(Te.majorLines.map((e) => Dt(e, M.formatCode ?? null, i.date1904, M.displayUnits)), nn(H)) : 0) / 2,
		r: se + u * .05 + Oe + Ae,
		b: Se,
		l: ce + Math.max(le * 2.2 + 10 + ge, xe)
	};
	To(n, i, c, l, u, f, l + I, re);
	let Le = dn(i, c, l, u, f, o, {
		titleBand: P,
		legendReserve: oe,
		pad: Fe,
		honorPlotAreaManualLayout: !0
	}), { px0: ze, py0: Ve, pw: He } = Le.plotRect, { ph: U } = Le.plotRect;
	if (He <= 0 || U <= 0) return;
	let Ue = Ua(i, He, Oe + Ae), We = B ? Ja(n, i, Ue / m, o) : null;
	We && We.totalHeight > be && (U = Math.max(1, U - (We.totalHeight - be)));
	let Ge = r(i, p);
	if (!i.catAxisHidden && !ve && O(i)) {
		let e = Math.max(1, Math.floor(i.catAxisTickLabelSkip ?? 1)), t = Ge && Ge.majorTicks.length > 0 ? Ge.majorTicks.map((e) => er(String(e.serial), i.catAxisFormatCode, i.date1904)) : Array.from({ length: Math.ceil(m / e) }, (t, n) => er((p[n * e] ?? "").toString(), i.catAxisFormatCode, i.date1904)), n = i.plotAreaManualLayout?.layoutTarget === "inner" && i.plotAreaManualLayout.w != null && i.plotAreaManualLayout.h != null, r = yt(t, nn(z), Xe(i));
		B && (ye += r), !n && r > 0 && (U = Math.max(1, U - r));
	}
	Nn(n, i, ze, Ve, He, U, o, s);
	let qe = ln(i, Me, Ne, U / o);
	if (qe.max - qe.min === 0) return;
	let Je = (e) => Ve + U - qe.frac(e) * U, Ye = Te?.makeToY(Ve, U) ?? Je, Ze = (e) => N(e) ? Ye : Je, Qe = et(i), W = Ke(i), $e = Ge ? (e) => ze + Ge.positions[e] * He : Qe ? (e) => ze + ((W ? m - 1 - e : e) + .5) / m * He : (e) => {
		let t = W ? m - 1 - e : e;
		return ze + (m === 1 ? He / 2 : t / (m - 1) * He);
	};
	if (!i.valAxisHidden) {
		n.font = rr(le, Y(i, i.valAxisFontFace, "minor"), i.valAxisFontBold ?? !1, i.valAxisFontItalic ?? !1), n.textBaseline = "middle";
		let t = mn(i, o), r = Mn(i, o);
		for (let e of qe.minorLines) Vt(n, ze, He, Je(e), !1, r);
		let a = e(i), s = i.valAxisTickLabelPos !== "none";
		for (let e of qe.majorLines) {
			let r = Je(e);
			a && Vt(n, ze, He, r, e === 0, t), F(n, i.valAxisMajorTickMark, "val", ze, r, void 0, void 0, !1, i.valAxisLineHidden, "major", o, i.valAxisLineDash), s && (n.fillStyle = i.valAxisFontColor ? `#${i.valAxisFontColor}` : "#555", n.textAlign = "right", Pt(n, In(i, e, !1), ze - 6, r, nn(le)));
		}
		for (let e of qe.minorTicks) F(n, i.valAxisMinorTickMark, "val", ze, Je(e), void 0, void 0, !1, i.valAxisLineHidden, "minor", o, i.valAxisLineDash);
	}
	M && Te && we(n, M, Te, Ye, ze, He, o);
	let G = it(i.catAxisLineColor, i.catAxisLineWidthEmu, o), K = it(i.valAxisLineColor, i.valAxisLineWidthEmu, o);
	if (!i.catAxisHidden && !i.catAxisLineHidden && En(n, ze, Ve + U, ze + He, Ve + U, G.color, G.width, i.catAxisLineDash), !i.valAxisHidden && !i.valAxisLineHidden && En(n, ze, Ve, ze, Ve + U, K.color, K.width, i.valAxisLineDash), i.stockDropLines) {
		let e = i.stockAutomaticStyle ? {
			lineColors: [i.stockAutomaticStyle.lineColor],
			linePaintAuthored: !0,
			lineWidthEmu: i.stockAutomaticStyle.lineWidthEmu
		} : void 0, t = an(i, i.stockDropLines, "dropLine", e);
		(t.paintAuthored !== !0 || t.color != null) && (t.color != null || t.widthEmu != null || t.dash != null) && en(n, t, o) && Lt(n, _, m, $e, (e) => Ze(e), () => Ve + U, (e, t) => e.values[t] ?? null);
	}
	if (i.stockHiLowLines === !0 && D != null && k != null && D && k) {
		let e = an(i, i.stockHiLowLineStyle ?? { color: i.stockHiLowLineColor ?? null }, "hiLoLine", i.stockAutomaticStyle ? {
			lineColors: [i.stockAutomaticStyle.lineColor],
			linePaintAuthored: !0,
			lineWidthEmu: i.stockAutomaticStyle.lineWidthEmu
		} : void 0);
		if ((e.paintAuthored !== !0 || e.color != null) && (e.color != null || e.widthEmu != null || e.dash != null) && en(n, e, o)) for (let e = 0; e < m; e++) {
			let t = D.values[e], r = k.values[e];
			if (t == null || r == null) continue;
			let i = $e(e), a = Ze(D), o = Ze(k);
			n.beginPath(), n.moveTo(i, a(t)), n.lineTo(i, o(r)), n.stroke();
		}
	}
	let q = (e, t, r) => {
		if (!e) return;
		let a = b.get(e) ?? t, c = fe(a, e), l = Ie(e, a), u = cr(e.dataPointOverrides), d = e.markerSymbol != null && e.markerSymbol !== "none" && Re(e), f = Math.max(3, He / m * .22);
		for (let t = 0; t < m; t++) {
			let a = e.values[t];
			if (a == null) continue;
			let p = $e(t), m = Ze(e)(a), h = u.get(t);
			if (h?.markerSymbol === "none" || h?.markerSymbol == null && e.markerSymbol === "none") continue;
			if (d || h?.markerSymbol != null && h.markerSymbol !== "none") {
				let r = h?.markerSymbol ?? e.markerSymbol ?? "circle";
				ra(n, i, e, h, t, p, m, r, h?.markerSize ?? e.markerSize ?? 3, Mt(e, h, t, c), h?.markerLine ?? e.markerLine ?? null, o, (h?.markerLineWidthEmu ?? e.markerLineWidthEmu) == null ? void 0 : nt(h?.markerLineWidthEmu ?? e.markerLineWidthEmu, o), st(e, h, t), s);
				continue;
			}
			let g = r === "right" ? p : r === "left" ? p - f : p - f / 2, _ = r === "right" ? p + f : r === "left" ? p : p + f / 2;
			Pe(n, de(h?.chartexStyle, e.chartexStyle), i.chartStyleRoles?.dataPointLine, l, {
				x: g,
				y: m,
				w: _ - g,
				h: 0
			}, o, (t) => {
				ie(t, i, "dataPointLine", e, h, l, c, Math.max(1, .75 * o), o, {
					x: ze,
					y: Ve,
					w: He,
					h: U
				}, s) && (t.beginPath(), t.moveTo(g, m), t.lineTo(_, m), t.stroke());
			});
		}
	};
	for (let e of v) {
		let r = b.get(e) ?? 0, c = fe(Math.max(0, r), e), l = Ze(e), u = cr(e.dataPointOverrides), f = Ie(e, r), p = t(i, r), h = [], g = [], _ = () => {
			g.length > 0 && h.push(g), g = [];
		};
		for (let t = 0; t < m; t++) {
			let n = e.values[t];
			if (n == null) {
				_();
				continue;
			}
			g.push({
				x: $e(t),
				y: l(n),
				index: t
			});
		}
		_(), p ? d(n, i, e, h, e.smooth === !0, !1, c, Math.max(1, 2.25 * o), o, {
			x: ze,
			y: Ve,
			w: He,
			h: U
		}, s) : Pe(n, de(e.chartexStyle), i.chartStyleRoles?.dataPointLine, f, {
			x: ze,
			y: Ve,
			w: He,
			h: U
		}, o, (t) => {
			if (t.save(), ie(t, i, "dataPointLine", e, void 0, f, c, Math.max(1, 2.25 * o), o, {
				x: ze,
				y: Ve,
				w: He,
				h: U
			}, s)) {
				t.beginPath();
				for (let n of h) t.moveTo(n[0].x, n[0].y), Be(t, n, e.smooth === !0);
				t.stroke();
			}
			t.restore();
		});
		let v = e.showMarker !== !1 && e.markerSymbol !== "none";
		if (v || Ft(e)) for (let t = 0; t < m; t++) {
			let r = e.values[t];
			if (r == null) continue;
			let a = u.get(t), d = rn(e, a, "circle", v);
			d !== "none" && ra(n, i, e, a, t, $e(t), l(r), d, a?.markerSize ?? e.markerSize ?? 5, Mt(e, a, t, c), a?.markerLine ?? e.markerLine ?? null, o, (a?.markerLineWidthEmu ?? e.markerLineWidthEmu) == null ? void 0 : nt(a?.markerLineWidthEmu ?? e.markerLineWidthEmu, o), st(e, a, t), s);
		}
		ua(n, e, c, $e, l, o, void 0, {
			chart: i,
			chartRect: a,
			plotRect: {
				x: ze,
				y: Ve,
				w: He,
				h: U
			},
			shapeRotationDeg: s
		});
	}
	if (i.stockUpDownBars && ee && te) {
		let e = i.stockUpDownBarStyle ?? {
			gapWidthPercent: 150,
			up: {},
			down: {}
		}, t = {
			...e,
			up: Ct(i, e.up, "upBar", i.stockAutomaticStyle ?? void 0),
			down: Ct(i, e.down, "downBar", i.stockAutomaticStyle ?? void 0)
		}, r = Ge ? (Ge.categoryBandFractions[0] ?? 0) * He : Qe ? He / m : m > 1 ? He / (m - 1) : He;
		Eo(n, (e) => ee.values[e] ?? null, (e) => te.values[e] ?? null, m, $e, Ze(ee), Ze(te), r, t, o, void 0, s);
	}
	q(j, S, "left"), (D?.markerSymbol != null || D && Ft(D)) && q(D, C, "both"), (k?.markerSymbol != null || k && Ft(k)) && q(k, T, "both"), q(A, E, "right");
	for (let e of y) {
		let t = fe(b.get(e) ?? 0, e);
		for (let r of e.errBars ?? []) $a(n, e, At(i, r), m, $e, Ze(e), (t) => e.values[t] ?? 0, t);
	}
	if (_.length < 3) for (let e = 0; e < _.length; e++) q(_[e], e, "both");
	for (let e of y) {
		let t = b.get(e) ?? 0;
		to(n, e, p, !0, $e, Ze(e), U, o, i.date1904, Y(i, i.dataLabelFontFace, "minor"), i.dataLabelPosition ?? "r", {
			x: ze,
			y: Ve,
			w: He,
			h: U
		}, a, (e) => Y(i, e, "minor"), N(e) ? M?.displayUnits : i.valAxisDisplayUnits, (e) => h(t, e), (t) => eo(i, t, N(e) ? Te?.max ?? qe.max : qe.max), s);
	}
	if (!i.catAxisHidden) {
		let e = Math.max(1, Math.floor(i.catAxisTickLabelSkip ?? 1)), t = i.catAxisFontColor ? `#${i.catAxisFontColor}` : "#555";
		n.fillStyle = t, n.textAlign = "center", n.textBaseline = "top", n.font = rr(z, Y(i, i.catAxisFontFace, "minor"), i.catAxisFontBold ?? !1, i.catAxisFontItalic ?? !1);
		let r = Ge ? (Ge.categoryBandFractions[0] ?? 0) * He - 4 : He / m * e - 4, a = !ve && O(i), s = Xe(i), c = Ge && Ge.majorTicks.length > 0 ? Ge.majorTicks.map((e) => ({
			label: er(String(e.serial), i.catAxisFormatCode, i.date1904),
			x: ze + e.fraction * He,
			categoryIndex: -1
		})) : Array.from({ length: Math.ceil(m / e) }, (t, n) => {
			let r = n * e;
			return {
				label: er((p[r] ?? "").toString(), i.catAxisFormatCode, i.date1904),
				x: $e(r),
				categoryIndex: r
			};
		});
		for (let e of c) {
			let c = e.categoryIndex < 0 ? null : ft(e.categoryIndex, m, et(i), Ke(i), i.catAxisLabelAlignment), l = c ? ze + c.fraction * He : e.x;
			if (F(n, i.catAxisMajorTickMark, "cat", Ve + U, l, G.color, G.width, !1, i.catAxisLineHidden, "major", o, i.catAxisLineDash), !a) continue;
			n.textAlign = c?.textAlign ?? "center", n.fillStyle = t;
			let u = e.label, d = s === 0 ? r : U * .4;
			De(n, u, l, Ve + U + Qn(5, i.catAxisLabelOffsetPercent), s, nn(z), d);
		}
		if (i.catAxisMinorTickMark && i.catAxisMinorTickMark !== "none" && Ge) for (let e of Ge.minorTicks) F(n, i.catAxisMinorTickMark, "cat", Ve + U, ze + e.fraction * He, void 0, void 0, !1, i.catAxisLineHidden, "minor", o, i.catAxisLineDash);
	}
	if (M && Te) {
		let e = i.valAxisFontColor ? `#${i.valAxisFontColor}` : "#555";
		_e(n, i, M, Te, Ye, a, ze, Ve, He, U, o, H, Oe, e, i.date1904);
	}
	We && Ya(n, i, We, ze, Ve + U + ye, Ue, c + ce, o), Ra(n, i, oe, c, l, u, f, ze, Ve, He, U, ae + 2, o), tt(n, i, c, l, u, f, ze, Ve, He, U, ce, L, pe, me);
}
var Ro = (e) => e[0] + e[2] > e[1] + e[3] ? [[
	0,
	1,
	2
], [
	0,
	2,
	3
]] : [[
	0,
	1,
	3
], [
	1,
	2,
	3
]], zo = 2e5, Bo = 1.25;
function Vo(e, t, n) {
	if (e.length === 0) return [];
	let r = [], i = (e) => n ? e.value >= t : e.value <= t, a = e[e.length - 1], o = i(a);
	for (let n of e) {
		let e = i(n);
		if (e !== o) {
			let e = n.value - a.value, i = e === 0 ? 0 : (t - a.value) / e;
			r.push({
				x: a.x + (n.x - a.x) * i,
				y: a.y + (n.y - a.y) * i,
				depth: a.depth + (n.depth - a.depth) * i,
				value: t
			});
		}
		e && r.push(n), a = n, o = e;
	}
	return r;
}
function Ho(t, n, r, i, a = 0) {
	let { x: o, y: s, w: l, h: u } = r, d = Jn(n), f = n.series, m = d.length, g = f.length;
	if (m < 2 || g < 2) return;
	let _ = Infinity, v = -Infinity;
	for (let e of f) for (let t = 0; t < m; t++) {
		let n = e.values[t];
		n == null || !Number.isFinite(n) || (_ = Math.min(_, n), v = Math.max(v, n));
	}
	if (!Number.isFinite(_) || !Number.isFinite(v)) return;
	n.valMin != null && (_ = n.valMin), n.valMax != null && (v = n.valMax);
	let y = {
		...n.threeD ?? {},
		rotationX: n.threeD?.rotationX ?? 15,
		rotationY: n.threeD?.rotationY ?? 20,
		rightAngleAxes: n.threeD?.rightAngleAxes ?? !1,
		perspective: n.threeD?.perspective ?? 30
	}, x = wr(y), S = Sr(y), C = Cr(y, r, {
		sceneDepthScale: Bo,
		perspectiveTangentGain: S
	}), w;
	if (C) {
		let e = C.topology.axisX === "min" ? C.front.x : C.front.x + C.front.w, t = C.project(e, C.front.y + C.front.h, C.topology.nearDepth), n = C.project(e, C.front.y, C.topology.nearDepth);
		w = Math.hypot(n.x - t.x, n.y - t.y) / i;
	}
	let T = n.valAxisMajorUnit == null ? b(_, v, w) : null, E = ln(T == null ? n : {
		...n,
		valAxisMajorUnit: T
	}, _, v, w), D = E.step;
	if (!(D > 0) || !Number.isFinite(D)) return;
	let O = n.valMin ?? E.min, A = n.valMax ?? Math.max(O + D, O + Math.ceil((v - O) / D) * D), j = A - O;
	if (!(j > 0) || !Number.isFinite(j)) return;
	let ee = Math.ceil(j / D), te = Math.floor(j / D + 1e-9) + 1, M = (m - 1) * (g - 1) * 2;
	if (!Number.isSafeInteger(ee) || !Number.isSafeInteger(te) || ee < 1 || te < 2 || M < 1 || ee > 512 || te > 512 || ee > Math.floor(zo / M) || te > zo) return;
	let N = ee, ne = dt(n) ? (e) => 1 - (e - O) / j : (e) => (e - O) / j, P = Array.from({ length: te }, (e, t) => O + t * D), re = (e, t) => {
		let n = Math.min(e, t), r = Math.max(e, t), i = Math.max(1, Math.floor((n - O) / D) + 1), a = Math.min(N - 1, Math.ceil((r - O) / D) - 1), o = [0];
		if (e !== t) for (let n = i; n <= a; n++) o.push((O + n * D - e) / (t - e));
		return o.push(1), o.sort((e, t) => e - t), o;
	}, ie = Array.from({ length: N }, (e, t) => n.themeAccentColors?.[t % 6] ? `#${n.themeAccentColors[t % 6]}` : f[t]?.color ? `#${f[t].color}` : fe(t, f[t])), F = new Map((n.surfaceBandFormats ?? []).map((e) => [e.idx, e])), I = n.linkedChartStyleRoles?.dataPoint3D ?? (n.classicChartStyleRoles == null ? n.chartStyleRoles?.dataPoint3D : void 0), ae = N <= 48 ? n.classicSurfaceBandStyles?.byBandCount?.[N - 1] ?? n.classicSurfaceBandStyles?.fixed : void 0, oe = Fn(ae, I), L = n.linkedChartStyleRoles?.dataPointWireframe ?? (n.classicChartStyleRoles == null ? n.chartStyleRoles?.dataPointWireframe : void 0), R = Fn(ae, L), z = (e) => e?.lineNoStyle !== !0 && (e?.linePaintAuthored === !0 || e?.lineHidden === !0 || (e?.lineColors?.length ?? 0) > 0 || (e?.linePaints?.length ?? 0) > 0), le = n.surfaceWireframe === !0 ? [] : Array.from({ length: N }, (e, t) => {
		let n = F.get(t), r, i = !0;
		if (n?.fillHidden === !0) r = c(I), r !== void 0 && (i = !1);
		else if (n?.fill) r = n.fill, i = !1;
		else {
			let e = Me(n?.style, I, t);
			e === void 0 ? r = Ge(I, t) : (r = e, i = !1);
		}
		return r === void 0 && n?.fillHidden === !0 && (r = Ge(I, t)), r === void 0 && (r = Ge(ae, t)), {
			recipe: r,
			fromRole: i
		};
	}), ue = le.map((e) => e.recipe), de = n.surfaceWireframe === !0 ? [] : Array.from({ length: N }, (e, t) => {
		let n = F.get(t), r;
		return r = n?.lineHidden === !0 ? qe(I) : n?.lineColor ? {
			fillType: "solid",
			color: n.lineColor
		} : Ze(I, I, t, n?.style), r === void 0 && n?.lineHidden === !0 && (r = k(I, t)), r === void 0 && (r = k(ae, t)), r;
	}), pe = (e, t) => {
		let n = e, r = n?.lineDashAuthored === !0 || n?.lineDash != null || n?.lineCustomDash != null;
		return {
			lineWidthEmu: n?.lineWidthEmu ?? t.lineWidthEmu,
			lineDash: r ? n?.lineDash : t.lineDash,
			lineCustomDash: r ? n?.lineCustomDash : t.lineCustomDash,
			lineCap: n?.lineCap ?? t.lineCap,
			lineJoin: n?.lineJoin ?? t.lineJoin,
			lineCompound: n?.lineCompound ?? t.lineCompound
		};
	}, me = R?.lineColorIndex, he;
	he = z(R) ? R?.lineHidden === !0 ? k(R, 0) : me == null ? void 0 : me == null ? null : k(R, me) : void 0;
	let ge = me == null && z(R) && R?.lineHidden !== !0 ? Array.from({ length: N }, (e, t) => k(R, t)) : [], _e = R == null ? {
		paint: void 0,
		lineWidthEmu: void 0,
		lineDash: void 0,
		lineCustomDash: void 0,
		lineCap: void 0,
		lineJoin: void 0,
		lineCompound: void 0
	} : {
		paint: he,
		lineWidthEmu: R?.lineWidthEmu,
		lineDash: R?.lineDash,
		lineCustomDash: R?.lineCustomDash,
		lineCap: R?.lineCap,
		lineJoin: R?.lineJoin,
		lineCompound: R?.lineCompound
	}, B = f[0], ve;
	ve = B?.lineHidden === !0 ? qe(L) : B?.lineColor == null ? $(B?.chartexStyle, L, 0) : {
		fillType: "solid",
		color: B.lineColor
	};
	let ye = pe(B?.chartexStyle, _e), be = {
		paint: ve === void 0 ? he : ve,
		...ye,
		lineWidthEmu: B?.lineWidthEmu ?? ye.lineWidthEmu
	};
	be.lineCompound != null && be.lineCompound !== "sng" && (be.paint = null);
	let xe = Array.from({ length: N }, (e, t) => {
		let n = F.get(t);
		if (n) return n.lineHidden === !0 ? qe(L) : n.lineColor == null ? $(n.style, L, t) : {
			fillType: "solid",
			color: n.lineColor
		};
	}), V = xe.map((e, t) => {
		let n = F.get(t), r = pe(n?.style, be), i = ve === void 0 ? ge[t] ?? be.paint : ve, a = {
			paint: e === void 0 ? i : e,
			...r,
			lineWidthEmu: n?.lineWidthEmu ?? r.lineWidthEmu
		};
		return a.lineCompound != null && a.lineCompound !== "sng" && (a.paint = null), a;
	}), Se = (e, t) => e.paint !== void 0 && e.paint === t.paint && e.lineWidthEmu === t.lineWidthEmu && e.lineDash === t.lineDash && e.lineCustomDash === t.lineCustomDash && e.lineCap === t.lineCap && e.lineJoin === t.lineJoin && e.lineCompound === t.lineCompound, Ce = (e, t) => {
		let n = re(e, t), r = [];
		for (let i = 0; i < n.length - 1; i++) {
			let a = n[i], o = n[i + 1], s = e + (t - e) * ((a + o) / 2), c = Math.max(0, Math.min(N - 1, Math.floor((s - O) / D))), l = r[r.length - 1];
			l && xe[l.band] === void 0 && xe[c] === void 0 && Se(V[l.band], V[c]) ? l.to = o : r.push({
				from: a,
				to: o,
				band: c
			});
		}
		return r;
	};
	if (n.surfaceWireframe === !0) {
		let e = 0, t = (t, n) => t == null || n == null || !Number.isFinite(t) || !Number.isFinite(n) ? !0 : (e += Ce(t, n).length, e <= zo);
		for (let e = 0; e < g; e++) for (let n = 0; n < m - 1; n++) if (!t(f[e].values[n], f[e].values[n + 1])) return;
		for (let e = 0; e < m; e++) for (let n = 0; n < g - 1; n++) if (!t(f[n].values[e], f[n + 1].values[e])) return;
		for (let t = 0; t < g - 1; t++) for (let n = 0; n < m - 1; n++) {
			let r = [
				f[t].values[n],
				f[t].values[n + 1],
				f[t + 1].values[n + 1],
				f[t + 1].values[n]
			];
			if (r.some((e) => e == null || !Number.isFinite(e))) continue;
			let i = r;
			for (let t of Ro(i)) {
				let n = Math.min(...t.map((e) => i[e])), r = Math.max(...t.map((e) => i[e])), a = Math.max(1, Math.floor((n - O) / D) + 1), o = Math.min(N - 1, Math.ceil((r - O) / D) - 1);
				if (!(o < a) && (e += o - a + 1, e > zo)) return;
			}
		}
	}
	let we = xe.some((e) => e === void 0) && (ve !== void 0 || ge.length === 0), Te = [
		{
			surface: n.threeD?.floor,
			role: "floor"
		},
		{
			surface: n.threeD?.sideWall,
			role: "wall"
		},
		{
			surface: n.threeD?.backWall,
			role: "wall"
		}
	].map(({ surface: e, role: t }) => ce(n, e, t)), Ee = 0;
	for (let e of [
		...ue,
		...de,
		...n.surfaceWireframe === !0 && we ? [be.paint] : [],
		...n.surfaceWireframe === !0 ? ge : [],
		...n.surfaceWireframe === !0 ? xe.filter((e) => e !== void 0) : [],
		...Te.flatMap((e) => [e.fill, e.line])
	]) {
		if (e == null) continue;
		let t = Un(e);
		if (e.fillType === "gradient" && t > Ti || t > Ei - Ee) return;
		Ee += t;
	}
	let H = Array.from({ length: N }, (e, t) => {
		let n = O + t * D, r = Math.min(A, n + D);
		return `${ze(n)}-${ze(r)}`;
	}), De = {
		...n,
		series: H.map((e, t) => {
			let n = ue[t], r = de[t];
			return {
				name: e,
				color: n === null ? "00000000" : n?.fillType === "solid" ? n.color.replace(/^#/, "") : ie[t].replace(/^#/, ""),
				...r?.fillType === "solid" ? { lineColor: r.color.replace(/^#/, "") } : r === null ? { lineHidden: !0 } : {},
				values: []
			};
		})
	}, Oe = [...ue];
	Math.abs(y.rotationX) === 90 && (De.series.reverse(), Oe.reverse());
	let Ne = Fa(t, De, l, u, i), { legRightW: Pe, legLeftW: Ie, legTopH: Re, legBottomH: Be } = Pn(Ne, n.legendOverlay === !0), Ve = Co(t, n, l, u, i), He = je(n.catAxisFontSizeHpt, u, i), U = n.threeD?.seriesAxis, Ue = Rt(U?.fontSizeHpt, i) ?? He, { px0: Je, py0: Ye, pw: Xe, ph: Qe } = dn(n, o, s, l, u, i, {
		titleBand: Ve,
		legendReserve: Ne,
		pad: {
			t: Ve.bandH + Re + Ue / 2,
			r: Pe + Ue * 3.2 + 12,
			b: ke(He, n.catAxisLabelOffsetPercent) + Be,
			l: Ie + He * 1.5
		},
		honorPlotAreaManualLayout: !0
	}).plotRect;
	if (!(Xe > 0) || !(Qe > 0)) return;
	To(t, n, n.titleManualLayout || !n.titleRichRuns?.length ? o : Je, s, n.titleManualLayout || !n.titleRichRuns?.length ? l : Xe, u, s + Ve.topPad, Ve.fontPx), Nn(t, n, Je, Ye, Xe, Qe, i, a);
	let W = Cr(y, {
		x: Je,
		y: Ye,
		w: Xe,
		h: Qe
	}, {
		sceneDepthScale: Bo,
		perspectiveTangentGain: S
	});
	if (!W) return;
	W = Er(W, n.threeD ?? {}, {
		x: Je,
		y: Ye,
		w: Xe,
		h: Qe
	});
	let $e = x || Math.abs(y.rotationX) === 90 && y.rotationY === 0 && y.rightAngleAxes === !1 && y.perspective === 0, { front: G } = W, tt = n.catAxisOrientation === "maxMin", K = et(n), q = (e) => G.x + p(e, m, K, tt) * G.w, J = U?.orientation === "maxMin", rt = (e) => p(e, g, !1, J), it = (e) => G.y + G.h - ne(e) * G.h, at = [], ot = [], st = (e, t) => {
		let n = (n) => ({
			x: e.x + (t.x - e.x) * n,
			y: e.y + (t.y - e.y) * n,
			depth: e.depth + (t.depth - e.depth) * n,
			value: e.value + (t.value - e.value) * n
		});
		for (let r of Ce(e.value, t.value)) {
			let e = n(r.from), t = n(r.to);
			ot.push({
				points: [W.project(e.x, e.y, e.depth), W.project(t.x, t.y, t.depth)],
				band: r.band
			});
		}
	}, ct = (e) => {
		let t = Math.min(...e.map((e) => e.value)), n = Math.max(...e.map((e) => e.value)), r = Math.max(1, Math.floor((t - O) / D) + 1), i = Math.min(N - 1, Math.ceil((n - O) / D) - 1);
		for (let t = r; t <= i; t++) {
			let n = O + t * D, r = [], i = (e) => {
				r.some((t) => Math.abs(t.x - e.x) < 1e-9 && Math.abs(t.y - e.y) < 1e-9 && Math.abs(t.depth - e.depth) < 1e-9) || r.push(e);
			};
			for (let t = 0; t < e.length; t++) {
				let r = e[t], a = e[(t + 1) % e.length];
				if (r.value === n && i(r), r.value < n && a.value > n || r.value > n && a.value < n) {
					let e = (n - r.value) / (a.value - r.value);
					i({
						x: r.x + (a.x - r.x) * e,
						y: r.y + (a.y - r.y) * e,
						depth: r.depth + (a.depth - r.depth) * e,
						value: n
					});
				}
			}
			r.length === 2 && ot.push({
				points: [W.project(r[0].x, r[0].y, r[0].depth), W.project(r[1].x, r[1].y, r[1].depth)],
				band: t - 1
			});
		}
	}, lt = (e) => {
		if (n.surfaceWireframe === !0) {
			ct(e);
			return;
		}
		let t = Math.min(...e.map((e) => e.value)), r = Math.max(...e.map((e) => e.value)), i = Math.max(0, Math.floor((t - O) / D)), a = Math.min(N - 1, Math.floor((r - O) / D));
		for (let t = i; t <= a; t++) {
			let n = O + t * D, r = t === N - 1 ? A : n + D, i = Vo(e, n, !0);
			if (i = Vo(i, r, !1), i.length < 3) continue;
			let a = i.map((e) => W.project(e.x, e.y, e.depth));
			at.push({
				points: a,
				scenePoints: i,
				band: t,
				depth: i.reduce((e, t) => e + W.cameraDepth(t.x, t.y, t.depth), 0) / i.length
			});
		}
	};
	t.save(), t.beginPath(), t.rect(Je, Ye, Xe, Qe), t.clip();
	let ut = (e, n, r, i, a = !1) => {
		if (e.length < 2) return;
		let o = a ? e.map((e) => W.projectUnbounded(e.x, e.y, e.depth)) : e.map((e) => W.project(e.x, e.y, e.depth));
		t.beginPath(), t.moveTo(o[0].x, o[0].y);
		for (let e = 1; e < o.length; e++) t.lineTo(o[e].x, o[e].y);
		t.strokeStyle = n, t.lineWidth = r, t.setLineDash(sr(i ?? "solid", r)), t.stroke();
	}, pt = W.topology.farDepth, mt = W.topology.nearDepth, ht = G.y + G.h, gt = G.y, _t = W.topology.farX === "min" ? G.x : G.x + G.w, vt = [
		"floor",
		"sideWall",
		"backWall"
	].map((e) => {
		let t = n.threeD?.[e];
		return br(W, e, t?.thicknessPercent);
	}), yt = [
		"floor",
		"sideWall",
		"backWall"
	], bt = (e, t, n, r, i, a) => {
		let o = vt[e], s = yt[e];
		for (let e of Dr(o, s, t, n)) o.thickness > 0 && !W.cameraFacing(o.faces[e.faceIndex]) || ut(e.scenePoints, r, i, a, !0);
	}, xt = (e, t, n, r) => {
		for (let i of e) {
			let e = ne(i);
			bt(1, "y", e, t, n, r), bt(2, "y", e, t, n, r);
		}
	}, St = (e, t, n, r) => {
		for (let i of e) bt(0, "x", i, t, n, r), bt(2, "x", i, t, n, r);
	}, Ct = vt.some((e) => e.thickness > 0) ? vt.map((e) => e.faces.filter((t) => e.thickness === 0 || W.cameraFacing(t)).map((e) => e.map((e) => W.projectUnbounded(e.x, e.y, e.depth)))) : [
		[
			W.project(G.x, ht, mt),
			W.project(G.x + G.w, ht, mt),
			W.project(G.x + G.w, ht, pt),
			W.project(G.x, ht, pt)
		],
		[
			W.project(_t, ht, mt),
			W.project(_t, ht, pt),
			W.project(_t, gt, pt),
			W.project(_t, gt, mt)
		],
		[
			W.project(G.x, ht, pt),
			W.project(G.x + G.w, ht, pt),
			W.project(G.x + G.w, gt, pt),
			W.project(G.x, gt, pt)
		]
	].map((e) => [e]);
	for (let e = 0; e < Ct.length; e++) {
		let r = Ct[e];
		if (!r.length) continue;
		let a = r.flat(), o = Te[e], s = o.fill?.fillType === "image" ? o.fill : null;
		if (s) {
			let r = Or(vt[e], i), a = Xt(s, r ? on(s, r.widthPt, r.heightPt) : void 0), o = n.threeD?.[yt[e]];
			a && Tr(t, s, a, o, yt[e], vt[e], vt[e].faces.map((e, t) => ({
				face: e,
				faceIndex: t
			})).filter(({ face: t }) => vt[e].thickness === 0 || W.cameraFacing(t)).map(({ faceIndex: e }) => e), (e) => W.projectUnbounded(e.x, e.y, e.depth), j, (e, t) => Xt(s, on(s, e, t)), i);
		}
		let c = Math.min(...a.map((e) => e.x)), l = Math.max(...a.map((e) => e.x)), u = Math.min(...a.map((e) => e.y)), d = Math.max(...a.map((e) => e.y)), f = s ? null : o.fill?.fillType === "solid" ? `#${o.fill.color}` : o.fill ? jn(o.fill, t, c, u, l - c, d - u) : null, p = o.line?.fillType === "solid" ? `#${o.line.color}` : o.line ? jn(o.line, t, c, u, l - c, d - u) : null, m = o.lineWidthEmu == null ? 1 : nt(o.lineWidthEmu, i);
		for (let e of r) {
			t.beginPath(), t.moveTo(e[0].x, e[0].y);
			for (let n = 1; n < e.length; n++) t.lineTo(e[n].x, e[n].y);
			t.closePath(), f && (t.fillStyle = f, t.fill()), p && (t.strokeStyle = p, t.lineWidth = m, t.setLineDash(Fe(o.lineCustomDash, o.lineDash, m)), t.lineCap = o.lineCap === "rnd" ? "round" : o.lineCap === "sq" ? "square" : "butt", t.lineJoin = o.lineJoin === "round" || o.lineJoin === "bevel" ? o.lineJoin : "miter", t.stroke());
		}
	}
	if (n.valAxisMinorGridlines === !0) {
		let e = Mn(n, i);
		xt(E.minorLines.filter((e) => e >= O && e <= A), e.color, e.width, n.valAxisMinorGridlineDash);
	}
	if (e(n)) {
		let e = Ae(n.valAxisGridlineColor, n.valAxisGridlineWidthEmu, i);
		if (n.valAxisMajorGridlines === !0) xt(P, e.color, e.width, n.valAxisGridlineDash);
		else for (let t of P) {
			let r = ne(t), i = it(t);
			vt[2].thickness > 0 ? bt(2, "y", r, e.color, e.width, n.valAxisGridlineDash) : ut([{
				x: G.x,
				y: i,
				depth: pt
			}, {
				x: G.x + G.w,
				y: i,
				depth: pt
			}], e.color, e.width, n.valAxisGridlineDash), vt[1].thickness > 0 ? bt(1, "y", r, e.color, e.width, n.valAxisGridlineDash) : ut([{
				x: _t,
				y: i,
				depth: mt
			}, {
				x: _t,
				y: i,
				depth: pt
			}], e.color, e.width, n.valAxisGridlineDash);
		}
	}
	if (n.catAxisMinorGridlines === !0) {
		let e = se(n, i);
		St(Le(m, K), e.color, e.width, n.catAxisMinorGridlineDash);
	}
	if (n.catAxisMajorGridlines) {
		let e = Ae(n.catAxisGridlineColor, n.catAxisGridlineWidthEmu, i);
		St(We(n, m), e.color, e.width, n.catAxisGridlineDash);
	}
	for (let e = 0; e < g - 1; e++) for (let t = 0; t < m - 1; t++) {
		let n = [
			f[e].values[t],
			f[e].values[t + 1],
			f[e + 1].values[t + 1],
			f[e + 1].values[t]
		];
		if (n.some((e) => e == null || !Number.isFinite(e))) continue;
		let r = [
			{
				x: q(t),
				y: it(n[0]),
				depth: rt(e),
				value: n[0]
			},
			{
				x: q(t + 1),
				y: it(n[1]),
				depth: rt(e),
				value: n[1]
			},
			{
				x: q(t + 1),
				y: it(n[2]),
				depth: rt(e + 1),
				value: n[2]
			},
			{
				x: q(t),
				y: it(n[3]),
				depth: rt(e + 1),
				value: n[3]
			}
		];
		for (let e of Ro(n)) lt(e.map((e) => r[e]));
	}
	if (n.surfaceWireframe === !0) {
		for (let e = 0; e < g; e++) for (let t = 0; t < m - 1; t++) {
			let n = f[e].values[t], r = f[e].values[t + 1];
			n == null || r == null || !Number.isFinite(n) || !Number.isFinite(r) || st({
				x: q(t),
				y: it(n),
				depth: rt(e),
				value: n
			}, {
				x: q(t + 1),
				y: it(r),
				depth: rt(e),
				value: r
			});
		}
		for (let e = 0; e < m; e++) for (let t = 0; t < g - 1; t++) {
			let n = f[t].values[e], r = f[t + 1].values[e];
			n == null || r == null || !Number.isFinite(n) || !Number.isFinite(r) || st({
				x: q(e),
				y: it(n),
				depth: rt(t),
				value: n
			}, {
				x: q(e),
				y: it(r),
				depth: rt(t + 1),
				value: r
			});
		}
	}
	at.sort((e, t) => e.depth - t.depth);
	let wt = Array.from({ length: N }, () => ({
		minX: Infinity,
		minY: Infinity,
		maxX: -Infinity,
		maxY: -Infinity
	})), Tt = Array.from({ length: N }, () => []);
	for (let e of at) {
		Tt[e.band].push(e);
		let t = wt[e.band];
		for (let n of e.points) t.minX = Math.min(t.minX, n.x), t.minY = Math.min(t.minY, n.y), t.maxX = Math.max(t.maxX, n.x), t.maxY = Math.max(t.maxY, n.y);
	}
	for (let e of ot) {
		let t = wt[e.band];
		for (let n of e.points) t.minX = Math.min(t.minX, n.x), t.minY = Math.min(t.minY, n.y), t.maxX = Math.max(t.maxX, n.x), t.maxY = Math.max(t.maxY, n.y);
	}
	let Et = (e, n) => {
		if (e == null) return e;
		if (e.fillType === "solid") return `#${e.color}`;
		let r = wt[n];
		return !Number.isFinite(r.minX) || !Number.isFinite(r.minY) || !Number.isFinite(r.maxX) || !Number.isFinite(r.maxY) ? null : jn(e, t, r.minX, r.minY, r.maxX - r.minX, r.maxY - r.minY);
	}, Dt = ue.map(Et), Ot = de.map(Et);
	for (let e = 0; e < N; e++) {
		if (Dt[e] === null) continue;
		let n = Tt[e].filter((e) => e.points.length >= 3);
		n.length && or(t, F.get(e)?.style, oe, 0, i, (e) => {
			e.beginPath();
			for (let t of n) {
				e.moveTo(t.points[0].x, t.points[0].y);
				for (let n = 1; n < t.points.length; n++) e.lineTo(t.points[n].x, t.points[n].y);
				e.closePath();
			}
			e.fillStyle = "#000000", e.fill();
		}, e);
	}
	for (let e of at) {
		let n = F.get(e.band);
		t.beginPath(), t.moveTo(e.points[0].x, e.points[0].y);
		for (let n = 1; n < e.points.length; n++) t.lineTo(e.points[n].x, e.points[n].y);
		t.closePath();
		let r = Dt[e.band];
		if (r !== null) {
			let n = $e ? xr(W.cameraNormal(e.scenePoints)) : 1;
			t.fillStyle = typeof r == "string" && le[e.band]?.fromRole ? kr(r, n) : r ?? kr(ie[e.band], n), t.fill();
		}
		let a = Ot[e.band];
		if (a != null) {
			let e = n?.style, r = I, o = ae;
			t.strokeStyle = a;
			let s = n?.lineWidthEmu ?? e?.lineWidthEmu ?? r?.lineWidthEmu ?? o?.lineWidthEmu;
			t.lineWidth = s == null ? 1 : nt(s, i), t.setLineDash(Fe(e?.lineCustomDash ?? r?.lineCustomDash ?? o?.lineCustomDash, e?.lineDash ?? r?.lineDash ?? o?.lineDash, t.lineWidth));
			let c = e?.lineCap ?? r?.lineCap ?? o?.lineCap, l = e?.lineJoin ?? r?.lineJoin ?? o?.lineJoin;
			t.lineCap = c === "rnd" ? "round" : c === "sq" ? "square" : "butt", t.lineJoin = l === "round" || l === "bevel" ? l : "miter", t.stroke();
		}
	}
	if (n.surfaceWireframe === !0) {
		let e = we ? be.paint?.fillType === "solid" ? `#${be.paint.color}` : be.paint ? jn(be.paint, t, Je, Ye, Xe, Qe) : be.paint : void 0, n = xe.map((t, n) => t === void 0 ? ve !== void 0 || ge.length === 0 ? e : Et(ge[n], n) : Et(t, n));
		for (let e of ot) {
			let r = V[e.band], a = n[e.band];
			if (a === null) continue;
			t.beginPath(), t.moveTo(e.points[0].x, e.points[0].y), t.lineTo(e.points[1].x, e.points[1].y), t.strokeStyle = a ?? ie[e.band], t.lineWidth = r.lineWidthEmu == null ? Math.max(1, .75 * i) : nt(r.lineWidthEmu, i), t.setLineDash(Fe(r.lineCustomDash, r.lineDash, t.lineWidth));
			let o = r.lineCap, s = r.lineJoin;
			t.lineCap = o === "rnd" ? "round" : o === "sq" ? "square" : "butt", t.lineJoin = s === "round" || s === "bevel" ? s : "miter", t.stroke();
		}
	}
	t.restore();
	let kt = W.project(G.x + G.w / 2, G.y + G.h / 2, .5), At = (e, n, r, a, o, s, c, l, u) => {
		if (c || e == null || e === "none") return;
		let d = a.x - r.x, f = a.y - r.y, p = Math.hypot(d, f);
		if (!(p > 1e-6)) return;
		let m = -f / p, h = d / p, g = (r.x + a.x) / 2, _ = (r.y + a.y) / 2;
		(g - kt.x) * m + (_ - kt.y) * h < 0 && (m = -m, h = -h);
		let v = ir(l, s, i), y = e === "cross" ? v / 2 : v, b = e === "out" || e === "cross" ? y : 0, x = e === "in" || e === "cross" ? y : 0;
		En(t, n.x + m * b, n.y + h * b, n.x - m * x, n.y - h * x, o, s, u);
	}, jt = W.project(G.x, ht, mt), Mt = W.project(G.x + G.w, ht, mt), Nt = n.catAxisLineWidthEmu == null ? 1 : nt(n.catAxisLineWidthEmu, i);
	En(t, jt.x, jt.y, Mt.x, Mt.y, n.catAxisLineColor ? `#${n.catAxisLineColor}` : "#000000", Nt, n.catAxisLineDash);
	let Ft = n.catAxisLineColor ? `#${n.catAxisLineColor}` : "#000000", It = n.catAxisHidden || n.catAxisLineHidden === !0, Lt = Math.max(1, Math.floor(n.catAxisTickMarkSkip ?? 1));
	for (let e = 0; e < m; e += Lt) At(n.catAxisMajorTickMark, W.project(q(e), ht, mt), jt, Mt, Ft, Nt, It, "major", n.catAxisLineDash);
	if (n.catAxisMinorTickMark != null && n.catAxisMinorTickMark !== "none") for (let e = 0; e < m - 1; e++) {
		let t = (p(e, m, K, tt) + p(e + 1, m, K, tt)) / 2;
		At(n.catAxisMinorTickMark, W.project(G.x + t * G.w, ht, mt), jt, Mt, Ft, Nt, It, "minor", n.catAxisLineDash);
	}
	t.font = rr(He, Y(n, n.catAxisFontFace, "minor"), n.catAxisFontBold ?? !1, n.catAxisFontItalic ?? !1), t.fillStyle = n.catAxisFontColor ? `#${n.catAxisFontColor}` : "#000000", t.textBaseline = "top";
	for (let e = 0; e < m; e++) {
		let r = ft(e, m, et(n), Ke(n), n.catAxisLabelAlignment), i = W.project(G.x + r.fraction * G.w, ht, mt);
		t.textAlign = r.textAlign, Pt(t, d[e] ?? "", i.x, i.y + Qn(8, n.catAxisLabelOffsetPercent), nn(He));
	}
	if (!U?.hidden) {
		let e = U?.lineWidthEmu == null ? 1 : nt(U.lineWidthEmu, i), r = W.project(G.x, ht, .5), a = W.project(G.x + G.w, ht, .5), o = r.x >= a.x ? G.x : G.x + G.w, s = W.project(o, ht, mt), c = W.project(o, ht, pt);
		En(t, s.x, s.y, c.x, c.y, U?.lineColor ? `#${U.lineColor}` : "#000000", e, U?.lineDash);
		let l = U?.lineColor ? `#${U.lineColor}` : "#000000", u = Math.max(1, Math.floor(U?.tickMarkSkip ?? 1));
		for (let t = 0; t < g; t += u) At(U?.majorTickMark, W.project(o, ht, rt(t)), s, c, l, e, U?.lineHidden === !0, "major", U?.lineDash);
		if (U?.minorTickMark != null && U.minorTickMark !== "none") for (let t = 0; t < g - 1; t++) At(U.minorTickMark, W.project(o, ht, (rt(t) + rt(t + 1)) / 2), s, c, l, e, U.lineHidden === !0, "minor", U.lineDash);
		t.font = rr(Ue, Y(n, U?.fontFace, "minor"), U?.fontBold ?? !1, U?.fontItalic ?? !1), t.fillStyle = U?.fontColor ? `#${U.fontColor}` : "#000000", t.textAlign = "left", t.textBaseline = "middle";
		for (let e = 0; e < g; e++) {
			let n = W.project(o, ht, rt(e));
			Pt(t, f[e].name, n.x + 8, n.y, nn(Ue));
		}
	}
	if (!n.valAxisHidden) {
		let e = W.topology.axisX === "min" ? G.x : G.x + G.w, r = W.project(e, G.y + G.h, mt), a = W.project(e, G.y, mt);
		if (Math.hypot(a.x - r.x, a.y - r.y) > 4) {
			let o = n.valAxisLineWidthEmu == null ? 1 : nt(n.valAxisLineWidthEmu, i);
			En(t, r.x, r.y, a.x, a.y, n.valAxisLineColor ? `#${n.valAxisLineColor}` : "#000000", o, n.valAxisLineDash);
			let s = je(n.valAxisFontSizeHpt, u, i);
			t.font = rr(s, Y(n, n.valAxisFontFace, "minor"), n.valAxisFontBold ?? !1, n.valAxisFontItalic ?? !1), t.fillStyle = n.valAxisFontColor ? `#${n.valAxisFontColor}` : "#000000";
			let c = (r.x + a.x) / 2 < Je + Xe / 2;
			t.textAlign = c ? "right" : "left", t.textBaseline = "middle";
			for (let r of P) {
				let i = W.project(e, it(r), mt);
				Pt(t, h(r, n.valAxisFormatCode, n.date1904), i.x + (c ? -6 : 6), i.y, nn(s));
			}
		}
	}
	Ra(t, De, Ne, o, s, l, u, Je, Ye, Xe, Qe, Ve.bandH + 2, i, Oe);
}
function Uo(i, a, o, s, c = 0) {
	let { x: l, y: u, w: f, h: p } = o, m = Jn(a), h = m.length;
	if (h === 0) return;
	let g = Sa(a, s), _ = a.series.map((e, t) => ({
		series: e,
		chartIndex: t
	})).filter(({ series: e }) => e.seriesType == null || e.seriesType === "area"), v = a.series.map((e, t) => ({
		series: e,
		chartIndex: t
	})).filter(({ series: e }) => e.seriesType === "line");
	if (_.length === 0 && v.length === 0) return;
	let y = kn(a), b = a.chartType === "stackedAreaPct" ? "percentStacked" : a.chartType === "stackedArea" ? "stacked" : "standard", x = a.plotGroups?.filter((e) => e.kind === "area") ?? [{
		kind: "area",
		seriesStart: 0,
		seriesCount: _.length,
		categoryAxis: "primary",
		valueAxis: "primary",
		seriesAxis: "none",
		grouping: b
	}], S = Array(a.series.length).fill(-1);
	for (let e = 0; e < _.length; e++) S[_[e].chartIndex] = e;
	let C = x.map((e) => {
		let t = [], n = Math.min(a.series.length, e.seriesStart + e.seriesCount);
		for (let r = e.seriesStart; r < n; r++) {
			let e = S[r];
			e >= 0 && t.push(e);
		}
		return {
			group: e,
			areaIndices: t
		};
	}), T = Array(_.length).fill(!1), E = Array(_.length).fill(!1), D = Array(_.length).fill(null), k = _.map(() => Array(h).fill(0)), j = _.map(() => Array(h).fill(0)), te = a.plotGroups?.filter((e) => (e.kind === "area" || e.kind === "line") && e.seriesCount > 0) ?? x, M = /* @__PURE__ */ new Map();
	for (let e of te) {
		let t = e.valueAxis;
		M.set(t, (M.get(t) ?? !0) && e.grouping === "percentStacked");
	}
	for (let { group: e, areaIndices: t } of C) {
		let n = e.grouping ?? "standard", r = n === "stacked" || n === "percentStacked", i = n === "percentStacked", a = i && M.get(e.valueAxis) === !0 ? 100 : 1, o = i ? m.map((e, n) => t.reduce((e, t) => e + Math.abs(_[t].series.values[n] ?? 0), 0) || 1) : null;
		for (let e of t) T[e] = r, E[e] = i, D[e] = o;
		for (let e = 0; e < h; e++) {
			let n = 0, s = 0;
			for (let c of t) {
				let t = _[c].series.values[e] ?? 0, l = i && o ? t / o[e] * a : t, u = l >= 0 ? n : s;
				k[c][e] = r ? u : 0, j[c][e] = r ? u + l : l, r && (l >= 0 ? n += l : s += l);
			}
		}
	}
	let N = te.filter((e) => e.valueAxis !== "secondary"), P = N.length > 0 && N.every((e) => e.grouping === "percentStacked"), re = new Map(_.map((e, t) => [e.series, t])), ae = new Map(a.series.map((e, t) => [e, t])), oe = (e) => y[ae.get(e) ?? -1]?.valueAxis === "secondary" || a.plotGroups == null && e.useSecondaryAxis === !0, se = te.filter((e) => e.valueAxis === "secondary"), ce = se.length > 0 && se.every((e) => e.grouping === "percentStacked"), L = a.secondaryValAxis && a.series.some((e) => oe(e)) ? a.secondaryValAxis : null, R = (e) => L != null && oe(e), z = Co(i, a, f, p, s), le = z.fontPx, ue = z.topPad, pe = z.bandH, he = je(a.catAxisFontSizeHpt, p, s), ge = je(a.valAxisFontSizeHpt, p, s), B = Fa(i, a, f, p, s), { legRightW: ve, legLeftW: ye, legTopH: be, legBottomH: xe } = Pn(B, a.legendOverlay === !0), V = wt(a, f, p, s), Se = V.catFontPx, Ce = V.valFontPx, Te = V.catBandH, Ee = V.valBandW, H = Ba(a), De = H && Va(a), Ae = Ha(a, he), Me = Ka(a, s), Ne = qa(i, a, s), Fe = pe + be + ge / 2 + 2, Le = (H ? Me + Ae : ke(he, a.catAxisLabelOffsetPercent)) + Te + xe, ze = p - Fe - Le, Ve = Ht(L, a.series, ze / s, "y", ce, !1, (e) => oe(e), (e, t) => {
		let n = re.get(e);
		return n == null ? e.values[t] ?? null : e.values[t] == null ? null : j[n][t] ?? null;
	}), He = Math.max(8, Math.min(11, p / 20)), U = Rt(L?.fontSizeHpt, s) ?? He, Ue = 0;
	if (L && Ve && !L.hidden) {
		let e = i.font;
		i.font = `${U}px ${Y(a, L.fontFace, "minor")}`;
		let t = 0;
		for (let e of Ve.majorLines) t = Math.max(t, ot(i, Dt(e, L.formatCode ?? null, a.date1904, L.displayUnits)));
		Ue = t + 18, i.font = e;
	}
	let Ge = L && L.title ? ne(L, s) : 0, qe = (() => {
		let e = Infinity, t = -Infinity;
		for (let n = 0; n < h; n++) {
			for (let r = 0; r < _.length; r++) {
				let { series: i } = _[r];
				R(i) || i.values[n] == null || (e = Math.min(e, k[r][n], j[r][n]), t = Math.max(t, k[r][n], j[r][n]));
			}
			for (let { series: r } of v) {
				if (R(r)) continue;
				let i = r.values[n];
				i != null && (e = Math.min(e, i), t = Math.max(t, i));
			}
		}
		return !isFinite(e) || !isFinite(t) ? {
			min: 0,
			max: 1
		} : P ? {
			min: e < 0 ? -100 : 0,
			max: t > 0 ? 100 : 0
		} : {
			min: e,
			max: t
		};
	})();
	if (!P) {
		let e = (e) => {
			qe = {
				min: Math.min(qe.min, e),
				max: Math.max(qe.max, e)
			};
		};
		for (let t = 0; t < _.length; t++) {
			let { series: n } = _[t];
			R(n) || w(n, "y", (e) => n.values[e] == null ? null : j[t][e], e);
		}
		for (let { series: t } of v) R(t) || w(t, "y", (e) => t.values[e] ?? null, e);
	}
	let Je = ln(a, qe.min, qe.max, ze / s, P), Ye = a.valAxisFontSizeHpt == null ? Math.max(8, Math.min(11, ze / 20)) : ge, Xe = 0;
	if (!a.valAxisHidden && a.plotAreaManualLayout != null && a.plotAreaManualLayout.layoutTarget !== "inner") {
		let e = i.font;
		i.font = rr(Ye, Y(a, a.valAxisFontFace, "minor"), a.valAxisFontBold ?? !1, a.valAxisFontItalic ?? !1);
		for (let e of Je.majorLines) Xe = Math.max(Xe, ot(i, In(a, e, P)));
		i.font = e;
	}
	let Ze = Math.max(a.valAxisHidden ? 0 : yt(Je.majorLines.map((e) => In(a, e, P)), nn(Ye)), L && Ve && !L.hidden && L.tickLabelPos !== "none" ? yt(Ve.majorLines.map((e) => Dt(e, L.formatCode ?? null, a.date1904, L.displayUnits)), nn(U)) : 0) / 2, Qe = Cn({
		valAxisHidden: a.valAxisHidden,
		catAxisHidden: a.catAxisHidden,
		valLabelWidth: Xe,
		valLabelFontPx: Ye,
		catLabelFontPx: he,
		valLabelGapPx: a.valAxisFontSizeHpt == null ? 6 : Ln(Ye),
		catLabelGapPx: a.catAxisFontSizeHpt == null ? Qn(3, a.catAxisLabelOffsetPercent) : Qn(n(he), a.catAxisLabelOffsetPercent),
		outerTextMarginPx: A * s,
		valTitleBandW: Ee,
		catTitleBandH: Te,
		secondaryBandW: Ue + Ge,
		valLabelExtraTopPx: Ze
	}), $e = {
		t: Fe + Ze,
		r: ve + f * .05 + Ue + Ge,
		b: Le,
		l: ye + Math.max(f * .12 + Ee, Ne)
	};
	To(i, a, l, u, f, p, u + ue, le);
	let G = dn(a, l, u, f, p, s, {
		titleBand: z,
		legendReserve: B,
		pad: $e,
		honorPlotAreaManualLayout: !0,
		manualOuterInsets: Qe
	}), { px0: K, py0: q, pw: J } = G.plotRect, { ph: rt } = G.plotRect;
	if (J <= 0 || rt <= 0) return;
	let at = Ua(a, J, Ue + Ge), ct = H ? Ja(i, a, at / h, s) : null;
	ct && ct.totalHeight > Me && (rt = Math.max(1, rt - (ct.totalHeight - Me)));
	let lt = r(a, m), ut = Math.max(1, Math.floor(a.catAxisTickLabelSkip ?? 1)), dt = () => lt ? lt.majorTicks.map((e) => er(String(e.serial), a.catAxisFormatCode, a.date1904)) : Array.from({ length: Math.ceil(h / ut) }, (e, t) => er((m[t * ut] ?? "").toString(), a.catAxisFormatCode, a.date1904));
	if (!De && !a.catAxisHidden && a.catAxisTickLabelPos !== "high") {
		let e = a.catAxisFontSizeHpt == null ? Math.max(8, Math.min(11, J / h * .8)) : he, t = a.plotAreaManualLayout?.layoutTarget === "inner" && a.plotAreaManualLayout.w != null && a.plotAreaManualLayout.h != null, n = yt(dt(), nn(e));
		H && (Ae += n), !t && n > 0 && (rt = Math.max(1, rt - n));
	}
	Nn(i, a, K, q, J, rt, s, c);
	let pt = ln(a, qe.min, qe.max, rt / s, P), mt = et(a), ht = Ke(a), gt = lt ? (e) => K + lt.positions[e] * J : mt ? (e) => K + ((ht ? h - 1 - e : e) + .5) / h * J : (e) => {
		let t = ht ? h - 1 - e : e;
		return K + (h === 1 ? J / 2 : t / (h - 1) * J);
	}, _t = (e) => q + rt - pt.frac(e) * rt, vt = Ve ? Ve.makeToY(q, rt) : _t, bt = (e) => R(e) ? vt : _t, xt = _t(ko(a, pt.min, pt.max)), St = L && Ve ? vt(Oo(a.secondaryCatAxis?.crossesAt, a.secondaryCatAxis?.crosses, Ve.min, Ve.max)) : xt, Ct = (e) => R(e) ? St : xt, { color: Tt, width: Et } = it(a.catAxisLineColor, a.catAxisLineWidthEmu, s), { color: Ot, width: kt } = it(a.valAxisLineColor, a.valAxisLineWidthEmu, s);
	if (!a.valAxisHidden) {
		let t = mn(a, s), n = Mn(a, s);
		if (a.valAxisMinorGridlines) for (let e of pt.minorLines) Vt(i, K, J, _t(e), !1, n);
		if (e(a)) for (let e of pt.majorLines) Vt(i, K, J, _t(e), e === 0, t);
	}
	if (L && Ve && we(i, L, Ve, vt, K, J, s), !a.catAxisHidden && W(a)) {
		let e = ee(a, s);
		i.strokeStyle = e.color, i.lineWidth = e.width;
		let t = e.dash.length > 0 && i.getLineDash ? i.getLineDash() : [];
		e.dash.length > 0 && i.setLineDash(e.dash);
		let n = lt ? lt.majorTicks.map((e) => e.fraction) : We(a, h);
		for (let e of n) {
			let t = K + e * J;
			i.beginPath(), i.moveTo(t, q), i.lineTo(t, q + rt), i.stroke();
		}
		e.dash.length > 0 && i.setLineDash(t);
	}
	let jt = C.flatMap(({ areaIndices: e }) => e), Nt = (e, t) => j[e]?.[t] ?? 0;
	for (let e of jt) {
		let { series: t, chartIndex: n } = _[e], r = fe(n, t), o = Ie(t, n), l = me(a, t, void 0, o), u = q + rt, d = bt(t), f = t.smooth === !0;
		Pe(i, de(t.chartexStyle), I(a, "dataPoint", Oe(a, t)), o, {
			x: K,
			y: q,
			w: J,
			h: rt
		}, s, (n) => {
			if (n.beginPath(), T[e]) {
				let t = [];
				for (let n = 0; n < h; n++) t.push({
					x: gt(n),
					y: _t(j[e][n])
				});
				n.moveTo(t[0].x, t[0].y), Be(n, t, f);
				for (let t = h - 1; t >= 0; t--) n.lineTo(gt(t), _t(k[e][t]));
			} else {
				let e = [];
				for (let n = 0; n < h; n++) e.push({
					x: gt(n),
					y: d(t.values[n] ?? 0)
				});
				n.moveTo(gt(0), u), n.lineTo(e[0].x, e[0].y), Be(n, e, f), n.lineTo(gt(h - 1), u);
			}
			n.closePath(), zi(n, l, {
				x: K,
				y: q,
				w: J,
				h: rt
			}, r, s, c), ie(n, a, "dataPoint", t, void 0, o, r, 1.5, s, {
				x: K,
				y: q,
				w: J,
				h: rt
			}, c) && n.stroke();
		});
	}
	let It = /* @__PURE__ */ new Map();
	for (let e = 0; e < _.length; e++) {
		let t = _[e].series, n = t.areaGroupIndex ?? 0, r = It.get(n) ?? [];
		r.push({
			series: t,
			areaIndex: e
		}), It.set(n, r);
	}
	for (let e of a.areaGroupDecorations ?? []) {
		if (!e.dropLines || !en(i, an(a, e.dropLines, "dropLine"), s)) continue;
		let t = It.get(e.groupIndex) ?? [];
		for (let e = 0; e < h; e++) {
			let n = Infinity, r = -Infinity, a = !1;
			for (let i of t) {
				if (i.series.values[e] == null) continue;
				let t = bt(i.series)(Nt(i.areaIndex, e)), o = Ct(i.series);
				!Number.isFinite(t) || !Number.isFinite(o) || (n = Math.min(n, t, o), r = Math.max(r, t, o), a = !0);
			}
			!a || Math.abs(r - n) < .01 || (i.beginPath(), i.moveTo(gt(e), n), i.lineTo(gt(e), r), i.stroke());
		}
	}
	{
		let e = Math.max(2, 2.5 * s);
		for (let t = 0; t < _.length; t++) {
			let { series: n, chartIndex: r } = _[t], o = cr(n.dataPointOverrides), d = fe(r, n), v = bt(n), y = (e) => Nt(t, e), b = D[t];
			for (let e of n.errBars ?? []) $a(i, n, At(a, e), h, gt, v, y, d);
			let x = (n.showMarker === !0 || Re(n)) && n.markerSymbol !== "none", S = ia(a, n, r);
			if (x || Ft(n)) for (let t = 0; t < h; t++) {
				if (n.sourceHidden?.[t] === !0 || n.values[t] == null) continue;
				let r = o.get(t), l = rn(n, r, "circle", x);
				if (l === "none") continue;
				let u = gt(t), f = v(y(t));
				if (S || $n(r)) {
					let e = r?.markerSize ?? n.markerSize ?? 5, o = Mt(n, r, t, d), p = r?.markerLine ?? n.markerLine ?? null, m = r?.markerLineWidthEmu ?? n.markerLineWidthEmu;
					ra(i, a, n, r, t, u, f, l, e, o, p, s, m == null ? void 0 : nt(m, s), st(n, r, t), c);
				} else i.fillStyle = d, i.beginPath(), i.arc(u, f, e, 0, Math.PI * 2), i.fill();
			}
			so(i, n, m, h, gt, v, y, rt, s, a.date1904 ?? !1, !0, Y(a, a.dataLabelFontFace, "minor"), a.dataLabelPosition ?? "ctr", {
				x: K,
				y: q,
				w: J,
				h: rt
			}, {
				x: l,
				y: u,
				w: f,
				h: p
			}, E[t] && b ? (e) => (n.values[e] ?? 0) / b[e] : void 0, void 0, (e) => Y(a, e, "minor"), R(n) ? L?.displayUnits : a.valAxisDisplayUnits, (e) => g(r, e), (e) => eo(a, e, R(n) && Ve ? Ve.max : pt.max), c);
		}
	}
	for (let { series: e, chartIndex: n } of v) {
		let r = cr(e.dataPointOverrides), _ = fe(n, e), v = e.lineColor ? `#${e.lineColor}` : _, y = Ie(e, n), b = bt(e), x = t(a, n), S = [], C = [], w = () => {
			C.length > 0 && S.push(C), C = [];
		};
		for (let t = 0; t < h; t++) {
			let n = e.values[t];
			if (e.sourceHidden?.[t] === !0) {
				w();
				continue;
			}
			n == null && ((a.dispBlanksAs ?? "gap") === "gap" && w(), (a.dispBlanksAs ?? "gap") !== "zero") || C.push({
				x: gt(t),
				y: b(n ?? 0),
				index: t
			});
		}
		w(), x ? d(i, a, e, S, e.smooth === !0, !1, _, Math.max(1, 2.25 * s), s, {
			x: K,
			y: q,
			w: J,
			h: rt
		}, c) : Pe(i, de(e.chartexStyle), a.chartStyleRoles?.dataPointLine, y, {
			x: K,
			y: q,
			w: J,
			h: rt
		}, s, (t) => {
			if (ie(t, a, "dataPointLine", e, void 0, y, _, Math.max(1, 2.25 * s), s, {
				x: K,
				y: q,
				w: J,
				h: rt
			}, c)) {
				t.beginPath();
				for (let n of S) t.moveTo(n[0].x, n[0].y), Be(t, n, e.smooth === !0);
				t.stroke();
			}
		});
		let T = (t) => e.values[t] ?? 0;
		for (let t of e.errBars ?? []) $a(i, e, At(a, t), h, gt, b, T, v);
		let E = (e.showMarker === !0 || Re(e)) && e.markerSymbol !== "none";
		if (E || Ft(e)) for (let t = 0; t < h; t++) {
			if (e.sourceHidden?.[t] === !0) continue;
			let n = e.values[t];
			if (n == null) continue;
			let o = r.get(t), l = rn(e, o, "circle", E);
			l !== "none" && ra(i, a, e, o, t, gt(t), b(n), l, o?.markerSize ?? e.markerSize ?? 5, Mt(e, o, t, v), o?.markerLine ?? e.markerLine ?? null, s, (o?.markerLineWidthEmu ?? e.markerLineWidthEmu) == null ? void 0 : nt(o?.markerLineWidthEmu ?? e.markerLineWidthEmu, s), st(e, o, t), c);
		}
		so(i, e, m, h, gt, b, T, rt, s, a.date1904 ?? !1, !1, Y(a, a.dataLabelFontFace, "minor"), a.dataLabelPosition ?? "r", {
			x: K,
			y: q,
			w: J,
			h: rt
		}, {
			x: l,
			y: u,
			w: f,
			h: p
		}, void 0, void 0, (e) => Y(a, e, "minor"), R(e) ? L?.displayUnits : a.valAxisDisplayUnits, (e) => g(n, e), (t) => eo(a, t, R(e) && Ve ? Ve.max : pt.max), c), ua(i, e, v, gt, b, s, void 0, {
			chart: a,
			chartRect: o,
			plotRect: {
				x: K,
				y: q,
				w: J,
				h: rt
			},
			shapeRotationDeg: c
		});
	}
	if (!a.valAxisHidden) {
		let e = a.valAxisFontSizeHpt == null ? Math.max(8, Math.min(11, rt / 20)) : ge;
		i.font = rr(e, Y(a, a.valAxisFontFace, "minor"), a.valAxisFontBold ?? !1, a.valAxisFontItalic ?? !1), i.textBaseline = "middle";
		for (let t of pt.majorLines) {
			let n = _t(t);
			F(i, a.valAxisMajorTickMark, "val", K, n, Ot, kt, !1, a.valAxisLineHidden, "major", s, a.valAxisLineDash), i.fillStyle = a.valAxisFontColor ? `#${a.valAxisFontColor}` : "#555", i.textAlign = "right";
			let r = a.valAxisFontSizeHpt == null ? 6 : Ln(e);
			Pt(i, In(a, t, P), K - r, n, nn(e));
		}
		if (a.valAxisMinorTickMark && a.valAxisMinorTickMark !== "none") for (let e of pt.minorTicks) F(i, a.valAxisMinorTickMark, "val", K, _t(e), Ot, kt, !1, a.valAxisLineHidden, "minor", s, a.valAxisLineDash);
	}
	if (!a.catAxisHidden && !a.catAxisLineHidden && En(i, K, xt, K + J, xt, Tt, Et, a.catAxisLineDash), !a.valAxisHidden && !a.valAxisLineHidden && a.valAxisLineColor != null && En(i, K, q, K, q + rt, Ot, kt, a.valAxisLineDash), !a.catAxisHidden && a.catAxisMajorTickMark && a.catAxisMajorTickMark !== "none") {
		let e = Math.max(1, Math.floor(a.catAxisTickMarkSkip ?? 1));
		if (lt) for (let e of lt.majorTicks) F(i, a.catAxisMajorTickMark, "cat", xt, K + e.fraction * J, Tt, Et, !1, a.catAxisLineHidden, "major", s, a.catAxisLineDash);
		else if (mt) for (let t = 0; t <= h; t += e) F(i, a.catAxisMajorTickMark, "cat", xt, K + t / h * J, Tt, Et, !1, a.catAxisLineHidden, "major", s, a.catAxisLineDash);
		else for (let t = 0; t < h; t += e) F(i, a.catAxisMajorTickMark, "cat", xt, gt(t), Tt, Et, !1, a.catAxisLineHidden, "major", s, a.catAxisLineDash);
	}
	if (!a.catAxisHidden && a.catAxisMinorTickMark && a.catAxisMinorTickMark !== "none" && lt) for (let e of lt.minorTicks) F(i, a.catAxisMinorTickMark, "cat", xt, K + e.fraction * J, Tt, Et, !1, a.catAxisLineHidden, "minor", s, a.catAxisLineDash);
	if (!De && !a.catAxisHidden && O(a)) {
		let e = a.catAxisFontSizeHpt == null ? Math.max(8, Math.min(11, J / h * .8)) : he;
		i.fillStyle = a.catAxisFontColor ? `#${a.catAxisFontColor}` : "#555", i.textAlign = "center", i.textBaseline = "top", i.font = rr(e, Y(a, a.catAxisFontFace, "minor"), a.catAxisFontBold ?? !1, a.catAxisFontItalic ?? !1);
		let t = Math.max(1, Math.floor(a.catAxisTickLabelSkip ?? 1)), r = lt ? lt.majorTicks.map((e) => ({
			label: er(String(e.serial), a.catAxisFormatCode, a.date1904),
			x: K + e.fraction * J,
			categoryIndex: -1
		})) : Array.from({ length: Math.ceil(h / t) }, (e, n) => {
			let r = n * t;
			return {
				label: er((m[r] ?? "").toString(), a.catAxisFormatCode, a.date1904),
				x: gt(r),
				categoryIndex: r
			};
		});
		for (let t of r) {
			let r = t.label;
			if (!r) continue;
			let o = t.categoryIndex < 0 ? null : ft(t.categoryIndex, h, et(a), Ke(a), a.catAxisLabelAlignment), s = Qn(a.catAxisFontSizeHpt == null ? 3 : n(e), a.catAxisLabelOffsetPercent);
			i.textAlign = o?.textAlign ?? "center";
			let c = a.catAxisTickLabelPos ?? "nextTo", l = c === "nextTo" ? xt : c === "high" ? q : q + rt;
			Pt(i, r, o ? K + o.fraction * J : t.x, l + s, nn(e));
		}
	}
	if (L && Ve) {
		let e = a.valAxisFontColor ? `#${a.valAxisFontColor}` : "#555";
		_e(i, a, L, Ve, vt, o, K, q, J, rt, s, U, Ue, e, a.date1904);
	}
	ct && Ya(i, a, ct, K, q + rt + Ae, at, l + ye, s), Ra(i, a, B, l, u, f, p, K, q, J, rt, pe + 2, s), tt(i, a, l, u, f, p, K, q, J, rt, ye, xe, Se, Ce);
}
var Wo = .88;
function Go(e, t, n) {
	return e === "ctr" || t > .01 ? (t + n) / 2 : n * Wo;
}
function Ko(e, t, n, r, i = 0) {
	let a = t.series[0];
	if (!a) return;
	let o = lt(t.ofPie, a.values);
	if (o == null || o.size === 0) {
		qo(e, {
			...t,
			chartType: "pie"
		}, n, !1, r, i);
		return;
	}
	let s = [], c = [];
	for (let e = 0; e < a.values.length; e++) {
		let t = a.values[e], n = t == null ? 0 : Math.abs(t);
		!(n > 0) || !Number.isFinite(n) || (o.has(e) ? c : s).push({
			sourceIndex: e,
			value: n
		});
	}
	if (c.length === 0) {
		qo(e, {
			...t,
			chartType: "pie"
		}, n, !1, r, i);
		return;
	}
	let l = {
		...t,
		chartType: "pie"
	}, u = Fa(e, l, n.w, n.h, r), d = dn(t, n.x, n.y, n.w, n.h, r, {
		titleTopPadFrac: .035,
		titleBottomPadFrac: .035,
		legendReserve: u,
		radialGapFrac: .02,
		honorPlotAreaManualLayout: !0
	});
	To(e, t, n.x, n.y, n.w, n.h, n.y + d.title.topPad, d.title.fontPx);
	let { px0: f, py0: p, pw: m, ph: h } = d.plotRect;
	if (!(m > 0) || !(h > 0)) return;
	Nn(e, t, f, p, m, h, r, i);
	let g = t.ofPie, _ = Math.max(.05, Math.min(2, (g?.secondPieSizePercent ?? 75) / 100)), v = Math.max(0, g?.gapWidthPercent ?? 150) / 100, y = Math.min(h * .44, m * .9 / (2 + 2 * _ + v)), b = y * _;
	if (!(y > 0) || !(b > 0)) return;
	let x = f + (m - (2 * y + v * y + 2 * b)) / 2, S = x + y, C = x + 2 * y + v * y + b, w = p + h / 2, T = c.reduce((e, t) => e + t.value, 0), E = cr(a.dataPointOverrides), D = [...s, {
		sourceIndex: c[0].sourceIndex,
		value: T
	}], O = (n, o, s) => {
		let c = n.reduce((e, t) => e + t.value, 0), l = -Math.PI / 2, u = l, d = l;
		for (let f = 0; f < n.length; f++) {
			let p = n[f], m = c > 0 ? p.value / c * Math.PI * 2 : 0, h = E.get(p.sourceIndex), g = Ye(t, a, 0, p.sourceIndex), _ = N(p.sourceIndex, a, t.varyColors !== !1, 0), v = me(t, a, h, g, p.sourceIndex), y = l, b = l + m;
			Pe(e, de(h?.chartexStyle, a.chartexStyle), I(t, "dataPoint", 0), g, {
				x: o - s,
				y: w - s,
				w: s * 2,
				h: s * 2
			}, r, (e) => {
				e.beginPath(), e.moveTo(o, w), e.arc(o, w, s, y, b), e.closePath(), zi(e, v, {
					x: o - s,
					y: w - s,
					w: s * 2,
					h: s * 2
				}, _, r, i), es(e, t, a, h, g, "#FFFFFF", r, {
					x: o - s,
					y: w - s,
					w: s * 2,
					h: s * 2
				}, i);
			}), f === n.length - 1 && (u = l, d = l + m), l += m;
		}
		return {
			aggregateStart: u,
			aggregateEnd: d
		};
	}, k = O(D, S, y), A = w - b, j = w + b;
	if ((g?.type ?? "pie") === "bar") {
		let n = w - b, o = b;
		for (let s of c) {
			let c = T > 0 ? 2 * b * s.value / T : 0, l = C - o / 2, u = E.get(s.sourceIndex), d = Ye(t, a, 0, s.sourceIndex), f = N(s.sourceIndex, a, t.varyColors !== !1, 0), p = me(t, a, u, d, s.sourceIndex);
			Pe(e, de(u?.chartexStyle, a.chartexStyle), I(t, "dataPoint", 0), d, {
				x: l,
				y: n,
				w: o,
				h: c
			}, r, (e) => {
				Bi(e, p, {
					x: l,
					y: n,
					w: o,
					h: c
				}, f, r, i), e.beginPath(), e.rect(l, n, o, c), es(e, t, a, u, d, "#FFFFFF", r, {
					x: l,
					y: n,
					w: o,
					h: c
				}, i);
			}), n += c;
		}
		A = w - b, j = w + b;
	} else O(c, C, b);
	if (g?.seriesLines ?? !0) {
		let n = an(t, g?.seriesLineStyle ?? {}, "seriesLine"), i = en(e, {
			...n,
			color: n.color ?? "808080"
		}, r), a = {
			x: S + Math.cos(k.aggregateStart) * y,
			y: w + Math.sin(k.aggregateStart) * y
		}, o = {
			x: S + Math.cos(k.aggregateEnd) * y,
			y: w + Math.sin(k.aggregateEnd) * y
		};
		i && (e.beginPath(), e.moveTo(a.x, a.y), e.lineTo(C - b, A), e.stroke(), e.beginPath(), e.moveTo(o.x, o.y), e.lineTo(C - b, j), e.stroke());
	}
	u && Ra(e, l, u, n.x, n.y, n.w, n.h, f, p, m, h, d.title.bandH + 2, r);
}
function qo(e, t, n, r, i, a = 0) {
	let { x: o, y: s, w: c, h: l } = n, u = t.series[0];
	if (!u) return;
	let d = u.categories && u.categories.length > 0 ? u.categories : t.categories, f = u.values.map((e) => Math.abs(e ?? 0)), p = f.reduce((e, t) => e + t, 0);
	if (p === 0) return;
	let m = r && t.varyColors === !1 && t.series.length > 1 ? t : {
		...t,
		series: [{
			...u,
			categories: d
		}]
	}, h = Fa(e, m, c, l, i), g = dn(t, o, s, c, l, i, {
		titleTopPadFrac: .035,
		titleBottomPadFrac: .035,
		legendReserve: h,
		radialGapFrac: .02,
		honorPlotAreaManualLayout: !0
	}), _ = g.title.fontPx, v = g.title.bandH;
	To(e, t, o, s, c, l, s + g.title.topPad, _);
	let { px0: y, py0: b, pw: x, ph: S } = g.plotRect;
	if (!(x > 0) || !(S > 0)) return;
	Nn(e, t, y, b, x, S, i, a);
	let C = g.center.cx, w = g.center.cy, T = Math.min(x, S) * .42, E = -Math.PI / 2 + (t.firstSliceAngle ?? 0) * Math.PI / 180, D = r ? Math.max(1, Math.min(90, t.holeSize ?? 50)) : 0, O = r ? t.series : [u], k = new Map(O.map((e) => [e, cr(e.dataPointOverrides)])), A = (T - D / 100 * T) / O.length, j = (e, t) => {
		let n = k.get(e)?.get(t)?.explosion ?? e.explosion ?? 0;
		return n > 0 ? n / 100 * T : 0;
	}, ee = u.seriesDataLabels ?? {
		showVal: !1,
		showCatName: !1,
		showSerName: !1,
		showPercent: !1
	}, te = u.seriesDataLabels != null || (u.dataLabelOverrides?.length ?? 0) > 0, M = t.showDataLabels && !te, ne = Y(t, ee.fontFace ?? t.dataLabelFontFace, "minor");
	for (let n = 0; n < O.length; n++) {
		let o = O[n], s = o.values.map((e) => Math.abs(e ?? 0)), c = s.reduce((e, t) => e + t, 0);
		if (c === 0) continue;
		let l = T - n * A, u = l - A, d = E;
		for (let f = 0; f < s.length; f++) {
			let p = s[f] / c * Math.PI * 2;
			if (!(p > 0)) continue;
			let m = Ye(t, o, n, f), h = N(f, o, t.varyColors !== !1, n), g = k.get(o)?.get(f), _ = d + p / 2, v = j(o, f), y = v > 0 ? Math.cos(_) * v : 0, b = v > 0 ? Math.sin(_) * v : 0, x = me(t, o, g, m, f), S = {
				x: C + y - l,
				y: w + b - l,
				w: l * 2,
				h: l * 2
			};
			if (Pe(e, de(g?.chartexStyle, o.chartexStyle), I(t, "dataPoint", Oe(t, o)), m, S, i, (e) => {
				e.beginPath(), u > .01 ? (e.arc(C + y, w + b, l, d, d + p), e.arc(C + y, w + b, u, d + p, d, !0)) : (e.moveTo(C + y, w + b), e.arc(C + y, w + b, l, d, d + p)), e.closePath(), zi(e, x, S, h, i, a), es(e, t, o, g, m, h, i, S, a);
			}), M && n === 0 && p > .15) {
				let t = T * (r ? .75 : .6), n = C + y + Math.cos(_) * t, i = w + b + Math.sin(_) * t, a = Math.round(s[f] / c * 100);
				e.font = `bold ${Math.max(8, T * .1)}px ${ne}`, e.fillStyle = "#fff", e.textAlign = "center", e.textBaseline = "middle", e.fillText(`${a}%`, n, i);
			}
			d += p;
		}
	}
	te && Jo(e, t, ee, u, d, f, p, C, w, T, r ? T - A : 0, E, ne, i, y, b, x, S, o, s, c, l, f.map((e, t) => j(u, t)), a), h && Ra(e, m, h, o, s, c, l, y, b, x, S, v + 2, i);
}
function Jo(e, t, n, r, i, a, o, s, c, l, u, d, f, p, m, h, g, _, v, y, b, x, S, C) {
	let w = cr(r.dataLabelOverrides ?? []), T = Sa({
		...t,
		series: [{
			...r,
			categories: i
		}]
	}, p), E = /* @__PURE__ */ new Set();
	for (let e = 0; e < a.length; e++) {
		if (r.sourceHidden?.[e] === !0) continue;
		let t = w.get(e);
		if (ue(n, t)) continue;
		let i = Wn(t?.labelBox, n.labelBox);
		i?.fillHidden !== !0 && (i?.fill != null || i?.fillPaint != null) && E.add(e);
	}
	E.size > 0 && $o(e, t, n, r, i, a, o, s, c, l, u, d, f, p, m, g, h, _, v, y, b, x, E, w, S, C);
	let D = [], O = d;
	for (let d = 0; d < a.length; d++) {
		let k = a[d] / o * Math.PI * 2, A = O + k / 2;
		if (O += k, !(k > 0) || r.sourceHidden?.[d] === !0 || E.has(d)) continue;
		let j = w.get(d);
		if (ue(n, j)) continue;
		let ee = j?.showCatName ?? n.showCatName, te = j?.showSerName ?? n.showSerName, M = j?.showVal ?? n.showVal, N = j?.showPercent ?? n.showPercent, ne = j?.showLegendKey ?? n.showLegendKey ?? !1, P = mt({
			customText: j?.text,
			showCategory: ee,
			showSeries: te,
			showValue: M,
			showPercent: N,
			category: (i[d] ?? "").toString(),
			seriesName: r.name,
			sourceValue: a[d],
			percentRatio: a[d] / o,
			formatCode: j?.formatCode ?? n.formatCode ?? r.valFormatCode ?? null,
			percentFormatCode: j?.formatCode ?? n.formatCode ?? "0%",
			date1904: t.date1904 ?? !1,
			separator: j?.separator ?? n.separator
		}), re = ne ? T(0, d) : void 0;
		if (!P && !re) continue;
		let ie = j?.position ?? n.position ?? "bestFit", F = ie === "outEnd", I = Rt(j?.fontSizeHpt ?? n.fontSizeHpt, p) ?? Math.max(8, l * .1), ae = j?.fontBold ?? n.fontBold, oe = j?.fontColor ?? n.fontColor, se = j?.fontFace ?? n.fontFace ? Y(t, j?.fontFace ?? n.fontFace, "minor") : f, ce = Ne(j, n), L = ro(t, j, p, se, ae ?? !1, ce), R = Go(ie, u, l) + (ie === "ctr" ? S[d] ?? 0 : 0);
		if (j?.manualLayout) {
			e.font = `${ce.fontItalic ? "italic " : ""}${ae ? "bold " : ""}${I}px ${se}`, ao(e, P, {
				kind: "point",
				x: s + Math.cos(A) * R,
				y: c + Math.sin(A) * R,
				position: "ctr"
			}, {
				x: m,
				y: h,
				w: g,
				h: _
			}, I, oe ? `#${oe}` : "#fff", j.manualLayout, {
				x: v,
				y,
				w: b,
				h: x
			}, L, re, ce, p, Wn(j?.labelBox, n.labelBox), C);
			continue;
		}
		if (F) {
			e.font = `${ce.fontItalic ? "italic " : ""}${ae ? "bold " : ""}${I}px ${se}`;
			let t = L ? jr(e, L, I, oe ? `#${oe}` : "#333") : null, n = I * 1.15, r = t ? [] : ar(P, Math.max(0, b - I), Math.max(0, x - I), n, (t) => e.measureText(t).width, ce);
			if (L && !t || !t && r.length === 0 && !re) continue;
			let i = t?.width ?? r.reduce((t, n) => Math.max(t, e.measureText(n).width), 0), a = t?.height ?? I + Math.max(0, r.length - 1) * n, o = re ? ja([re.entry], I, p)[0] ?? 0 : 0, u = re ? Na(re.entry, I, p) : 0;
			D.push(Zo(r, A, s, c, l, Math.min(o + (P ? 4 : 0) + i, Math.max(0, b - I)), Math.min(Math.max(u, a), Math.max(0, x - I)), n, I, ae ?? !1, oe ? `#${oe}` : "#333", se, t ?? void 0, re, ce, p));
			continue;
		}
		let z = R, le = s + Math.cos(A) * z, de = c + Math.sin(A) * z;
		e.font = `${ce.fontItalic ? "italic " : ""}${ae ? "bold " : ""}${I}px ${se}`;
		let fe = 2 * z * Math.sin(Math.min(Math.PI, Math.abs(k)) / 2) - I, pe = u > .01 ? l - u - I : l - I;
		if (ie !== "ctr" && (!(fe > 0) || !(pe > 0))) continue;
		let me = ie === "ctr" ? {
			x: m,
			y: h,
			w: g,
			h: _
		} : ts({
			x: le - fe / 2,
			y: de - pe / 2,
			w: fe,
			h: pe
		}, {
			x: m,
			y: h,
			w: g,
			h: _
		});
		me && ao(e, P, {
			kind: "point",
			x: le,
			y: de,
			position: "ctr"
		}, me, I, oe ? `#${oe}` : "#fff", void 0, {
			x: v,
			y,
			w: b,
			h: x
		}, L, re, ce, p, Wn(j?.labelBox, n.labelBox), C);
	}
	Qo(e, D, v, y, b, x);
}
function Yo(e, t, n, r, i, a) {
	let o = Math.max(Math.abs(n - e) - i, 0), s = Math.max(Math.abs(r - t) - a, 0);
	return Math.hypot(o, s);
}
function Xo(e, t, n, r, i) {
	let a = Math.cos(e), o = Math.sin(e), s = t + i, c = 0, l = s + Math.hypot(n, r);
	for (let e = 0; e < 32; e++) {
		let e = (c + l) / 2;
		Yo(0, 0, a * e, o * e, n, r) >= s ? l = e : c = e;
	}
	return l;
}
function Zo(e, t, n, r, i, a, o, s, c, u, d, f, p, m, h = {}, g = 1) {
	let _ = l(a, o, h.textRotation, h.textVerticalMode), v = re(h, g), y = a + v.left + v.right, b = o + v.top + v.bottom, x = l(y, b, h.textRotation, h.textVerticalMode);
	a = x.w, o = x.h;
	let S = Xo(t, i, _.w / 2, _.h / 2, c * .5), C = n + Math.cos(t) * S, w = r + Math.sin(t) * S;
	return {
		lines: e,
		rich: p,
		legendKey: m,
		boxW: a,
		boxH: o,
		unrotatedW: y,
		unrotatedH: b,
		textStyle: h,
		ptToPx: g,
		lineHeight: s,
		fontPx: c,
		bold: u,
		fontColor: d,
		font: f,
		cxBox: C,
		cyBox: w
	};
}
function Qo(e, t, n, r, i, a) {
	if (t.length !== 0) {
		e.save(), e.beginPath(), e.rect(n, r, i, a), e.clip();
		for (let n of t) {
			let t = re(n.textStyle, n.ptToPx), r = l(n.unrotatedW, n.unrotatedH, n.textStyle.textRotation, n.textStyle.textVerticalMode), i = n.cxBox + (t.left - t.right) / 2, a = n.cyBox + (t.top - t.bottom) / 2, o = Math.max(0, n.unrotatedW - t.left - t.right), s = E(n.textStyle, "center"), c = s === "left" ? n.cxBox - n.unrotatedW / 2 + t.left : s === "right" ? n.cxBox + n.unrotatedW / 2 - t.right : i;
			if (e.save(), r.radians !== 0 && (e.translate(n.cxBox, n.cyBox), e.rotate(r.radians), e.translate(-n.cxBox, -n.cyBox)), !n.legendKey) {
				if (n.rich) {
					Ar(e, n.rich, c, a, s, "middle", o), e.restore();
					continue;
				}
				e.font = `${n.textStyle.fontItalic ? "italic " : ""}${n.bold ? "bold " : ""}${n.fontPx}px ${n.font}`, e.fillStyle = n.fontColor, e.textAlign = s, e.textBaseline = "middle";
				let t = (n.textStyle.fontBaseline ?? 0) * n.fontPx, r = a - (n.lines.length - 1) * n.lineHeight / 2 - t;
				if (!(n.textStyle.fontPaintAuthored === !0 && (n.textStyle.fontHidden === !0 || n.textStyle.fontColor == null))) for (let t = 0; t < n.lines.length; t++) e.fillText(n.lines[t], c, r + t * n.lineHeight);
				e.restore();
				continue;
			}
			e.font = `${n.textStyle.fontItalic ? "italic " : ""}${n.bold ? "bold " : ""}${n.fontPx}px ${n.font}`;
			let u = n.legendKey ? ja([n.legendKey.entry], n.fontPx, n.legendKey.ptToPx)[0] ?? 0 : 0, d = n.legendKey ? Na(n.legendKey.entry, n.fontPx, n.legendKey.ptToPx) : 0, f = n.rich?.width ?? n.lines.reduce((t, n) => Math.max(t, e.measureText(n).width), 0), p = n.legendKey && (n.rich || n.lines.length > 0) ? 4 : 0, m = i - (u + p + f) / 2;
			if (n.legendKey && va(e, n.legendKey.entry.swatchStyle, n.legendKey.entry.color, m, a - d / 2, u, d, n.legendKey.entry.marker, n.legendKey.entry.fillPaint, n.legendKey.entry.outlinePaint, n.legendKey.entry.outlineColor, n.legendKey.entry.outlineWidthEmu, n.legendKey.entry.outlineDash, n.legendKey.entry.outlineCustomDash, n.legendKey.entry.outlineCap, n.legendKey.entry.outlineJoin, n.legendKey.ptToPx, n.legendKey.shapeRotationDeg, n.legendKey.entry.directEffect, n.legendKey.entry.fallbackEffect, n.legendKey.entry.directEffectIndex, n.legendKey.entry.fallbackEffectIndex), n.rich) {
				Ar(e, n.rich, m + u + p, a, "left", "middle"), e.restore();
				continue;
			}
			e.fillStyle = n.fontColor, e.textAlign = "left", e.textBaseline = "middle";
			let h = (n.textStyle.fontBaseline ?? 0) * n.fontPx, g = a - (n.lines.length - 1) * n.lineHeight / 2 - h;
			if (!(n.textStyle.fontPaintAuthored === !0 && (n.textStyle.fontHidden === !0 || n.textStyle.fontColor == null))) for (let t = 0; t < n.lines.length; t++) e.fillText(n.lines[t], m + u + p, g + t * n.lineHeight);
			e.restore();
		}
		e.restore();
	}
}
function $o(e, t, n, r, i, a, o, s, c, u, d, f, p, m, h, g, _, v, y, b, x, S, C, w, T, D) {
	let O = Sa(t, m), k = (e) => w.get(e), A = Rt(n.fontSizeHpt, m) ?? Math.max(9, u * .09), j = n.labelBox, ee = [], te = f;
	for (let f = 0; f < a.length; f++) {
		let w = a[f] / o * Math.PI * 2, E = te + w / 2;
		if (te += w, w <= 0 || !C.has(f)) continue;
		let D = k(f);
		if (ue(n, D)) continue;
		let M = D?.showCatName ?? n.showCatName, N = D?.showSerName ?? n.showSerName, ne = D?.showVal ?? n.showVal, P = D?.showPercent ?? n.showPercent, ie = D?.showLegendKey ?? n.showLegendKey ?? !1, F = Rt(D?.fontSizeHpt, m) ?? A, I = D?.fontBold ?? n.fontBold ?? !1, ae = D?.fontFace ?? n.fontFace ? Y(t, D?.fontFace ?? n.fontFace, "minor") : p, oe = D?.fontColor ? `#${D.fontColor}` : n.fontColor ? `#${n.fontColor}` : "#000", se = Wn(D?.labelBox, j), ce = D?.position ?? n.position ?? "bestFit", L = mt({
			customText: D?.text,
			showCategory: M,
			showSeries: N,
			showValue: ne,
			showPercent: P,
			category: (i[f] ?? "").toString(),
			seriesName: r.name,
			sourceValue: a[f],
			percentRatio: a[f] / o,
			formatCode: D?.formatCode ?? n.formatCode ?? r.valFormatCode ?? null,
			percentFormatCode: D?.formatCode ?? n.formatCode ?? "0%",
			date1904: t.date1904 ?? !1,
			separator: D?.separator ?? n.separator,
			defaultSeparator: "\n"
		}), R = ie ? O(0, f) : void 0;
		if (!L && !R) continue;
		let z = Ne(D, n), le = ro(t, D, m, ae, I, z), de = z.textBodyAuthored === !0 || z.textLInsEmu != null || z.textTInsEmu != null || z.textRInsEmu != null || z.textBInsEmu != null, fe = re(z, m), pe = de ? fe.left : Math.max(4, F * .45), me = de ? fe.right : Math.max(4, F * .45), he = de ? fe.top : Math.max(2, F * .28), ge = de ? fe.bottom : Math.max(2, F * .28), _e = F * .22, B = F + _e;
		e.font = `${z.fontItalic ? "italic " : ""}${I ? "bold " : ""}${F}px ${ae}`;
		let ve = le ? jr(e, le, F, oe) : null;
		if (le && !ve) continue;
		let ye = ve ? [] : ar(L, Math.max(0, g - pe - me), Math.max(0, v - he - ge), B, (t) => e.measureText(t).width, z);
		if (!ve && ye.length === 0 && !R) continue;
		let be = ve?.width ?? 0;
		if (!ve) for (let t of ye) be = Math.max(be, e.measureText(t).width);
		let xe = R ? ja([R.entry], F, m)[0] ?? 0 : 0, V = R ? Na(R.entry, F, m) : 0, Se = R && L ? 4 : 0, Ce = xe + Se + be + pe + me, we = Math.max(V, ve?.height ?? (ye.length > 0 ? ye.length * B - _e : 0)) + he + ge, Te = l(Ce, we, z.textRotation, z.textVerticalMode), Ee = Math.min(Te.w, g), H = Math.max(V, ve?.height ?? (ye.length > 0 ? ye.length * B - _e : 0));
		H = Math.min(Te.h, v);
		let De = s + Math.cos(E) * u, Oe = c + Math.sin(E) * u, ke = Math.cos(E) < 0, Ae = Math.max(Ee, H) * .55 + u * .06, je = De + Math.cos(E) * Ae, Me = Oe + Math.sin(E) * Ae, Pe, Fe = !1;
		if (D?.manualLayout) {
			let t = Qe({
				kind: "point",
				x: je,
				y: Me,
				position: "ctr"
			}, {
				x: h,
				y: _,
				w: g,
				h: v
			}, {
				w: Ee,
				h: H
			}, F, D.manualLayout, {
				x: y,
				y: b,
				w: x,
				h: S
			});
			if (!t || (Ee = t.rect.w, H = t.rect.h, Ce = Ee, we = H, !ve && (ye = ar(L, Math.max(0, Ee - pe - me - xe - Se), Math.max(0, H - he - ge), B, (t) => e.measureText(t).width, z), ye.length === 0 && !R))) continue;
			je = t.rect.x + t.rect.w / 2, Me = t.rect.y + t.rect.h / 2, ke = je < s, Pe = t.clip;
		} else if (ce !== "bestFit" && ce !== "outEnd") {
			let t = Go(ce, d, u) + (ce === "ctr" ? T[f] ?? 0 : 0), n = s + Math.cos(E) * t, r = c + Math.sin(E) * t, i = 2 * t * Math.sin(Math.min(Math.PI, Math.abs(w)) / 2) - F, a = d > .01 ? u - d - F : u - F, o = ce === "ctr" ? {
				x: h,
				y: _,
				w: g,
				h: v
			} : ts({
				x: n - i / 2,
				y: r - a / 2,
				w: i,
				h: a
			}, {
				x: h,
				y: _,
				w: g,
				h: v
			});
			if (!o) continue;
			if (ve) Ee = Math.min(Ee, o.w), H = Math.min(H, o.h);
			else {
				if (ye = ar(L, Math.max(0, o.w - pe - me - xe - Se), Math.max(0, o.h - he - ge), B, (t) => e.measureText(t).width, z), ye.length === 0 && !R) continue;
				be = ye.reduce((t, n) => Math.max(t, e.measureText(n).width), 0), Ce = xe + Se + be + pe + me, we = Math.max(V, ye.length > 0 ? ye.length * B - _e : 0) + he + ge, Te = l(Ce, we, z.textRotation, z.textVerticalMode), Ee = Te.w, H = Te.h;
			}
			let p = Qe({
				kind: "point",
				x: n,
				y: r,
				position: ce === "inBase" || ce === "inEnd" ? "ctr" : ce
			}, o, {
				w: Ee,
				h: H
			}, F);
			if (!p) continue;
			je = p.textAlign === "left" ? p.x + Ee / 2 : p.textAlign === "right" ? p.x - Ee / 2 : p.x, Me = p.textBaseline === "top" ? p.y + H / 2 : p.textBaseline === "bottom" ? p.y - H / 2 : p.y, ke = je < s, Pe = p.clip, Fe = !0;
		}
		ee.push({
			lines: ye,
			rich: ve ?? void 0,
			legendKey: R,
			lineHeight: B,
			midAngle: E,
			rimX: De,
			rimY: Oe,
			boxW: Ee,
			boxH: H,
			unrotatedW: Ce,
			unrotatedH: we,
			cxBox: je,
			cyBox: Me,
			leftSide: ke,
			fontColor: oe,
			box: se,
			fontPx: F,
			bold: I,
			font: ae,
			textStyle: z,
			ptToPx: m,
			inside: Fe,
			manualClip: Pe
		});
	}
	let M = _ + 2, N = _ + v - 2, ne = N - M, P = (e) => {
		if (e.length === 0) return;
		e.sort((e, t) => e.cyBox - t.cyBox);
		let t = 0;
		for (let n of e) t += n.boxH;
		if (t += (e.length - 1) * 3, t > ne) {
			let t = e.reduce((e, t) => e + t.boxH, 0), n = e.length;
			if (n === 1) {
				e[0].cyBox = Math.min(Math.max(e[0].cyBox, M + e[0].boxH / 2), N - e[0].boxH / 2);
				return;
			}
			let r = (ne - t) / (n - 1), i = M;
			for (let t of e) t.cyBox = i + t.boxH / 2, i += t.boxH + r;
			return;
		}
		for (let t = 1; t < e.length; t++) {
			let n = e[t - 1], r = e[t], i = (n.boxH + r.boxH) / 2 + 3;
			r.cyBox - n.cyBox < i && (r.cyBox = n.cyBox + i);
		}
		let n = e[e.length - 1].cyBox + e[e.length - 1].boxH / 2 - N;
		if (n > 0) for (let t of e) t.cyBox -= n;
		let r = M - (e[0].cyBox - e[0].boxH / 2);
		if (r > 0) for (let t of e) t.cyBox += r;
	};
	P(ee.filter((e) => !e.manualClip && !e.leftSide)), P(ee.filter((e) => !e.manualClip && e.leftSide));
	for (let e of ee) e.manualClip || (e.cyBox = Math.max(M + e.boxH / 2, e.cyBox), e.cyBox = Math.min(N - e.boxH / 2, e.cyBox));
	let ie = h + 2, F = h + g - 2;
	for (let e of ee) {
		if (e.manualClip) continue;
		let t = e.boxW / 2;
		e.cxBox - t < ie && (e.cxBox = ie + t), e.cxBox + t > F && (e.cxBox = F - t);
	}
	e.save(), e.beginPath(), e.rect(h, _, g, v), e.clip();
	let I = _n(t, n), ae = I.color ? `#${I.color}` : "#a6a6a6", oe = I.widthEmu ? Math.max(.5, I.widthEmu / qt * m) : 1;
	e.setLineDash(Ue(I.dash ?? void 0, oe));
	for (let t of ee) {
		let r = t.cxBox + (t.leftSide ? t.boxW / 2 : -t.boxW / 2), i = t.cyBox, a = r - t.rimX, o = i - t.rimY, s = Math.hypot(a, o);
		!t.inside && n.showLeaderLines && I.hidden !== !0 && (I.paintAuthored !== !0 || I.color != null) && s > t.fontPx * .9 && (e.beginPath(), e.moveTo(t.rimX, t.rimY), e.lineTo(r, i), e.strokeStyle = ae, e.lineWidth = oe, e.stroke());
	}
	for (let t of ee) {
		t.manualClip && (e.save(), e.beginPath(), e.rect(t.manualClip.x, t.manualClip.y, t.manualClip.w, t.manualClip.h), e.clip());
		let n = t.cxBox - t.boxW / 2, r = t.cyBox - t.boxH / 2;
		Z(e, t.box, {
			x: n,
			y: r,
			w: t.boxW,
			h: t.boxH
		}, m, D);
		let i = t.textStyle.textBodyAuthored === !0 || t.textStyle.textLInsEmu != null || t.textStyle.textTInsEmu != null || t.textStyle.textRInsEmu != null || t.textStyle.textBInsEmu != null, a = re(t.textStyle, t.ptToPx), o = i ? a.left : Math.max(4, t.fontPx * .45), s = i ? a.right : Math.max(4, t.fontPx * .45), c = i ? a.top : Math.max(2, t.fontPx * .28), u = i ? a.bottom : Math.max(2, t.fontPx * .28), d = l(t.unrotatedW, t.unrotatedH, t.textStyle.textRotation, t.textStyle.textVerticalMode), f = t.cxBox + (o - s) / 2, p = t.cyBox + (c - u) / 2, h = n + o, g = n + t.boxW - s, _ = Math.max(0, g - h), v = E(t.textStyle, "center"), y = v === "left" ? h : v === "right" ? g : f, b = (e) => (t.textStyle.textVerticalAnchor ?? (t.textStyle.textBodyAuthored === !0 ? "t" : "ctr")) === "t" ? r + c + e / 2 : (t.textStyle.textVerticalAnchor ?? (t.textStyle.textBodyAuthored === !0 ? "t" : "ctr")) === "b" ? r + t.boxH - u - e / 2 : p, x = (e) => v === "left" ? h : v === "right" ? g - e : f - e / 2;
		if (e.save(), e.beginPath(), e.rect(n, r, t.boxW, t.boxH), e.clip(), d.radians !== 0 && (e.translate(t.cxBox, t.cyBox), e.rotate(d.radians), e.translate(-t.cxBox, -t.cyBox)), !t.legendKey) {
			let n = b(t.rich?.height ?? Math.max(0, t.lines.length * t.lineHeight - (t.lineHeight - t.fontPx)));
			if (t.rich) {
				Ar(e, t.rich, y, n, v, "middle", _), e.restore(), t.manualClip && e.restore();
				continue;
			}
			e.font = `${t.textStyle.fontItalic ? "italic " : ""}${t.bold ? "bold " : ""}${t.fontPx}px ${t.font}`, e.fillStyle = t.fontColor, e.textAlign = v, e.textBaseline = "middle";
			let r = t.lineHeight - t.fontPx, i = (t.textStyle.fontBaseline ?? 0) * t.fontPx, a = n - (t.lines.length * t.lineHeight - r) / 2 + t.fontPx / 2 - i;
			if (!(t.textStyle.fontPaintAuthored === !0 && (t.textStyle.fontHidden === !0 || t.textStyle.fontColor == null))) for (let n = 0; n < t.lines.length; n++) e.fillText(t.lines[n], y, a + n * t.lineHeight);
			e.restore(), t.manualClip && e.restore();
			continue;
		}
		let S = t.legendKey ? ja([t.legendKey.entry], t.fontPx, t.legendKey.ptToPx)[0] ?? 0 : 0, C = t.legendKey ? Na(t.legendKey.entry, t.fontPx, t.legendKey.ptToPx) : 0, w = t.legendKey && (t.rich || t.lines.length > 0) ? 4 : 0, T = t.rich?.width ?? t.lines.reduce((t, n) => Math.max(t, e.measureText(n).width), 0), O = S + w + T, k = b(Math.max(C, t.rich?.height ?? Math.max(0, t.lines.length * t.lineHeight - (t.lineHeight - t.fontPx)))), A = x(O);
		if (t.legendKey && va(e, t.legendKey.entry.swatchStyle, t.legendKey.entry.color, A, k - C / 2, S, C, t.legendKey.entry.marker, t.legendKey.entry.fillPaint, t.legendKey.entry.outlinePaint, t.legendKey.entry.outlineColor, t.legendKey.entry.outlineWidthEmu, t.legendKey.entry.outlineDash, t.legendKey.entry.outlineCustomDash, t.legendKey.entry.outlineCap, t.legendKey.entry.outlineJoin, t.legendKey.ptToPx, t.legendKey.shapeRotationDeg, t.legendKey.entry.directEffect, t.legendKey.entry.fallbackEffect, t.legendKey.entry.directEffectIndex, t.legendKey.entry.fallbackEffectIndex), t.rich) {
			Ar(e, t.rich, A + S + w, k, "left", "middle", T), e.restore(), t.manualClip && e.restore();
			continue;
		}
		e.font = `${t.textStyle.fontItalic ? "italic " : ""}${t.bold ? "bold " : ""}${t.fontPx}px ${t.font}`, e.fillStyle = t.fontColor, e.textAlign = "left", e.textBaseline = "middle";
		let j = t.lineHeight - t.fontPx, ee = (t.textStyle.fontBaseline ?? 0) * t.fontPx, te = k - (t.lines.length * t.lineHeight - j) / 2 + t.fontPx / 2 - ee;
		if (!(t.textStyle.fontPaintAuthored === !0 && (t.textStyle.fontHidden === !0 || t.textStyle.fontColor == null))) for (let n = 0; n < t.lines.length; n++) e.fillText(t.lines[n], A + S + w, te + n * t.lineHeight);
		e.restore(), t.manualClip && e.restore();
	}
	e.restore();
}
function es(e, t, n, r, i, a, o, s, c) {
	let l = r?.chartexStyle, u = Oe(t, n), d = I(t, "dataPoint", u >= 0 ? u : i), f = n.chartexStyle, p = k(l, r?.idx ?? i) !== void 0 || r?.lineHidden === !0 || r?.lineColor != null, m = k(f, i) !== void 0 || n.lineHidden === !0 || n.lineColor != null, h = k(d, i) !== void 0;
	!p && !m && !h || (e.save(), ie(e, t, "dataPoint", n, r, i, a, Math.max(.5, o * .75), o, s, c, !1) && e.stroke(), e.restore());
}
function ts(e, t) {
	let n = Math.max(e.x, t.x), r = Math.max(e.y, t.y), i = Math.min(e.x + e.w, t.x + t.w), a = Math.min(e.y + e.h, t.y + t.h);
	return i > n && a > r ? {
		x: n,
		y: r,
		w: i - n,
		h: a - r
	} : null;
}
function ns(n, r, i, a, o = 0) {
	let { x: s, y: c, w: l, h: u } = i, f = Jn(r), p = f.length;
	if (p < 3) return;
	let m = Fa(n, r, l, u, a), h = dn(r, s, c, l, u, a, {
		titleTopPadFrac: .035,
		titleBottomPadFrac: .035,
		legendReserve: m,
		radialGapFrac: .02,
		honorPlotAreaManualLayout: !0
	}), g = h.title.fontPx;
	To(n, r, s, c, l, u, c + h.title.topPad, g);
	let { px0: v, py0: y, pw: b, ph: x } = h.plotRect;
	if (!(b > 0) || !(x > 0)) return;
	Nn(n, r, v, y, b, x, a, o);
	let S = h.center.cx, w = h.center.cy, T = r.plotAreaManualLayout, E = T?.layoutTarget === "inner" && T.w != null && T.h != null && Number.isFinite(T.w) && Number.isFinite(T.h) && h.plotAreaManualLayoutApplied ? Math.min(b, x) / 2 : Math.min(b, x) * .38, D = Infinity, k = -Infinity;
	for (let e of r.series) for (let t of e.values) t != null && (D = Math.min(D, t), k = Math.max(k, t));
	isFinite(D) || (D = 0, k = 1), k === 0 && (k = 1);
	let A = r.valAxisMinorTickMark != null && r.valAxisMinorTickMark !== "none", j = r.valAxisLogBase != null && Number.isFinite(r.valAxisLogBase) && r.valAxisLogBase >= 2, ee = r.valAxisMajorUnit ?? (j ? null : C(r.valMin ?? D, r.valMax ?? k, E / a)), te = be({
		dataMin: D,
		dataMax: k,
		explicitMin: r.valMin,
		explicitMax: r.valMax,
		axisLenPt: E / a,
		axisOrientation: "vertical",
		majorUnit: ee,
		minorUnit: r.valAxisMinorUnit,
		needMinor: r.valAxisMinorGridlines === !0 || A,
		logBase: r.valAxisLogBase,
		reversed: dt(r)
	}), M = (e) => _(te.fraction(e), 0, 1), N = -Math.PI / 2, ne = (e) => N + e / p * Math.PI * 2, P = te.majorTicks.filter((e) => M(e) > 0), re = (e) => {
		let t = M(e) * E;
		n.beginPath();
		for (let e = 0; e < p; e++) {
			let r = ne(e), i = S + Math.cos(r) * t, a = w + Math.sin(r) * t;
			e === 0 ? n.moveTo(i, a) : n.lineTo(i, a);
		}
		n.closePath(), n.stroke();
	};
	if (r.valAxisMinorGridlines) {
		let e = Mn(r, a);
		n.strokeStyle = e.color, n.lineWidth = e.width;
		let t = e.dash.length > 0 && n.getLineDash ? n.getLineDash() : [];
		e.dash.length > 0 && n.setLineDash(e.dash);
		for (let e of te.minorTicks) re(e);
		e.dash.length > 0 && n.setLineDash(t);
	}
	if (!r.valAxisHidden && e(r)) {
		let e = mn(r, a);
		n.strokeStyle = e.color, n.lineWidth = e.width;
		let t = e.dash.length > 0 && n.getLineDash ? n.getLineDash() : [];
		e.dash.length > 0 && n.setLineDash(e.dash);
		for (let e of P) re(e);
		e.dash.length > 0 && n.setLineDash(t);
	}
	n.strokeStyle = "#bbb", n.lineWidth = .5;
	for (let e = 0; e < p; e++) {
		let t = ne(e);
		n.beginPath(), n.moveTo(S, w), n.lineTo(S + Math.cos(t) * E, w + Math.sin(t) * E), n.stroke();
	}
	if (!r.valAxisHidden) {
		let e = je(r.valAxisFontSizeHpt, u, a);
		n.font = rr(e, Y(r, r.valAxisFontFace, "minor"), r.valAxisFontBold ?? !1, r.valAxisFontItalic ?? !1), n.fillStyle = r.valAxisFontColor ? `#${r.valAxisFontColor}` : "#555", n.textAlign = "right", n.textBaseline = "middle";
		for (let t of P) {
			let i = w - M(t) * E, o = it(r.valAxisLineColor, r.valAxisLineWidthEmu, a);
			F(n, r.valAxisMajorTickMark, "val", S, i, o.color, o.width, !1, r.valAxisLineHidden, "major", a, r.valAxisLineDash), r.valAxisTickLabelPos !== "none" && Pt(n, In(r, t, !1), S - 3, i, nn(e));
		}
		if (A) {
			let e = it(r.valAxisLineColor, r.valAxisLineWidthEmu, a);
			for (let t of te.minorTicks) F(n, r.valAxisMinorTickMark, "val", S, w - M(t) * E, e.color, e.width, !1, r.valAxisLineHidden, "minor", a, r.valAxisLineDash);
		}
	}
	let ae = r.catAxisFontSizeHpt == null ? Math.max(8, Math.min(11, E * .2)) : je(r.catAxisFontSizeHpt, u, a);
	n.font = rr(ae, Y(r, r.catAxisFontFace, "minor"), r.catAxisFontBold ?? !1, r.catAxisFontItalic ?? !1), n.fillStyle = r.catAxisFontColor ? `#${r.catAxisFontColor}` : "#444", n.textBaseline = "middle";
	let oe = S - b / 2, se = S + b / 2;
	if (!r.catAxisHidden && O(r)) for (let e = 0; e < p; e++) {
		let t = ne(e), i = Qn(12, r.catAxisLabelOffsetPercent), a = S + Math.cos(t) * (E + i), o = w + Math.sin(t) * (E + i), s = r.catAxisLabelAlignment, c = s === "l" ? "left" : s === "r" ? "right" : s === "ctr" ? "center" : Math.cos(t) < -.1 ? "right" : Math.cos(t) > .1 ? "left" : "center";
		n.textAlign = c;
		let l = c === "right" ? a - oe : c === "left" ? se - a : 2 * Math.min(se - a, a - oe);
		Pt(n, er((f[e] ?? "").toString(), r.catAxisFormatCode, r.date1904), a, o, nn(ae), l);
	}
	let ce = pt("radar", r.chartType, r.scatterStyle, r.radarStyle), L = Math.max(2, E * .025);
	for (let e = 0; e < r.series.length; e++) {
		let i = r.series[e], s = fe(e, i), c = Ie(i, e), l = [];
		for (let e = 0; e < p; e++) {
			let t = i.values[e];
			if (t == null) {
				l.push(null);
				continue;
			}
			let n = M(t), r = ne(e);
			l.push([S + Math.cos(r) * E * n, w + Math.sin(r) * E * n]);
		}
		let u = l.every((e) => e != null), f = [], m = [];
		for (let e = 0; e < l.length; e++) {
			let t = l[e];
			t == null ? (m.length > 0 && f.push(m), m = []) : m.push({
				x: t[0],
				y: t[1],
				index: e
			});
		}
		m.length > 0 && f.push(m);
		let h = {
			x: v,
			y,
			w: b,
			h: x
		}, g = (e) => {
			let t = e.getLineDash ? e.getLineDash() : [], n = e.lineCap, d = e.lineJoin;
			e.beginPath();
			let f = !1;
			for (let t of l) {
				if (t == null) {
					f = !1;
					continue;
				}
				f ? e.lineTo(t[0], t[1]) : (e.moveTo(t[0], t[1]), f = !0);
			}
			u && e.closePath(), ce && u && zi(e, me(r, i, void 0, c), h, ut(s, .25), a, o), ie(e, r, "dataPointLine", i, void 0, c, s, 2, a, h, o) && e.stroke(), e.setLineDash(t), e.lineCap = n, e.lineJoin = d;
		};
		if (t(r, e)) {
			if (ce && u) {
				let t = cr(i.dataPointOverrides).get(0);
				Pe(n, de(t?.chartexStyle, i.chartexStyle), I(r, "dataPoint", e), 0, h, a, (e) => {
					let n = me(r, i, t, 0, 0);
					if (n !== null) {
						e.beginPath();
						for (let t = 0; t < l.length; t++) {
							let n = l[t];
							t === 0 ? e.moveTo(n[0], n[1]) : e.lineTo(n[0], n[1]);
						}
						e.closePath(), zi(e, n, h, ut(s, .25), a, o);
					}
				}, 0);
			}
			d(n, r, i, f, !1, u, s, 2, a, h, o);
		} else Pe(n, de(i.chartexStyle), r.chartStyleRoles?.[ce ? "dataPoint" : "dataPointLine"], c, h, a, g);
		let _ = !ce && i.showMarker !== !1 && i.markerSymbol !== "none";
		if (!ce && (_ || Ft(i))) {
			let e = cr(i.dataPointOverrides);
			for (let t = 0; t < l.length; t++) {
				let c = l[t];
				if (c == null) continue;
				let u = e.get(t), d = rn(i, u, "circle", _);
				if (d === "none") continue;
				let f = u?.markerSize ?? i.markerSize ?? Math.max(4, L * 2 / a), p = Mt(i, u, t, s), m = u?.markerLine ?? i.markerLine ?? null, h = u?.markerLineWidthEmu ?? i.markerLineWidthEmu;
				ra(n, r, i, u, t, c[0], c[1], d, f, p, m, a, h == null ? 1 : nt(h, a), st(i, u, t), o);
			}
		}
	}
	Ra(n, r, m, s, c, l, u, v, y, b, x, h.title.bandH + 2, a);
}
function rs(t, r, i, a, o = 0) {
	let { x: c, y: l, w: u, h: d } = i, f = r.series.map((e, t) => ({
		series: e,
		index: t
	})), p = kn(r), m = ({ series: e, index: t }) => p[t]?.categoryAxis === "secondary" || r.plotGroups == null && e.useSecondaryAxis === !0, h = ({ series: e, index: t }) => p[t]?.valueAxis === "secondary" || r.plotGroups == null && e.useSecondaryAxis === !0, g = f.filter((e) => !m(e)), v = f.filter(m), y = f.filter((e) => !h(e)), b = f.filter(h), x = f.filter((e) => !m(e) && !h(e)), S = f.filter((e) => m(e) && h(e)), C = v.length > 0 ? r.secondaryCatAxis : null, T = b.length > 0 ? r.secondaryValAxis : null, E = ((e) => {
		let t = [];
		for (let { series: n } of e) {
			let e = n.categories ?? r.categories;
			for (let n of e) {
				let e = parseFloat(n);
				Number.isFinite(e) && t.push(e);
			}
		}
		return t;
	})(f).length === 0, D = f.length === 1 && f[0].series.bubbleXSourceIsString === !0 ? f[0].series.values.length + 1 : null, O = (e) => {
		let t = [], n = [];
		for (let { series: i } of e) {
			let e = i.categories ?? r.categories;
			for (let r = 0; r < i.values.length; r++) {
				let a = i.values[r];
				if (a == null) continue;
				let o = Xa(e, r, E);
				o != null && (t.push(o), n.push(a));
			}
			w(i, "x", (t) => i.values[t] == null ? null : Xa(e, t, E), (e) => t.push(e)), w(i, "y", (t) => Xa(e, t, E) == null ? null : i.values[t] ?? null, (e) => n.push(e));
		}
		if (E && t.length === 0) {
			let n = 0;
			for (let { series: t } of e) n = Math.max(n, t.values.length);
			for (let e = 0; e < n; e++) t.push(e);
		}
		return {
			x: Ce(t),
			y: Ce(n)
		};
	}, k = {
		x: O(g.length > 0 ? g : v).x,
		y: O(y.length > 0 ? y : b).y
	}, A = {
		x: O(v).x,
		y: O(b).y
	}, j = Co(t, r, u, d, a), te = j.fontPx, M = j.topPad, N = je(r.catAxisFontSizeHpt, d, a), ne = je(r.valAxisFontSizeHpt, d, a), P = Fa(t, r, u, d, a), { legRightW: re, legLeftW: ie, legTopH: I, legBottomH: ae } = Pn(P, r.legendOverlay === !0), oe = wt(r, u, d, a), ce = oe.catFontPx, L = oe.valFontPx, R = oe.catBandH, z = oe.valBandW;
	To(t, r, c, l, u, d, l + M, te);
	let le = T ? be({
		dataMin: A.y.min,
		dataMax: A.y.max,
		explicitMin: T.min,
		explicitMax: T.max,
		axisLenPt: Math.max(1, d * .7 / a),
		axisOrientation: "vertical",
		majorUnit: T.majorUnit,
		minorUnit: T.minorUnit,
		needMinor: T.minorGridlines === !0 || T.minorTickMark != null && T.minorTickMark !== "none",
		logBase: T.logBase,
		reversed: T.orientation === "maxMin"
	}) : null, ue = 0;
	if (T && le && !T.hidden && T.tickLabelPos !== "none") {
		let e = t.font;
		t.font = rr(Rt(T.fontSizeHpt, a) ?? ne, Y(r, T.fontFace, "minor"), T.fontBold ?? !1, T.fontItalic ?? !1);
		for (let e of le.majorTicks) ue = Math.max(ue, ot(t, Dt(e, T.formatCode, r.date1904, T.displayUnits)));
		ue += Ln(ne) + 4, t.font = e;
	}
	let de = C && !C.hidden && C.tickLabelPos !== "none" ? (Rt(C.fontSizeHpt, a) ?? N) + n(N) + 2 : 0, fe = (e, t, n, r, i, o, s, c) => be({
		dataMin: t ?? e.min,
		dataMax: n ?? e.max,
		explicitMin: t,
		explicitMax: n,
		axisLenPt: Math.max(1, r * .7 / a),
		axisOrientation: i,
		majorUnit: o,
		minorUnit: null,
		needMinor: !1,
		logBase: s,
		reversed: c
	}).majorTicks, pe = r.valAxisHidden || r.valAxisTickLabelPos === "none" ? 0 : yt(fe(k.y, r.valMin, r.valMax, d, "vertical", r.valAxisMajorUnit, r.valAxisLogBase, dt(r)).map((e) => In(r, e, !1)), nn(ne)), me = T && le && !T.hidden && T.tickLabelPos !== "none" ? yt(le.majorTicks.map((e) => Dt(e, T.formatCode, r.date1904, T.displayUnits)), nn(Rt(T.fontSizeHpt, a) ?? ne)) : 0, he = r.catAxisHidden || r.catAxisTickLabelPos === "none" ? 0 : yt(fe(k.x, r.catAxisMin ?? (D == null ? null : 0), r.catAxisMax ?? D, u, "horizontal", r.catAxisMajorUnit, r.catAxisLogBase, Ke(r)).map((e) => Dt(e, r.catAxisFormatCode, r.date1904, r.catAxisDisplayUnits)), nn(N)), ge = C && !C.hidden && C.tickLabelPos !== "none" ? yt(fe(A.x, C.min, C.max, u, "horizontal", C.majorUnit, C.logBase, C.orientation === "maxMin").map((e) => Dt(e, C.formatCode, r.date1904, C.displayUnits)), nn(Rt(C.fontSizeHpt, a) ?? N)) : 0, B = r.catAxisTickLabelPos === "high", ve = Math.max(Math.max(pe, me) / 2, ge) + (B ? he : 0), ye = B ? 0 : he, { plotRect: { px0: xe, py0: V, pw: Se, ph: we } } = dn(r, c, l, u, d, a, {
		titleBand: j,
		legendReserve: P,
		pad: {
			t: j.bandH + I + ne / 2 + 2 + de + ve,
			r: re + u * .05 + ue,
			b: (r.catAxisHidden ? d * .04 : ke(N) + ye) + R + ae,
			l: (r.valAxisHidden ? u * .04 : u * .12) + z + ie
		},
		honorPlotAreaManualLayout: !0
	});
	if (Se <= 0 || we <= 0) return;
	Nn(t, r, xe, V, Se, we, a, o);
	let { min: Te, max: Ee } = k.x, { min: H, max: De } = k.y;
	r.valMin != null && (H = r.valMin), r.valMax != null && (De = r.valMax);
	let Oe = r.valAxisMinorGridlines === !0 || r.valAxisMinorTickMark != null && r.valAxisMinorTickMark !== "none", Ae = be({
		dataMin: H,
		dataMax: De,
		explicitMin: r.valMin,
		explicitMax: r.valMax,
		axisLenPt: we / a,
		axisOrientation: "vertical",
		majorUnit: r.valAxisMajorUnit,
		minorUnit: r.valAxisMinorUnit,
		needMinor: Oe,
		logBase: r.valAxisLogBase,
		reversed: dt(r)
	});
	H = Ae.min, De = Ae.max;
	let Me = r.catAxisMinorGridlines === !0 || r.catAxisMinorTickMark != null && r.catAxisMinorTickMark !== "none", Ne = be({
		dataMin: Te,
		dataMax: Ee,
		explicitMin: r.catAxisMin ?? (D == null ? null : 0),
		explicitMax: r.catAxisMax ?? D,
		axisLenPt: Se / a,
		axisOrientation: "horizontal",
		majorUnit: r.catAxisMajorUnit,
		minorUnit: r.catAxisMinorUnit,
		needMinor: Me,
		logBase: r.catAxisLogBase,
		reversed: Ke(r)
	});
	Te = Ne.min, Ee = Ne.max;
	let Pe = C ? be({
		dataMin: A.x.min,
		dataMax: A.x.max,
		explicitMin: C.min,
		explicitMax: C.max,
		axisLenPt: Se / a,
		axisOrientation: "horizontal",
		majorUnit: C.majorUnit,
		minorUnit: C.minorUnit,
		needMinor: C.minorGridlines === !0 || C.minorTickMark != null && C.minorTickMark !== "none",
		logBase: C.logBase,
		reversed: C.orientation === "maxMin"
	}) : null, Fe = T ? be({
		dataMin: A.y.min,
		dataMax: A.y.max,
		explicitMin: T.min,
		explicitMax: T.max,
		axisLenPt: we / a,
		axisOrientation: "vertical",
		majorUnit: T.majorUnit,
		minorUnit: T.minorUnit,
		needMinor: T.minorGridlines === !0 || T.minorTickMark != null && T.minorTickMark !== "none",
		logBase: T.logBase,
		reversed: T.orientation === "maxMin"
	}) : null, Ie = (e) => xe + Ne.fraction(e) * Se, Le = (e) => V + we - Ae.fraction(e) * we, Re = (e) => xe + (Pe?.fraction(e) ?? 0) * Se, ze = (e) => V + we - (Fe?.fraction(e) ?? 0) * we, Be = Ne.majorUnit, Ve = Ae.majorTicks, He = Ae.minorTicks, U = Ne.majorTicks, Ue = Ne.minorTicks, We = V + we;
	if (r.catAxisCrossesAt != null) We = _(Le(r.catAxisCrossesAt), V, V + we);
	else {
		let e = r.catAxisCrosses ?? "autoZero";
		e === "autoZero" && H < 0 && De > 0 ? We = _(Le(0), V, V + we) : e === "max" && (We = V);
	}
	let Ge = xe;
	if (r.valAxisCrossesAt != null) Ge = _(Ie(r.valAxisCrossesAt), xe, xe + Se);
	else {
		let e = r.valAxisCrosses ?? "autoZero";
		e === "autoZero" && Te < 0 && Ee > 0 ? Ge = _(Ie(0), xe, xe + Se) : e === "max" && (Ge = xe + Se);
	}
	let qe = mn(r, a);
	if (!r.valAxisHidden) {
		let n = r.valAxisFontSizeHpt == null ? Math.max(8, Math.min(11, we / 20)) : je(r.valAxisFontSizeHpt, d, a), i = r.valAxisFontSizeHpt == null ? 4 : Ln(n);
		t.font = rr(n, Y(r, r.valAxisFontFace, "minor"), r.valAxisFontBold ?? !1, r.valAxisFontItalic ?? !1);
		let o = r.valAxisLineColor ? `#${r.valAxisLineColor}` : void 0, c = nt(r.valAxisLineWidthEmu, a), l = r.valAxisLineHidden ? 0 : s(r.valAxisMajorTickMark, "major", c, a);
		if (r.valAxisMinorGridlines) {
			let e = Mn(r, a);
			for (let n of He) Vt(t, xe, Se, Le(n), !1, e);
		}
		for (let s of Ve) {
			let u = Le(s);
			if (t.strokeStyle = qe.color, t.lineWidth = qe.width, e(r)) {
				let e = qe.dash.length > 0 && t.getLineDash ? t.getLineDash() : [];
				qe.dash.length > 0 && t.setLineDash(qe.dash), t.beginPath(), t.moveTo(xe, u), t.lineTo(xe + Se, u), t.stroke(), qe.dash.length > 0 && t.setLineDash(e);
			}
			if (r.valAxisTickLabelPos !== "none") {
				t.fillStyle = r.valAxisFontColor ? `#${r.valAxisFontColor}` : "#555";
				let e = r.valAxisTickLabelPos ?? "nextTo", a;
				e === "high" ? (t.textAlign = "left", a = xe + Se + i) : e === "low" ? (t.textAlign = "right", a = xe - i) : (t.textAlign = "right", a = Ge - l - i), t.textBaseline = "middle", Pt(t, In(r, s, !1), a, u, nn(n));
			}
			F(t, r.valAxisMajorTickMark, "val", Ge, u, o, c, !1, r.valAxisLineHidden, "major", a, r.valAxisLineDash);
		}
		if (r.valAxisMinorTickMark && r.valAxisMinorTickMark !== "none") for (let e of He) F(t, r.valAxisMinorTickMark, "val", Ge, Le(e), o, c, !1, r.valAxisLineHidden, "minor", a, r.valAxisLineDash);
	}
	if (!r.catAxisHidden && W(r) && Be > 0) {
		let e = ee(r, a);
		t.strokeStyle = e.color, t.lineWidth = e.width;
		let n = e.dash.length > 0 && t.getLineDash ? t.getLineDash() : [];
		e.dash.length > 0 && t.setLineDash(e.dash);
		for (let e of U) {
			let n = Ie(e);
			t.beginPath(), t.moveTo(n, V), t.lineTo(n, V + we), t.stroke();
		}
		e.dash.length > 0 && t.setLineDash(n);
	}
	if (!r.catAxisHidden && r.catAxisMinorGridlines && Be > 0) {
		let e = se(r, a), n = e.dash.length > 0 && t.getLineDash ? t.getLineDash() : [];
		t.strokeStyle = e.color, t.lineWidth = e.width, e.dash.length > 0 && t.setLineDash(e.dash);
		for (let e of Ue) {
			let n = Ie(e);
			t.beginPath(), t.moveTo(n, V), t.lineTo(n, V + we), t.stroke();
		}
		e.dash.length > 0 && t.setLineDash(n);
	}
	if (!r.catAxisHidden && !r.catAxisLineHidden && (t.save(), t.lineCap = "butt", En(t, xe, We, xe + Se, We, r.catAxisLineColor ? `#${r.catAxisLineColor}` : "#888", nt(r.catAxisLineWidthEmu, a), r.catAxisLineDash), t.restore()), !r.valAxisHidden && !r.valAxisLineHidden && (t.save(), En(t, Ge, V, Ge, V + we, r.valAxisLineColor ? `#${r.valAxisLineColor}` : "#888", nt(r.valAxisLineWidthEmu, a), r.valAxisLineDash), t.restore()), !r.catAxisHidden) {
		let e = r.catAxisFontSizeHpt == null ? Math.max(8, Math.min(11, we / 20)) : je(r.catAxisFontSizeHpt, d, a), i = r.catAxisFontSizeHpt == null ? 4 : n(e);
		t.font = rr(e, Y(r, r.catAxisFontFace, "minor"), r.catAxisFontBold ?? !1, r.catAxisFontItalic ?? !1), t.fillStyle = r.catAxisFontColor ? `#${r.catAxisFontColor}` : "#555", t.textAlign = "center";
		let o = r.catAxisTickLabelPos ?? "nextTo", c = nt(r.catAxisLineWidthEmu, a), l = r.catAxisLineColor ? `#${r.catAxisLineColor}` : void 0, u = r.catAxisLineHidden ? 0 : s(r.catAxisMajorTickMark, "major", c, a), f = o === "low" ? V + we + i : o === "high" ? V - i : We + u + i;
		t.textBaseline = o === "high" ? "bottom" : "top";
		for (let n of U) {
			let i = Ie(n);
			o !== "none" && Pt(t, Dt(n, r.catAxisFormatCode, r.date1904, r.catAxisDisplayUnits), i, f, nn(e)), F(t, r.catAxisMajorTickMark, "cat", We, i, l, c, !1, r.catAxisLineHidden, "major", a, r.catAxisLineDash);
		}
		if (r.catAxisMinorTickMark && r.catAxisMinorTickMark !== "none") for (let e of Ue) F(t, r.catAxisMinorTickMark, "cat", We, Ie(e), l, c, !1, r.catAxisLineHidden, "minor", a, r.catAxisLineDash);
	}
	let Je = (e, n, s, f, p, m, h, g) => {
		e.length !== 0 && mo(t, r, e, E, f, p, i, xe, V, Se, we, a, n, s, {
			x: c,
			y: l,
			w: u,
			h: d
		}, m, h, o, g);
	};
	if (r.plotGroups == null) {
		let e = r.chartType === "bubble", t = e ? "marker" : r.scatterStyle ?? "marker";
		Je(x, e, t, Ie, Le, Ae.max, r.valAxisDisplayUnits), S.length > 0 && Pe && Fe && Je(S, e, t, Re, ze, Fe.max, T?.displayUnits);
	} else for (let e of r.plotGroups) {
		if (e.kind !== "scatter" && e.kind !== "bubble") continue;
		let t = r.series.slice(e.seriesStart, e.seriesStart + e.seriesCount).map((t, n) => ({
			series: t,
			index: e.seriesStart + n
		}));
		if (t.length === 0) continue;
		let n = e.kind === "bubble", i = e.categoryAxis === "secondary", a = e.valueAxis === "secondary";
		Je(t, n, n ? "marker" : e.scatterStyle ?? r.scatterStyle ?? "marker", i ? Re : Ie, a ? ze : Le, a && Fe ? Fe.max : Ae.max, a ? T?.displayUnits : r.valAxisDisplayUnits, n ? {
			bubbleScale: e.bubbleScale ?? r.bubbleScale,
			bubbleSizeRepresents: e.bubbleSizeRepresents ?? r.bubbleSizeRepresents,
			showNegativeBubbles: e.showNegativeBubbles ?? r.showNegativeBubbles
		} : void 0);
	}
	if (C && Pe && !C.hidden) {
		let e = it(C.lineColor, C.lineWidthEmu, a);
		C.lineHidden || En(t, xe, V, xe + Se, V, e.color, e.width, C.lineDash);
		let i = Rt(C.fontSizeHpt, a) ?? N;
		t.font = rr(i, Y(r, C.fontFace, "minor"), C.fontBold ?? !1, C.fontItalic ?? !1), t.fillStyle = C.fontColor ? `#${C.fontColor}` : "#555", t.textAlign = "center", t.textBaseline = "bottom";
		let o = C.lineHidden ? 0 : s(C.majorTickMark, "major", e.width, a);
		for (let s of Pe.majorTicks) {
			let c = Re(s);
			C.tickLabelPos !== "none" && Pt(t, Dt(s, C.formatCode, r.date1904, C.displayUnits), c, V - o - n(i), nn(i)), F(t, C.majorTickMark, "cat", V, c, e.color, e.width, !0, C.lineHidden, "major", a, C.lineDash);
		}
		if (C.minorTickMark && C.minorTickMark !== "none") for (let n of Pe.minorTicks) F(t, C.minorTickMark, "cat", V, Re(n), e.color, e.width, !0, C.lineHidden, "minor", a, C.lineDash);
	}
	T && Fe && _e(t, r, T, {
		min: Fe.min,
		max: Fe.max,
		step: Fe.majorUnit,
		majorLines: Fe.majorTicks,
		minorTicks: Fe.minorTicks,
		makeToY: () => ze
	}, ze, i, xe, V, Se, we, a, Rt(T.fontSizeHpt, a) ?? ne, ue, r.valAxisFontColor ? `#${r.valAxisFontColor}` : "#555", r.date1904), Ra(t, r, P, c, l, u, d, xe, V, Se, we, j.bandH + 2, a), tt(t, r, c, l, u, d, xe, V, Se, we, ie, ae, ce, L);
}
var is = (e, t, n, r, i) => jo(e, t, n, r, {}, i), as = (e, t, n, r, i) => qo(e, t, n, !1, r, i), os = (e, t, n, r, i) => qo(e, t, n, !0, r, i), ss = {
	"bar-combo": is,
	"line-groups": Io,
	"area-groups": Uo,
	"scatter-bubble": rs,
	"stock-line": Lo
}, cs = {
	clusteredBar: is,
	clusteredBarH: is,
	stackedBar: is,
	stackedBarH: is,
	stackedBarPct: is,
	stackedBarHPct: is,
	line: Io,
	stackedLine: Io,
	stackedLinePct: Io,
	area: Uo,
	stackedArea: Uo,
	stackedAreaPct: Uo,
	pie: as,
	ofPie: Ko,
	doughnut: os,
	radar: ns,
	scatter: rs,
	bubble: rs,
	stock: Lo,
	surface: Ho,
	surface3D: Ho
};
function ls(e, t, n, r = Ut, i = 0, a, o, s, c, l = xt(t)) {
	e.save();
	try {
		if (vo(e, n, l)) return;
		t = Bn(t, r);
		let { x: u, y: d, w: f, h: p } = n, m = t.roundedCorners === !0, h = m ? 10 * r : 0, g = (e) => {
			m ? (bo(e, u, d, f, p, h), e.fill()) : e.fillRect(u, d, f, p);
		};
		Pe(e, t.chartAreaStyle, t.chartStyleRoles?.chartArea, 0, n, r, (e) => {
			if (t.chartFillHidden !== !0) if (t.chartFill?.fillType === "image") m ? (e.save(), bo(e, u, d, f, p, h), e.clip(), Vn(e, t.chartFill, u, d, f, p, r, i), e.restore()) : Vn(e, t.chartFill, u, d, f, p, r, i);
			else if (t.chartFill) {
				let n = jn(t.chartFill, e, u, d, f, p, i);
				n && (e.fillStyle = n), n && g(e);
			} else t.chartBg && (e.fillStyle = `#${t.chartBg}`, g(e));
			if (t.chartBorderHidden !== !0 && (t.chartBorderLineFill || t.chartBorderColor)) {
				e.save();
				let n = t.chartBorderLineFill ? jn(t.chartBorderLineFill, e, u, d, f, p, i) : t.chartBorderColor ? `#${t.chartBorderColor}` : null;
				if (!n) e.restore();
				else {
					e.strokeStyle = n;
					let i = t.chartBorderWidthEmu ? Math.max(.5, t.chartBorderWidthEmu / qt) * r : 1;
					e.setLineDash(Zn(t.chartBorderCustomDash, t.chartBorderDash, i)), e.lineCap = t.chartBorderCap === "rnd" ? "round" : t.chartBorderCap === "sq" ? "square" : "butt", e.lineJoin = t.chartBorderJoin === "round" || t.chartBorderJoin === "bevel" ? t.chartBorderJoin : "miter", oe(e, u, d, f, p, i, t.chartBorderCompound, m ? h : 0), e.restore();
				}
			}
		});
		let _ = t.chartexBox != null || t.chartexSunburst != null || t.chartexTreemap != null || t.chartexRegionMap != null;
		if (t.series.length === 0 && !_) {
			t.authoredWithoutSeries !== !0 && (e.fillStyle = "#888", e.font = "12px sans-serif", e.textAlign = "center", e.textBaseline = "middle", e.fillText("(no data)", u + f / 2, d + p / 2)), yo(e, t, n, r);
			return;
		}
		let v = $t(t), y = (v != null || t.chartexBox != null) && (v ?? 0) <= 1e4 ? go(t, s, r, n) : null, b = _o(t, a), x = v != null && v <= 1e4 ? Rr(t, s, r, n, b != null) : null, S = uo(t, a, s, r, n);
		if ((v != null || y != null || x != null || b != null || S != null) && vo(e, n, Math.max(v ?? 0, y != null && y + (x ?? 0) > Ei ? 10001 : 0, x != null && x > Ei ? 10001 : 0, b ?? 0, S != null && S > Oi ? 10001 : 0))) {
			yo(e, t, n, r);
			return;
		}
		let C = fn(t);
		if (C === "unsupported") {
			e.fillStyle = "#888", e.font = "11px sans-serif", e.textAlign = "center", e.textBaseline = "middle", e.fillText("Unsupported chart", u + f / 2, d + p / 2), yo(e, t, n, r);
			return;
		}
		if (C !== "legacy") {
			ss[C]?.(e, t, n, r, i), K(e, t, n, r), yo(e, t, n, r);
			return;
		}
		if (a?.render(e, t, n, r, i)) {
			K(e, t, n, r), yo(e, t, n, r);
			return;
		}
		if (o?.render(e, t, n, r, i)) {
			yo(e, t, n, r);
			return;
		}
		if (c?.render(e, t, n, r, i)) {
			K(e, t, n, r), yo(e, t, n, r);
			return;
		}
		let w = Object.hasOwn(cs, t.chartType) ? cs[t.chartType] : void 0;
		w ? w(e, t, n, r, i) : (e.fillStyle = "#888", e.font = "11px sans-serif", e.textAlign = "center", e.textBaseline = "middle", e.fillText("Unsupported chart", u + f / 2, d + p / 2)), K(e, t, n, r), yo(e, t, n, r);
	} finally {
		e.restore();
	}
}
function us(e, t, n = Ut, r = 0, i, a, o, s) {
	if (!tn(e).some(({ fill: e }) => un(e))) return [];
	let c = /* @__PURE__ */ new Map(), l = null;
	try {
		l = rt(1, 1);
		let u = l?.getContext("2d");
		if (!l || !u) throw new Gn("unsupported-image-effect", "Chart occurrence surface unavailable");
		let d = l;
		at(d), fs(u, e, t, n, r, i, a, (e, t) => {
			if (!un(e)) return null;
			let n = s?.(e, t);
			if (n !== void 0) return n;
			if (t) {
				let n = Sn(e, void 0, t);
				c.has(n) || (wn(c.size + 1, 256), c.set(n, {
					fill: e,
					occurrence: t
				}));
			}
			return d;
		}, o);
	} catch (e) {
		throw wi(e);
	} finally {
		l && (l.width = 0);
	}
	return [...c.values()];
}
function ds(e) {
	wn(e, 256);
}
function fs(e, t, n, r = Ut, i = 0, a, o, s, c) {
	St(e, r, () => Tn(() => {
		yn(() => {
			nr(s, () => {
				let l = xt(t), d = l <= 1e4 ? Bt(j(t)) : t;
				u(e, () => {
					ls(e, d, n, r, i, a, o, s, c, l);
				}, void 0, l <= 1e4 ? _t(d) : 1);
			});
		});
	}));
}
export { qi as A, Ur as B, Gi as C, Qi as D, Bi as E, Si as F, Hr as H, ui as I, ri as L, wi as M, hi as N, Ki as O, xi as P, ii as R, Ii as S, zi as T, Br as V, Ni as _, Ao as a, Xi as b, Co as c, io as d, Ra as f, Ji as g, Hi as h, Io as i, Vi as j, Ui as k, vo as l, aa as m, us as n, jo as o, Fa as p, fs as r, To as s, ds as t, ao as u, Li as v, Ai as w, Wi as x, Ri as y, ti as z };
