#!/usr/bin/env node
/**
 * Usage: node render-pdf.js <inputHtmlPath> <outputPdfPath>
 * Reads UTF-8 HTML from disk, writes PDF. Exits 0 on success, 1 on error.
 *
 * Env:
 *   PUPPETEER_CACHE_DIR - project .puppeteer-cache (PHP sets this)
 *   PUPPETEER_EXECUTABLE_PATH - optional override path to Chrome/Chromium
 *   PDF_PUPPETEER_NO_SANDBOX=1 - add --no-sandbox (common under Apache/Docker)
 *   PDF_PUPPETEER_DUMPIO=1 - pass dumpio:true to Puppeteer (noisy, for debug)
 *   PUPPETEER_LAUNCH_TIMEOUT_MS - browser start timeout (default 180000; Puppeteer default 30000 is often too low under Apache)
 *   PUPPETEER_PROTOCOL_TIMEOUT_MS - CDP protocol timeout (defaults to launch timeout)
 *   PDF_PAPER_FORMAT - default A4
 *   PDF_MARGIN_MM - default 12 (applied to all sides)
 *   PDF_RENDER_DEBUG_LOG - PHP sets this; sync milestones appended for Apache debugging
 */
const fs = require("fs");
const path = require("path");

function pdfDbg(line) {
  const p = process.env.PDF_RENDER_DEBUG_LOG;
  if (!p) return;
  try {
    fs.appendFileSync(p, new Date().toISOString() + " " + line + "\n");
  } catch (e) {
    /* ignore */
  }
}

pdfDbg("boot pid=" + process.pid + " arch=" + process.arch + " cwd=" + process.cwd());

let puppeteer;
try {
  puppeteer = require("puppeteer");
  pdfDbg("require(puppeteer) ok");
} catch (e) {
  pdfDbg("require(puppeteer) FAIL: " + (e && e.message ? e.message : e));
  console.error(e);
  process.exit(1);
}

function findBundledChromeExecutable(cacheRoot) {
  if (!cacheRoot || !fs.existsSync(cacheRoot)) return null;
  const chromeDir = path.join(cacheRoot, "chrome");
  if (!fs.existsSync(chromeDir)) return null;
  let versions;
  try {
    versions = fs.readdirSync(chromeDir);
  } catch (e) {
    return null;
  }

  const macTestingRel = (folder) =>
    path.join(folder, "Google Chrome for Testing.app", "Contents", "MacOS", "Google Chrome for Testing");

  let relativeCandidates = [];
  if (process.platform === "darwin") {
    if (process.arch === "arm64") {
      relativeCandidates.push(macTestingRel("chrome-mac-arm64"));
    } else {
      relativeCandidates.push(macTestingRel("chrome-mac-x64"));
    }
  } else if (process.platform === "linux") {
    relativeCandidates.push(path.join("chrome-linux64", "chrome"));
  } else if (process.platform === "win32") {
    relativeCandidates.push(path.join("chrome-win64", "chrome.exe"));
  }

  for (const v of versions) {
    const vPath = path.join(chromeDir, v);
    let st;
    try {
      st = fs.statSync(vPath);
    } catch (e) {
      continue;
    }
    if (!st.isDirectory()) continue;
    for (const rel of relativeCandidates) {
      const candidate = path.join(vPath, rel);
      try {
        if (fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
          return candidate;
        }
      } catch (e) {
        /* continue */
      }
    }
  }
  return null;
}

async function main() {
  const inputPath = process.argv[2];
  const outputPath = process.argv[3];
  if (!inputPath || !outputPath) {
    console.error("Usage: node render-pdf.js <inputHtmlPath> <outputPdfPath>");
    process.exit(1);
  }

  const absIn = path.resolve(inputPath);
  const absOut = path.resolve(outputPath);
  if (!fs.existsSync(absIn)) {
    console.error("Input HTML not found:", absIn);
    process.exit(1);
  }

  const html = fs.readFileSync(absIn, "utf8");
  const paperFormat = process.env.PDF_PAPER_FORMAT || "A4";
  const marginMm = process.env.PDF_MARGIN_MM || "12";
  const margin = `${marginMm}mm`;

  const cacheDir = process.env.PUPPETEER_CACHE_DIR || "";
  const fromCache = findBundledChromeExecutable(cacheDir);
  const explicitPath = process.env.PUPPETEER_EXECUTABLE_PATH
    ? process.env.PUPPETEER_EXECUTABLE_PATH.trim()
    : "";

  const launchTimeoutMs = Math.min(
    600000,
    Math.max(30000, parseInt(process.env.PUPPETEER_LAUNCH_TIMEOUT_MS || "180000", 10) || 180000)
  );
  const protocolTimeoutMs = Math.min(
    600000,
    Math.max(
      30000,
      parseInt(process.env.PUPPETEER_PROTOCOL_TIMEOUT_MS || String(launchTimeoutMs), 10) || launchTimeoutMs
    )
  );

  const launchOpts = {
    headless: true,
    pipe: true,
    args: ["--disable-gpu"],
    dumpio: process.env.PDF_PUPPETEER_DUMPIO === "1",
    timeout: launchTimeoutMs,
    protocolTimeout: protocolTimeoutMs,
  };

  const isAppleSilicon = process.platform === "darwin" && process.arch === "arm64";
  const forceSystemChrome = process.env.PUPPETEER_FORCE_SYSTEM_CHROME === "1";

  if (isAppleSilicon && fromCache && !forceSystemChrome) {
    launchOpts.executablePath = fromCache;
    if (explicitPath) {
      console.error(
        "Using bundled Chrome-for-Testing (arm64). System Chrome was skipped to avoid Rosetta/x64 Chromium under Apache. " +
          "Clear pdf_preview_puppeteer_executable or set env PUPPETEER_FORCE_SYSTEM_CHROME=1 to force system Chrome."
      );
    }
  } else if (explicitPath) {
    launchOpts.executablePath = explicitPath;
  } else if (fromCache) {
    launchOpts.executablePath = fromCache;
  }

  if (process.env.PDF_PUPPETEER_NO_SANDBOX === "1") {
    launchOpts.args.push("--no-sandbox", "--disable-setuid-sandbox");
  }

  if (!launchOpts.executablePath) {
    console.error(
      "No Chrome executable: set PUPPETEER_EXECUTABLE_PATH or install browsers with npm run install-chrome (PUPPETEER_CACHE_DIR=",
      cacheDir || "(unset)",
      ")"
    );
    process.exit(1);
  }

  pdfDbg("chrome executable=" + launchOpts.executablePath);
  try {
    fs.accessSync(launchOpts.executablePath, fs.constants.X_OK);
    pdfDbg("chrome X_OK ok");
  } catch (e) {
    pdfDbg("chrome NOT executable for this user: " + (e && e.message ? e.message : e));
  }

  let browser;
  try {
    pdfDbg("puppeteer.launch starting");
    browser = await puppeteer.launch(launchOpts);
    pdfDbg("puppeteer.launch done");
    const page = await browser.newPage();
    await page.setContent(html, { waitUntil: "load" });
    await page.pdf({
      path: absOut,
      printBackground: true,
      format: paperFormat,
      margin: { top: margin, right: margin, bottom: margin, left: margin },
    });
    pdfDbg("page.pdf done");
  } catch (err) {
    const msg = err && err.stack ? err.stack : err && err.message ? err.message : String(err);
    pdfDbg("ERROR: " + msg);
    console.error(msg);
    process.exit(1);
  } finally {
    if (browser) {
      try {
        await browser.close();
      } catch (e) {
        console.error("browser.close:", e && e.message ? e.message : e);
      }
    }
  }

  if (!fs.existsSync(absOut) || fs.statSync(absOut).size === 0) {
    pdfDbg("FAIL: PDF missing or empty at " + absOut);
    console.error("PDF was not written or is empty");
    process.exit(1);
  }
  pdfDbg("SUCCESS bytes=" + fs.statSync(absOut).size);
}

main().catch((err) => {
  pdfDbg("main() catch: " + (err && err.stack ? err.stack : err));
  console.error(err && err.stack ? err.stack : err);
  process.exit(1);
});
