import { $r as e, Aa as t, Ao as n, Co as r, Dr as i, Eo as a, Fr as o, Gn as s, Hn as c, Ir as l, Jn as u, Kn as d, Oo as f, Pr as p, Qr as m, So as h, To as g, Tr as _, Un as v, Vn as y, Vr as b, Wn as x, Yn as S, Zr as C, _o as w, a as T, ao as E, ar as D, c as O, co as k, cr as A, d as j, do as M, ei as N, fo as P, fr as F, go as I, gr as L, h as R, ho as z, i as B, ka as V, lo as H, lr as U, m as W, mo as G, mr as ee, nr as te, o as K, oo as ne, or as q, po as J, pr as re, qn as ie, r as ae, so as oe, ti as se, u as ce, uo as le, ur as ue, vo as de, wo as fe, wr as pe, xr as me, yo as he } from "./plot-area-frame-D3JtHvov.js";
import { $ as ge, At as _e, Ct as ve, Dt as ye, E as be, Et as xe, Ft as Se, Gt as Ce, J as we, Mt as Te, Nt as Ee, Pt as De, Q as Oe, Tt as ke, Ut as Ae, Wt as je, X as Me, Y as Ne, Z as Pe, Zt as Fe, _ as Ie, _t as Le, bt as Re, c as ze, et as Be, f as Ve, ft as He, g as Ue, gt as We, h as Ge, i as Ke, jt as qe, kt as Je, l as Ye, mn as Xe, nt as Ze, o as Qe, p as $e, pn as et, pt as tt, r as nt, sn as rt, st as it, tn as at, tt as ot, ut as st, v as ct, vt as lt, wt as ut, xt as dt, y as ft, yt as pt } from "./bounded-raw-part-cache-B8t03tSP.js";
import { a as mt, c as ht, d as gt, f as _t, h as vt, m as yt, o as bt, p as xt, r as St, s as Ct, t as wt, u as Tt, y as Et } from "./line-distribute-DR9Qwd3u.js";
import { a as Dt, f as Ot, i as kt, u as At } from "./pixel-budget-DI82Hn3R.js";
import { H as jt, L as Mt, M as Nt, R as Pt, V as Ft, n as It, r as Lt, t as Rt } from "./renderer-xdugdcMd.js";
import { i as zt } from "./tiff-contract-DEd8CGuz.js";
import { _ as Bt, g as Vt, h as Ht } from "./three-d-ByHGsYrX.js";
import { a as Ut, o as Wt } from "./duotone-BI7CoghW.js";
import { _ as Gt, a as Kt, c as qt, d as Jt, f as Yt, h as Xt, i as Zt, l as Qt, m as $t, n as en, o as tn, p as nn, s as rn, t as an, u as on, v as sn, y as cn } from "./resource-measurement-D2bu0Qfx.js";
function ln(e, t, n) {
	let r = typeof e.getTransform == "function" ? e.getTransform() : {
		a: 1,
		b: 0,
		c: 0,
		d: 1,
		e: 0,
		f: 0
	};
	if (![
		t,
		n,
		t + n,
		e.canvas.width,
		e.canvas.height,
		r.a,
		r.b,
		r.c,
		r.d,
		r.e,
		r.f
	].every(Number.isFinite)) return;
	let i = Math.max(Math.abs(r.a), Math.abs(r.c)), a = Math.max(Math.abs(r.b), Math.abs(r.d));
	if (i === 0 || a === 0) return;
	let o = r.a / i, s = r.c / i, c = r.b / a, l = o * (r.d / a) - c * s;
	if (l === 0) return;
	let u = Infinity, d = -Infinity;
	for (let t of [0, e.canvas.width]) for (let n of [0, e.canvas.height]) {
		let e = ((c === 0 ? 0 : -c * ((t - r.e) / i)) + (o === 0 ? 0 : o * ((n - r.f) / a))) / l;
		if (!Number.isFinite(e)) return;
		u = Math.min(u, e), d = Math.max(d, e);
	}
	let f = d - u;
	Number.isFinite(f) && (e.beginPath(), e.rect(t, u, n, f), e.clip());
}
function un(e, t) {
	let n = e.fieldType === "slidenum" && t !== void 0 ? String(t) : e.text;
	return e.caps === "all" || e.caps === "small" ? n.toUpperCase() : n;
}
function dn(e, t) {
	let n = m(e, 1, 1)?.getContext("2d");
	if (!n) return;
	let r = { paths: [] }, i = Infinity, a = Infinity, o = -Infinity, s = -Infinity, c = (e) => {
		if (!(!e || ![
			e.x,
			e.y,
			e.w,
			e.h
		].every(Number.isFinite)) && (i = Math.min(i, e.x), a = Math.min(a, e.y), o = Math.max(o, e.x + e.w), s = Math.max(s, e.y + e.h), n.shadowColor !== "rgba(0, 0, 0, 0)" && n.shadowColor !== "transparent")) {
			let t = 3 * n.shadowBlur;
			i = Math.min(i, e.x + n.shadowOffsetX - t), a = Math.min(a, e.y + n.shadowOffsetY - t), o = Math.max(o, e.x + e.w + n.shadowOffsetX + t), s = Math.max(s, e.y + e.h + n.shadowOffsetY + t);
		}
	}, l = (e, t, r, i, a = 0) => {
		let o = n.getTransform(), s = [
			[e, t],
			[e + r, t],
			[e + r, t + i],
			[e, t + i]
		], l = s.map(([e, t]) => o.a * e + o.c * t + o.e), u = s.map(([e, t]) => o.b * e + o.d * t + o.f), d = a * Math.hypot(o.a, o.c), f = a * Math.hypot(o.b, o.d);
		c({
			x: Math.min(...l) - d,
			y: Math.min(...u) - f,
			w: Math.max(...l) - Math.min(...l) + 2 * d,
			h: Math.max(...u) - Math.min(...u) + 2 * f
		});
	};
	return t(new Proxy(n, {
		get(t, i) {
			if (i === "canvas") return e.canvas;
			let a = Reflect.get(t, i, t);
			return typeof a == "function" ? (...e) => {
				let o = String(i), s = n.getTransform();
				if (ee(r, {
					a: s.a,
					b: s.b,
					c: s.c,
					d: s.d,
					e: s.e,
					f: s.f
				}, o, e), o === "fillText" || o === "strokeText") {
					let [t, r, i, a] = e, s = n.measureText(t), c = a !== void 0 && s.width > a ? a / s.width : 1;
					l(r - s.actualBoundingBoxLeft * c, i - s.actualBoundingBoxAscent, (s.actualBoundingBoxLeft + s.actualBoundingBoxRight) * c, s.actualBoundingBoxAscent + s.actualBoundingBoxDescent, o === "strokeText" ? n.lineWidth / 2 * (n.lineJoin === "miter" ? n.miterLimit : 1) : 0);
					return;
				}
				if (o === "fill" || o === "stroke") {
					if (s.a * s.d - s.b * s.c === 0) return;
					c(L(r, s, {
						lineWidth: o === "stroke" ? n.lineWidth : 0,
						lineCap: n.lineCap,
						lineJoin: n.lineJoin,
						miterLimit: n.miterLimit,
						dash: n.getLineDash()
					}));
					return;
				}
				if (o === "fillRect" || o === "strokeRect") {
					let [t, r, i, a] = e;
					l(t, r, i, a, o === "strokeRect" ? n.lineWidth / 2 * Math.max(1, n.miterLimit) : 0);
					return;
				}
				if (o === "drawImage") {
					let t = e[0], [n, r, i, a] = e.length === 9 ? e.slice(5) : e.length === 5 ? e.slice(1) : [
						e[1],
						e[2],
						t.width,
						t.height
					];
					l(n, r, i, a);
					return;
				}
				if (o !== "clearRect") return Reflect.apply(a, t, e);
			} : a;
		},
		set(e, t, n) {
			return Reflect.set(e, t, n, e);
		}
	})), o > i && s > a ? {
		x: i,
		y: a,
		w: o - i,
		h: s - a
	} : void 0;
}
var fn = 6.28, pn = 26;
function Y(e = 0, t = 0, n = 0, r = 0, i = 0) {
	return {
		kind: "orthographic",
		baseLat: e,
		baseLon: t,
		baseRev: n,
		fovDeg: 0,
		shearX: r,
		shearY: i
	};
}
function X(e, t, n, r = 45) {
	return {
		kind: "perspective",
		baseLat: e,
		baseLon: t,
		baseRev: n,
		fovDeg: r,
		sceneInches: fn,
		shearX: 0,
		shearY: 0
	};
}
var mn = {
	isometricBottomDown: Y(35.4, 314.7, 299.8),
	isometricBottomUp: Y(35.4, 45.3, 60.2),
	isometricLeftDown: Y(35, 45, 0),
	isometricLeftUp: Y(325, 45, 0),
	isometricOffAxis1Left: Y(18, 64, 0),
	isometricOffAxis1Right: Y(18, 334, 0),
	isometricOffAxis1Top: Y(301.3, 306.5, 57.6),
	isometricOffAxis2Left: Y(18, 26, 0),
	isometricOffAxis2Right: Y(18, 296, 0),
	isometricOffAxis2Top: Y(301.3, 53.5, 302.4),
	isometricOffAxis3Bottom: Y(58.7, 306.5, 302.4),
	isometricOffAxis3Left: Y(342, 64, 0),
	isometricOffAxis3Right: Y(342, 334, 0),
	isometricOffAxis4Bottom: Y(58.7, 53.5, 57.6),
	isometricOffAxis4Left: Y(342, 26, 0),
	isometricOffAxis4Right: Y(342, 296, 0),
	isometricRightDown: Y(325, 315, 0),
	isometricRightUp: Y(35, 315, 0),
	isometricTopDown: Y(324.6, 45.3, 299.8),
	isometricTopUp: Y(324.6, 314.7, 60.2),
	obliqueBottom: Y(0, 0, 0, 0, -.3),
	obliqueBottomLeft: Y(0, 0, 0, .3 / Math.sqrt(2), -.3 / Math.sqrt(2)),
	obliqueBottomRight: Y(0, 0, 0, -.3 / Math.sqrt(2), -.3 / Math.sqrt(2)),
	obliqueLeft: Y(0, 0, 0, .3, 0),
	obliqueRight: Y(0, 0, 0, -.3, 0),
	obliqueTop: Y(0, 0, 0, 0, .3),
	obliqueTopLeft: Y(0, 0, 0, .3 / Math.sqrt(2), .3 / Math.sqrt(2)),
	obliqueTopRight: Y(0, 0, 0, -.3 / Math.sqrt(2), .3 / Math.sqrt(2)),
	orthographicFront: Y(0, 0, 0),
	perspectiveAbove: X(340, 0, 0),
	perspectiveAboveLeftFacing: X(39.3, 14.3, 341.1),
	perspectiveAboveRightFacing: X(39.3, 345.7, 18.9),
	perspectiveBelow: X(20, 0, 0),
	perspectiveContrastingLeftFacing: X(10.4, 43.9, 356.4),
	perspectiveContrastingRightFacing: X(10.4, 316.1, 3.6),
	perspectiveFront: X(0, 0, 0),
	perspectiveHeroicExtremeLeftFacing: X(8.1, 34.5, 357.1, 80),
	perspectiveHeroicExtremeRightFacing: X(8.1, 325.5, 2.9, 80),
	perspectiveHeroicLeftFacing: X(349, 14.3, 2.6),
	perspectiveHeroicRightFacing: X(349, 345.7, 357.4),
	perspectiveLeft: X(0, 20, 0),
	perspectiveRelaxed: X(309.6, 0, 0),
	perspectiveRelaxedModerately: X(324.8, 0, 0),
	perspectiveRight: X(0, 340, 0)
}, hn = {
	...X(0, 0, 0, pn),
	sceneInches: void 0
};
function gn(e, t) {
	let n = Array(9).fill(0);
	for (let r = 0; r < 3; r++) for (let i = 0; i < 3; i++) {
		let a = 0;
		for (let n = 0; n < 3; n++) a += e[r * 3 + n] * t[n * 3 + i];
		n[r * 3 + i] = a;
	}
	return n;
}
function _n(e) {
	let t = e * Math.PI / 180, n = Math.cos(t), r = Math.sin(t);
	return [
		1,
		0,
		0,
		0,
		n,
		-r,
		0,
		r,
		n
	];
}
function vn(e) {
	let t = e * Math.PI / 180, n = Math.cos(t), r = Math.sin(t);
	return [
		n,
		0,
		r,
		0,
		1,
		0,
		-r,
		0,
		n
	];
}
function yn(e) {
	let t = e * Math.PI / 180, n = Math.cos(t), r = Math.sin(t);
	return [
		n,
		-r,
		0,
		r,
		n,
		0,
		0,
		0,
		1
	];
}
function bn(e, t, n, r) {
	return [
		e[0] * t + e[1] * n + e[2] * r,
		e[3] * t + e[4] * n + e[5] * r,
		e[6] * t + e[7] * n + e[8] * r
	];
}
function xn(e, t) {
	let n = t ? t.lat : e.baseLat, r = t ? t.lon : e.baseLon;
	return gn(yn(-(t ? t.rev : e.baseRev)), gn(_n(-n), vn(-r)));
}
function Sn(e) {
	return n(mn, e) || (e.startsWith("perspective") ? hn : mn.orthographicFront);
}
function Cn(e, t, n, r, i, a) {
	let o = e.fov ?? t.fovDeg, s = Math.max(1, Math.min(179, o)) * Math.PI / 180, c = (t.sceneInches === void 0 ? Math.max(r, i) / 2 : t.sceneInches * (e.unitsPerInch ?? 96)) / Math.tan(s / 2), l = Math.max(0, e.sceneDepth ?? 0, a), u = Math.abs(n[6]) * r / 2 + Math.abs(n[7]) * i / 2, d = -n[8] * l, f = u + Math.max(0, d), p = 2 * u + Math.abs(d);
	return Math.max(c, f + .02 * p);
}
function wn(e, t, n, r = 0) {
	let i = Sn(e.prst), a = xn(i, e.rot);
	if (t <= 0 || n <= 0) return {
		corners: [
			{
				x: 0,
				y: 0
			},
			{
				x: t,
				y: 0
			},
			{
				x: t,
				y: n
			},
			{
				x: 0,
				y: n
			}
		],
		isAffine: !0,
		isIdentity: !0
	};
	let o = t / 2, s = n / 2, c = [
		[-o, -s],
		[o, -s],
		[o, s],
		[-o, s]
	], l = e.zoom ?? 1, u;
	if (i.kind === "perspective") {
		let o = Cn(e, i, a, t, n, r);
		u = c.map(([e, t]) => {
			let [n, i, s] = bn(a, e, t, -r), c = o / (o - s);
			return [n * c, i * c];
		});
	} else u = c.map(([e, t]) => {
		let [n, o, s] = bn(a, e, t, -r);
		return [n + s * i.shearX, o + s * i.shearY];
	});
	u = u.map(([e, t]) => [e * l, t * l]);
	let d = u.map(([e, r]) => ({
		x: t / 2 + e,
		y: n / 2 + r
	})), f = .001 * Math.max(t, n), p = d[0].x + d[2].x - (d[1].x + d[3].x), m = d[0].y + d[2].y - (d[1].y + d[3].y), h = Math.abs(p) < f && Math.abs(m) < f, g = [
		[0, 0],
		[t, 0],
		[t, n],
		[0, n]
	], _ = !0;
	for (let e = 0; e < 4; e++) if (Math.abs(d[e].x - g[e][0]) > f || Math.abs(d[e].y - g[e][1]) > f) {
		_ = !1;
		break;
	}
	return {
		corners: d,
		isAffine: h,
		isIdentity: _
	};
}
function Tn(e, t, n, r = 0) {
	if (t !== void 0 && n !== void 0) {
		if (t <= 0 || n <= 0) return !1;
		if (e = {
			...e,
			sceneDepth: Math.max(e.sceneDepth ?? 0, r)
		}, !wn(e, t, n).isIdentity) return !0;
		if (r <= 0) return !1;
		let i = wn(e, t, n, r);
		if (Sn(e.prst).sceneInches === void 0) return !i.isIdentity;
		let a = .001 * Math.max(t, n);
		return i.corners.some((e) => e.x < -a || e.y < -a || e.x > t + a || e.y > n + a);
	}
	let i = Sn(e.prst), a = xn(i, e.rot), o = i.kind === "perspective" ? [
		a[0] - 1,
		a[1],
		a[3],
		a[4] - 1,
		a[6],
		a[7]
	] : [
		a[0] + i.shearX * a[6] - 1,
		a[1] + i.shearX * a[7],
		a[3] + i.shearY * a[6],
		a[4] + i.shearY * a[7] - 1
	];
	if (r > 0) {
		if (i.kind === "perspective") return !0;
		o.push(a[2] + i.shearX * a[8], a[5] + i.shearY * a[8]);
	}
	return (e.zoom ?? 1) !== 1 || o.some((e) => Math.abs(e) > 1e-12);
}
function En(e) {
	let t = Sn(e.prst), n = xn(t, e.rot);
	return {
		rotation: n,
		backFacing: n[8] - t.shearX * n[2] - t.shearY * n[5] < 0
	};
}
function Dn(e, t, n, r) {
	let i = Sn(e.prst), a = xn(i, e.rot);
	if (t <= 0 || n <= 0 || r === 0) return {
		x: 0,
		y: 0
	};
	let o = e.zoom ?? 1, s = i.kind === "perspective" ? Cn(e, i, a, t, n, r) : 0, c = (e) => {
		let [t, n, r] = bn(a, 0, 0, e);
		if (i.kind === "perspective") {
			let e = s / (s - r);
			return [t * e * o, n * e * o];
		}
		return [(t + r * i.shearX) * o, (n + r * i.shearY) * o];
	}, [l, u] = c(0), [d, f] = c(-r);
	return {
		x: d - l,
		y: f - u
	};
}
var On = {
	angle: [[1, 1]],
	artDeco: [[
		0,
		.184095,
		.149238,
		.333333,
		.333333,
		.333333
	], [
		.333333,
		.701523,
		.63181,
		1,
		1,
		1
	]],
	circle: [[
		0,
		.55627,
		.44373,
		1,
		1,
		1
	]],
	convex: [
		[
			0,
			.07082,
			.0297458,
			.1,
			.101416,
			.1
		],
		[
			.501416,
			.1,
			.9,
			.7,
			.901416,
			.899999
		],
		[
			.9,
			.97167,
			.93343,
			1,
			1,
			1
		]
	],
	coolSlant: [[
		0,
		.138122,
		0,
		.2,
		.271356,
		.775535
	], [
		.377654,
		1,
		.519455,
		1,
		.583589,
		1
	]],
	cross: [
		[
			0,
			.05563,
			.04437,
			.1,
			.1,
			.1
		],
		[.4, .1],
		[
			.45563,
			.1,
			.5,
			.14437,
			.5,
			.2
		],
		[.5, .9],
		[
			.5,
			.95563,
			.54437,
			1,
			.6,
			1
		]
	],
	divot: [
		[
			0,
			.236604,
			.119276,
			.607024,
			.263046,
			.760235
		],
		[
			.361098,
			.864726,
			.457934,
			.909567,
			.537806,
			.925082
		],
		[
			.559245,
			.929246,
			.567625,
			.89793,
			.542066,
			.845567
		],
		[
			.484073,
			.726757,
			.477103,
			.393693,
			.551651,
			.393693
		],
		[.899894, .393693],
		[
			.99148,
			.393693,
			.958466,
			.746659,
			.907348,
			.779629
		],
		[.879394, .797658],
		[
			.845545,
			.819489,
			.848775,
			.954172,
			.874334,
			.971627
		],
		[
			.915883,
			1,
			1,
			1
		]
	],
	hardEdge: [
		[
			0,
			.092437,
			.042353,
			.305322
		],
		[.170124, .947558],
		[
			.177203,
			.983142,
			.2,
			1,
			.268235,
			.998599
		],
		[.614118, .998599],
		[
			.647059,
			.998599,
			.656471,
			.987395,
			.663529,
			.969188
		],
		[
			.687006,
			.908633,
			.802353,
			.822129,
			1,
			.822129
		]
	],
	relaxedInset: [[
		0,
		.367,
		.124605,
		.82,
		.507899,
		1
	], [
		.724115,
		.737,
		.790455,
		.64,
		1,
		.64
	]],
	riblet: [
		[
			0,
			.238519,
			.132047,
			.500741,
			.357567,
			.731852
		],
		[
			.513167,
			.891311,
			.563798,
			.912593,
			.735905,
			.912593
		],
		[.873887, .912593],
		[
			.956973,
			.912593,
			.878338,
			1,
			1,
			1
		]
	],
	slope: [
		[
			0,
			.125,
			.025,
			.25
		],
		[.125, .75],
		[
			.175,
			1,
			.25,
			1,
			.375,
			1
		],
		[.5, 1],
		[
			.625,
			1,
			.7,
			1,
			.75,
			.75
		],
		[.875, .125],
		[
			.9,
			.01,
			.98,
			.01,
			1,
			.01
		]
	],
	softRound: [[
		0,
		.4775,
		.0968736,
		1,
		.156301,
		1
	], [
		.264179,
		1,
		.376919,
		.33333,
		1,
		.33333
	]]
}, kn = 1024;
function An(e) {
	let t = new Float64Array(1025).fill(-Infinity), n = new Float64Array(1025), r = [0], i = [0], a = 0, o = 0;
	for (let t of e) {
		let e = t.length / 2;
		for (let n = 1; n <= 256; n++) {
			let s = n / 256, c = 1 - s;
			e === 1 ? (r.push(c * a + s * t[0]), i.push(c * o + s * t[1])) : e === 2 ? (r.push(c * c * a + 2 * c * s * t[0] + s * s * t[2]), i.push(c * c * o + 2 * c * s * t[1] + s * s * t[3])) : (r.push(c * c * c * a + 3 * c * c * s * t[0] + 3 * c * s * s * t[2] + s * s * s * t[4]), i.push(c * c * c * o + 3 * c * c * s * t[1] + 3 * c * s * s * t[3] + s * s * s * t[5]));
		}
		a = t[t.length - 2], o = t[t.length - 1];
	}
	let s = Math.max(...i);
	for (let e = 1; e < r.length; e++) {
		let a = r[e - 1], o = r[e], c = i[e - 1] / s, l = o - a, u = i[e] / s - c;
		if (l === 0) continue;
		let d = Math.max(0, Math.ceil(Math.min(a, o) * kn)), f = Math.min(kn, Math.floor(Math.max(a, o) * kn));
		for (let e = d; e <= f; e++) {
			let r = c + (e / kn - a) * u / l;
			r > t[e] && (t[e] = r, n[e] = u / l);
		}
	}
	for (let e = 0; e <= kn; e++) e / kn >= a && (t[e] = o / s, n[e] = 0);
	return {
		height: t,
		slope: n
	};
}
var jn = /* @__PURE__ */ new Map();
function Mn(e) {
	let t = Object.hasOwn(On, e) ? e : "circle", n = jn.get(t);
	return n === void 0 && (n = An(On[t]), jn.set(t, n)), n;
}
function Nn(e, t) {
	let n = Math.max(0, Math.min(1, t)) * kn, r = Math.min(kn - 1, Math.floor(n));
	return e[r] + (e[r + 1] - e[r]) * (n - r);
}
function Pn(e, t) {
	return Nn(Mn(e).slope, t);
}
function Fn(e, t, n, r, i, a, o, s) {
	let c = e.slice(), l = new Int8Array(r * i);
	for (let t = 0; t < i; t++) for (let a = 0; a < r; a++) {
		let o = t * r + a, s = (a + 1 < r ? n[o + 1] : 0) - (a > 0 ? n[o - 1] : 0), c = (t + 1 < i ? n[o + r] : 0) - (t > 0 ? n[o - r] : 0);
		l[o] = Math.floor((Math.atan2(c, s) + Math.PI) * 16 / Math.PI) % 32, e[o * 4 + 3] = 0;
	}
	let u = (t, n, a, o) => {
		if (t < 0 || t >= r || n < 0 || n >= i) return;
		let s = (n * r + t) * 4, l = a * 4, u = c[l + 3] * Math.min(1, Math.max(0, o));
		u <= e[s + 3] || (e[s] = c[l], e[s + 1] = c[l + 1], e[s + 2] = c[l + 2], e[s + 3] = u);
	}, d = s && Number.isFinite(s.x) ? s.x : 0, f = s && Number.isFinite(s.y) ? s.y : 0, p = s ? new Uint8Array(r * i) : void 0;
	for (let e = 0; e < i; e++) for (let c = 0; c < r; c++) {
		let m = e * r + c;
		if (t[m] === 0) continue;
		let h = Math.max(0, n[m] - .5), g = Math.max(0, Math.min(1, o + .5 - h)), _ = a > o ? Math.max(0, o / 2 + .5 - Math.abs(h - a)) : 0, v = c + 1 < r && t[m + 1] > 0 && l[m] !== l[m + 1] || e + 1 < i && t[m + r] > 0 && l[m] !== l[m + r], y = Math.max(g, _, a > 0 && h <= a && v ? 1 : 0);
		y > 0 && (u(c, e, m, y), s && u(Math.round(c + d), Math.round(e + f), m, y)), p && g > 0 && v && (p[m] = 1);
	}
	if (!p || d === 0 && f === 0) return;
	let m = Math.abs(d) >= Math.abs(f), h = m ? r : i, g = m ? i : r, _ = m ? d : f, v = (m ? f : d) / _, y = _ > 0 ? 1 : -1, b = new Int32Array(g + 2 * h + 1).fill(-1), x = new Float64Array(b.length);
	for (let e = y > 0 ? 0 : h - 1; e >= 0 && e < h; e += y) {
		let t = Math.round(v * e);
		for (let n = 0; n < g; n++) {
			let i = m ? e : n, a = m ? n : e, o = a * r + i, s = n - t + h;
			p[o] && (b[s] = o, x[s] = y * e + Math.abs(_)), b[s] >= 0 && y * e <= x[s] && u(i, a, b[s], 1);
		}
	}
}
var In = {
	threePt: {
		ambient: 0,
		lights: [
			{
				rgb: [
					1.141,
					1.141,
					1.141
				],
				direction: [
					-.6515,
					-.2693,
					-.7093
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					.5,
					.5,
					.5
				],
				direction: [
					.8482,
					.2469,
					-.4686
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					1,
					1,
					1
				],
				direction: [
					.5634,
					-.2812,
					.7769
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			}
		]
	},
	balanced: {
		ambient: .13,
		lights: [
			{
				rgb: [
					1.05,
					1.05,
					1.05
				],
				direction: [
					.5263,
					-.4092,
					-.7453
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					1,
					1,
					1
				],
				direction: [
					-.9386,
					.3426,
					-.041
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					.5,
					.5,
					.5
				],
				direction: [
					.0934,
					.763,
					.6396
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			}
		]
	},
	soft: {
		ambient: .3,
		lights: [{
			rgb: [
				.8,
				.8,
				.8
			],
			direction: [
				-.6897,
				.2484,
				-.6802
			],
			scale: .5,
			offset: .5,
			specular: !0,
			diffuse: !0
		}]
	},
	harsh: {
		ambient: .28,
		lights: [{
			rgb: [
				.88,
				.88,
				.88
			],
			direction: [
				.6689,
				-.6755,
				-.3104
			],
			scale: 1,
			offset: 0,
			specular: !0,
			diffuse: !0
		}, {
			rgb: [
				.88,
				.88,
				.88
			],
			direction: [
				-.592,
				-.7371,
				-.326
			],
			scale: 1,
			offset: 0,
			specular: !0,
			diffuse: !0
		}]
	},
	flood: {
		ambient: .13,
		lights: [
			{
				rgb: [
					1.1,
					1.1,
					1.1
				],
				direction: [
					.5685,
					-.7651,
					-.3022
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					1.1,
					1.1,
					1.1
				],
				direction: [
					-.2366,
					-.9595,
					-.1531
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					.55,
					.55,
					.55
				],
				direction: [
					-.8982,
					.1386,
					-.4171
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			}
		]
	},
	contrasting: {
		ambient: 1,
		lights: [{
			rgb: [
				1,
				1,
				1
			],
			direction: [
				0,
				-1,
				0
			],
			scale: 1,
			offset: 0,
			specular: !0,
			diffuse: !1
		}, {
			rgb: [
				1,
				1,
				1
			],
			direction: [
				0,
				1,
				0
			],
			scale: 1,
			offset: 0,
			specular: !0,
			diffuse: !1
		}]
	},
	morning: {
		ambient: 0,
		lights: [
			{
				rgb: [
					.669,
					.648,
					.596
				],
				direction: [
					.6574,
					-.7316,
					-.1806
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					.459,
					.454,
					.385
				],
				direction: [
					-.2781,
					-.4509,
					-.8482
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					.9,
					.86,
					.83
				],
				direction: [
					.672,
					-.6185,
					-.4073
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					.911,
					.846,
					.728
				],
				direction: [
					-.1825,
					.968,
					.1722
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			}
		]
	},
	sunrise: {
		ambient: 0,
		lights: [
			{
				rgb: [
					.667,
					.63,
					.527
				],
				direction: [
					.6574,
					-.7316,
					-.1806
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					.459,
					.459,
					.371
				],
				direction: [
					-.2781,
					-.4509,
					-.8482
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					.826,
					.712,
					.638
				],
				direction: [
					.672,
					-.6185,
					-.4073
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					1.511,
					1.319,
					.994
				],
				direction: [
					-.1825,
					.968,
					.1722
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			}
		]
	},
	sunset: {
		ambient: 0,
		lights: [
			{
				rgb: [
					.672,
					.169,
					.169
				],
				direction: [
					.6574,
					-.7316,
					-.1806
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					.459,
					.448,
					.327
				],
				direction: [
					.0922,
					-.3551,
					-.9303
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					.775,
					.612,
					.502
				],
				direction: [
					.672,
					-.6185,
					-.4073
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					.761,
					.69,
					.397
				],
				direction: [
					-.424,
					.8891,
					.1722
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			}
		]
	},
	chilly: {
		ambient: .11,
		lights: [
			{
				rgb: [
					.31,
					.32,
					.32
				],
				direction: [
					.6574,
					-.7316,
					-.1806
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					.45,
					.45,
					.45
				],
				direction: [
					-.3539,
					-.1505,
					-.9231
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					1.03,
					1.02,
					1.15
				],
				direction: [
					.672,
					-.6185,
					-.4073
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					.41,
					.45,
					.48
				],
				direction: [
					-.5781,
					.7976,
					.1722
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			}
		]
	},
	freezing: {
		ambient: 0,
		lights: [
			{
				rgb: [
					.53,
					.567,
					.661
				],
				direction: [
					.6574,
					-.7316,
					-.1806
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			},
			{
				rgb: [
					.37,
					.461,
					.461
				],
				direction: [
					-.2781,
					-.4509,
					-.8482
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					.649,
					.638,
					.904
				],
				direction: [
					.672,
					-.6185,
					-.4073
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					.971,
					1.19,
					1.363
				],
				direction: [
					-.1825,
					.968,
					.1722
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !0
			}
		]
	},
	flat: {
		ambient: 1,
		lights: [
			{
				rgb: [
					.821,
					.821,
					.821
				],
				direction: [
					-.9546,
					-.1619,
					-.2502
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !1
			},
			{
				rgb: [
					2.072,
					2.54,
					2.91
				],
				direction: [
					9e-4,
					.8605,
					.5095
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !1
			},
			{
				rgb: [
					3.843,
					3.843,
					3.843
				],
				direction: [
					.6574,
					-.7316,
					-.1806
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !1
			}
		]
	},
	twoPt: {
		ambient: .25,
		lights: [{
			rgb: [
				.84,
				.84,
				.84
			],
			direction: [
				.5266,
				-.4089,
				-.7454
			],
			scale: 1,
			offset: 0,
			specular: !0,
			diffuse: !0
		}, {
			rgb: [
				.3,
				.3,
				.3
			],
			direction: [
				-.8983,
				.2365,
				-.3704
			],
			scale: 1,
			offset: 0,
			specular: !0,
			diffuse: !0
		}]
	},
	glow: {
		ambient: 1,
		lights: [{
			rgb: [
				1,
				1,
				1
			],
			direction: [
				0,
				-1,
				0
			],
			scale: 1,
			offset: 0,
			specular: !0,
			diffuse: !0
		}, {
			rgb: [
				.7,
				.7,
				.7
			],
			direction: [
				0,
				1,
				0
			],
			scale: 1,
			offset: 0,
			specular: !0,
			diffuse: !0
		}]
	},
	brightRoom: {
		ambient: 1.5,
		lights: [
			{
				rgb: [
					1,
					1,
					1
				],
				direction: [
					0,
					-1,
					0
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					1,
					1,
					1
				],
				direction: [
					.8227,
					-.1882,
					-.5364
				],
				scale: 1,
				offset: 0,
				specular: !0,
				diffuse: !1
			},
			{
				rgb: [
					-.5,
					-.5,
					-.5
				],
				direction: [
					0,
					0,
					-1
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			},
			{
				rgb: [
					.5,
					.5,
					.5
				],
				direction: [
					0,
					1,
					0
				],
				scale: 1,
				offset: 0,
				specular: !1,
				diffuse: !0
			}
		]
	}
}, Ln = {};
for (let [e, t, n, r, i, a] of [
	[
		"Flat",
		.58,
		.58,
		.305,
		.5,
		.5
	],
	[
		"Normal",
		.671,
		.366,
		.153,
		.5,
		.5
	],
	[
		"Harsh",
		.793,
		.214,
		.061,
		1,
		0
	]
]) for (let o = 1; o <= 4; o++) {
	let s = o === 1 ? [
		0,
		0,
		-.2
	] : [
		o - 3,
		-1,
		-.2
	], c = o === 1 ? [
		0,
		0,
		-.2
	] : [
		0,
		1,
		-.2
	];
	Ln[`legacy${e}${o}`] = {
		ambient: r,
		lights: [{
			rgb: [
				t,
				t,
				t
			],
			direction: s,
			scale: 1,
			offset: 0,
			specular: !0,
			diffuse: !0
		}, {
			rgb: [
				n,
				n,
				n
			],
			direction: c,
			scale: i,
			offset: a,
			specular: !1,
			diffuse: !0
		}]
	};
}
function Rn(e) {
	return Object.hasOwn(In, e) ? In[e] : Object.hasOwn(Ln, e) ? Ln[e] : In.threePt;
}
function zn(e, t, n, r, i, a = !1, o = 0, s = 0, c = 1, l = c, u = 0) {
	return {
		specularShape: e,
		specularWhite: t,
		diffuseShape: n,
		diffuseWhite: r,
		power: i,
		blinn: a,
		diffuseFresnel: o,
		alphaFresnel: s,
		diffuseAlpha: c,
		ambientAlpha: l,
		emissive: u
	};
}
var Bn = {
	legacyMatte: zn(0, 0, 1, 0, 0, !0),
	legacyPlastic: zn(0, 1, 1, 0, 32, !0),
	legacyMetal: zn(1, 0, 2 / 3, 0, 32, !0),
	legacyWireframe: zn(0, 0, 0, 0, 0),
	matte: zn(0, 0, 1, 0, 0),
	plastic: zn(0, .6, 1, 0, 12),
	metal: zn(-.32, 1.32, 1, 0, 12, !1, 4),
	warmMatte: zn(0, .3, 1, 0, 8),
	translucentPowder: zn(0, .3, 1, 0, 10, !0, 2, -1, .7),
	powder: zn(0, .3, .5, .5, 10, !0, 2),
	dkEdge: zn(0, 1, 1, 0, 35, !1, -2),
	softEdge: zn(0, 1, 1, 0, 35, !1, 4, -10),
	clear: zn(0, .6, 1, 0, 20, !1, -8, 1, .1, 1),
	flat: zn(0, .8, 0, 0, 50, !1, -4, 0, 1, 1, 1),
	softmetal: zn(.5, .5, 1, 0, 8)
};
function Vn(e) {
	return e && Object.hasOwn(Bn, e) ? e : "matte";
}
var Hn = {
	t: 0,
	tr: 45,
	r: 90,
	br: 135,
	b: 180,
	bl: 225,
	l: 270,
	tl: 315
};
function Un(e, t, n) {
	let r = Rn(e), i = Math.PI / 180, a = (n?.lat ?? 0) * i, o = (n?.lon ?? 0) * i, s = (n ? n.rev : Object.hasOwn(Hn, t) ? Hn[t] : 0) * i, c = Math.cos(a), l = Math.sin(a), u = Math.cos(o), d = Math.sin(o), f = Math.cos(s), p = Math.sin(s), m = r.lights.map((e) => {
		let [t, n, r] = e.direction, i = Math.hypot(t, n, r) || 1, a = n / i, o = t / i, s = -r / i, m = f * a - p * o, h = p * a + f * o, g = c * h - l * s, _ = l * h + c * s, v = u * m + d * _, y = -d * m + u * _, b = Math.hypot(v, g, y + 1) || 1;
		return {
			x: v,
			y: g,
			z: y,
			hx: v / b,
			hy: g / b,
			hz: (y + 1) / b,
			rgb: e.rgb,
			scale: e.scale,
			offset: e.offset,
			specular: e.specular,
			diffuse: e.diffuse
		};
	});
	return {
		x: m[0].x,
		y: m[0].y,
		z: m[0].z,
		rigLights: m,
		rigAmbient: r.ambient
	};
}
function Wn(e, t) {
	let n = t, r = Math.hypot(t.x, t.y, t.z + 1) || 1, i = {
		material: Bn[e],
		ambient: n.rigAmbient ?? 0,
		lights: n.rigLights ?? [{
			...t,
			hx: t.x / r,
			hy: t.y / r,
			hz: (t.z + 1) / r,
			rgb: [
				1,
				1,
				1
			],
			scale: 1,
			offset: 0,
			specular: !0,
			diffuse: !0
		}],
		terms: new Float64Array(6),
		flatTerms: new Float64Array(6)
	};
	return Gn(i.flatTerms, 0, 0, 1, i), i;
}
function Gn(e, t, n, r, i) {
	let a = i.material, o = 0, s = 0, c = 0, l = 0, u = 0, d = 0;
	for (let e of i.lights) {
		let i = t * e.x + n * e.y + r * e.z;
		if (e.diffuse) {
			let t = Math.max(0, i * e.scale + e.offset);
			o += e.rgb[0] * t, s += e.rgb[1] * t, c += e.rgb[2] * t;
		}
		if (e.specular && i > 0 && (a.specularShape !== 0 || a.specularWhite !== 0)) {
			let o = a.blinn ? t * e.hx + n * e.hy + r * e.hz : 2 * i * r - e.z, s = Math.max(0, o) ** +a.power;
			l += e.rgb[0] * s, u += e.rgb[1] * s, d += e.rgb[2] * s;
		}
	}
	e[0] = o, e[1] = s, e[2] = c, e[3] = l, e[4] = u, e[5] = d;
}
function Kn(e, t, n, r, i, a, o, s, c) {
	let l = c.material, u = 1 - Math.min(1, Math.abs(s)), d = l.diffuseFresnel === 0 ? 0 : u ** +Math.abs(l.diffuseFresnel), f = l.alphaFresnel === 0 ? 0 : u ** +Math.abs(l.alphaFresnel), p = l.diffuseAlpha;
	l.alphaFresnel > 0 ? p += (1 - p) * f : l.alphaFresnel < 0 && (p *= 1 - f);
	let m = a === 0 && o === 0 && s === 1 ? c.flatTerms : c.terms;
	m === c.terms && Gn(m, a, o, s, c);
	for (let i = 0; i < 3; i++) {
		let a = (i === 0 ? t : i === 1 ? n : r) / 255, o = a * l.diffuseShape + l.diffuseWhite;
		o = l.diffuseFresnel > 0 ? o + (1 - o) * d : o * (1 - d);
		let s = a * l.specularShape + l.specularWhite, u = a * l.emissive + (a * l.diffuseShape + l.diffuseWhite) * c.ambient + o * m[i] + s * m[i + 3];
		e[i] = Math.max(0, Math.min(255, u * 255));
	}
	e[3] = i * Math.max(0, Math.min(1, p));
}
function qn(e) {
	let t = e.length, n = new Float64Array(t);
	if (t === 0) return n;
	let r = new Int32Array(t), i = new Float64Array(t + 1), a = 0;
	r[0] = 0, i[0] = -Infinity, i[1] = Infinity;
	for (let n = 1; n < t; n++) {
		let t = (e[n] + n * n - (e[r[a]] + r[a] * r[a])) / (2 * n - 2 * r[a]);
		for (; t <= i[a];) a--, t = (e[n] + n * n - (e[r[a]] + r[a] * r[a])) / (2 * n - 2 * r[a]);
		a++, r[a] = n, i[a] = t, i[a + 1] = Infinity;
	}
	a = 0;
	for (let o = 0; o < t; o++) {
		for (; i[a + 1] < o;) a++;
		let t = o - r[a];
		n[o] = t * t + e[r[a]];
	}
	return n;
}
function Jn(e, t = 3) {
	if (e <= 0) return Array(t).fill(1);
	let n = Math.sqrt(12 * e * e / t + 1), r = Math.floor(n);
	r % 2 == 0 && r--;
	let i = r + 2, a = (12 * e * e - t * r * r - 4 * t * r - 3 * t) / (-4 * r - 4), o = Math.round(a), s = [];
	for (let e = 0; e < t; e++) s.push(e < o ? r : i);
	return s;
}
function Yn(e, t, n, r, i, a) {
	let o = 1 / (2 * i + 1);
	if (a) for (let a = 0; a < r; a++) {
		let r = a * n, s = 0;
		for (let t = 0; t <= i; t++) t < n && (s += e[r + t]);
		for (let a = 0; a < n; a++) {
			t[r + a] = s * o;
			let c = a + i + 1, l = a - i;
			c < n && (s += e[r + c]), l >= 0 && (s -= e[r + l]);
		}
	}
	else for (let a = 0; a < n; a++) {
		let s = 0;
		for (let t = 0; t <= i; t++) t < r && (s += e[t * n + a]);
		for (let c = 0; c < r; c++) {
			t[c * n + a] = s * o;
			let l = c + i + 1, u = c - i;
			l < r && (s += e[l * n + a]), u >= 0 && (s -= e[u * n + a]);
		}
	}
}
function Xn(e, t, n, r) {
	let i = Float64Array.from(e);
	if (r <= 0 || t <= 0 || n <= 0) return i;
	let a = new Float64Array(t * n);
	for (let e of Jn(r, 3)) {
		let r = Math.max(1, (e - 1) / 2);
		Yn(i, a, t, n, r, !0), Yn(a, i, t, n, r, !1);
	}
	return i;
}
function Zn(e, t, n, r = 128) {
	let i = new Float64Array(t * n);
	for (let a = 0; a < t * n; a++) i[a] = (e[a] ?? 0) >= r ? 0x56bc75e2d63100000 : 0;
	let a = new Float64Array(n);
	for (let e = 0; e < t; e++) {
		for (let r = 0; r < n; r++) a[r] = i[r * t + e];
		let r = qn(a);
		for (let a = 0; a < n; a++) i[a * t + e] = r[a];
	}
	let o = new Float64Array(t);
	for (let e = 0; e < n; e++) {
		for (let n = 0; n < t; n++) o[n] = i[e * t + n];
		let n = qn(o);
		for (let r = 0; r < t; r++) i[e * t + r] = n[r];
	}
	for (let e = 0; e < n; e++) for (let r = 0; r < t; r++) {
		let a = e * t + r;
		if (i[a] === 0) continue;
		let o = (e + 1) * (e + 1), s = (n - e) * (n - e), c = (r + 1) * (r + 1), l = (t - r) * (t - r), u = Math.min(o, s, c, l);
		u < i[a] && (i[a] = u);
	}
	for (let e = 0; e < t * n; e++) i[e] = Math.sqrt(i[e]);
	return i;
}
function Qn(e, t, n, r, i, a) {
	let o = new Float32Array(t * n * 3), s = new Uint8Array(t * n), c = new Float32Array(t * n);
	if (t <= 0 || n <= 0) return {
		normals: o,
		bandMask: s,
		bandWeight: c
	};
	let l = Zn(e, t, n, 1), u = r > 0 ? Xn(l, t, n, Math.max(1, r * .25)) : l;
	for (let d = 0; d < n; d++) for (let f = 0; f < t; f++) {
		let p = d * t + f;
		if (o[p * 3 + 2] = 1, (e[p] ?? 0) === 0 || r <= 0 || a <= 0) continue;
		let m = Math.max(0, l[p] - .5);
		if (m >= r) continue;
		s[p] = 1, c[p] = 1;
		let h = Math.max(0, f - 1), g = Math.min(t - 1, f + 1), _ = Math.max(0, d - 1), v = Math.min(n - 1, d + 1), y = (u[d * t + g] - u[d * t + h]) / (g - h || 1), b = (u[v * t + f] - u[_ * t + f]) / (v - _ || 1), x = Math.hypot(y, b), S = Pn(i, m / r) * a / r, C = x > 1e-9 ? -S * y / x : 0, w = x > 1e-9 ? -S * b / x : 0, T = Math.hypot(C, w, 1);
		o[p * 3] = C / T, o[p * 3 + 1] = w / T, o[p * 3 + 2] = 1 / T;
	}
	return {
		normals: o,
		bandMask: s,
		bandWeight: c
	};
}
function $n(e, t, n) {
	return Un(e, t, n);
}
function er(e) {
	return Vn(e);
}
function tr(e, t, n) {
	if (!e) return {
		x: 0,
		y: 0,
		w: t,
		h: n
	};
	let r = Math.max(0, Math.floor(e.x)), i = Math.max(0, Math.floor(e.y)), a = Math.min(t, Math.ceil(e.x + e.w)), o = Math.min(n, Math.ceil(e.y + e.h));
	return {
		x: r,
		y: i,
		w: Math.max(0, a - r),
		h: Math.max(0, o - i)
	};
}
function nr(e, t, n) {
	if (e.canvas.width <= 0 || e.canvas.height <= 0) return;
	let r = t.camera ? En(t.camera) : void 0, i = r?.backFacing ?? !1;
	if (!!t.bottom !== i) return;
	let { x: a, y: o, w: s, h: c } = tr(n, e.canvas.width, e.canvas.height);
	if (s <= 0 || c <= 0) return;
	let l = e.getImageData(a, o, s, c), u = l.data, d = new Uint8ClampedArray(s * c);
	for (let e = 0; e < s * c; e++) d[e] = u[e * 4 + 3] > 0 ? 255 : 0;
	if (t.material === "legacyWireframe") {
		Fn(u, d, Zn(d, s, c), s, c, t.widthPx, t.wireframeLinePx ?? 1, t.wireframeDepth), e.putImageData(l, a, o);
		return;
	}
	let { normals: f } = Qn(d, s, c, t.widthPx, t.prst, t.heightPx), p = Wn(t.material, t.light), m = new Float64Array(4);
	for (let e = 0; e < s * c; e++) {
		let n = e * 4;
		if (u[n + 3] === 0) continue;
		let i = f[e * 3], a = f[e * 3 + 1], o = f[e * 3 + 2];
		if (t.bottom && r) {
			let e = r.rotation, t = e[0] * i + e[1] * a - e[2] * o, n = e[3] * i + e[4] * a - e[5] * o;
			o = e[6] * i + e[7] * a - e[8] * o, i = t, a = n;
		}
		Kn(m, u[n], u[n + 1], u[n + 2], u[n + 3], i, a, o, p), u[n] = m[0], u[n + 1] = m[1], u[n + 2] = m[2], u[n + 3] = m[3];
	}
	e.putImageData(l, a, o);
}
function rr(e, t, n) {
	let r = e.canvas.width, i = e.canvas.height;
	if (r <= 0 || i <= 0) return;
	let a = t.offsetX, o = t.offsetY, s = Math.hypot(a, o);
	if (s < .75) return;
	let { x: c, y: l, w: u, h: d } = tr(n, r, i);
	if (u <= 0 || d <= 0) return;
	let f = e.getImageData(c, l, u, d), p = f.data, m = new Uint8ClampedArray(u * d);
	for (let e = 0; e < u * d; e++) m[e] = p[e * 4 + 3];
	let h = Math.max(1, Math.ceil(s)), [g, _, v] = t.rgb;
	for (let e = 0; e < d; e++) for (let t = 0; t < u; t++) {
		let n = e * u + t;
		if (m[n] > 0) continue;
		let r = !1;
		for (let n = 1; n <= h; n++) {
			let i = n / h, s = Math.round(t - a * i), c = Math.round(e - o * i);
			if (!(s < 0 || c < 0 || s >= u || c >= d) && m[c * u + s] > 0) {
				r = !0;
				break;
			}
		}
		if (!r) continue;
		let i = n * 4;
		p[i] = g, p[i + 1] = _, p[i + 2] = v, p[i + 3] = 255;
	}
	e.putImageData(f, c, l);
}
function ir(e, t) {
	return {
		top: e - t * .85,
		height: t * 1.1
	};
}
function ar(e, t, n, r, i = 0) {
	let a = [], o = 0, s = 0;
	for (let c of t) a.push({
		text: e.slice(o, c).join(""),
		dx: r(e.slice(0, o).join("")) + o * i + s * n
	}), o = c, s++;
	return a.push({
		text: e.slice(o).join(""),
		dx: r(e.slice(0, o).join("")) + o * i + s * n
	}), a;
}
var or = {
	palatino: "Palatino Linotype",
	helvetica: "Arial",
	"helvetica neue": "Arial"
}, sr = /* @__PURE__ */ new Map();
function cr(e, t, n) {
	let r = `${e.trim().toLocaleLowerCase("en-US")}|${t ? 700 : 400}|${n ? "i" : "n"}`, i = sr.get(r);
	if (i !== void 0) return sr.delete(r), sr.set(r, i), i ?? void 0;
	let a = dr(e, t, n);
	if (sr.set(r, a ?? null), sr.size > 256) {
		let e = sr.keys().next().value;
		e !== void 0 && sr.delete(e);
	}
	return a;
}
function lr(e, t, r) {
	let i = t ? 700 : 400, a = n(or, e.trim().toLocaleLowerCase("en-US")) ?? e.trim();
	if (!a) return i;
	let o = r ? "italic" : "normal";
	if (w(a, {
		weight: i,
		style: o
	}).length > 0) return i;
	let s = w(a, { style: o });
	return s.length > 0 && s.every((e) => e.weight === s[0].weight) ? s[0].weight : i;
}
function ur(e, t, r) {
	let i = e.trim();
	if (!i) return [];
	let a = n(or, i.toLocaleLowerCase("en-US")) ?? i, o = r ? "italic" : "normal", s = w(a, {
		weight: t ? 700 : 400,
		style: o
	});
	if (s.length === 0) {
		let e = w(a, { style: o });
		e.length > 0 && e.every((t) => t.weight === e[0].weight) && (s = e);
	}
	let c = s.filter((e) => e.source === "macos-supplemental");
	return c.length > 0 ? c : s.filter((e) => e.source === "office-mac");
}
function dr(e, t, n) {
	let r = ur(e, t, n);
	if (r.length === 0) return;
	let i;
	for (let e of r) {
		let t;
		if (e.typoMetrics) {
			let [n, r, i] = e.typoMetrics, a = n + Math.max(0, i);
			t = a / (a - r);
		} else if (e.win) {
			let [n, r] = e.win;
			t = n / (n + r);
		}
		if (t === void 0 || !Number.isFinite(t) || t <= 0 || t >= 1 || i !== void 0 && Math.abs(i - t) > 1e-12) return;
		i = t;
	}
	return i;
}
function fr(e, t, n) {
	let r = ur(e, t, n);
	if (r.length === 0) return;
	let i;
	for (let e of r) {
		let t;
		if (e.typoMetrics) {
			let [n, r, i] = e.typoMetrics;
			t = {
				ascent: (n + Math.max(0, i)) / e.unitsPerEm,
				descent: -r / e.unitsPerEm
			};
		} else if (e.win) {
			let [n, r] = e.win;
			t = {
				ascent: n / e.unitsPerEm,
				descent: r / e.unitsPerEm
			};
		}
		if (t === void 0 || !(e.unitsPerEm > 0)) return;
		let n = t.ascent / (t.ascent + t.descent);
		if (!Number.isFinite(n) || n <= 0 || n >= 1 || i && (Math.abs(i.ascent - t.ascent) > 1e-12 || Math.abs(i.descent - t.descent) > 1e-12)) return;
		i = t;
	}
	return i;
}
function pr(e, t, n) {
	let r;
	for (let i of ur(e, t, n)) {
		if (i.farEastCodePage == null || !i.win) return;
		let e = Qe({
			faceSource: i.source === "macos-supplemental" ? "system" : "office-bundle",
			unitsPerEm: i.unitsPerEm,
			hhea: i.hhea,
			win: i.win,
			typoMetrics: i.typoMetrics,
			farEastCodePage: i.farEastCodePage
		});
		if (!e || r && (r.ascent !== e.ascentRatio || r.descent !== e.descentRatio)) return;
		r = {
			ascent: e.ascentRatio,
			descent: e.descentRatio
		};
	}
	return r;
}
function mr(e, t, n) {
	let r;
	for (let i of ur(e, t, n)) {
		let e = i.decoration;
		if (!e || !(i.unitsPerEm > 0)) return;
		let [t, n, a, o] = e;
		if (!(n > 0) || !(o > 0) || !(a > 0)) return;
		let s = i.unitsPerEm, c = {
			underlineTopEm: -t / s,
			underlineThicknessEm: n / s,
			strikeTopEm: -a / s,
			strikeThicknessEm: o / s
		};
		if (r && Object.keys(c).some((e) => Math.abs(r[e] - c[e]) > 1e-12)) return;
		r = Object.freeze(c);
	}
	return r;
}
var hr = /* @__PURE__ */ new Map();
function gr(e, t, n) {
	let r = `${e.trim().toLocaleLowerCase("en-US")}|${t ? 700 : 400}|${n ? "i" : "n"}`, i = hr.get(r);
	if (i !== void 0) return hr.delete(r), hr.set(r, i), i ?? void 0;
	let a = cr(e, t, n), o = a === void 0 ? null : Object.freeze({
		share: a,
		glyph: fr(e, t, n),
		excel: pr(e, t, n),
		decoration: mr(e, t, n)
	});
	if (hr.set(r, o), hr.size > 256) {
		let e = hr.keys().next().value;
		e !== void 0 && hr.delete(e);
	}
	return o ?? void 0;
}
function _r(e) {
	let t = 0, n = 0, r = 0;
	for (let i of e) t = Math.max(t, i.sizePx), n = Math.max(n, 1.2 * i.sizePx * i.share), r = Math.max(r, 1.2 * i.sizePx * (1 - i.share));
	let i = 1.2 * t;
	if (!(n + r > 0)) return {
		ascent: i * gt,
		descent: i * _t
	};
	let a = i * n / (n + r);
	return {
		ascent: a,
		descent: i - a
	};
}
function vr(e) {
	return Math.floor(e + .5);
}
function yr(e) {
	let t = 0, n = 0;
	for (let r of e) t = Math.max(t, r.box.ascent * r.sizePx), n = Math.max(n, r.box.descent * r.sizePx);
	return {
		ascent: t,
		descent: n
	};
}
var br = .72;
function xr(e, t) {
	return t ? e.face.excel : e.face.glyph;
}
function Sr(e, t, n, r) {
	if (t.length === 0) return;
	let i = [];
	for (let e of t) {
		let t = xr(e, n);
		if (!t) return;
		i.push({
			ascent: t.ascent * e.sizePx,
			descent: t.descent * e.sizePx
		});
	}
	if (!n) {
		let n = 0;
		for (let e of t) n = Math.max(n, e.sizePx);
		let i = 1.2 * n;
		if (e === "t") return {
			ascent: 0,
			descent: i
		};
		if (e === "ctr") return {
			ascent: i / 2,
			descent: i / 2
		};
		let a = _r(t.map((e) => ({
			sizePx: e.sizePx,
			share: e.face.share
		}))), o = (i - Math.round(a.ascent / r) * r) / 2;
		return {
			ascent: i - o,
			descent: o
		};
	}
	if (e === "b") {
		let e = 0, t = 0;
		for (let n of i) e = Math.max(e, n.ascent + n.descent / 2), t = Math.max(t, n.descent / 2);
		return {
			ascent: e,
			descent: t
		};
	}
	let a = 0;
	for (let e of i) a = Math.max(a, e.ascent + e.descent);
	return e === "t" ? {
		ascent: 0,
		descent: a
	} : {
		ascent: a / 2,
		descent: a / 2
	};
}
function Cr(e, t, n, r) {
	let i = xr(t, n);
	if (!i) return;
	let a = i.ascent * t.sizePx, o = i.descent * t.sizePx;
	if (e === "b") return -Math.round(o / 2 / r) * r;
	let s = e === "t" ? a : (a - o) / 2;
	return Math.round(s / r) * r;
}
var wr = {
	...a,
	...oe
};
function* Tr(e) {
	let t = [e[Symbol.iterator]()];
	for (; t.length;) {
		let e = t[t.length - 1].next();
		if (e.done) {
			t.pop();
			continue;
		}
		let n = e.value;
		yield n, (n.type === "shape" || n.type === "table") && n.mathFallback && t.push(n.mathFallback.elements[Symbol.iterator]());
	}
}
function* Er(e) {
	for (let t of e?.paragraphs ?? []) {
		for (let e of t.mathFallbacks ?? []) yield e.text;
		for (let e of t.runs) e.type === "text" && (yield e.text), e.type === "math" && e.fallbackText && (yield e.fallbackText);
	}
}
function* Dr(e) {
	for (let t of e?.paragraphs ?? []) {
		t.defFontFamily && (yield t.defFontFamily);
		for (let e of t.runs) e.type === "text" && (e.fontFamily && (yield e.fontFamily), e.fontFamilyEa && (yield e.fontFamilyEa), e.fontFamilyCs && (yield e.fontFamilyCs), e.fontFamilySym && (yield e.fontFamilySym));
	}
}
function* Or(e) {
	for (let t of Tr(e.elements)) if (t.type === "shape") yield* Er(t.textBody);
	else if (t.type === "table") for (let e of t.rows) for (let t of e.cells) yield* Er(t.textBody);
	else if (t.type === "chart") {
		t.chart.title && (yield t.chart.title);
		for (let e of t.chart.categories) yield e;
		for (let e of t.chart.series) e.name && (yield e.name);
	}
}
var kr = class e {
	majorFont;
	minorFont;
	fallback;
	scriptNames;
	scripts;
	families;
	constructor(e, t, n, r, i, a = /* @__PURE__ */ new Set()) {
		this.majorFont = e, this.minorFont = t, this.fallback = i, this.scriptNames = a;
		let o = M(e) ?? M(t) ?? i ?? null;
		this.scripts = n ?? new k(o), this.families = r ?? /* @__PURE__ */ new Set(), e && this.families.add(e), t && this.families.add(t);
	}
	addSlide(e) {
		e.theme?.majorFont && this.families.add(e.theme.majorFont), e.theme?.minorFont && this.families.add(e.theme.minorFont), this.scripts.addText(Or(e));
		for (let t of I(Or(e), M(e.theme ? e.theme.majorFont : this.majorFont) ?? M(e.theme ? e.theme.minorFont : this.minorFont) ?? this.fallback ?? null)) this.scriptNames.add(t);
		for (let t of Tr(e.elements)) if (t.type === "shape") for (let e of Dr(t.textBody)) this.families.add(e);
		else if (t.type === "table") for (let e of t.rows) for (let t of e.cells) for (let e of Dr(t.textBody)) this.families.add(e);
	}
	names() {
		return [...new Set([
			...this.families,
			...this.scripts.names(),
			...this.scriptNames
		])];
	}
	withSlide(t) {
		let n = new e(this.majorFont, this.minorFont, this.scripts.clone(), new Set(this.families), this.fallback, new Set(this.scriptNames));
		return n.addSlide(t), n;
	}
}, Ar = new Set([
	"serif",
	"sans-serif",
	"monospace",
	"cursive",
	"fantasy",
	"system-ui"
]);
function jr(e, t, n) {
	e.theme && (t = e.theme.majorFont, n = e.theme.minorFont);
	let r = /* @__PURE__ */ new Map(), i = (e) => e ? e.startsWith("+mn-") ? n : e.startsWith("+mj-") ? t : e.split(",")[0]?.trim() ?? null : null, a = (e, t, n) => {
		let a = i(e);
		if (!a || Ar.has(a.toLowerCase())) return;
		let o = lr(a, t, n), s = n ? "italic" : "normal";
		r.set(`${a.toLowerCase()}:${o}:${s}`, {
			family: a,
			weight: o,
			style: s
		}), r.set(`presence:${a.toLowerCase()}`, {
			family: a,
			presenceOnly: !0
		});
	}, o = (e) => {
		for (let t of e?.paragraphs ?? []) {
			for (let n of t.runs) {
				if (n.type === "break") {
					a(n.fontFamily ?? t.defFontFamily, n.bold ?? t.defBold ?? e?.defaultBold ?? !1, n.italic ?? t.defItalic ?? e?.defaultItalic ?? !1);
					continue;
				}
				if (n.type !== "text") continue;
				let r = n.bold ?? t.defBold ?? e?.defaultBold ?? !1, i = n.italic ?? t.defItalic ?? e?.defaultItalic ?? !1;
				a(n.fontFamily ?? t.defFontFamily, r, i), n.fontFamilyEa && a(n.fontFamilyEa, r, i), n.fontFamilyCs && a(n.fontFamilyCs, r, i), n.fontFamilySym && a(n.fontFamilySym, r, i);
			}
			let n = t.endRunProperties;
			if (n && a(n.fontFamily ?? t.defFontFamily, n.bold ?? t.defBold ?? e?.defaultBold ?? !1, n.italic ?? t.defItalic ?? e?.defaultItalic ?? !1), t.bullet.type === "char" || t.bullet.type === "autoNum") {
				let e = t.runs.find((e) => e.type === "text" && !!e.fontFamily), n = e?.type === "text" ? e.fontFamily : null;
				a(t.bullet.fontFamily ?? (t.bullet.type === "autoNum" ? n ?? t.defFontFamily : void 0), !1, !1);
			}
		}
	};
	for (let t of Tr(e.elements)) if (t.type === "shape" && o(t.textBody), t.type === "table") for (let e of t.rows) for (let t of e.cells) o(t.textBody);
	return [...r.values()];
}
function Mr(e, t, n, r) {
	return le(Or(e), M(t) ?? M(n) ?? r);
}
function Nr(e) {
	return e;
}
var Pr = .5;
function Fr(e, t) {
	if (!(t > 0) || !(e.h > 0)) return [{
		y: e.y,
		h: Math.max(0, e.h),
		radius: 0
	}];
	let n = Math.max(4, Math.min(24, Math.ceil(t / Pr) + 1)), r = e.y + e.h, i = [];
	for (let a = 0; a < n; a++) {
		let o = Math.sqrt(a / (n - 1)), s = a === 0 ? 0 : Math.sqrt((a - 1) / (n - 1)), c = a === n - 1 ? 1 : Math.sqrt((a + 1) / (n - 1)), l = a === 0 ? 0 : (s + o) / 2, u = a === n - 1 ? 1 : (o + c) / 2, d = r - u * e.h;
		i.push({
			y: d,
			h: (u - l) * e.h,
			radius: t * a / (n - 1)
		});
	}
	return i;
}
function Ir(e, t, n, r, i) {
	for (let a of Fr(n, r)) e.save(), e.beginPath(), e.rect(0, a.y, i, a.h), e.clip(), e.filter = a.radius > 0 ? `blur(${a.radius}px)` : "none", e.drawImage(t, 0, 0), e.restore();
}
function Lr(e, t) {
	let n = e !== void 0 && e !== "" ? e : void 0, r = t !== void 0 && t !== "" ? t : void 0;
	if (n === void 0 && r === void 0) return;
	if (r !== void 0) return {
		kind: "internal",
		ref: n ?? r
	};
	let i = n, a = Ye(i);
	return a !== null && ze.includes(a) ? {
		kind: "external",
		url: i
	} : {
		kind: "internal",
		ref: i
	};
}
function Rr(e, t, n, r, i, a) {
	let o = Math.max(18, Math.min(32, Math.min(r, i) * .25));
	if (e.save(), e.shadowColor = "rgba(0, 0, 0, 0.3)", e.shadowBlur = o * .35, e.fillStyle = "rgba(20, 20, 20, 0.7)", e.beginPath(), e.arc(t, n, o, 0, Math.PI * 2), e.fill(), e.shadowColor = "transparent", e.shadowBlur = 0, e.fillStyle = "#fff", a === "paused") {
		e.beginPath();
		let r = o * .48;
		e.moveTo(t - r * .4, n - r), e.lineTo(t - r * .4, n + r), e.lineTo(t + r * .75, n), e.closePath(), e.fill();
	} else {
		let r = o * .2, i = o * .8, a = o * .15;
		e.fillRect(t - a - r, n - i / 2, r, i), e.fillRect(t + a, n - i / 2, r, i);
	}
	e.restore();
}
var zr = { isTab: (e) => "isTab" in e }, Br = (e) => Ke(e), Vr = (e, t) => nt(e, t, zr), Hr = (e) => /\s/.test(String.fromCodePoint(e));
function Ur(e, t, n, r, i) {
	if (r === "just" && i) return null;
	let a = t - n;
	if (a <= .5) return null;
	let o = wt(e, a, {
		firstContentSi: 0,
		lastDrawnSi: e.length,
		isGapChar: rt,
		isWhitespace: Hr,
		seaClusterGaps: r === "thaiDist"
	});
	if (!o) return null;
	let { perGap: s, perSeg: c } = o, l = [];
	for (let t = 0; t < e.length; t++) {
		let n = e[t], r = c.get(t), i = r?.trailingGap ? s : 0, a = r?.splitBefore;
		a && a.length > 0 ? l.push({
			...n,
			jext: i,
			splitBefore: [...a],
			perGap: s
		}) : l.push({
			...n,
			jext: i
		});
	}
	return l;
}
function Wr(e) {
	let t = e ? Ut(e.replace(/^#/, "").slice(0, 6)) : null;
	return t ? {
		r: t[0],
		g: t[1],
		b: t[2]
	} : {
		r: 0,
		g: 0,
		b: 0
	};
}
function Gr(e) {
	let t = Wr(e);
	return .299 * t.r + .587 * t.g + .114 * t.b;
}
function Kr(e, t) {
	if (!e && !t) return null;
	if (!e) return t;
	if (!t) return e;
	if (e.width !== t.width) return e.width > t.width ? e : t;
	let n = Gr(e.color), r = Gr(t.color);
	return n === r || n < r ? e : t;
}
function qr(e) {
	let t = Ut(e.length === 8 ? e.slice(0, 6) : e);
	if (!t) return null;
	let n = Wt(t[0], t[1], t[2]);
	if (e.length !== 8) return n;
	let r = Number.parseInt(e.slice(6, 8), 16);
	if (Number.isNaN(r)) return null;
	let i = r / 255;
	return i * n + (1 - i);
}
function Jr(e) {
	if (!e) return null;
	if (e.fillType === "solid") return qr(e.color);
	if (e.fillType === "gradient") {
		let t = e.stops.map((e) => ({
			p: Math.min(1, Math.max(0, e.position)),
			l: qr(e.color)
		})).filter((e) => e.l !== null).sort((e, t) => e.p - t.p);
		if (t.length === 0) return null;
		let n = t[0], r = t[t.length - 1], i = n.l * n.p + r.l * (1 - r.p);
		for (let e = 0; e + 1 < t.length; e++) i += (t[e].l + t[e + 1].l) / 2 * (t[e + 1].p - t[e].p);
		return i;
	}
	return null;
}
function Yr(e) {
	return e.name === "SmartArt" && e.id === void 0;
}
function Xr(e, t) {
	let n = Jr(e);
	if (n === null || n >= .5) return null;
	let r = qr(t.replace(/^#/, ""));
	return r !== null && r >= .5 ? null : "#FFFFFF";
}
function Zr(e, t, n, r, i, a = 0) {
	let o = e.map((e) => e.width), s = (t) => {
		let n = 0;
		for (let r = t; r < e.length && !e[r].isTab; r++) n += o[r];
		return n;
	}, c = n;
	for (let n = 0; n < e.length; n++) {
		if (!e[n].isTab) {
			c += o[n];
			continue;
		}
		let l = null;
		for (let e of t) e.pos > c && (l === null || e.pos < l.pos) && (l = e);
		if (l === null) if (a > 0) l = {
			pos: (Math.floor(c / a) + 1) * a,
			algn: "l"
		};
		else {
			o[n] = i, c += i;
			continue;
		}
		let u = s(n + 1), d = l.algn === "ctr" ? .5 : +(l.algn === "r" || l.algn === "dec"), f = l.pos - u * d;
		f + u > r && (f = r - u), f < c && (f = c), o[n] = f - c, c = f;
	}
	return o;
}
var Qr = () => !1;
function $r(e, t, n, r, i, a, o = "fill", s = Qr) {
	let c = e.textAlign, l = e.textBaseline, u = o === "stroke" ? e.strokeText.bind(e) : e.fillText.bind(e), d = r - xt(e, t, i), f = 0;
	for (let o of t) {
		let t = o.codePointAt(0) ?? 0, l = Ie(t), p = e.measureText(o).width + a, m = l === "Tr" ? Ge(t) : null, h = l === "Tr" && m === null && ft(t), g = l === "U" || l === "Tu" || m !== null || h;
		if (ct(t) && s(t)) {
			let t = n + f + p / 2;
			e.save(), e.translate(t, d), e.rotate(-Math.PI / 2), e.textAlign = "center", e.textBaseline = "middle", $e(e, () => u(o, 0, 0)), e.restore();
		} else if (g) {
			let r = m === null && l === "Tu" ? Ue(t) : null, a = m === null ? r : m, s = a === null ? o : String.fromCodePoint(a), c = n + f + p / 2, h = r === null ? St(e, s) / i : 0;
			e.save(), e.translate(c, d), e.rotate(-Math.PI / 2), e.textAlign = "center", e.textBaseline = "middle", u(s, 0, h * i), e.restore();
		} else if (l === "Tr") {
			let t = n + f + p / 2;
			e.textAlign = "center", e.textBaseline = "middle", u(o, t, d);
		} else e.textAlign = c, e.textBaseline = "alphabetic", u(o, n + f, r);
		f += p;
	}
	e.textAlign = c, e.textBaseline = l;
}
function ei(e, t, n, r, i, a, o = "fill") {
	$r(e, t, n, r, i, a, o, (t) => Ve(e, t));
}
var ti = /* @__PURE__ */ new WeakMap();
function ni(e) {
	let t = 0;
	for (let n of e) t++;
	return t;
}
function ri(e) {
	let t = ti.get(e);
	if (t !== void 0) return t;
	let n = e, r = n.letterSpacing;
	if (typeof r != "string") return ti.set(e, !1), !1;
	let i = !1;
	try {
		n.letterSpacing = "0px";
		let t = e.measureText("ii").width;
		n.letterSpacing = "1px";
		let r = e.measureText("ii").width;
		i = Number.isFinite(t) && Number.isFinite(r) && r !== t;
	} catch {
		i = !1;
	} finally {
		try {
			n.letterSpacing = r;
		} catch {}
	}
	return ti.set(e, i), i;
}
function ii(e, t, n = 0) {
	let r = e, i = r.letterSpacing;
	if (n !== 0 && ri(e)) try {
		r.letterSpacing = `${n}px`;
		let i = e.measureText(t).width;
		if (Number.isFinite(i)) return t.length > 0 ? i - n : i;
	} finally {
		try {
			r.letterSpacing = i;
		} catch {}
	}
	return e.measureText(t).width + n * Math.max(0, ni(t) - 1);
}
function ai(e, t) {
	let n = t.lineMetric?.glyph ?? (t.faceFamily ? on(t.faceFamily, /\bbold\b/.test(t.font), /\bitalic\b/.test(t.font)) : void 0);
	if (n) return n;
	e.font = t.font;
	let r = e.measureText("M"), i = t.sizePx || 1, a = r.fontBoundingBoxAscent, o = r.fontBoundingBoxDescent;
	return Number.isFinite(a) && Number.isFinite(o) && a + o > 0 ? {
		ascent: a / i,
		descent: o / i
	} : {
		ascent: .9,
		descent: .25
	};
}
function oi(e) {
	let t = /* @__PURE__ */ new Map();
	return (n, r, i = !1) => {
		let a = t.get(r);
		a || (a = ai(e, r), t.set(r, a));
		let o = r.faceFamily ?? "", s = a.ascent + a.descent, c = Qt * s * r.sizePx, l = r.faceFamilyLatin ?? o, u = r.lineMetricLatin?.glyph ?? on(l, /\bbold\b/.test(r.font), /\bitalic\b/.test(r.font)), d = l === o ? s : u ? u.ascent + u.descent : s, f = Math.max(c, Qt * d * r.sizePx), p = r.letterSpacingPx ?? 0;
		return tn(n, {
			family: o,
			bold: /\bbold\b/.test(r.font),
			italic: /\bitalic\b/.test(r.font)
		}, {
			upright: c,
			verticalGlyph: r.sizePx,
			rotated: (t) => (e.font = r.font, e.measureText(t).width)
		}, i).map(({ text: e, kind: t, advance: n, nominal: i }) => ({
			text: e,
			style: r,
			kind: t,
			advance: n + p,
			thickness: f,
			space: e === " ",
			box: a,
			cell: c,
			...i ? { nominal: i } : {}
		}));
	};
}
function si(e, t) {
	let n = oi(e), r = t.paragraphs.map((e) => e.marker && "text" in e.marker ? n(e.marker.text, e.marker.style, !0) : []), i = Jt(t.paragraphs.map((e, t) => ({
		runs: e.runs.map((e) => e.type === "object" && e.style.math ? {
			...e,
			width: e.style.math.ascent + e.style.math.descent
		} : e),
		alignment: e.alignment,
		marginStart: e.marginStart,
		marginEnd: e.marginEnd,
		firstLineIndent: e.firstLineIndent,
		firstLinePrefix: e.marker && ("text" in e.marker ? {
			advance: r[t].reduce((e, t) => e + t.advance, 0),
			thickness: r[t].reduce((e, t) => Math.max(e, t.thickness), 0)
		} : e.marker),
		lineSpacing: e.lineSpacing,
		spaceBefore: e.spaceBefore,
		spaceAfter: e.spaceAfter,
		emptyThickness: n("M", e.markStyle)[0].thickness,
		eastAsianLineBreak: e.eastAsianLineBreak
	})), {
		direction: t.vert,
		rect: t.rect,
		anchor: t.anchor,
		anchorCtr: t.anchorCtr,
		wrap: t.wrap,
		spcFirstLastPara: t.spcFirstLastPara,
		lnSpcReduction: t.lnSpcReduction,
		pxPerPt: t.pxPerPt,
		glyphs: n,
		sameStyle: t.sameStyle,
		objectGlyph: ({ style: e }) => {
			if (!e.math || !t.drawObject) throw Error("Missing stacked equation adapter");
			let n = e.math.ascent + e.math.descent;
			return {
				text: "",
				style: e,
				kind: "upright",
				advance: n,
				thickness: e.math.width,
				space: !1,
				box: {
					ascent: 0,
					descent: 0
				},
				cell: n
			};
		}
	}), a = i.columns.map(() => ({
		descent: 0,
		size: 0
	}));
	for (let e of i.glyphs) {
		if (e.style.math) continue;
		let t = a[e.column];
		t.descent = Math.max(t.descent, e.box.descent * e.style.sizePx), t.size = Math.max(t.size, e.style.sizePx);
	}
	if (t.drawUnderline) {
		let n, r = () => {
			if (!n) return;
			let r = i.columns[n.column], o = a[n.column];
			e.save(), e.translate(r.axisX - r.thickness / 2 + o.descent, n.top), e.rotate(Math.PI / 2), t.drawUnderline(n.style, n.bottom - n.top, o.size), e.restore();
		};
		for (let e of i.glyphs) {
			if (e.style.math) {
				r(), n = void 0;
				continue;
			}
			n && n.column === e.column && (t.sameUnderline?.(n.style, e.style) ?? n.style === e.style) ? n.bottom = e.cellTop + e.advance : (r(), n = {
				style: e.style,
				column: e.column,
				top: e.cellTop,
				bottom: e.cellTop + e.advance
			});
		}
		r();
	}
	let o = [], s = e.textAlign, c = e.textBaseline, l = (n, r, i, a) => {
		let s = a !== void 0, { style: c, box: l } = n;
		if (c.math) {
			t.drawObject(c, r - n.thickness / 2, i);
			return;
		}
		let u = c.sizePx;
		s || o.push({
			text: n.text,
			font: c.font,
			fontSize: u,
			x: r - n.thickness / 2,
			y: i,
			w: n.thickness,
			h: n.advance,
			...c.hyperlink ? { hyperlink: c.hyperlink } : {}
		}), e.font = c.font;
		let d = (n, r, i, a = !1) => {
			if (s || !c.strikethrough || !t.drawStrike) return;
			let o = e.measureText(n).width;
			t.drawStrike(c, a ? r - o / 2 : r, i, o);
		};
		if (!c.noFill) if (e.fillStyle = s && t.markerPaint ? t.markerPaint(a, r, i + n.cell - l.descent * u) : c.color, n.kind === "upright") {
			e.textAlign = "center", e.textBaseline = "alphabetic";
			let t = rn(i, n.cell, l, u);
			d(n.text, r, t, !0), n.space || e.fillText(n.text, r, t);
		} else if (n.kind === "verticalGlyph") {
			let a = qt(n);
			if (a !== null) Kt(e, a, r, i, l, u, (e, t) => d(a, e, t));
			else {
				let a = r - (l.ascent + l.descent) * u / 2;
				if (e.save(), e.translate(a + u / 2, i + u / 2), e.rotate(Math.PI / 2), e.textAlign = "center", !s && c.strikethrough && t.drawStrike) {
					e.textBaseline = "alphabetic";
					let t = e.measureText(n.text).fontBoundingBoxAscent;
					e.textBaseline = "middle";
					let r = e.measureText(n.text).fontBoundingBoxAscent, i = Number.isFinite(t - r) ? t - r : (l.ascent - l.descent) * u / 2;
					d(n.text, 0, i, !0);
				}
				e.textBaseline = "middle", e.fillText(n.text, 0, 0), e.restore();
			}
		} else Zt(e, n.text, r, i, l, u, () => d(n.text, 0, 0));
	}, u = 0;
	for (let [a, o] of i.columns.entries()) {
		if (o.prefixTop !== void 0) {
			let e = o.prefixTop;
			for (let t of r[o.paragraph]) l(t, o.axisX, e, o.paragraph), e += t.advance;
			t.drawMarker?.(o.paragraph, o.axisX, o.prefixTop);
		}
		let s;
		for (; u < i.glyphs.length && i.glyphs[u].column === a;) {
			let e = i.glyphs[u++];
			l(e, e.axisX, e.cellTop), s = e;
		}
		let c = t.paragraphs[o.paragraph].endMarkStyle ?? s?.style;
		if (s && c && !c.math && c.strikethrough && t.drawStrike && i.columns[a + 1]?.paragraph !== o.paragraph) {
			let r = n(" ", c)[0];
			e.font = c.font;
			let i = e.measureText(" ").width;
			t.drawStrike(c, o.axisX - i / 2, s.cellTop + s.advance + r.cell - r.box.descent * c.sizePx, i);
		}
	}
	return e.textAlign = s, e.textBaseline = c, o;
}
var ci = [
	[12288, 40959],
	[44032, 55203],
	[63744, 64255],
	[65280, 65519]
], li = "/////wEA/zj+////////////f/7///////////////8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD//wAAAAD/////TwD+/wAAAAD///////////8P//////////////////////////9/+P//////////////P////39EAAAAAFAAAAAAAAAABQAAAAAEAAAAIBCCAAAIAAAAAAAAAIAAAAAAAAAAYAhAAAAAAAAAQAEAACAAAAQAABAAIAAAAAAAAAAABAAAAAAAAAAAQgIAAAAAAIAAAAAAAAAAAAAAAAAAAAAAAAAGGCAAACAAAAAAAAACAAAABAEQIAEAAAAAgEAAAQAAEAIAAAAAAAAAAAAAAAAAAAAAAAAEAACABAAAAAAAAAAAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAgAAAAAAAAAAAAAAMAAAAQAAABAABQEAAAAAAAAAAAggACAIQAAEABAAAggAAAAAAEAAIAAAEAAAAAAAAAAAAAAAAAAAAAAAAAACAAEAAAAAAAAAAJAAAAAAAAAAAAQAAAAAAAABAAAAAABABAAAAAAACAAAAAAAACAAAAAAEEBAAAIABAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAIAAAACkAAAAAAAAQAAAAAKAAAAAAAAAAAAAAAAAAgAAAAEAAAAAAAAIAAAAAAAAAAAAAAAAACgAAAAAAAAAAIEAAAgAAAAAAAAAAAAQgAAAAABAQAgAgEAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAQABAIAAAAAAEAAAAAAgAAAgAAAAAAAAAAAAAAAAAAAAAAAAAAEABAAABgBAEAAAAAAAICAAAQAQAAAIAAAAIQAAAEAAAAAAAACEAACAAAAAIAAAAAAAAAAAAACAAAAABAAAAACAEAAAAgAAAAAAAAAIAAAAgAAAAAAAAAAKAAAAAAAAAAAAAAAAQAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAgAABAAAAAAAAQQAAAAAAAAAAAAAAAACAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAAAAAAgAAABBAAAAAAAgAAAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAAgAAAAAAAAAAAAAAAAAAAAABAQEAAABAAAAAIAAAQAAAAAAAAAAAAAAAAAAAAAAAAAQAAIAAAAAAIAAAAAAAAAAAAAAAAAAgAAAAAAAAAAAAAAAAAAAAAAAv//3w1r/Z5u/6eL3DAM6wmV/9vt3+Uke3+3x7z/p7/gP/+c2AMD7f837//8Z1uv/fv/f7wPo5Pp//5e9f8Bv9ff+3//+f20K79fvlu00dyF75X/fD7S/br32e/f+435O33v1/4q1vp///72VdX/qWX3+7yN/P//1fL9ueed2/2f/Tf4htEfvH7i+8P171r+jvTf+nfm+83/7t/f7/xf/z++96/+7vy//7z+eX+vyv++cQVinc+/9LS3//O7///8FHv8P7NP/cnvd5yLA1H/7uHv/Z5D3/47/32cAAWf3TvTz83EAAMxue/O2y31bAAD438+e95h/RYD6Wp79HALA4uDn1wsAk/+73/oZSF+/dgXxnTDm5XveUC//i//7ac+mn79uvf/3menl9AcC9u+73g4buPafFwkhydn88Nsi8f99PyDq06Q3pgO+t8eWCyXTq7/Z8EdPDl++df/3N9qfPmS+fT++/+/h2MO/76//fDsefOyquPGNXlovH/b4zdaJG3KO1bmnYRDzEyFE7DSAzBAITd6FEC79VpvBFGtGYSwDG8AqJjRVQSsY9/14yyv1+//7v3zz/5f/8t3v99t75f7/zsRde2f/Y+0r3r/zcz6AlRegzz/+XaAKhSID53cswmj3r90KEqLMXS+SF7ZJxp4dvaqVWJU3iHrPa/9LvmvNOHavIentYbla89keff3/m8zgFPU96uNO33/+Lxvb/9//+/v++/P5s3XbO9++v/e/v89zl/+7M7g733MX/2+9gOT9v/evKgTPP/emv7+jwH5//u39B/959M86/69B/z/P9//ehx3z9vZ/KOet2f/Zn2zft7F/RFfP/yHw7rL09Zt3f/zPzh3r33t/XRvhSv495v9bQBnq/vT3/z8D9P7wnj9Lg4CvZP5e/r0b4Hv2vZ3Xdc///zRoe8N/HCt+/AdApt1Rdbdx5ATIHr2LolgvqIEv73p7Dfq2frcF/7b37DOHWPtc3vb/uOP3480YtSW+V6e7+/i9s37b1YvnMzfe3fd+8P//+55/34b////535t7x79VaDbb39NQvkFbvf+r6/z6xX3a5eLze/J/9P/YstrvP0eC/zt/x59/1tL/2h4A2eOGvx/7f3e/u4jI995+odm/7+9/Hni//7e7/ec++//PHm4Fe6tkRrh+f/+//wdkdhnj6Zfdq/3LY6zvfuovnL7uH0/97/BPZ4vWDA7F22qT+C/cG+lOE3v8lBUPPPyOx+ibmeTvyHZdq//eue9/LuDJHf+///n73I3K2IqCNOefithR+M0H7LX+ha1db/4z/Z8GbB//drb/Pq//HwiR3I7O8M9++zIAgN/71pETp3f+GwDctLP13Wfvd9//H7EPqO7/+lRfOP/e+YzJTOgFsEmzpezerL//OmIA+JTrVrd7DW2+b9kigJ7/V/ERdd9534X7xWtS7wcwzRPba3bjrqLtyZGVVphfMmE42mFxYkb0ixD4mw8AjXqzEBACJvibEFZD6EMR4l7vVvVTACKxlXSOBdEPxhtb/ZbhcCDbwSOprBC9df/v2e3/j8uW+5diTAiF8VdC31LliycymH+0LMz7tN/+7jjgxeHJ36cf+0750WUvrLpl/0xi62ay7/sGCgx2/3+G2yiu/t3vT0Dri4fX788nrgCXWd3dz9edenDu1fufz//Z/5f+977//+bePzY8CL/3//S8+5/SH9afv2z7/yM9H/8fZJO291d9/3lp/v/l0aduf9Ta+1VXW9NftN514/B35eTE3f8dHfYReHLEtfZr+ogipt799hsZZHI2Iu9wtYS40QZwG90UVML0uuWaty/84l9/jNOV3O7DUAfe4X4z/POP+/2lK4TeM/x6OcLPG++Xv3bl/z4DGY1/st+D6Lf//fPhsq8uced/Df/BCdZp3vj/Lr7vv1Pv1I0Wy+n/e6rj+3cg8fa/htDq9/8jtHc995/QLnEn/Vff+p7K6x3a52m0//+78i1yzqq1yfdpz/615/z3xYp0Tf2v/39P/C/2//nLfu5Fb69uu8r+zfz/7/385nd/v3YOMw+n+M/57u76bHXn5u4p+873f30AAAAAAAAAAAAAAAAAAAAAAABA/bjwP7n7b6fj7f3TdF7nVLNr1TSIa9sN/v/9//NTUfn+WQRExVeh/+ryW0h/9r4WB3j19G9z4H9/7C/AXZmSK3bgXL+A4+uTh3/NaX0dQz3tb+/q77s9f/+/993/Bl/8F/nKwrfd7dbrm25er/v/n/9gAL/e/63abv9fwPkO+f83veb/AQwAqbn1Znz/xT8i4KPWW7Ejj9c72vyqAgWWIjPg58af//77NzYIvdJCpxb/1/9LFoDUl7zNULvd/rx84EvP7/cfr4Du85+bu/X/0feG7iG3cyfI3vVsz0+WokG3R2D9/5+a4g6AP2o12q/7g/tqtt758gB//e8f1yrDlv4peqqje8VWmfw7qcON+Crs009lln287s/q7n/fjmFnHn9/7v8fi6fqBynE3xVNs/Q//D77goG/c9b0bXfhDedPf7LE3F/svfzLr/md45b9tAEA9DXoKlqjvdX/6X+8+nzrY/9573rv3v2678f7883a3L///Nd4+9X3fNP+QzmJUn7zXt9IutfzO/x99gAAAAAAAAAAAAAAAAAAAAAAAIDn4t+7L14/aF/kfh17l//dv//van5ftbf9uu/dCAAAAAAAAAAA8HrbgDYlI8JJmaxe5d3yrrqdP7Byn1PgzwT7I5f/Mfofslcevi4/b4b+a0z7vRaYDGmDf62X/fvr//jP//a+8T4AAAAAAAD4++H33XdsPwR97/Nvf/n774/3/tO+/n977/P1t7/fowtvhX9hulWk+zap9eB1IfVXreLXNsj/3A++NrwP7m6/UVb9f73/+9v/+vbjuvN2/3F40/nX/e/Df/fD4/spZ+6fsanEv+/5rbO7d42fVaEz9//f/AyQL/ddsf579Ae99E3ae3EG+j/L8/YBoi+e4/mHAsh+rToVLt4P7afwGgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAgEPd/17j/ztU/sg/f/d3AAAAAAAAcHbVnr8XuNX5flT9/X7v8r/5x98/835m/wyPxw7951p+ngVPa97n5l/fb9q/c6rh1015ftf6f5t7E+D+jf7131d7+L150Pz+T+ycGwAAAAAAAO3TnfwZAJjv7l9wrBf//ddZPn7ne7fbAQAAAAAAwOtocT+2egM//4R8N/s+3E9GnWnrQb989q00DgAAAAAAAAD19dtbwf/b/f6+rn97Rb/t774+PvnyTWr9/IP57t/kwX+X28PH16m+za+nVb9/f/7n5276x939qW/lDQAAAAAAAAAAAAAAAOAa3QLMS6b5SJ0d/P4FH/I/ns1c2ObsBxLeNr/fhvjG6eodZ4RkZ34AAAAAAAAAAAAAIL+/2e7pcPc5/1j5N/ux8fe7jMP/jkeclv76zPzlz/fvJACg93AvAAAAcAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAIwAAQAAAAAAAAQAAAAAAAAAAAAAAAQAAEAAAAAAPz////z/////////wMAAAAAAAAAAAAAAAAAAAAAAOD/////////////////////////DwAAAAAAAADw9wc=", ui = [
	[12288, 12291],
	[12293, 12306],
	[12308, 12309],
	[12316, 12318],
	[12321, 12329],
	[12336, 12336],
	[12353, 12435],
	[12443, 12446],
	[12449, 12534],
	[12539, 12542],
	[12549, 12585],
	[12690, 12703],
	[12736, 12751],
	[12849, 12849],
	[12963, 12963],
	[13198, 13199],
	[13212, 13214],
	[13217, 13217],
	[13252, 13252],
	[13262, 13262],
	[13265, 13266],
	[13269, 13269],
	[13312, 19893],
	[19968, 40883],
	[40903, 40907],
	[63744, 64045],
	[65281, 65374],
	[65377, 65439],
	[65504, 65509],
	[65517, 65517]
], di = [
	[12288, 12351],
	[12353, 12438],
	[12441, 12543],
	[12549, 12587],
	[12688, 12727],
	[12736, 12751],
	[12784, 12799],
	[12849, 12849],
	[12963, 12963],
	[13198, 13199],
	[13212, 13214],
	[13217, 13217],
	[13252, 13252],
	[13262, 13262],
	[13265, 13266],
	[13269, 13269],
	[13312, 19893],
	[19968, 40883],
	[63744, 64045],
	[65281, 65470],
	[65474, 65479],
	[65482, 65487],
	[65490, 65495],
	[65498, 65500],
	[65504, 65510],
	[65512, 65518]
], fi = [
	"MS Mincho",
	"PMingLiU",
	"Batang"
], pi = [
	"MS Gothic",
	"Microsoft JhengHei",
	"Malgun Gothic"
], mi = Object.freeze([
	"Arial",
	"Angsana New",
	"Mangal",
	"Latha"
]), hi = /\p{Script=Thai}/u, gi = /\p{Script=Devanagari}/u, _i = /\p{Script=Tamil}/u;
function vi(e) {
	return hi.test(e) ? "Angsana New" : gi.test(e) ? "Mangal" : _i.test(e) ? "Latha" : "Arial";
}
var yi;
function bi() {
	if (yi) return yi;
	let e = atob(li), t = new Uint8Array(e.length);
	for (let n = 0; n < e.length; n++) t[n] = e.charCodeAt(n);
	return yi = t, t;
}
function xi(e) {
	let t = 0;
	for (let [n, r] of ci) {
		if (e >= n && e <= r) return t + e - n;
		t += r - n + 1;
	}
	return -1;
}
function Si(e, t) {
	let n = 0, r = t.length - 1;
	for (; n <= r;) {
		let i = n + r >> 1, [a, o] = t[i];
		if (e < a) r = i - 1;
		else if (e > o) n = i + 1;
		else return !0;
	}
	return !1;
}
function Ci(e) {
	let t = xi(e);
	return t < 0 ? !1 : (bi()[t >> 3] & 1 << (t & 7)) != 0;
}
function wi(e, t) {
	for (let n of t) {
		let t = n.codePointAt(0) ?? 0;
		if (!/\s/u.test(n) && !(e === "MS Mincho" || e === "MS Gothic" ? Ci(t) : e === "PMingLiU" ? Si(t, ui) : e === "Microsoft JhengHei" && Si(t, di))) return !1;
	}
	return !0;
}
function Ti(e) {
	let t = w(e, { source: "office-mac" });
	return t.length > 0 ? t : w(e);
}
function Ei(e) {
	return Ti(e).some((e) => e.farEastCodePage === !0);
}
function Di(e) {
	return Ti(e).some((e) => {
		let t = e.panose;
		return !!t && t[0] === 2 && t[1] >= 2 && t[1] <= 10;
	});
}
function Oi(e, t) {
	if (e && Ei(e)) return M(e) === "jp" ? [
		e,
		"Microsoft JhengHei",
		"Malgun Gothic"
	] : [
		e,
		"PMingLiU",
		"Batang"
	];
	let n = e && Di(e) ? fi : pi, r = n.slice(0, 2).find((e) => wi(e, t));
	return r ? [r, ...n.filter((e) => e !== r)] : [
		n[2],
		n[0],
		n[1]
	];
}
var ki = new Set([
	167,
	168,
	176,
	177,
	180
]), Ai = new Set([
	215,
	247,
	8263,
	8264
]), ji = /^(?:ja|ko|zh)(?:[-_]|$)/i, Mi = /^(?:ar|he|hi|th)(?:[-_]|$)/i;
function Ni(e, t) {
	return ki.has(e) || e >= 9312 && e <= 9471 || e >= 9632 && e <= 10175 || e >= 65072 && e <= 65103 ? !0 : Ai.has(e) ? !Mi.test(t ?? "") : e >= 8216 && e <= 8222 && ji.test(t ?? "");
}
function Z(e, t) {
	return e * t;
}
function Pi(e, t, n, r) {
	let i = _(Math.abs(e * n), Math.abs(t * n), r);
	return i ? {
		targetWidthPx: i.width,
		targetHeightPx: i.height
	} : void 0;
}
function Fi(e, t) {
	return e.blipEffects?.length ? t.catch((e) => {
		throw Nt(e);
	}) : t;
}
function Ii(e) {
	return e instanceof de && e.code === "unsupported-image-effect";
}
function Q(e, t = e) {
	if (!e.blipEffects?.length) return e.duotone ?? void 0;
	let n = e.tile ? 914400 / (e.dpi != null && e.dpi > 0 ? e.dpi : 96) : void 0;
	return {
		effects: e.blipEffects,
		duotone: e.duotone ?? null,
		hostRotation: e.rotWithShape === !1 ? 0 : t.rotation,
		hostFlipH: t.flipH,
		hostFlipV: t.flipV,
		...n === void 0 ? {} : {
			emuPerPixelX: n,
			emuPerPixelY: n
		}
	};
}
function Li(e, t, n) {
	return e.tile && e.blipEffects?.length ? {
		widthPt: 0,
		heightPt: 0
	} : {
		widthPt: t,
		heightPt: n
	};
}
function Ri(e, t) {
	return Be(e, t);
}
function zi(e, t) {
	let n = e.targets.get(t);
	return n ? {
		targetWidthPx: n.width,
		targetHeightPx: n.height,
		maxRetainedPixels: n.maxRetainedPixels
	} : void 0;
}
function Bi(e) {
	return !Q(e) && Ze(e) ? e.svgImagePath : e.imagePath;
}
function Vi(e, t, n, r) {
	let i = Nr(t.bullet);
	if (i.type !== "blip") return;
	let a;
	for (let e of t.runs) if (e.type === "text" && e.fontSize != null) {
		a = e.fontSize;
		break;
	}
	let o = a ?? t.defFontSize ?? e.defaultFontSize ?? 18, s = i.sizePts == null ? o * ((i.sizePct ?? 100) / 100) : i.sizePts;
	return Math.max(1, s * V * n * r);
}
async function Hi(e, t, n, r, i, a, o, s, c, l) {
	let u = lt(a), d = [], f = [], p = (e, t, n, r, i, a = 1, o = i) => {
		if (!(!t || !i)) {
			if (Mt(r) && (u.resolution === "display" || u.strategy === "adaptive")) {
				d.push({
					key: e,
					...t,
					retainedSurfaceCount: a
				});
				return;
			}
			f.push(He(n, r, i, o).then((n) => n.dimensions && Pt(n.format, l !== void 0) ? {
				key: e,
				...t,
				sourceWidthPx: n.dimensions.width,
				sourceHeightPx: n.dimensions.height,
				retainedSurfaceCount: a
			} : null).catch((e) => {
				if (e instanceof he || Ii(e) || Ot(e)) throw e;
				return null;
			}));
		}
	}, m = e.background;
	if (m?.fillType === "image" && m.imagePath && !m.tile && !Q(m)) {
		let e = m.fillRect ?? {}, r = t * (1 - (e.l ?? 0) - (e.r ?? 0)), a = n * (1 - (e.t ?? 0) - (e.b ?? 0));
		p(Ri(m.imagePath, Q(m)), Pi(r, a, i, m.srcRect), m.imagePath, m.mimeType, o, 1);
	}
	for (let t of e.elements) {
		for (let { fill: e, width: n, height: a } of Ui(t)) if (!e.tile && !Q(e)) {
			let t = e.fillRect ?? {}, s = Z(n, r) * (1 - (t.l ?? 0) - (t.r ?? 0)), c = Z(a, r) * (1 - (t.t ?? 0) - (t.b ?? 0));
			e.mimeType === "image/svg+xml" || Ze(e) || p(Ri(e.imagePath, Q(e)), Pi(s, c, i, e.srcRect), e.imagePath, e.mimeType, o, 1);
		}
		if (t.type === "picture") !(Ze(t) || t.mimeType === "image/svg+xml") && !Q(t) && p(Ri(t.imagePath, Q(t)), Pi(Z(t.width, r), Z(t.height, r), i, t.srcRect), t.imagePath, t.mimeType, o, 1);
		else if (t.type === "media" && t.posterPath) {
			let e = s ? Mo(s) : void 0;
			p(Ri(t.posterPath), Pi(Z(t.width, r), Z(t.height, r), i), t.posterPath, t.posterMimeType || "application/octet-stream", e, 1, c ?? e);
		} else if (t.type === "chart") {
			if (!Number.isFinite(t.width) || !Number.isFinite(t.height) || t.width < 0 || t.height < 0) throw new de("unsupported-image-effect", "Invalid chart image frame");
			if (!(t.width > 0 && t.height > 0)) continue;
			let e = {
				widthPt: t.width / V,
				heightPt: t.height / V,
				targetWidthPx: Z(t.width, r) * i,
				targetHeightPx: Z(t.height, r) * i
			};
			for (let n of W(t.chart)) {
				let t = n.fill, r = j(n, e);
				!(t.mimeType === "image/svg+xml" || Ze({
					svgImagePath: t.svgImagePath,
					srcRect: n.hasSourceCrop ? !0 : null
				})) && !Q(t) && !n.preserveNaturalSize && r?.targetWidthPx && r.targetHeightPx && p(Ri(t.imagePath, Q(t)), {
					targetWidthPx: r.targetWidthPx,
					targetHeightPx: r.targetHeightPx
				}, t.imagePath, t.mimeType, o, 1);
			}
		} else if (t.type === "shape") {
			if (!t.textBody) continue;
			for (let e of t.textBody.paragraphs) {
				let n = Nr(e.bullet);
				if (n.type !== "blip") continue;
				let a = Vi(t.textBody, e, r, i);
				!a || !o || f.push(He(n.imagePath, n.mimeType, o).then((e) => e.dimensions && e.dimensions.width > 0 && e.dimensions.height > 0 && Pt(e.format, l !== void 0) ? {
					key: Ri(n.imagePath),
					targetWidthPx: a * e.dimensions.width / e.dimensions.height,
					targetHeightPx: a,
					sourceWidthPx: e.dimensions.width,
					sourceHeightPx: e.dimensions.height
				} : null).catch((e) => {
					if (e instanceof he || Ii(e) || Ot(e)) throw e;
					return null;
				}));
			}
		}
	}
	return d.push(...(await Promise.all(f)).filter((e) => e !== null)), pt(d, u);
}
function* Ui(e) {
	let t = (t, n, r) => t?.fillType === "image" && Va(t) ? {
		fill: t,
		width: n,
		height: r,
		host: e
	} : null;
	if (e.type === "shape") {
		if (e.fill?.fillType === "image" && (![e.width, e.height].every(Number.isFinite) || e.width < 0 || e.height < 0)) throw new de("unsupported-image-effect", "Invalid image fill frame");
		let n = t(e.fill, e.width, e.height);
		n && e.height > 0 && (yield n);
	} else if (e.type === "table") {
		let n = [0];
		for (let t of e.cols) n.push(n[n.length - 1] + t);
		let r = [0];
		for (let t of e.rows) r.push(r[r.length - 1] + t.height);
		let i = n[n.length - 1], a = r[r.length - 1] || e.height, o = t(e.background, i, a);
		o && (yield o);
		for (let i = 0; i < e.rows.length; i++) {
			let a = e.rows[i];
			for (let o = 0; o < a.cells.length; o++) {
				let s = a.cells[o];
				if (s.hMerge || s.vMerge || s.fill?.fillType !== "image" || !Va(s.fill)) continue;
				let c = (n[Math.min(o + (s.gridSpan || 1), e.cols.length)] ?? 0) - (n[o] ?? 0), l = r[Math.min(i + (s.rowSpan || 1), e.rows.length)] - r[i] || e.height, u = t(s.fill, c, l);
				u && (yield u);
			}
		}
	}
}
function* Wi(e) {
	for (let t of e.elements) yield* Ui(t);
}
function Gi(e) {
	return e.background?.fillType === "image" ? !0 : e.elements.some((e) => e.type === "picture" ? !0 : e.type === "media" ? !!e.posterPath : e.type === "chart" ? W(e.chart).length > 0 : !Ui(e).next().done || (e.type === "shape" || e.type === "table") && e.mathFallback?.elements.length ? !0 : e.type === "shape" && (e.fill?.fillType === "image" || !!e.textBody?.paragraphs.some((e) => Nr(e.bullet).type === "blip")));
}
var $ = D;
function Ki(e, t, n, r, i, a, o, s) {
	let c = s?.glyph, { top: l, height: u } = s && c ? (() => {
		let e = n + 1.2 * i * (1 - s.share), t = (c.ascent + c.descent) * i;
		return {
			top: e - t,
			height: t
		};
	})() : ir(n, i);
	e.fillStyle = a, e.fillRect(t, l, r, u), e.fillStyle = o;
}
function qi(e, t, n, r, i, a, o = 0, s = 4 / 3, c, l) {
	return q(e, t, n, r, i, a, o, s, void 0, c, l);
}
function Ji(e, t, n, r, i, a, o, s, c = t.drawSizePx ?? t.sizePx) {
	if (t.underline && !t.underlineLineNoFill && (!t.noFill || t.underlineFill || t.underlineColor || t.underlineLine?.fill)) {
		let u = t.underlineFill ? q(t.underlineFill, e, n, r, i, c * .05, 0, o * 12700) ?? "rgba(0,0,0,0)" : t.underlineLine?.fill ? q(t.underlineLine.fill, e, n, r, i, c * .05, 0, o * 12700) ?? "rgba(0,0,0,0)" : t.underlineColor ?? a, d = t.lineMetric?.decoration;
		l(e, n, r, i, c, u, t.underlineStyle, s, t.underlineLine?.width ? Z(t.underlineLine.width, o) : void 0, d ? {
			topPx: d.underlineTopEm * c,
			thicknessPx: d.underlineThicknessEm * c
		} : void 0);
	}
}
function Yi(e, t, n, r, i, a, s) {
	let c = t.drawSizePx ?? t.sizePx;
	if (t.strikethrough && !t.noFill) {
		let l = t.lineMetric?.decoration, u = l ? l.strikeThicknessEm * c : Math.max(1, c * p);
		e.strokeStyle = a, e.lineWidth = u, e.setLineDash([]);
		let d = l ? r + l.strikeTopEm * c + u / 2 : r - c * o;
		if (t.strikeDouble) {
			let t = l ? u : u * .9, r = d - t, a = d + t;
			e.beginPath(), e.moveTo(n, r + b(r, u, s)), e.lineTo(n + i, r + b(r, u, s)), e.moveTo(n, a + b(a, u, s)), e.lineTo(n + i, a + b(a, u, s)), e.stroke();
		} else {
			let t = d + b(d, u, s);
			e.beginPath(), e.moveTo(n, t), e.lineTo(n + i, t), e.stroke();
		}
	}
}
function Xi(e, t, n, r, i, a, o, s) {
	Ji(e, t, n, r, i, a, o, s), Yi(e, t, n, r, i, a, s);
}
var Zi = /* @__PURE__ */ new WeakMap(), Qi = /* @__PURE__ */ new WeakSet();
function* $i(e) {
	if (e.type === "shape" && e.textBody) yield e.textBody;
	else if (e.type === "table") for (let t of e.rows) for (let e of t.cells) e.textBody && (yield e.textBody);
}
function ea(e) {
	let t = [];
	for (let n of e.elements) for (let e of $i(n)) for (let n of e.paragraphs) for (let e of n.runs) e.type === "math" && t.push({
		nodes: e.nodes,
		display: e.display
	});
	return t;
}
async function ta(e, t) {
	let n = ea(e);
	if (n.length !== 0) {
		try {
			await t.loadMathJax();
		} catch (e) {
			if (e instanceof he || Ot(e)) throw e;
			for (let e of n) Zi.has(e.nodes) || Qi.add(e.nodes);
			return;
		}
		for (let e of n) if (!Zi.has(e.nodes)) try {
			let n = await t.mathMLToSvg(ge(e.nodes, e.display)), r = await Oe(n, "#000000");
			Qi.delete(e.nodes), Zi.set(e.nodes, {
				raster: r,
				widthEm: n.widthEm,
				ascentEm: n.ascentEm,
				descentEm: n.descentEm,
				tinted: /* @__PURE__ */ new Map()
			});
		} catch (t) {
			if (t instanceof he || Ii(t) || Ot(t)) throw t;
			Qi.add(e.nodes);
		}
	}
}
function na(e, t, n, r) {
	let i = t.math, a = i.unavailable ? void 0 : Zi.get(i.nodes), o = i.ascent + i.descent;
	if (i.width > 0 && o > 0) if (a) e.drawImage(Pe(a, t.color), n, r, i.width, o);
	else if (i.fallbackText) {
		e.font = t.font, e.fillStyle = t.color;
		let a = e.textBaseline, o = e.textAlign;
		e.textBaseline = "top", e.textAlign = "left", e.fillText(i.fallbackText, n, r), e.textBaseline = a, e.textAlign = o;
	} else i.unavailable && jt(e, "equation", {
		x: n,
		y: r,
		width: i.width,
		height: o
	});
}
function ra(e, t, n, r, i) {
	return t.patternFill ? q(t.patternFill, e, n, r, 0, 0, 0, i * 12700) ?? t.color : t.color;
}
function ia(e, t) {
	let n = (e) => t.embeddedFontAliases?.get(e.trim().toLowerCase()) ?? e;
	if (!e) return n(t.themeMinorFont ?? "sans-serif");
	if (e.startsWith("+")) return n(e === "+mj-lt" || e === "+mj-ea" || e === "+mj-cs" ? t.themeMajorFont ?? "sans-serif" : t.themeMinorFont ?? "sans-serif");
	let r = e.split(",")[0].trim();
	return r ? n(r) : t.themeMinorFont ?? "sans-serif";
}
function aa(e) {
	return !!e?.trim();
}
var oa = new Set([
	"serif",
	"sans-serif",
	"monospace",
	"cursive",
	"fantasy",
	"system-ui"
]);
function sa(e) {
	let t = P(e);
	return t === "mono" ? "monospace" : t === "serif" ? "serif" : "sans-serif";
}
function ca(e) {
	if (fe(e)?.includes("Arabic")) return !0;
	let t = e.toLowerCase();
	return /arabic|naskh|kufi|nastaliq|amiri|scheherazade|lateef|aldhabi|urdu|farsi|العرب|[؀-ۿ]/.test(t);
}
function la(e, t = e, i, o = "", s = !1) {
	let c = sa(t), l = r(o), u = s ? fe(t) : void 0, d = u && Et(n(a, t.toLowerCase()), o, "any") ? u : void 0, p = d ? `${f(d)}, ` : "", m = G(t), _ = m ? `${f(m)}, ` : "", v = h(c === "serif"), y = l ? `${g(v)}, ` : "";
	if (ca(t) && l) {
		let t = i ? H(i, "sans") : [];
		return `${f(e)}, ${p}${y}${t.length ? `${g(t)}, ` : ""}${c}`;
	}
	let b = c === "serif" ? "serif" : "sans", x = M(t), S = x ?? i, C = S ? H(S, b).filter((e) => e !== m) : [], w = C.length > 0 ? `${g(C)}, ` : "", T = `${g(b === "serif" ? ne : E)}, `;
	return x ? `${f(e)}, ${p}${_}${w}${T}${y}${c}` : `${f(e)}, ${p}${_}${T}${w}${y}${c}`;
}
function ua(e) {
	return e ? e.kind === "external" ? `e:${e.url}` : `i:${e.ref}` : "";
}
function da(e) {
	let t = e.toLowerCase();
	return /\bthin\b/.test(t) ? 100 : /\b(extra[- ]?light|ultra[- ]?light)\b/.test(t) ? 200 : /\blight\b/.test(t) ? 300 : /\b(black|heavy)\b/.test(t) ? 900 : /\b(extra[- ]?bold|ultra[- ]?bold)\b/.test(t) ? 800 : /\b(semi[- ]?bold|demi[- ]?bold)\b/.test(t) ? 600 : /\bbold\b/.test(t) ? 700 : /\bmedium\b/.test(t) ? 500 : /\b(normal|regular)\b/.test(t) ? 400 : null;
}
function fa(e, t, n, r, i, a, o, s) {
	let c = Math.max(0, r.blur * i), l = Math.ceil(c * 3) + 2, u = Math.max(0, Math.floor(n.x - l)), d = Math.max(0, Math.floor(n.y - l)), f = Math.min(o, Math.ceil(n.x + n.w + l)), p = Math.min(s, Math.ceil(n.y + n.h + l)), m = Math.max(1, f - u), h = Math.max(1, p - d), g = C(m, h), _ = g?.getContext("2d");
	if (!g || !_) return;
	_.save(), _.setTransform(a.a, a.b, a.c, a.d, a.e - u, a.f - d), U(e, _, () => t(_), {
		x: u,
		y: d
	}), _.restore();
	let v = n.y - d, y = v + n.h, b = g, x = _;
	if (c > 0) {
		let e = C(m, h), t = e?.getContext("2d");
		e && t && (b = e, x = t);
	}
	b !== g && Ir(x, g, {
		x: n.x - u,
		y: v,
		w: n.w,
		h: n.h
	}, c, m);
	let S = Math.max(0, Math.min(1, r.stPos)), w = Math.max(0, Math.min(1, r.endPos)), T = Math.max(1, y - v);
	try {
		let e = x.getImageData(0, 0, m, h);
		for (let t = 0; t < h; t++) {
			let n = Math.max(0, Math.min(1, (y - (t + .5)) / T)), i;
			if (n <= S) i = r.stA;
			else if (n >= w || w <= S) i = r.endA;
			else {
				let e = (n - S) / (w - S);
				i = r.stA + (r.endA - r.stA) * e;
			}
			let a = Math.max(0, Math.min(1, i));
			for (let n = t * m * 4 + 3; n < (t + 1) * m * 4; n += 4) e.data[n] = Math.round(e.data[n] * a);
		}
		x.putImageData(e, 0, 0);
	} catch {
		x.save(), x.globalCompositeOperation = "destination-in", x.fillStyle = `rgba(0,0,0,${Math.max(0, Math.min(1, r.stA))})`, x.fillRect(0, 0, m, h), x.restore();
	}
	let E = r.dist * i, D = r.dir * Math.PI / 180, O = n.y + n.h;
	e.save(), e.setTransform(1, 0, 0, 1, 0, 0), e.translate(n.x + Math.cos(D) * E, O + Math.sin(D) * E), e.scale(r.sx, r.sy), e.translate(-n.x, -O), e.drawImage(b, u, d), e.restore();
}
function pa(e, t, n, r, i, a = "", o = !0, s = []) {
	let c = t ? "italic " : "", l = ia(r, i), { authoredFamily: u, officeRoute: d } = ha(l, e, t, i), f = da(u), p = e ? "bold " : f ? `${f} ` : "", m = o ? d : void 0, h = J(a) ? i.cjkFallback ?? M(i.themeMajorFont) ?? M(i.themeMinorFont) ?? void 0 : void 0;
	if (oa.has(l)) {
		let e = h ? H(h, l === "serif" ? "serif" : "sans") : [], t = l === "monospace" ? ["Courier New", "Liberation Mono"] : l === "serif" ? [
			...ne,
			"Times New Roman",
			"Liberation Serif"
		] : [
			...E,
			"Arial",
			"Helvetica",
			"Liberation Sans"
		];
		return `${c}${p}${n}px ${e.length ? `${g([...t, ...e])}, ` : ""}${l}`;
	}
	let _ = la(m?.family ?? l, u, h, a, i.googleSubstitutes === !0), v = s.filter((e) => e !== l);
	return `${c}${p}${n}px ${v.length ? _.replace(/^("(?:[^"\\]|\\.)*"), /u, (e, t) => `${t}, ${g(v)}, `) : _}`;
}
function ma(e, t, n, r) {
	if (oa.has(e)) return;
	let { authoredFamily: i, embedded: a, officeRoute: o } = ha(ia(e, r), t, n, r);
	if (o) return gr(a ? i : e, t, n);
	if (!(a || !ga(i, r))) return gr(e, t, n);
}
function ha(e, t, n, r) {
	let i = r.embeddedFontAuthoredFamilies?.get(e), a = i ?? e, o = da(a), s = `${a.toLowerCase()}:${t || o === 700 ? 700 : 400}:${n ? "italic" : "normal"}`, c = r.embeddedFontTuples?.has(s) ? void 0 : r.officeFontRoutes?.[_a(a, lr(a, t, n), n)];
	return {
		authoredFamily: a,
		embedded: i !== void 0,
		officeRoute: c
	};
}
function ga(e, t) {
	return t.officeInstalledFamilies?.includes(Fe(e)) === !0;
}
function _a(e, t, n) {
	let r = Fe(e);
	return t === 400 && !n ? r : `${r}:${t}:${n ? "italic" : "normal"}`;
}
function va(e) {
	return e.bullet.type === "char" || e.bullet.type === "autoNum" || Nr(e.bullet).type === "blip";
}
function ya(e, t, n) {
	let r = null;
	for (let e of t.runs) if (e.type === "text" && e.fontSize != null) {
		r = e.fontSize;
		break;
	}
	let i = r ?? t.defFontSize ?? e.defaultFontSize ?? 18;
	return n.sizePts == null ? i * ((n.sizePct ?? 100) / 100) : n.sizePts;
}
function ba(e, t) {
	return e ? 0 : t;
}
var xa = /[\p{Script=Devanagari}\p{Script=Thai}\p{Script=Bengali}\p{Script=Tamil}\p{Script=Telugu}\p{Script=Kannada}\p{Script=Malayalam}\p{Script=Gujarati}\p{Script=Gurmukhi}\p{Script=Oriya}\p{Script=Sinhala}\p{Script=Khmer}\p{Script=Lao}\p{Script=Myanmar}\p{Script=Tibetan}]/u;
function Sa(e, t) {
	return t != null && t !== 0 ? e * .65 : e;
}
function Ca(e, t, n, r, i, a, o, s) {
	let c = (t.defaultFontSize ?? 18) * V * a;
	for (let l of t.paragraphs) {
		let u = Z(l.marL, a), d = Z(l.marR, a), f = Z(l.indent, a), p = ba(va(l), f), m = n - r - i - u - d - p, { input: h } = Oa(l, l.defFontSize == null ? c : l.defFontSize * V * a, "", a, t.defaultBold ?? !1, t.defaultItalic ?? !1, 1, s, o), g = 0, _;
		for (let t of h) {
			if (t.type !== "text") {
				_ = void 0;
				continue;
			}
			e.font = t.style.font;
			let n = t.style.letterSpacingPx ?? 0;
			if (g += Da(e, t.text, n), _?.sourceRunId === t.style.sourceRunId && (g += n), _ = t.style, g > m) return !0;
		}
	}
	return !1;
}
function wa(e) {
	let t = 0;
	for (let n of e) t++;
	return t;
}
var Ta = /* @__PURE__ */ new WeakMap();
function Ea(e) {
	let t = Ta.get(e);
	if (t != null) return t;
	let n = e, r = n.letterSpacing;
	if (typeof r != "string") return Ta.set(e, !1), !1;
	let i = !1;
	try {
		n.letterSpacing = "0px";
		let t = e.measureText("ii").width;
		n.letterSpacing = "1px";
		let r = e.measureText("ii").width;
		i = Number.isFinite(t) && Number.isFinite(r) && r !== t;
	} catch {
		i = !1;
	} finally {
		try {
			n.letterSpacing = r;
		} catch {}
	}
	return Ta.set(e, i), i;
}
function Da(e, t, n) {
	let r = e, i = r.letterSpacing;
	if (n !== 0 && Ea(e)) try {
		r.letterSpacing = `${n}px`;
		let i = e.measureText(t).width;
		if (Number.isFinite(i)) return t.length > 0 ? i - n : i;
	} finally {
		try {
			r.letterSpacing = i;
		} catch {}
	}
	let a = Math.max(0, wa(t) - 1);
	return e.measureText(t).width + n * a;
}
function Oa(e, t, n, i, a, o, s, c, l, u = !1, d) {
	let f = [], p = e.runs.map((e) => e.type === "text" ? un(e, c) : null), m = [], h = "";
	for (let e of p) m.push(h.length), h += e ?? "\n";
	let g = at(h);
	g.push(h.length);
	let _ = 0, v = (e) => {
		for (; _ < g.length && g[_] < e;) _++;
		return _ < g.length ? g[_] : h.length;
	}, y = null, b = /* @__PURE__ */ new Map(), x;
	if (e.mathFallbacks?.length) {
		x = /* @__PURE__ */ new Map();
		let t = [0];
		for (let n of e.runs) t.push(t[t.length - 1] + (n.type === "math" && Qi.has(n.nodes) ? 1 : 0));
		for (let n of e.mathFallbacks) {
			let { start: r, end: i } = n;
			Number.isInteger(r) && Number.isInteger(i) && r >= 0 && i <= e.runs.length && i > r && e.runs[r].type === "math" && t[i] > t[r] && i >= (x.get(r)?.end ?? r) && x.set(r, n);
		}
	}
	let S = 0;
	for (let [c, h] of e.runs.entries()) {
		if (c < S) continue;
		let g = x?.get(c);
		if (g && (S = g.end), h.type !== "text" && (y = null), h.type === "break") {
			let r = h.fontSize == null ? t : h.fontSize * V * i * s, c = ia(h.fontFamily ?? e.defFontFamily ?? null, l), u = h.bold ?? e.defBold ?? a, d = h.italic ?? e.defItalic ?? o, p = h.fontSize != null || h.fontFamily != null || h.bold != null || h.italic != null ? {
				text: "",
				sizePx: r,
				color: n,
				font: pa(u, d, r, c, l, ""),
				underline: !1,
				strikethrough: !1,
				lineMetric: ma(c, u, d, l)
			} : void 0;
			b.set(f.length, {
				text: "",
				sizePx: r,
				color: n,
				font: pa(u, d, r, c, l, ""),
				underline: !1,
				strikethrough: !1,
				lineMetric: ma(c, u, d, l),
				markFace: {
					family: c,
					bold: u,
					italic: d
				}
			}), f.push({
				type: "break",
				style: p
			});
			continue;
		}
		if (h.type === "math") {
			let e = g ? void 0 : Zi.get(h.nodes), r = h.fontSize == null ? t : h.fontSize * V * i * s, a = !!g || !e && Qi.has(h.nodes), o = g?.text ?? (a ? h.fallbackText : void 0);
			d && (d.font = `${r}px sans-serif`);
			let c = e ? e.widthEm * r : a ? d?.measureText(o ?? "Equation unavailable").width ?? 0 : 0, l = {
				text: "",
				font: `${r}px sans-serif`,
				sizePx: r,
				color: h.color ? $(h.color) : n,
				underline: !1,
				strikethrough: !1,
				math: {
					nodes: h.nodes,
					display: h.display,
					width: c,
					ascent: e ? e.ascentEm * r : a ? r : 0,
					descent: e ? e.descentEm * r : 0,
					fallbackText: o,
					unavailable: a
				}
			};
			f.push({
				type: "object",
				width: c,
				style: l,
				display: h.display
			});
			continue;
		}
		let _ = h.fontSize == null ? t : h.fontSize * V * i * s, C = Sa(_, h.baseline ?? void 0), w = ia(h.fontFamily ?? e.defFontFamily ?? null, l), T = h.fontFamilySym ? ia(h.fontFamilySym, l) : null, E = h.bold ?? e.defBold ?? a, D = h.italic ?? e.defItalic ?? o, O = p[c] ?? "", k = h.fontFamilyEa ? [] : Oi(h.fontFamily ?? e.defFontFamily ?? l.themeMinorFont ?? null, [...O].filter((e) => rt(e.codePointAt(0) ?? 0)).join("")), A = h.fontFamilyEa ? ia(h.fontFamilyEa, l) : k[0], j = h.fontFamilyCs ? ia(h.fontFamilyCs, l) : null, M = m[c], N = M + O.length;
		if (y && O) {
			let e = Math.min(v(M), N);
			e > M && (y.text += O.slice(0, e - M), O = O.slice(e - M), M = e);
		}
		let P = pa(E, D, C, A, l, O, !0, k.slice(1)), F = /* @__PURE__ */ new Map(), I = (e, t, n, i = []) => {
			let a = `${e}\0${+!!n}\0${+!!J(t)}\0${+!!r(t)}\0${i.join("\0")}`, o = F.get(a);
			if (o !== void 0) return o;
			let s = pa(E, D, C, e, l, t, n, i);
			return F.set(a, s), s;
		}, L = (e, t) => I(e, t, !0, mi), R = (h.letterSpacing ?? 0) * V * i, B = {
			text: "",
			font: "",
			sizePx: _,
			drawSizePx: C,
			color: h.color ? $(h.color) : h.hyperlink && l.themeHlinkColor ? $(l.themeHlinkColor) : n,
			patternFill: h.hyperlink && !h.hyperlinkUsesTextFill ? void 0 : h.patternFill,
			noFill: h.noFill,
			underline: h.underline || h.hyperlink !== void 0,
			underlineStyle: h.underlineStyle,
			underlineColor: h.underlineColor ? $(h.underlineColor) : void 0,
			underlineFill: h.underlineFill,
			underlineLine: h.underlineLine,
			underlineLineNoFill: h.underlineLineNoFill,
			strikethrough: h.strikethrough,
			strikeDouble: h.strikeDouble === !0,
			letterSpacingPx: R || void 0,
			sourceRunId: c,
			lang: h.lang,
			baseline: h.baseline ?? void 0,
			shadow: h.shadow,
			reflection: h.reflection,
			outline: h.outline,
			highlight: h.highlight ? $(h.highlight) : void 0,
			hyperlink: Lr(h.hyperlink)
		}, H = "", U = "", W, G = w, ee = ma(w, E, D, l) ?? null, te = () => {
			H && f.push({
				type: "text",
				text: H,
				style: {
					...B,
					font: U,
					lineMetric: W,
					lineMetricLatin: ee,
					faceFamily: G,
					faceFamilyLatin: w
				}
			}), H = "";
		}, K = 0, ne = !1, q = "", re = u && Aa.test(h.lang ?? "");
		for (; K < O.length;) {
			let t = Math.min(v(M + K + 1), N) - M, n = O.slice(K, t);
			K = t, ne = !0;
			let r = String.fromCodePoint(n.codePointAt(0) ?? 0), i = n, a = r.codePointAt(0) ?? 0, o = !rt(a) && !!h.fontFamilyEa && Ni(a, h.lang), s = o || rt(a), c = z(a) || xa.test(r), u = c ? j ?? vi(r) : w, d = o ? I(A, n, !0) : s ? P : c ? L(u, n) : I(w, n, aa(h.fontFamily ?? e.defFontFamily)), f = s ? A : u, p = ma(f, E, D, l);
			if (/[\uf020-\uf0ff]/u.test(r) && (T != null || yt(w))) {
				let e = T ?? w, t = vt(r, e);
				i = t + n.slice(r.length), d = pa(E, D, C, t === r ? e : "sans-serif", l, i), p = void 0, f = t === r ? e : "sans-serif";
			}
			let m = re && (ja(n) && Ma(q) || ja(q) && Ma(n));
			H && (d !== U || p !== W || m) && te(), H += i, U = d, W = p, G = f, q = n;
		}
		if (te(), ne) {
			let e = f[f.length - 1];
			y = e?.type === "text" ? e : null;
		}
	}
	return {
		input: f,
		sameStyle: (e, t) => e.font === t.font && e.color === t.color && e.patternFill === t.patternFill && e.noFill === t.noFill && e.sizePx === t.sizePx && e.drawSizePx === t.drawSizePx && e.underline === t.underline && e.underlineStyle === t.underlineStyle && e.underlineColor === t.underlineColor && e.underlineFill === t.underlineFill && e.underlineLine === t.underlineLine && e.underlineLineNoFill === t.underlineLineNoFill && e.strikethrough === t.strikethrough && e.strikeDouble === t.strikeDouble && e.letterSpacingPx === t.letterSpacingPx && e.baseline === t.baseline && e.shadow === t.shadow && e.reflection === t.reflection && e.outline === t.outline && e.highlight === t.highlight && ua(e.hyperlink) === ua(t.hyperlink) && (!e.letterSpacingPx || e.sourceRunId === t.sourceRunId) && e.lineMetric === t.lineMetric && e.lineMetricLatin === t.lineMetricLatin,
		breakMarks: b
	};
}
var ka = /^ja(?:[-_]|$)/i, Aa = /^(?:en-US|ja-JP)$/i;
function ja(e) {
	return e === "“" || e === "”";
}
function Ma(e) {
	return /^\p{Script=Han}/u.test(e);
}
function Na(e, t, n, r) {
	return ka.test(e.lang ?? "") && ka.test(t.lang ?? "") && (ja(n) && Ma(r) || Ma(n) && ja(r));
}
function Pa(e, t, n, r, i, a, o, s = !1, c = !1, l = 1, u, d = {
	themeMajorFont: null,
	themeMinorFont: null,
	dpr: 1
}, f = 0) {
	let { input: p, sameStyle: m, breakMarks: h } = Oa(t, r, i, a, s, c, l, u, d, !0, e), g = Z(t.marR, a), _ = cn(p, {
		maxWidth: n,
		firstLineIndent: f,
		measureText(t, n) {
			return e.font = n.font, ii(e, t, n.letterSpacingPx ?? 0);
		},
		sameStyle: m,
		boundaryAdvance: (e, t) => e.sourceRunId === t.sourceRunId ? t.letterSpacingPx ?? 0 : 0,
		tabStops: (t.tabStops ?? []).map((e) => ({
			pos: Z(e.pos, a),
			algn: e.algn
		})),
		defaultTabSize: Z(t.defTabSz ?? 914400, a),
		tabStartPen: (e) => t.rtl ? g : o + (e === 0 ? f : 0),
		nonMonotoneMeasure: p.some((e) => e.type === "text" && (e.style.letterSpacingPx ?? 0) < 0),
		eastAsianLineBreak: t.eaLnBrk !== !1,
		kinsokuAcrossRuns: Na
	}), v = t.endRunProperties, y = v?.fontSize == null ? r : v.fontSize * V * a * l, b = ia(v?.fontFamily ?? t.defFontFamily ?? null, d), x = v?.bold ?? t.defBold ?? s, S = v?.italic ?? t.defItalic ?? c, C = v && (v.fontSize != null || v.fontFamily != null || v.bold != null || v.italic != null) ? {
		text: "",
		sizePx: y,
		color: i,
		font: pa(x, S, y, b, d, ""),
		underline: !1,
		strikethrough: !1,
		lineMetric: ma(b, x, S, d)
	} : void 0, w = v ? {
		text: "",
		sizePx: y,
		color: i,
		font: pa(x, S, y, b, d, ""),
		underline: !1,
		strikethrough: !1,
		lineMetric: ma(b, x, S, d),
		markFace: {
			family: b,
			bold: x,
			italic: S
		}
	} : void 0, T = (e, t) => {
		let n = t[t.length - 1];
		if (!n || !e.markFace || n.sizePx === e.sizePx) return {
			...e,
			text: ""
		};
		let { family: r, bold: i, italic: a } = e.markFace;
		return {
			...e,
			text: "",
			sizePx: n.sizePx,
			font: pa(i, a, n.sizePx, r, d, "")
		};
	};
	return _.map((e, t) => {
		let n = t === _.length - 1, r = [...n && e.segments.length === 0 && C ? [C] : [], ...e.segments.length === 0 && e.lineFeedRun !== void 0 && !(n && C) && p[e.lineFeedRun]?.type === "text" ? [{
			...p[e.lineFeedRun].style,
			text: ""
		}] : e.segments.map((t, n) => {
			if (t.type === "text") {
				let r = e.segments[n - 1], i = r?.type === "text" && r.style.sourceRunId === t.style.sourceRunId ? t.style.letterSpacingPx : void 0;
				return {
					...t.style,
					text: t.text,
					leadingLetterSpacingPx: i
				};
			}
			return t.type === "tab" ? {
				...t.style,
				text: "",
				isTab: !0,
				tabWidthPx: t.width
			} : {
				...t.style,
				text: ""
			};
		})], i = e.endBreakRun !== void 0 && p[e.endBreakRun]?.type === "break", a = i ? p[e.endBreakRun].style : void 0, o = i ? h.get(e.endBreakRun) : void 0, s = e.segments.length > 0, c = s ? [...o ? [T(o, r)] : [], ...n && w ? [T(w, r)] : []] : [];
		return {
			segments: [...r, ...!s && a ? [{
				...a,
				text: ""
			}] : []],
			...c.length > 0 ? { metricMarks: c } : {},
			...e.endsWithBreak ? { endsWithBreak: !0 } : {}
		};
	});
}
var Fa = { paint: () => {} };
async function Ia(e, t, n, r, a, o, s, c) {
	if (e && e.fillType === "image") {
		let l = (e) => {
			e.fillStyle = "#FFFFFF", e.fillRect(0, 0, t, n);
		};
		if (!e.imagePath || !e.mimeType || !a) return { paint: l };
		let u = (e) => Ft(e, "tiff") ? { paint: (e) => {
			l(e), jt(e, "tiff", {
				x: 0,
				y: 0,
				width: t,
				height: n
			});
		} } : e instanceof he || Ii(e) || Ot(e) || zt(e) ? {
			paint: l,
			failure: { error: e }
		} : { paint: l }, d, f;
		try {
			let l = c && !Q(e) ? zi(c, Ri(e.imagePath, Q(e))) : void 0;
			f = e.tile ? await Fi(e, He(e.imagePath, e.mimeType, a)) : void 0, d = await ot(e.imagePath, e.mimeType, Q(e.tile ? {
				...e,
				dpi: void 0
			} : e), a, {
				...e.blipEffects?.length && !e.tile ? ((e) => ({
					widthPt: e.width,
					heightPt: e.height
				}))(i(t / r / V, n / r / V, e.srcRect, e.fillRect)) : Li(e, t / r / V, n / r / V),
				...l ?? {},
				tiff: o,
				svgDecoder: s
			});
		} catch (e) {
			return u(e);
		}
		if (!d) return { paint: l };
		let p = d;
		return { paint: (i) => {
			l(i);
			try {
				let a = e.fillRect ?? {}, o = a.l ?? 0, s = a.t ?? 0, c = a.r ?? 0, l = a.b ?? 0, u = o * t, d = s * n, m = t * (1 - o - c), h = n * (1 - s - l);
				i.save();
				try {
					i.beginPath(), i.rect(0, 0, t, n), i.clip(), e.alpha != null && (i.globalAlpha = e.alpha), e.tile ? za(i, p, e.tile, t, n, r, e.srcRect, f?.dimensions ?? void 0) : me(i, p, e.srcRect, u, d, m, h);
				} finally {
					i.restore();
				}
			} catch (r) {
				if (Ft(r, "tiff")) {
					jt(i, "tiff", {
						x: 0,
						y: 0,
						width: t,
						height: n
					});
					return;
				}
				if (r instanceof he || Ii(r) || Ot(r) || zt(r)) throw r;
				if (e.blipEffects?.length) throw Nt(r);
			}
		} };
	}
	return { paint: (i) => {
		i.fillStyle = qi(e, i, 0, 0, t, n, 0, r * 12700) ?? "#FFFFFF", i.fillRect(0, 0, t, n);
	} };
}
function La(e, t, n, r, i) {
	let a;
	a = e === "t" || e === "ctr" || e === "b" ? (t - r) / 2 : e === "tr" || e === "r" || e === "br" ? t - r : 0;
	let o;
	return o = e === "l" || e === "ctr" || e === "r" ? (n - i) / 2 : e === "bl" || e === "b" || e === "br" ? n - i : 0, {
		ax: a,
		ay: o
	};
}
function Ra(e, t, n) {
	return {
		width: e * (n ? 1 - n.l - n.r : 1),
		height: t * (n ? 1 - n.t - n.b : 1)
	};
}
function za(e, n, r, i, a, o, s, c, l = 96, d = {
	x: 0,
	y: 0,
	w: i,
	h: a
}) {
	let f = N(n), p = Ra(c?.width ?? f?.width ?? n.width, c?.height ?? f?.height ?? n.height, s), m = t * 96 / l, h = p.width * m * (r.sx ?? 1) * o, g = p.height * m * (r.sy ?? 1) * o;
	if (![h, g].every((e) => Number.isFinite(e) && e > 0)) throw new de("unsupported-image-effect", "Unrepresentable picture tile size");
	let _ = r.flip === "x" || r.flip === "xy", v = r.flip === "y" || r.flip === "xy", y = h * (_ ? 2 : 1), b = g * (v ? 2 : 1);
	if (!Number.isFinite(y) || !Number.isFinite(b)) throw new de("unsupported-image-effect", "Unrepresentable picture tile cell");
	let x = Math.max(1, Math.ceil(y)), S = Math.max(1, Math.ceil(b)), w = x > 32767 || S > 32767 || x > Math.floor(16777216 / S);
	if (w && (x = Math.min(u, Math.max(1, Math.ceil(Math.min(y, p.width * (_ ? 2 : 1))))), S = Math.min(u, Math.max(1, Math.ceil(Math.min(b, p.height * (v ? 2 : 1))))), x * S > 16777216)) {
		let e = Math.sqrt(ie / (x * S));
		x = Math.max(1, Math.floor(x * e)), S = Math.max(1, Math.floor(S * e));
	}
	let T = w ? x / y : 1, E = w ? S / b : 1, D = C(x, S);
	if (!D) throw new de("unsupported-image-effect", "Picture tile surface unavailable");
	let O = D.getContext("2d");
	if (!O) throw new de("unsupported-image-effect", "Picture tile surface unavailable");
	O.scale(T, E);
	let k = (e, t, r, i) => {
		O.save(), O.translate(e + (r ? h : 0), t + (i ? g : 0)), O.scale(r ? -1 : 1, i ? -1 : 1), me(O, n, s, 0, 0, h, g), O.restore();
	}, A = se(n, s != null), j = Math.ceil(A.x / (_ ? 2 : 1)), M = Math.ceil(A.y / (v ? 2 : 1)), P = (2 * j + 1) * (2 * M + 1) * (_ ? 2 : 1) * (v ? 2 : 1);
	if (!Number.isSafeInteger(P) || P > kt * kt) throw D.width = 0, new At("image-effect-work", kt * kt, Number.isSafeInteger(P) ? P : 2 ** 53 - 1);
	let F = P > (_ ? 2 : 1) * (v ? 2 : 1) ? P * x * S : 0;
	if (!Number.isSafeInteger(F) || F > 134217728) throw D.width = 0, new At("image-effect-work", Dt, Number.isSafeInteger(F) ? F : 2 ** 53 - 1);
	for (let e = -M; e <= M; e++) for (let t = -j; t <= j; t++) {
		let n = t * y, r = e * b;
		k(n, r, !1, !1), _ && k(n + h, r, !0, !1), v && k(n, r + g, !1, !0), _ && v && k(n + h, r + g, !0, !0);
	}
	let I = e.createPattern(D, "repeat");
	if (!I) throw new de("unsupported-image-effect", "Picture tile surface unavailable");
	let { ax: L, ay: R } = La(r.algn ?? "tl", i, a, h, g), z = L + Z(r.tx ?? 0, o), B = R + Z(r.ty ?? 0, o);
	if (![z, B].every(Number.isFinite)) throw new de("unsupported-image-effect", "Unrepresentable picture tile phase");
	return typeof I.setTransform == "function" && typeof DOMMatrix < "u" ? (I.setTransform(new DOMMatrix().translateSelf(z, B).scaleSelf(1 / T, 1 / E)), e.fillStyle = I, e.fillRect(d.x, d.y, d.w, d.h)) : (e.save(), e.translate(z, B), e.scale(1 / T, 1 / E), e.fillStyle = I, e.fillRect((d.x - z) * T, (d.y - B) * E, d.w * T, d.h * E), e.restore()), !0;
}
function Ba(e, t, n, r = {}) {
	return JSON.stringify([
		e.svgImagePath ?? null,
		Ri(e.imagePath, Q(e, r)),
		...e.blipEffects?.length ? ((e) => [e.width, e.height])(e.tile ? {
			width: t,
			height: n
		} : i(t, n, e.srcRect, e.fillRect)) : []
	]);
}
function Va(e) {
	return e.tile != null != (e.stretch === !0);
}
function Ha(e, t, n, r, i, a = !1, o = {
	rotation: 0,
	flipH: !1,
	flipV: !1
}) {
	if (![
		r.x,
		r.y,
		r.w,
		r.h,
		i
	].every(Number.isFinite) || r.w < 0 || r.h < 0) throw new de("unsupported-image-effect", "Invalid image fill geometry");
	if (!n || !(r.w > 0) || !(r.h > 0) || !Va(t)) return !1;
	let { x: s, y: c, w: l, h: u } = r;
	e.save();
	try {
		e.clip(a ? "evenodd" : "nonzero"), t.alpha != null && (e.globalAlpha *= Math.max(0, Math.min(1, t.alpha)));
		let r = 0;
		if (t.rotWithShape === !1 && o.rotation !== 0) {
			let t = o.flipH === o.flipV ? -1 : 1;
			e.translate(s + l / 2, c + u / 2), r = t * o.rotation * Math.PI / 180, e.rotate(r), e.translate(-(s + l / 2), -(c + u / 2));
		}
		if (t.tile) {
			let a = Math.abs(Math.cos(r)), o = Math.abs(Math.sin(r)), d = l * a + u * o, f = l * o + u * a;
			e.translate(s, c);
			try {
				return za(e, n.image, t.tile, l, u, i, t.srcRect, n.intrinsicSize, t.dpi && t.dpi > 0 ? t.dpi : 96, {
					x: (l - d) / 2,
					y: (u - f) / 2,
					w: d,
					h: f
				});
			} catch (e) {
				throw t.blipEffects?.length ? Nt(e) : e;
			}
		}
		let d = t.fillRect ?? {}, f = s + (d.l ?? 0) * l, p = c + (d.t ?? 0) * u, m = (1 - (d.l ?? 0) - (d.r ?? 0)) * l, h = (1 - (d.t ?? 0) - (d.b ?? 0)) * u;
		if (![
			f,
			p,
			m,
			h
		].every(Number.isFinite)) throw new de("unsupported-image-effect", "Unrepresentable image fill rectangle");
		return !(m > 0) || !(h > 0) ? !1 : (me(e, n.image, t.srcRect, f, p, m, h), !0);
	} finally {
		e.restore();
	}
}
function Ua(e, t, n) {
	if (!t) return;
	let r = t.dir * Math.PI / 180, i = Z(t.dist, n);
	e.shadowColor = $(t.color, t.alpha), e.shadowBlur = 0, e.shadowOffsetX = Math.cos(r) * i, e.shadowOffsetY = Math.sin(r) * i;
}
function Wa(e, t, n) {
	t && (e.shadowColor = $(t.color, t.alpha), e.shadowBlur = Z(t.radius, n), e.shadowOffsetX = 0, e.shadowOffsetY = 0);
}
function Ga(e) {
	e.shadowColor = "transparent", e.shadowBlur = 0, e.shadowOffsetX = 0, e.shadowOffsetY = 0;
}
var Ka = 8, qa = 1, Ja = 1, Ya = 256;
function Xa(e, t, n, r, i, a, o, s, c, l, u, d, f, p, m, h, g = 0) {
	if (r <= 0) return;
	let _ = e.measureText(t), v = _.actualBoundingBoxAscent > 0 ? _.actualBoundingBoxAscent : r, y = _.actualBoundingBoxDescent > 0 ? _.actualBoundingBoxDescent : r * .25, b = _.actualBoundingBoxLeft > 0 ? _.actualBoundingBoxLeft : 0, x = _.actualBoundingBoxRight > 0 ? _.actualBoundingBoxRight : r, S = r * l * i, w = Math.min(Ya, Math.max(1, Math.round(S / Ka))), T = (e) => $a(e, a, r, o, s, c, u, d), E = T(w), D = to(E, a, o, s, c, u, d, l, i, -v, y);
	for (; D > Ja && w < Ya;) {
		let e = Math.min(Ya, w * 2), t = T(e), n = to(t, a, o, s, c, u, d, l, i, -v, y);
		if (n >= D * .75) {
			E = t;
			break;
		}
		w = e, E = t, D = n;
	}
	let O = 1e4, k = qa / (l * i), A = E.length - 1, j = (e, t, n) => e === 0 ? -1e4 : t - n - k, M = (e, t, n) => e === A ? O : t - n + k, N = (e, r) => {
		g > 0 ? (e.strokeStyle = r, e.lineWidth = g, e.lineJoin = "round") : e.fillStyle = r;
		for (let r = 0; r <= A; r++) {
			let { s0: i, s1: a, g: o } = E[r], s = (i + a) / 2;
			e.save(), e.translate(f + o.x, p + o.y), e.rotate(o.angle), o.shear !== 0 && e.transform(1, 0, o.shear, 1, 0, 0), (l !== 1 || o.vScale !== 1) && e.scale(l, o.vScale), e.beginPath();
			let c = j(r, i, s), u = M(r, a, s);
			e.rect(c, -1e4, u - c, 2 * O), e.clip(), g > 0 ? e.strokeText(t, -s + n / 2, 0) : e.fillText(t, -s + n / 2, 0), e.restore();
		}
	}, P = typeof m == "string" ? Za(m) : 1, F = typeof e.globalAlpha == "number" ? e.globalAlpha : 1;
	if (typeof m == "string" && P >= 1 && F >= 1) {
		N(e, m);
		return;
	}
	if (P <= 0 || F <= 0) return;
	let I = typeof e.getTransform == "function" ? e.getTransform() : null;
	if (!I) {
		N(e, m);
		return;
	}
	let L = Infinity, R = Infinity, z = -Infinity, B = -Infinity;
	for (let e = 0; e <= A; e++) {
		let { s0: t, s1: r, g: i } = E[e], a = (t + r) / 2, o = -a + n / 2, s = Math.max(j(e, t, a), o - b), c = Math.min(M(e, r, a), o + x);
		if (!(c <= s)) for (let [e, t] of [
			[s, -v],
			[c, -v],
			[s, y],
			[c, y]
		]) {
			let n = eo(i, l, e, t), r = f + n.x, a = p + n.y, o = I.a * r + I.c * a + I.e, s = I.b * r + I.d * a + I.f;
			o < L && (L = o), o > z && (z = o), s < R && (R = s), s > B && (B = s);
		}
	}
	if (!(z > L && B > R)) return;
	let V = 2 + g * i, H = Math.floor(L - V), W = Math.floor(R - V), G = Math.ceil(z + V) - H, ee = Math.ceil(B + V) - W, te = C(G, ee), K = te ? te.getContext("2d") : null;
	if (!te || !K) {
		N(e, m);
		return;
	}
	K.font = e.font, K.textAlign = "left", K.textBaseline = "alphabetic", K.setTransform(I.a, I.b, I.c, I.d, I.e - H, I.f - W), typeof m == "string" ? N(K, Qa(m)) : (N(K, "#fff"), K.save(), K.setTransform(1, 0, 0, 1, 0, 0), K.globalCompositeOperation = "source-in", U(e, K, () => {
		K.fillStyle = h?.(K) ?? m, K.fillRect(0, 0, G, ee);
	}, {
		x: H,
		y: W
	}), K.restore()), e.save(), e.setTransform(1, 0, 0, 1, 0, 0), e.globalAlpha = F * P, e.drawImage(te, H, W), e.restore();
}
function Za(e) {
	let t = /^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*,\s*([\d.]+)\s*\)$/i.exec(e);
	if (!t) return 1;
	let n = parseFloat(t[1]);
	return Number.isFinite(n) ? Math.min(1, Math.max(0, n)) : 1;
}
function Qa(e) {
	let t = /^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i.exec(e);
	return t ? `rgb(${t[1]}, ${t[2]}, ${t[3]})` : e;
}
function $a(e, t, n, r, i, a, o, s) {
	let c = Array(e);
	for (let l = 0; l < e; l++) {
		let u = l / e * n, d = (l + 1) / e * n;
		c[l] = {
			s0: u,
			s1: d,
			g: Tt(t, (r + (u + d) / 2) / i * a, o, s)
		};
	}
	return c;
}
function eo(e, t, n, r) {
	let i = n * t, a = r * e.vScale, o = i + e.shear * a, s = Math.cos(e.angle), c = Math.sin(e.angle);
	return {
		x: e.x + s * o - c * a,
		y: e.y + c * o + s * a
	};
}
function to(e, t, n, r, i, a, o, s, c, l, u) {
	let d = 0;
	for (let f of e) {
		let e = (f.s0 + f.s1) / 2;
		for (let p of [f.s0, f.s1]) {
			let m = Tt(t, (n + p) / r * i, a, o);
			for (let t of [l, u]) {
				let n = eo(m, s, 0, t), r = eo(f.g, s, p - e, t), i = Math.hypot(r.x - n.x, r.y - n.y) * c;
				i > d && (d = i);
			}
		}
	}
	return d;
}
function no(e, t, n, r, i, a, o, s, c, l, u) {
	let d = i, f = a, m = Math.max(1, o), h = Math.max(1, s), g = bt(n, r, m, h);
	if (!g) return;
	let _ = t.defaultBold ?? !1, v = t.defaultItalic ?? !1, y = (t.defaultFontSize ?? 18) * V * c, b = [], x = 0;
	for (let n of t.paragraphs) {
		let t = n.defFontSize == null ? y : n.defFontSize * V * c, r = Pa(e, n, Infinity, t, n.defColor ? $(n.defColor) : l, c, 0, _, v, 1, void 0, u, 0);
		b.length > 0 && (x += (n.spaceBefore ?? 0) / 100 * V * c);
		for (let e of r) {
			let r = e.segments.reduce((e, t) => Math.max(e, t.sizePx), 0) || t, i = n.spaceLine?.type === "pts" ? n.spaceLine.val * V * c : r * 1.2 * (n.spaceLine ? n.spaceLine.val / 1e5 : 1);
			b.push({
				line: e,
				alignment: n.alignment,
				baseline: x + i * .8
			}), x += i;
		}
		x += (n.spaceAfter ?? 0) / 100 * V * c;
	}
	if (b.length === 0) return;
	e.save(), e.textAlign = "left", e.textBaseline = "alphabetic";
	let S = -1, C = () => {
		if (S >= 0) return S;
		let t = typeof e.getTransform == "function" ? e.getTransform() : null, n = t ? Math.abs(t.a * t.d - t.b * t.c) : 1;
		return S = n > 0 ? Math.sqrt(n) : 1, S;
	}, w = b.length, T = g.top[0], E = g.top[1], D = T && E ? (T.x - m / 2) * (E.y - h / 2) - (T.y - h / 2) * (E.x - m / 2) > 0 : !0, O = b[D ? w - 1 : 0].baseline;
	for (let t = 0; t < w; t++) {
		let { line: i, alignment: a } = b[t], o = t / w, s = (t + 1) / w, l = 0, u = 0, _ = 0, v = 0;
		for (let t of i.segments) {
			if (t.math) {
				l += t.math.width, u = Math.max(u, t.sizePx), _ = Math.max(_, t.math.ascent), v = Math.max(v, t.math.descent);
				continue;
			}
			e.font = t.font;
			let n = t.letterSpacingPx ?? 0, r = e.measureText(t.text);
			l += r.width + n * wa(t.text), u = Math.max(u, t.sizePx), r.actualBoundingBoxAscent > 0 && (_ = Math.max(_, r.actualBoundingBoxAscent)), r.actualBoundingBoxDescent > 0 && (v = Math.max(v, r.actualBoundingBoxDescent));
		}
		if (l <= 0) continue;
		let y = g.singleEdge ? Math.max(0, D ? O - b[t].baseline : b[t].baseline - O) : 0, x = y > 0 ? bt(n, r, m + 2 * y, h + 2 * y) ?? g : g, S = _ + v > 0 ? _ + v : u, T = g.singleEdge ? .8 : S > 0 ? _ / S : .8, E = g.singleEdge ? 1 : m / l, k = g.singleEdge ? S : S / (s - o), A = Ct(x, l), j = g.singleEdge ? (1 - A) * (a === "r" ? 1 : a === "ctr" ? .5 : 0) : 0, M = 0;
		for (let t of i.segments) {
			let n = M, r = g.singleEdge ? T : o + T * (s - o);
			if (t.math) {
				M += t.math.width;
				continue;
			}
			e.font = t.font;
			let i = ra(e, t, d, f, c);
			e.fillStyle = i;
			let a = t.outline && t.outline.width > 0 ? t.outline : void 0, u = a?.fill, _ = a ? u ? q(u, e, d, f, m, h, 0, c * V) : a.color ? `#${a.color}` : t.noFill ? void 0 : i : void 0, v = a ? Math.max(.5, Z(a.width, c)) : 0, b = t.letterSpacingPx ?? 0, S = [...t.text];
			for (let n of S) {
				let r = e.measureText(n).width + b;
				if (t.noFill && !_) {
					M += r;
					continue;
				}
				let p = g.singleEdge ? T : o + T * (s - o);
				if (!g.singleEdge && r > 0) {
					t.noFill || Xa(e, n, b, r, C(), g, M, l, A, E, k, p, d, f, i, t.patternFill ? (e) => ra(e, t, d, f, c) : void 0), _ && a && Xa(e, n, b, r, C(), g, M, l, A, E, k, p, d, f, _, u ? (e) => q(u, e, d, f, m, h, 0, c * 12700) ?? "rgba(0,0,0,0)" : void 0, v), M += r;
					continue;
				}
				let S = Tt(x, j + (M + r / 2) / l * A, k, p);
				S.x -= y, S.y -= y, e.save(), e.translate(d + S.x, f + S.y), e.rotate(S.angle), S.shear !== 0 && e.transform(1, 0, S.shear, 1, 0, 0), (E !== 1 || S.vScale !== 1) && e.scale(E, S.vScale), t.noFill || e.fillText(n, -r / 2 + b / 2, 0), _ && (e.strokeStyle = _, e.lineWidth = v, e.lineJoin = "round", e.strokeText(n, -r / 2 + b / 2, 0)), e.restore(), M += r;
			}
			if (t.underline && !t.underlineLineNoFill && M > n && (!t.noFill || t.underlineFill || t.underlineColor || t.underlineLine?.fill)) {
				let a = t.underlineFill ? q(t.underlineFill, e, d, f, m, h, 0, c * V) : t.underlineLine?.fill ? q(t.underlineLine.fill, e, d, f, m, h, 0, c * V) : t.underlineColor ?? i;
				if (a) {
					e.save(), e.strokeStyle = a, e.lineWidth = t.underlineLine?.width ? Z(t.underlineLine.width, c) : Math.max(1, t.sizePx * p), e.beginPath();
					let i = Math.max(2, Math.ceil((M - n) / 8));
					for (let t = 0; t <= i; t++) {
						let a = Tt(x, j + (n + (M - n) * t / i) / l * A, k, r), o = Math.max(2, e.lineWidth) * a.vScale, s = d + a.x - y - Math.sin(a.angle) * o, c = f + a.y + Math.cos(a.angle) * o;
						t === 0 ? e.moveTo(s, c) : e.lineTo(s, c);
					}
					e.stroke(), e.restore();
				}
			}
		}
	}
	e.restore();
}
function ro(e, t, n, r, i, a, o) {
	let s = Math.min(r, i);
	switch (e) {
		case "rightarrow":
		case "leftarrow": {
			let c = Math.min(Math.max(a ?? 5e4, 0), 1e5), l = s * Math.min(Math.max(o ?? 5e4, 0), 1e5) / 1e5, u = i * c / 2e5, d = n + i / 2 - u, f = 2 * u, p = Math.max(0, r - l);
			return e === "rightarrow" ? {
				tx: t,
				ty: d,
				tw: p,
				th: f
			} : {
				tx: t + l,
				ty: d,
				tw: p,
				th: f
			};
		}
		case "roundrect": {
			let e = s * Math.min(Math.max(a ?? 16667, 0), 1e5) / 1e5 * (1 - 1 / Math.SQRT2);
			return {
				tx: t + e,
				ty: n + e,
				tw: Math.max(0, r - 2 * e),
				th: Math.max(0, i - 2 * e)
			};
		}
		default: return null;
	}
}
function io(e, t) {
	return e.defaultTextColor ? $(e.defaultTextColor) : t.smartArtFallbackTextColor != null && Yr(e) ? t.smartArtFallbackTextColor : null;
}
function ao(e, t, n) {
	return {
		outerRotation: e,
		localFlipH: t,
		localFlipV: n
	};
}
function oo(e, t, n, r, i) {
	let a = [
		{
			x: t,
			y: n
		},
		{
			x: t + r,
			y: n
		},
		{
			x: t,
			y: n + i
		},
		{
			x: t + r,
			y: n + i
		}
	].map((t) => ({
		x: e.a * t.x + e.c * t.y + e.e,
		y: e.b * t.x + e.d * t.y + e.f
	})), o = Math.min(...a.map((e) => e.x)), s = Math.min(...a.map((e) => e.y)), c = Math.max(...a.map((e) => e.x)), l = Math.max(...a.map((e) => e.y));
	return {
		x: o,
		y: s,
		w: c - o,
		h: l - s
	};
}
var so = {
	tl: [0, 0],
	t: [.5, 0],
	tr: [1, 0],
	l: [0, .5],
	ctr: [.5, .5],
	r: [1, .5],
	bl: [0, 1],
	b: [.5, 1],
	br: [1, 1]
};
function co(e, t, n) {
	return [e.a * t + e.c * n + e.e, e.b * t + e.d * n + e.f];
}
function lo(e, t, r, i, a, o) {
	let [s, c] = n(so, o ?? "b") ?? so.b;
	return co(e, t + s * i, r + c * a);
}
function uo(e, t, n, r = 0, i = 0, a = 1) {
	r > (e.sceneDepth ?? 0) && (e = {
		...e,
		sceneDepth: r
	});
	let o = wn(e, t, n), [s, c, , l] = o.corners, u = (c.x - s.x) * (l.y - s.y) - (c.y - s.y) * (l.x - s.x) < 0, d = u && r > 0 ? wn(e, t, n, r) : o, f = Math.max(0, Math.ceil(i * a)), p = f > 0 ? Vt(d.corners, f / a / t, f / a / n) : null;
	return p || (f = 0), {
		face: d.corners,
		corners: p ?? d.corners,
		backFacing: u,
		padDev: f,
		padCss: f / a
	};
}
function fo(e, t, r, i, a, o, s, c, l = 0) {
	let u = Math.abs(t.a * t.d - t.b * t.c), { face: d, corners: f } = uo(e, a, o, l, s, u > 0 ? Math.sqrt(u) : 1), p = f.map((e) => co(t, r + e.x, i + e.y)), m = p.map(([e]) => e), h = p.map(([, e]) => e), g = Math.min(...m), _ = Math.min(...h), v = Math.max(...m), y = Math.max(...h), [b, x] = n(so, c ?? "b") ?? so.b, S = Bt(d, b, x);
	return {
		bbox: {
			x: g,
			y: _,
			w: v - g,
			h: y - _
		},
		anchor: S ? co(t, r + S.x, i + S.y) : lo(t, r, i, a, o, c)
	};
}
function po(e, t, n, r, i) {
	let a = Math.floor(n.x) - 1, o = Math.floor(n.y) - 1, s = Math.max(1, Math.ceil(n.x + n.w) - a + 1), c = Math.max(1, Math.ceil(n.y + n.h) - o + 1);
	if (r && (a + s <= 0 || o + c <= 0 || a >= r.w || o >= r.h) || S(s, c).clamped) return e;
	let l = null;
	try {
		l = C(s, c);
	} catch {
		return e;
	}
	let u = l?.getContext("2d");
	if (!l || !u) return e;
	u.setTransform(t.a, t.b, t.c, t.d, t.e - a, t.f - o), i ? U(i, u, () => e(u), {
		x: a,
		y: o
	}) : e(u);
	let d = {
		a: 1,
		b: 0,
		c: 0,
		d: 1,
		e: 0,
		f: 0
	};
	return (e) => {
		e.save(), e.setTransform(d), e.drawImage(l, a, o), e.restore();
	};
}
function mo(e, t, n, r, i, a, o, l, u, f = !0, p = !1) {
	let m = e.canvas.width || 0, h = e.canvas.height || 0, g = m > 0 && h > 0, _ = {
		a: 1,
		b: 0,
		c: 0,
		d: 1,
		e: 0,
		f: 0
	}, b = (e) => e.setTransform(u), S = (t) => {
		b(t), U(e, t, () => n(t));
	}, C = (t) => {
		b(t), U(e, t, () => r(t));
	}, w = p && g && (t.softEdge || t.innerShadow) ? s(C, d(i, t, l, m, h)) : null, T = !p || !!w, E = !1;
	t.shadow && g ? (e.save(), e.setTransform(_), E = !c(e, S, i, t.shadow, l, m, h, Math.atan2(u.b, u.a) * 180 / Math.PI, a), e.restore()) : t.shadow && (E = !0), t.reflection && g && (e.save(), e.setTransform(_), v(e, S, i, t.reflection, l, m, h), e.restore()), E ? Ua(e, t.shadow ?? null, o) : t.glow && Wa(e, t.glow, o), t.softEdge && g && T ? (e.save(), e.setTransform(_), x(e, S, i, t.softEdge, l, m, h, w ?? C), e.restore()) : n(e), (E || t.glow) && Ga(e), t.innerShadow && f && g && T && (e.save(), e.setTransform(_), y(e, w ?? C, i, t.innerShadow, l, m, h), e.restore());
}
function ho(e, t, n) {
	if (t === n || !Number.isFinite(e)) return e;
	let r = ((e + 180) % 360 + 360) % 360 - 180;
	return r > 90 ? r -= 180 : r < -90 && (r += 180), Object.is(r, -0) ? 0 : r;
}
function go(e, t, n, r) {
	return e === "wordArtVert" || e === "wordArtVertRtl" ? t + (r ? 180 : 0) : ho(t, n, r);
}
function _o(e, t, n, r = "#000000", i, a = {
	themeMajorFont: null,
	themeMinorFont: null,
	dpr: 1
}, o, s) {
	A(t.stroke?.fill) && (e = re(e));
	let c = Z(t.x, n), l = Z(t.y, n), u = Z(t.width, n), d = Z(t.height, n), f = o && t.id !== void 0 ? (e) => o({
		...e,
		shapeId: t.id
	}) : o;
	if (d === 0 && t.textBody?.verticalAnchor === "b") {
		if (t.stroke && (e.save(), e.beginPath(), e.moveTo(c, l), e.lineTo(c + u, l), Bo(e, t.stroke, n, {
			x: c,
			y: l,
			w: u,
			h: 1
		}, t.rotation), e.stroke(), e.restore()), t.textBody) {
			let o = io(t, a);
			To(e, t.textBody, c, l, u, d, n, o, t.rotation, t.flipH, t.flipV, r, i, a, f, !1, s);
		}
		return;
	}
	let p = t.scene3d && {
		...t.scene3d.camera,
		unitsPerInch: 914400 * n,
		sceneDepth: (t.sp3d?.extrusionH ?? 0) * n
	}, m = p && Tn(p, u, d, (t.sp3d?.extrusionH ?? 0) * n) ? {
		...t.scene3d,
		camera: p
	} : null;
	if (m && u > 0 && d > 0) {
		let o = e.getTransform(), s = Math.abs(o.a * o.d - o.b * o.c), f = s > 0 ? Math.sqrt(s) : 1, p = Eo(t.sp3d, t.scene3d?.lightRig, t.sp3d?.prstMaterial, n, f, m.camera), h = (p.find((e) => e.bottom)?.depthPx ?? 0) / f, g = Do(t.sp3d, m.camera, u, d, n, f), _ = ao(t.rotation, t.flipH, t.flipV);
		e.save(), _.outerRotation !== 0 && (e.translate(c + u / 2, l + d / 2), e.rotate(_.outerRotation * Math.PI / 180), e.translate(-(c + u / 2), -(l + d / 2)));
		let v = {
			...t,
			x: 0,
			y: 0,
			rotation: 0,
			flipH: _.localFlipH,
			flipV: _.localFlipV,
			scene3d: void 0,
			sp3d: void 0,
			shadow: null,
			innerShadow: void 0,
			glow: void 0,
			softEdge: void 0,
			reflection: void 0
		}, y = {
			...v,
			textBody: null
		}, b = {
			...v,
			fill: null,
			stroke: null
		}, x = (t.stroke ? t.stroke.width * n / 2 : 0) + (t.sp3d?.contourW ? t.sp3d.contourW * n : 0) + (g ? Math.hypot(g.offsetX, g.offsetY) / f : 0) + 2, S = t.textBody?.paragraphs.some((e) => e.runs.some((e) => e.type === "text" && (e.patternFill?.fillType === "pattern" || e.outline?.fill?.fillType === "pattern" || e.underlineFill?.fillType === "pattern"))) ?? !1, C = (e, t, o, s = !1) => ko(e, m.camera, c, l, u, d, (e) => {
			_o(e, t, n, r, i, a, void 0);
		}, o ? {
			bevels: p,
			backDepthCss: h,
			extrusion: g ?? void 0,
			edgePadCss: x,
			preservePatternFrame: s
		} : {
			preservePatternFrame: s,
			measureOverflow: !0
		}), w = (e) => C(e, y, !0), T = (e) => {
			!t.textBody || C(e, b, !1, S) || (e.save(), e.translate(c, l), _o(e, b, n, r, i, a), e.restore());
		};
		if (t.shadow || t.innerShadow || t.glow || t.softEdge || t.reflection) {
			let r = e.getTransform(), i = Math.abs(r.a * r.d - r.b * r.c), a = i > 0 ? Math.sqrt(i) : 1, o = fo(m.camera, r, c, l, u, d, x, t.shadow?.algn, h), s = !1, f = po((e) => {
				s = w(e) || s;
			}, r, o.bbox, {
				w: e.canvas.width || 0,
				h: e.canvas.height || 0
			}, e);
			if (mo(e, t, f, f, o.bbox, o.anchor, n, n * a, r, !!t.fill, !0), s) {
				T(e), e.restore();
				return;
			}
		} else if (p.length > 0 ? w(e) : C(e, v, !0, S && t.fill?.fillType !== "pattern")) {
			p.length > 0 && T(e), e.restore();
			return;
		}
		e.restore();
	}
	e.save(), (t.rotation !== 0 || t.flipH || t.flipV) && (e.translate(c + u / 2, l + d / 2), e.rotate(t.rotation * Math.PI / 180), t.flipH && e.scale(-1, 1), t.flipV && e.scale(1, -1), e.translate(-(c + u / 2), -(l + d / 2)));
	let h = t.geometry.toLowerCase(), g = (e, n, r, i, a) => {
		t.custGeom ? vo(e, t.custGeomPaint?.length === t.custGeom.length ? t.custGeom.filter((e, n) => t.custGeomPaint?.[n].fill !== "none") : t.custGeom, n, r, i, a) : qe(e, h, n, r, i, a, [
			t.adj,
			t.adj2,
			t.adj3,
			t.adj4,
			t.adj5,
			t.adj6,
			t.adj7,
			t.adj8
		]) || _e(e, h, n, r, i, a, t.adj, t.adj2, t.adj3, t.adj4);
	}, _ = qi(t.fill, e, c, l, u, d, t.rotation, n * V, g), v = t.fill?.fillType === "image" && Va(t.fill) ? t.fill : null, y = v ? a.shapeFillImages?.get(Ba(v, t.width, t.height, t)) : void 0, b = !!(v && y);
	t.shadow || Wa(e, t.glow ?? null, n);
	let x = new Set([
		"line",
		"straightconnector1",
		"bentconnector2",
		"bentconnector3",
		"bentconnector4",
		"bentconnector5",
		"curvedconnector2",
		"curvedconnector3",
		"curvedconnector4",
		"curvedconnector5"
	]), S = new Set([
		"callout1",
		"callout2",
		"callout3",
		"bordercallout1",
		"bordercallout2",
		"bordercallout3",
		"accentcallout1",
		"accentcallout2",
		"accentcallout3",
		"accentbordercallout1",
		"accentbordercallout2",
		"accentbordercallout3"
	]), C = (e) => S.has(e) || e === "line" || e === "straightconnector1" || e.startsWith("bentconnector"), w = t.stroke?.headEnd ? ye(t.stroke.headEnd, t.stroke, n, V) : 0, T = t.stroke?.tailEnd ? ye(t.stroke.tailEnd, t.stroke, n, V) : 0, E = h.startsWith("curvedconnector") && (w > 0 || T > 0), D = t.custGeom && t.custGeom.length > 0 && (w > 0 || T > 0) ? Ae(t.custGeom) : null, O = D?.start ? w : 0, k = D?.end ? T : 0, j = !t.custGeom && De(h), M = (r, i, a = {
		x: c,
		y: l,
		w: u,
		h: d
	}) => {
		A(t.stroke?.fill) && (r = re(r));
		let { x: o, y: s, w: f, h: p } = a, m = i ?? (r === e && o === c && s === l && f === u && p === d ? _ : qi(t.fill, r, o, s, f, p, t.rotation, n * 12700, g)), b = i ? null : t.stroke ? () => {
			Bo(r, t.stroke, n, {
				x: o,
				y: s,
				w: f,
				h: p
			}, t.rotation), r.stroke();
		} : null, x = () => Ga(r), S = h === "donut" || h === "smileyface" || h === "frame", w = v && !i ? (e) => Ha(e, v, y, {
			x: o,
			y: s,
			w: f,
			h: p
		}, n, S, t) : void 0;
		if (j && !i) {
			let e = C(h) || E;
			Re(r, h, o, s, f, p, [
				t.adj,
				t.adj2,
				t.adj3,
				t.adj4,
				t.adj5,
				t.adj6,
				t.adj7,
				t.adj8
			], m, b, x, e || w ? {
				...e ? { skipTrailingStroke: !0 } : {},
				paintFill: w
			} : void 0);
			return;
		}
		let T = t.custGeom && t.custGeomPaint?.length === t.custGeom.length ? t.custGeomPaint : null;
		if (t.custGeom && T) {
			let e = !1;
			t.custGeom.forEach((n, a) => {
				let c = T[a], l = c.fill !== "none";
				if (!(i && !l)) {
					if (r.beginPath(), vo(r, [n], o, s, f, p), l) {
						let t = !1;
						if (w) {
							r.save();
							try {
								t = w(r);
							} finally {
								r.restore();
							}
						} else m && (r.fillStyle = m, r.fill(), t = !0);
						let n = t && !i ? Se(c.fill) : null;
						n && (r.save(), r.fillStyle = n, r.fill(), r.restore()), t && !i && !e && (x(), e = !0);
					}
					if (c.stroke && b) {
						let e = a === 0 ? O : 0, i = a === t.custGeom.length - 1 ? k : 0;
						(e > 0 || i > 0) && (r.beginPath(), ke(r, (e) => vo(e, [n], o, s, f, p), e, i)), b();
					}
				}
			});
			return;
		}
		r.beginPath(), t.custGeom && t.custGeom.length > 0 ? vo(r, t.custGeom, o, s, f, p) : _e(r, h, o, s, f, p, t.adj, t.adj2, t.adj3, t.adj4), w && h !== "arc" ? w(r) && x() : m && h !== "arc" && (r.fillStyle = m, S ? r.fill("evenodd") : r.fill(), i || x()), b && (t.custGeom && t.custGeom.length > 0 && (O > 0 || k > 0) && (r.beginPath(), ke(r, (e) => vo(e, t.custGeom, o, s, f, p), O, k)), b());
	}, N = e.getTransform(), P = Math.abs(N.a * N.d - N.b * N.c), F = P > 0 ? Math.sqrt(P) : 1, I = (t.shadow || t.reflection || t.softEdge || t.innerShadow ? j ? Ee(h, c, l, u, d, [
		t.adj,
		t.adj2,
		t.adj3,
		t.adj4,
		t.adj5,
		t.adj6,
		t.adj7,
		t.adj8
	]) : t.custGeom && t.custGeom.length > 0 ? Ce(t.custGeom, c, l, u, d) : null : null) ?? {
		x: c,
		y: l,
		w: u,
		h: d
	}, L = t.stroke ? t.stroke.width * n / 2 : 0, R = t.stroke ? Math.max(t.stroke.headEnd ? xe(t.stroke.headEnd, t.stroke, n, V) : 0, t.stroke.tailEnd ? xe(t.stroke.tailEnd, t.stroke, n, V) : 0) : 0, z = t.sp3d?.contourW ? t.sp3d.contourW * n : 0, B = Math.max(L, R, z), H = B > 0 ? {
		x: I.x - B,
		y: I.y - B,
		w: I.w + B * 2,
		h: I.h + B * 2
	} : I, U = oo(N, H.x, H.y, H.w, H.h), W = n * F, G = m ? [] : Eo(t.sp3d, t.scene3d?.lightRig, t.sp3d?.prstMaterial, n, F, t.scene3d?.camera), ee = (t.stroke ? t.stroke.width * n / 2 : 0) + 2, te = (e) => {
		if (A(t.stroke?.fill) && (e = re(e)), t.stroke && (x.has(h) || S.has(h))) {
			let r = Te(h, c, l, u, d, [
				t.adj,
				t.adj2,
				t.adj3,
				t.adj4,
				t.adj5,
				t.adj6,
				t.adj7,
				t.adj8
			]);
			if (!r) return;
			let i = t.stroke.cmpd, a = h === "line" || h === "straightconnector1";
			if (C(h) && r.vertices.length >= 2 && !(i && a)) {
				let i = r.vertices;
				e.beginPath(), ke(e, (e) => {
					e.moveTo(i[0].x, i[0].y);
					for (let t = 1; t < i.length; t++) e.lineTo(i[t].x, i[t].y);
				}, w, T), Bo(e, t.stroke, n, {
					x: c,
					y: l,
					w: u,
					h: d
				}, t.rotation), e.stroke();
			}
			if (E && (e.beginPath(), ut(e, h, c, l, u, d, [
				t.adj,
				t.adj2,
				t.adj3,
				t.adj4,
				t.adj5,
				t.adj6,
				t.adj7,
				t.adj8
			], w, T) && (Bo(e, t.stroke, n, {
				x: c,
				y: l,
				w: u,
				h: d
			}, t.rotation), e.stroke())), i && a) {
				let a = Je([{
					op: "moveTo",
					args: [r.start.x, r.start.y]
				}, {
					op: "lineTo",
					args: [r.end.x, r.end.y]
				}], w, T);
				a.length === 2 && zo(e, {
					x: a[0].args[0],
					y: a[0].args[1]
				}, {
					x: a[1].args[0],
					y: a[1].args[1]
				}, t.stroke, i, n, t.rotation);
			}
			t.stroke.tailEnd && dt(e, r.end.x, r.end.y, r.end.angle, t.stroke.tailEnd, t.stroke, n, V, ve(e, t.stroke, n, V, r.end.x, r.end.y, r.end.angle, t.stroke.tailEnd, {
				x: c,
				y: l,
				w: u,
				h: d
			}, t.rotation, n * V)), t.stroke.headEnd && dt(e, r.start.x, r.start.y, r.start.angle, t.stroke.headEnd, t.stroke, n, V, ve(e, t.stroke, n, V, r.start.x, r.start.y, r.start.angle, t.stroke.headEnd, {
				x: c,
				y: l,
				w: u,
				h: d
			}, t.rotation, n * V));
			return;
		}
		if (!t.stroke || !t.custGeom || t.custGeom.length === 0 || (!t.stroke.headEnd || t.stroke.headEnd.type === "none") && (!t.stroke.tailEnd || t.stroke.tailEnd.type === "none")) return;
		let { start: r, end: i } = Ae(t.custGeom);
		r && t.stroke.headEnd && t.stroke.headEnd.type !== "none" && dt(e, c + r.x * u, l + r.y * d, Math.atan2(r.dy * d, r.dx * u), t.stroke.headEnd, t.stroke, n, V, ve(e, t.stroke, n, V, c + r.x * u, l + r.y * d, Math.atan2(r.dy * d, r.dx * u), t.stroke.headEnd, {
			x: c,
			y: l,
			w: u,
			h: d
		}, t.rotation, n * V)), i && t.stroke.tailEnd && t.stroke.tailEnd.type !== "none" && dt(e, c + i.x * u, l + i.y * d, Math.atan2(i.dy * d, i.dx * u), t.stroke.tailEnd, t.stroke, n, V, ve(e, t.stroke, n, V, c + i.x * u, l + i.y * d, Math.atan2(i.dy * d, i.dx * u), t.stroke.tailEnd, {
			x: c,
			y: l,
			w: u,
			h: d
		}, t.rotation, n * V));
	}, K = (e) => {
		if (G.length > 0 && Ao(e, c, l, u, d, G, (e, t, n, r, i) => M(e, void 0, {
			x: t,
			y: n,
			w: r,
			h: i
		}), void 0, ee)) {
			te(e), Ga(e);
			return;
		}
		M(e), te(e);
	};
	if (mo(e, t, K, K, U, lo(N, c, l, u, d, t.shadow?.algn), n, W, N, !!_ || b, !0), t.textBody) {
		let o = io(t, a), p = go(t.textBody.vert, t.rotation, t.flipH, t.flipV);
		if (e.save(), t.flipH || t.flipV) {
			let n = c + u / 2, r = l + d / 2;
			e.translate(n, r), t.flipH && e.scale(-1, 1), t.flipV && e.scale(1, -1), e.translate(-n, -r);
		}
		let m = (p - t.rotation) % 360;
		if (m !== 0) {
			let t = c + u / 2, n = l + d / 2;
			e.translate(t, n), e.rotate(m * Math.PI / 180), e.translate(-t, -n);
		}
		let g = c, _ = l, v = u, y = d;
		if (t.textRect) g = Z(t.textRect.x, n), _ = Z(t.textRect.y, n), v = Z(t.textRect.width, n), y = Z(t.textRect.height, n);
		else if (h === "ellipse") {
			let e = u * (1 - 1 / Math.SQRT2) / 2, t = d * (1 - 1 / Math.SQRT2) / 2;
			g = c + e, _ = l + t, v = u / Math.SQRT2, y = d / Math.SQRT2;
		} else {
			let e = ro(h, c, l, u, d, t.adj, t.adj2);
			e && (g = e.tx, _ = e.ty, v = e.tw, y = e.th);
		}
		To(e, t.textBody, g, _, v, y, n, o, p, !1, !1, r, i, a, f, !1, s), e.restore();
	}
	e.restore();
}
var vo = je;
function yo(e, t) {
	let n = `${e}`, r = e >= 1 && e <= 26 ? String.fromCharCode(96 + e) : n, i = e >= 1 && e <= 26 ? String.fromCharCode(64 + e) : n, a = bo(e).toLowerCase(), o = bo(e), s = n.replace(/[0-9]/g, (e) => String.fromCharCode(65296 + (e.charCodeAt(0) - 48)));
	switch (t) {
		case "arabicPlain": return n;
		case "arabicPeriod": return `${n}.`;
		case "arabicParenR": return `${n})`;
		case "arabicParenBoth": return `(${n})`;
		case "arabicDbPlain": return s;
		case "arabicDbPeriod": return `${s}.`;
		case "alphaLcPlain": return r;
		case "alphaLcPeriod": return `${r}.`;
		case "alphaLcParenR": return `${r})`;
		case "alphaLcParenBoth": return `(${r})`;
		case "alphaUcPlain": return i;
		case "alphaUcPeriod": return `${i}.`;
		case "alphaUcParenR": return `${i})`;
		case "alphaUcParenBoth": return `(${i})`;
		case "romanLcPlain": return a;
		case "romanLcPeriod": return `${a}.`;
		case "romanLcParenR": return `${a})`;
		case "romanLcParenBoth": return `(${a})`;
		case "romanUcPlain": return o;
		case "romanUcPeriod": return `${o}.`;
		case "romanUcParenR": return `${o})`;
		case "romanUcParenBoth": return `(${o})`;
		default: return `${n}.`;
	}
}
function bo(e) {
	let t = [
		1e3,
		900,
		500,
		400,
		100,
		90,
		50,
		40,
		10,
		9,
		5,
		4,
		1
	], n = [
		"M",
		"CM",
		"D",
		"CD",
		"C",
		"XC",
		"L",
		"XL",
		"X",
		"IX",
		"V",
		"IV",
		"I"
	], r = "";
	for (let i = 0; i < t.length; i++) for (; e >= t[i];) r += n[i], e -= t[i];
	return r;
}
function xo(e) {
	for (let t of e.runs) if (t.type === "text" && t.text !== "" || t.type === "math") return !0;
	return !1;
}
function So(e, t) {
	let n = xo(e);
	if (e.bullet.type === "char") return t.clear(), n ? vt(e.bullet.char, e.bullet.fontFamily ?? null) : "";
	if (e.bullet.type === "autoNum") {
		if (!n) return "";
		let r = e.lvl;
		return t.has(r) ? t.set(r, t.get(r) + 1) : t.set(r, e.bullet.startAt ?? 1), yo(t.get(r), e.bullet.numType);
	}
	return t.clear(), "";
}
function Co(e, t, n, r, i, a, o) {
	let s = (() => {
		for (let e of t.runs) if (e.type === "text" && e.fontSize != null) return e.fontSize;
		return t.defFontSize ?? e.defaultFontSize ?? 18;
	})() * V * r * i, c = (() => {
		for (let e of t.runs) if (e.type === "text" && e.color) return e.color;
		return null;
	})(), l = c ? $(c) : n, u = (() => {
		for (let e of t.runs) if (e.type === "text" && e.fontFamily) return e.fontFamily;
		return t.defFontFamily ?? null;
	})(), d = "", f = s, p = "sans-serif", m = pa(!1, !1, f, p, a), h = l, g = !1, _ = null;
	d = So(t, o);
	let v = Nr(t.bullet);
	if (v.type === "char") {
		let e = v, t = e.sizePts == null ? e.sizePct == null ? s : s * (e.sizePct / 100) : e.sizePts * V * r * i, n = d === e.char ? ia(e.fontFamily ?? null, a) : "sans-serif";
		f = t, p = n, m = pa(!1, !1, t, n, a, d, aa(e.fontFamily)), h = e.color ? $(e.color) : l, g = !e.color;
	} else if (v.type === "autoNum") {
		let e = v, t = e.sizePts == null ? e.sizePct == null ? s : s * (e.sizePct / 100) : e.sizePts * V * r * i;
		f = t, p = ia(e.fontFamily ?? u, a), m = pa(!1, !1, t, ia(e.fontFamily ?? u, a), a, d, aa(e.fontFamily ?? u)), h = v.color ? $(v.color) : l, g = !v.color;
	} else if (v.type === "blip") {
		let n = v, a = ya(e, t, n) * V * r * i;
		_ = {
			imagePath: n.imagePath,
			mimeType: n.mimeType,
			sizePx: a
		};
	}
	return {
		bulletLabel: d,
		bulletFont: m,
		bulletSizePx: f,
		bulletFamily: p,
		bulletColor: h,
		bulletFollowsText: g,
		bulletImage: _
	};
}
function wo(e, t, n, r, i, a, o, s, c, l, u, d, f, p) {
	let m = t.autoFit === "norm" && t.fontScale != null && t.fontScale > 0 && t.fontScale < 1 ? t.fontScale : 1, h = V * s, g = sn(a, o, {
		lIns: t.lIns,
		rIns: t.rIns,
		tIns: t.tIns,
		bIns: t.bIns
	}, s), _ = (t.defaultFontSize ?? 18) * h * m, v = t.defaultBold ?? !1, y = t.defaultItalic ?? !1, b, x = /* @__PURE__ */ new Map(), S = [], C = t.paragraphs.map((n) => {
		let r = n.defFontSize == null ? _ : n.defFontSize * h * m, i = n.defColor ? $(n.defColor) : c, a = Co(t, n, i, s, m, d, x);
		S.push(a);
		let o = Oa(n, r, i, s, v, y, m, u, d, !1, e);
		b ??= o.sameStyle;
		let l = o.input.find((e) => e.type === "text"), f = ia(n.defFontFamily ?? null, d), p = n.defBold ?? v, g = n.defItalic ?? y, C = l?.type === "text" ? l.style : {
			text: "",
			font: pa(p, g, r, f, d, ""),
			sizePx: r,
			color: i,
			underline: !1,
			strikethrough: !1,
			lineMetric: ma(f, p, g, d),
			faceFamily: f
		}, w = n.endRunProperties, T = w ? Oa({
			...n,
			runs: [{
				...w,
				text: " "
			}]
		}, r, i, s, v, y, m, u, d, !1, e).input.find((e) => e.type === "text") : void 0, E = (e, t) => t == null ? e == null ? void 0 : {
			type: "pts",
			val: e / 100 * m
		} : {
			type: "pct",
			val: t
		};
		return {
			runs: o.input,
			marginStart: Z(n.marL, s),
			marginEnd: Z(n.marR, s),
			firstLineIndent: Z(n.indent, s),
			marker: xo(n) ? a.bulletLabel ? {
				text: a.bulletLabel,
				style: {
					text: a.bulletLabel,
					font: a.bulletFont,
					sizePx: a.bulletSizePx,
					color: a.bulletColor,
					underline: !1,
					strikethrough: !1,
					faceFamily: a.bulletFamily,
					lineMetric: ma(a.bulletFamily, !1, !1, d)
				}
			} : a.bulletImage ? {
				advance: a.bulletImage.sizePx,
				thickness: a.bulletImage.sizePx
			} : void 0 : void 0,
			alignment: n.alignment,
			lineSpacing: n.spaceLine?.type === "pts" ? {
				type: "pts",
				val: vr(n.spaceLine.val)
			} : n.spaceLine,
			spaceBefore: E(n.spaceBefore, n.spaceBeforePct),
			spaceAfter: E(n.spaceAfter, n.spaceAfterPct),
			markStyle: C,
			endMarkStyle: T?.type === "text" ? T.style : void 0,
			eastAsianLineBreak: n.eaLnBrk !== !1
		};
	}), w = si(e, {
		vert: n,
		rect: {
			left: r + g.left,
			top: i + g.top,
			width: g.width,
			height: g.height
		},
		anchor: t.verticalAnchor,
		anchorCtr: t.anchorCtr === !0,
		wrap: t.wrap !== "none",
		spcFirstLastPara: t.spcFirstLastPara === !0,
		lnSpcReduction: t.autoFit === "norm" ? t.lnSpcReduction ?? 0 : 0,
		pxPerPt: h,
		paragraphs: C,
		sameStyle: b,
		drawMarker: (n, r, i) => {
			let a = t.paragraphs[n];
			if (!xo(a)) return;
			let o = S[n], s = i;
			e.save();
			let c = o.bulletImage, l = c ? d.pictureBulletImages?.has(c.imagePath) ? d.pictureBulletImages.get(c.imagePath) : p ? tt(c.imagePath, p) : null : null;
			if (c && l) {
				let t = c.sizePx, n = l.height > 0 ? t * l.width / l.height : t;
				e.drawImage(l, r - n / 2, s, n, t);
			}
			e.restore();
		},
		markerPaint: (t, n, r) => {
			let i = S[t], a = C[t].runs.find((e) => e.type === "text");
			return i.bulletFollowsText && a?.type === "text" && a.style.patternFill ? ra(e, a.style, n, r, s) : i.bulletColor;
		},
		drawUnderline: (t, n, r) => {
			Ji(e, t, 0, 0, n, t.color, s, d.dpr, r);
		},
		sameUnderline: (e, t) => e.underline === t.underline && e.underlineStyle === t.underlineStyle && e.underlineColor === t.underlineColor && e.underlineFill === t.underlineFill && e.underlineLine === t.underlineLine && e.underlineLineNoFill === t.underlineLineNoFill && e.color === t.color && e.noFill === t.noFill,
		drawStrike: (t, n, r, i) => {
			Yi(e, t, n, r, i, t.color, d.dpr);
		},
		drawObject: (t, n, r) => {
			na(e, t, n, r);
		}
	});
	if (f) for (let e of w) f({
		text: e.text,
		inShapeX: e.x - r,
		inShapeY: e.y - i,
		w: e.w,
		h: e.h,
		fontSize: e.fontSize,
		font: e.font,
		shapeX: r,
		shapeY: i,
		shapeW: a,
		shapeH: o,
		rotation: l,
		...e.hyperlink ? { hyperlink: e.hyperlink } : {}
	});
}
function To(e, t, n, r, i, a, o, s = null, c = 0, l = !1, u = !1, d = "#000000", f, p = {
	themeMajorFont: null,
	themeMinorFont: null,
	dpr: 1
}, m, h = !1, g, _ = !1, v = h) {
	if (t.vert === "wordArtVert" || t.vert === "wordArtVertRtl") {
		if (h) return i;
		wo(e, t, t.vert, n, r, i, a, o, s ?? d, c, f, p, m, g);
		return;
	}
	let y = t.vert === "vert" || t.vert === "eaVert", b = t.vert === "vert270";
	if (y || b) {
		let l = n + i / 2, u = r + a / 2, _ = y ? 90 : -90, v = m ? (e) => m({
			...e,
			inShapeX: e.inShapeX - a / 2 + i / 2,
			inShapeY: e.inShapeY - i / 2 + a / 2,
			shapeX: n,
			shapeY: r,
			shapeW: i,
			shapeH: a,
			rotation: c,
			textBodyRotation: _
		}) : void 0;
		if (h) return i;
		e.save(), e.translate(l, u), e.rotate(b ? -Math.PI / 2 : Math.PI / 2), To(e, {
			...t,
			vert: "horz"
		}, -a / 2, -i / 2, a, i, o, s, 0, !1, !1, d, f, p, v, !1, g, t.vert === "eaVert"), e.restore();
		return;
	}
	let x = t.textWarp;
	if (!h && x && ht(x.preset)) {
		no(e, t, x.preset, x.adj ?? [], n, r, i, a, o, s ?? d, p);
		return;
	}
	let S = sn(i, a, {
		lIns: t.lIns,
		rIns: t.rIns,
		tIns: t.tIns,
		bIns: t.bIns
	}, o).left, C = Z(t.rIns, o), w = Z(t.tIns, o), T = Z(t.bIns, o), E = t.wrap !== "none", D = t.autoFit === "sp", O = D ? E && Ca(e, t, i, S, C, o, p, f) : E, k = Math.max(1, t.numCol ?? 1), A = Z(t.spcCol ?? 0, o), j = t.defaultBold ?? !1, M = t.defaultItalic ?? !1, N = s ?? d, P = (r, a) => {
		let s = (t.defaultFontSize ?? 18) * V * o * r, c = [], l = 0, u = 0, d = a, m = /* @__PURE__ */ new Map();
		for (let g = 0; g < t.paragraphs.length; g++) {
			let _ = t.paragraphs[g], y = Z(_.marL, o), b = Z(_.marR, o), x = Z(_.indent, o), w = _.defFontSize == null ? s : _.defFontSize * V * o * r, T = _.defColor ? $(_.defColor) : N, E = va(_), { bulletLabel: P, bulletFont: F, bulletColor: I, bulletFollowsText: L, bulletImage: R } = Co(t, _, T, o, r, p, m), z = k > 1 ? (i - S - C - (k - 1) * A) / k : i - S - C, B = n + S + y, H = n + S + y + x, U = z - y - b, W = Pa(e, _, O ? U : Infinity, w, T, o, y, j, M, r, f, p, ba(E, x)), G = _.spaceBefore == null ? 0 : _.spaceBefore / 100 * V * o * r, ee = _.spaceAfter == null ? 0 : _.spaceAfter / 100 * V * o * r;
			for (let n = 0; n < W.length; n++) {
				let r = W[n], i = n === 0, s = n === W.length - 1, f = 0, p = [], m = 0;
				for (let t of r.segments) {
					let n = t.math ? Math.max(t.sizePx, (t.math.ascent + t.math.descent) / 1.2) : t.sizePx;
					if (n > f && (f = n), t.math ? d = !1 : t.isTab || (t.lineMetric === void 0 ? d = !1 : p.push({
						sizePx: t.sizePx,
						face: t.lineMetric
					}), t.lineMetricLatin === null ? d = !1 : t.lineMetricLatin !== void 0 && t.lineMetricLatin !== t.lineMetric && p.push({
						sizePx: t.sizePx,
						face: t.lineMetricLatin
					})), !t.math && D) {
						e.font = t.font;
						let n = e.measureText(t.text || "M"), r = (n.fontBoundingBoxAscent ?? 0) + (n.fontBoundingBoxDescent ?? 0);
						r > m && (m = r);
					}
				}
				for (let e of r.metricMarks ?? []) e.lineMetric !== void 0 && p.push({
					sizePx: e.sizePx,
					face: e.lineMetric
				});
				f === 0 && (f = w);
				let y = f;
				if (i && P) {
					e.font = F;
					let t = e.measureText("M"), n = t.actualBoundingBoxAscent + t.actualBoundingBoxDescent;
					n > f && (f = n);
				}
				i && R && R.sizePx > f && (f = R.sizePx), f > y && (d = !1);
				let b = f * 1.2, S = D && m > b, C = Xt(b, _.spaceLine, V * o);
				h && !D && !v && (_.spaceLine || (C = f));
				let T = _.spaceLine?.type === "pct" ? f * (_.spaceLine.val / 1e5) : C;
				t.autoFit === "norm" && t.lnSpcReduction != null && _.spaceLine?.type !== "pts" && (C *= 1 - t.lnSpcReduction);
				let O, k, A = b, j = t.compatLnSpc === !1;
				a && d && j && (p.length === 0 || p.some((e) => e.face.excel === void 0)) && (d = !1);
				let M = _.fontAlgn, N;
				if (a && d && M && p.length > 0) {
					let e = br * V * o;
					N = Sr(M, p, j, e), N || (d = !1);
					for (let t of r.segments) {
						if (!N) break;
						if (t.isTab || !t.text) continue;
						let n = t.lineMetric && Cr(M, {
							sizePx: t.sizePx,
							face: t.lineMetric
						}, j, e);
						if (n === void 0) {
							d = !1;
							break;
						}
						t.fontAlgnOffsetPx = n;
					}
				}
				if (a && d && !(h && !D && !v && !_.spaceLine)) {
					let e = N ?? (j ? yr(p.map((e) => ({
						sizePx: e.sizePx,
						box: e.face.excel
					}))) : p.length > 0 ? _r(p.map((e) => ({
						sizePx: e.sizePx,
						share: e.face.share
					}))) : {
						ascent: b * .8,
						descent: b * .2
					});
					j && (A = e.ascent + e.descent);
					let n = Gt(e, _.spaceLine?.type === "pts" ? {
						type: "pts",
						val: vr(_.spaceLine.val)
					} : _.spaceLine, V * o, t.autoFit === "norm" && t.lnSpcReduction != null ? t.lnSpcReduction : 0);
					C = n.ascent + n.descent, O = n.ascent, k = e.descent;
				}
				let z = s && _.spaceAfterPct != null ? A * (_.spaceAfterPct / 1e5) : ee, te = i && _.spaceBeforePct != null ? A * (_.spaceBeforePct / 1e5) : G, K = t.spcFirstLastPara === !0, ne = g === t.paragraphs.length - 1, q = C + (s && (K || !ne) ? z : 0), J = i && (K || g > 0) ? te : 0, re = i ? ba(E, x) : 0, ie = r.segments.some((e) => e.text && e.text.length > 0 || e.math != null), ae = i && ie ? R : null;
				c.push({
					line: r,
					linePx: q,
					lineHeight: C,
					baselineLineHeight: T,
					topGapPx: J,
					textXOffset: re,
					bulletLabel: i ? P : "",
					bulletFont: F,
					bulletColor: I,
					bulletFollowsText: L,
					bulletX: H,
					bulletImage: ae,
					textX: B,
					textMaxW: U,
					alignment: _.alignment,
					isLastLine: s,
					para: _,
					useResolvedFontMetrics: O === void 0 && S,
					metricAscent: O,
					metricNaturalDescent: k
				});
				let oe = l + J;
				l += q + J, u = Math.max(u, l, oe + (S && O === void 0 ? Math.max(C, m) : C) + (q - C));
			}
		}
		return {
			allLines: c,
			totalHeight: l,
			requiredHeight: u,
			metricOk: d
		};
	}, F = !0, I = (e) => {
		let t = P(e, F);
		return F && !t.metricOk && (F = !1, t = P(e, !1)), t;
	}, { allLines: L, requiredHeight: R } = I(1);
	if (t.autoFit === "norm" && t.fontScale != null && t.fontScale > 0 && t.fontScale < 1 && ({allLines: L, requiredHeight: R} = I(t.fontScale)), h) return w + R + T;
	let z = t.verticalAnchor ?? "t", B = r, H;
	a === 0 && z === "b" ? (H = w + R + T, B = r - H) : H = t.autoFit === "sp" && a === 0 ? w + R + T : a;
	let U = L[L.length - 1], W = U?.metricAscent !== void 0 && U.metricNaturalDescent !== void 0 ? R - (U.lineHeight - U.metricAscent) + U.metricNaturalDescent : R, G, ee = Math.max(0, H - w - T);
	G = z === "ctr" ? B + $t("ctr", {
		left: S,
		top: w,
		width: i - S - C,
		height: ee
	}, W) : z === "b" ? B + H - T - W : B + w, e.save(), e.textAlign = "left", e.textBaseline = "alphabetic";
	let te = G, K = k > 1 ? (i - S - C - (k - 1) * A) / k + A : 0, ne = Math.max(0, H - w - T), J = L[L.length - 1], re = J ? Math.max(0, J.linePx - J.lineHeight) : 0, ie = R - re, ae = a === 0 || ie <= ne + .5, oe = k > 1 && !ae ? Math.ceil(L.length / k) : L.length, se = 0, ce = 0;
	for (let s of L) {
		let { line: l, linePx: u, lineHeight: d, topGapPx: f, textXOffset: h, bulletLabel: v, bulletFont: y, bulletColor: b, bulletFollowsText: x, bulletImage: S, alignment: C, isLastLine: w, useResolvedFontMetrics: T, metricAscent: E } = s;
		k > 1 && se < k - 1 && ce >= oe && (se++, ce = 0, G = te), G += f, ce++;
		let D = (t.rtlCol ? k - 1 - se : se) * K, O = s.textX + D, A = s.bulletX + D, j = s.textMaxW, M = s.para.rtl === !0, N = M || Br(l.segments), P = l.segments.some((e) => e.isTab);
		if (P) {
			let t = Z(s.para.marL, o), n = Z(s.para.marR, o), r = M ? n : t + h, i = j + t + n;
			e.font = l.segments.find((e) => e.isTab).font;
			let a = e.measureText(" ").width, c = Zr(l.segments.map((t) => {
				if (t.isTab) return {
					isTab: !0,
					width: 0
				};
				if (t.math) return {
					isTab: !1,
					width: t.math.width
				};
				e.font = t.font;
				let n = t.letterSpacingPx ?? 0;
				return {
					isTab: !1,
					width: t.text ? (t.leadingLetterSpacingPx ?? 0) + Da(e, t.text, n) : 0
				};
			}), (s.para.tabStops ?? []).map((e) => ({
				pos: Z(e.pos, o),
				algn: e.algn
			})), r, i, a, Z(s.para.defTabSz ?? 914400, o));
			for (let e = 0; e < l.segments.length; e++) l.segments[e].isTab && (l.segments[e].tabWidthPx = c[e]);
		}
		let F = 0, I = 0, L = 0, R = 0;
		for (let t of l.segments) {
			if (t.isTab) {
				F += t.tabWidthPx ?? 0;
				continue;
			}
			if (t.math) {
				F += t.math.width, I = Math.max(I, t.math.ascent);
				continue;
			}
			e.font = t.font;
			let n = e.measureText(t.text || "M"), r = t.letterSpacingPx ?? 0;
			if (F += t.leadingLetterSpacingPx ?? 0, F += t.text ? Da(e, t.text, r) : 0, n.actualBoundingBoxAscent > 0 && (I = Math.max(I, n.actualBoundingBoxAscent)), T) {
				let e = n.fontBoundingBoxAscent ?? 0, t = e + (n.fontBoundingBoxDescent ?? 0);
				t > R && (R = t, L = e);
			}
		}
		let B = T && R > 0 ? z === "t" && I > 0 ? s.para.spaceLine?.type === "pct" ? Math.max(I, L + (s.baselineLineHeight - R) / 2) : I : Math.max(I, L + Math.max(0, d - R) / 2) : Math.max(d * .8, I), V = E === void 0 ? G + B : G + E, H = V + (l.segments.find((e) => !e.isTab && !e.math && !!e.text)?.fontAlgnOffsetPx ?? 0), U = O + j, W = S && g ? p.pictureBulletImages?.has(S.imagePath) ? p.pictureBulletImages.get(S.imagePath) : tt(S.imagePath, g) : void 0, ee = 0;
		if (N && M) {
			if (v) e.font = y, ee = e.measureText(v).width;
			else if (S && W) {
				let e = S.sizePx;
				ee = W.height > 0 ? e * (W.width / W.height) : e;
			}
		}
		if (v) {
			e.font = y;
			let t = l.segments.find((e) => !e.isTab && !e.math && !!e.text);
			if (e.fillStyle = x && t?.patternFill ? ra(e, t, A, H, o) : b, N && M) {
				let t = e.direction;
				e.direction = "rtl", e.fillText(v, U - ee, H), e.direction = t;
			} else e.fillText(v, A, H);
		}
		if (S && g && W) {
			let t = S.sizePx, n = W.height > 0 ? t * (W.width / W.height) : t, r = H - t;
			N && M ? e.drawImage(W, U - n, r, n, t) : e.drawImage(W, A, r, n, t);
		}
		let ne = O + h, J;
		J = P ? M ? O + j - ee - F : ne : C === "ctr" ? nn("ctr", ne, j - h, F, !1, !0) : C === "r" ? nn("r", O - ee, j, F, !1, !0) : ne;
		let re = C === "just" || C === "justLow" ? "just" : C === "thaiDist" ? "thaiDist" : C === "dist" ? "dist" : null, ie = w || (l.endsWithBreak ?? !1), ae = (re && !N && !P && Yt(C, w, l.endsWithBreak ?? !1) ? Ur(l.segments, j - h, F, re, ie) : null) ?? l.segments, le = N ? Vr(l.segments, M) : null, ue = (e, t) => {
			if (Math.abs(e - t) !== 1) return 0;
			let n = l.segments[Math.min(e, t)], r = l.segments[Math.max(e, t)];
			return n.isTab || n.math || r.isTab || r.math || n.sourceRunId == null || n.sourceRunId !== r.sourceRunId ? 0 : r.leadingLetterSpacingPx ?? 0;
		}, de = ae.length;
		for (let t = 0; t < de; t++) {
			let s = le ? le.order[t] : t, l = ae[s], u = le ? le.rtl[s] : !1;
			if (N && (e.direction = u ? "rtl" : "ltr"), t > 0) {
				let e = le ? le.order[t - 1] : t - 1;
				J += ue(e, s);
			}
			if (l.isTab) {
				J += l.tabWidthPx ?? 0;
				continue;
			}
			let f = l.jext ?? 0, h = l.splitBefore, g = l.perGap ?? 0, v = h && h.length > 0 ? h.length * g : 0;
			if (l.math) {
				let t = l.math.width;
				na(e, l, J, H - l.math.ascent), J += t, J += f;
				continue;
			}
			e.font = l.font;
			let y = l.drawSizePx ?? l.sizePx, b = l.baseline ? -(l.baseline / 1e5) * l.sizePx : 0, x = V + (l.fontAlgnOffsetPx ?? 0) + b, S = ra(e, l, J, x, o);
			e.fillStyle = S;
			let C = l.letterSpacingPx ?? 0;
			if (l.highlight && l.text) {
				let t = Da(e, l.text, C) + v + f;
				Ki(e, J, x, t, y, l.highlight, S, l.lineMetric);
			}
			let w = l.shadow, T = (e, t, n, r) => {
				let i = r === "fill" ? e.fillText.bind(e) : e.strokeText.bind(e);
				if (C !== 0 && wa(t) > 1) {
					let r = e, a = r.letterSpacing;
					if (Ea(e)) {
						r.letterSpacing = `${C}px`, i(t, n, x);
						try {
							r.letterSpacing = a;
						} catch {}
					} else {
						let r = n, a = [...t];
						for (let t = 0; t < a.length; t++) {
							let n = a[t];
							i(n, r, x), t < a.length - 1 && (r += e.measureText(n).width + C);
						}
					}
				} else i(t, n, x);
			}, E = (t) => Da(e, t, C), D = h && h.length > 0 ? ar([...l.text], h, g, E) : null, O = [...l.text], k = !!h && h.length === O.length - 1 && O.length > 1, A = (e, t) => {
				if (_) {
					let n = k ? C + g : C;
					ei(e, l.text, J, x, y, n, t);
					return;
				}
				if (k) {
					let n = t === "fill" ? e.fillText.bind(e) : e.strokeText.bind(e), r = C + g;
					if (Ea(e)) {
						let t = e, i = t.letterSpacing;
						t.letterSpacing = `${r}px`, n(l.text, J, x);
						try {
							t.letterSpacing = i;
						} catch {}
					} else {
						let t = J;
						for (let i = 0; i < O.length; i++) {
							let a = O[i];
							n(a, t, x), i < O.length - 1 && (t += e.measureText(a).width + r);
						}
					}
				} else if (D) for (let { text: n, dx: r } of D) T(e, n, J + r, t);
				else T(e, l.text, J, t);
			}, j = l.reflection;
			if (j && l.text && !l.noFill) {
				let t = e.canvas.width || 0, n = e.canvas.height || 0;
				if (t > 0 && n > 0) {
					e.font = l.font;
					let r = e.measureText(l.text), i = Number.isFinite(r.actualBoundingBoxAscent) ? r.actualBoundingBoxAscent : y * .8, a = Number.isFinite(r.actualBoundingBoxDescent) ? r.actualBoundingBoxDescent : y * .2, s = Number.isFinite(r.actualBoundingBoxLeft) ? r.actualBoundingBoxLeft : 0, c = Number.isFinite(r.actualBoundingBoxRight) ? r.actualBoundingBoxRight : r.width, u = e.getTransform(), d = Math.abs(u.a * u.d - u.b * u.c), f = d > 0 ? Math.sqrt(d) : 1;
					fa(e, (e) => {
						e.font = l.font, e.fillStyle = ra(e, l, J, x, o), A(e, "fill");
					}, {
						x: (J - s) * f,
						y: (x - i) * f,
						w: Math.max(1, s + c) * f,
						h: Math.max(1, i + a) * f
					}, j, o * f, u, t, n), e.font = l.font, e.fillStyle = S;
				}
			}
			if (w && !l.noFill) {
				let t = w.dir * Math.PI / 180, n = Z(w.dist, o);
				e.save(), e.shadowColor = $(w.color, w.alpha), e.shadowBlur = Z(w.blur, o), e.shadowOffsetX = Math.cos(t) * n, e.shadowOffsetY = Math.sin(t) * n;
			}
			l.noFill || A(e, "fill"), w && !l.noFill && e.restore(), e.font = l.font;
			let M = Da(e, l.text, C) + v, P = l.outline;
			P && P.width > 0 && (e.save(), e.lineWidth = Math.max(.5, Z(P.width, o)), e.strokeStyle = P.fill ? q(P.fill, e, J, x - y, Math.max(1, M), y, 0, o * 12700) ?? "rgba(0,0,0,0)" : P.color ? `#${P.color}` : l.noFill ? "rgba(0,0,0,0)" : S, (!l.noFill || P.fill || P.color) && (e.lineJoin = "round", A(e, "stroke")), e.restore()), m && l.text && m({
				text: l.text,
				inShapeX: J - n,
				inShapeY: G - r,
				w: M + f,
				h: d,
				fontSize: y,
				font: l.font,
				shapeX: n,
				shapeY: r,
				shapeW: i,
				shapeH: a,
				rotation: c,
				hyperlink: l.hyperlink
			}), Xi(e, l, J, x, M + f, S, o, p.dpr), J += M, J += f;
		}
		N && (e.direction = "ltr"), G += u;
	}
	e.restore();
}
function Eo(e, t, n, r, i, a) {
	if (!e) return [];
	let o = $n(t?.rig ?? "threePt", t?.dir ?? "t", t?.rot), s = er(n), c = r * i, l = [];
	if (e.bevelT && e.bevelT.w > 0 && e.bevelT.h > 0 && l.push({
		widthPx: e.bevelT.w * c,
		heightPx: e.bevelT.h * c,
		prst: e.bevelT.prst || "circle",
		material: s,
		light: o,
		camera: a,
		wireframeLinePx: i
	}), e.bevelB && e.bevelB.w > 0 && e.bevelB.h > 0 && l.push({
		widthPx: e.bevelB.w * c,
		heightPx: e.bevelB.h * c,
		prst: e.bevelB.prst || "circle",
		material: s,
		light: o,
		bottom: !0,
		camera: a,
		depthPx: (e.extrusionH ?? 0) * c,
		wireframeLinePx: i
	}), s === "legacyWireframe") for (let t of [!1, !0]) l.some((e) => !!e.bottom === t) || l.push({
		widthPx: 0,
		heightPx: 0,
		prst: "circle",
		material: s,
		light: o,
		camera: a,
		bottom: t,
		depthPx: t ? (e.extrusionH ?? 0) * c : 0,
		wireframeLinePx: i
	});
	return l;
}
function Do(e, t, n, r, i, a) {
	if (!e || !e.extrusionH || e.extrusionH <= 0) return null;
	let o = e.extrusionH * i * a, s = Dn({
		...t,
		unitsPerInch: (t.unitsPerInch ?? 96) * a,
		sceneDepth: (t.sceneDepth ?? 0) * a
	}, n * a, r * a, o);
	if (Math.hypot(s.x, s.y) < .75) return null;
	let c = [
		64,
		64,
		64
	];
	if (e.extrusionClr) {
		let t = e.extrusionClr.replace("#", "");
		t.length >= 6 && (c = [
			parseInt(t.slice(0, 2), 16),
			parseInt(t.slice(2, 4), 16),
			parseInt(t.slice(4, 6), 16)
		]);
	}
	return {
		offsetX: s.x,
		offsetY: s.y,
		rgb: c
	};
}
function Oo(e, t, n, r, i, a = n) {
	let o = e.a * e.d - e.b * e.c;
	if (Math.abs(o) <= 1e-12) return;
	let s = e.d / o, c = -e.b / o, l = -e.c / o, u = e.a / o, d = -(s * e.e + l * e.f), f = -(c * e.e + u * e.f);
	return {
		a: t * s,
		b: t * c,
		c: t * l,
		d: t * u,
		e: n + t * (d - r),
		f: a + t * (f - i)
	};
}
function ko(e, t, n, r, i, a, o, s = {}) {
	if (i <= 0 || a <= 0) return !1;
	let c = e.getTransform(), l = Math.abs(c.a * c.d - c.b * c.c), u = l > 0 ? Math.sqrt(l) : 1, d = s.backDepthCss ?? 0, { face: f, corners: p, backFacing: m, padDev: h } = uo(t, i, a, d, s.edgePadCss, u), g = p, _ = h, v = h, y = Math.max(1, Math.ceil(i * u) + 2 * h), b = Math.max(1, Math.ceil(a * u) + 2 * h);
	if (s.measureOverflow) {
		let t = dn(e, (e) => o(e, 0, 0, i, a));
		if (t) {
			let e = Math.min(-h, Math.floor(t.x * u) - 1), n = Math.min(-h, Math.floor(t.y * u) - 1), r = Math.max(y - h, Math.ceil((t.x + t.w) * u) + 1), o = Math.max(b - h, Math.ceil((t.y + t.h) * u) + 1), s = [
				[e, n],
				[r, n],
				[r, o],
				[e, o]
			].map(([e, t]) => Bt(f, e / u / i, t / u / a));
			if (s.some((e) => !e)) return Bt(f, .5, .5) === null;
			g = s, _ = -e, v = -n, y = r - e, b = o - n;
		}
	}
	if (s.measureOverflow && (!Number.isFinite(y * b) || y > 32767 || b > 32767 || y * b > 16777216)) return !1;
	let x = C(y, b);
	if (!x) return !1;
	let S = x.getContext("2d");
	if (!S) return !1;
	S.save(), S.scale(u, u), S.translate(_ / u, v / u), s.preservePatternFrame ? U(e, S, () => o(S, 0, 0, i, a), void 0, Oo(c, u, _, n, r, v)) : o(S, 0, 0, i, a), S.restore();
	let w = Math.ceil(i * u), T = Math.ceil(a * u), E = (e) => ({
		x: _ - e,
		y: v - e,
		w: w + 2 * e,
		h: T + 2 * e
	}), D = s.extrusion && m && d > 0 ? {
		...s.extrusion,
		offsetX: -s.extrusion.offsetX,
		offsetY: -s.extrusion.offsetY
	} : s.extrusion, O = s.bevels?.some((e) => e.material === "legacyWireframe"), k = m && s.bevels?.some((e) => e.bottom), A = () => {
		!D || O || rr(S, D, E(Math.ceil(Math.hypot(D.offsetX, D.offsetY)) + 2));
	};
	if (k || A(), s.bevels && s.bevels.length > 0) for (let e of s.bevels) {
		let t = O && D ? {
			x: D.offsetX,
			y: D.offsetY
		} : void 0, n = e.widthPx + (t ? Math.hypot(t.x, t.y) : 0);
		nr(S, t ? {
			...e,
			wireframeDepth: t
		} : e, E(Math.ceil(n) + 2));
	}
	k && A(), s.paintEdges && (S.save(), S.scale(u, u), S.translate(_ / u, v / u), s.paintEdges(S, 0, 0, i, a), S.restore());
	let j = g.map((e) => ({
		x: n + e.x,
		y: r + e.y
	}));
	return Ht(x, e, y, b, j), !0;
}
function Ao(e, t, n, r, i, a, o, s, c = 0) {
	if (r <= 0 || i <= 0 || a.length === 0) return !1;
	let l = e.getTransform(), u = Math.abs(l.a * l.d - l.b * l.c), d = u > 0 ? Math.sqrt(u) : 1, f = Math.max(0, Math.ceil(c * d)), p = f / d, m = Math.max(1, Math.ceil(r * d) + 2 * f), h = Math.max(1, Math.ceil(i * d) + 2 * f), g = C(m, h);
	if (!g) return !1;
	let _ = g.getContext("2d");
	if (!_) return !1;
	_.save(), _.scale(d, d), _.translate(p, p), U(e, _, () => o(_, 0, 0, r, i), void 0, Oo(l, d, f, t, n)), _.restore();
	let v = Math.ceil(r * d), y = Math.ceil(i * d);
	for (let e of a) {
		let t = Math.ceil(e.widthPx) + 2;
		nr(_, e, {
			x: f - t,
			y: f - t,
			w: v + 2 * t,
			h: y + 2 * t
		});
	}
	return s && (_.save(), _.scale(d, d), _.translate(p, p), s(_, 0, 0, r, i), _.restore()), e.drawImage(g, t - p, n - p, m / d, h / d), !0;
}
var jo = /* @__PURE__ */ new WeakMap();
function Mo(e) {
	let t = jo.get(e);
	return t || (t = async (t, n) => {
		let r = await e(t);
		return r.type === n ? r : new Blob([r], { type: n });
	}, jo.set(e, t)), t;
}
function No(e, t, n = Mo(t), r, i, a) {
	let o = Mo(t);
	return st(e.posterPath, e.posterMimeType || "application/octet-stream", o, {
		tiff: r,
		svgDecoder: a,
		...i ?? {}
	}, n).then((e) => {
		if (!e) throw Error("Media poster could not be decoded");
		return e;
	});
}
function Po(e, t, n, r = "tiff") {
	let i = Z(t.x, n), a = Z(t.y, n), o = Z(t.width, n), s = Z(t.height, n);
	e.save();
	try {
		t.alpha != null && (e.globalAlpha *= t.alpha), (t.rotation !== 0 || t.flipH || t.flipV) && (e.translate(i + o / 2, a + s / 2), e.rotate(t.rotation * Math.PI / 180), e.scale(t.flipH ? -1 : 1, t.flipV ? -1 : 1), e.translate(-(i + o / 2), -(a + s / 2))), jt(e, r, {
			x: i,
			y: a,
			width: o,
			height: s
		});
	} finally {
		e.restore();
	}
}
async function Fo(e, t, n, r, a = 1, o, s) {
	if (e.mimeType === "image/x-pict") return { paint: (n) => Po(n, e, t, "pict") };
	if (!n) return Fa;
	try {
		let c = e.mimeType === "image/svg+xml", l = pe(e.mimeType, e.srcRect, e.width / V, e.height / V);
		if (!l) return Fa;
		let { widthPt: u, heightPt: d } = l, f = Pi(Z(e.width, t), Z(e.height, t), a, e.srcRect), p = Ze(e) || c, m = p ? f : s && !Q(e) ? zi(s, Ri(Bi(e), p ? void 0 : Q(e))) : void 0, h = m && "maxRetainedPixels" in m ? m.maxRetainedPixels : void 0, g = {
			...m ? {
				targetWidthPx: m.targetWidthPx,
				targetHeightPx: m.targetHeightPx,
				...h === void 0 ? {} : { maxRetainedPixels: h }
			} : {},
			workerDecoder: o
		}, _;
		if (!Q(e) && Ze(e)) try {
			_ = await it(e.svgImagePath, n, g);
		} catch (t) {
			if (t instanceof he || Ii(t) || Ot(t)) throw t;
			_ = c ? await it(e.imagePath, n, g) : await ot(e.imagePath, e.mimeType, Q(e), n, {
				widthPt: u,
				heightPt: d,
				...m ?? {},
				tiff: r
			});
		}
		else if (c && !Q(e)) _ = await it(e.imagePath, n, g);
		else {
			let t = e.blipEffects?.length ? i(e.width / V, e.height / V, e.srcRect, null) : null;
			_ = await ot(e.imagePath, e.mimeType, Q(e), n, {
				widthPt: t?.width ?? u,
				heightPt: t?.height ?? d,
				...m ?? {},
				tiff: r,
				svgDecoder: o
			});
		}
		if (!_) return Fa;
		let v = _;
		return { paint: (n) => Lo(n, e, v, t) };
	} catch (n) {
		return Io(e, t, n);
	}
}
function Io(e, t, n) {
	return Ft(n, "tiff") ? { paint: (n) => Po(n, e, t) } : n instanceof he || Ii(n) || Ot(n) || zt(n) ? {
		...Fa,
		failure: { error: n }
	} : Fa;
}
function Lo(t, n, r, i) {
	try {
		t.save(), n.alpha != null && (t.globalAlpha *= n.alpha);
		let a = Z(n.x, i), o = Z(n.y, i), s = Z(n.width, i), c = Z(n.height, i);
		(n.rotation !== 0 || n.flipH || n.flipV) && (t.translate(a + s / 2, o + c / 2), t.rotate(n.rotation * Math.PI / 180), n.flipH && t.scale(-1, 1), n.flipV && t.scale(1, -1), t.translate(-(a + s / 2), -(o + c / 2)));
		let l = (e, t, r, i, a) => {
			n.custGeom && n.custGeom.length > 0 ? vo(e, n.custGeom, t, r, i, a) : n.prstGeom && qe(e, n.prstGeom, t, r, i, a, n.prstAdjust ?? []) || e.rect(t, r, i, a);
		}, u = (e, t, n, r, i) => {
			e.beginPath(), l(e, t, n, r, i);
		}, d = (t, i, a, o, s) => {
			!(n.prstGeom === "rect" && !n.custGeom?.length && e(r)) && (n.prstGeom || n.custGeom && n.custGeom.length > 0) && (u(t, i, a, o, s), t.clip());
		}, f = (e, t, r, a, o) => {
			n.stroke && (A(n.stroke?.fill) && (e = re(e)), e.save(), u(e, t, r, a, o), Bo(e, n.stroke, i, {
				x: t,
				y: r,
				w: a,
				h: o
			}, n.rotation), e.stroke(), e.restore());
		}, p = (e, t, r, a, o) => {
			let s = n.sp3d;
			if (s && (s.contourW ?? 0) > 0 && s.contourClr) {
				let n = Math.max(.5, s.contourW * i);
				e.save(), e.beginPath();
				let c = n * 2 + Math.max(a, o);
				e.rect(t - c, r - c, a + 2 * c, o + 2 * c), l(e, t, r, a, o), e.clip("evenodd"), e.beginPath(), u(e, t, r, a, o), e.strokeStyle = $(s.contourClr), e.lineWidth = n * 2, e.setLineDash([]), e.stroke(), e.restore();
			}
		}, m = n.scene3d && {
			...n.scene3d.camera,
			unitsPerInch: 914400 * i,
			sceneDepth: (n.sp3d?.extrusionH ?? 0) * i
		}, h = m && Tn(m, s, c, (n.sp3d?.extrusionH ?? 0) * i) ? {
			...n.scene3d,
			camera: m
		} : null, g = (e, t, a, o, s) => {
			let c = n.fill && n.fill.fillType !== "none" && n.fill.fillType !== "image" ? qi(n.fill, e, t, a, o, s, n.rotation, i * V, l) : null;
			c && (e.save(), u(e, t, a, o, s), e.fillStyle = c, e.fill(), e.restore()), e.save(), d(e, t, a, o, s), me(e, r, n.srcRect, t, a, o, s), e.restore();
		}, _ = (e, t, n, r, i) => {
			g(e, t, n, r, i), f(e, t, n, r, i), p(e, t, n, r, i);
		}, v = (e, t, n, r, i) => {
			g(e, t, n, r, i), f(e, t, n, r, i);
		}, y = t.getTransform(), b = Math.abs(y.a * y.d - y.b * y.c), x = b > 0 ? Math.sqrt(b) : 1, S = Eo(n.sp3d, n.scene3d?.lightRig, n.sp3d ? n.sp3d.prstMaterial : void 0, i, x, n.scene3d?.camera), C = (S.find((e) => e.bottom)?.depthPx ?? 0) / x, w = h ? Do(n.sp3d, h.camera, s, c, i, x) : null, T = n.stroke ? n.stroke.width * i / 2 : 0, E = n.sp3d?.contourW ? n.sp3d.contourW * i : 0, D = w ? Math.hypot(w.offsetX, w.offsetY) / x : 0, O = T + E + D + 2, k = (e) => {
			if (h) {
				if (ko(e, h.camera, a, o, s, c, v, {
					bevels: S,
					backDepthCss: C,
					extrusion: w ?? void 0,
					paintEdges: p,
					edgePadCss: O
				})) return;
			} else if (S.length > 0 && Ao(e, a, o, s, c, S, v, p, O)) return;
			_(e, a, o, s, c);
		}, j = (e, t, n, r, i, a) => {
			e.save(), d(e, n, r, i, a), e.fillStyle = t, e.fillRect(n, r, i, a), e.restore();
		}, M = t.getTransform(), N = Math.abs(M.a * M.d - M.b * M.c), P = N > 0 ? Math.sqrt(N) : 1, F = T + E, I = (n.custGeom && n.custGeom.length > 0 ? Ce(n.custGeom, a, o, s, c) : n.prstGeom && De(n.prstGeom.toLowerCase()) ? Ee(n.prstGeom.toLowerCase(), a, o, s, c, n.prstAdjust ?? []) : null) ?? {
			x: a,
			y: o,
			w: s,
			h: c
		}, L = h ? fo(h.camera, M, a, o, s, c, O, n.shadow?.algn, C) : {
			bbox: oo(M, I.x - F, I.y - F, I.w + F * 2, I.h + F * 2),
			anchor: lo(M, a, o, s, c, n.shadow?.algn)
		}, R = i * P, z = !!(n.shadow || n.innerShadow || n.glow || n.softEdge || n.reflection), B = (e) => j(e, "#000", a, o, s, c), H = h && z ? po(k, M, L.bbox, {
			w: t.canvas.width || 0,
			h: t.canvas.height || 0
		}) : k, U = !!h || S.length > 0;
		mo(t, n, H, U ? H : B, L.bbox, L.anchor, i, R, M, !0, U), t.restore();
	} catch (e) {
		let r = Io(n, i, e);
		if (r.paint(t), r.failure) throw r.failure.error;
	}
}
async function Ro(e, t, n, r, i, a, o = 1, s, c) {
	let l = Z(e.x, t), u = Z(e.y, t), d = Z(e.width, t), f = Z(e.height, t), p, m = !1;
	if (e.posterPath && n) try {
		p = await No(e, n, i, a, e.posterMimeType === "image/svg+xml" ? Pi(d, f, o) : c ? zi(c, Ri(e.posterPath)) : void 0, s);
	} catch (e) {
		if (Ft(e, "tiff")) m = !0;
		else if (e instanceof he || Ii(e) || Ot(e) || zt(e)) return {
			...Fa,
			failure: { error: e }
		};
	}
	return { paint: (n) => {
		n.save(), Vo(n, e, t), p ? n.drawImage(p, l, u, d, f) : (n.fillStyle = e.mediaKind === "video" ? "#111" : "#f0f0f0", n.fillRect(l, u, d, f), m && jt(n, "tiff", {
			x: l,
			y: u,
			width: d,
			height: f
		})), r || Rr(n, l + d / 2, u + f / 2, d, f, "paused"), n.restore();
	} };
}
function zo(e, t, n, r, i, a, o) {
	let s = Math.max(.5, Z(r.width, a)), c = n.x - t.x, l = n.y - t.y, u = Math.hypot(c, l);
	if (u === 0) return;
	let d = -l / u, f = c / u, p;
	switch (i) {
		case "dbl":
			p = [{
				offset: -1 / 3,
				widthFrac: 1 / 3
			}, {
				offset: 1 / 3,
				widthFrac: 1 / 3
			}];
			break;
		case "thinThick":
			p = [{
				offset: -3 / 8,
				widthFrac: 1 / 4
			}, {
				offset: 1 / 4,
				widthFrac: 1 / 2
			}];
			break;
		case "thickThin":
			p = [{
				offset: -1 / 4,
				widthFrac: 1 / 2
			}, {
				offset: 3 / 8,
				widthFrac: 1 / 4
			}];
			break;
		case "tri":
			p = [
				{
					offset: -2 / 5,
					widthFrac: 1 / 5
				},
				{
					offset: 0,
					widthFrac: 3 / 5
				},
				{
					offset: 2 / 5,
					widthFrac: 1 / 5
				}
			];
			break;
		default: return;
	}
	e.save(), e.globalCompositeOperation = "destination-out", e.strokeStyle = "#000", e.lineWidth = s + .5, e.setLineDash([]), e.beginPath(), e.moveTo(t.x, t.y), e.lineTo(n.x, n.y), e.stroke(), e.globalCompositeOperation = "source-over", e.strokeStyle = (r.fill ? qi(r.fill, e, Math.min(t.x, n.x), Math.min(t.y, n.y), Math.max(1, Math.abs(n.x - t.x)), Math.max(1, Math.abs(n.y - t.y)), o, a * 12700) : null) ?? $(r.color);
	for (let i of p) {
		let c = d * (s * i.offset), l = f * (s * i.offset);
		if (e.lineWidth = Math.max(.5, s * i.widthFrac), e.beginPath(), e.moveTo(t.x + c, t.y + l), e.lineTo(n.x + c, n.y + l), r.fill && A(r.fill)) {
			let i = qi(r.fill, e, Math.min(t.x, n.x), Math.min(t.y, n.y), Math.max(1, Math.abs(n.x - t.x)), Math.max(1, Math.abs(n.y - t.y)), o, a * V, void 0, F(e));
			i && (e.strokeStyle = i);
		}
		e.stroke();
	}
	e.restore();
}
function Bo(e, t, n, r, i = 0) {
	if (te(e, t, n), t?.fill && r) {
		let a = qi(t.fill, e, r.x, r.y, r.w, r.h, i, n * V, void 0, F(e));
		a && (e.strokeStyle = a);
	}
}
function Vo(e, t, n) {
	if (t.rotation === 0 && !t.flipH && !t.flipV) return;
	let r = Z(t.x, n), i = Z(t.y, n), a = Z(t.width, n), o = Z(t.height, n);
	e.translate(r + a / 2, i + o / 2), e.rotate(t.rotation * Math.PI / 180), t.flipH && e.scale(-1, 1), t.flipV && e.scale(1, -1), e.translate(-(r + a / 2), -(i + o / 2));
}
function Ho(e, t, n, r, i = {
	themeMajorFont: null,
	themeMinorFont: null,
	dpr: 1
}, a) {
	e.save(), Vo(e, t, n);
	let o = Z(t.x, n), s = Z(t.y, n), c = (e) => e.spcFirstLastPara ? {
		...e,
		spcFirstLastPara: !1
	} : e, l = t.cols.map((e) => Z(e, n)), u = l.length, d = (e, t) => {
		let n = 0;
		for (let r = 0; r < t; r++) n += l[e + r] ?? 0;
		return n;
	}, f = [...t.rows.map((e) => Z(e.height, n))], p = t.rows.reduce((e, t) => e + t.height, 0), m = t.height > p;
	for (let a = 0; a < t.rows.length; a++) {
		let o = t.rows[a];
		for (let t = 0; t < o.cells.length; t++) {
			let s = o.cells[t];
			if (s.hMerge || s.vMerge || (s.rowSpan || 1) > 1 || !s.textBody || o.height > 0 && !m) continue;
			let l = d(t, s.gridSpan || 1), u = To(e, c(s.textBody), 0, 0, l, 0, n, null, 0, !1, !1, "#000000", r, i, void 0, !0, void 0, !1, o.height === 0) || 0;
			u > f[a] && (f[a] = u);
		}
	}
	for (let a = 0; a < t.rows.length; a++) {
		let o = t.rows[a];
		for (let s = 0; s < o.cells.length; s++) {
			let l = o.cells[s];
			if (l.hMerge || l.vMerge) continue;
			let u = l.rowSpan || 1;
			if (u <= 1 || !l.textBody) continue;
			let p = d(s, l.gridSpan || 1), h = t.rows.slice(a, Math.min(t.rows.length, a + u)).some((e) => e.height === 0);
			if (!h && !m) continue;
			let g = To(e, c(l.textBody), 0, 0, p, 0, n, null, 0, !1, !1, "#000000", r, i, void 0, !0, void 0, !1, h) || 0, _ = 0;
			for (let e = 0; e < u && a + e < f.length; e++) _ += f[a + e];
			if (g > _) {
				let e = (g - _) / u;
				for (let t = 0; t < u && a + t < f.length; t++) f[a + t] += e;
			}
		}
	}
	let h = l.reduce((e, t) => e + t, 0), g = Array(u);
	if (t.rtl) {
		let e = o + h;
		for (let t = 0; t < u; t++) e -= l[t], g[t] = e;
	} else {
		let e = o;
		for (let t = 0; t < u; t++) g[t] = e, e += l[t];
	}
	let _ = (e, n) => t.rtl ? g[e + n - 1] : g[e], v = Array(t.rows.length);
	{
		let e = s;
		for (let n = 0; n < t.rows.length; n++) v[n] = e, e += f[n];
	}
	let y, x, S = {
		row: 0,
		column: 0
	};
	if (a) {
		let e = Z(t.width, n), r = Z(t.height, n), i = o + e / 2, c = s + r / 2, l = t.rotation * Math.PI / 180, u = Math.cos(l), d = Math.sin(l);
		y = (e) => {
			let n = e.shapeX + e.shapeW / 2 - i, r = e.shapeY + e.shapeH / 2 - c;
			t.flipH && (n = -n), t.flipV && (r = -r);
			let o = i + u * n - d * r, s = c + d * n + u * r;
			a({
				...e,
				...t.id === void 0 ? {} : { shapeId: t.id },
				shapeX: o - e.shapeW / 2,
				shapeY: s - e.shapeH / 2,
				rotation: t.rotation,
				...t.flipH ? { shapeFlipH: !0 } : {},
				...t.flipV ? { shapeFlipV: !0 } : {},
				tableCell: S,
				...x === void 0 ? {} : { cellHorzOverflow: x }
			});
		};
	}
	let C = [], w = t.rows.map(() => Array(u).fill(-1));
	for (let e = 0; e < t.rows.length; e++) {
		let n = t.rows[e], r = v[e];
		for (let i = 0; i < n.cells.length; i++) {
			let a = n.cells[i];
			if (a.hMerge || a.vMerge) continue;
			let o = a.gridSpan || 1, s = a.rowSpan || 1, c = d(i, o), l = 0;
			for (let t = 0; t < s; t++) l += f[e + t] ?? 0;
			let p = _(i, o), m = Math.min(e + s - 1, t.rows.length - 1), h = C.length;
			C.push({
				cell: a,
				colX: p,
				rowY: r,
				cellW: c,
				cellH: l,
				ci: i,
				ri: e,
				span: o,
				lastRi: m
			});
			for (let t = e; t <= m; t++) for (let e = i; e < i + o && e < u; e++) w[t][e] = h;
		}
	}
	let T = f.reduce((e, t) => e + t, 0), E = (r, a, o, s, c, l, u) => {
		if (r?.fillType === "image") e.beginPath(), e.rect(a, o, s, c), Ha(e, r, i.shapeFillImages?.get(Ba(r, l, u, t)), {
			x: a,
			y: o,
			w: s,
			h: c
		}, n, !1, t);
		else {
			let i = qi(r, e, a, o, s, c, t.rotation, n * V);
			i && (e.fillStyle = i, e.fillRect(a, o, s, c));
		}
	}, D = [0], O = [0];
	for (let e of t.cols) D.push(D[D.length - 1] + e);
	for (let e of t.rows) O.push(O[O.length - 1] + e.height);
	let k = D[D.length - 1], A = O[O.length - 1] || t.height;
	E(t.background ?? null, o, s, h, T, k, A);
	for (let { cell: a, colX: o, rowY: s, cellW: l, cellH: u, ci: d, ri: f } of C) {
		let p = (D[Math.min(d + (a.gridSpan || 1), t.cols.length)] ?? 0) - (D[d] ?? 0), m = O[Math.min(f + (a.rowSpan || 1), t.rows.length)] - O[f] || t.height;
		if (E(a.fill, o, s, l, u, p, m), a.textBody) {
			y && (S = {
				row: f,
				column: d
			});
			let t = a.textColor ? $(a.textColor) : null, p = a.textBody.vert;
			x = p === void 0 || typeof p == "string" && /^[ \t\r\n]*horz[ \t\r\n]*$/.test(p) ? a.horzOverflow === "overflow" ? "overflow" : "clip" : void 0, e.save();
			try {
				x === "clip" && ln(e, o, l), To(e, c(a.textBody), o, s, l, u, n, t, 0, !1, !1, "#000000", r, i, y);
			} finally {
				e.restore();
			}
		}
	}
	let j = i.dpr, M = (e, t) => {
		if (e < 0 || e >= w.length || t < 0 || t >= u) return null;
		let n = w[e][t];
		return n < 0 ? null : C[n];
	}, N = (r, i, a, o, s) => {
		if (Bo(e, r, n, {
			x: Math.min(i, o),
			y: Math.min(a, s),
			w: Math.max(1, Math.abs(o - i)),
			h: Math.max(1, Math.abs(s - a))
		}, t.rotation), r.cmpd === "dbl" && !r.dashStyle && !r.customDash?.length && (i === o || a === s)) {
			e.fillStyle = e.strokeStyle, mt(e, i, a, o, s, Math.max(.5, Z(r.width, n)), j);
			return;
		}
		let c = i === o ? b(i, e.lineWidth, j) : 0, l = a === s ? b(a, e.lineWidth, j) : 0;
		e.beginPath(), e.moveTo(i + c, a + l), e.lineTo(o + c, s + l), e.stroke();
	};
	for (let r of C) {
		let { cell: i, colX: a, rowY: o, cellW: s, cellH: c } = r;
		e.save();
		let l = t.rtl ? i.borderR : i.borderL, p = t.rtl ? i.borderL : i.borderR, m = t.rtl ? r.ci + r.span === u : r.ci === 0, h = t.rtl ? r.ci === 0 : r.ci + r.span === u, g = t.rtl ? r.ci - 1 : r.ci + r.span, y = (e) => t.rtl ? e.borderR : e.borderL;
		if (r.ri === 0 && i.borderT && N(i.borderT, a, o, a + s, o), m && l && N(l, a, o, a, o + c), r.lastRi === t.rows.length - 1) {
			let e = i.borderB;
			e && N(e, a, o + c, a + s, o + c);
		} else {
			let e = r.lastRi + 1, t = o + c, n = Math.min(r.ci + r.span, u), a = r.ci;
			for (; a < n;) {
				let r = w[e][a], o = a + 1;
				for (; o < n && w[e][o] === r;) o++;
				let s = M(e, a), c = Kr(i.borderB, s ? s.cell.borderT : null);
				if (c) {
					let e = _(a, o - a);
					N(c, e, t, e + d(a, o - a), t);
				}
				a = o;
			}
		}
		if (h) {
			let e = p;
			e && N(e, a + s, o, a + s, o + c);
		} else {
			let e = a + s, t = r.ri;
			for (; t <= r.lastRi;) {
				let n = w[t][g], i = t;
				for (; i + 1 <= r.lastRi && w[i + 1][g] === n;) i++;
				let a = M(t, g), o = Kr(p, a ? y(a.cell) : null);
				o && N(o, e, v[t], e, v[i] + f[i]), t = i + 1;
			}
		}
		i.diagonalTL && (Bo(e, i.diagonalTL, n, {
			x: a,
			y: o,
			w: s,
			h: c
		}, t.rotation), e.beginPath(), e.moveTo(a, o), e.lineTo(a + s, o + c), e.stroke()), i.diagonalTR && (Bo(e, i.diagonalTR, n, {
			x: a,
			y: o,
			w: s,
			h: c
		}, t.rotation), e.beginPath(), e.moveTo(a + s, o), e.lineTo(a, o + c), e.stroke()), e.restore();
	}
	e.restore();
}
function Uo(e, t, n, r) {
	e.save(), e.globalAlpha = t.opacity, e.fillStyle = t.color, e.fillRect(0, 0, n, r), e.restore();
}
var Wo = /* @__PURE__ */ new WeakMap();
function Go(e) {
	Wo.set(e, (Wo.get(e) ?? 0) + 1);
}
function Ko(e, t, n, r, i) {
	e.save(), e.fillStyle = "#f7f7f8", e.fillRect(0, 0, t, n);
	let a = Math.max(12, Math.min(t, n) * .04);
	e.strokeStyle = "#c8ccd2", e.lineWidth = Math.max(1, Math.min(t, n) * .004), e.setLineDash([e.lineWidth * 6, e.lineWidth * 5]), e.strokeRect(a, a, t - a * 2, n - a * 2), e.setLineDash([]);
	let o = t / 2, s = Math.max(18, Math.min(t, n) * .14);
	e.fillStyle = "#b23b3b", e.textAlign = "center", e.textBaseline = "middle", e.font = `${s}px sans-serif`, e.fillText("⚠", o, n * .34);
	let c = Math.max(11, Math.min(t, n) * .045);
	e.fillStyle = "#333333", e.font = `600 ${c}px sans-serif`, e.fillText(`Slide ${r} could not be displayed`, o, n * .52);
	let l = Math.max(9, Math.min(t, n) * .028);
	e.fillStyle = "#666666", e.font = `${l}px sans-serif`;
	let u = t - a * 4, d = i.split(/\s+/), f = [], p = "";
	for (let t of d) {
		let n = p ? `${p} ${t}` : t;
		if (e.measureText(n).width > u && p ? (f.push(p), p = t) : p = n, f.length >= 4) break;
	}
	p && f.length < 4 && f.push(p);
	let m = l * 1.35, h = n * .6 + m;
	for (let t of f.slice(0, 4)) e.fillText(t, o, h), h += m;
	e.restore();
}
async function qo(e, t, n, r, i = {}, a) {
	return Jo(e, t, n, r, i, a);
}
async function Jo(e, t, n, r, i = {}, a) {
	let o = i.fetchImage ?? (i.fetchMedia ? Mo(i.fetchMedia) : void 0), s = (Wo.get(e) ?? 0) + 1;
	Wo.set(e, s);
	let c = () => Wo.get(e) !== s, l = () => c() ? Promise.resolve(e) : Yo(e, t, n, r, i, a, o, c);
	return o && Gi(t) ? We(o, i.imageResources, l) : l();
}
async function Yo(e, t, n, r, a = {}, o, s, c = () => !1) {
	let l = a.svgDecoder ?? (typeof Image < "u" ? Le : void 0), u = a.width ?? ((Me(e) ? e.offsetWidth : 0) || 960), d = u / n, f = Math.round(u), p = Math.round(r * d), m = a.dpr ?? Ne(), h = S(f * m, p * m), g = h.clamped ? m * h.scale : m;
	if (a.math && (await ta(t, a.math), c())) return e;
	let _, v = t;
	if (a.math) {
		let e = [0], n = /* @__PURE__ */ new Map();
		for (let n of t.elements) {
			let t = !1;
			for (let e of $i(n)) if (e.paragraphs.some((e) => e.runs.some((e) => e.type === "math" && Qi.has(e.nodes)))) {
				t = !0;
				break;
			}
			e.push(e[e.length - 1] + +!!t);
		}
		t.elements.forEach((r, i) => {
			if (r.type !== "shape" && r.type !== "table" || !r.mathFallback) return;
			let a = r.mathFallback, o = i - a.before, s = i + a.after + 1;
			o >= 0 && s <= t.elements.length && e[s] > e[o] && s > (n.get(o)?.end ?? o) && n.set(o, {
				end: s,
				elements: a.elements
			});
		});
		let r = [];
		_ = [];
		for (let e = 0; e < t.elements.length;) {
			let i = n.get(e);
			if (i) {
				for (let e of i.elements) r.push(e), _.push(void 0);
				e = i.end;
			} else _.push(e), r.push(t.elements[e++]);
		}
		t = {
			...t,
			elements: r
		};
	}
	let y = await Hi(t, f, p, d, g, a.imageResources, a.fetchImage, a.fetchMedia, s, a.tiff), b = () => {
		e.width = h.width, e.height = h.height, Me(e) && (e.style.width = `${f}px`, e.style.display || (e.style.display = "block"));
		let t = e.getContext("2d");
		if (!t) throw Error("Could not get 2D context");
		return t.scale(g, g), t;
	};
	if (t.parseError) return Ko(b(), f, p, t.slideNumber, t.parseError), e;
	let x = t.theme ? t.theme.defaultTextColor : a.defaultTextColor, C = x ? `#${x}` : "#000000", w = /* @__PURE__ */ new Map(), E = /* @__PURE__ */ new Map(), D = {
		cjkFallback: a.cjkFallback ? Mr(t, t.theme ? t.theme.majorFont : a.majorFont ?? null, t.theme ? t.theme.minorFont : a.minorFont ?? null, a.cjkFallback) : void 0,
		themeMajorFont: t.theme ? t.theme.majorFont : a.majorFont ?? null,
		themeMinorFont: t.theme ? t.theme.minorFont : a.minorFont ?? null,
		themeHlinkColor: t.theme ? t.theme.hlinkColor : a.hlinkColor ?? null,
		embeddedFontAliases: a.embeddedFontAliases,
		embeddedFontAuthoredFamilies: a.embeddedFontAuthoredFamilies,
		embeddedFontTuples: a.embeddedFontTuples,
		officeFontRoutes: a.officeFontRoutes,
		officeInstalledFamilies: a.officeInstalledFamilies,
		googleSubstitutes: a.googleSubstitutes,
		dpr: g,
		smartArtFallbackTextColor: Xr(t.background, C),
		pictureBulletImages: w,
		shapeFillImages: E
	}, k = [], A = () => k.some((e) => e.failure);
	if (k.push(await Ia(t.background, f, p, d, a.fetchImage, a.tiff, l, y)), c()) return e;
	let M = t.slideNumber, N = A() ? [] : t.elements.map((e) => e.type === "picture" ? Fo(e, d, a.fetchImage, a.tiff, g, l, y) : e.type === "media" ? Ro(e, d, a.fetchMedia, a.skipMediaControls, s, a.tiff, g, l, y) : Promise.resolve(null)).map((e) => e.catch((e) => ({
		...Fa,
		failure: { error: e }
	}))), P = /* @__PURE__ */ new Map();
	if (!A() && a.fetchImage) {
		let n = a.fetchImage, r = /* @__PURE__ */ new Map();
		for (let { fill: e, width: n, height: a, host: o } of Wi(t)) {
			if (!(n > 0) || !(a >= 0)) continue;
			let t = e.blipEffects?.length && !e.tile ? i(n, a, e.srcRect, e.fillRect) : {
				width: n,
				height: a
			}, s = Ba(e, n, a, o), c = r.get(s), l = t.width / V, u = t.height / V, f = e.fillRect ?? {}, p = Pi(Z(n, d) * (1 - (f.l ?? 0) - (f.r ?? 0)), Z(a, d) * (1 - (f.t ?? 0) - (f.b ?? 0)), g, e.srcRect);
			r.set(s, c ? {
				fill: c.fill,
				host: c.host,
				widthPt: Math.max(c.widthPt, l),
				heightPt: Math.max(c.heightPt, u),
				targetWidthPx: Math.max(c.targetWidthPx ?? 0, p?.targetWidthPx ?? 0) || void 0,
				targetHeightPx: Math.max(c.targetHeightPx ?? 0, p?.targetHeightPx ?? 0) || void 0,
				preserveNaturalSize: c.preserveNaturalSize || e.tile != null,
				hasSourceCrop: c.hasSourceCrop || e.srcRect != null
			} : {
				fill: e,
				host: o,
				widthPt: l,
				heightPt: u,
				targetWidthPx: p?.targetWidthPx,
				targetHeightPx: p?.targetHeightPx,
				preserveNaturalSize: e.tile != null,
				hasSourceCrop: e.srcRect != null
			});
		}
		let o = /* @__PURE__ */ new Map(), s = [];
		for (let e of t.elements) {
			if (e.type !== "chart" || !Number.isFinite(e.width) || e.width <= 0 || !Number.isFinite(e.height) || e.height <= 0) continue;
			let t = {
				widthPt: e.width / V,
				heightPt: e.height / V,
				targetWidthPx: Z(e.width, d) * g,
				targetHeightPx: Z(e.height, d) * g
			}, n = [], r = !0;
			for (let i of W(e.chart)) {
				let e = j(i, t);
				if (!e) {
					r = !1;
					break;
				}
				n.push({
					usage: i,
					size: e
				});
			}
			r && s.push({
				element: e,
				frame: t,
				usages: n
			});
		}
		let u = /* @__PURE__ */ new Map(), f = /* @__PURE__ */ new Set();
		for (let e of R(s.map(({ element: e }) => e.chart), (e, t) => j(e, s[t].frame) != null)) f.add(T(e.fill));
		for (let { element: e, usages: t } of s) {
			let n = {
				rotation: e.rotation,
				flipH: e.flipH,
				flipV: e.flipV
			};
			for (let { usage: e, size: r } of t) {
				let { fill: t } = e;
				if (!f.has(T(t)) || K(t)) continue;
				let i = B(t, n), a = u.get(i) ?? {
					fill: t,
					widthPt: 0,
					heightPt: 0,
					preserveNaturalSize: e.preserveNaturalSize,
					hasSourceCrop: e.hasSourceCrop,
					...t.blipEffects?.length ? { host: n } : {}
				}, o = a.preserveNaturalSize || e.preserveNaturalSize, s = a.hasSourceCrop || e.hasSourceCrop, c = !Q(t) && (t.mimeType === "image/svg+xml" || Ze({
					svgImagePath: t.svgImagePath,
					srcRect: s ? !0 : null
				})), l = o || Q(t) || c ? void 0 : zi(y, Ri(t.imagePath, Q(t)));
				u.set(i, {
					...a,
					widthPt: Math.max(a.widthPt, r.widthPt),
					heightPt: Math.max(a.heightPt, r.heightPt),
					targetWidthPx: o ? void 0 : l?.targetWidthPx ?? (c && Math.max(a.targetWidthPx ?? 0, r.targetWidthPx ?? 0) || void 0),
					targetHeightPx: o ? void 0 : l?.targetHeightPx ?? (c && Math.max(a.targetHeightPx ?? 0, r.targetHeightPx ?? 0) || void 0),
					maxRetainedPixels: l?.maxRetainedPixels,
					preserveNaturalSize: o,
					hasSourceCrop: s
				});
			}
		}
		let p = (e) => {
			let t = [];
			for (let { element: n } of s) {
				let r = {
					rotation: n.rotation,
					flipH: n.flipH,
					flipV: n.flipV
				};
				for (let { fill: i, occurrence: o } of It(n.chart, {
					x: Z(n.x, d),
					y: Z(n.y, d),
					w: Z(n.width, d),
					h: Z(n.height, d)
				}, V * d, n.rotation, a.threeD, a.regionMap, a.chartEx, e ? ce(P, r, !1) : void 0)) {
					if (!f.has(T(i))) continue;
					let e = ae(i, r, o);
					u.has(e) || (u.set(e, {
						fill: i,
						widthPt: o.widthPt,
						heightPt: o.heightPt,
						preserveNaturalSize: !0,
						hasSourceCrop: i.srcRect != null,
						host: r,
						renderedSize: o
					}), t.push(e));
				}
			}
			return Rt(u.size), t;
		};
		p(!1);
		for (let e of t.elements) if (!(e.type !== "shape" || !e.textBody)) for (let t of e.textBody.paragraphs) {
			let n = Nr(t.bullet);
			if (n.type === "blip") {
				let r = ya(e.textBody, t, n) * V * d * g, i = o.get(n.imagePath);
				i ? Number.isFinite(r) && r > 0 && (i.targetHeightPx = Math.max(i.targetHeightPx ?? 0, r)) : o.set(n.imagePath, {
					mimeType: n.mimeType,
					...Number.isFinite(r) && r > 0 ? { targetHeightPx: r } : {}
				});
			}
		}
		if (o.size > 0 || u.size > 0 || r.size > 0) {
			let t = [...o].map(async ([e, { mimeType: t, targetHeightPx: r }]) => {
				try {
					let i = t === "image/svg+xml" ? r === void 0 ? {} : {
						targetWidthPx: 1,
						targetHeightPx: r
					} : zi(y, Ri(e)) ?? {}, o = t === "image/svg+xml" ? await it(e, n, {
						...i,
						workerDecoder: l
					}) : await st(e, t, n, {
						tiff: a.tiff,
						...i
					});
					w.set(e, o);
				} catch (t) {
					if (Ft(t, "tiff")) {
						w.set(e, null);
						return;
					}
					if (t instanceof he || Ii(t) || Ot(t) || zt(t)) throw t;
					w.set(e, null);
				}
			}), i = async ([e, t]) => {
				let { fill: r, widthPt: i, heightPt: o, targetWidthPx: s, targetHeightPx: c, maxRetainedPixels: u, hasSourceCrop: d, host: f, renderedSize: p } = t, m = s && c ? {
					targetWidthPx: s,
					targetHeightPx: c,
					...u === void 0 ? {} : { maxRetainedPixels: u }
				} : void 0;
				try {
					let t = () => r.mimeType === "image/svg+xml" && !Q(r) ? it(r.imagePath, n, {
						...m ?? {},
						workerDecoder: l
					}) : ot(r.imagePath, r.mimeType, O(r, f, p) ?? void 0, n, {
						widthPt: p ? p.widthPt : r.blipEffects?.length ? 0 : i,
						heightPt: p ? p.heightPt : r.blipEffects?.length ? 0 : o,
						...m ?? {},
						failClosedOnDuotoneFailure: !0,
						tiff: a.tiff,
						svgDecoder: l
					}), s, c = {
						svgImagePath: r.svgImagePath,
						srcRect: d ? !0 : null
					};
					if (!Q(r) && Ze(c)) try {
						s = await it(c.svgImagePath, n, {
							...m ?? {},
							workerDecoder: l
						});
					} catch (e) {
						if (e instanceof he || Ii(e) || Ot(e)) throw e;
						s = await t();
					}
					else s = await t();
					P.set(e, s);
				} catch (t) {
					if (Ft(t, "tiff")) {
						P.set(e, null);
						return;
					}
					if (t instanceof he || Ii(t) || Ot(t) || zt(t)) throw t;
					P.set(e, null);
				}
			}, s = [(async () => {
				for (await Promise.all([...u].map(i));;) {
					let e = p(!0);
					if (e.length === 0) break;
					await Promise.all(e.map((e) => i([e, u.get(e)])));
				}
			})()], d = [...r].map(async ([e, { fill: t, host: r, widthPt: i, heightPt: o, targetWidthPx: s, targetHeightPx: c, preserveNaturalSize: u, hasSourceCrop: d }]) => {
				let f = t.mimeType === "image/svg+xml" || !Q(t, r) && Ze({
					svgImagePath: t.svgImagePath,
					srcRect: d ? !0 : null
				}), p = !u && !Q(t, r) ? zi(y, Ri(t.imagePath, Q(t, r))) : void 0, m = f ? !u && s && c ? {
					targetWidthPx: s,
					targetHeightPx: c
				} : void 0 : p;
				try {
					let s = u ? (await Fi(t, He(t.imagePath, t.mimeType, n))).dimensions ?? void 0 : void 0, c = () => t.mimeType === "image/svg+xml" && !Q(t, r) ? it(t.imagePath, n, {
						...m ?? {},
						workerDecoder: l
					}) : ot(t.imagePath, t.mimeType, Q(t, r), n, {
						...Li(t, i, o),
						...m ?? {},
						failClosedOnDuotoneFailure: !0,
						tiff: a.tiff,
						svgDecoder: l
					}), f;
					if (!Q(t, r) && Ze({
						svgImagePath: t.svgImagePath,
						srcRect: d ? !0 : null
					})) try {
						f = await it(t.svgImagePath, n, {
							...m ?? {},
							workerDecoder: l
						});
					} catch (e) {
						if (e instanceof he || Ii(e) || Ot(e)) throw e;
						f = await c();
					}
					else f = await c();
					E.set(e, f ? {
						image: f,
						intrinsicSize: s
					} : null);
				} catch (t) {
					if (Ft(t, "tiff")) {
						E.set(e, null);
						return;
					}
					if (t instanceof he || Ii(t) || Ot(t) || zt(t)) throw t;
					E.set(e, null);
				}
			});
			try {
				await Promise.all([
					...t,
					...s,
					...d
				]);
			} catch (e) {
				k.push({
					...Fa,
					failure: { error: e }
				});
			}
			if (c()) return e;
		}
	}
	let F = [];
	if (!A()) for (let t of N) {
		let n = await t;
		if (c()) return e;
		if (F.push(n), n?.failure) break;
	}
	if (c()) return e;
	let I = b();
	for (let e of k) if (e.paint(I), e.failure) throw e.failure.error;
	let L = {
		a: g,
		d: g
	};
	for (let [n, r] of t.elements.entries()) {
		if (c()) return e;
		let t = _ ? _[n] : n, i = o ? (e) => {
			o(t === void 0 ? {
				...e,
				renderElementIndex: n,
				elementIndex: void 0,
				origin: void 0,
				shapeId: void 0
			} : {
				...e,
				renderElementIndex: n,
				elementIndex: t,
				origin: v.elementSources?.[t]?.origin ?? "slide"
			});
		} : void 0;
		if (r.type === "shape") ue(I, L, () => _o(I, r, d, C, M, D, i, a.fetchImage));
		else if (r.type === "picture" || r.type === "media") {
			let e = F[n];
			if (e && (ue(I, L, () => e.paint(I)), e.failure)) throw e.failure.error;
		} else if (r.type === "table") ue(I, L, () => Ho(I, r, d, M, D, i));
		else if (r.type === "chart") {
			let e = V * d;
			ue(I, L, () => {
				I.save(), Vo(I, r, d), Lt(I, r.chart, {
					x: Z(r.x, d),
					y: Z(r.y, d),
					w: Z(r.width, d),
					h: Z(r.height, d)
				}, e, r.rotation, a.threeD, a.regionMap, ce(P, {
					rotation: r.rotation,
					flipH: r.flipH,
					flipV: r.flipV
				}), a.chartEx), I.restore();
			});
		}
	}
	return c() || a.dim && Uo(I, a.dim, f, p), e;
}
var Xo = Object.freeze({
	archiveEntryCount: 0,
	declaredInflatedBytes: 0,
	distinctInflatedBytes: 0,
	operationInflatedBytes: 0
}), Zo = et;
function Qo(e, t) {
	if (e !== null && typeof e != "string") throw Error(`invalid PPTX presentation bootstrap ${t}`);
}
function $o(e, t) {
	if (!e || typeof e != "object" || Array.isArray(e)) throw Error(`invalid PPTX presentation bootstrap slide at ${t}`);
	let n = e;
	if (n.index !== t) throw Error(`invalid PPTX presentation bootstrap slide index ${n.index}`);
	if (n.partName !== void 0 && typeof n.partName != "string") throw Error(`invalid PPTX presentation bootstrap slide partName at ${t}`);
	return Object.freeze({
		index: n.index,
		...n.partName === void 0 ? {} : { partName: n.partName }
	});
}
function es(e, t) {
	if (!e || typeof e != "object" || Array.isArray(e)) throw Error(`invalid PPTX presentation bootstrap embedded font at ${t}`);
	let n = e;
	if (typeof n.fontName != "string" || n.fontName.length === 0 || ![
		"regular",
		"bold",
		"italic",
		"boldItalic"
	].includes(n.style ?? "") || typeof n.partPath != "string" || n.partPath.length === 0 || n.partPath.startsWith("/") || n.partPath.split("/").includes("..") || !["application/x-font-ttf", "application/x-fontdata"].includes(n.contentType ?? "")) throw Error(`invalid PPTX presentation bootstrap embedded font fields at ${t}`);
	return Object.freeze({
		fontName: n.fontName,
		style: n.style,
		partPath: n.partPath,
		contentType: n.contentType
	});
}
function ts(e) {
	if (!e || typeof e != "object" || Array.isArray(e)) throw Error("invalid PPTX presentation bootstrap payload");
	let t = e;
	if (!Number.isSafeInteger(t.slideCount) || (t.slideCount ?? -1) < 0 || !Number.isSafeInteger(t.slideWidth) || (t.slideWidth ?? 0) <= 0 || !Number.isSafeInteger(t.slideHeight) || (t.slideHeight ?? 0) <= 0 || !Array.isArray(t.embeddedFonts) || !Array.isArray(t.slides) || t.slides.length !== t.slideCount) throw Error("invalid PPTX presentation bootstrap dimensions or slide count");
	return Qo(t.defaultTextColor, "defaultTextColor"), Qo(t.majorFont, "majorFont"), Qo(t.minorFont, "minorFont"), Qo(t.hlinkColor, "hlinkColor"), Qo(t.folHlinkColor, "folHlinkColor"), Object.freeze({
		slideCount: t.slideCount,
		slideWidth: t.slideWidth,
		slideHeight: t.slideHeight,
		defaultTextColor: t.defaultTextColor,
		majorFont: t.majorFont,
		minorFont: t.minorFont,
		hlinkColor: t.hlinkColor,
		folHlinkColor: t.folHlinkColor,
		embeddedFonts: Object.freeze(t.embeddedFonts.map(es)),
		slides: Object.freeze(t.slides.map($o))
	});
}
function ns(e) {
	return Object.freeze({
		type: "media",
		x: e.x,
		y: e.y,
		width: e.width,
		height: e.height,
		rotation: e.rotation,
		flipH: e.flipH,
		flipV: e.flipV,
		mediaKind: e.mediaKind,
		posterPath: e.posterPath,
		posterMimeType: e.posterMimeType,
		mediaPath: e.mediaPath,
		mimeType: e.mimeType
	});
}
function rs(e) {
	return Object.freeze({
		...e.id === void 0 ? {} : { id: e.id },
		...e.authorId === void 0 ? {} : { authorId: e.authorId },
		...e.author === void 0 ? {} : { author: e.author },
		...e.date === void 0 ? {} : { date: e.date },
		...e.status === void 0 ? {} : { status: e.status },
		text: e.text
	});
}
function is(e) {
	return Object.freeze({ ...e });
}
function as(e) {
	return Object.freeze({
		...e.authorId === void 0 ? {} : { authorId: e.authorId },
		...e.modernAuthorId === void 0 ? {} : { modernAuthorId: e.modernAuthorId },
		...e.id === void 0 ? {} : { id: e.id },
		...e.index === void 0 ? {} : { index: e.index },
		...e.author === void 0 ? {} : { author: e.author },
		...e.date === void 0 ? {} : { date: e.date },
		...e.x === void 0 ? {} : { x: e.x },
		...e.y === void 0 ? {} : { y: e.y },
		...e.anchors?.length ? { anchors: Object.freeze(e.anchors.map(is)) } : {},
		...e.status === void 0 ? {} : { status: e.status },
		text: e.text,
		...e.replies?.length ? { replies: Object.freeze(e.replies.map(rs)) } : {}
	});
}
function os(e, t, n) {
	if (e !== void 0 && typeof e != "string") throw Error(`invalid PPTX presentation preflight comment ${t} at slide ${n}`);
}
function ss(e, t) {
	if (!e || typeof e != "object" || Array.isArray(e)) throw Error(`invalid PPTX presentation preflight comment reply at slide ${t}`);
	let n = e;
	for (let e of [
		"id",
		"authorId",
		"author",
		"date",
		"status"
	]) os(n[e], e, t);
	if (typeof n.text != "string") throw Error(`invalid PPTX presentation preflight comment reply text at slide ${t}`);
	if (n.status !== void 0 && ![
		"active",
		"resolved",
		"closed"
	].includes(n.status)) throw Error(`invalid PPTX presentation preflight comment reply status at slide ${t}`);
	return rs(n);
}
function cs(e, t) {
	if (!e || typeof e != "object" || Array.isArray(e)) throw Error(`invalid PPTX presentation preflight comment at slide ${t}`);
	let n = e;
	for (let e of [
		"modernAuthorId",
		"id",
		"author",
		"date",
		"status"
	]) os(n[e], e, t);
	for (let e of [
		"authorId",
		"index",
		"x",
		"y"
	]) {
		let r = n[e];
		if (r !== void 0 && (typeof r != "number" || !Number.isSafeInteger(r))) throw Error(`invalid PPTX presentation preflight comment ${e} at slide ${t}`);
	}
	if (typeof n.text != "string" || n.replies !== void 0 && !Array.isArray(n.replies) || n.anchors !== void 0 && !Array.isArray(n.anchors)) throw Error(`invalid PPTX presentation preflight comment fields at slide ${t}`);
	if (n.status !== void 0 && ![
		"active",
		"resolved",
		"closed"
	].includes(n.status)) throw Error(`invalid PPTX presentation preflight comment status at slide ${t}`);
	return as({
		...n,
		...n.anchors?.length ? { anchors: n.anchors.map((e) => ls(e, t)) } : {},
		...n.replies?.length ? { replies: n.replies.map((e) => ss(e, t)) } : {}
	});
}
function ls(e, t) {
	if (!e || typeof e != "object" || Array.isArray(e)) throw Error(`invalid PPTX presentation preflight comment anchor at slide ${t}`);
	let n = e;
	if (n.type === "slide" || n.type === "unknown") return Object.freeze({ type: n.type });
	if (n.type === "drawingElement") return os(n.elementId, "anchor.elementId", t), os(n.creationId, "anchor.creationId", t), Object.freeze({
		type: "drawingElement",
		...n.elementId === void 0 ? {} : { elementId: n.elementId },
		...n.creationId === void 0 ? {} : { creationId: n.creationId }
	});
	if (n.type === "textRange") {
		os(n.elementId, "anchor.elementId", t);
		for (let e of ["start", "length"]) {
			let r = n[e];
			if (r !== void 0 && (typeof r != "number" || !Number.isSafeInteger(r))) throw Error(`invalid PPTX presentation preflight comment anchor.${e} at slide ${t}`);
		}
		return Object.freeze({
			type: "textRange",
			...n.elementId === void 0 ? {} : { elementId: n.elementId },
			...n.start === void 0 ? {} : { start: n.start },
			...n.length === void 0 ? {} : { length: n.length }
		});
	}
	throw Error(`invalid PPTX presentation preflight comment anchor type at slide ${t}`);
}
function us(e, t) {
	if (!e || typeof e != "object" || Array.isArray(e)) throw Error(`invalid PPTX presentation preflight media at slide ${t}`);
	let n = e;
	for (let e of [
		"x",
		"y",
		"width",
		"height",
		"rotation"
	]) if (typeof n[e] != "number" || !Number.isFinite(n[e])) throw Error(`invalid PPTX presentation preflight media ${e} at slide ${t}`);
	if (n.type !== "media" || typeof n.flipH != "boolean" || typeof n.flipV != "boolean" || n.mediaKind !== "audio" && n.mediaKind !== "video" || typeof n.posterPath != "string" || typeof n.posterMimeType != "string" || typeof n.mediaPath != "string" || typeof n.mimeType != "string") throw Error(`invalid PPTX presentation preflight media fields at slide ${t}`);
	return ns(n);
}
function ds(e, t) {
	if (!e || typeof e != "object" || Array.isArray(e)) throw Error("invalid PPTX presentation preflight payload");
	let n = e;
	if (!Number.isSafeInteger(n.slideCount) || (n.slideCount ?? -1) < 0 || !Number.isSafeInteger(n.slideWidth) || (n.slideWidth ?? 0) <= 0 || !Number.isSafeInteger(n.slideHeight) || (n.slideHeight ?? 0) <= 0 || !Array.isArray(n.embeddedFonts) || !Array.isArray(n.slides) || (t ? n.slides.length > (n.slideCount ?? -1) : n.slides.length !== n.slideCount) || !Array.isArray(n.fontPreloadNames)) throw Error("invalid PPTX presentation preflight dimensions or slide count");
	Qo(n.defaultTextColor, "defaultTextColor"), Qo(n.majorFont, "majorFont"), Qo(n.minorFont, "minorFont"), Qo(n.hlinkColor, "hlinkColor"), Qo(n.folHlinkColor, "folHlinkColor");
	let r = n.slides.map((e, t) => {
		if (!e || typeof e != "object" || Array.isArray(e)) throw Error(`invalid PPTX presentation preflight slide at ${t}`);
		let n = e;
		if (n.index !== t || n.partName !== void 0 && typeof n.partName != "string" || n.notes !== null && typeof n.notes != "string" || typeof n.hidden != "boolean" || !Array.isArray(n.mediaElements) || n.comments !== void 0 && !Array.isArray(n.comments)) throw Error(`invalid PPTX presentation preflight slide fields at ${t}`);
		return Object.freeze({
			index: t,
			...n.partName === void 0 ? {} : { partName: n.partName },
			notes: n.notes,
			hidden: n.hidden,
			mediaElements: Object.freeze(n.mediaElements.map((e) => us(e, t))),
			...n.comments?.length ? { comments: Object.freeze(n.comments.map((e) => cs(e, t))) } : {}
		});
	}), i;
	if (n.fontPreloadDemand !== void 0) {
		let e = n.fontPreloadDemand;
		if (e === "all") i = "all";
		else if (Array.isArray(e) && e.every((e) => Number.isInteger(e) && e >= 0 && e <= 1114111 && !(e >= 55296 && e <= 57343))) i = Object.freeze([...new Set(e)]);
		else throw Error("invalid PPTX presentation preflight font demand");
	}
	let a = n.fontPreloadNames.map((e, t) => {
		if (e !== null && typeof e != "string") throw Error(`invalid PPTX presentation preflight font at ${t}`);
		return e;
	});
	return Object.freeze({
		slideCount: n.slideCount,
		slideWidth: n.slideWidth,
		slideHeight: n.slideHeight,
		defaultTextColor: n.defaultTextColor,
		majorFont: n.majorFont,
		minorFont: n.minorFont,
		hlinkColor: n.hlinkColor,
		folHlinkColor: n.folHlinkColor,
		embeddedFonts: Object.freeze(n.embeddedFonts.map(es)),
		slides: Object.freeze(r),
		fontPreloadNames: Object.freeze(a),
		...i === void 0 ? {} : { fontPreloadDemand: i }
	});
}
function fs(e) {
	return ds(e, !1);
}
function ps(e) {
	return ds(e, !0);
}
function ms(e, t) {
	for (let n of e.slides) for (let e of n.mediaElements) {
		if (e.mediaPath === t) return e.mimeType;
		if (e.posterPath === t) return e.posterMimeType;
	}
	return "";
}
function hs(e, t) {
	if (e.index !== t.index || e.partName !== t.partName) throw Error(`PPTX pulled slide identity does not match bootstrap index ${t.index}`);
	return Object.freeze({
		index: t.index,
		...t.partName === void 0 ? {} : { partName: t.partName },
		notes: e.notes ?? null,
		hidden: e.hidden ?? !1,
		mediaElements: Object.freeze(e.elements.filter((e) => e.type === "media").map(ns)),
		...e.comments?.length ? { comments: Object.freeze(e.comments.map(as)) } : {}
	});
}
function gs(e, t, n) {
	if (!(e <= t)) throw new he(`PPTX presentation preflight exceeded its hard limit of ${t} projected bytes`, {
		stage: "parsing",
		violation: {
			format: "pptx",
			operation: "presentation-preflight",
			resource: "presentation-preflight",
			metric: "projected-bytes",
			limit: t,
			observed: Math.min(e, t + 1),
			configurable: !1,
			usage: n
		}
	});
}
var _s = class {
	slideCountValue;
	slideWidthValue;
	slideHeightValue;
	defaultTextColorValue;
	majorFontValue;
	minorFontValue;
	hlinkColorValue;
	folHlinkColorValue;
	embeddedFontsValue;
	descriptors;
	slides = [];
	fonts;
	fontPreloadNames;
	fontProjectionBytes;
	projectionBytesValue;
	limit;
	pending = null;
	finished = null;
	demandPoints;
	demandAll = !1;
	demandBytes = 0;
	demandMembershipBytes = 0;
	demandDeltaBytes = 0;
	collectFontDemand;
	demandDelta;
	constructor(e, t = {}) {
		let n = ts(e), r = t.maxProjectionBytes ?? Zo;
		if (!Number.isSafeInteger(r) || r <= 0 || r > Zo) throw Error("invalid PPTX presentation preflight limit");
		this.limit = r, this.collectFontDemand = t.collectFontDemand, this.demandPoints = this.collectFontDemand ? /* @__PURE__ */ new Set() : void 0, this.demandBytes = this.collectFontDemand ? en({ fontPreloadDemand: [] }, this.limit).jsonBytes - 1 : 0, this.demandMembershipBytes = this.demandBytes, this.slideCountValue = n.slideCount, this.slideWidthValue = n.slideWidth, this.slideHeightValue = n.slideHeight, this.defaultTextColorValue = n.defaultTextColor, this.majorFontValue = n.majorFont, this.minorFontValue = n.minorFont, this.hlinkColorValue = n.hlinkColor, this.folHlinkColorValue = n.folHlinkColor, this.embeddedFontsValue = n.embeddedFonts, this.descriptors = [...n.slides], this.fonts = new kr(this.majorFontValue, this.minorFontValue, void 0, void 0, t.cjkFallback), this.fontPreloadNames = Object.freeze(this.fonts.names()), this.fontProjectionBytes = en(this.fontPreloadNames, this.limit).jsonBytes, this.projectionBytesValue = en({
			slideCount: this.slideCountValue,
			slideWidth: this.slideWidthValue,
			slideHeight: this.slideHeightValue,
			defaultTextColor: this.defaultTextColorValue,
			majorFont: this.majorFontValue,
			minorFont: this.minorFontValue,
			hlinkColor: this.hlinkColorValue,
			folHlinkColor: this.folHlinkColorValue,
			embeddedFonts: this.embeddedFontsValue,
			remainingSlides: this.descriptors,
			slides: [],
			fontPreloadNames: this.fontPreloadNames
		}, this.limit).jsonBytes, this.projectionBytesValue + this.demandBytes > this.limit && (this.demandAll = !0, this.demandBytes = 0, this.demandMembershipBytes = 0), this.projectionBytesValue = an(this.projectionBytesValue, this.demandBytes, this.limit), gs(this.projectionBytesValue, this.limit, Xo);
	}
	get acceptedSlideCount() {
		return this.finished?.slideCount ?? this.slides.length;
	}
	get projectedBytes() {
		return this.projectionBytesValue;
	}
	get remainingDescriptorCount() {
		return this.descriptors.reduce((e, t) => e + Number(t !== void 0), 0);
	}
	get latestSlide() {
		return this.slides[this.slides.length - 1];
	}
	get currentFontPreloadNames() {
		return this.fontPreloadNames;
	}
	get currentFontPreloadDemandDelta() {
		return this.demandDelta;
	}
	snapshot() {
		if (this.finished) return this.finished;
		if (this.pending) throw Error("PPTX presentation preflight has an uncommitted slide");
		return Object.freeze({
			slideCount: this.slideCountValue,
			slideWidth: this.slideWidthValue,
			slideHeight: this.slideHeightValue,
			defaultTextColor: this.defaultTextColorValue,
			majorFont: this.majorFontValue,
			minorFont: this.minorFontValue,
			hlinkColor: this.hlinkColorValue,
			folHlinkColor: this.folHlinkColorValue,
			embeddedFonts: this.embeddedFontsValue,
			slides: Object.freeze([...this.slides]),
			fontPreloadNames: this.fontPreloadNames
		});
	}
	addSlide(e, t = Xo) {
		this.prepareSlide(e, t).commit();
	}
	prepareSlide(e, t = Xo) {
		if (this.finished) throw Error("PPTX presentation preflight is already finished");
		if (this.pending) throw Error("PPTX presentation preflight already has a prepared slide");
		let n = this.slides.length, r = this.descriptors[n];
		if (!r) throw Error("PPTX presentation preflight received an extra slide");
		let i = hs(e, r), a = this.fonts.withSlide(e), o = Object.freeze(a.names()), s = en(o, this.limit).jsonBytes, c = en(i, this.limit).jsonBytes, l = this.projectionBytesValue - this.demandBytes - this.fontProjectionBytes - en(r, this.limit).jsonBytes + 4;
		l = an(l, s, this.limit), l = an(l, c, this.limit), this.slides.length !== 0 && (l = an(l, 1, this.limit));
		let u = en({
			slide: i,
			fontPreloadNames: o
		}, this.limit).jsonBytes, d = an(this.projectionBytesValue - this.demandBytes, u, this.limit), f = l;
		gs(Math.max(d, f), this.limit, t);
		let p = d + this.demandBytes;
		l += this.demandBytes, Math.max(p, l) > this.limit && this.demandBytes && (this.projectionBytesValue -= this.demandBytes, this.demandBytes = 0, this.demandMembershipBytes = 0, this.demandDeltaBytes = 0, this.demandPoints?.clear(), this.demandAll = !0, this.demandDelta = "all", p = d, l = f);
		let m, h = this.demandBytes, g = this.demandMembershipBytes, _ = this.demandDeltaBytes;
		if (this.collectFontDemand) {
			let t = en([], this.limit).jsonBytes, n = this.limit - p, r = this.limit - f - this.demandMembershipBytes, i = Math.min(n, Math.floor((r + t - Number((this.demandPoints?.size ?? 0) > 0)) / 2));
			if (m = this.demandAll ? "all" : this.collectFontDemand(e, this.demandPoints, Math.max(0, i)), m === "all") h = 0, g = 0, _ = 0;
			else {
				_ = en(m, this.limit).jsonBytes;
				let e = m.length === 0 ? 0 : _ - t + Number((this.demandPoints?.size ?? 0) > 0);
				g += e, h = g + _, p += _;
			}
			l = f + h;
		}
		gs(Math.max(p, l), this.limit, t);
		let v = {
			state: "prepared",
			fact: i,
			fonts: a,
			fontNames: o,
			fontBytes: s,
			committedBytes: l,
			demandDelta: m,
			demandBytes: h,
			demandMembershipBytes: g,
			demandDeltaBytes: _
		};
		return this.pending = v, {
			projectedBytes: p,
			commit: () => {
				if (v.state !== "committed") {
					if (v.state === "rolled-back") throw Error("PPTX presentation preflight cannot commit a rolled-back slide");
					if (this.pending !== v) throw Error("PPTX presentation preflight prepared slide is stale");
					if (this.descriptors[n] = void 0, this.slides.push(v.fact), this.fonts = v.fonts, this.fontPreloadNames = v.fontNames, this.fontProjectionBytes = v.fontBytes, this.projectionBytesValue = v.committedBytes, this.demandDelta = v.demandDelta, v.demandDelta === "all") this.demandAll = !0, this.demandPoints?.clear();
					else for (let e of v.demandDelta ?? []) this.demandPoints?.add(e);
					this.demandBytes = v.demandBytes, this.demandMembershipBytes = v.demandMembershipBytes, this.demandDeltaBytes = v.demandDeltaBytes, v.state = "committed", this.pending = null;
				}
			},
			rollback: () => {
				if (v.state !== "rolled-back") {
					if (v.state === "committed") throw Error("PPTX presentation preflight cannot roll back a committed slide");
					if (this.pending !== v) throw Error("PPTX presentation preflight prepared slide is stale");
					v.state = "rolled-back", this.pending = null;
				}
			}
		};
	}
	finish() {
		if (this.finished) return this.finished;
		if (this.pending) throw Error("PPTX presentation preflight has an uncommitted slide");
		if (this.slides.length !== this.slideCountValue) throw Error(`PPTX presentation preflight is incomplete: ${this.slides.length}/${this.slideCountValue} slides`);
		return this.finished = Object.freeze({
			slideCount: this.slideCountValue,
			slideWidth: this.slideWidthValue,
			slideHeight: this.slideHeightValue,
			defaultTextColor: this.defaultTextColorValue,
			majorFont: this.majorFontValue,
			minorFont: this.minorFontValue,
			hlinkColor: this.hlinkColorValue,
			folHlinkColor: this.folHlinkColorValue,
			embeddedFonts: this.embeddedFontsValue,
			slides: Object.freeze([...this.slides]),
			fontPreloadNames: this.fontPreloadNames,
			...this.collectFontDemand && !this.demandAll && this.demandBytes ? { fontPreloadDemand: Object.freeze([...this.demandPoints ?? []]) } : {}
		}), this.demandPoints?.clear(), this.demandDelta = void 0, this.descriptors = [], this.slides = [], this.projectionBytesValue = en(this.finished, this.limit).jsonBytes, this.finished;
	}
}, vs = 1024 * 1024, ys = class {
	options;
	active = /* @__PURE__ */ new Set();
	nextSessionId = 1;
	constructor(e) {
		if (this.options = e, !Number.isSafeInteger(e.slideCount) || e.slideCount < 0) throw TypeError("slideCount must be a non-negative safe integer");
		if (e.generation !== void 0 && (!Number.isSafeInteger(e.generation) || e.generation <= 0)) throw TypeError("generation must be a positive safe integer");
	}
	async load(e, t = !0, n) {
		this.assertSlideIndex(e);
		let r = this.nextSessionId++, i = {
			sessionId: r,
			operationId: r,
			generation: this.options.generation ?? 1
		}, a = new be(this.options.transport, {
			...i,
			maxByteCredit: Xe,
			timeoutMs: n
		});
		this.active.add(a);
		try {
			await this.options.open(e, i, n);
			let r = await we(a, vs, Xe);
			try {
				let e = r.usage ?? a.usageCheckpoint;
				e && this.options.onUsage?.(e);
				let n = t ? JSON.parse(new TextDecoder().decode(new Uint8Array(r.payload))) : void 0;
				return await r.ack(), n;
			} finally {
				r.disposeTransferred();
			}
		} catch (e) {
			throw await a.cancel("request-error").catch(() => void 0), e;
		} finally {
			this.active.delete(a);
		}
	}
	cancelAll() {
		for (let e of this.active) e.cancel("closed").catch(() => void 0);
		this.active.clear();
	}
	assertSlideIndex(e) {
		if (!Number.isSafeInteger(e) || e < 0 || e >= this.options.slideCount) throw RangeError(`Slide index ${e} out of range (count: ${this.options.slideCount})`);
	}
};
function bs(e) {
	return !!e && typeof e == "object" && e.protocol === "ooxml-pull-v1";
}
export { ts as a, Go as c, Rr as d, wr as f, ms as i, qo as l, un as m, bs as n, fs as o, jr as p, _s as r, ps as s, ys as t, Jo as u };
