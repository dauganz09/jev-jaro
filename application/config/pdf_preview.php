<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| PDF preview (Puppeteer)
|--------------------------------------------------------------------------
|
| Server-side PDF for report previews. When disabled, the app behaves as
| before (HTML sheet preview only).
|
| Setup (once per server):
|   cd tools/puppeteer-pdf && npm install && npm run install-chrome
|   (install-chrome downloads Chromium into tools/puppeteer-pdf/.puppeteer-cache
|   so PHP/Apache can use it; without this step PDF preview will fall back to HTML.)
|
| If preview falls back to HTML, the server always writes a trace file (even
| when php.ini disables error_log):
|   - {project}/temp/pdf_preview_last_error.txt
|   - or PHP sys temp: jev_pdf_preview_last_error.txt
| The browser dialog also lists these paths when possible.
| Optional: xamppfiles/logs/php_error_log and application/logs (if log_threshold > 0).
|
| NVM only: Apache may not see `node`. Either install Homebrew node, or add
| your NVM binary to pdf_preview_extra_node_paths (see below).
|
| XAMPP / Apache often has no Node in PATH: set pdf_preview_node_path to the
| full path from a terminal: which node
|   (e.g. /opt/homebrew/bin/node or /usr/local/bin/node)
|
| Chromium sandbox: on many local servers Puppeteer needs no-sandbox (below).
| Hardened production: set pdf_preview_chrome_no_sandbox = FALSE if Chrome runs.
|
| Linux containers: PDF_PUPPETEER_NO_SANDBOX=1 in environment, or use the flag.
| Apple Silicon (M1/M2/M3): leave pdf_preview_puppeteer_executable EMPTY so the bundled
| Chrome-for-Testing (arm64) is used. Pointing at Google Chrome.app can run the x64 slice
| via Rosetta and triggers "x64 Chromium on Arm" errors under Apache.
| To force system Chrome anyway: set executable path + env PUPPETEER_FORCE_SYSTEM_CHROME=1 in PHP.
|
| If you see "Timed out after 30000 ms while waiting for the WS endpoint", raise
| pdf_preview_puppeteer_launch_timeout_ms (e.g. 240000). PHP proc timeout must stay higher
| (pdf_preview_timeout_seconds).
|
| Exit -1 with no output: try leaving pdf_preview_puppeteer_executable empty so Puppeteer
| uses Chrome-for-Testing from npm run install-chrome; system Chrome can be killed by macOS
| when launched from Apache. Set pdf_preview_puppeteer_dumpio = TRUE for verbose traces.
|
|--------------------------------------------------------------------------
*/
$config['pdf_preview_enabled'] = true;
$config['pdf_preview_node_path'] = '/opt/homebrew/bin/node';
$config['pdf_preview_extra_node_paths'] = array('/Users/chan/.nvm/versions/node/v20.19.4/bin/node');
$config['pdf_preview_autodetect_nvm_on_mac'] = false;
$config['pdf_preview_puppeteer_executable'] = '';
$config['pdf_preview_puppeteer_force_system_chrome'] = false;
$config['pdf_preview_puppeteer_dumpio'] = false;
$config['pdf_preview_puppeteer_launch_timeout_ms'] = 180000;
$config['pdf_preview_chrome_no_sandbox'] = true;
$config['pdf_preview_script_relative'] = 'tools/puppeteer-pdf/render-pdf.js';
$config['pdf_preview_timeout_seconds'] = 300;
$config['pdf_preview_paper_format'] = 'A4';
$config['pdf_preview_margin_mm'] = 12;
