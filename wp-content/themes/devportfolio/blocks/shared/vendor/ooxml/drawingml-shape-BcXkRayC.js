import { ar as e, cr as t, fr as n, hr as r, or as i, pr as a, tr as o, vr as s } from "./plot-area-frame-D3JtHvov.js";
import { At as c, Bt as l, Ct as u, Dt as d, Ft as f, Ht as p, It as m, Mt as h, Ot as g, St as _, Ut as v, Vt as y, Wt as b, jt as x, kt as S, zt as C } from "./bounded-raw-part-cache-B8t03tSP.js";
var w = new Set([
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
]), T = new Set([
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
]);
function E(e) {
	return T.has(e) || e === "line" || e === "straightconnector1" || e.startsWith("bentconnector");
}
function D(e) {
	return JSON.stringify({
		geometry: e.geometry,
		stroke: e.stroke,
		fill: e.fill
	});
}
var O = 1;
function k(e, t, n = Infinity) {
	let r = 0;
	if (!(n === Infinity || Number.isSafeInteger(n) && n > 0)) throw new C("Invalid DrawingML geometry budget");
	let i = (e) => {
		if (r += e, !Number.isSafeInteger(r) || r > n) throw new C("DrawingML geometry work budget exceeded");
	}, { x: a, y: o, w: l, h: u } = e.rect;
	if (![
		t,
		e.rect.x,
		e.rect.y,
		l,
		u,
		e.transform.rotationDeg
	].every(Number.isFinite) || t < 0 || l < 0 || u < 0) throw RangeError("Invalid DrawingML geometry frame");
	i(1);
	let f = e.stroke;
	if (i(f?.customDash?.length ?? 0), e.geometry.kind === "preset" && e.geometry.adjustments.some((e) => e !== null && !Number.isFinite(e))) throw RangeError("Non-finite DrawingML adjustment");
	let _ = f ? s(f, t) : null, y = [], k = [], A = (e, t, n, r = "nonzero") => {
		y.push(Object.freeze({
			path: e,
			fill: t,
			stroke: n,
			fillRule: r
		}));
	}, j = (e, n, r, a) => {
		if (!a || !f) return;
		let o = g(a, f, t, O, i);
		if (o) {
			if (![
				e,
				n,
				r
			].every(Number.isFinite)) throw RangeError("Invalid resolved DrawingML arrow frame");
			k.push(Object.freeze({
				tipX: e,
				tipY: n,
				angle: r,
				end: Object.freeze({ ...a }),
				geometry: o
			}));
		}
	}, M = f?.headEnd ? d(f.headEnd, f, t, O) : 0, N = f?.tailEnd ? d(f.tailEnd, f, t, O) : 0, P = (e, t, n, r, a) => {
		if (!(r > 0 || a > 0) || !_) {
			A(e, t, _, n);
			return;
		}
		let o = S(e, r, a);
		i(o.reduce((e, t) => e + 1 + t.args.length, 0)), t !== "none" && A(e, t, null, n), A(o, "none", _, n);
	}, F = p(i);
	if (e.geometry.kind === "preset") {
		i(e.geometry.adjustments.length);
		let t = e.geometry.name.toLowerCase(), n = [...e.geometry.adjustments], r = E(t) && !!(f?.headEnd || f?.tailEnd), s = t.startsWith("curvedconnector"), d = m(t, l, u, n, i, a, o);
		if (d) d.forEach((e, t) => {
			let n = t === d.length - 1 && (e.fill === "none" || e.fill == null);
			s && n && e.stroke ? P(e.path, e.fill, "nonzero", M, N) : A(e.path, e.fill, e.stroke && !(r && n) ? _ : null);
		});
		else {
			let e = p(i);
			c(e.sink, t, a, o, l, u, n[0], n[1], n[2], n[3]), A(Object.freeze(e.commands), null, _, "nonzero");
		}
		if (x(F.sink, e.geometry.name, a, o, l, u, n) || c(F.sink, e.geometry.name, a, o, l, u, n[0], n[1], n[2], n[3]), f && (w.has(t) || T.has(t))) {
			let e = h(t, a, o, l, u, n);
			if (e) {
				if (r && e.vertices.length >= 2) {
					let t = p(i), n = e.vertices;
					t.sink.moveTo(n[0].x, n[0].y);
					for (let e = 1; e < n.length; e++) t.sink.lineTo(n[e].x, n[e].y);
					let r = S(Object.freeze(t.commands), M, N);
					r.length >= 2 && A(r, "none", _);
				}
				j(e.end.x, e.end.y, e.end.angle, f.tailEnd), j(e.start.x, e.start.y, e.start.angle, f.headEnd);
			}
		}
	} else {
		let { subpaths: t } = e.geometry;
		for (let e of t) i(e.length + 1);
		let n = e.geometry.paint?.length === t.length ? e.geometry.paint : null, r = f && (M > 0 || N > 0) ? v(t) : null, s = r?.start ? M : 0, c = r?.end ? N : 0;
		if (n) t.forEach((e, r) => {
			let d = p(i);
			b(d.sink, [e], a, o, l, u);
			let f = Object.freeze(d.commands), m = n[r].fill ?? null;
			n[r].stroke === !1 ? A(f, m, null, n[r].fillRule) : P(f, m, n[r].fillRule ?? "nonzero", r === 0 ? s : 0, r === t.length - 1 ? c : 0);
		});
		else {
			let e = p(i);
			b(e.sink, t, a, o, l, u), P(Object.freeze(e.commands), null, "nonzero", s, c);
		}
		if (b(F.sink, n ? t.filter((e, t) => n[t].fill !== "none") : t, a, o, l, u), f) {
			let e = v(t);
			e.start && j(a + e.start.x * l, o + e.start.y * u, Math.atan2(e.start.dy * u, e.start.dx * l), f.headEnd), e.end && j(a + e.end.x * l, o + e.end.y * u, Math.atan2(e.end.dy * u, e.end.dx * l), f.tailEnd);
		}
	}
	return Object.freeze({
		width: l,
		height: u,
		scale: t,
		originX: a,
		originY: o,
		sourceKey: D(e),
		paths: Object.freeze(y),
		arrows: Object.freeze(k),
		fillSilhouette: Object.freeze(F.commands),
		workUnits: r
	});
}
function A(e, t) {
	let n = e.resolvedGeometry;
	if (![
		t,
		e.rect.x,
		e.rect.y,
		e.rect.w,
		e.rect.h,
		e.transform.rotationDeg
	].every(Number.isFinite) || t < 0 || e.rect.w < 0 || e.rect.h < 0 || typeof e.transform.flipH != "boolean" || typeof e.transform.flipV != "boolean") throw RangeError("Invalid retained DrawingML frame");
	if (!n || n.width !== e.rect.w || n.height !== e.rect.h || n.scale !== t || n.sourceKey !== D(e)) throw RangeError("Missing or stale retained DrawingML geometry");
	if (![n.originX, n.originY].every(Number.isFinite)) throw RangeError("Invalid retained DrawingML origin");
	if (!Number.isSafeInteger(n.workUnits) || n.workUnits < 1 || !Array.isArray(n.paths) || !Array.isArray(n.arrows)) throw RangeError("Invalid retained DrawingML work/scheduling");
	if (n.paths.length + n.arrows.length > n.workUnits) throw new C("Retained geometry scheduling exceeds its acquired work");
	let r = 0, i = (e) => {
		if (r += e, r > n.workUnits) throw new C("Retained geometry paths exceed their acquired work");
	}, a = (e) => {
		if (!Number.isFinite(e.lineWidth) || e.lineWidth < 0 || !Number.isFinite(e.miterLimit) || e.miterLimit <= 0 || ![
			"butt",
			"round",
			"square"
		].includes(e.lineCap) || ![
			"round",
			"bevel",
			"miter"
		].includes(e.lineJoin) || !Array.isArray(e.dash) || e.dash.length > n.workUnits || !e.dash.every((e) => Number.isFinite(e) && e >= 0)) throw RangeError("Invalid retained stroke geometry");
	};
	for (let e of n.paths) {
		if (y(e.path, i), e.fill !== null && ![
			"norm",
			"none",
			"lighten",
			"lightenLess",
			"darken",
			"darkenLess"
		].includes(e.fill)) throw RangeError("Invalid retained path fill");
		if (e.fillRule !== "nonzero" && e.fillRule !== "evenodd") throw RangeError("Invalid retained fill rule");
		e.stroke && a(e.stroke);
	}
	y(n.fillSilhouette, i);
	for (let e of n.arrows) {
		if (![
			e.tipX,
			e.tipY,
			e.angle
		].every(Number.isFinite) || !["fill", "stroke"].includes(e.geometry.paint)) throw RangeError("Invalid retained arrow frame");
		y(e.geometry.path, i), a(e.geometry.stroke);
	}
	return n;
}
function j(e, t) {
	let n = {
		a: e.a * t.a + e.c * t.b,
		b: e.b * t.a + e.d * t.b,
		c: e.a * t.c + e.c * t.d,
		d: e.b * t.c + e.d * t.d,
		e: e.a * t.e + e.c * t.f + e.e,
		f: e.b * t.e + e.d * t.f + e.f
	};
	if (!Object.values(n).every(Number.isFinite) || !Number.isFinite(n.a * n.d - n.b * n.c) || n.a * n.d - n.b * n.c === 0) throw RangeError("Invalid DrawingML geometry transform");
	return n;
}
var M = {
	a: 1,
	b: 0,
	c: 0,
	d: 1,
	e: 0,
	f: 0
}, N = (e, t) => ({
	...M,
	e,
	f: t
}), P = (e) => ({
	a: Math.cos(e),
	b: Math.sin(e),
	c: -Math.sin(e),
	d: Math.cos(e),
	e: 0,
	f: 0
});
function F(e, t = M) {
	let n = A(e, 1), { x: i, y: a, w: o, h: c } = e.rect, l = e.transform, u = t;
	(l.rotationDeg !== 0 || l.flipH || l.flipV) && (u = j(u, N(i + o / 2, a + c / 2)), l.rotationDeg !== 0 && (u = j(u, P(l.rotationDeg * Math.PI / 180))), u = j(u, {
		...M,
		a: l.flipH ? -1 : 1,
		d: l.flipV ? -1 : 1
	}), u = j(u, N(-(i + o / 2), -(a + c / 2)))), u = j(u, N(i - n.originX, a - n.originY));
	let d = [], f = s(null, 1), p = (e, t, n) => {
		let i = r(e, t, n);
		i && d.push(i);
	};
	for (let t of n.paths) e.fill && e.fill.fillType !== "none" && t.fill !== "none" && p(t.path, u, f), t.stroke && p(t.path, u, t.stroke);
	for (let e of n.arrows) {
		let t = j(j(u, N(e.tipX, e.tipY)), P(e.angle));
		p(e.geometry.path, t, e.geometry.paint === "stroke" ? e.geometry.stroke : f);
	}
	if (d.length === 0) return null;
	let m = Infinity, h = Infinity, g = -Infinity, _ = -Infinity;
	for (let e of d) m = Math.min(m, e.x), h = Math.min(h, e.y), g = Math.max(g, e.x + e.w), _ = Math.max(_, e.y + e.h);
	let v = {
		x: m,
		y: h,
		w: g - m,
		h: _ - h
	};
	if (!Object.values(v).every(Number.isFinite)) throw RangeError("Non-finite DrawingML enclosure");
	return v;
}
var I = 1;
function L(e, t, n) {
	let { x: r, y: i, w: a, h: o } = t.rect, { rotationDeg: s, flipH: c, flipV: l } = t.transform;
	e.save();
	try {
		(s !== 0 || c || l) && (e.translate(r + a / 2, i + o / 2), s !== 0 && e.rotate(s * Math.PI / 180), e.scale(c ? -1 : 1, l ? -1 : 1), e.translate(-(r + a / 2), -(i + o / 2))), n();
	} finally {
		e.restore();
	}
}
function R(e, t, n, r, i, a) {
	let o = t.resolvedGeometry ? A(t, t.resolvedGeometry.scale) : k(t, 1);
	if (i !== o.width || a !== o.height) throw RangeError("Retained DrawingML silhouette frame mismatch");
	l(e, o.fillSilhouette, n - o.originX, r - o.originY);
}
function z(e, t) {
	let { x: n, y: r, w: i, h: a } = t.rect;
	e.beginPath(), R(e, t, n, r, i, a), e.clip();
}
function B(t, r, a, s, c) {
	if (t.strokeStyle = e(r.color), o(t, a), r.fill) {
		let e = i(r.fill, t, s.x, s.y, s.w, s.h, c, I, void 0, void 0, n(t));
		e && (t.strokeStyle = e);
	}
}
function V(e, n, r) {
	let o = n.resolvedGeometry ? A(n, r) : k(n, r), s = n.resolvedGeometry ? n : {
		...n,
		resolvedGeometry: o
	};
	t(n.stroke?.fill) && (e = a(e));
	let { x: c, y: d, w: p, h: m } = n.rect;
	L(e, n, () => {
		let t = i(n.fill, e, c, d, p, m, n.transform.rotationDeg, I, void 0, (e, t, n, r, i) => R(e, s, t, n, r, i)), a = n.stroke;
		for (let r of o.paths) {
			if (e.beginPath(), l(e, r.path, c - o.originX, d - o.originY), t && r.fill !== "none") {
				e.save();
				try {
					e.fillStyle = t, r.fillRule === "evenodd" ? e.fill("evenodd") : e.fill();
					let n = f(r.fill);
					n && (e.fillStyle = n, r.fillRule === "evenodd" ? e.fill("evenodd") : e.fill());
				} finally {
					e.restore();
				}
			}
			a && r.stroke && (B(e, a, r.stroke, n.rect, n.transform.rotationDeg), e.stroke());
		}
		if (a) for (let t of o.arrows) {
			let i = c - o.originX + t.tipX, s = d - o.originY + t.tipY;
			_(e, i, s, t.angle, t.geometry, a, u(e, a, r, I, i, s, t.angle, t.end, n.rect, n.transform.rotationDeg, I));
		}
	});
}
export { A as a, F as i, V as n, k as o, L as r, z as t };
