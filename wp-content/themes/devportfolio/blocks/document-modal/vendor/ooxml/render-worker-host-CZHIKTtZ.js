var e = "" + new URL("assets/render-worker-B3hF7Akg.js", import.meta.url).href;
function t() {
	return new Worker(e, { type: "module" });
}
export { t as createRenderWorker };
