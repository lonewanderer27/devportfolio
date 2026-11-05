var e = "" + new URL("assets/render-worker-B5HWxkbU.js", import.meta.url).href;
function t() {
	return new Worker(e, { type: "module" });
}
export { t as createRenderWorker };
