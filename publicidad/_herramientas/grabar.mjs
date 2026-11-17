// Graba un HTML animado fotograma a fotograma con Chrome headless.
// Uso: node grabar.mjs <archivo.html> <carpeta_frames> <segundos> [fps]
import { spawn } from "node:child_process";
import { mkdirSync, writeFileSync, rmSync } from "node:fs";
import { pathToFileURL } from "node:url";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";

const [html, outDir, secs, fpsArg] = process.argv.slice(2);
const fps = Number(fpsArg || 30), D = Number(secs);
const port = 9300 + Math.floor(Math.random() * 500);
const prof = join(tmpdir(), "reelprof" + port);
const chrome = spawn("C:/Program Files/Google/Chrome/Application/chrome.exe", [
  "--headless=new", "--disable-gpu", "--hide-scrollbars", `--remote-debugging-port=${port}`,
  `--user-data-dir=${prof}`, "--allow-file-access-from-files", "--window-size=1080,1920", "about:blank"]);

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let target;
for (let i = 0; i < 60 && !target; i++) {
  try { target = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find((t) => t.type === "page"); }
  catch { await sleep(250); }
}
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((r) => ws.addEventListener("open", r));
let id = 0; const pend = new Map();
ws.addEventListener("message", (e) => { const m = JSON.parse(e.data); if (m.id && pend.has(m.id)) { pend.get(m.id)(m); pend.delete(m.id); } });
const send = (method, params = {}) => new Promise((r) => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });

await send("Emulation.setDeviceMetricsOverride", { width: 1080, height: 1920, deviceScaleFactor: 1, mobile: false });
await send("Page.enable");
await send("Page.navigate", { url: pathToFileURL(resolve(html)).href });
await sleep(2500);
await send("Runtime.evaluate", { expression: "document.fonts.ready.then(()=>1)", awaitPromise: true });
await sleep(800);

mkdirSync(outDir, { recursive: true });
const total = Math.round(D * fps);
for (let f = 0; f < total; f++) {
  const t = (f / fps) * 1000;
  await send("Runtime.evaluate", { expression: `document.getAnimations().forEach(a=>{a.pause();a.currentTime=${t}})` });
  const r = await send("Page.captureScreenshot", { format: "jpeg", quality: 93 });
  writeFileSync(join(outDir, `f${String(f).padStart(4, "0")}.jpg`), Buffer.from(r.result.data, "base64"));
}
ws.close(); chrome.kill();
await sleep(500);
try { rmSync(prof, { recursive: true, force: true }); } catch {}
console.log("frames", total);
