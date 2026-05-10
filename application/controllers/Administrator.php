<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class Administrator extends CI_Controller {
	private $previewMode = false;
	private $subsidiaryTable = 'tbl_subsidiaries';
	private $bankAccountsTable = 'tbl_bank_accounts';
	private $pdfPreviewLastFailureSummary = '';

	private function enablePreviewMode(){
		$this->previewMode = true;
	}

	private function isPreviewRequest(){
		return $this->previewMode === true;
	}

	private function respondNoData($message, $redirectPath){
		if($this->isPreviewRequest()){
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'success' => false,
					'message' => $message
				)));
			return false;
		}

		$this->session->set_flashdata('error', $message);
		redirect($redirectPath);
		return false;
	}

	private function writePdfPreviewFailureTraceFile($body){
		$header = '=== '.date('c').' | SAPI='.PHP_SAPI." ===\n";
		$full = $header.$body;
		if(substr($full, -1) !== "\n"){
			$full .= "\n";
		}

		$candidates = array(
			FCPATH.'temp'.DIRECTORY_SEPARATOR.'pdf_preview_last_error.txt',
			rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'jev_pdf_preview_last_error.txt',
		);

		foreach($candidates as $path){
			$dir = dirname($path);
			if(!is_dir($dir)){
				@mkdir($dir, 0775, true);
			}
			if(@file_put_contents($path, $full, LOCK_EX) !== false){
				return $path;
			}
		}
		return false;
	}

	private function appendPdfPuppeteerJsDebugToDetail($detail){
		$jsDebugLogPath = FCPATH.'temp'.DIRECTORY_SEPARATOR.'pdf_puppeteer_js_debug.log';
		if(!is_file($jsDebugLogPath)){
			return $detail;
		}
		$chunk = @file_get_contents($jsDebugLogPath);
		if($chunk === false || $chunk === ''){
			return $detail;
		}
		$max = 8192;
		if(strlen($chunk) > $max){
			$chunk = "...(truncated from start)...\n".substr($chunk, -$max);
		}
		$sep = ($detail !== '' && substr($detail, -1) !== "\n") ? "\n" : '';
		return $detail.$sep."\n--- pdf_puppeteer_js_debug.log ---\n".$chunk;
	}

	private function failPdfPreviewCli($headline, $detail = ''){
		$this->pdfPreviewLastFailureSummary = $headline;
		$body = $headline;
		if($detail !== ''){
			$body .= "\n\n".$detail;
		}
		$written = $this->writePdfPreviewFailureTraceFile($body);
		$logLine = 'PDF preview: '.$headline.($detail !== '' ? ' | '.str_replace("\n", ' ', substr($detail, 0, 500)) : '');
		log_message('error', $logLine);
		if(function_exists('error_log')){
			@error_log($logLine);
		}
		if($written === false){
			if(function_exists('error_log')){
				@error_log('PDF preview: could not write trace file (check temp/ and sys temp permissions)');
			}
		}
		return false;
	}

	private function getPreviewFormatFromRequest(){
		$post = $this->input->post('preview_format');
		if($post !== null && $post !== ''){
			return strtolower(trim((string)$post));
		}
		$get = $this->input->get('preview_format');
		if($get !== null && $get !== ''){
			return strtolower(trim((string)$get));
		}
		return '';
	}

	private function extractHtmlBodyInner($html){
		if(preg_match('~<body[^>]*>(.*)</body>~is', $html, $matches)){
			return trim($matches[1]);
		}
		return trim($html);
	}

	private function extractHtmlStyleBlocks($html){
		if(preg_match_all('~<style[^>]*>(.*?)</style>~is', $html, $matches)){
			return "\n".implode("\n", $matches[1]);
		}
		return '';
	}

	private function resolvePdfPreviewNodeExecutable($configuredPath){
		$this->config->load('pdf_preview');

		$configuredPath = trim((string)$configuredPath);
		if($configuredPath !== '' && $configuredPath !== 'node'){
			if(@is_file($configuredPath) && @is_executable($configuredPath)){
				return $configuredPath;
			}
			$msg = 'PDF preview: pdf_preview_node_path is missing or not executable: '.$configuredPath;
			log_message('error', $msg);
			error_log($msg);
		}

		$extraPaths = $this->config->item('pdf_preview_extra_node_paths');
		if(is_array($extraPaths)){
			foreach($extraPaths as $extraPath){
				$extraPath = trim((string)$extraPath);
				if($extraPath !== '' && @is_file($extraPath) && @is_executable($extraPath)){
					return $extraPath;
				}
			}
		}

		$candidates = array('/opt/homebrew/bin/node', '/usr/local/bin/node', '/usr/bin/node');
		foreach($candidates as $candidate){
			if(@is_file($candidate) && @is_executable($candidate)){
				return $candidate;
			}
		}

		$autoNvm = $this->config->item('pdf_preview_autodetect_nvm_on_mac');
		if($autoNvm !== false && $autoNvm !== '0' && DIRECTORY_SEPARATOR === '/'){
			$isDarwin = (defined('PHP_OS') && stripos(PHP_OS, 'Darwin') !== false);
			if($isDarwin){
				$nvmMatches = glob('/Users/*/.nvm/versions/node/v*/bin/node', GLOB_NOSORT);
				if(is_array($nvmMatches) && count($nvmMatches) > 0){
					$bestPath = '';
					$bestMtime = -1;
					foreach($nvmMatches as $nvmNode){
						if(@is_file($nvmNode) && @is_executable($nvmNode)){
							$mtime = @filemtime($nvmNode);
							if($mtime !== false && $mtime > $bestMtime){
								$bestMtime = $mtime;
								$bestPath = $nvmNode;
							}
						}
					}
					if($bestPath !== ''){
						return $bestPath;
					}
				}
			}
		}

		if(function_exists('shell_exec')){
			$which = @shell_exec('command -v node 2>/dev/null');
			if($which){
				$which = trim($which);
				if($which !== '' && @is_executable($which)){
					return $which;
				}
			}
		}

		return $configuredPath !== '' ? $configuredPath : 'node';
	}

	private function mergePreviewSheetsHtmlForPdf($previewSheets){
		$combinedStyles = '';
		$blocks = '';
		foreach($previewSheets as $sheet){
			$combinedStyles .= $this->extractHtmlStyleBlocks($sheet['html']);
			$body = $this->extractHtmlBodyInner($sheet['html']);
			$name = htmlspecialchars($sheet['name'], ENT_QUOTES, 'UTF-8');
			$blocks .= '<div class="preview-pdf-sheet"><h2 class="preview-pdf-sheet-title">'.$name.'</h2>'.$body.'</div>';
		}

		$globalCss = '
			.preview-pdf-sheet { page-break-after: always; }
			.preview-pdf-sheet:last-child { page-break-after: auto; }
			html, body { margin: 0; padding: 0; }
			table { border-collapse: collapse; width: auto !important; max-width: none !important; min-width: max-content !important; }
			.preview-pdf-sheet-title { font-family: Arial, Helvetica, sans-serif; font-size: 14px; margin: 0 0 8px 0; }
		';

		return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
			.$globalCss.$combinedStyles
			.'</style></head><body>'
			.$blocks
			.'</body></html>';
	}

	private function runPuppeteerPdfCli($mergedHtml){
		$this->config->load('pdf_preview');
		$this->pdfPreviewLastFailureSummary = '';

		if(!$this->config->item('pdf_preview_enabled')){
			return false;
		}

		$node = $this->resolvePdfPreviewNodeExecutable($this->config->item('pdf_preview_node_path'));
		if($node !== 'node' && (!@is_file($node) || !@is_executable($node))){
			return $this->failPdfPreviewCli(
				'Node is not executable by the web server.',
				'Resolved path: '.$node."\n".'Use pdf_preview_node_path (e.g. /opt/homebrew/bin/node) and ensure chmod +x and traverse permissions for the Apache/PHP user.'
			);
		}
		$relativeScript = $this->config->item('pdf_preview_script_relative');
		if(!$relativeScript){
			return $this->failPdfPreviewCli('Config pdf_preview_script_relative is empty.');
		}
		$scriptPath = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $relativeScript);
		if(!is_file($scriptPath)){
			return $this->failPdfPreviewCli('render-pdf.js not found.', $scriptPath);
		}

		$projectPuppeteerCache = FCPATH.'tools'.DIRECTORY_SEPARATOR.'puppeteer-pdf'.DIRECTORY_SEPARATOR.'.puppeteer-cache';
		if(!is_dir($projectPuppeteerCache)){
			@mkdir($projectPuppeteerCache, 0775, true);
		}
		$chromeVendorDirs = glob($projectPuppeteerCache.DIRECTORY_SEPARATOR.'chrome'.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);
		$hasChromeBundle = is_array($chromeVendorDirs) && count($chromeVendorDirs) > 0;
		if(!$hasChromeBundle){
			return $this->failPdfPreviewCli(
				'Chromium is not installed for Puppeteer (project cache empty).',
				"Run in terminal:\ncd tools/puppeteer-pdf\nnpm install\nnpm run install-chrome\n\nExpected folder:\n".$projectPuppeteerCache.DIRECTORY_SEPARATOR.'chrome'
			);
		}
		if(!is_writable($projectPuppeteerCache)){
			return $this->failPdfPreviewCli('Puppeteer cache directory is not writable by the web server.', $projectPuppeteerCache);
		}

		$timeout = (int)$this->config->item('pdf_preview_timeout_seconds');
		if($timeout < 10){
			$timeout = 10;
		}
		if($timeout > 600){
			$timeout = 600;
		}

		$paper = $this->config->item('pdf_preview_paper_format');
		if(!$paper){
			$paper = 'A4';
		}
		$marginMm = (int)$this->config->item('pdf_preview_margin_mm');
		if($marginMm < 0){
			$marginMm = 12;
		}

		$tmpHtml = tempnam(sys_get_temp_dir(), 'jev_pdf_');
		if($tmpHtml === false){
			return $this->failPdfPreviewCli('tempnam() failed for HTML input.', 'sys_get_temp_dir()='.sys_get_temp_dir());
		}
		$tmpPdfBase = tempnam(sys_get_temp_dir(), 'jev_pdf_');
		if($tmpPdfBase === false){
			@unlink($tmpHtml);
			return $this->failPdfPreviewCli('tempnam() failed for PDF output.', 'sys_get_temp_dir()='.sys_get_temp_dir());
		}
		@unlink($tmpPdfBase);
		$tmpPdfPath = $tmpPdfBase.'.pdf';

		if(@file_put_contents($tmpHtml, $mergedHtml) === false){
			@unlink($tmpHtml);
			return $this->failPdfPreviewCli('Could not write temp HTML for Puppeteer.', $tmpHtml);
		}

		$command = escapeshellarg($node).' '.escapeshellarg($scriptPath).' '.escapeshellarg($tmpHtml).' '.escapeshellarg($tmpPdfPath);
		$descriptorSpec = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);

		$env = array();
		foreach(array_merge($_SERVER, $_ENV) as $key => $value){
			if(is_string($key) && is_string($value)){
				$env[$key] = $value;
			}
		}
		$env['PDF_PAPER_FORMAT'] = (string)$paper;
		$env['PDF_MARGIN_MM'] = (string)$marginMm;

		$launchTimeoutMs = (int)$this->config->item('pdf_preview_puppeteer_launch_timeout_ms');
		if($launchTimeoutMs < 30000){
			$launchTimeoutMs = 180000;
		}
		if($launchTimeoutMs > 600000){
			$launchTimeoutMs = 600000;
		}
		$env['PUPPETEER_LAUNCH_TIMEOUT_MS'] = (string)$launchTimeoutMs;
		$env['PUPPETEER_PROTOCOL_TIMEOUT_MS'] = (string)$launchTimeoutMs;

		$pathPrefix = '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin';
		$existingPath = isset($env['PATH']) ? $env['PATH'] : '';
		$env['PATH'] = $pathPrefix.(($existingPath !== '') ? PATH_SEPARATOR.$existingPath : '');

		if($this->config->item('pdf_preview_chrome_no_sandbox')){
			$env['PDF_PUPPETEER_NO_SANDBOX'] = '1';
			$env['PUPPETEER_DANGEROUS_NO_SANDBOX'] = 'true';
		}

		if($this->config->item('pdf_preview_puppeteer_dumpio')){
			$env['PDF_PUPPETEER_DUMPIO'] = '1';
		}

		if($this->config->item('pdf_preview_puppeteer_force_system_chrome')){
			$env['PUPPETEER_FORCE_SYSTEM_CHROME'] = '1';
		}

		$puppeteerExe = trim((string)$this->config->item('pdf_preview_puppeteer_executable'));
		if($puppeteerExe !== '' && @is_file($puppeteerExe)){
			$env['PUPPETEER_EXECUTABLE_PATH'] = $puppeteerExe;
		}

		$env['PUPPETEER_CACHE_DIR'] = $projectPuppeteerCache;

		$projectTempDir = FCPATH.'temp';
		if(!is_dir($projectTempDir)){
			@mkdir($projectTempDir, 0775, true);
		}
		if(is_dir($projectTempDir) && is_writable($projectTempDir)){
			$env['TMPDIR'] = $projectTempDir;
			$env['TMP'] = $projectTempDir;
			$env['TEMP'] = $projectTempDir;
		}
		$jsDebugLogPath = $projectTempDir.DIRECTORY_SEPARATOR.'pdf_puppeteer_js_debug.log';
		@file_put_contents($jsDebugLogPath, "\n=== ".date('c').' PDF render (PHP) ==='."\n", FILE_APPEND | LOCK_EX);
		$env['PDF_RENDER_DEBUG_LOG'] = $jsDebugLogPath;

		$puppetHome = FCPATH.'temp'.DIRECTORY_SEPARATOR.'.puppeteer_chrome_profile';
		if(!is_dir($puppetHome)){
			@mkdir($puppetHome, 0775, true);
		}
		if(is_dir($puppetHome) && is_writable($puppetHome)){
			$env['HOME'] = $puppetHome;
		}

		if(function_exists('set_time_limit')){
			$phpTimeBudget = $timeout + 120;
			if($phpTimeBudget < 180){
				$phpTimeBudget = 180;
			}
			@ini_set('max_execution_time', (string)$phpTimeBudget);
			@set_time_limit($phpTimeBudget);
		}

		$puppeteerToolDir = FCPATH.'tools'.DIRECTORY_SEPARATOR.'puppeteer-pdf';
		$process = @proc_open($command, $descriptorSpec, $pipes, is_dir($puppeteerToolDir) ? $puppeteerToolDir : null, $env, null);
		if(!is_resource($process)){
			@unlink($tmpHtml);
			return $this->failPdfPreviewCli(
				'proc_open() failed (Node/Puppeteer could not be started).',
				'node='.$node."\n".'Check php.ini disable_functions, and that Node is executable by the web server user.'."\n".'Command: '.$command
			);
		}

		fclose($pipes[0]);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		$stderr = '';
		$stdout = '';
		$start = time();
		$lastStatus = null;
		while(true){
			$lastStatus = proc_get_status($process);
			if(!$lastStatus['running']){
				break;
			}
			if((time() - $start) > $timeout){
				proc_terminate($process);
				$killWait = time();
				while(proc_get_status($process)['running'] && (time() - $killWait) < 15){
					usleep(100000);
				}
				$stdout .= (string)stream_get_contents($pipes[1]);
				$stderr .= (string)stream_get_contents($pipes[2]);
				@fclose($pipes[1]);
				@fclose($pipes[2]);
				@proc_close($process);
				@unlink($tmpHtml);
				@unlink($tmpPdfPath);
				$detail = 'node='.$node."\n";
				$outT = trim($stdout);
				$errT = trim($stderr);
				if($outT !== ''){
					$detail .= "stdout:\n".(strlen($outT) > 1500 ? substr($outT, 0, 1500).'…' : $outT)."\n";
				}
				if($errT !== ''){
					$detail .= "stderr:\n".(strlen($errT) > 1500 ? substr($errT, 0, 1500).'…' : $errT)."\n";
				}
				$detail = $this->appendPdfPuppeteerJsDebugToDetail($detail);
				return $this->failPdfPreviewCli('Puppeteer CLI timed out after '.$timeout.' seconds.', $detail);
			}
			$stdout .= (string)stream_get_contents($pipes[1]);
			$stderr .= (string)stream_get_contents($pipes[2]);
			usleep(100000);
		}

		$stdout .= (string)stream_get_contents($pipes[1]);
		$stderr .= (string)stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exitFromStatus = ($lastStatus !== null && array_key_exists('exitcode', $lastStatus)) ? (int)$lastStatus['exitcode'] : -1;
		$exitFromClose = proc_close($process);
		$exitCode = ($exitFromStatus !== -1) ? $exitFromStatus : (int)$exitFromClose;

		@unlink($tmpHtml);

		if($exitCode !== 0){
			$stdoutTrim = trim($stdout);
			$stderrTrim = trim($stderr);
			if(strlen($stdoutTrim) > 1500){
				$stdoutTrim = substr($stdoutTrim, 0, 1500).'…';
			}
			if(strlen($stderrTrim) > 1500){
				$stderrTrim = substr($stderrTrim, 0, 1500).'…';
			}
			$detail = 'node='.$node."\n";
			if($stdoutTrim !== ''){
				$detail .= "stdout:\n".$stdoutTrim."\n";
			}
			if($stderrTrim !== ''){
				$detail .= "stderr:\n".$stderrTrim."\n";
			}
			if($exitCode === -1 && defined('PHP_OS') && stripos(PHP_OS, 'Darwin') !== false){
				$detail .= "\n---\nExit -1: Chrome/Node was killed or PHP cut the request short. Try:\n"
					."1) Raise max_execution_time in php.ini (e.g. 300+) for Apache.\n"
					."2) Clear pdf_preview_puppeteer_executable (use bundled Chrome from npm run install-chrome).\n"
					."3) Or keep system Chrome but ensure it matches your Puppeteer version.\n"
					."4) Enable pdf_preview_puppeteer_dumpio in config and retry; check trace for Chrome logs.\n";
			}
			$detail = $this->appendPdfPuppeteerJsDebugToDetail($detail);
			@unlink($tmpPdfPath);
			return $this->failPdfPreviewCli('Puppeteer exited with code '.$exitCode.'.', $detail);
		}

		if(!is_file($tmpPdfPath) || filesize($tmpPdfPath) < 1){
			@unlink($tmpPdfPath);
			return $this->failPdfPreviewCli('PDF file missing or empty after Puppeteer.', 'node='.$node);
		}

		$pdfBinary = file_get_contents($tmpPdfPath);
		@unlink($tmpPdfPath);
		if($pdfBinary === false || $pdfBinary === ''){
			return $this->failPdfPreviewCli('Could not read generated PDF from disk.', 'node='.$node);
		}
		return $pdfBinary;
	}

	private function respondWithSpreadsheetFile($excelFileName, $excelFilePath){
		if($this->isPreviewRequest()){
			$previewSpreadsheet = IOFactory::load($excelFilePath);
			$htmlWriter = IOFactory::createWriter($previewSpreadsheet, 'Html');
			$sheetNames = $previewSpreadsheet->getSheetNames();
			$previewSheets = array();

			foreach($sheetNames as $sheetIndex => $sheetName){
				if(method_exists($htmlWriter, 'setSheetIndex')){
					$htmlWriter->setSheetIndex($sheetIndex);
				}

				ob_start();
				$htmlWriter->save('php://output');
				$previewSheets[] = array(
					'name' => $sheetName,
					'html' => ob_get_clean()
				);
			}
			delete_files($excelFilePath);

			$wantPdf = $this->getPreviewFormatFromRequest() === 'pdf';
			$pdfFallbackMessage = '';
			if($wantPdf){
				$this->config->load('pdf_preview');
				if($this->config->item('pdf_preview_enabled')){
					$mergedHtml = $this->mergePreviewSheetsHtmlForPdf($previewSheets);
					$pdfBinary = $this->runPuppeteerPdfCli($mergedHtml);
					if($pdfBinary !== false && $pdfBinary !== ''){
						$pdfFileName = preg_replace('/\.xlsx$/i', '.pdf', $excelFileName);
						if($pdfFileName === $excelFileName){
							$pdfFileName = $excelFileName.'.pdf';
						}
						return $this->output
							->set_content_type('application/json')
							->set_output(json_encode(array(
								'success' => true,
								'fileName' => $pdfFileName,
								'previewPdf' => base64_encode($pdfBinary),
								'previewFormat' => 'pdf'
							)));
					}
					$pdfFallbackMessage = 'PDF preview is unavailable; showing HTML preview.';
				}
			}

			$payload = array(
				'success' => true,
				'fileName' => $excelFileName,
				'previewSheets' => $previewSheets
			);
			if($pdfFallbackMessage !== ''){
				$payload['pdfFallbackMessage'] = $pdfFallbackMessage;
				$payload['pdfFailureTraceFiles'] = array(
					FCPATH.'temp'.DIRECTORY_SEPARATOR.'pdf_preview_last_error.txt',
					rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'jev_pdf_preview_last_error.txt',
				);
				if($this->pdfPreviewLastFailureSummary !== ''){
					$payload['pdfFailureSummary'] = $this->pdfPreviewLastFailureSummary;
				}
			}
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode($payload));
		}

		$fileContents = file_get_contents($excelFilePath);
		delete_files($excelFilePath);
		force_download($excelFileName, $fileContents);
	}

	private function getSelectedBrgyId(){
		if(isset($_SESSION['currbrgyid']) && $_SESSION['currbrgyid'] !== ''){
			return (int) $_SESSION['currbrgyid'];
		}

		$postBrgy = (int) $this->input->post('brgy');
		if($postBrgy > 0){
			return $postBrgy;
		}

		return 0;
	}

	private function hasSubsidiaryMaster(){
		return $this->db->table_exists($this->subsidiaryTable);
	}

	private function hasBankReconTables(){
		return $this->db->table_exists($this->bankAccountsTable) && $this->db->table_exists('tbl_bank_recon');
	}

	private function respondJson($payload){
		return $this->output
			->set_content_type('application/json')
			->set_output(json_encode($payload));
	}

	private function parseMoney($value){
		$value = trim((string)$value);
		if($value === '') return 0.0;
		$value = str_replace(array(',', ' '), '', $value);
		return (float)$value;
	}

	private function buildPeriodDates($year, $month){
		$year = (int)$year;
		$month = (int)$month;
		if($month < 1) $month = 1;
		if($month > 12) $month = 12;
		$start = sprintf('%04d-%02d-01', $year, $month);
		$end = date('Y-m-t', strtotime($start));
		return array($start, $end);
	}

	private function loadBrsFormConfig(){
		$this->config->load('brs_form', true);
		$cfg = $this->config->item('brs_form');
		return is_array($cfg) ? $cfg : array();
	}

	private function brsSumItemsByType($items){
		$typeSum = array();
		foreach($items as $it){
			$t = (string)$it['item_type'];
			if(!isset($typeSum[$t])) $typeSum[$t] = 0.0;
			$typeSum[$t] += (float)$it['amount'];
		}
		return $typeSum;
	}

	private function getBankAccount($bankAccountId, $brgyId){
		$this->db->where('bank_account_id', (int)$bankAccountId);
		$this->db->where('brgy_id', (int)$brgyId);
		return $this->db->get($this->bankAccountsTable)->row_array();
	}

	private function getOrCreateRecon($brgyId, $bankAccountId, $year, $month){
		$this->db->where('brgy_id', (int)$brgyId);
		$this->db->where('bank_account_id', (int)$bankAccountId);
		$this->db->where('period_year', (int)$year);
		$this->db->where('period_month', (int)$month);
		$row = $this->db->get('tbl_bank_recon')->row_array();
		if($row) return $row;

		$insert = array(
			'brgy_id' => (int)$brgyId,
			'bank_account_id' => (int)$bankAccountId,
			'period_year' => (int)$year,
			'period_month' => (int)$month,
			'statement_ending_balance' => 0,
			'book_ending_balance' => 0,
			'status' => 'draft'
		);
		$this->db->insert('tbl_bank_recon', $insert);
		$insert['recon_id'] = $this->db->insert_id();
		return $insert;
	}

	private function computeBookEndingBalance($brgyId, $bankAccountRow, $periodEnd){
		$cashCode = isset($bankAccountRow['cash_in_bank_acc_code']) && trim((string)$bankAccountRow['cash_in_bank_acc_code']) !== ''
			? trim((string)$bankAccountRow['cash_in_bank_acc_code'])
			: '10102020';

		$this->db->select('SUM(jd.debit) AS debit_sum, SUM(jd.credit) AS credit_sum', false);
		$this->db->from('tbl_jevdata jd');
		$this->db->join('tbl_jev j', 'jd.jev_id = j.jev_id AND jd.jev_no = j.jev_no');
		$this->db->where('j.brgy', (int)$brgyId);
		$this->db->where('j.jev_date <=', $periodEnd);
		$this->db->where('jd.acc_code', $cashCode);
		$this->db->group_start();
		$this->db->where('j.bank_account_id', (int)$bankAccountRow['bank_account_id']);
		$this->db->or_where('j.bank_account_id IS NULL', null, false);
		$this->db->group_end();
		$row = $this->db->get()->row_array();
		$debit = (float)($row['debit_sum'] ?? 0);
		$credit = (float)($row['credit_sum'] ?? 0);
		return $debit - $credit;
	}

	private function listBookLinesForPeriod($brgyId, $bankAccountRow, $periodStart, $periodEnd){
		$cashCode = isset($bankAccountRow['cash_in_bank_acc_code']) && trim((string)$bankAccountRow['cash_in_bank_acc_code']) !== ''
			? trim((string)$bankAccountRow['cash_in_bank_acc_code'])
			: '10102020';

		$this->db->select('j.jev_date, j.jev_no, j.type, j.payor_payee, jd.jevdata_id, jd.or_num, jd.dv_no, jd.check_no, jd.check_date, jd.bank_acct, jd.debit, jd.credit');
		$this->db->from('tbl_jevdata jd');
		$this->db->join('tbl_jev j', 'jd.jev_id = j.jev_id AND jd.jev_no = j.jev_no');
		$this->db->where('j.brgy', (int)$brgyId);
		$this->db->where('j.jev_date >=', $periodStart);
		$this->db->where('j.jev_date <=', $periodEnd);
		$this->db->where('jd.acc_code', $cashCode);
		$this->db->order_by('j.jev_date', 'ASC');
		$this->db->order_by('j.jev_no', 'ASC');
		$rows = $this->db->get()->result_array();

		return array_map(function($r){
			$ref = '';
			if(!empty($r['or_num'])) $ref = 'OR: '.$r['or_num'];
			if(!empty($r['dv_no'])) $ref = ($ref !== '' ? ' | ' : '').'DV: '.$r['dv_no'];
			if(!empty($r['check_no'])) $ref = ($ref !== '' ? ' | ' : '').'Chk: '.$r['check_no'];
			if(!empty($r['bank_acct'])) $ref = ($ref !== '' ? ' | ' : '').'Bank: '.$r['bank_acct'];
			$net = (float)$r['debit'] - (float)$r['credit'];
			return array(
				'jevdata_id' => (int)$r['jevdata_id'],
				'jev_date' => $r['jev_date'],
				'jev_no' => $r['jev_no'],
				'ref' => $ref,
				'book_net' => $net,
				'net' => $net
			);
		}, $rows);
	}

	public function getsubsidiaries(){
		if(!$this->hasSubsidiaryMaster()){
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode(array()));
		}

		$q = trim((string) $this->input->get('q'));
		$type = trim((string) $this->input->get('type'));
		$limit = (int) $this->input->get('limit');
		if($limit <= 0 || $limit > 200){
			$limit = 50;
		}

		$this->db->from($this->subsidiaryTable);
		$this->db->where('is_active', 1);
		if($type !== ''){
			$this->db->where('subsidiary_type', $type);
		}
		if($q !== ''){
			$this->db->like('name', $q);
		}
		$this->db->order_by('subsidiary_type', 'ASC');
		$this->db->order_by('name', 'ASC');
		$this->db->limit($limit);

		$rows = $this->db->get()->result_array();
		return $this->output
			->set_content_type('application/json')
			->set_output(json_encode($rows));
	}

	public function createsubsidiary(){
		if(!$this->hasSubsidiaryMaster()){
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'success' => false,
					'message' => 'Subsidiary master table is missing. Apply the migration first.'
				)));
		}

		$type = trim((string) $this->input->post('subsidiary_type'));
		$rawName = (string) $this->input->post('name');
		$name = preg_replace('/\s+/u', ' ', trim($rawName));
		$externalId = trim((string) $this->input->post('external_id'));
		$tin = trim((string) $this->input->post('tin'));

		if($type === '' || $name === ''){
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'success' => false,
					'message' => 'subsidiary_type and name are required.'
				)));
		}

		$normKey = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
		$table = $this->db->dbprefix($this->subsidiaryTable);
		$dup = $this->db->query(
			'SELECT subsidiary_id, name FROM `'.$table.'` WHERE subsidiary_type = ? AND LOWER(TRIM(REGEXP_REPLACE(TRIM(name), ?, ?))) = ? LIMIT 1',
			array($type, '[[:space:]]+', ' ', $normKey)
		)->row_array();

		if(!empty($dup['subsidiary_id'])){
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'success' => true,
					'subsidiary_id' => (int) $dup['subsidiary_id'],
					'duplicate_of_existing' => true,
					'canonical_name' => $dup['name']
				)));
		}

		$insert = array(
			'subsidiary_type' => $type,
			'name' => $name,
			'external_id' => ($externalId !== '' ? $externalId : null),
			'tin' => ($tin !== '' ? $tin : null),
			'is_active' => 1
		);

		$this->db->insert($this->subsidiaryTable, $insert);
		$id = $this->db->insert_id();

		return $this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'success' => $id > 0,
				'subsidiary_id' => $id,
				'duplicate_of_existing' => false
			)));
	}

	
	public function index()
	{
		$this->load->view('admin/login');
	}

	public function db_backup(){
		
        $this->load->helper('file');
        $this->load->helper('download');

		$this->load->dbutil();

        // Backup your entire database and assign it to a variable
        $backup = $this->dbutil->backup();

        // Specify the backup filename
        $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';

        // Specify the path to save the backup file
        $filePath = $excelFilePath = FCPATH . 'backups/'. $fileName;

        // Write the backup to a file
        write_file($filePath, $backup);

        // Force download the backup file
        force_download($fileName, $backup);
	}

	public function addlog($action,$user){
		$log_array = array(
			"action"=>$action,
			"user"=>$user,
			"date"=> date('Y-m-d')

		);
		$res = $this->db->insert('tbl_logs',$log_array);
	}

	public function jev_page(){
		$data['alink'] = "JEV";
		$data['title'] = "JEV";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/jev');
		$this->load->view('templates/footer.php');
	}

	public function bb_page(){
		$data['alink'] = "BB";
		$data['title'] = "Set Beginning Balance";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/beginning_balance');
		$this->load->view('templates/footer.php');
	}

	public function vbb_page(){
		$data['alink'] = "VBB";
		$data['title'] = "View Beginning Balance";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/viewbb');
		$this->load->view('templates/footer.php');
	}

	public function viewbb_page($id,$year){
		$this->db->where('brgy_id',$id);
		$this->db->where('year',$year);
		$this->db->order_by('bal_id','ASC');
		$res = $this->db->get('tbl_begbal');


		$data['alink'] = "VBB";
		$data['title'] = "View Beginning Balance";
		$data['bal'] = $res->result_array();

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/viewbbpage',$data);
		$this->load->view('templates/footer.php');
	}

	public function fs_page(){
		$data['alink'] = "FS";
		$data['title'] = "FS";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/fs');
		$this->load->view('templates/footer.php');
	}

	public function jevlist_page(){
		$data['alink'] = "JEVLIST";
		$data['title'] = "JEV LIST";

		$this->db->select_sum('jd.credit', 'total_credit');
		$this->db->from('tbl_jev j');
		$this->db->join('tbl_jevdata jd', 'j.jev_no = jd.jev_no AND j.jev_id = jd.jev_id', 'left');
		$this->db->where('j.brgy', $_SESSION['currbrgyid']);

		$query = $this->db->get();
		
		$result = $query->row_array();

		$this->db->select_sum('jd.debit', 'total_debit');
		$this->db->from('tbl_jev j');
		$this->db->join('tbl_jevdata jd', 'j.jev_no = jd.jev_no AND j.jev_id = jd.jev_id', 'left');
		$this->db->where('j.brgy', $_SESSION['currbrgyid']);

		$query2 = $this->db->get();
		
		$result2 = $query2->row_array();

		$this->db->select('COUNT(*) as total_jev');
		$this->db->from('tbl_jev');
		$this->db->where('brgy', $_SESSION['currbrgyid']);

		$query3 = $this->db->get();
		$result3 = $query3->row_array();

		$data['total_jev'] = $result3['total_jev'];

		$data['total_debit'] = $result2['total_debit'];
		$data['total_credit'] = $result['total_credit'];

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/jev_list',$data);
		$this->load->view('templates/footer.php');
	}

	
	public function logs(){
		$data['alink'] = "LOGS";
		$data['title'] = "System Logs";

		$this->db->order_by('log_id','DESC');
		$res = $this->db->get('tbl_logs');

		$data['log_count'] = $res->num_rows();
		$data['logs'] = $res->result_array();
		

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/logs',$data);
		$this->load->view('templates/footer.php');
	}

	public function tb_page(){
		$data['alink'] = "TB";
		$data['title'] = "Trial Balance";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/tb');
		$this->load->view('templates/footer.php');
	}

	
	public function gl_page(){
		$data['alink'] = "GL";
		$data['title'] = "Ledgers";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/gl');
		$this->load->view('templates/footer.php');
	}

	public function journals_page(){
		$data['alink'] = "JL";
		$data['title'] = "Journals";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/journals');
		$this->load->view('templates/footer.php');
	}

	
	public function rrr_page(){
		$data['alink'] = "RRR";
		$data['title'] = "Report on Revenue and Receipts";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/rrr');
		$this->load->view('templates/footer.php');
	}

	
	public function sba_page(){
		$data['alink'] = "SBA";
		$data['title'] = "Comaprison of Budget and Actual Amounts";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/sba');
		$this->load->view('templates/footer.php');
	}

	
	public function aging_page(){
		$data['alink'] = "AGING";
		$data['title'] = "Aging Schedule of Unliquidated Cash Advances";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/aging');
		$this->load->view('templates/footer.php');
	}


	public function dashboard(){

		$data['alink'] = "Dashboard";
		$data['title'] = "Dashboard";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/dashboard');
		$this->load->view('templates/footer.php');
	}
	
	public function logout(){
		$this->addlog("User Logged Out",$_SESSION['fname'].' '.$_SESSION['lname']);
		session_destroy();
		redirect('/');
		
	}


	public function userlogin(){
		$this->db->where('uname',$this->input->post('uname'));
		$this->db->where('pass',$this->input->post('pass'));

		$res = $this->db->get('tbl_users');

		if($res->num_rows() == 1){
			$uid = $res->row_array()['user_id'];
			$this->db->where('user_id', $uid);
			$this->db->order_by('brgy_id', 'asc');
			$res2 = $this->db->get('tbl_brgys');

			$data = array(
				'uname'  => $res->row_array()['uname'],
				'fname' => $res->row_array()['fname'],
				'lname' =>$res->row_array()['lname'],
				'position'=>$res->row_array()['position'],
				'currbrgyid'=>$res2->result_array()[0]['brgy_id'],
				'currbrgy'=>$res2->result_array()[0]['name'],
				'brgys' => $res2->result_array(),
				'logged_in' => TRUE
			);

			$this->session->set_userdata($data);
			$this->addlog("User logged In",$res->row_array()['fname'].' '.$res->row_array()['lname']);
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode(true));

		}else{
			
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode(false));
		}
	}


	public function changecurrbrgy(){
		$this->session->set_userdata('currbrgyid',$this->input->post('brgy_id'));
		$this->session->set_userdata('currbrgy',$this->input->post('brgy'));
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode(true));
	}

	public function getaccounts(){
		$this->db->order_by('code','asc');
		if($this->db->field_exists('is_active', 'tbl_accounts')){
			$this->db->where('is_active', 1);
		}
		$res = $this->db->get('tbl_accounts');
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res->result_array()));
	}

	public function chart_of_accounts_page(){
		$data['alink'] = 'COA';
		$data['title'] = 'Chart of Accounts';
		// Merge into main config (do not use use_sections=TRUE — that nests under 'coa_accounts' and breaks item() lookups)
		$this->load->config('coa_accounts', false);
		$data['coa_classes'] = $this->config->item('coa_account_classes');
		if(!is_array($data['coa_classes'])){
			$data['coa_classes'] = array();
		}
		$data['coa_code_hint'] = $this->config->item('coa_code_hint');
		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/chart_of_accounts',$data);
		$this->load->view('templates/footer.php',$data);
	}

	public function api_coa_accounts(){
		if(!$this->db->table_exists('tbl_accounts')){
			return $this->respondJson(array('success'=>false,'message'=>'tbl_accounts missing.'));
		}
		$this->db->order_by('code','asc');
		$rows = $this->db->get('tbl_accounts')->result_array();
		return $this->respondJson(array('success'=>true,'accounts'=>$rows));
	}

	public function api_coa_account_save(){
		if(!$this->db->table_exists('tbl_accounts')){
			return $this->respondJson(array('success'=>false,'message'=>'tbl_accounts missing.'));
		}
		$id = (int)$this->input->post('account_id');
		$code = preg_replace('/\s+/','',trim((string)$this->input->post('code')));
		$name = trim((string)$this->input->post('name'));
		$accountClass = trim((string)$this->input->post('account_class'));
		$notes = trim((string)$this->input->post('notes'));
		$isActive = (int)$this->input->post('is_active') ? 1 : 0;

		if($code === '' || $name === ''){
			return $this->respondJson(array('success'=>false,'message'=>'Account code and title are required.'));
		}
		if(strlen($code) > 40){
			return $this->respondJson(array('success'=>false,'message'=>'Account code is too long.'));
		}

		$this->db->where('code', $code);
		if($id > 0){
			$this->db->where('account_id !=', $id);
		}
		$dup = $this->db->count_all_results('tbl_accounts');
		if($dup > 0){
			return $this->respondJson(array('success'=>false,'message'=>'That account code already exists.'));
		}

		$data = array(
			'code' => $code,
			'name' => $name,
		);
		if($this->db->field_exists('account_class', 'tbl_accounts')){
			$data['account_class'] = $accountClass !== '' ? $accountClass : null;
		}
		if($this->db->field_exists('notes', 'tbl_accounts')){
			$data['notes'] = $notes !== '' ? $notes : null;
		}
		if($this->db->field_exists('is_active', 'tbl_accounts')){
			$data['is_active'] = $isActive;
		}

		if($id > 0){
			$this->db->where('account_id', $id);
			$this->db->update('tbl_accounts', $data);
			return $this->respondJson(array('success'=>true));
		}
		$this->db->insert('tbl_accounts', $data);
		return $this->respondJson(array('success'=>true,'account_id'=>$this->db->insert_id()));
	}

	public function api_coa_account_delete(){
		if(!$this->db->table_exists('tbl_accounts')){
			return $this->respondJson(array('success'=>false,'message'=>'tbl_accounts missing.'));
		}
		$id = (int)$this->input->post('account_id');
		$row = $this->db->where('account_id', $id)->get('tbl_accounts')->row_array();
		if(!$row){
			return $this->respondJson(array('success'=>false,'message'=>'Account not found.'));
		}
		$code = trim((string)$row['code']);
		$this->db->where('acc_code', $code);
		$n = $this->db->count_all_results('tbl_jevdata');
		if($n > 0){
			return $this->respondJson(array(
				'success'=>false,
				'message'=>'This account is used on '.$n.' JEV line(s). Deactivate it instead of deleting.',
			));
		}
		$this->db->where('account_id', $id)->delete('tbl_accounts');
		return $this->respondJson(array('success'=>true));
	}

	public function getjevlist($id){
		$this->db->where('brgy',$id);
		$this->db->order_by('jev_no','ASC');
		$res = $this->db->get('tbl_jev');
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res->result_array()));
	}

	
	public function getbbs($id){
		$this->db->select('year, SUM(credit) as total_credit, SUM(debit) as total_debit');
		$this->db->where('brgy_id',$id);
	
		$this->db->order_by('year','ASC');
		$this->db->group_by('year');
		$res = $this->db->get('tbl_begbal');
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res->result_array()));
	}

	public function getbbs2($id,$year){
		$this->db->select('year, SUM(credit) as total_credit, SUM(debit) as total_debit');
		$this->db->where('brgy_id',$id);
		$this->db->where('year',$year);
		$this->db->order_by('year','ASC');
		$this->db->group_by('year');
		$res = $this->db->get('tbl_begbal');
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res->result_array()));
	}

	public function getbegbalYear($id,$year){
		$this->db->select('
    SUM(CASE WHEN acc_code = "10102020" THEN debit ELSE 0 END) AS total_debit_10102020,
    SUM(CASE WHEN acc_code = "10101010" THEN credit ELSE 0 END) AS total_credit_10101010
');
$this->db->where('brgy_id', $id);
$this->db->where('year', $year);
$res = $this->db->get('tbl_begbal');

$result = $res->result_array();

if (!empty($result)) {
    $row = $result[0];
    // Calculate the balance difference
    $debit_balance = $row['total_debit_10102020'];
    $credit_balance = $row['total_credit_10101010'];
    
    // The result will be the difference between credit and debit
    $balance_diff =   $debit_balance - $credit_balance;

    return $balance_diff;
} else {
    return 0; // Return 0 if no result found
}


	}

	public function getbegbalCashYear($id, $year){
		$this->db->select('
			SUM(CASE WHEN acc_code = "10101010" THEN debit ELSE 0 END) AS total_debit_10101010,
			SUM(CASE WHEN acc_code = "10101010" THEN credit ELSE 0 END) AS total_credit_10101010,
			SUM(CASE WHEN acc_code = "10102020" THEN debit ELSE 0 END) AS total_debit_10102020,
			SUM(CASE WHEN acc_code = "10102020" THEN credit ELSE 0 END) AS total_credit_10102020
		');
		$this->db->where('brgy_id', $id);
		$this->db->where('year', $year);
		$res = $this->db->get('tbl_begbal');

		$result = $res->result_array();

		if (!empty($result)) {
			$row = $result[0];

			$bal_10101010 = (float) $row['total_debit_10101010'] - (float) $row['total_credit_10101010'];
			$bal_10102020 = (float) $row['total_debit_10102020'] - (float) $row['total_credit_10102020'];

			return $bal_10101010 + $bal_10102020;
		} else {
			return 0;
		}
	}

	
	public function getjevlist2(){
		

		$month = $this->input->post('month');
		$year = $this->input->post('year');
		$sdate = '';
		$edate ='';
		if($month == 13){
		$sdate = $year.'-01-01';
		$edate = $year.'-12-31';
		}else{
		$sdate = $year.'-'.$month.'-01';
		$edate = $year.'-'.$month.'-31';
		}
		// $edate = date('Y-m-d',strtotime($year.'-'.$month.'-31'));



		$this->db->where('brgy',$_SESSION['currbrgyid']);
		$this->db->where('jev_date >=',$sdate);
		$this->db->where('jev_date <=',$edate);
		$this->db->order_by('jev_no','ASC');
		$res = $this->db->get('tbl_jev');

		$this->db->select_sum('jd.credit', 'total_credit');
		$this->db->from('tbl_jev j');
		$this->db->join('tbl_jevdata jd', 'j.jev_no = jd.jev_no AND j.jev_id = jd.jev_id', 'left');
		$this->db->where('jev_date >=',$sdate);
		$this->db->where('jev_date <=',$edate);
		$this->db->where('j.brgy', $_SESSION['currbrgyid']);

		$query = $this->db->get();
		
		$result = $query->row_array();

		$this->db->select_sum('jd.debit', 'total_debit');
		$this->db->from('tbl_jev j');
		$this->db->join('tbl_jevdata jd', 'j.jev_no = jd.jev_no AND j.jev_id = jd.jev_id', 'left');
		$this->db->where('jev_date >=',$sdate);
		$this->db->where('jev_date <=',$edate);
		$this->db->where('j.brgy', $_SESSION['currbrgyid']);

		$query2 = $this->db->get();
		
		$result2 = $query2->row_array();

		$this->db->select('COUNT(*) as total_jev');
		$this->db->from('tbl_jev');
		$this->db->where('jev_date >=',$sdate);
		$this->db->where('jev_date <=',$edate);
		$this->db->where('brgy', $_SESSION['currbrgyid']);

		$query3 = $this->db->get();
		$result3 = $query3->row_array();

		$data['total_jev'] = $result3['total_jev'];

		$data['total_debit'] = $result2['total_debit'];
		$data['total_credit'] = $result['total_credit'];
		$data['jevs'] = $res->result_array();



		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($data));
	}


	public function viewjev_page($id){
		$data['alink'] = "VJEV";
		$data['title'] = "View Jev";

		$this->db->where('jev_id',$id);
		$res = $this->db->get('tbl_jev');
		$data['jev'] = $res->row_array();
		$jevRow = $data['jev'];

		if($this->hasSubsidiaryMaster()){
			$this->db->select('jd.*, s.name AS subsidiary_name');
			$this->db->from('tbl_jevdata jd');
			$this->db->join($this->subsidiaryTable.' s', 'jd.subsidiary_id = s.subsidiary_id', 'left');
			$this->db->where('jd.jev_no', $jevRow['jev_no']);
			$this->db->where('jd.jev_id', $jevRow['jev_id']);
			$this->db->order_by('jd.acc_code', 'ASC');
			$data['jd'] = $this->db->get()->result_array();
		}else{
			$this->db->where('jev_no', $jevRow['jev_no']);
			$this->db->where('jev_id', $jevRow['jev_id']);
			$this->db->order_by('acc_code','ASC');
			$data['jd'] = $this->db->get('tbl_jevdata')->result_array();
		}

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/viewjev',$data);
		$this->load->view('templates/footer.php',$data);

	}




	//pages

	public function incident_report_page(){

		$data['alink'] = "IReport";
		$data['title'] = "Incident Report";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/incident_report');
		$this->load->view('templates/footer.php');
	}
	
	public function incident_list_page(){

		$data['alink'] = "IList";
		$data['title'] = "Incident List";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/incident_list');
		$this->load->view('templates/footer.php');
	}

	
	public function teams_page(){
		$this->db->where('has_team',0);
		$data['leader'] = $this->db->get('tbl_respondents')->result_array();

		$data['alink'] = "Teams";
		$data['title'] = "Teams List";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/teams',$data);
		$this->load->view('templates/footer.php');
	}


	
	public function respondents_page(){

		$data['alink'] = "Respondents";
		$data['title'] = "Manage Respondets";

		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/respondents');
		$this->load->view('templates/footer.php');
	}

	// ----------------------------
	// Bank reconciliation pages
	// ----------------------------

	public function bank_accounts_page(){
		$data['alink'] = "BANKACCTS";
		$data['title'] = "Bank Accounts";
		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/bank_accounts',$data);
		$this->load->view('templates/footer.php');
	}

	public function bank_recon_page(){
		$data['alink'] = "BANKRECON";
		$data['title'] = "Bank Reconciliation";
		$this->load->view('templates/header.php',$data);
		$this->load->view('templates/sidebar.php',$data);
		$this->load->view('admin/bank_recon',$data);
		$this->load->view('templates/footer.php');
	}

	// ----------------------------
	// Bank reconciliation APIs
	// ----------------------------

	public function api_bank_accounts(){
		$brgyId = $this->getSelectedBrgyId();
		if(!$this->db->table_exists($this->bankAccountsTable)){
			return $this->respondJson(array());
		}
		$this->db->where('brgy_id', $brgyId);
		$this->db->order_by('is_active','DESC');
		$this->db->order_by('bank_name','ASC');
		$rows = $this->db->get($this->bankAccountsTable)->result_array();
		return $this->respondJson($rows);
	}

	public function api_bank_account_save(){
		$brgyId = $this->getSelectedBrgyId();
		if(!$this->db->table_exists($this->bankAccountsTable)){
			return $this->respondJson(array('success'=>false,'message'=>'Apply bank_recon_migration.sql first.'));
		}
		$id = (int)$this->input->post('bank_account_id');
		$bankName = trim((string)$this->input->post('bank_name'));
		$branch = trim((string)$this->input->post('branch'));
		$accountNo = trim((string)$this->input->post('account_no'));
		$accountName = trim((string)$this->input->post('account_name'));
		$cashCode = trim((string)$this->input->post('cash_in_bank_acc_code'));
		if($cashCode === '') $cashCode = '10102020';
		if($bankName === '' || $accountNo === '' || $accountName === ''){
			return $this->respondJson(array('success'=>false,'message'=>'Bank name, account no, and account name are required.'));
		}
		$data = array(
			'brgy_id' => $brgyId,
			'bank_name' => $bankName,
			'branch' => $branch,
			'account_no' => $accountNo,
			'account_name' => $accountName,
			'cash_in_bank_acc_code' => $cashCode,
		);
		if($id > 0){
			$this->db->where('bank_account_id', $id);
			$this->db->where('brgy_id', $brgyId);
			$this->db->update($this->bankAccountsTable, $data);
			return $this->respondJson(array('success'=>true));
		}
		$data['is_active'] = 1;
		$this->db->insert($this->bankAccountsTable, $data);
		return $this->respondJson(array('success'=>true,'bank_account_id'=>$this->db->insert_id()));
	}

	public function api_bank_account_toggle(){
		$brgyId = $this->getSelectedBrgyId();
		$id = (int)$this->input->post('bank_account_id');
		$isActive = (int)$this->input->post('is_active') ? 1 : 0;
		$this->db->where('bank_account_id', $id);
		$this->db->where('brgy_id', $brgyId);
		$this->db->update($this->bankAccountsTable, array('is_active'=>$isActive));
		return $this->respondJson(array('success'=>true));
	}

	public function api_bank_recon_upsert(){
		$brgyId = $this->getSelectedBrgyId();
		if(!$this->hasBankReconTables()){
			return $this->respondJson(array('success'=>false,'message'=>'Apply bank_recon_migration.sql first.'));
		}
		$bankAccountId = (int)$this->input->post('bank_account_id');
		$year = (int)$this->input->post('year');
		$month = (int)$this->input->post('month');
		$statementEnding = $this->parseMoney($this->input->post('statement_ending_balance'));
		$bank = $this->getBankAccount($bankAccountId, $brgyId);
		if(!$bank){
			return $this->respondJson(array('success'=>false,'message'=>'Invalid bank account.'));
		}
		$recon = $this->getOrCreateRecon($brgyId, $bankAccountId, $year, $month);
		list($_ps, $_pe) = $this->buildPeriodDates($year, $month);
		$bookEnding = $this->computeBookEndingBalance($brgyId, $bank, $_pe);
		$stmtAsOfIn = trim((string)$this->input->post('statement_as_of_date'));
		$explanatory = (string)$this->input->post('explanatory_comment');
		$stmtAsOfDate = $_pe;
		if($stmtAsOfIn !== ''){
			$ts = strtotime($stmtAsOfIn);
			if($ts !== false){
				$stmtAsOfDate = date('Y-m-d', $ts);
			}
		}
		$this->db->where('recon_id', (int)$recon['recon_id']);
		$this->db->update('tbl_bank_recon', array(
			'statement_ending_balance' => $statementEnding,
			'book_ending_balance' => $bookEnding,
			'date_updated' => date('Y-m-d H:i:s'),
			'statement_as_of_date' => $stmtAsOfDate,
			'explanatory_comment' => $explanatory,
		));
		return $this->respondJson(array(
			'success' => true,
			'recon_id' => (int)$recon['recon_id'],
			'statement_ending_balance' => (string)$statementEnding,
			'book_ending_balance' => (string)$bookEnding,
			'statement_as_of_date' => $stmtAsOfDate,
			'explanatory_comment' => $explanatory,
		));
	}

	public function api_bank_recon_get(){
		$brgyId = $this->getSelectedBrgyId();
		if(!$this->hasBankReconTables()){
			return $this->respondJson(array('success'=>false,'message'=>'Apply bank_recon_migration.sql first.'));
		}
		$bankAccountId = (int)$this->input->get('bank_account_id');
		$year = (int)$this->input->get('year');
		$month = (int)$this->input->get('month');
		$bank = $this->getBankAccount($bankAccountId, $brgyId);
		if(!$bank){
			return $this->respondJson(array('success'=>false,'message'=>'Invalid bank account.'));
		}
		list($_ps, $_pe) = $this->buildPeriodDates($year, $month);
		$bookEndingFresh = $this->computeBookEndingBalance($brgyId, $bank, $_pe);
		$this->db->where('brgy_id', (int)$brgyId);
		$this->db->where('bank_account_id', (int)$bankAccountId);
		$this->db->where('period_year', (int)$year);
		$this->db->where('period_month', (int)$month);
		$recon = $this->db->get('tbl_bank_recon')->row_array();
		if(!$recon){
			return $this->respondJson(array(
				'success' => true,
				'recon_id' => null,
				'statement_ending_balance' => '',
				'book_ending_balance' => (string)$bookEndingFresh,
				'statement_as_of_date' => $_pe,
				'explanatory_comment' => '',
			));
		}
		return $this->respondJson(array(
			'success' => true,
			'recon_id' => (int)$recon['recon_id'],
			'statement_ending_balance' => (string)$recon['statement_ending_balance'],
			'book_ending_balance' => (string)$bookEndingFresh,
			'statement_as_of_date' => !empty($recon['statement_as_of_date']) ? $recon['statement_as_of_date'] : $_pe,
			'explanatory_comment' => isset($recon['explanatory_comment']) ? (string)$recon['explanatory_comment'] : '',
		));
	}

	public function api_bank_recon_lines(){
		$reconId = (int)$this->input->get('recon_id');
		$this->db->where('recon_id', $reconId);
		$lines = $this->db->get('tbl_bank_statement_lines')->result_array();
		// matches with joined info
		$sql = "SELECT m.match_id, m.matched_amount,
			sl.txn_date, sl.description, sl.amount AS stmt_amount,
			j.jev_date, j.jev_no,
			(jd.debit - jd.credit) AS book_net,
			CONCAT(IFNULL(jd.or_num,''), IF(jd.dv_no IS NULL OR jd.dv_no='', '', CONCAT(' DV:', jd.dv_no)), IF(jd.check_no IS NULL OR jd.check_no='', '', CONCAT(' Chk:', jd.check_no))) AS ref
		FROM tbl_bank_recon_matches m
		INNER JOIN tbl_bank_statement_lines sl ON sl.statement_line_id = m.statement_line_id
		INNER JOIN tbl_jevdata jd ON jd.jevdata_id = m.jevdata_id
		INNER JOIN tbl_jev j ON j.jev_id = jd.jev_id AND j.jev_no = jd.jev_no
		WHERE m.recon_id = ?";
		$matches = $this->db->query($sql, array($reconId))->result_array();
		return $this->respondJson(array('success'=>true,'lines'=>$lines,'matches'=>$matches));
	}

	public function api_bank_recon_line_add(){
		$reconId = (int)$this->input->post('recon_id');
		$txnDate = (string)$this->input->post('txn_date');
		$desc = trim((string)$this->input->post('description'));
		$ref = trim((string)$this->input->post('reference'));
		$amt = $this->parseMoney($this->input->post('amount'));
		if($txnDate === '' || $desc === ''){
			return $this->respondJson(array('success'=>false,'message'=>'Date and description required.'));
		}
		$this->db->insert('tbl_bank_statement_lines', array(
			'recon_id' => $reconId,
			'txn_date' => $txnDate,
			'description' => $desc,
			'reference' => $ref,
			'amount' => $amt
		));
		return $this->respondJson(array('success'=>true));
	}

	public function api_bank_recon_line_delete(){
		$id = (int)$this->input->post('statement_line_id');
		$this->db->where('statement_line_id', $id)->delete('tbl_bank_statement_lines');
		$this->db->where('statement_line_id', $id)->delete('tbl_bank_recon_matches');
		return $this->respondJson(array('success'=>true));
	}

	public function api_bank_recon_items(){
		$reconId = (int)$this->input->get('recon_id');
		$this->db->where('recon_id', $reconId);
		$this->db->order_by('recon_item_id','DESC');
		$items = $this->db->get('tbl_bank_recon_items')->result_array();
		return $this->respondJson(array('success'=>true,'items'=>$items));
	}

	public function api_bank_recon_item_add(){
		$reconId = (int)$this->input->post('recon_id');
		$type = trim((string)$this->input->post('item_type'));
		$amt = $this->parseMoney($this->input->post('amount'));
		$ref = trim((string)$this->input->post('reference'));
		$notes = trim((string)$this->input->post('notes'));
		if($type === ''){
			return $this->respondJson(array('success'=>false,'message'=>'Item type required.'));
		}
		$this->db->insert('tbl_bank_recon_items', array(
			'recon_id'=>$reconId,
			'item_type'=>$type,
			'amount'=>$amt,
			'reference'=>$ref,
			'notes'=>$notes
		));
		return $this->respondJson(array('success'=>true));
	}

	public function api_bank_recon_item_delete(){
		$id = (int)$this->input->post('recon_item_id');
		$this->db->where('recon_item_id', $id)->delete('tbl_bank_recon_items');
		return $this->respondJson(array('success'=>true));
	}

	public function api_bank_recon_item_create_jev(){
		$brgyId = $this->getSelectedBrgyId();
		$itemId = (int)$this->input->post('recon_item_id');
		$item = $this->db->where('recon_item_id', $itemId)->get('tbl_bank_recon_items')->row_array();
		if(!$item){
			return $this->respondJson(array('success'=>false,'message'=>'Recon item not found.'));
		}
		if(!empty($item['linked_jev_id'])){
			return $this->respondJson(array('success'=>false,'message'=>'Item already linked to a JEV.'));
		}

		$recon = $this->db->where('recon_id', (int)$item['recon_id'])->get('tbl_bank_recon')->row_array();
		if(!$recon){
			return $this->respondJson(array('success'=>false,'message'=>'Recon header not found.'));
		}
		$bank = $this->getBankAccount($recon['bank_account_id'], $brgyId);
		if(!$bank){
			return $this->respondJson(array('success'=>false,'message'=>'Bank account not found.'));
		}
		$cashCode = trim((string)($bank['cash_in_bank_acc_code'] ?? '10102020'));
		if($cashCode === '') $cashCode = '10102020';

		// Build a simple 2-line GJ entry. This is a DRAFT helper; accountant can adjust titles/codes.
		$amount = (float)$item['amount'];
		if($amount == 0){
			return $this->respondJson(array('success'=>false,'message'=>'Amount must be non-zero.'));
		}
		$absAmt = abs($amount);

		// Derive date from period end
		list($_ps, $_pe) = $this->buildPeriodDates($recon['period_year'], $recon['period_month']);

		$jevNo = 'BRS-' . date('YmdHis');
		$jevData = array(
			'jev_no' => $jevNo,
			'jev_date' => $_pe,
			'fund' => '',
			'bank_account_id' => (int)$bank['bank_account_id'],
			'payor_payee' => 'BANK',
			'particulars' => 'Bank reconciliation adjustment: '.$item['item_type'].' '.$this->journalCoalesceField($item['reference'], ''),
			'resp_center' => '',
			'type' => 'GJ',
			'brgy' => $brgyId,
			'status' => 0
		);

		$this->db->trans_begin();
		$this->db->insert('tbl_jev', $jevData);
		$jevId = $this->db->insert_id();

		// Very simple placeholder COA codes for expenses/income; user can edit later.
		$counterAccCode = '99999999';
		$counterTitle = '';
		$cashTitle = 'Cash in Bank';
		$cashDebit = 0; $cashCredit = 0;
		$ctrDebit = 0; $ctrCredit = 0;

		if($item['item_type'] === 'bank_charge' || $item['item_type'] === 'bank_debit_memo'){
			// Bank charges / debit memo reduce cash
			$cashCredit = $absAmt;
			$ctrDebit = $absAmt;
			$counterTitle = 'Bank Charges / Adjustment';
		}elseif($item['item_type'] === 'interest_income' || $item['item_type'] === 'bank_credit_memo'){
			// Interest / credit memo increase cash
			$cashDebit = $absAmt;
			$ctrCredit = $absAmt;
			$counterTitle = 'Interest Income / Adjustment';
		}else{
			$this->db->trans_rollback();
			return $this->respondJson(array('success'=>false,'message'=>'Item type not supported for auto JEV.'));
		}

		$this->db->insert('tbl_jevdata', array(
			'jev_id' => $jevId,
			'jev_no' => $jevNo,
			'acc_title' => $cashTitle,
			'acc_code' => $cashCode,
			'debit' => $cashDebit,
			'credit' => $cashCredit,
			'bank_acct' => $bank['account_no'],
			'bank_account_id' => (int)$bank['bank_account_id'],
		));
		$this->db->insert('tbl_jevdata', array(
			'jev_id' => $jevId,
			'jev_no' => $jevNo,
			'acc_title' => $counterTitle,
			'acc_code' => $counterAccCode,
			'debit' => $ctrDebit,
			'credit' => $ctrCredit,
			'bank_acct' => $bank['account_no'],
			'bank_account_id' => (int)$bank['bank_account_id'],
		));

		$this->db->where('recon_item_id', $itemId);
		$this->db->update('tbl_bank_recon_items', array('linked_jev_id'=>$jevId));

		if($this->db->trans_status() === FALSE){
			$this->db->trans_rollback();
			return $this->respondJson(array('success'=>false,'message'=>'Failed creating JEV.'));
		}
		$this->db->trans_commit();
		return $this->respondJson(array('success'=>true,'jev_id'=>$jevId,'jev_no'=>$jevNo));
	}

	public function api_bank_recon_book_lines(){
		$brgyId = $this->getSelectedBrgyId();
		$reconId = (int)$this->input->get('recon_id');
		$recon = $this->db->where('recon_id', $reconId)->get('tbl_bank_recon')->row_array();
		if(!$recon){
			return $this->respondJson(array('success'=>false,'message'=>'Recon not found.'));
		}
		$bank = $this->getBankAccount($recon['bank_account_id'], $brgyId);
		list($ps, $pe) = $this->buildPeriodDates($recon['period_year'], $recon['period_month']);
		$lines = $this->listBookLinesForPeriod($brgyId, $bank, $ps, $pe);
		$bookEnding = $this->computeBookEndingBalance($brgyId, $bank, $pe);
		return $this->respondJson(array('success'=>true,'lines'=>$lines,'book_ending_balance'=>$bookEnding));
	}

	public function api_bank_recon_suggest(){
		$brgyId = $this->getSelectedBrgyId();
		$reconId = (int)$this->input->get('recon_id');
		$stmtLineId = (int)$this->input->get('statement_line_id');
		$recon = $this->db->where('recon_id', $reconId)->get('tbl_bank_recon')->row_array();
		$stmt = $this->db->where('statement_line_id', $stmtLineId)->get('tbl_bank_statement_lines')->row_array();
		if(!$recon || !$stmt){
			return $this->respondJson(array('success'=>false,'message'=>'Missing recon or statement line.'));
		}
		$bank = $this->getBankAccount($recon['bank_account_id'], $brgyId);
		list($ps, $pe) = $this->buildPeriodDates($recon['period_year'], $recon['period_month']);
		$bookLines = $this->listBookLinesForPeriod($brgyId, $bank, $ps, $pe);

		$targetAmt = abs((float)$stmt['amount']);
		$targetDate = strtotime($stmt['txn_date']);
		$ref = strtolower(trim((string)($stmt['reference'] ?? '')));
		$desc = strtolower(trim((string)($stmt['description'] ?? '')));

		$suggestions = array();
		foreach($bookLines as $b){
			$bookAmt = abs((float)$b['book_net']);
			if($bookAmt == 0) continue;
			if(abs($bookAmt - $targetAmt) > 0.009) continue;
			$bd = strtotime($b['jev_date']);
			$dayDiff = abs(($bd - $targetDate) / 86400);
			if($dayDiff > 7) continue;
			$score = 10 - $dayDiff;
			$refHit = 0;
			if($ref !== '' && strpos(strtolower($b['ref']), $ref) !== false) $refHit = 5;
			if($desc !== '' && strpos(strtolower($b['ref']), $desc) !== false) $refHit = max($refHit, 2);
			$score += $refHit;
			$suggestions[] = array(
				'jevdata_id' => $b['jevdata_id'],
				'jev_date' => $b['jev_date'],
				'jev_no' => $b['jev_no'],
				'ref' => $b['ref'],
				'book_net' => $b['book_net'],
				'matched_amount' => $targetAmt,
				'score' => $score
			);
		}
		usort($suggestions, function($a,$b){ return $b['score'] <=> $a['score']; });
		$suggestions = array_slice($suggestions, 0, 10);
		return $this->respondJson(array('success'=>true,'suggestions'=>$suggestions));
	}

	public function api_bank_recon_match_add(){
		$reconId = (int)$this->input->post('recon_id');
		$stmt = (int)$this->input->post('statement_line_id');
		$jevdataId = (int)$this->input->post('jevdata_id');
		$amt = $this->parseMoney($this->input->post('matched_amount'));
		$this->db->insert('tbl_bank_recon_matches', array(
			'recon_id'=>$reconId,
			'statement_line_id'=>$stmt,
			'jevdata_id'=>$jevdataId,
			'matched_amount'=>$amt
		));
		return $this->respondJson(array('success'=>true));
	}

	public function api_bank_recon_match_delete(){
		$id = (int)$this->input->post('match_id');
		$this->db->where('match_id', $id)->delete('tbl_bank_recon_matches');
		return $this->respondJson(array('success'=>true));
	}

	// ----------------------------
	// BRS generation (xlsx + preview)
	// ----------------------------

	private function buildBRSWorkbook($reconId){
		$brgyId = $this->getSelectedBrgyId();
		$recon = $this->db->where('recon_id', (int)$reconId)->get('tbl_bank_recon')->row_array();
		if(!$recon){
			return array(null, 'Recon not found.');
		}
		$bank = $this->getBankAccount($recon['bank_account_id'], $brgyId);
		if(!$bank){
			return array(null, 'Bank account not found.');
		}
		list($ps, $pe) = $this->buildPeriodDates($recon['period_year'], $recon['period_month']);

		$stmtEnding = (float)$recon['statement_ending_balance'];
		$bookEnding = (float)$recon['book_ending_balance'];

		$this->db->where('recon_id', (int)$reconId);
		$stmtLines = $this->db->get('tbl_bank_statement_lines')->result_array();
		$this->db->where('recon_id', (int)$reconId);
		$items = $this->db->get('tbl_bank_recon_items')->result_array();

		$typeSum = $this->brsSumItemsByType($items);
		$depositsInTransit = (float)($typeSum['deposit_in_transit'] ?? 0);
		$outstandingChecks = (float)($typeSum['outstanding_check'] ?? 0);
		$bankErrors = (float)($typeSum['bank_error'] ?? 0);
		$bookErrors = (float)($typeSum['book_error'] ?? 0);
		$bankCharges = (float)($typeSum['bank_charge'] ?? 0);
		$bankDebitMemos = (float)($typeSum['bank_debit_memo'] ?? 0);
		$interest = (float)($typeSum['interest_income'] ?? 0);
		$bankCreditMemos = (float)($typeSum['bank_credit_memo'] ?? 0);
		$other = (float)($typeSum['other'] ?? 0);

		$adjustedBank = $stmtEnding + $depositsInTransit - $outstandingChecks + $bankErrors;
		$adjustedBook = $bookEnding - $bankCharges - $bankDebitMemos + $interest + $bankCreditMemos + $bookErrors + $other;
		$diff = $adjustedBank - $adjustedBook;

		$form = $this->loadBrsFormConfig();
		$itemLabels = (isset($form['item_row_labels']) && is_array($form['item_row_labels'])) ? $form['item_row_labels'] : array();

		$asOf = !empty($recon['statement_as_of_date']) ? $recon['statement_as_of_date'] : $pe;
		$explanatory = isset($recon['explanatory_comment']) ? trim((string)$recon['explanatory_comment']) : '';
		$branch = isset($bank['branch']) ? trim((string)$bank['branch']) : '';

		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('BRS Summary');

		$r = 1;
		$sheet->setCellValue('A'.$r, isset($form['title']) ? $form['title'] : 'BANK RECONCILIATION STATEMENT (BRS)');
		$r += 2;
		$sheet->setCellValue('A'.$r, 'Date (as of): '.date('F d, Y', strtotime($asOf)));
		$r++;
		$sheet->setCellValue('A'.$r, 'Barangay: '.$_SESSION['currbrgy']);
		$r++;
		$sheet->setCellValue('A'.$r, 'Tel. No.: '.(isset($form['barangay_tel']) ? $form['barangay_tel'] : ''));
		$r++;
		$sheet->setCellValue('A'.$r, 'Bank Name: '.$bank['bank_name']);
		$r++;
		$sheet->setCellValue('A'.$r, 'Branch: '.$branch);
		$r++;
		$sheet->setCellValue('A'.$r, 'City/Municipality: '.(isset($form['city_municipality']) ? $form['city_municipality'] : ''));
		$r++;
		$sheet->setCellValue('A'.$r, 'Province: '.(isset($form['province']) ? $form['province'] : ''));
		$r++;
		$sheet->setCellValue('A'.$r, 'Current Account No.: '.$bank['account_no']);
		$r++;
		if(!empty($form['account_kind_label'])){
			$sheet->setCellValue('A'.$r, 'Account type: '.$form['account_kind_label']);
			$r++;
		}
		$sheet->setCellValue('A'.$r, 'Account name (in bank records): '.$bank['account_name']);
		$r++;
		$sheet->setCellValue('A'.$r, 'Accounting period (book): '.date('F Y', strtotime($ps)));
		$r += 2;

		$tableHeaderRow = $r;
		$sheet->setCellValue('A'.$r, 'Particulars');
		$sheet->setCellValue('B'.$r, 'Book');
		$sheet->setCellValue('C'.$r, 'Bank');
		$sheet->getStyle('A'.$r.':C'.$r)->getFont()->setBold(true);
		$r++;

		$sheet->setCellValue('A'.$r, isset($form['row_unadjusted_book']) ? $form['row_unadjusted_book'] : 'Unadjusted balance per books');
		$sheet->setCellValue('B'.$r, $bookEnding);
		$r++;

		if($bankCharges > 0){
			$sheet->setCellValue('A'.$r, isset($form['row_less_bank_charges']) ? $form['row_less_bank_charges'] : 'Less: Bank charges');
			$sheet->setCellValue('B'.$r, -$bankCharges);
			$r++;
		}
		if($bankDebitMemos > 0){
			$sheet->setCellValue('A'.$r, isset($form['row_less_bank_debit_memo']) ? $form['row_less_bank_debit_memo'] : 'Less: Bank debit memos');
			$sheet->setCellValue('B'.$r, -$bankDebitMemos);
			$r++;
		}
		if($interest > 0){
			$sheet->setCellValue('A'.$r, isset($form['row_add_interest']) ? $form['row_add_interest'] : 'Add: Interest income');
			$sheet->setCellValue('B'.$r, $interest);
			$r++;
		}
		if($bankCreditMemos > 0){
			$sheet->setCellValue('A'.$r, isset($form['row_add_bank_credit_memo']) ? $form['row_add_bank_credit_memo'] : 'Add: Bank credit memos');
			$sheet->setCellValue('B'.$r, $bankCreditMemos);
			$r++;
		}
		$bookErrOther = $bookErrors + $other;
		if(abs($bookErrOther) > 0.00001){
			$sheet->setCellValue('A'.$r, isset($form['row_book_errors_other']) ? $form['row_book_errors_other'] : 'Add/Less: Book errors and other');
			$sheet->setCellValue('B'.$r, $bookErrOther);
			$r++;
		}

		$sheet->setCellValue('A'.$r, isset($form['row_adj_book']) ? $form['row_adj_book'] : 'Adjusted balance per books');
		$sheet->setCellValue('B'.$r, $adjustedBook);
		$sheet->getStyle('A'.$r.':B'.$r)->getFont()->setBold(true);
		$r += 2;

		$sheet->setCellValue('A'.$r, isset($form['row_unadjusted_bank']) ? $form['row_unadjusted_bank'] : 'Unadjusted balance per bank statement');
		$sheet->setCellValue('C'.$r, $stmtEnding);
		$r++;

		if($depositsInTransit > 0){
			$sheet->setCellValue('A'.$r, isset($form['row_add_deposits_transit']) ? $form['row_add_deposits_transit'] : 'Add: Deposits in transit');
			$sheet->setCellValue('C'.$r, $depositsInTransit);
			$r++;
		}
		if($outstandingChecks > 0){
			$sheet->setCellValue('A'.$r, isset($form['row_less_outstanding_checks']) ? $form['row_less_outstanding_checks'] : 'Less: Outstanding checks');
			$sheet->setCellValue('C'.$r, -$outstandingChecks);
			$r++;
		}
		if(abs($bankErrors) > 0.00001){
			$sheet->setCellValue('A'.$r, isset($form['row_bank_errors']) ? $form['row_bank_errors'] : 'Add/Less: Bank errors');
			$sheet->setCellValue('C'.$r, $bankErrors);
			$r++;
		}

		$sheet->setCellValue('A'.$r, isset($form['row_adj_bank']) ? $form['row_adj_bank'] : 'Adjusted balance per bank statement');
		$sheet->setCellValue('C'.$r, $adjustedBank);
		$sheet->getStyle('A'.$r.':C'.$r)->getFont()->setBold(true);
		$r += 2;

		$sheet->setCellValue('A'.$r, isset($form['row_difference']) ? $form['row_difference'] : 'Difference (should be zero)');
		$sheet->setCellValue('B'.$r, $diff);
		$lastNumericRow = $r;
		$r += 2;

		$sheet->getStyle('B'.($tableHeaderRow + 1).':C'.$lastNumericRow)->getNumberFormat()->setFormatCode('#,##0.00');

		$sheet->setCellValue('A'.$r, 'Explanatory comment:');
		$r++;
		$sheet->setCellValue('A'.$r, $explanatory !== '' ? $explanatory : '—');
		$sheet->getStyle('A'.$r)->getAlignment()->setWrapText(true);
		$sheet->mergeCells('A'.$r.':C'.$r);
		$r += 2;

		$sheet->setCellValue('A'.$r, 'Certified correct:');
		$r++;
		$certName = isset($form['certified_by_name']) ? $form['certified_by_name'] : '';
		$certTitle = isset($form['certified_by_title']) ? $form['certified_by_title'] : '';
		$certLine = ($certName !== '' && $certTitle !== '') ? ($certName.', '.$certTitle) : ($certName !== '' ? $certName : $certTitle);
		$sheet->setCellValue('A'.$r, $certLine !== '' ? $certLine : '___________________________');
		$r += 2;

		if(!empty($form['instructions_short'])){
			$sheet->setCellValue('A'.$r, 'Notes (submission / distribution):');
			$r++;
			$sheet->setCellValue('A'.$r, $form['instructions_short']);
			$sheet->getStyle('A'.$r)->getAlignment()->setWrapText(true);
			$sheet->mergeCells('A'.$r.':C'.$r);
			$r++;
		}

		$sheet->getColumnDimension('A')->setWidth(52);
		$sheet->getColumnDimension('B')->setWidth(16);
		$sheet->getColumnDimension('C')->setWidth(16);

		// Details sheet
		$detail = $spreadsheet->createSheet();
		$detail->setTitle('Details');
		$detail->setCellValue('A1', 'Statement Lines');
		$detail->fromArray(array(array('Date', 'Description', 'Ref', 'Amount')), null, 'A2');
		$rr = 3;
		foreach($stmtLines as $sl){
			$detail->fromArray(array(array($sl['txn_date'], $sl['description'], $sl['reference'], (float)$sl['amount'])), null, 'A'.$rr);
			$rr++;
		}
		$rr += 2;
		$detail->setCellValue('A'.$rr, 'Reconciling Items (detail)');
		$rr++;
		$detail->fromArray(array(array('Type', 'Reference', 'Notes', 'Amount')), null, 'A'.$rr);
		$rr++;
		foreach($items as $it){
			$lbl = isset($itemLabels[$it['item_type']]) ? $itemLabels[$it['item_type']] : $it['item_type'];
			$detail->fromArray(array(array($lbl.' ('.$it['item_type'].')', $it['reference'], $it['notes'], (float)$it['amount'])), null, 'A'.$rr);
			$rr++;
		}

		return array($spreadsheet, null);
	}

	public function generateBRS(){
		$reconId = (int)$this->input->get('recon_id');
		list($spreadsheet, $err) = $this->buildBRSWorkbook($reconId);
		if(!$spreadsheet){
			return $this->respondNoData($err, '/bank_recon');
		}
		$this->load->helper('download');
		$this->load->helper('file');
		$currentDateTime = date('F-Y');
		$excelFileName = 'Bank_Reconciliation_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;
		$writer = new Xlsx($spreadsheet);
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
	}

	public function previewBRS(){
		$this->enablePreviewMode();
		return $this->generateBRS();
	}


	//functionality



	//jev

	public function savejev(){
		$acct_t = $this->input->post('acct_t');
		$acct_c = $this->input->post('acct_c');
		$debit = $this->input->post('debit');
		$credit = $this->input->post('credit');
		$subsidiaryIds = $this->input->post('subsidiary_id');
		$subsidiaryTypes = $this->input->post('subsidiary_type');
		$subsidiaryRefs = $this->input->post('subsidiary_ref');
		$cat = count($acct_t);
		$cac = count($acct_c);

		$hasSubsidiaryColumns = $this->db->field_exists('subsidiary_id', 'tbl_jevdata');

		// $this->db->where('jev_no',$this->input->post('jev_no'));
		// $this->db->where('brgy',$this->input->post('brgy'));
		// $check_res = $this->db->get('tbl_jev');
		// if($check_res->num_rows()>0){
		// 	$this->output
		// ->set_content_type('application/json')
		// ->set_output(json_encode(false));
		// }else{


		$jdate=strtotime($this->input->post('jev_date')); 
		
		$jdate=date("Y-m-d",$jdate);

		$jev_data = array(
			'jev_no'=>$this->input->post('jev_no'),
			'jev_date'=>$jdate,
			'fund'=>$this->input->post('fund'),
			'payor_payee'=>$this->input->post('payor'),
			'particulars'=>$this->input->post('parts'),
			'resp_center'=>$this->input->post('resp_center'),
			'type'=>$this->input->post('type'),
			'brgy'=>$this->input->post('brgy'),
			'status'=> 0
			
		);

		$res =  $this->db->insert('tbl_jev',$jev_data);
		$jev_id = $this->db->insert_id();
		

		if($res){
			if($this->input->post('type') == 'COL'){
				$odate=strtotime($this->input->post('or_date'));
				$odate=date("Y-m-d",$odate);
				for($i=0;$i < $cat;$i++){
					$data_array = array(
						'jev_id'=>$jev_id,
						'jev_no'=>$this->input->post('jev_no'),
						'or_num'=>$this->input->post('or_no'),
						'or_date'=> $odate,
						'payor'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);
					if($hasSubsidiaryColumns){
						$data_array['subsidiary_id'] = is_array($subsidiaryIds) && isset($subsidiaryIds[$i]) && $subsidiaryIds[$i] !== '' ? (int)$subsidiaryIds[$i] : null;
						$data_array['subsidiary_type'] = is_array($subsidiaryTypes) && isset($subsidiaryTypes[$i]) && $subsidiaryTypes[$i] !== '' ? $subsidiaryTypes[$i] : null;
						$data_array['subsidiary_ref'] = is_array($subsidiaryRefs) && isset($subsidiaryRefs[$i]) && $subsidiaryRefs[$i] !== '' ? $subsidiaryRefs[$i] : null;
					}

					$res2 = $this->db->insert('tbl_jevdata',$data_array);
				}
				$this->output
		->set_content_type('application/json')
		->set_output(json_encode(true));
				
			}elseif($this->input->post('type') == 'CKD'){
				$chkdate=strtotime($this->input->post('chk_date'));
				$chkdate=date("Y-m-d",$chkdate);
				for($i=0;$i < $cat;$i++){
					$data_array = array(
						'jev_id'=>$jev_id,
						'jev_no'=>$this->input->post('jev_no'),
						'dv_no'=>$this->input->post('v_no'),
						'check_no'=>$this->input->post('chk_no'),
						'check_date'=>$chkdate,
						'bank_acct'=>$this->input->post('bank_acct'),
						'payee'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);
					if($hasSubsidiaryColumns){
						$data_array['subsidiary_id'] = is_array($subsidiaryIds) && isset($subsidiaryIds[$i]) && $subsidiaryIds[$i] !== '' ? (int)$subsidiaryIds[$i] : null;
						$data_array['subsidiary_type'] = is_array($subsidiaryTypes) && isset($subsidiaryTypes[$i]) && $subsidiaryTypes[$i] !== '' ? $subsidiaryTypes[$i] : null;
						$data_array['subsidiary_ref'] = is_array($subsidiaryRefs) && isset($subsidiaryRefs[$i]) && $subsidiaryRefs[$i] !== '' ? $subsidiaryRefs[$i] : null;
					}

					$res2 = $this->db->insert('tbl_jevdata',$data_array);
				}
				$this->output
		->set_content_type('application/json')
		->set_output(json_encode(true));

			}elseif($this->input->post('type') == 'CSD'){
				for($i=0;$i < $cat;$i++){
					$data_array = array(
						'jev_id'=>$jev_id,
						'jev_no'=>$this->input->post('jev_no'),
						'dv_no'=>$this->input->post('vc_no'),
						'payee'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);
					if($hasSubsidiaryColumns){
						$data_array['subsidiary_id'] = is_array($subsidiaryIds) && isset($subsidiaryIds[$i]) && $subsidiaryIds[$i] !== '' ? (int)$subsidiaryIds[$i] : null;
						$data_array['subsidiary_type'] = is_array($subsidiaryTypes) && isset($subsidiaryTypes[$i]) && $subsidiaryTypes[$i] !== '' ? $subsidiaryTypes[$i] : null;
						$data_array['subsidiary_ref'] = is_array($subsidiaryRefs) && isset($subsidiaryRefs[$i]) && $subsidiaryRefs[$i] !== '' ? $subsidiaryRefs[$i] : null;
					}

					$res2 = $this->db->insert('tbl_jevdata',$data_array);
				}
				$this->output
		->set_content_type('application/json')
		->set_output(json_encode(true));

			}else{
				for($i=0;$i < $cat;$i++){
					$data_array = array(
						'jev_id'=>$jev_id,
						'jev_no'=>$this->input->post('jev_no'),
						'iv_no'=>$this->input->post('iv_no'),
						'iv_date'=>$this->input->post('iv_date'),
						'po_no'=>$this->input->post('po_no'),
						'po_date'=>$this->input->post('po_date'),
						'check_no'=>$this->input->post('chk_no'),
						'check_date'=>$this->input->post('chk_date'),
						'payee'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);
					if($hasSubsidiaryColumns){
						$data_array['subsidiary_id'] = is_array($subsidiaryIds) && isset($subsidiaryIds[$i]) && $subsidiaryIds[$i] !== '' ? (int)$subsidiaryIds[$i] : null;
						$data_array['subsidiary_type'] = is_array($subsidiaryTypes) && isset($subsidiaryTypes[$i]) && $subsidiaryTypes[$i] !== '' ? $subsidiaryTypes[$i] : null;
						$data_array['subsidiary_ref'] = is_array($subsidiaryRefs) && isset($subsidiaryRefs[$i]) && $subsidiaryRefs[$i] !== '' ? $subsidiaryRefs[$i] : null;
					}

					$res2 = $this->db->insert('tbl_jevdata',$data_array);
				}
				$this->output
		->set_content_type('application/json')
		->set_output(json_encode(true));

			}
			
		
		}
	
		
	}

	//save beginning balance
	public function savebb(){
		$acct_t = $this->input->post('acct_t');
		$acct_c = $this->input->post('acct_c');
		$debit = $this->input->post('debit');
		$credit = $this->input->post('credit');
		$cat = count($acct_t);
		$cac = count($acct_c);
		$year = $this->input->post('year');
		$brgy = $this->input->post('brgy');

		$this->db->where('year',$year);
		$this->db->where('brgy_id',$brgy);
		$check_res = $this->db->get('tbl_begbal');

		if($check_res->num_rows()>0){
			$res = array(
				'status'=>false,
				'message'=>'Beginning balance for year '.$year.' already exists in the system!'

			);

			$this->output
			->set_content_type('application/json')
			->set_output(json_encode($res));
			return;
		}

		
			$this->db->trans_start();

			for($i=0;$i < $cat;$i++){
				$data_array = array(
					'acc_title'=>$acct_t[$i],
					'acc_code'=>$acct_c[$i],
					'debit'=> $debit[$i],
					'credit'=> $credit[$i],
					'year'=>$year,
					'brgy_id'=>$brgy,
					'date_created'=>date('F j, Y')
					);

				$this->db->insert('tbl_begbal',$data_array);
				}

			$this->db->trans_complete();	

			if ($this->db->trans_status() === FALSE){
				$res = array(
					'status'=>false,
					'message'=>'Error in adding beginning balance for year '.$year

				);

					$this->output
			->set_content_type('application/json')
			->set_output(json_encode($res));
           }else{
			$res = array(
				'status'=>true,
				'message'=>'Beginning Balance Data for Year '.$year.' Successflly Added!'

			);

			$this->session->unset_userdata('data');

			$this->output
			->set_content_type('application/json')
			->set_output(json_encode($res));
		   }

				
	}



	// end beginning balance

	
	public function updatejev(){
		$acct_t = $this->input->post('acct_t');
		$acct_c = $this->input->post('acct_c');
		$jdid = $this->input->post('jdid');
		$debit = $this->input->post('debit');
		$credit = $this->input->post('credit');
		$subsidiaryIds = $this->input->post('subsidiary_id');
		$subsidiaryTypes = $this->input->post('subsidiary_type');
		$subsidiaryRefs = $this->input->post('subsidiary_ref');
		$cat = count($acct_t);
		$cac = count($acct_c);

		$hasSubsidiaryColumns = $this->db->field_exists('subsidiary_id', 'tbl_jevdata');
		


		$jdate=strtotime($this->input->post('jev_date')); 
		
		$jdate=date("Y-m-d",$jdate);
		
		$jev_data = array(
			'jev_no'=>$this->input->post('jev_no'),
			'jev_date'=>$jdate,
			'fund'=>$this->input->post('fund'),
			'payor_payee'=>$this->input->post('payor'),
			'particulars'=>$this->input->post('parts'),
			'resp_center'=>$this->input->post('resp_center'),
			'type'=>$this->input->post('type'),
			'brgy'=>$this->input->post('brgy'),
			'status'=> 0
			
		);
		$this->db->where('jev_id', $this->input->post('dbjev_no'));
		$res =  $this->db->update('tbl_jev',$jev_data);
		
		$existingJevData = $this->getAllJevData($this->input->post('dbjev_no'));
		$flattened = array_map(fn($item) => $item['jevdata_id'], $existingJevData);
		$f = count($flattened);
		
		$difference = array_diff($jdid,$flattened);
		

		if(!empty($difference)){
			if($this->input->post('type') == 'COL'){
				$odate=strtotime($this->input->post('or_date'));
				$odate=date("Y-m-d",$odate);
				for($i=$f;$i < $cat;$i++){
					$data_array = array(
						'jev_id'=>$this->input->post('dbjev_no'),
						'jev_no'=>$this->input->post('jev_no'),
						'or_num'=>$this->input->post('or_no'),
						'or_date'=> $odate,
						'payor'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);

					$res2 = $this->db->insert('tbl_jevdata',$data_array);
				}
				
				
			}elseif($this->input->post('type') == 'CKD'){
				$chkdate=strtotime($this->input->post('chk_date'));
				$chkdate=date("Y-m-d",$chkdate);
				for($i=$f;$i < $cat;$i++){
					$data_array = array(
						'jev_id'=>$this->input->post('dbjev_no'),
						'jev_no'=>$this->input->post('jev_no'),
						'dv_no'=>$this->input->post('v_no'),
						'check_no'=>$this->input->post('chk_no'),
						'check_date'=>$chkdate,
						'bank_acct'=>$this->input->post('bank_acct'),
						'payee'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);

					$res2 = $this->db->insert('tbl_jevdata',$data_array);
				}
				

			}elseif($this->input->post('type') == 'CSD'){
				for($i=$f;$i < $cat;$i++){
					$data_array = array(
						'jev_id'=>$this->input->post('dbjev_no'),
						'jev_no'=>$this->input->post('jev_no'),
						'dv_no'=>$this->input->post('vc_no'),
						'payee'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);

					$res2 = $this->db->insert('tbl_jevdata',$data_array);
				}
			

			}else{
				for($i=$f;$i < $cat;$i++){
					$data_array = array(
						'jev_id'=>$this->input->post('dbjev_no'),
						'jev_no'=>$this->input->post('jev_no'),
						'iv_no'=>$this->input->post('iv_no'),
						'iv_date'=>$this->input->post('iv_date'),
						'po_no'=>$this->input->post('po_no'),
						'po_date'=>$this->input->post('po_date'),
						'check_no'=>$this->input->post('chk_no'),
						'check_date'=>$this->input->post('chk_date'),
						'payee'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);

					$res2 = $this->db->insert('tbl_jevdata',$data_array);
				}
				

			}


		}

		if($res){
			if($this->input->post('type') == 'COL'){
				$odate=strtotime($this->input->post('or_date'));
				$odate=date("Y-m-d",$odate);
				for($i=0;$i < $cat;$i++){
					$data_array = array(
						'jev_id'=>$this->input->post('dbjev_no'),
						'jev_no'=>$this->input->post('jev_no'),
						'or_num'=>$this->input->post('or_no'),
						'or_date'=> $odate,
						'payor'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);
					if($hasSubsidiaryColumns){
						$data_array['subsidiary_id'] = is_array($subsidiaryIds) && isset($subsidiaryIds[$i]) && $subsidiaryIds[$i] !== '' ? (int)$subsidiaryIds[$i] : null;
						$data_array['subsidiary_type'] = is_array($subsidiaryTypes) && isset($subsidiaryTypes[$i]) && $subsidiaryTypes[$i] !== '' ? $subsidiaryTypes[$i] : null;
						$data_array['subsidiary_ref'] = is_array($subsidiaryRefs) && isset($subsidiaryRefs[$i]) && $subsidiaryRefs[$i] !== '' ? $subsidiaryRefs[$i] : null;
					}
					$this->db->where('jevdata_id', $jdid[$i]);
					$res2 = $this->db->update('tbl_jevdata',$data_array);
				}
				$this->output
		->set_content_type('application/json')
		->set_output(json_encode(true));
				
			}elseif($this->input->post('type') == 'CKD'){
				$chkdate=strtotime($this->input->post('chk_date'));
				$chkdate=date("Y-m-d",$chkdate);
				for($i=0;$i < $cat;$i++){
					$data_array = array(
						'jev_no'=>$this->input->post('jev_no'),
						'dv_no'=>$this->input->post('v_no'),
						'check_no'=>$this->input->post('chk_no'),
						'check_date'=>$chkdate,
						'bank_acct'=>$this->input->post('bank_acct'),
						'payee'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);
					if($hasSubsidiaryColumns){
						$data_array['subsidiary_id'] = is_array($subsidiaryIds) && isset($subsidiaryIds[$i]) && $subsidiaryIds[$i] !== '' ? (int)$subsidiaryIds[$i] : null;
						$data_array['subsidiary_type'] = is_array($subsidiaryTypes) && isset($subsidiaryTypes[$i]) && $subsidiaryTypes[$i] !== '' ? $subsidiaryTypes[$i] : null;
						$data_array['subsidiary_ref'] = is_array($subsidiaryRefs) && isset($subsidiaryRefs[$i]) && $subsidiaryRefs[$i] !== '' ? $subsidiaryRefs[$i] : null;
					}
					$this->db->where('jevdata_id', $jdid[$i]);
					$res2 = $this->db->update('tbl_jevdata',$data_array);
				}
				$this->output
		->set_content_type('application/json')
		->set_output(json_encode(true));

			}else{
				for($i=0;$i <$cat;$i++){
					$data_array = array(
						'jev_no'=>$this->input->post('jev_no'),
						'dv_no'=>$this->input->post('vc_no'),
						'payee'=>$this->input->post('payor'),
						'acc_title'=>$acct_t[$i],
						'acc_code'=>$acct_c[$i],
						'debit'=> $debit[$i],
						'credit'=> $credit[$i]

					);
					if($hasSubsidiaryColumns){
						$data_array['subsidiary_id'] = is_array($subsidiaryIds) && isset($subsidiaryIds[$i]) && $subsidiaryIds[$i] !== '' ? (int)$subsidiaryIds[$i] : null;
						$data_array['subsidiary_type'] = is_array($subsidiaryTypes) && isset($subsidiaryTypes[$i]) && $subsidiaryTypes[$i] !== '' ? $subsidiaryTypes[$i] : null;
						$data_array['subsidiary_ref'] = is_array($subsidiaryRefs) && isset($subsidiaryRefs[$i]) && $subsidiaryRefs[$i] !== '' ? $subsidiaryRefs[$i] : null;
					}

					$this->db->where('jevdata_id', $jdid[$i]);
					$res2 = $this->db->update('tbl_jevdata',$data_array);
				}
				$this->output
		->set_content_type('application/json')
		->set_output(json_encode(true));

			}
			
		
		}
		
	}

	public function deletejev(){
		$this->db->where('jev_id', $this->input->post('jevid'));
		$res = $this->db->delete('tbl_jev');

		if($res){
			$this->db->where('jev_id',  $this->input->post('jevid'));
			$res2 = $this->db->delete('tbl_jevdata');
			$this->output
			->set_content_type('application/json')
			->set_output(json_encode(true));
	

		}else{
			$this->output
			->set_content_type('application/json')
			->set_output(json_encode(false));
	
		}
	}


	public function getAllJevData($jev_id){
		$sqlQuery = "SELECT
		jevdata_id
    FROM
        tbl_jevdata 
	WHERE jev_id = ?;";

	$queryParams = [$jev_id];
	$query = $this->db->query($sqlQuery, $queryParams);
		return $query->result_array();
	}

	// <option value="GJ">General Journal</option>
	// <option value="CRJ">Cash Receipts Journal</option>
	// <option value="CKDJ">Check Disbursement Journal</option>
	// <option value="CSDJ">Cash Disbursement Journal</option>

	public function getType($type){
		if($type == "GJ"){
			return "GJ";
		}elseif($type == "CRJ"){
			return "COL";
		}elseif($type == "CKDJ"){
			return "CKD";
		}else{
			return "CSD";
		}
	}

	public function generateLedger(){
		$startDate = date('Y-m-d', strtotime($this->input->post('sdate')));
		$endDate = date('Y-m-d', strtotime($this->input->post('edate')));
		$acc_code = $this->input->post('acc_code');
		$acc_name = $this->input->post('acc_name');
		$ltype = $this->input->post('l_type');
		$gtype = $this->input->post('g_type');
		$hasSubsidiaryId = $this->db->field_exists('subsidiary_id', 'tbl_jevdata');

		if($gtype == 'sp'){

		if($ltype == 's'){
			if($hasSubsidiaryId){
				$this->generateSubsidiaryLedger($acc_code,$acc_name,$startDate,$endDate);
			}else{
				$this->generateGeneralLedger($acc_code,$acc_name,$startDate,$endDate);
			}
		
		}elseif($ltype=='ss'){
			if($hasSubsidiaryId){
				$this->generateSubsidiarySchedule($acc_code,$acc_name,$startDate,$endDate);
			}else{
				$this->generateGeneralLedgerSS($acc_code,$acc_name,$startDate,$endDate);
			}
		}else{
			$this->generateGeneralLedger2($acc_code,$acc_name,$startDate,$endDate);
		}
	}else{
		if($ltype == 's'){
			if($hasSubsidiaryId){
				$this->generateSubsidiaryLedgerAll($startDate,$endDate);
			}else{
				$this->generateGeneralLedgerAll($startDate,$endDate);
			}
		
		}elseif($ltype=='ss'){
			if($hasSubsidiaryId){
				$this->generateSubsidiaryScheduleAll($startDate,$endDate);
			}else{
				$this->generateGeneralLedgerSSAll($startDate,$endDate);
			}
		}else{
			$this->generateGeneralLedger2All($startDate,$endDate);
		}

	}
	}

	public function previewLedger(){
		$this->enablePreviewMode();
		return $this->generateLedger();
	}

	
	public function generateGeneralLedgerSS($acc_code,$acc_name,$startDate,$endDate){
		

		$sqlQuery = "SELECT
		jd.jevdata_id,
        j.jev_date,
        j.jev_no,
		j.particulars,
		j.type,
		j.payor_payee,
		jd.payee,
		jd.payor,
        jd.debit,
        jd.credit
    FROM
        tbl_jevdata jd
    LEFT JOIN
        tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
    WHERE
        jd.acc_code = ?
        AND j.jev_date BETWEEN ? AND ?
		AND j.brgy = ?
    ORDER BY
        j.jev_date, j.jev_no;
";

$queryParams = [$acc_code, $startDate, $endDate,$_SESSION['currbrgyid']];
$query = $this->db->query($sqlQuery, $queryParams);
		if($query->num_rows() == 0){
			return $this->respondNoData('No Data Available for the specific Date range and Account Code!', '/gl');

		}else{
		$ledgerEntries = $query->result();
$this->generateGLS_file($ledgerEntries,$acc_code,$acc_name,$startDate,$endDate);
		}

	}

	
	public function generateGeneralLedger2($acc_code,$acc_name,$startDate,$endDate){
		

		$sqlQuery = "SELECT
        j.jev_date,
        j.jev_no,
		j.particulars,
		j.type,
        jd.debit,
        jd.credit,
		jd.jevdata_id
    FROM
        tbl_jevdata jd
    LEFT JOIN
        tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
    WHERE
        jd.acc_code = ?
        AND j.jev_date BETWEEN ? AND ?
		AND j.brgy = ?
    ORDER BY
        j.jev_date, j.jev_no;
";

$queryParams = [$acc_code, $startDate, $endDate,$_SESSION['currbrgyid']];
$query = $this->db->query($sqlQuery, $queryParams);
		if($query->num_rows() == 0){
			return $this->respondNoData('No Data Available for the specific Date range and Account Group!', '/gl');

		}else{
		$ledgerEntries = $query->result();
$this->generateGL_file2($ledgerEntries,$acc_code,$acc_name,$startDate,$endDate);
		}

	}

	public function generateGeneralLedger($acc_code,$acc_name,$startDate,$endDate){
		$sqlQuery = "SELECT
        j.jev_date,
        j.jev_no,
		j.particulars,
		j.type,
        jd.debit,
        jd.credit,
		jd.jevdata_id
    FROM
        tbl_jevdata jd
    LEFT JOIN
        tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
    WHERE
        jd.acc_code = ?
        AND j.jev_date BETWEEN ? AND ?
		AND j.brgy = ?
    ORDER BY
        j.jev_date, j.jev_no;
";

$queryParams = [$acc_code, $startDate, $endDate,$_SESSION['currbrgyid']];
$query = $this->db->query($sqlQuery, $queryParams);
		if($query->num_rows() == 0){
			return $this->respondNoData('No Data Available for the specific Date range and Account Code!', '/gl');

		}else{
		$ledgerEntries = $query->result();
$this->generateGL_file($ledgerEntries,$acc_code,$acc_name,$startDate,$endDate);
		}
	

	}

	public function generateSubsidiaryLedger($acc_code, $acc_name, $startDate, $endDate){
		$this->load->helper('download');
		$this->load->helper('file');

		$brgyId = $this->getSelectedBrgyId();
		$hasSubsidiaryMaster = $this->hasSubsidiaryMaster();

		$this->db->select('jd.subsidiary_id, s.subsidiary_type, s.name as subsidiary_name');
		$this->db->from('tbl_jevdata jd');
		$this->db->join('tbl_jev j', 'jd.jev_id = j.jev_id AND jd.jev_no = j.jev_no');
		if($hasSubsidiaryMaster){
			$this->db->join($this->subsidiaryTable.' s', 'jd.subsidiary_id = s.subsidiary_id', 'left');
		}else{
			$this->db->join('(SELECT NULL as subsidiary_id, NULL as subsidiary_type, NULL as name) s', '1=0', 'left', false);
		}
		$this->db->where('j.brgy', $brgyId);
		$this->db->where('jd.acc_code', $acc_code);
		$this->db->where('j.jev_date >=', $startDate);
		$this->db->where('j.jev_date <=', $endDate);
		$this->db->where('jd.subsidiary_id IS NOT NULL', null, false);
		$this->db->group_by('jd.subsidiary_id');
		$this->db->order_by('s.subsidiary_type', 'ASC');
		$this->db->order_by('s.name', 'ASC');
		$subs = $this->db->get()->result_array();

		if(empty($subs)){
			return $this->respondNoData('No subsidiary-tagged entries found for this account/date range. Please encode subsidiary IDs on JEV line items.', '/gl');
		}

		$templatePath = FCPATH .'assets/templates/sl.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

		$activeSheet = 0;
		foreach($subs as $sub){
			$subId = (int) $sub['subsidiary_id'];
			$subName = (string) ($sub['subsidiary_name'] ?? ('Subsidiary '.$subId));
			$newStr = str_replace(array('*', ':', '/', '\\', '?', '[', ']'), ' ', $subName);

			if($activeSheet === 0){
				$sheet = $spreadsheet->getSheetByName('SL') ?: $spreadsheet->getActiveSheet();
				$sheet->setTitle(substr($newStr, 0, 30));
			}else{
				$clonedWorksheet = clone ($spreadsheet->getSheetByName('SL') ?: $spreadsheet->getActiveSheet());
				$clonedWorksheet->setTitle(substr($newStr, 0, 30));
				$spreadsheet->addSheet($clonedWorksheet);
			}

			$spreadsheet->setActiveSheetIndex($activeSheet);
			$sheet = $spreadsheet->getActiveSheet();

			// Fetch entries for this subsidiary
			$sqlQuery = "SELECT
				j.jev_date,
				j.jev_no,
				j.particulars,
				j.type,
				j.payor_payee,
				jd.debit,
				jd.credit
			FROM tbl_jevdata jd
			JOIN tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
			WHERE jd.acc_code = ?
				AND jd.subsidiary_id = ?
				AND j.jev_date BETWEEN ? AND ?
				AND j.brgy = ?
			ORDER BY j.jev_date, j.jev_no;";

			$queryParams = array($acc_code, $subId, $startDate, $endDate, $brgyId);
			$ledgerEntries = $this->db->query($sqlQuery, $queryParams)->result();

			$startYear = date('Y', strtotime($startDate));
			$beg = $this->getAccountBeginningBalance($brgyId, $startYear, $acc_code, $subId);
			$begbald = $beg['debit'];
			$begbalc = $beg['credit'];

			// Header (best-effort; template may differ)
			$sheet->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
			$sheet->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
			$sheet->setCellValue('C5', 'Account Name: '.$acc_name);
			$sheet->setCellValue('C6', 'Subsidiary: '.$subName);
			$sheet->setCellValue('F6', $acc_code);
			$sheet->setCellValue('E10', $begbald);
			$sheet->setCellValue('F10', $begbalc);

			$currentMonth = date('Y-m', strtotime($startDate));
			$rowIndex = 11;
			$firstRow = 11;
			$prevDebitTotalIndex = 10;
			$prevCreditTotalIndex = 10;

			foreach($ledgerEntries as $entry){
				$entryMonth = date('Y-m', strtotime($entry->jev_date));
				if($entryMonth != $currentMonth){
					$rowIndex++;
					$sheet->setCellValue('B' . $rowIndex, 'TOTAL THIS MONTH');
					$sheet->setCellValue('E' . $rowIndex, '=SUM(E'.$firstRow.':E' . ($rowIndex - 2) . ')');
					$sheet->setCellValue('F' . $rowIndex, '=SUM(F'.$firstRow.':F' . ($rowIndex - 2) . ')');
					$sheet->setCellValue('B' . ($rowIndex+1), 'TOTAL TO DATE');
					$sheet->setCellValue('E' . ($rowIndex+1), '=E'.$prevDebitTotalIndex.'+E' .$rowIndex);
					$sheet->setCellValue('F' . ($rowIndex+1), '=F'.$prevCreditTotalIndex.'+F' .$rowIndex);
					$sheet->setCellValue('G' . ($rowIndex+1), '=E'.($rowIndex+1).'-F' . ($rowIndex+1));
					$prevDebitTotalIndex = $rowIndex+1;
					$prevCreditTotalIndex = $rowIndex+1;
					$rowIndex += 4;
					$firstRow = $rowIndex;
					$currentMonth = $entryMonth;
				}

				$sheet->setCellValue('A' . $rowIndex, $entry->jev_date);
				$sheet->setCellValue('B' . $rowIndex, $entry->particulars);
				$sheet->setCellValue('D' . $rowIndex, $entry->type.' '.substr($entry->jev_no, -3));
				$sheet->setCellValue('E' . $rowIndex, $entry->debit);
				$sheet->setCellValue('F' . $rowIndex, $entry->credit);
				$rowIndex++;
			}

			$activeSheet++;
		}

		$spreadsheet->setActiveSheetIndex(0);
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Subsidiary Ledger_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;
		$writer = new Xlsx($spreadsheet);
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
	}

	public function generateSubsidiaryLedgerAll($startDate, $endDate){
		$this->load->helper('download');
		$this->load->helper('file');

		$this->db->order_by('code','ASC');
		$accounts = $this->db->get('tbl_accounts')->result();

		$templatePath = FCPATH .'assets/templates/sl.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
		$baseSheet = $spreadsheet->getSheetByName('SL') ?: $spreadsheet->getActiveSheet();

		$brgyId = $this->getSelectedBrgyId();
		$hasSubsidiaryMaster = $this->hasSubsidiaryMaster();

		$activeSheet = 0;
		foreach($accounts as $a){
			$sql = "SELECT DISTINCT jd.subsidiary_id
				FROM tbl_jevdata jd
				JOIN tbl_jev j ON jd.jev_id = j.jev_id AND jd.jev_no = j.jev_no
				WHERE j.brgy = ?
					AND jd.acc_code = ?
					AND jd.subsidiary_id IS NOT NULL
					AND j.jev_date BETWEEN ? AND ?;";
			$subs = $this->db->query($sql, array($brgyId, $a->code, $startDate, $endDate))->result_array();
			if(empty($subs)){
				continue;
			}

			foreach($subs as $subRow){
				$subId = (int)$subRow['subsidiary_id'];
				$subName = 'Subsidiary '.$subId;
				if($hasSubsidiaryMaster){
					$sub = $this->db->get_where($this->subsidiaryTable, array('subsidiary_id' => $subId))->row_array();
					if($sub && isset($sub['name'])){
						$subName = $sub['name'];
					}
				}

				$title = substr(str_replace(array('*', ':', '/', '\\', '?', '[', ']'), ' ', $a->code.'-'.$subName), 0, 30);
				if($activeSheet === 0){
					$baseSheet->setTitle($title);
				}else{
					$cloned = clone $baseSheet;
					$cloned->setTitle($title);
					$spreadsheet->addSheet($cloned);
				}

				$spreadsheet->setActiveSheetIndex($activeSheet);
				$sheet = $spreadsheet->getActiveSheet();

				$sqlQuery = "SELECT
					j.jev_date,
					j.jev_no,
					j.particulars,
					j.type,
					jd.debit,
					jd.credit
				FROM tbl_jevdata jd
				JOIN tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
				WHERE jd.acc_code = ?
					AND jd.subsidiary_id = ?
					AND j.jev_date BETWEEN ? AND ?
					AND j.brgy = ?
				ORDER BY j.jev_date, j.jev_no;";
				$ledgerEntries = $this->db->query($sqlQuery, array($a->code, $subId, $startDate, $endDate, $brgyId))->result();

				$startYear = date('Y', strtotime($startDate));
				$beg = $this->getAccountBeginningBalance($brgyId, $startYear, $a->code, $subId);
				$begbald = $beg['debit'];
				$begbalc = $beg['credit'];

				$sheet->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
				$sheet->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
				$sheet->setCellValue('C5', 'Account Name: '.$a->name);
				$sheet->setCellValue('C6', 'Subsidiary: '.$subName);
				$sheet->setCellValue('F6', $a->code);
				$sheet->setCellValue('E10', $begbald);
				$sheet->setCellValue('F10', $begbalc);

				$currentMonth = date('Y-m', strtotime($startDate));
				$rowIndex = 11;
				$firstRow = 11;
				$prevDebitTotalIndex = 10;
				$prevCreditTotalIndex = 10;
				foreach($ledgerEntries as $entry){
					$entryMonth = date('Y-m', strtotime($entry->jev_date));
					if($entryMonth != $currentMonth){
						$rowIndex++;
						$sheet->setCellValue('B' . $rowIndex, 'TOTAL THIS MONTH');
						$sheet->setCellValue('E' . $rowIndex, '=SUM(E'.$firstRow.':E' . ($rowIndex - 2) . ')');
						$sheet->setCellValue('F' . $rowIndex, '=SUM(F'.$firstRow.':F' . ($rowIndex - 2) . ')');
						$sheet->setCellValue('B' . ($rowIndex+1), 'TOTAL TO DATE');
						$sheet->setCellValue('E' . ($rowIndex+1), '=E'.$prevDebitTotalIndex.'+E' .$rowIndex);
						$sheet->setCellValue('F' . ($rowIndex+1), '=F'.$prevCreditTotalIndex.'+F' .$rowIndex);
						$sheet->setCellValue('G' . ($rowIndex+1), '=E'.($rowIndex+1).'-F' . ($rowIndex+1));
						$prevDebitTotalIndex = $rowIndex+1;
						$prevCreditTotalIndex = $rowIndex+1;
						$rowIndex += 4;
						$firstRow = $rowIndex;
						$currentMonth = $entryMonth;
					}

					$sheet->setCellValue('A' . $rowIndex, $entry->jev_date);
					$sheet->setCellValue('B' . $rowIndex, $entry->particulars);
					$sheet->setCellValue('D' . $rowIndex, $entry->type.' '.substr($entry->jev_no, -3));
					$sheet->setCellValue('E' . $rowIndex, $entry->debit);
					$sheet->setCellValue('F' . $rowIndex, $entry->credit);
					$rowIndex++;
				}

				$activeSheet++;
			}
		}

		if($activeSheet === 0){
			return $this->respondNoData('No subsidiary-tagged entries found for the selected range.', '/gl');
		}

		$spreadsheet->setActiveSheetIndex(0);
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Subsidiary Ledger_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;
		$writer = new Xlsx($spreadsheet);
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
	}

	public function generateSubsidiarySchedule($acc_code, $acc_name, $startDate, $endDate){
		$this->load->helper('download');
		$this->load->helper('file');

		$brgyId = $this->getSelectedBrgyId();
		$hasSubsidiaryMaster = $this->hasSubsidiaryMaster();

		// Period totals per subsidiary
		$this->db->select('jd.subsidiary_id');
		$this->db->select_sum('jd.debit', 'total_debit');
		$this->db->select_sum('jd.credit', 'total_credit');
		$this->db->from('tbl_jevdata jd');
		$this->db->join('tbl_jev j', 'jd.jev_id = j.jev_id AND jd.jev_no = j.jev_no');
		$this->db->where('j.brgy', $brgyId);
		$this->db->where('jd.acc_code', $acc_code);
		$this->db->where('jd.subsidiary_id IS NOT NULL', null, false);
		$this->db->where('j.jev_date >=', $startDate);
		$this->db->where('j.jev_date <=', $endDate);
		$this->db->group_by('jd.subsidiary_id');
		$rows = $this->db->get()->result_array();

		if(empty($rows)){
			return $this->respondNoData('No subsidiary-tagged entries found for this account/date range.', '/gl');
		}

		// Build schedule rows with names + beginnings
		$year = (int) date('Y', strtotime($startDate));
		$schedule = array();
		$totalEnding = 0.0;
		foreach($rows as $row){
			$subId = (int) $row['subsidiary_id'];
			$name = 'Subsidiary '.$subId;
			$type = null;
			if($hasSubsidiaryMaster){
				$sub = $this->db->get_where($this->subsidiaryTable, array('subsidiary_id' => $subId))->row_array();
				if($sub){
					$name = (string)($sub['name'] ?? $name);
					$type = $sub['subsidiary_type'] ?? null;
				}
			}

			$beg = $this->getAccountBeginningBalance($brgyId, $year, $acc_code, $subId);
			$begNet = (float)$beg['debit'] - (float)$beg['credit'];
			$periodNet = (float)$row['total_debit'] - (float)$row['total_credit'];
			$ending = $begNet + $periodNet;

			$schedule[] = array(
				'subsidiary_id' => $subId,
				'subsidiary_type' => $type,
				'name' => $name,
				'beg_debit' => (float)$beg['debit'],
				'beg_credit' => (float)$beg['credit'],
				'period_debit' => (float)$row['total_debit'],
				'period_credit' => (float)$row['total_credit'],
				'ending' => $ending
			);
			$totalEnding += $ending;
		}

		// Compute GL ending for the control account for reconciliation
		$begAcc = $this->getAccountBeginningBalance($brgyId, $year, $acc_code, null);
		$begAccNet = (float)$begAcc['debit'] - (float)$begAcc['credit'];
		$this->db->select_sum('jd.debit', 'total_debit');
		$this->db->select_sum('jd.credit', 'total_credit');
		$this->db->from('tbl_jevdata jd');
		$this->db->join('tbl_jev j', 'jd.jev_id = j.jev_id AND jd.jev_no = j.jev_no');
		$this->db->where('j.brgy', $brgyId);
		$this->db->where('jd.acc_code', $acc_code);
		$this->db->where('j.jev_date >=', $startDate);
		$this->db->where('j.jev_date <=', $endDate);
		$tot = $this->db->get()->row_array();
		$glEnding = $begAccNet + ((float)($tot['total_debit'] ?? 0) - (float)($tot['total_credit'] ?? 0));
		$diff = $totalEnding - $glEnding;

		usort($schedule, function($a, $b){
			return strcmp((string)$a['name'], (string)$b['name']);
		});

		$templatePath = FCPATH .'assets/templates/ss.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
		$sheet = $spreadsheet->getActiveSheet();

		$sheet->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
		$sheet->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$sheet->setCellValue('A7', 'Account Name: '.$acc_name);
		$sheet->setCellValue('E6', date('Y', strtotime($endDate)));
		$sheet->setCellValue('E7', $acc_code);

		$row = 10;
		foreach($schedule as $item){
			$sheet->setCellValue('A'.$row, $item['subsidiary_id']);
			$sheet->setCellValue('B'.$row, $item['name']);
			$sheet->setCellValue('C'.$row, $item['period_debit']);
			$sheet->setCellValue('D'.$row, $item['period_credit']);
			$sheet->setCellValue('E'.$row, $item['ending']);
			$row++;
		}

		// Totals + reconciliation block (best-effort layout)
		$sheet->setCellValue('B'.$row, 'TOTAL ENDING (SS)');
		$sheet->setCellValue('E'.$row, $totalEnding);
		$row += 2;
		$sheet->setCellValue('B'.$row, 'GL ENDING (CONTROL)');
		$sheet->setCellValue('E'.$row, $glEnding);
		$row++;
		$sheet->setCellValue('B'.$row, 'DIFFERENCE (SS - GL)');
		$sheet->setCellValue('E'.$row, $diff);

		$spreadsheet->setActiveSheetIndex(0);
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Subsidiary Schedule_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;
		$writer = new Xlsx($spreadsheet);
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
	}

	public function generateSubsidiaryScheduleAll($startDate, $endDate){
		$this->load->helper('download');
		$this->load->helper('file');

		$brgyId = $this->getSelectedBrgyId();
		$this->db->order_by('code','ASC');
		$accounts = $this->db->get('tbl_accounts')->result();

		$templatePath = FCPATH .'assets/templates/ss.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
		$baseSheet = $spreadsheet->getActiveSheet();

		$hasSubsidiaryMaster = $this->hasSubsidiaryMaster();
		$year = (int) date('Y', strtotime($startDate));

		$activeSheet = 0;
		foreach($accounts as $a){
			// only include accounts with subsidiary-tagged entries in range
			$sql = "SELECT 1
				FROM tbl_jevdata jd
				JOIN tbl_jev j ON jd.jev_id = j.jev_id AND jd.jev_no = j.jev_no
				WHERE j.brgy = ?
					AND jd.acc_code = ?
					AND jd.subsidiary_id IS NOT NULL
					AND j.jev_date BETWEEN ? AND ?
				LIMIT 1;";
			$has = $this->db->query($sql, array($brgyId, $a->code, $startDate, $endDate))->num_rows() > 0;
			if(!$has){
				continue;
			}

			$title = substr(str_replace(array('*', ':', '/', '\\', '?', '[', ']'), ' ', $a->code), 0, 30);
			if($activeSheet === 0){
				$baseSheet->setTitle($title);
			}else{
				$cloned = clone $baseSheet;
				$cloned->setTitle($title);
				$spreadsheet->addSheet($cloned);
			}
			$spreadsheet->setActiveSheetIndex($activeSheet);

			// Build SS rows for this account
			$this->db->select('jd.subsidiary_id');
			$this->db->select_sum('jd.debit', 'total_debit');
			$this->db->select_sum('jd.credit', 'total_credit');
			$this->db->from('tbl_jevdata jd');
			$this->db->join('tbl_jev j', 'jd.jev_id = j.jev_id AND jd.jev_no = j.jev_no');
			$this->db->where('j.brgy', $brgyId);
			$this->db->where('jd.acc_code', $a->code);
			$this->db->where('jd.subsidiary_id IS NOT NULL', null, false);
			$this->db->where('j.jev_date >=', $startDate);
			$this->db->where('j.jev_date <=', $endDate);
			$this->db->group_by('jd.subsidiary_id');
			$rows = $this->db->get()->result_array();
			if(empty($rows)){
				$activeSheet++;
				continue;
			}

			$schedule = array();
			$totalEnding = 0.0;
			foreach($rows as $row){
				$subId = (int) $row['subsidiary_id'];
				$name = 'Subsidiary '.$subId;
				if($hasSubsidiaryMaster){
					$sub = $this->db->get_where($this->subsidiaryTable, array('subsidiary_id' => $subId))->row_array();
					if($sub && isset($sub['name'])){
						$name = (string)$sub['name'];
					}
				}
				$beg = $this->getAccountBeginningBalance($brgyId, $year, $a->code, $subId);
				$ending = ((float)$beg['debit'] - (float)$beg['credit']) + ((float)$row['total_debit'] - (float)$row['total_credit']);
				$schedule[] = array(
					'subsidiary_id' => $subId,
					'name' => $name,
					'period_debit' => (float)$row['total_debit'],
					'period_credit' => (float)$row['total_credit'],
					'ending' => $ending
				);
				$totalEnding += $ending;
			}

			$begAcc = $this->getAccountBeginningBalance($brgyId, $year, $a->code, null);
			$begAccNet = (float)$begAcc['debit'] - (float)$begAcc['credit'];
			$this->db->select_sum('jd.debit', 'total_debit');
			$this->db->select_sum('jd.credit', 'total_credit');
			$this->db->from('tbl_jevdata jd');
			$this->db->join('tbl_jev j', 'jd.jev_id = j.jev_id AND jd.jev_no = j.jev_no');
			$this->db->where('j.brgy', $brgyId);
			$this->db->where('jd.acc_code', $a->code);
			$this->db->where('j.jev_date >=', $startDate);
			$this->db->where('j.jev_date <=', $endDate);
			$tot = $this->db->get()->row_array();
			$glEnding = $begAccNet + ((float)($tot['total_debit'] ?? 0) - (float)($tot['total_credit'] ?? 0));
			$diff = $totalEnding - $glEnding;

			usort($schedule, function($x, $y){
				return strcmp((string)$x['name'], (string)$y['name']);
			});

			$sheet = $spreadsheet->getActiveSheet();
			$sheet->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
			$sheet->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
			$sheet->setCellValue('A7', 'Account Name: '.$a->name);
			$sheet->setCellValue('E6', date('Y', strtotime($endDate)));
			$sheet->setCellValue('E7', $a->code);

			$r = 10;
			foreach($schedule as $item){
				$sheet->setCellValue('A'.$r, $item['subsidiary_id']);
				$sheet->setCellValue('B'.$r, $item['name']);
				$sheet->setCellValue('C'.$r, $item['period_debit']);
				$sheet->setCellValue('D'.$r, $item['period_credit']);
				$sheet->setCellValue('E'.$r, $item['ending']);
				$r++;
			}
			$sheet->setCellValue('B'.$r, 'TOTAL ENDING (SS)');
			$sheet->setCellValue('E'.$r, $totalEnding);
			$r += 2;
			$sheet->setCellValue('B'.$r, 'GL ENDING (CONTROL)');
			$sheet->setCellValue('E'.$r, $glEnding);
			$r++;
			$sheet->setCellValue('B'.$r, 'DIFFERENCE (SS - GL)');
			$sheet->setCellValue('E'.$r, $diff);

			$activeSheet++;
		}

		if($activeSheet === 0){
			return $this->respondNoData('No subsidiary schedules available for the selected range.', '/gl');
		}

		$spreadsheet->setActiveSheetIndex(0);
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Subsidiary Schedule_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;
		$writer = new Xlsx($spreadsheet);
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
	}


	public function generateGeneralLedgerAll($startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
		

		$this->db->order_by('code','ASC');
		$ares = $this->db->get('tbl_accounts');
		$accounts = $ares->result();

		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/sl.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
		// Get the active sheet
		
		$activeSheet = 1;
		foreach($accounts as $a){
			
			$newStr = str_replace( array('*', ':', '/', '\\', '?', '[', ']'), ' ', $a->name);

			$clonedWorksheet = clone $spreadsheet->getSheetByName('SL');
			$clonedWorksheet->setTitle(substr($newStr,0,30));
			$spreadsheet->addSheet($clonedWorksheet);

			$spreadsheet->setActiveSheetIndex($activeSheet);
			
		$sqlQuery = "SELECT
        j.jev_date,
        j.jev_no,
		j.particulars,
		j.type,
        jd.debit,
        jd.credit,
		jd.jevdata_id
    FROM
        tbl_jevdata jd
    LEFT JOIN
        tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
    WHERE
        jd.acc_code = ?
        AND j.jev_date BETWEEN ? AND ?
		AND j.brgy = ?
    ORDER BY
        j.jev_date, j.jev_no;
";

		$queryParams = [$a->code, $startDate, $endDate,$_SESSION['currbrgyid']];
		$query = $this->db->query($sqlQuery, $queryParams);
		if($query->num_rows() > 0){
			$ledgerEntries = $query->result();
			// $this->generateGL_file($ledgerEntries,$acc_code,$acc_name,$startDate,$endDate);
		$startYear = date('Y',strtotime($startDate));
		$beg = $this->getAccountBeginningBalance($this->getSelectedBrgyId(), $startYear, $a->code);
		$begbald = $beg['debit'];
		$begbalc = $beg['credit'];


		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();



		$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('C5',$a->name);
		$spreadsheet->getActiveSheet()->setCellValue('G5',date('Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('G6',$a->code);

		$spreadsheet->getActiveSheet()->setCellValue('E10',$begbald);
		$spreadsheet->getActiveSheet()->setCellValue('F10',$begbalc);



		$currentMonth = date('Y-m', strtotime($startDate));
		$rowIndex = 11;
		$firstRow = 11;
		$prevDebitTotalIndex = 10;
		$prevCreditTotalIndex =10;

		// Add data from the $ledgerEntries array
	foreach ($ledgerEntries as $entry) {
		$entryMonth = date('Y-m', strtotime($entry->jev_date));
		// Check if the month has changed
    if ($entryMonth != $currentMonth) {
        // Add a blank row for separation
        $rowIndex++;

        // Add a row for monthly totals
        $sheet->setCellValue('B' . $rowIndex, 'TOTAL THIS MONTH');
        $sheet->setCellValue('E' . $rowIndex, '=SUM(E'.$firstRow.':E' . ($rowIndex - 2) . ')'); // Total Debit
        $sheet->setCellValue('F' . $rowIndex, '=SUM(F'.$firstRow.':F' . ($rowIndex - 2) . ')'); // Total Credit
		$sheet->getStyle('E'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->setCellValue('B' . ($rowIndex+1), 'TOTAL TO DATE');
        $sheet->setCellValue('E' . ($rowIndex+1), '=E'.$prevDebitTotalIndex.'+E' .$rowIndex); // Total Debit
        $sheet->setCellValue('F' .( $rowIndex+1), '=F'.$prevCreditTotalIndex.'+F' .$rowIndex); // Total Credit
		$sheet->setCellValue('G' .( $rowIndex+1), '=E'.($rowIndex+1).'-F' . ($rowIndex+1)); // Total balance

		$sheet->getStyle('E'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		

		$prevDebitTotalIndex = $rowIndex+1;
		$prevCreditTotalIndex = $rowIndex+1;
        // Move to the next row
        $rowIndex+=4;
		$firstRow = $rowIndex;
		 
        // Update the current month
        $currentMonth = $entryMonth;

		$sheet->setCellValue('A' . $rowIndex, $entry->jev_date);
		$sheet->setCellValue('B' . $rowIndex, $entry->particulars);
		$sheet->setCellValue('D' . $rowIndex, $entry->type.' '.substr($entry->jev_no, -3));
		$sheet->setCellValue('E' . $rowIndex, $entry->debit);
		$sheet->setCellValue('F' . $rowIndex, $entry->credit);
		

		
    }else{

		$sheet->setCellValue('A' . $rowIndex, $entry->jev_date);
		$sheet->setCellValue('B' . $rowIndex, $entry->particulars);
		$sheet->setCellValue('D' . $rowIndex, $entry->type.' '.substr($entry->jev_no, -3));
		$sheet->setCellValue('E' . $rowIndex, $entry->debit);
		$sheet->setCellValue('F' . $rowIndex, $entry->credit);
		
		$rowIndex++;
	}

    	
    
		

}
		$rowIndex+=2;
		$sheet->setCellValue('B' . $rowIndex, 'TOTAL THIS MONTH');
		$sheet->setCellValue('E' . $rowIndex, '=SUM(E'.$firstRow.':E' . ($rowIndex - 2) . ')'); // Total Debit
		$sheet->setCellValue('F' . $rowIndex, '=SUM(F'.$firstRow.':F' . ($rowIndex - 2) . ')'); // Total Credit

		$sheet->getStyle('E'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->setCellValue('B' . ($rowIndex+1), 'TOTAL TO DATE');
        $sheet->setCellValue('E' . ($rowIndex+1), '=E'.$prevDebitTotalIndex.'+E' . ($rowIndex)); // Total Debit
        $sheet->setCellValue('F' .( $rowIndex+1), '=F'.$prevCreditTotalIndex.'+F' . ($rowIndex)); // Total Credit
		$sheet->setCellValue('G' .( $rowIndex+1), '=E'.($rowIndex+1).'-F' . ($rowIndex+1)); // Total balance

		$sheet->getStyle('E'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');

		$spreadsheet->getActiveSheet()->getStyle('A10:G'.($rowIndex+1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
		
		$spreadsheet->getActiveSheet()->setCellValue('E'.($rowIndex+4),"Approved by: ");
		$spreadsheet->getActiveSheet()->setCellValue('F'.($rowIndex+6),"Judy G. Parado, CPA");
		$spreadsheet->getActiveSheet()->setCellValue('F'.($rowIndex+7),"Municipal Accountant");

		$spreadsheet->getActiveSheet()->setCellValue('A'.($rowIndex+4),"Prepared by: ");
		$spreadsheet->getActiveSheet()->setCellValue('B'.($rowIndex+6),ucfirst($_SESSION['fname'].' '.ucfirst($_SESSION['lname'])));
		$spreadsheet->getActiveSheet()->setCellValue('B'.($rowIndex+7),$_SESSION['position']);


		

		}else{
			// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();



		$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('C5',$a->name);
		//$spreadsheet->getActiveSheet()->setCellValue('G5',date('Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('F6',$a->code);

		$spreadsheet->getActiveSheet()->setCellValue('E13',"Approved by: ");
		$spreadsheet->getActiveSheet()->setCellValue('F14',"Judy G. Parado, CPA");
		$spreadsheet->getActiveSheet()->setCellValue('F15',"Municipal Accountant");

		$spreadsheet->getActiveSheet()->setCellValue('A13',"Prepared by: ");
		$spreadsheet->getActiveSheet()->setCellValue('B14',ucfirst($_SESSION['fname'].' '.ucfirst($_SESSION['lname'])));
		$spreadsheet->getActiveSheet()->setCellValue('B15',$_SESSION['position']);

		}

		$activeSheet++;
		}
		$spreadsheet->setActiveSheetIndex(1);


		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Subsidiary Ledger_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		$this->addlog("Subsidiary Ledger File",$_SESSION['fname'].' '.$_SESSION['lname']);

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);



	

	}

	
	public function generateGeneralLedger2All($startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
		

		$this->db->order_by('code','ASC');
		$ares = $this->db->get('tbl_accounts');
		$accounts = $ares->result();

		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/gl.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
		// Get the active sheet
		
		$activeSheet = 1;
		foreach($accounts as $a){
			
			$newStr = str_replace( array('*', ':', '/', '\\', '?', '[', ']'), ' ', $a->name);

			$clonedWorksheet = clone $spreadsheet->getSheetByName('GL');
			$clonedWorksheet->setTitle(substr($newStr,0,30));
			$spreadsheet->addSheet($clonedWorksheet);

			$spreadsheet->setActiveSheetIndex($activeSheet);
			
		$sqlQuery = "SELECT
        j.jev_date,
        j.jev_no,
		j.particulars,
		j.type,
        jd.debit,
        jd.credit,
		jd.jevdata_id
    FROM
        tbl_jevdata jd
    LEFT JOIN
        tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
    WHERE
        jd.acc_code = ?
        AND j.jev_date BETWEEN ? AND ?
		AND j.brgy = ?
    ORDER BY
        j.jev_date, j.jev_no;
";

		$queryParams = [$a->code, $startDate, $endDate,$_SESSION['currbrgyid']];
		$query = $this->db->query($sqlQuery, $queryParams);
		if($query->num_rows() > 0){
			$ledgerEntries = $query->result();
			// $this->generateGL_file($ledgerEntries,$acc_code,$acc_name,$startDate,$endDate);
		$startYear = date('Y',strtotime($startDate));
		$beg = $this->getAccountBeginningBalance($this->getSelectedBrgyId(), $startYear, $a->code);
		$begbald = $beg['debit'];
		$begbalc = $beg['credit'];


		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();



		$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('A6','Account Name: '.$a->name);
		$spreadsheet->getActiveSheet()->setCellValue('G5',date('Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('G6',$a->code);

		$spreadsheet->getActiveSheet()->setCellValue('E10',$begbald);
		$spreadsheet->getActiveSheet()->setCellValue('F10',$begbalc);



		$currentMonth = date('Y-m', strtotime($startDate));
		$rowIndex = 11;
		$firstRow = 11;
		$prevDebitTotalIndex = 10;
		$prevCreditTotalIndex =10;

		// Add data from the $ledgerEntries array
	foreach ($ledgerEntries as $entry) {
		$entryMonth = date('Y-m', strtotime($entry->jev_date));
		// Check if the month has changed
    if ($entryMonth != $currentMonth) {
        // Add a blank row for separation
        $rowIndex++;

        // Add a row for monthly totals
        $sheet->setCellValue('B' . $rowIndex, 'TOTAL THIS MONTH');
        $sheet->setCellValue('E' . $rowIndex, '=SUM(E'.$firstRow.':E' . ($rowIndex - 2) . ')'); // Total Debit
        $sheet->setCellValue('F' . $rowIndex, '=SUM(F'.$firstRow.':F' . ($rowIndex - 2) . ')'); // Total Credit
		$sheet->getStyle('E'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->setCellValue('B' . ($rowIndex+1), 'TOTAL TO DATE');
        $sheet->setCellValue('E' . ($rowIndex+1), '=E'.$prevDebitTotalIndex.'+E' .$rowIndex); // Total Debit
        $sheet->setCellValue('F' .( $rowIndex+1), '=F'.$prevCreditTotalIndex.'+F' .$rowIndex); // Total Credit
		$sheet->setCellValue('G' .( $rowIndex+1), '=E'.($rowIndex+1).'-F' . ($rowIndex+1)); // Total balance

		$sheet->getStyle('E'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		

		$prevDebitTotalIndex = $rowIndex+1;
		$prevCreditTotalIndex = $rowIndex+1;
        // Move to the next row
        $rowIndex+=4;
		$firstRow = $rowIndex;
		 
        // Update the current month
        $currentMonth = $entryMonth;

		$sheet->setCellValue('A' . $rowIndex, $entry->jev_date);
		$sheet->setCellValue('B' . $rowIndex, $entry->particulars);
		$sheet->setCellValue('D' . $rowIndex, $entry->type.' '.substr($entry->jev_no, -3));
		$sheet->setCellValue('E' . $rowIndex, $entry->debit);
		$sheet->setCellValue('F' . $rowIndex, $entry->credit);
		

		
    }else{

		$sheet->setCellValue('A' . $rowIndex, $entry->jev_date);
		$sheet->setCellValue('B' . $rowIndex, $entry->particulars);
		$sheet->setCellValue('D' . $rowIndex, $entry->type.' '.substr($entry->jev_no, -3));
		$sheet->setCellValue('E' . $rowIndex, $entry->debit);
		$sheet->setCellValue('F' . $rowIndex, $entry->credit);
		
		$rowIndex++;
	}

    	
    
		

}
		$rowIndex+=2;
		$sheet->setCellValue('B' . $rowIndex, 'TOTAL THIS MONTH');
		$sheet->setCellValue('E' . $rowIndex, '=SUM(E'.$firstRow.':E' . ($rowIndex - 2) . ')'); // Total Debit
		$sheet->setCellValue('F' . $rowIndex, '=SUM(F'.$firstRow.':F' . ($rowIndex - 2) . ')'); // Total Credit

		$sheet->getStyle('E'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->setCellValue('B' . ($rowIndex+1), 'TOTAL TO DATE');
        $sheet->setCellValue('E' . ($rowIndex+1), '=E'.$prevDebitTotalIndex.'+E' . ($rowIndex)); // Total Debit
        $sheet->setCellValue('F' .( $rowIndex+1), '=F'.$prevCreditTotalIndex.'+F' . ($rowIndex)); // Total Credit
		$sheet->setCellValue('G' .( $rowIndex+1), '=E'.($rowIndex+1).'-F' . ($rowIndex+1)); // Total balance

		$sheet->getStyle('E'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');

		$spreadsheet->getActiveSheet()->getStyle('A10:G'.($rowIndex+1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
		
		$spreadsheet->getActiveSheet()->setCellValue('E'.($rowIndex+4),"Approved by: ");
		$spreadsheet->getActiveSheet()->setCellValue('F'.($rowIndex+6),"Judy G. Parado, CPA");
		$spreadsheet->getActiveSheet()->setCellValue('F'.($rowIndex+7),"Municipal Accountant");

		$spreadsheet->getActiveSheet()->setCellValue('A'.($rowIndex+4),"Prepared by: ");
		$spreadsheet->getActiveSheet()->setCellValue('B'.($rowIndex+6),ucfirst($_SESSION['fname'].' '.ucfirst($_SESSION['lname'])));
		$spreadsheet->getActiveSheet()->setCellValue('B'.($rowIndex+7),$_SESSION['position']);


		

		}else{
			// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();



		$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('A6','Account Name: '.$a->name);
		$spreadsheet->getActiveSheet()->setCellValue('G5',date('Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('G6',$a->code);

		$spreadsheet->getActiveSheet()->setCellValue('E13',"Approved by: ");
		$spreadsheet->getActiveSheet()->setCellValue('F14',"Judy G. Parado, CPA");
		$spreadsheet->getActiveSheet()->setCellValue('F15',"Municipal Accountant");

		$spreadsheet->getActiveSheet()->setCellValue('A13',"Prepared by: ");
		$spreadsheet->getActiveSheet()->setCellValue('B14',ucfirst($_SESSION['fname'].' '.ucfirst($_SESSION['lname'])));
		$spreadsheet->getActiveSheet()->setCellValue('B15',$_SESSION['position']);

		}

		$activeSheet++;
		}
		$spreadsheet->setActiveSheetIndex(1);


		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'General Ledger_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		$this->addlog("General Ledger File",$_SESSION['fname'].' '.$_SESSION['lname']);

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);



	

	}

	
	public function generateGeneralLedgerSSAll($startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
		

		$this->db->order_by('code','ASC');
		$ares = $this->db->get('tbl_accounts');
		$accounts = $ares->result();

		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/SS.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
		// Get the active sheet
		
		$activeSheet = 1;
		foreach($accounts as $a){
			
			$newStr = str_replace( array('*', ':', '/', '\\', '?', '[', ']'), ' ', $a->name);

			$clonedWorksheet = clone $spreadsheet->getSheetByName('SS');
			$clonedWorksheet->setTitle(substr($newStr,0,30));
			$spreadsheet->addSheet($clonedWorksheet);

			$spreadsheet->setActiveSheetIndex($activeSheet);
			
		$sqlQuery = "SELECT
       	jd.jevdata_id,
        j.jev_date,
        j.jev_no,
		j.particulars,
		j.type,
		j.payor_payee,
		jd.payee,
		jd.payor,
        jd.debit,
        jd.credit
    FROM
        tbl_jevdata jd
    LEFT JOIN
        tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
    WHERE
        jd.acc_code = ?
        AND j.jev_date BETWEEN ? AND ?
		AND j.brgy = ?
    ORDER BY
        j.jev_date, j.jev_no;
";

		$queryParams = [$a->code, $startDate, $endDate,$_SESSION['currbrgyid']];
		$query = $this->db->query($sqlQuery, $queryParams);
		if($query->num_rows() > 0){
			$ledgerEntries = $query->result();
			// $this->generateGL_file($ledgerEntries,$acc_code,$acc_name,$startDate,$endDate);
		$startYear = date('Y',strtotime($startDate));

		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();



		$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('A7', 'Account Name: '.$a->name);
		$spreadsheet->getActiveSheet()->setCellValue('E6',date('Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('E7',$a->code);



		$currentMonth = date('Y-m', strtotime($startDate));
		

		$brow=10;
		$row = 10;

		// Add data from the $ledgerEntries array
	foreach ($ledgerEntries as $entry) {
		$sheet->setCellValue('A'.$row,$entry->jevdata_id);
		$sheet->setCellValue('B'.$row,$entry->payee.' '.$entry->payor);
		$sheet->setCellValue('D'.$row,$entry->debit);
		$sheet->setCellValue('E'.$row,$entry->credit);

		// Check if the month has change
		$row++;
		}
		$sheet->setCellValue('B'.$row,"Grand Total");
		$sheet->setCellValue('D'.$row,"=SUM(D".$brow.":D".($row-1).")");
		$sheet->setCellValue('E'.$row,"=SUM(E".$brow.":E".($row-1).")");

		$spreadsheet->getActiveSheet()->getStyle('A10:E'.($row+1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
		
		$spreadsheet->getActiveSheet()->setCellValue('D'.($row+4),"Approved by: ");
		$spreadsheet->getActiveSheet()->setCellValue('E'.($row+5),"Judy G. Parado, CPA");
		$spreadsheet->getActiveSheet()->setCellValue('E'.($row+6),"Municipal Accountant");

		$spreadsheet->getActiveSheet()->setCellValue('A'.($row+4),"Prepared by: ");
		$spreadsheet->getActiveSheet()->setCellValue('B'.($row+4),ucfirst($_SESSION['fname'].' '.ucfirst($_SESSION['lname'])));
		$spreadsheet->getActiveSheet()->setCellValue('B'.($row+4),$_SESSION['position']);
		
		

		}else{
			// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();
		$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('A7', 'Account Name: '.$a->name);
		$spreadsheet->getActiveSheet()->setCellValue('E6',date('Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('E7',$a->code);



		$spreadsheet->getActiveSheet()->setCellValue('D13',"Approved by: ");
		$spreadsheet->getActiveSheet()->setCellValue('E14',"Judy G. Parado, CPA");
		$spreadsheet->getActiveSheet()->setCellValue('E15',"Municipal Accountant");

		$spreadsheet->getActiveSheet()->setCellValue('A13',"Prepared by: ");
		$spreadsheet->getActiveSheet()->setCellValue('B14',ucfirst($_SESSION['fname'].' '.ucfirst($_SESSION['lname'])));
		$spreadsheet->getActiveSheet()->setCellValue('B15',$_SESSION['position']);

		}

		$activeSheet++;
		}
		$spreadsheet->setActiveSheetIndex(1);


		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Subsidiary Schedule_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		$this->addlog("Generate Subsidiary Schedule  File",$_SESSION['fname'].' '.$_SESSION['lname']);

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);



	

	}

	public function generateGL_file($result,$acc_code,$acc_name,$startDate,$endDate){
		$startYear = date('Y',strtotime($startDate));
		$this->load->helper('download');
		$this->load->helper('file');
		$beg = $this->getAccountBeginningBalance($this->getSelectedBrgyId(), $startYear, $acc_code);
		$begbald = $beg['debit'];
		$begbalc = $beg['credit'];

		

	
		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/sl.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);


		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();



		$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('C5', 'Account Name: '.$acc_name);
		//$spreadsheet->getActiveSheet()->setCellValue('G5',date('Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('F6',$acc_code);

		$spreadsheet->getActiveSheet()->setCellValue('E10',$begbald);
		$spreadsheet->getActiveSheet()->setCellValue('F10',$begbalc);

		$runningBalance = (float)$begbald - (float)$begbalc;


		$currentMonth = date('Y-m', strtotime($startDate));
		$rowIndex = 11;
		$firstRow = 11;
		$prevDebitTotalIndex = 10;
		$prevCreditTotalIndex =10;

		// Add data from the $ledgerEntries array
	foreach ($result as $entry) {
		$entryMonth = date('Y-m', strtotime($entry->jev_date));
		// Check if the month has changed
    if ($entryMonth != $currentMonth) {
        // Add a blank row for separation
        $rowIndex++;

        // Add a row for monthly totals
        $sheet->setCellValue('B' . $rowIndex, 'TOTAL THIS MONTH');
        $sheet->setCellValue('E' . $rowIndex, '=SUM(E'.$firstRow.':E' . ($rowIndex - 2) . ')'); // Total Debit
        $sheet->setCellValue('F' . $rowIndex, '=SUM(F'.$firstRow.':F' . ($rowIndex - 2) . ')'); // Total Credit
		$sheet->getStyle('E'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->setCellValue('B' . ($rowIndex+1), 'TOTAL TO DATE');
        $sheet->setCellValue('E' . ($rowIndex+1), '=E'.$prevDebitTotalIndex.'+E' .$rowIndex); // Total Debit
        $sheet->setCellValue('F' .( $rowIndex+1), '=F'.$prevCreditTotalIndex.'+F' .$rowIndex); // Total Credit
		$sheet->setCellValue('G' .( $rowIndex+1), '=E'.($rowIndex+1).'-F' . ($rowIndex+1)); // Total balance

		$sheet->getStyle('E'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		

		$prevDebitTotalIndex = $rowIndex+1;
		$prevCreditTotalIndex = $rowIndex+1;
        // Move to the next row
        $rowIndex+=4;
		$firstRow = $rowIndex;
		 
        // Update the current month
        $currentMonth = $entryMonth;

		$sheet->setCellValue('A' . $rowIndex, $entry->jev_date);
		$sheet->setCellValue('B' . $rowIndex, $entry->particulars);
		$sheet->setCellValue('D' . $rowIndex, $entry->type.' '.substr($entry->jev_no, -3));
		$sheet->setCellValue('E' . $rowIndex, $entry->debit);
		$sheet->setCellValue('F' . $rowIndex, $entry->credit);
		$runningBalance += ((float)$entry->debit - (float)$entry->credit);
		$sheet->setCellValue('G' . $rowIndex, $runningBalance);
		

		
    }else{

		$sheet->setCellValue('A' . $rowIndex, $entry->jev_date);
		$sheet->setCellValue('B' . $rowIndex, $entry->particulars);
		$sheet->setCellValue('D' . $rowIndex, $entry->type.' '.substr($entry->jev_no, -3));
		$sheet->setCellValue('E' . $rowIndex, $entry->debit);
		$sheet->setCellValue('F' . $rowIndex, $entry->credit);
		$runningBalance += ((float)$entry->debit - (float)$entry->credit);
		$sheet->setCellValue('G' . $rowIndex, $runningBalance);
		
		$rowIndex++;
	}

    	
    
		

}
		$rowIndex+=2;
		$sheet->setCellValue('B' . $rowIndex, 'TOTAL THIS MONTH');
		$sheet->setCellValue('E' . $rowIndex, '=SUM(E'.$firstRow.':E' . ($rowIndex - 2) . ')'); // Total Debit
		$sheet->setCellValue('F' . $rowIndex, '=SUM(F'.$firstRow.':F' . ($rowIndex - 2) . ')'); // Total Credit

		$sheet->getStyle('E'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->setCellValue('B' . ($rowIndex+1), 'TOTAL TO DATE');
        $sheet->setCellValue('E' . ($rowIndex+1), '=E'.$prevDebitTotalIndex.'+E' . ($rowIndex)); // Total Debit
        $sheet->setCellValue('F' .( $rowIndex+1), '=F'.$prevCreditTotalIndex.'+F' . ($rowIndex)); // Total Credit
		$sheet->setCellValue('G' .( $rowIndex+1), '=E'.($rowIndex+1).'-F' . ($rowIndex+1)); // Total balance

		$sheet->getStyle('E'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');

		$spreadsheet->getActiveSheet()->getStyle('A10:G'.($rowIndex+1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
		

		$spreadsheet->setActiveSheetIndex(0);

		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Subsidiary Ledger_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		$this->addlog("Subsidiary Ledger File",$_SESSION['fname'].' '.$_SESSION['lname']);

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);


	}

	
	public function generateGLS_file($result,$acc_code,$acc_name,$startDate,$endDate){
		$startyear = date('Y',strtotime($startDate));
		$this->load->helper('download');
		$this->load->helper('file');
		

	
		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/ss.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);


		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();



		$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('A7', 'Account Name: '.$acc_name);
		$spreadsheet->getActiveSheet()->setCellValue('E6',date('Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('E7',$acc_code);



	$brow=10;
	$row = 10;
		// Add data from the $ledgerEntries array
	foreach ($result as $entry) {
		$sheet->setCellValue('A'.$row,$entry->jevdata_id);
		$sheet->setCellValue('B'.$row,$entry->payee.' '.$entry->payor);
		$sheet->setCellValue('D'.$row,$entry->debit);
		$sheet->setCellValue('E'.$row,$entry->credit);

		// Check if the month has change
		$row++;
		}
		$sheet->setCellValue('C'.$row,"Grand Total");
		$sheet->setCellValue('D'.$row,"=SUM(D".$brow.":D".($row-1).")");
		$sheet->setCellValue('E'.$row,"=SUM(E".$brow.":E".($row-1).")");
		$spreadsheet->setActiveSheetIndex(0);

		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Subsidiary Schedule_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		$this->addlog("Generate Subsidiary Schedule",$_SESSION['fname'].' '.$_SESSION['lname']);

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);


	}

	
	public function generateGL_file2($result,$acc_code,$acc_name,$startDate,$endDate){
		$startYear = date('Y',strtotime($startDate));
		$this->load->helper('download');
		$this->load->helper('file');
		$beg = $this->getAccountBeginningBalance($this->getSelectedBrgyId(), $startYear, $acc_code);
		$begbald = $beg['debit'];
		$begbalc = $beg['credit'];

		// if($res2->num_rows()>0){
		// 	$this->output
		// ->set_content_type('application/json')
		// ->set_output(json_encode($res2->row_array()));
		// return;
			
		// }

		

	
		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/gl.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);


		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();



		$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('A6', 'Account Name: '.$acc_name);
		$spreadsheet->getActiveSheet()->setCellValue('G5',date('Y', strtotime($endDate)));
		$spreadsheet->getActiveSheet()->setCellValue('G6',$acc_code);

		$spreadsheet->getActiveSheet()->setCellValue('E10',$begbald);
		$spreadsheet->getActiveSheet()->setCellValue('F10',$begbalc);

		$runningBalance = (float)$begbald - (float)$begbalc;


		$currentMonth = date('Y-m', strtotime($startDate));
		$rowIndex = 11;
		$firstRow = 11;
		$prevDebitTotalIndex = 10;
		$prevCreditTotalIndex =10;

		// Add data from the $ledgerEntries array
	foreach ($result as $entry) {
		$entryMonth = date('Y-m', strtotime($entry->jev_date));
		// Check if the month has changed
    if ($entryMonth != $currentMonth) {
        // Add a blank row for separation
        $rowIndex++;

        // Add a row for monthly totals
        $sheet->setCellValue('B' . $rowIndex, 'TOTAL THIS MONTH');
        $sheet->setCellValue('E' . $rowIndex, '=SUM(E'.$firstRow.':E' . ($rowIndex - 2) . ')'); // Total Debit
        $sheet->setCellValue('F' . $rowIndex, '=SUM(F'.$firstRow.':F' . ($rowIndex - 2) . ')'); // Total Credit
		$sheet->getStyle('E'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->setCellValue('B' . ($rowIndex+1), 'TOTAL TO DATE');
        $sheet->setCellValue('E' . ($rowIndex+1), '=E'.$prevDebitTotalIndex.'+E' .$rowIndex); // Total Debit
        $sheet->setCellValue('F' .( $rowIndex+1), '=F'.$prevCreditTotalIndex.'+F' .$rowIndex); // Total Credit
		$sheet->setCellValue('G' .( $rowIndex+1), '=E'.($rowIndex+1).'-F' . ($rowIndex+1)); // Total balance

		$sheet->getStyle('E'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		

		$prevDebitTotalIndex = $rowIndex+1;
		$prevCreditTotalIndex = $rowIndex+1;
        // Move to the next row
        $rowIndex+=4;
		$firstRow = $rowIndex;
		 
        // Update the current month
        $currentMonth = $entryMonth;

		$sheet->setCellValue('A' . $rowIndex, $entry->jev_date);
		$sheet->setCellValue('B' . $rowIndex, $entry->particulars);
		$sheet->setCellValue('D' . $rowIndex, $entry->type.' '.substr($entry->jev_no, -3));
		$sheet->setCellValue('E' . $rowIndex, $entry->debit);
		$sheet->setCellValue('F' . $rowIndex, $entry->credit);
		$runningBalance += ((float)$entry->debit - (float)$entry->credit);
		$sheet->setCellValue('G' . $rowIndex, $runningBalance);
		

		
    }else{

		$sheet->setCellValue('A' . $rowIndex, $entry->jev_date);
		$sheet->setCellValue('B' . $rowIndex, $entry->particulars);
		$sheet->setCellValue('D' . $rowIndex, $entry->type.' '.substr($entry->jev_no, -3));
		$sheet->setCellValue('E' . $rowIndex, $entry->debit);
		$sheet->setCellValue('F' . $rowIndex, $entry->credit);
		$runningBalance += ((float)$entry->debit - (float)$entry->credit);
		$sheet->setCellValue('G' . $rowIndex, $runningBalance);
		
		$rowIndex++;
	}

    	
    
		

}
		$rowIndex+=2;
		$sheet->setCellValue('B' . $rowIndex, 'TOTAL THIS MONTH');
		$sheet->setCellValue('E' . $rowIndex, '=SUM(E'.$firstRow.':E' . ($rowIndex - 2) . ')'); // Total Debit
		$sheet->setCellValue('F' . $rowIndex, '=SUM(F'.$firstRow.':F' . ($rowIndex - 2) . ')'); // Total Credit

		$sheet->getStyle('E'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->setCellValue('B' . ($rowIndex+1), 'TOTAL TO DATE');
        $sheet->setCellValue('E' . ($rowIndex+1), '=E'.$prevDebitTotalIndex.'+E' . ($rowIndex)); // Total Debit
        $sheet->setCellValue('F' .( $rowIndex+1), '=F'.$prevCreditTotalIndex.'+F' . ($rowIndex)); // Total Credit
		$sheet->setCellValue('G' .( $rowIndex+1), '=E'.($rowIndex+1).'-F' . ($rowIndex+1)); // Total balance

		$sheet->getStyle('E'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('F'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
		$sheet->getStyle('G'.($rowIndex+1))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');

		$spreadsheet->getActiveSheet()->getStyle('A10:G'.($rowIndex+1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
		

		$spreadsheet->setActiveSheetIndex(0);

		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'General Ledger_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);


	}

	private function loadJournalReportsConfig(){
		$this->config->load('journal_reports', true);
		$cfg = $this->config->item('journal_reports', 'journal_reports');
		return is_array($cfg) ? $cfg : array();
	}

	private function loadJournalColumnMapConfig(){
		$this->config->load('journal_column_map', true);
		$cfg = $this->config->item('journal_column_map', 'journal_column_map');
		return is_array($cfg) ? $cfg : array();
	}

	/** First digit of account code as integer, or null if empty/invalid. */
	private function jevAccountFirstDigit($accCode){
		$accCode = trim((string) $accCode);
		if($accCode === ''){
			return null;
		}
		if(!ctype_digit($accCode[0])){
			return null;
		}
		return (int) $accCode[0];
	}

	/** First non-empty string among values. */
	private function journalCoalesceField(){
		$args = func_get_args();
		foreach($args as $a){
			if($a === null){
				continue;
			}
			$s = trim((string) $a);
			if($s !== ''){
				return $s;
			}
		}
		return '';
	}

	private function journalApplyCrjLine($sheet, $row, &$newrow, $m, $map){
		$code = isset($m['acc_code']) ? (string) $m['acc_code'] : '';
		$crj = isset($map['crj']) && is_array($map['crj']) ? $map['crj'] : array();
		$exact = isset($crj['exact']) && is_array($crj['exact']) ? $crj['exact'] : array();
		if(isset($exact[$code])){
			$rule = $map['crj']['exact'][$code];
			$amt = ($rule['amount'] === 'debit') ? $m['debit'] : $m['credit'];
			$sheet->setCellValue($rule['col'].$row, $amt);
			return;
		}
		$dig = $this->jevAccountFirstDigit($code);
		$min = isset($crj['liability_first_digit_min']) ? (int) $crj['liability_first_digit_min'] : 2;
		if($dig !== null && $dig >= $min){
			$lc = isset($crj['liability_cols']) && is_array($crj['liability_cols'])
				? $crj['liability_cols']
				: array('acc' => 'J', 'debit' => 'K', 'credit' => 'L');
			$sheet->setCellValue($lc['acc'].($row + $newrow), $m['acc_code']);
			$sheet->setCellValue($lc['debit'].($row + $newrow), $m['debit']);
			$sheet->setCellValue($lc['credit'].($row + $newrow), $m['credit']);
			$newrow++;
			return;
		}
		$oc = isset($crj['other_cols']) && is_array($crj['other_cols'])
			? $crj['other_cols']
			: array('acc' => 'P', 'debit' => 'Q', 'credit' => 'R');
		$sheet->setCellValue($oc['acc'].($row + $newrow), $m['acc_code']);
		$sheet->setCellValue($oc['debit'].($row + $newrow), $m['debit']);
		$sheet->setCellValue($oc['credit'].($row + $newrow), $m['credit']);
		$newrow++;
	}

	private function journalApplyCkdjLine($sheet, $row, &$newrow, $m, $map){
		$code = isset($m['acc_code']) ? (string) $m['acc_code'] : '';
		$ckdj = isset($map['ckdj']) && is_array($map['ckdj']) ? $map['ckdj'] : array();
		$exact = isset($ckdj['exact']) && is_array($ckdj['exact']) ? $ckdj['exact'] : array();
		if(isset($exact[$code])){
			$rule = $exact[$code];
			$amt = ($rule['amount'] === 'debit') ? $m['debit'] : $m['credit'];
			$sheet->setCellValue($rule['col'].$row, $amt);
			if(in_array($code, array('10305020','10305040','10305010','50204020'), true)){
				$newrow++;
			}
			return;
		}
		$nested = isset($ckdj['nested']) && is_array($ckdj['nested']) ? $ckdj['nested'] : array();
		if(isset($nested[$code]) && is_array($nested[$code])){
			foreach($nested[$code] as $rule){
				if($rule['when'] === 'credit_zero' && (float) $m['credit'] == 0){
					$c = $rule['cols'];
					$sheet->setCellValue($c['acc'].($row + $newrow), $m['acc_code']);
					$sheet->setCellValue($c['debit'].($row + $newrow), $m['debit']);
					$sheet->setCellValue($c['credit'].($row + $newrow), $m['credit']);
					$newrow++;
					return;
				}
				if($rule['when'] === 'else'){
					$sheet->setCellValue($rule['col'].$row, $m['credit']);
					return;
				}
			}
		}
		$oc = isset($ckdj['other_cols']) && is_array($ckdj['other_cols'])
			? $ckdj['other_cols']
			: array('acc' => 'L', 'debit' => 'M', 'credit' => 'N');
		$sheet->setCellValue($oc['acc'].($row + $newrow), $m['acc_code']);
		$sheet->setCellValue($oc['debit'].($row + $newrow), $m['debit']);
		$sheet->setCellValue($oc['credit'].($row + $newrow), $m['credit']);
		$newrow++;
	}

	private function journalApplyCsdjLine($sheet, $row, &$newrow, $m, $map){
		$code = isset($m['acc_code']) ? (string) $m['acc_code'] : '';
		$csdj = isset($map['csdj']) && is_array($map['csdj']) ? $map['csdj'] : array();
		$exact = isset($csdj['exact']) && is_array($csdj['exact']) ? $csdj['exact'] : array();
		if(isset($exact[$code])){
			$rule = $exact[$code];
			$amt = ($rule['amount'] === 'debit') ? $m['debit'] : $m['credit'];
			$sheet->setCellValue($rule['col'].$row, $amt);
			return;
		}
		$nested = isset($csdj['nested']) && is_array($csdj['nested']) ? $csdj['nested'] : array();
		if(isset($nested[$code]) && is_array($nested[$code])){
			foreach($nested[$code] as $rule){
				if($rule['when'] === 'credit_zero' && (float) $m['credit'] == 0){
					$c = $rule['cols'];
					$sheet->setCellValue($c['acc'].($row + $newrow), $m['acc_code']);
					$sheet->setCellValue($c['debit'].($row + $newrow), $m['debit']);
					$sheet->setCellValue($c['credit'].($row + $newrow), $m['credit']);
					$newrow++;
					return;
				}
				if($rule['when'] === 'debit_zero' && (float) $m['debit'] == 0){
					$c = $rule['cols'];
					$sheet->setCellValue($c['acc'].($row + $newrow), $m['acc_code']);
					$sheet->setCellValue($c['debit'].($row + $newrow), $m['debit']);
					$sheet->setCellValue($c['credit'].($row + $newrow), $m['credit']);
					$newrow++;
					return;
				}
				if($rule['when'] === 'else'){
					$fld = isset($rule['amount']) ? $rule['amount'] : 'debit';
					$amt = ($fld === 'credit') ? $m['credit'] : $m['debit'];
					$sheet->setCellValue($rule['col'].$row, $amt);
					$newrow++;
					return;
				}
			}
		}
		$oc = isset($csdj['other_cols']) && is_array($csdj['other_cols'])
			? $csdj['other_cols']
			: array('acc' => 'L', 'debit' => 'M', 'credit' => 'N');
		$sheet->setCellValue($oc['acc'].($row + $newrow), $m['acc_code']);
		$sheet->setCellValue($oc['debit'].($row + $newrow), $m['debit']);
		$sheet->setCellValue($oc['credit'].($row + $newrow), $m['credit']);
		$newrow++;
	}

	public function generateGJ(){
		$startDate = date('Y-m-d', strtotime($this->input->post('sdate')));
		$endDate = date('Y-m-d', strtotime($this->input->post('edate')));
		$jtype = $this->getType($this->input->post('j_type'));

		$includePending = ($this->input->post('include_pending') === '1');
		if(!$includePending){
			$this->db->where('j.status', 1);
		}

		$this->db->select('
			j.jev_id, j.jev_no, j.jev_date, j.type, j.particulars, j.payor_payee, j.fund, j.status,
			jd.jevdata_id, jd.acc_code, jd.acc_title, jd.debit, jd.credit,
			jd.or_num, jd.or_date, jd.payor, jd.payee, jd.dv_no, jd.check_no, jd.check_date, jd.bank_acct
		');
		$this->db->from('tbl_jev j');
		$this->db->join('tbl_jevdata jd', 'j.jev_no = jd.jev_no AND j.jev_id = jd.jev_id');
		$this->db->where('j.brgy', $this->input->post('brgy'));
		$this->db->where('j.jev_date >=', $startDate);
		$this->db->where('j.jev_date <=', $endDate);
		$this->db->where('j.type', $jtype);
		$this->db->order_by('j.jev_date,j.jev_no, jd.acc_code');

				
		$query = $this->db->get();
		
	if($query->num_rows()>0){
		$result = $query->result();

		
// Initialize an array to store the organized JEV data
$organizedJevData = array();

foreach ($result as $row) {
    // Extract JEV data
    $jevNo = $row->jev_no;
	$parts = $row->particulars;
	$payor_payee = $row->payor_payee;
	$type = $row->type;
    $jevDate = $row->jev_date;
    $accCode = $row->acc_code;
    $accTitle = $row->acc_title;
    $debit = $row->debit;
    $credit = $row->credit;

    // Check if the date already exists in the array
    if (!isset($organizedJevData[$jevDate])) {
        // If not, create a new entry
        $organizedJevData[$jevDate] = array();
    }

    // Check if the JEV number already exists in the date entry
    if (!isset($organizedJevData[$jevDate][$jevNo])) {
        // If not, create a new JEV entry — header refs filled from first line, then merged
        $organizedJevData[$jevDate][$jevNo] = array(
            'jev_no' => $jevNo,
			'parts'=>$parts,
			'payor_payee'=>$payor_payee,
			'type'=>$type,
			'fund' => isset($row->fund) ? $row->fund : '',
			'status' => isset($row->status) ? $row->status : 0,
			'or_num' => '',
			'or_date' => '',
			'payor_line' => '',
			'payee_line' => '',
			'dv_no' => '',
			'check_no' => '',
			'check_date' => '',
			'bank_acct' => '',
            'jev_data' => array(),
        );
    }

	$h =& $organizedJevData[$jevDate][$jevNo];
	$h['or_num'] = $this->journalCoalesceField($h['or_num'], isset($row->or_num) ? $row->or_num : '');
	$od = isset($row->or_date) ? $row->or_date : '';
	if($od !== '' && $od !== '0000-00-00'){
		$h['or_date'] = $this->journalCoalesceField($h['or_date'], $od);
	}
	$h['payor_line'] = $this->journalCoalesceField($h['payor_line'], isset($row->payor) ? $row->payor : '');
	$h['payee_line'] = $this->journalCoalesceField($h['payee_line'], isset($row->payee) ? $row->payee : '');
	$h['dv_no'] = $this->journalCoalesceField($h['dv_no'], isset($row->dv_no) ? $row->dv_no : '');
	$h['check_no'] = $this->journalCoalesceField($h['check_no'], isset($row->check_no) ? $row->check_no : '');
	$cd = isset($row->check_date) ? $row->check_date : '';
	if($cd !== '' && $cd !== '0000-00-00'){
		$h['check_date'] = $this->journalCoalesceField($h['check_date'], $cd);
	}
	$h['bank_acct'] = $this->journalCoalesceField($h['bank_acct'], isset($row->bank_acct) ? $row->bank_acct : '');

    // Add JEV data to the array
    $organizedJevData[$jevDate][$jevNo]['jev_data'][] = array(
        'acc_code' => $accCode,
        'acc_title' => $accTitle,
        'debit' => $debit,
        'credit' => $credit,
    );
}

	//  $this->output
	// 	->set_content_type('application/json')
	// 	->set_output(json_encode($organizedJevData));
	if($jtype == "GJ"){
		
	//  $this->output
	// 	->set_content_type('application/json')
	// 	->set_output(json_encode($organizedJevData));
		$this->generategj_file($organizedJevData,$startDate,$endDate);
	}elseif($jtype == "COL"){
		$this->generatecrj_file($organizedJevData,$startDate,$endDate);
	}elseif($jtype == "CKD"){
			
	 //$this->output
		// ->set_content_type('application/json')
		// ->set_output(json_encode($organizedJevData));
		$this->generateckdj_file($organizedJevData,$startDate,$endDate);
	}else{
		$this->generatecsdj_file($organizedJevData,$startDate,$endDate);
	}
}else{
	return $this->respondNoData('No Data Available for the specific Data range!', '/journals');
}

	
	
		
	


	}

	public function previewGJ(){
		$this->enablePreviewMode();
		return $this->generateGJ();
	}

	
	public function generatecsdj_file($result,$startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
	
		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/csd.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

		// Assuming $result contains your trial balance result (array or object)
		// Replace this with your method to get the trial balance result

		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		// $spreadsheet->getActiveSheet()->setCellValue('A7', 'Fund: General Fund');

		$colMap = $this->loadJournalColumnMapConfig();

		$row = 11;
		
		foreach($result as $date => $jev_no){
			
			
			foreach($jev_no as $k => $v){
				

				
				$sheet->setCellValue('B' . $row,$date);
				$sheet->setCellValue('C' . $row,$v['jev_no']);
				$sheet->setCellValue('E' . $row,$v['payor_payee']);
				$disbRef = '';
				if(!empty($v['dv_no'])){
					$disbRef .= 'DV: '.$v['dv_no'];
				}
				if(!empty($v['check_no'])){
					$disbRef .= ($disbRef !== '' ? ' | ' : '').'Chk: '.$v['check_no'];
				}
				if(!empty($v['check_date']) && $v['check_date'] !== '0000-00-00'){
					$disbRef .= ($disbRef !== '' ? ' ' : '').date('m/d/Y', strtotime($v['check_date']));
				}
				if(!empty($v['bank_acct'])){
					$disbRef .= ($disbRef !== '' ? ' | ' : '').'Bank: '.$v['bank_acct'];
				}
				$sheet->setCellValue('D' . $row,$disbRef);
				// $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
								
				
				
					$newrow=0;
					foreach($v['jev_data'] as $l =>$m){
						$this->journalApplyCsdjLine($sheet, $row, $newrow, $m, $colMap);
				}
				$row +=$newrow;
				

				// $sheet->mergeCells('A'.$main_row.':A'.($main_row+($count-1)));
				// $sheet->setCellValue('A' . $main_row,date('m/d/Y', strtotime($date)));
				// $sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true);
				// $sheet->getStyle('A')->getAlignment()->setHorizontal('center');
				// $sheet->getStyle('A')->getAlignment()->setVertical('center');
				
				
				//$main_row +=$count;
			}
			
		}
		$sheet->setCellValue('E'.$row,'TOTAL');
		
		$sheet->setCellValue('G'.$row,'=SUM(G11:G'.($row-1).')');
		$sheet->setCellValue('H'.$row,'=SUM(H11:H'.($row-1).')');
		$sheet->setCellValue('I'.$row,'=SUM(I11:I'.($row-1).')');
		$sheet->setCellValue('J'.$row,'=SUM(J11:J'.($row-1).')');
		$sheet->setCellValue('L'.$row,'=SUM(L11:L'.($row-1).')');
		$sheet->setCellValue('M'.$row,'=SUM(M11:M'.($row-1).')');

		$spreadsheet->getActiveSheet()->getStyle('B11:M'.($row-1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
			
		// Save the Excel file with the updated data
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Cash Disbursement Journal_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);


}

	
	
	public function generateckdj_file($result,$startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
	
		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/chk.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

		// Assuming $result contains your trial balance result (array or object)
		// Replace this with your method to get the trial balance result

		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A7', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		// $spreadsheet->getActiveSheet()->setCellValue('A7', 'Fund: General Fund');

		$colMap = $this->loadJournalColumnMapConfig();

		$row = 11;
		
		foreach($result as $date => $jev_no){
			
			
			foreach($jev_no as $k => $v){
				

				
				$sheet->setCellValue('B' . $row,$date);
				$sheet->setCellValue('C' . $row,$v['jev_no']);
				$sheet->setCellValue('E' . $row,$v['payor_payee']);
				$chkRef = '';
				if(!empty($v['dv_no'])){
					$chkRef .= 'DV: '.$v['dv_no'];
				}
				if(!empty($v['check_no'])){
					$chkRef .= ($chkRef !== '' ? ' | ' : '').'Chk: '.$v['check_no'];
				}
				if(!empty($v['check_date']) && $v['check_date'] !== '0000-00-00'){
					$chkRef .= ($chkRef !== '' ? ' ' : '').date('m/d/Y', strtotime($v['check_date']));
				}
				if(!empty($v['bank_acct'])){
					$chkRef .= ($chkRef !== '' ? ' | ' : '').'Bank: '.$v['bank_acct'];
				}
				$sheet->setCellValue('D' . $row,$chkRef);
				// $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
								
				
				
					$newrow=0;
					foreach($v['jev_data'] as $l =>$m){
						$this->journalApplyCkdjLine($sheet, $row, $newrow, $m, $colMap);
				}
				$row +=$newrow;
				

				// $sheet->mergeCells('A'.$main_row.':A'.($main_row+($count-1)));
				// $sheet->setCellValue('A' . $main_row,date('m/d/Y', strtotime($date)));
				// $sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true);
				// $sheet->getStyle('A')->getAlignment()->setHorizontal('center');
				// $sheet->getStyle('A')->getAlignment()->setVertical('center');
				
				
				//$main_row +=$count;
			}
			
		}
		$sheet->setCellValue('E'.$row,'TOTAL');
		$sheet->setCellValue('F'.$row,'=SUM(F11:F'.($row-1).')');
		$sheet->setCellValue('G'.$row,'=SUM(G11:G'.($row-1).')');
		$sheet->setCellValue('H'.$row,'=SUM(H11:H'.($row-1).')');
		$sheet->setCellValue('I'.$row,'=SUM(I11:I'.($row-1).')');
		$sheet->setCellValue('J'.$row,'=SUM(J11:J'.($row-1).')');
		$sheet->setCellValue('K'.$row,'=SUM(K11:K'.($row-1).')');
		$sheet->setCellValue('M'.$row,'=SUM(M11:M'.($row-1).')');
		$sheet->setCellValue('N'.$row,'=SUM(N11:N'.($row-1).')');

		$spreadsheet->getActiveSheet()->getStyle('B11:N'.($row))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
			
		// Save the Excel file with the updated data
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = ' Check Disbursement Journal_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);


		
	}
	

	
	public function generatecrj_file($result,$startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
	
		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/col.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

		// Assuming $result contains your trial balance result (array or object)
		// Replace this with your method to get the trial balance result

		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		// $spreadsheet->getActiveSheet()->setCellValue('A7', 'Fund: General Fund');

		$colMap = $this->loadJournalColumnMapConfig();

		$row = 12;
		
		foreach($result as $date => $jev_no){
			
			
			foreach($jev_no as $k => $v){
				

				
				$sheet->setCellValue('B' . $row,$date);
				$sheet->setCellValue('C' . $row,$v['jev_no']);
				$sheet->setCellValue('E' . $row,$v['payor_payee']);
				$crjRef = '';
				if(!empty($v['or_num'])){
					$crjRef .= 'OR: '.$v['or_num'];
				}
				if(!empty($v['or_date']) && $v['or_date'] !== '0000-00-00'){
					$crjRef .= ($crjRef !== '' ? ' ' : '').date('m/d/Y', strtotime($v['or_date']));
				}
				$sheet->setCellValue('D' . $row,$crjRef);
				// $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
								
				
				
					$newrow=0;
					foreach($v['jev_data'] as $l =>$m){
						$this->journalApplyCrjLine($sheet, $row, $newrow, $m, $colMap);
				}
				$row +=$newrow;
				//$row++;

				// $sheet->mergeCells('A'.$main_row.':A'.($main_row+($count-1)));
				// $sheet->setCellValue('A' . $main_row,date('m/d/Y', strtotime($date)));
				// $sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true);
				// $sheet->getStyle('A')->getAlignment()->setHorizontal('center');
				// $sheet->getStyle('A')->getAlignment()->setVertical('center');
				
				
				//$main_row +=$count;
			}
			
		}

		$sheet->setCellValue('E'.$row,'TOTAL');
		$sheet->setCellValue('F'.$row,'=SUM(F12:F'.($row-1).')');
		$sheet->setCellValue('G'.$row,'=SUM(G12:G'.($row-1).')');
		$sheet->setCellValue('H'.$row,'=SUM(H12:H'.($row-1).')');
		$sheet->setCellValue('I'.$row,'=SUM(I12:I'.($row-1).')');
		
		$sheet->setCellValue('K'.$row,'=SUM(K12:K'.($row-1).')');
		$sheet->setCellValue('L'.$row,'=SUM(L12:L'.($row-1).')');
		$sheet->setCellValue('M'.$row,'=SUM(M12:M'.($row-1).')');
		$sheet->setCellValue('N'.$row,'=SUM(N12:N'.($row-1).')');
		$sheet->setCellValue('O'.$row,'=SUM(O12:O'.($row-1).')');
		$sheet->setCellValue('Q'.$row,'=SUM(Q12:Q'.($row-1).')');
		$sheet->setCellValue('R'.$row,'=SUM(R12:R'.($row-1).')');

		$spreadsheet->getActiveSheet()->getStyle('B11:R'.($row))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
			
		// Save the Excel file with the updated data
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Cash Receipts Journal_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);


}


	public function generategj_file($result,$startDate,$endDate){
			$this->load->helper('download');
			$this->load->helper('file');
		
			// Load PhpSpreadsheet library
			
	
			// Load the existing template
			$templatePath = FCPATH .'assets/templates/gj.xlsx';
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
	
			// Assuming $result contains your trial balance result (array or object)
			// Replace this with your method to get the trial balance result
	
			// Get the active sheet
			$sheet = $spreadsheet->getActiveSheet();
			$jr = $this->loadJournalReportsConfig();
			$spreadsheet->getActiveSheet()->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
			$spreadsheet->getActiveSheet()->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
			$funds = array();
			foreach($result as $d => $jevList){
				foreach($jevList as $vv){
					$fn = isset($vv['fund']) ? trim((string) $vv['fund']) : '';
					if($fn !== ''){
						$funds[$fn] = true;
					}
				}
			}
			if(count($funds) === 1){
				$fk = array_keys($funds);
				$fundLine = 'Fund: '.$fk[0];
			}elseif(count($funds) > 1){
				$fundLine = 'Fund: (multiple)';
			}else{
				$fundLine = 'Fund: '.(isset($jr['fund_label_default']) ? $jr['fund_label_default'] : 'General Fund');
			}
			$spreadsheet->getActiveSheet()->setCellValue('A7', $fundLine);

			$row = 12;
			$main_row=12;
			foreach($result as $date => $jev_no){
				$main_merge=0;
				$count=0;
				foreach($jev_no as $k => $v){
					$count += count($v['jev_data']);
					
					
					// $sheet->setCellValue('C' . $row,$v['parts']);
					// $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
						$merge = 0;
						foreach($v['jev_data'] as $l =>$m){
							$sheet->setCellValue('D' . ($row+$merge),$m['acc_code']);
							// $sheet->setCellValue('E' . ($row+$merge),$merge);
							$sheet->setCellValue('F' . ($row+$merge),$m['debit']);
							$sheet->setCellValue('G' . ($row+$merge),$m['credit']);
							// $sheet->setCellValue('H' . ($row+$merge),$merge);
							
							$merge++;
							$main_merge++;
							} 
								$sheet->mergeCells('B'.$row.':B'.($row+($merge-1)));
								$sheet->setCellValue('B' . $row,$v['jev_no']);
								$sheet->getStyle('B'.$row)->getAlignment()->setWrapText(true);
								$sheet->getStyle('B')->getAlignment()->setHorizontal('center');
								$sheet->getStyle('B')->getAlignment()->setVertical('center');
								$sheet->mergeCells('C'.$row.':C'.($row+($merge-1)));
								$sheet->setCellValue('C' . $row,$v['parts']);
								$sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
								$sheet->getStyle('C')->getAlignment()->setHorizontal('center');
								$sheet->getStyle('C')->getAlignment()->setVertical('center');

								// $sheet->setCellValue('A' . $row,date('m/d/Y', strtotime($date)));
								$row = $row + $merge;
								$main_merge++;
					}

					$sheet->mergeCells('A'.$main_row.':A'.($main_row+($count-1)));
					$sheet->setCellValue('A' . $main_row,date('m/d/Y', strtotime($date)));
					$sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true);
					$sheet->getStyle('A')->getAlignment()->setHorizontal('center');
					$sheet->getStyle('A')->getAlignment()->setVertical('center');
					
					
					$main_row +=$count;
				}
				$spreadsheet->getActiveSheet()->getStyle('A12:G'.($main_row-1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
				$spreadsheet->getActiveSheet()->getStyle('F'.($main_row).':G'.($main_row))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
				$sheet->setCellValue('F'.($main_row),'=SUM(F12:F'.($main_row-1).')');
				$sheet->setCellValue('G'.($main_row),'=F'.$main_row);
				$sheet->getStyle('F'.($main_row))->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
				$sheet->getStyle('G'.($main_row))->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
				$sheet->getStyle('F'.($main_row))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00');
				$sheet->getStyle('G'.($main_row))->getFill()
				->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
				->getStartColor()
				->setARGB('FFFF00'); 


				$sheet->mergeCells('A'.($main_row+3).':B'.($main_row+3));
				$sheet->setCellValue('A'.($main_row+3),'Prepared By:');
				$sheet->getStyle('A'.($main_row+3))->getAlignment()->setHorizontal('left');

				$sheet->mergeCells('A'.($main_row+5).':B'.($main_row+5));
				$sheet->setCellValue('A'.($main_row+5),$_SESSION['fname'].' '.$_SESSION['lname']);
				$sheet->getStyle('A'.($main_row+5))
				->getFont()
				->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE);
				$sheet->mergeCells('A'.($main_row+6).':B'.($main_row+6));
				$sheet->setCellValue('A'.($main_row+6),$_SESSION['position']);

				$sheet->getStyle('A'.($main_row+5).':B'.($main_row+6))->getAlignment()->setHorizontal('center');
				$sheet->getStyle('A'.($main_row+5).':B'.($main_row+6))->getAlignment()->setVertical('center');


				$sheet->mergeCells('D'.($main_row+3).':F'.($main_row+3));
				$sheet->setCellValue('D'.($main_row+3),'Approved By:');

				$sheet->mergeCells('F'.($main_row+5).':G'.($main_row+5));
				$sheet->setCellValue('F'.($main_row+5), isset($jr['approved_by_name']) ? $jr['approved_by_name'] : '');
				$sheet->getStyle('F'.($main_row+5))
				->getFont()
				->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE);
				$sheet->mergeCells('F'.($main_row+6).':G'.($main_row+6));
				$sheet->setCellValue('F'.($main_row+6), isset($jr['approved_by_title']) ? $jr['approved_by_title'] : '');

				$sheet->getStyle('F'.($main_row+5).':G'.($main_row+6))->getAlignment()->setHorizontal('center');
				$sheet->getStyle('F'.($main_row+5).':G'.($main_row+6))->getAlignment()->setVertical('center');

				// $sheet->setCellvalue('C',$row,$jev_no->parts);
				// $a=0;
				// // foreach($jev_no->jev_data as $i){
				// // 	$sheet->setCellvalue('D',$row+$a,$i->acc_code);
				// // 	$sheet->setCellvalue('F',$row+$a,$i->debit);
				// // 	$sheet->setCellvalue('G',$row+$a,$i->credit);
				// // 	$a++;
				// // }
				
			

			// Save the Excel file with the updated data
			$currentDateTime = date('F-Y', strtotime($endDate));
			$excelFileName = 'General Journal_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
			$excelFilePath = FCPATH . 'temp/' . $excelFileName;

			 // Set the response headers for Excel file download
			//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
			//  header('Cache-Control: max-age=0');

			$writer = new Xlsx($spreadsheet);
			// $writer->save('php://output');
			$writer->save($excelFilePath);
			return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);


	}

	public function checkYears($syear,$eyear){
		// $startYear = date('Y',strtotime($syear));
		// $endYear = date('Y',strtotime($eyear));
		// echo $syear.' '.$eyear;
		
		// Assuming $startYear and $endYear represent the range of years (e.g., 2022 and 2024)
		$sqlQuery = "
		SELECT
			GROUP_CONCAT(DISTINCT YEAR(j.jev_date)) AS years_present
		FROM
			tbl_jev j
		WHERE
			j.brgy = ?
		";

		$queryParams = $_SESSION['currbrgyid'];
		$query = $this->db->query($sqlQuery, $queryParams);

		$result = $query->row();

		if (!empty($result->years_present)) {
		$yearsPresent = explode(',', $result->years_present);
		$allYears = range($syear, $eyear);
		
		$missingYears = array_diff($allYears, $yearsPresent);
		

		if (empty($missingYears)) {
			return true;
		} else {
			return $this->respondNoData('No Data Available for Year/s. '.implode(', ',$missingYears).' Please select a different  range!', '/fs_page');
		}
		} else {
			return $this->respondNoData('Selected Year range have no Data in database!!', '/fs_page');
		}
	}

	
	public function generateFS(){
		$ftype = $this->input->post('fs_type');

		if($ftype == "custom"){
		
		// Convert the date strings to 'YYYY-MM-DD' format
		$startDate = date('Y-m-d', strtotime($this->input->post('sdate')));
		$endDate = date('Y-m-d', strtotime($this->input->post('edate')));
		

		//if($ftype == "FPE"){
			$this->generatefp_file($startDate,$endDate);
		// }elseif($ftype=="FPO"){
		// 	$this->generatefpos_file('',$startDate,$endDate);
		// }elseif($ftype=="CF"){
		// 	$this->generatecf_file('',$startDate,$endDate);
		// }else{
		// 	$this->generatecae_file('',$startDate,$endDate);
		// }
		}else{
		$fs_year = $this->input->post('fss_year');
		$fe_year = $this->input->post('fse_year');
		

		//check if all years exists
		$year_result = $this->checkYears($fs_year,$fe_year);

		if($year_result){
				// Convert the string years to integers
		$syear = (int)$fs_year;
		$eyear = (int)$fe_year;

		$c = 0;		
		for ($receivedYear = $syear; $receivedYear <= $eyear; $receivedYear++) {
			// Create start date (January 1st of the received year)
			$startDateString = $receivedYear . '-01-01';
			$startDateTimestamp = date('Y-m-d',strtotime($startDateString));

			// Create end date (December 31st of the received year)
			$endDateString = $receivedYear . '-12-31';
			$endDateTimestamp = date('Y-m-d',strtotime($endDateString));

			$this->generatefpc_file($c,$startDateTimestamp,$endDateTimestamp);

			$c++;
			
		}

	}




		}

	
	// if($data->num_rows()>0){
	// 	$this->output
	// 	->set_content_type('application/json')
	// 	->set_output(json_encode($data->result()));
	 

	// }else{
	// 	$this->session->set_flashdata('error', 'No Data Available for the specific Data range!');
	// 	redirect('/fs_page');
	// }
	
	// 	 $this->output
	// ->set_content_type('application/json')
	// ->set_output(json_encode($result));


}

public function previewFS(){
	$this->enablePreviewMode();
	return $this->generateFS();
}

//generate rrr


public function generateRRR(){
	
	// Convert the date strings to 'YYYY-MM-DD' format
	$startDate = date('Y-m-d', strtotime($this->input->post('sdate')));
	$endDate = date('Y-m-d', strtotime($this->input->post('edate')));
	
	//$this->getrrr_data($startDate,$endDate);
	
	$this->generaterrr_file($startDate,$endDate);
	
	

}


public function generateAG(){
	$sRaw = trim((string)$this->input->post('sdate'));
	$eRaw = trim((string)$this->input->post('edate'));
	if($sRaw === '' || $eRaw === ''){
		$this->session->set_flashdata('error', 'Please select a date range (choose Type e.g. Monthly, or Custom dates).');
		redirect('aging_page');
		return;
	}
	$tsStart = strtotime($sRaw);
	$tsEnd = strtotime($eRaw);
	if($tsStart === false || $tsEnd === false){
		$this->session->set_flashdata('error', 'Invalid start or end date.');
		redirect('aging_page');
		return;
	}
	$startDate = date('Y-m-d', $tsStart);
	$endDate = date('Y-m-d', $tsEnd);
	$this->generateag_file($startDate, $endDate);
}


public function generateag_file($startDate,$endDate){
	$this->load->helper('download');
	$this->load->helper('file');
	
	$this->config->load('aging_report', true);
	$ar = $this->config->item('aging_report');
	$acStart = (is_array($ar) && !empty($ar['acc_code_start'])) ? trim((string)$ar['acc_code_start']) : '10305010';
	$acEnd = (is_array($ar) && !empty($ar['acc_code_end'])) ? trim((string)$ar['acc_code_end']) : '10305040';

	$result = $this->getaging_data($acStart, $acEnd, $startDate, $endDate);

	$templatePath = FCPATH.'assets/templates/aging.xlsx';
	if(is_file($templatePath)){
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
	}else{
		$spreadsheet = new Spreadsheet();
		$sheetStub = $spreadsheet->getActiveSheet();
		$sheetStub->setTitle('Aging');
		$sheetStub->setCellValue('A1', 'AGING SCHEDULE OF UNLIQUIDATED CASH ADVANCES');
		$sheetStub->mergeCells('A1:L1');
		$sheetStub->fromArray(array(array(
			'Account Title','Payee','Particulars','Check No.','Check Date','Amount',
			'0-30 days','31-90 days','91-365 days','2nd year','3rd year','Beyond 3 years'
		)), null, 'A12');
	}

	// Assuming $result contains your trial balance result (array or object)
	// Replace this with your method to get the trial balance result

	// Get the active sheet
	$sheet = $spreadsheet->getActiveSheet();
	
				
	// Set the name of the barangay in the merged cell (A1 to C1)
	
	$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
	$spreadsheet->getActiveSheet()->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
	// $spreadsheet->getActiveSheet()->setCellValue('A31',ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
	// $spreadsheet->getActiveSheet()->setCellValue('A32',$_SESSION['position']);
	
	$row = 13;

	foreach($result as $item){
		$dateForAging = !empty($item->check_date) ? $item->check_date : (isset($item->jev_date) ? $item->jev_date : null);
		$checkTs = $dateForAging ? strtotime($dateForAging) : false;
		if($checkTs === false){
			$checkTs = strtotime($endDate);
		}
		$current_date = time();
		$difference = $current_date - $checkTs;
		$days_difference = (int)floor($difference / (60 * 60 * 24));
		$sheet->setCellValue('A'.$row,$item->acc_title);
		$sheet->setCellValue('B'.$row,$item->payee);
		$sheet->setCellValue('C'.$row,$item->particulars);
		$sheet->setCellValue('D'.$row,$item->check_no);
		$sheet->setCellValue('E'.$row,$dateForAging ? date('Y-m-d', strtotime($dateForAging)) : '');
		$sheet->setCellValue('F'.$row,$item->debit);

		// Categorize the aging
		if ($days_difference <= 30) {
			$sheet->setCellValue('G'.$row,$item->debit);
		} elseif ($days_difference <= 90) {
			$sheet->setCellValue('H'.$row,$item->debit);
		} elseif ($days_difference <= 365) {
			$sheet->setCellValue('I'.$row,$item->debit);
		} elseif ($days_difference <= 365 * 2) {
			$sheet->setCellValue('J'.$row,$item->debit);
		} elseif($days_difference <= 365 * 3) {
			$sheet->setCellValue('K'.$row,$item->debit);
		}else{
			$sheet->setCellValue('L'.$row,$item->debit);
		}
		$row++;
	}

	$spreadsheet->getActiveSheet()->setCellValue('A'.($row+2),"Prepared By: ");
	$spreadsheet->getActiveSheet()->setCellValue('B'.($row+4),ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
	$spreadsheet->getActiveSheet()->setCellValue('B'.($row+5),$_SESSION['position']);

	$spreadsheet->getActiveSheet()->setCellValue('H'.($row+2),"Certified By: ");
	$spreadsheet->getActiveSheet()->setCellValue('I'.($row+4),"Judy G. Parado, CPA");
	$spreadsheet->getActiveSheet()->setCellValue('I'.($row+5),"Municipal Accountant");


	// Save the Excel file with the updated data
	$currentDateTime = date('F-Y', strtotime($endDate));
	$excelFileName = 'Aging Schedule of Cash Advances ' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
	$excelFilePath = FCPATH . 'temp/' . $excelFileName;

	 // Set the response headers for Excel file download
	//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
	//  header('Cache-Control: max-age=0');

	$writer = new Xlsx($spreadsheet);
	// $writer->save('php://output');
	$writer->save($excelFilePath);
	return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
	


	
}


//aging

public function getaging_data($ac_start,$ac_end,$startDate,$endDate){
	/*
	 * As-of aging: include all cash-advance lines booked through the report END date for this barangay.
	 * Do NOT use jev_date BETWEEN start and end only — that hides older unliquidated advances when the
	 * user picks a short period (e.g. one month) with no new DV activity.
	 */
	$brgyId = isset($_SESSION['currbrgyid']) ? (int)$_SESSION['currbrgyid'] : 0;
	$sql = "SELECT
	jd.debit,
	jd.credit,
	jd.check_no,
	jd.check_date,
	jd.payee,
	jd.payor,
	jd.acc_title,
	jd.acc_code,
	j.particulars,
	j.jev_date
FROM
	tbl_jevdata jd
INNER JOIN
	tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
WHERE
	j.brgy = ?
	AND j.jev_date IS NOT NULL
	AND j.jev_date <= ?
	AND TRIM(jd.acc_code) >= ?
	AND TRIM(jd.acc_code) <= ?";

	$queryParams = array($brgyId, $endDate, trim((string)$ac_start), trim((string)$ac_end));
	$query = $this->db->query($sql, $queryParams);
	if($query->num_rows() > 0){
		return $query->result();
	}
	$this->session->set_flashdata('error', 'No Data Available for the selected cut-off. '
		.'Confirm barangay, JEV lines use account codes between '.$ac_start.' and '.$ac_end.', and JEV date is on or before '.$endDate.'.');
	redirect('/aging_page');
}


//end aging


public function generateSBA(){
	
	// Convert the date strings to 'YYYY-MM-DD' format
	$startDate = date('Y-m-d', strtotime($this->input->post('sdate')));
	$endDate = date('Y-m-d', strtotime($this->input->post('edate')));
	
	//$this->getrrr_data($startDate,$endDate);
	
	$this->generatesba_file($startDate,$endDate);
	
	

}





public function generatesba_file($startDate,$endDate){
	$this->load->helper('download');
	$this->load->helper('file');
	
	//get data
	// $result =$this->getsba_data($startDate,$endDate);
	
	// $this->output
	// ->set_content_type('application/json')
	// ->set_output(json_encode($result));


	// Load PhpSpreadsheet library
	

	// Load the existing template
	$templatePath = FCPATH .'assets/templates/sba.xlsx';
	$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

	// Assuming $result contains your trial balance result (array or object)
	// Replace this with your method to get the trial balance result

	// Get the active sheet
	$sheet = $spreadsheet->getActiveSheet();
	$bal = 0.0;
				
	// Set the name of the barangay in the merged cell (A1 to C1)
	
	$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
	$spreadsheet->getActiveSheet()->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
	$spreadsheet->getActiveSheet()->setCellValue('A31',ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
	$spreadsheet->getActiveSheet()->setCellValue('A32',$_SESSION['position']);
	
	//tax revenue
	$bal = $this->getsba_data("40101020","40106050",$startDate,$endDate);
	$sheet->setCellvalue('E11',$bal);
	//Services Business
	$bal = $this->getsba_data("40201010","40202990",$startDate,$endDate);
	$sheet->setCellvalue('E12',$bal);
	// Subsidy
	$bal = $this->getsba_data("40301010","40302050",$startDate,$endDate);
	$sheet->setCellvalue('E14',$bal);
	//Gains
	$bal = $this->getsba_data("40501010","40501990",$startDate,$endDate);
	$sheet->setCellvalue('E15',$bal);
	//Others
	$bal = $this->getsba_data("40601010","40601020",$startDate,$endDate);
	$sheet->setCellvalue('E16',$bal);
	//personall services
	$bal = $this->getsba_data("50101010","50104990",$startDate,$endDate);
	$sheet->setCellvalue('E20',$bal);
	//Maintenance
	$bal =$this->getsba_data("50201010","50299990",$startDate,$endDate);
	$sheet->setCellvalue('E21',$bal);	
	//capital outlay

	//Financial Expenses
	$bal = $this->getsba_data("50301010","50301990",$startDate,$endDate);
	$sheet->setCellvalue('E23',$bal);
	
	//others
	$bal = $this->getsba_data("50401010","50505010",$startDate,$endDate);
	$sheet->setCellvalue('E24',$bal);
	


	// Save the Excel file with the updated data
	$currentDateTime = date('F-Y', strtotime($endDate));
	$excelFileName = 'Statement of Comparison of Budget and Actual Amounts ' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
	$excelFilePath = FCPATH . 'temp/' . $excelFileName;

	 // Set the response headers for Excel file download
	//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
	//  header('Cache-Control: max-age=0');

	$writer = new Xlsx($spreadsheet);
	// $writer->save('php://output');
	$writer->save($excelFilePath);
	return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
	


	
}

//sba

public function getsba_data($ac_start,$ac_end,$startDate,$endDate){
	$sql = "SELECT
	COALESCE(SUM(jd.debit), 0) AS total_debit,
	COALESCE(SUM(jd.credit), 0) AS total_credit,
	ABS(COALESCE(SUM(jd.debit - jd.credit), 0)) AS net_balance
FROM
	tbl_jevdata jd
LEFT JOIN
	tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
WHERE
	(j.jev_date BETWEEN ? AND ? OR j.jev_date IS NULL)
	AND j.brgy = ?
	AND jd.acc_code BETWEEN ? AND ?";
	
$queryParams = [$startDate, $endDate,$_SESSION['currbrgyid'],$ac_start,$ac_end];
$query = $this->db->query($sql, $queryParams);
if($query->num_rows() == 1){
	return $result = $query->row_array()['net_balance'];
	// $this->output
	// ->set_content_type('application/json')
	// ->set_output(json_encode($query->result()));
	


}else{
	return 0;
}

}


//end sba





public function generaterrr_file($startDate,$endDate){
	$this->load->helper('download');
	$this->load->helper('file');
	
	//get data
	$result =$this->getrrr_data($startDate,$endDate);
	
	// $this->output
	// ->set_content_type('application/json')
	// ->set_output(json_encode($result));


	// Load PhpSpreadsheet library
	

	// Load the existing template
	$templatePath = FCPATH .'assets/templates/rrr.xlsx';
	$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

	// Assuming $result contains your trial balance result (array or object)
	// Replace this with your method to get the trial balance result

	// Get the active sheet
	$sheet = $spreadsheet->getActiveSheet();

				
	// Set the name of the barangay in the merged cell (A1 to C1)
	
	$spreadsheet->getActiveSheet()->setCellValue('A3', 'Barangay ' . $_SESSION['currbrgy']);
	$spreadsheet->getActiveSheet()->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
	$spreadsheet->getActiveSheet()->setCellValue('A80',ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
	$spreadsheet->getActiveSheet()->setCellValue('A81',$_SESSION['position']);
	$columnIndex = 5;
	
	//Create a map to store database results by account code
	$resultMap = array();
	foreach ($result as $item) {
		
		$accountCode = $item->acc_code;
		$balance = $item->net_balance;
		$resultMap[$accountCode] = array('bal' => $balance);
	}

				
	//Iterate through the Excel file rows once
	$row = 11;
	$end_row=74;
	while ($row <= $end_row) {
		$accountCode = $sheet->getCell('C' . $row)->getValue();

		if (!empty($accountCode)){
			if (isset($resultMap[$accountCode])) {
			
				$bal = $resultMap[$accountCode]['bal'];
				

				// Update the debit and credit columns in the corresponding row
				
				$sheet->setCellValue('E' . $row, $bal);
				
				
				
			}else{
				$sheet->setCellValue('E' . $row, 0);
				
				
			}
			

		}
		
		// Check if the account code exists in the result map
		

		$row++;
	}

	$sheet->removeColumnByIndex(8);
		
	

	// Save the Excel file with the updated data
	$currentDateTime = date('F-Y', strtotime($endDate));
	$excelFileName = 'Report on Revenue and Receipts ' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
	$excelFilePath = FCPATH . 'temp/' . $excelFileName;

	 // Set the response headers for Excel file download
	//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
	//  header('Cache-Control: max-age=0');

	$writer = new Xlsx($spreadsheet);
	// $writer->save('php://output');
	$writer->save($excelFilePath);
	return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
	


	
}



//end

//functions fs

public function generatefpc_file($count,$startDate,$endDate){
	$this->load->helper('download');
	$this->load->helper('file');
	$brgyId = $this->getSelectedBrgyId();
	
	//get data
	$result = $this->getfp_data($startDate,$endDate);
	$result2 = $this->getfpos_data($startDate,$endDate);
	if($result === false || $result2 === false){
		return;
	}
	$bal_res = $this->getbegbalYear($brgyId,date('Y', strtotime($startDate)));
	// $this->output
	// ->set_content_type('application/json')
	// ->set_output(json_encode($data));


	// Load PhpSpreadsheet library
	

	// Load the existing template
	$templatePath = FCPATH .'assets/templates/fpc.xlsx';
	$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

	// Assuming $result contains your trial balance result (array or object)
	// Replace this with your method to get the trial balance result

	// Get the active sheet
	$sheet = $spreadsheet->getActiveSheet();

				
	// Set the name of the barangay in the merged cell (A1 to C1)
	
	$spreadsheet->getActiveSheet()->setCellValue('A9', 'Barangay ' . $_SESSION['currbrgy']);
	$spreadsheet->getActiveSheet()->setCellValue('A12', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
	// $spreadsheet->getActiveSheet()->setCellValue('A215',ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
	// $spreadsheet->getActiveSheet()->setCellValue('A216',$_SESSION['position']);
	$columnIndex = 5;
	$columnLetter =Coordinate::stringFromColumnIndex($columnIndex);
	// Set a value in the current column (e.g., A1, B1, C1)
    $sheet->setCellValueByColumnAndRow($columnIndex+$count, 13, date('Y', strtotime($endDate)));
	//$sheet->setCellValue('F13',date('Y', strtotime($endDate)));
	
					
	//Create a map to store database results by account code
	$resultMap = array();
	foreach ($result as $item) {
		
		$accountCode = $item->acc_code;
		$balance = $item->net_balance;
		$resultMap[$accountCode] = array('bal' => $balance);
	}

				
	//Iterate through the Excel file rows once
	$row = 17;
	$end_row=331;
	while ($row <= $end_row) {
		$accountCode = $sheet->getCell('O' . $row)->getValue();

		if (!empty($accountCode)){
			if (isset($resultMap[$accountCode])) {
			
				$bal = $resultMap[$accountCode]['bal'];
				

				// Update the debit and credit columns in the corresponding row
				
				//$sheet->setCellValue('F' . $row, $bal);
				$sheet->setCellValueByColumnAndRow($columnIndex+$count, $row, $bal);
				
			}else{
				//$sheet->setCellValue('F' . $row, 0);
				$sheet->setCellValueByColumnAndRow($columnIndex+$count, $row, 0);
			}
			

		}
		
		// Check if the account code exists in the result map
		

		$row++;
	}
		
	// Set active sheet index to 2 (indexing is zero-based)
	$spreadsheet->setActiveSheetIndex(2);

	// Get the active sheet
	$activeSheet = $spreadsheet->getActiveSheet();
	// //get 3rd sheet
	// $sheet3 = $spreadsheet->getSheet(2);
	$activeSheet->setCellValue('A8', 'Barangay ' . $_SESSION['currbrgy']);
	$activeSheet->setCellValue('A11', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));

	$columnIndex2 = 5;
	$columnLetter = Coordinate::stringFromColumnIndex($columnIndex2);
	// Set a value in the current column (e.g., A1, B1, C1)
    $sheet->setCellValueByColumnAndRow($columnIndex2+$count, 12, date('Y', strtotime($endDate)));
	//$sheet->setCellValue('F13',date('Y', strtotime($endDate)));
	

	//$activeSheet->setCellValue('F12',date('Y', strtotime($endDate)));

	$resultMap2 = array();
	foreach ($result2 as $item) {
		
		$accountCode = $item->acc_code;
		$balance = $item->net_balance;
		$resultMap2[$accountCode] = array('bal' => $balance);
	}

				
	//Iterate through the Excel file rows once
	$row = 17;
	$end_row=553;
	while ($row <= $end_row) {
		$accountCode = $activeSheet->getCell('O' . $row)->getValue();

		if (!empty($accountCode)){
			if (isset($resultMap2[$accountCode])) {
			
				$bal = $resultMap2[$accountCode]['bal'];
				

				// Update the debit and credit columns in the corresponding row
				
				$sheet->setCellValueByColumnAndRow($columnIndex2+$count, $row, $bal);
				
			}else{
				$sheet->setCellValueByColumnAndRow($columnIndex2+$count, $row, 0);
			}
			

		}
		
		// Check if the account code exists in the result map
		

		$row++;
	}


	//get 4th sheet
	$spreadsheet->setActiveSheetIndex(3);
	$activeSheet = $spreadsheet->getActiveSheet();

	$activeSheet->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
	$activeSheet->setCellValue('A8', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));

	$columnIndex3 = 4;
	$columnLetter = Coordinate::stringFromColumnIndex($columnIndex3);
	// Set a value in the current column (e.g., A1, B1, C1)
    $sheet->setCellValueByColumnAndRow($columnIndex3+$count, 9, date('Y', strtotime($endDate)));
	//$activeSheet->setCellValue('D9',date('Y', strtotime($endDate)));


	$spreadsheet->setActiveSheetIndex(4);
	$activeSheet = $spreadsheet->getActiveSheet(4);

	$activeSheet->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
	$activeSheet->setCellValue('A7', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));

	$columnIndex4 = 5;
	$columnLetter = Coordinate::stringFromColumnIndex($columnIndex4);
	// Set a value in the current column (e.g., A1, B1, C1)
    $sheet->setCellValueByColumnAndRow($columnIndex4+$count, 8, date('Y', strtotime($endDate)));
	$sheet->setCellValueByColumnAndRow($columnIndex4+$count, 54, $bal_res);
	//$activeSheet->setCellValue('F8',date('Y', strtotime($endDate)));

	$spreadsheet->setActiveSheetIndex(5);
	$activeSheet = $spreadsheet->getActiveSheet();

	$activeSheet->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
	$activeSheet->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
	
	$columnIndex5 = 8;
	$columnLetter = Coordinate::stringFromColumnIndex($columnIndex5);
	// Set a value in the current column (e.g., A1, B1, C1)
    $sheet->setCellValueByColumnAndRow($columnIndex5+$count, 13, date('Y', strtotime($endDate)));
	//$activeSheet->setCellValue('H13',date('Y', strtotime($endDate)));
	$activeSheet->setCellValue('A14',"Beginning Balance as of ".date('F j, Y', strtotime($startDate)));
	$activeSheet->setCellValue('A24',"Ending Balance as of ".date('F j, Y', strtotime($endDate)));
	
	$spreadsheet->setActiveSheetIndex(0);
	

	// Save the Excel file with the updated data
	$currentDateTime = date('F-Y', strtotime($endDate));
	$excelFileName = 'Comparative Financial Statements ' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
	$excelFilePath = FCPATH . 'temp/' . $excelFileName;

	 // Set the response headers for Excel file download
	//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
	//  header('Cache-Control: max-age=0');

	$writer = new Xlsx($spreadsheet);
	// $writer->save('php://output');
	$writer->save($excelFilePath);
	return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
				
	// Set a session flashdata with success message
	// $this->session->set_flashdata('success_message', 'Successfully downloaded the Trial Balance');
	// echo '<script>
		
	// 	setTimeout(function() {
	// 		window.location.href = "/tb";
	// 	}, 2000);
	// </script>';
// 	redirect('/tb_page');
// 	echo '<script>
	
//     setTimeout(function() {
//         window.location.href = "/tb";
//     }, 2000);
// </script>';


	
}




//end functions

	public function generateTB(){
		$startDate = date('Y-m-d', strtotime($this->input->post('sdate')));
		$endDate = date('Y-m-d', strtotime($this->input->post('edate')));
		$brgyId = (int) $this->input->post('brgy');

		$result = $this->buildTrialBalanceTotalsWithCarry($brgyId, $startDate, $endDate);

		if(!empty($result)){
			$this->generatetb_file($result,$startDate,$endDate);
		}else{
			return $this->respondNoData('No Data Available for the specific Data range!', '/tb_page');
		}
	}

	public function previewTB(){
		$this->enablePreviewMode();
		return $this->generateTB();
	}

	private function buildTrialBalanceTotalsWithCarry($brgyId, $startDate, $endDate){
		$selectedStart = new DateTime($startDate);
		$selectedEnd = new DateTime($endDate);

		$selectedStartMonth = $selectedStart->format('Y-m-01');
		$selectedEndMonth = $selectedEnd->format('Y-m-01');
		$processStart = $selectedStart->format('Y') . '-01-01';

		$monthlyBase = array();

		$seedEnd = new DateTime($selectedStartMonth);
		$seedEnd->modify('-1 day');
		if($seedEnd->format('Y-m-d') >= $processStart){
			$seedRows = $this->getMonthlyJevTotalsByAccount($brgyId, $processStart, $seedEnd->format('Y-m-d'));
			$this->mergeMonthlyRows($monthlyBase, $seedRows);
		}

		$rangeRows = $this->getMonthlyJevTotalsByAccount($brgyId, $startDate, $endDate);
		$this->mergeMonthlyRows($monthlyBase, $rangeRows);

		$beginningBalanceEligibleCodes = array('10102020', '30101010');
		$yearlyBeginningBalances = $this->getYearlyBeginningBalances(
			$brgyId,
			(int) $selectedStart->format('Y'),
			(int) $selectedEnd->format('Y'),
			$beginningBalanceEligibleCodes
		);
		$monthsToProcess = $this->buildMonthKeys($processStart, $selectedEndMonth);

		$processedMonthlyLedger = array();
		$monthlyCarry = array();
		$cashInBankCode = '10102020';
		$governmentEquityCode = '30101010';

		foreach($monthsToProcess as $monthKey){
			$year = substr($monthKey, 0, 4);
			$month = substr($monthKey, 5, 2);
			$monthLedger = isset($monthlyBase[$monthKey]) ? $monthlyBase[$monthKey] : array();

			if($month === '01' && isset($yearlyBeginningBalances[$year])){
				$yearBalances = $yearlyBeginningBalances[$year];

				$cashDebitBeg = isset($yearBalances[$cashInBankCode]) ? (float) $yearBalances[$cashInBankCode]['debit'] : 0.0;
				$cashCreditBeg = isset($yearBalances[$cashInBankCode]) ? (float) $yearBalances[$cashInBankCode]['credit'] : 0.0;

				if(isset($yearBalances[$cashInBankCode])){
					$this->addAccountAmount($monthLedger, $cashInBankCode, $cashDebitBeg, $cashCreditBeg);
				}

				// Force Government Equity (30101010) January beginning balance to mirror
				// Cash in Bank (10102020) so the two are always equal at year start,
				// regardless of the actual 30101010 value stored in tbl_begbal.
				if($cashDebitBeg != 0.0 || $cashCreditBeg != 0.0){
					$this->addAccountAmount($monthLedger, $governmentEquityCode, $cashCreditBeg, $cashDebitBeg);
				}
			}

			if(isset($monthlyCarry[$monthKey])){
				foreach($monthlyCarry[$monthKey] as $accountCode => $amounts){
					$this->addAccountAmount($monthLedger, $accountCode, $amounts['debit'], $amounts['credit']);
				}
			}

			$processedMonthlyLedger[$monthKey] = $monthLedger;

			$cashDebit = isset($monthLedger[$cashInBankCode]) ? (float)$monthLedger[$cashInBankCode]['debit'] : 0.0;
			$cashCredit = isset($monthLedger[$cashInBankCode]) ? (float)$monthLedger[$cashInBankCode]['credit'] : 0.0;
			$net = $cashDebit - $cashCredit;

			if($net != 0){
				$nextMonth = new DateTime($monthKey);
				$nextMonth->modify('+1 month');
				$nextMonthKey = $nextMonth->format('Y-m-01');
				if(!isset($monthlyCarry[$nextMonthKey])){
					$monthlyCarry[$nextMonthKey] = array();
				}

				if($net > 0){
					$this->addAccountAmount($monthlyCarry[$nextMonthKey], $cashInBankCode, $net, 0);
					$this->addAccountAmount($monthlyCarry[$nextMonthKey], $governmentEquityCode, 0, $net);
				}else{
					$forwardAmount = abs($net);
					$this->addAccountAmount($monthlyCarry[$nextMonthKey], $cashInBankCode, 0, $forwardAmount);
					$this->addAccountAmount($monthlyCarry[$nextMonthKey], $governmentEquityCode, $forwardAmount, 0);
				}
			}
		}

		$finalTotals = array();
		foreach($processedMonthlyLedger as $monthKey => $monthLedger){
			if($monthKey < $selectedStartMonth || $monthKey > $selectedEndMonth){
				continue;
			}

			foreach($monthLedger as $accountCode => $amounts){
				$this->addAccountAmount($finalTotals, $accountCode, $amounts['debit'], $amounts['credit']);
			}
		}

		ksort($finalTotals);
		$result = array();
		foreach($finalTotals as $accountCode => $amounts){
			if((float)$amounts['debit'] == 0.0 && (float)$amounts['credit'] == 0.0){
				continue;
			}

			$result[] = (object) array(
				'code' => $accountCode,
				'total_debit' => $amounts['debit'],
				'total_credit' => $amounts['credit']
			);
		}

		return $result;
	}

	private function getMonthlyJevTotalsByAccount($brgyId, $startDate, $endDate){
		$this->db->select('YEAR(j.jev_date) AS year, MONTH(j.jev_date) AS month, jd.acc_code AS acc_code');
		$this->db->select_sum('jd.debit', 'total_debit');
		$this->db->select_sum('jd.credit', 'total_credit');
		$this->db->from('tbl_jevdata jd');
		$this->db->join('tbl_jev j', 'jd.jev_id = j.jev_id AND jd.jev_no = j.jev_no');
		$this->db->where('j.brgy', $brgyId);
		$this->db->where('j.jev_date >=', $startDate);
		$this->db->where('j.jev_date <=', $endDate);
		$this->db->group_by('YEAR(j.jev_date), MONTH(j.jev_date), jd.acc_code');

		return $this->db->get()->result_array();
	}

	private function getYearlyBeginningBalances($brgyId, $startYear, $endYear, $accountCodes = array()){
		$this->db->select('year, acc_code');
		$this->db->select_sum('debit', 'total_debit');
		$this->db->select_sum('credit', 'total_credit');
		$this->db->from('tbl_begbal');
		$this->db->where('brgy_id', $brgyId);
		$this->db->where('year >=', $startYear);
		$this->db->where('year <=', $endYear);
		if(!empty($accountCodes)){
			$this->db->where_in('acc_code', $accountCodes);
		}
		$this->db->group_by('year, acc_code');

		$rows = $this->db->get()->result_array();
		$balances = array();
		foreach($rows as $row){
			$year = (string)$row['year'];
			$accountCode = $row['acc_code'];
			if(!isset($balances[$year])){
				$balances[$year] = array();
			}

			$balances[$year][$accountCode] = array(
				'debit' => (float)$row['total_debit'],
				'credit' => (float)$row['total_credit']
			);
		}

		return $balances;
	}

	private function getAccountBeginningBalance($brgyId, $year, $accountCode, $subsidiaryId = null){
		$brgyId = (int) $brgyId;
		$year = (int) $year;
		$accountCode = (string) $accountCode;

		if(!$this->db->table_exists('tbl_begbal')){
			return array('debit' => 0.0, 'credit' => 0.0);
		}

		$hasYear = $this->db->field_exists('year', 'tbl_begbal');
		$hasBrgy = $this->db->field_exists('brgy_id', 'tbl_begbal');
		$hasBalDate = $this->db->field_exists('bal_date', 'tbl_begbal');

		$this->db->select_sum('debit', 'total_debit');
		$this->db->select_sum('credit', 'total_credit');
		$this->db->from('tbl_begbal');
		$this->db->where('acc_code', $accountCode);
		if($hasBrgy){
			$this->db->where('brgy_id', $brgyId);
		}
		if($subsidiaryId !== null && $this->db->field_exists('subsidiary_id', 'tbl_begbal')){
			$this->db->where('subsidiary_id', (int)$subsidiaryId);
		}

		if($hasYear){
			$this->db->where('year', $year);
		}else if($hasBalDate){
			$this->db->where('YEAR(bal_date)', $year);
		}else{
			return array('debit' => 0.0, 'credit' => 0.0);
		}

		$row = $this->db->get()->row_array();
		return array(
			'debit' => isset($row['total_debit']) ? (float) $row['total_debit'] : 0.0,
			'credit' => isset($row['total_credit']) ? (float) $row['total_credit'] : 0.0
		);
	}

	private function mergeMonthlyRows(&$monthlyBase, $rows){
		foreach($rows as $row){
			$monthKey = sprintf('%04d-%02d-01', (int)$row['year'], (int)$row['month']);
			if(!isset($monthlyBase[$monthKey])){
				$monthlyBase[$monthKey] = array();
			}

			$this->addAccountAmount(
				$monthlyBase[$monthKey],
				$row['acc_code'],
				(float)$row['total_debit'],
				(float)$row['total_credit']
			);
		}
	}

	private function addAccountAmount(&$ledger, $accountCode, $debit, $credit){
		if(!isset($ledger[$accountCode])){
			$ledger[$accountCode] = array(
				'debit' => 0.0,
				'credit' => 0.0
			);
		}

		$ledger[$accountCode]['debit'] += (float)$debit;
		$ledger[$accountCode]['credit'] += (float)$credit;
	}

	private function buildMonthKeys($startMonth, $endMonth){
		$months = array();
		$cursor = new DateTime($startMonth);
		$end = new DateTime($endMonth);

		while($cursor->getTimestamp() <= $end->getTimestamp()){
			$months[] = $cursor->format('Y-m-01');
			$cursor->modify('+1 month');
		}

		return $months;
	}

	
	public function generatefs_file($result,$startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
	
		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/fs.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

		// Assuming $result contains your trial balance result (array or object)
		// Replace this with your method to get the trial balance result

		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();

					
		// Set the name of the barangay in the merged cell (A1 to C1)
		
		// $spreadsheet->getActiveSheet()->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		// $spreadsheet->getActiveSheet()->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		// $spreadsheet->getActiveSheet()->setCellValue('A215',ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
		// $spreadsheet->getActiveSheet()->setCellValue('A216',$_SESSION['position']);


					
		// Create a map to store database results by account code
		// $resultMap = array();
		// foreach ($result as $item) {
			
		// 	$accountCode = $item->code;
		// 	$debit = $item->total_debit;
		// 	$credit = $item->total_credit;
		// 	$resultMap[$accountCode] = array('debit' => $debit, 'credit' => $credit);
		// }

					
		// Iterate through the Excel file rows once
		// $row = 11;
		// while ($sheet->getCell('B' . $row)->getValue() !== null) {
		// 	$accountCode = $sheet->getCell('B' . $row)->getValue();
			
		// 	// Check if the account code exists in the result map
		// 	if (isset($resultMap[$accountCode])) {
				
		// 		$debit = $resultMap[$accountCode]['debit'];
		// 		$credit = $resultMap[$accountCode]['credit'];

		// 		// Update the debit and credit columns in the corresponding row
				
		// 		$sheet->setCellValue('C' . $row, $debit);
		// 		$sheet->setCellValue('D' . $row, $credit);
		// 	}

		// 	$row++;
		// }
			

		// Update data starting from the 11th row
		// $row = 11;
		// foreach ($result as $item) {
		// 	$sheet->setCellValue('A' . $row, $item->account_name);
		// 	$sheet->setCellValue('B' . $row, $item->account_code);
		// 	$sheet->setCellValue('C' . $row, $item->total_debit);
		// 	$sheet->setCellValue('D' . $row, $item->total_credit);
		// 	$row++;
		// }

		// Auto-size columns for better readability
		

		// Save the Excel file with the updated data
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Financial Statement_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
					
		// Set a session flashdata with success message
		// $this->session->set_flashdata('success_message', 'Successfully downloaded the Trial Balance');
		// echo '<script>
			
		// 	setTimeout(function() {
		// 		window.location.href = "/tb";
		// 	}, 2000);
		// </script>';
	// 	redirect('/tb_page');
	// 	echo '<script>
		
	//     setTimeout(function() {
	//         window.location.href = "/tb";
	//     }, 2000);
	// </script>';


		
	}

	
	public function getrrr_data($startDate,$endDate){
		$sql = "SELECT
        a.code AS acc_code,
        COALESCE(SUM(jd.debit), 0) AS total_debit,
        COALESCE(SUM(jd.credit), 0) AS total_credit,
        ABS(COALESCE(SUM(jd.debit - jd.credit), 0)) AS net_balance
    FROM
        tbl_accounts a
    LEFT JOIN
        tbl_jevdata jd ON a.code = jd.acc_code
    LEFT JOIN
        tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
    WHERE
        (j.jev_date BETWEEN ? AND ? OR j.jev_date IS NULL)
        AND j.brgy = ?
        AND a.code BETWEEN '40101050' AND '40601010'
    GROUP BY
        a.code
    ORDER BY
        a.code";

		
$queryParams = [$startDate, $endDate,$_SESSION['currbrgyid']];
$query = $this->db->query($sql, $queryParams);
	if($query->num_rows()>0){
		return $result = $query->result();
		// $this->output
		// ->set_content_type('application/json')
		// ->set_output(json_encode($query->result()));
		
	

	}else{
		$this->session->set_flashdata('error', 'No Data Available for the specific Data range!');
		redirect('/rrr_page');
	}

	}


	public function getfp_data($startDate,$endDate){
		$brgyId = $this->getSelectedBrgyId();
		if($brgyId <= 0){
			return $this->respondNoData('No barangay selected for report preview.', '/fs_page');
		}
		$sql = "SELECT
        a.code AS acc_code,
        COALESCE(SUM(jd.debit), 0) AS total_debit,
        COALESCE(SUM(jd.credit), 0) AS total_credit,
        ABS(COALESCE(SUM(jd.debit - jd.credit), 0)) AS net_balance
    FROM
        tbl_accounts a
    LEFT JOIN
        tbl_jevdata jd ON a.code = jd.acc_code
    LEFT JOIN
        tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
    WHERE
        (j.jev_date BETWEEN ? AND ? OR j.jev_date IS NULL)
        AND j.brgy = ?
        AND a.code BETWEEN '40101020' AND '50504990'
    GROUP BY
        a.code
    ORDER BY
        a.code";

		
$queryParams = [$startDate, $endDate,$brgyId];
$query = $this->db->query($sql, $queryParams);
	if($query->num_rows()>0){
		return $result = $query->result();
	

	}else{
		return $this->respondNoData('No Data Available for the specific Data range!', '/fs_page');
	}

	}

	
	public function getfpos_data($startDate,$endDate){
		$brgyId = $this->getSelectedBrgyId();
		if($brgyId <= 0){
			return $this->respondNoData('No barangay selected for report preview.', '/fs_page');
		}
		$sql = "SELECT
        a.code AS acc_code,
        COALESCE(SUM(jd.debit), 0) AS total_debit,
        COALESCE(SUM(jd.credit), 0) AS total_credit,
        ABS(COALESCE(SUM(jd.debit - jd.credit), 0)) AS net_balance
    FROM
        tbl_accounts a
    LEFT JOIN
        tbl_jevdata jd ON a.code = jd.acc_code
    LEFT JOIN
        tbl_jev j ON jd.jev_no = j.jev_no AND jd.jev_id = j.jev_id
    WHERE
        (j.jev_date BETWEEN ? AND ? OR j.jev_date IS NULL)
        AND j.brgy = ?
        AND a.code BETWEEN '10101010' AND '30101010'
    GROUP BY
        a.code
    ORDER BY
        a.code";

		
$queryParams = [$startDate, $endDate,$brgyId];
$query = $this->db->query($sql, $queryParams);
if($query->num_rows()>0){
	return $result = $query->result();


}else{
	return $this->respondNoData('No Data Available for the specific Data range!', '/fs_page');
}

	}

	public function getBegBal($acc_code,$syear){
		$year = date("Y",strtotime($syear));
		$brgyId = $this->getSelectedBrgyId();
		if($brgyId <= 0){
			return 0;
		}

		$sql = "SELECT debit,credit,
    IFNULL(debit, 0) - IFNULL(credit, 0) AS debit_credit_difference 
FROM tbl_begbal 
WHERE acc_code = ? AND year = ? AND brgy_id = ?;";
	
	$queryParams = [$acc_code, $year,$brgyId];
	$query = $this->db->query($sql, $queryParams);
		if($query->num_rows()>0){
			$result = $query->row_array();
		return [$result['debit_credit_difference'],$result['debit'],$result['credit']];
	
		}else{
			return 0;
		}
	}

	
	public function generatefp_file($startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
		$brgyId = $this->getSelectedBrgyId();
		if($brgyId <= 0){
			return $this->respondNoData('No barangay selected for report preview.', '/fs_page');
		}
		
		//get data
		$result = $this->getfp_data($startDate,$endDate);
		$result2 = $this->getfpos_data($startDate,$endDate);
		if($result === false || $result2 === false){
			return;
		}
		$bal_res = $this->getbegbalYear($brgyId,date('Y', strtotime($startDate)));
		$cashBegBal = $this->getbegbalCashYear($brgyId, date('Y', strtotime($startDate)));
		// return $this->output
		// ->set_content_type('application/json')
		// ->set_output(json_encode($bal_res));


		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/fp.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

		// Assuming $result contains your trial balance result (array or object)
		// Replace this with your method to get the trial balance result

		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();

					
		// Set the name of the barangay in the merged cell (A1 to C1)
		
		$spreadsheet->getActiveSheet()->setCellValue('A9', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A12', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		// $spreadsheet->getActiveSheet()->setCellValue('A215',ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
		// $spreadsheet->getActiveSheet()->setCellValue('A216',$_SESSION['position']);

		$sheet->setCellValue('F13',date('Y', strtotime($endDate)));
		
						
		//Create a map to store database results by account code
		$resultMap = array();
		foreach ($result as $item) {
			
			$accountCode = $item->acc_code;
			$balance = $item->net_balance;
			$resultMap[$accountCode] = array('bal' => $balance);
		}

					
		//Iterate through the Excel file rows once
		$row = 17;
		$end_row=331;
		while ($row <= $end_row) {
			$accountCode = $sheet->getCell('O' . $row)->getValue();

			if (!empty($accountCode)){
				if (isset($resultMap[$accountCode])) {
				
					$bal = $resultMap[$accountCode]['bal'];
					
	
					// Update the debit and credit columns in the corresponding row
					
					$sheet->setCellValue('F' . $row, $bal);
					
				}else{
					$sheet->setCellValue('F' . $row, 0);
				}
				

			}
			
			// Check if the account code exists in the result map
			

			$row++;
		}
			
		// Set active sheet index to 2 (indexing is zero-based)
		$spreadsheet->setActiveSheetIndex(2);

		// Get the active sheet
		$activeSheet = $spreadsheet->getActiveSheet();
		// //get 3rd sheet
		// $sheet3 = $spreadsheet->getSheet(2);
		$activeSheet->setCellValue('A8', 'Barangay ' . $_SESSION['currbrgy']);
		$activeSheet->setCellValue('A11', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$activeSheet->setCellValue('F12',date('Y', strtotime($endDate)));

		$resultMap2 = array();
		foreach ($result2 as $item) {
			$accountCode = $item->acc_code;
			[$begbal,$d,$c] = $this->getBegBal($accountCode,$startDate);
			//$balance = $item->net_balance;
			$td =$item->total_debit + $d;
			$tc = $item->total_credit +$c;
			
			
			$balance = $td - $tc;
			if($accountCode >= 20101010 ){
				$resultMap2[$accountCode] = array('bal' => abs($balance));
			}else{
				$resultMap2[$accountCode] = array('bal' => $balance);
			}
			
		}

					
		//Iterate through the Excel file rows once
		$row = 17;
		$end_row=553;
		while ($row <= $end_row) {
			$accountCode = $activeSheet->getCell('J' . $row)->getValue();

			if (!empty($accountCode)){
				if (isset($resultMap2[$accountCode])) {
				
					$bal = $resultMap2[$accountCode]['bal'];
					
	
					// Update the debit and credit columns in the corresponding row
					
					$activeSheet->setCellValue('F' . $row, $bal);
					
				}else{
					
					if($accountCode>=20101010){
					[$begbal,$d,$c] = $this->getBegBal($accountCode,$startDate);
					
					$activeSheet->setCellValue('F' . $row, abs($begbal));
					
					}else{
						[$begbal,$d,$c] = $this->getBegBal($accountCode,$startDate);
						$activeSheet->setCellValue('F' . $row, $begbal);
					}
					
				}
				

			}
			
			// Check if the account code exists in the result map
			

			$row++;
		}


		//get 4th sheet
		$spreadsheet->setActiveSheetIndex(3);
		$activeSheet = $spreadsheet->getActiveSheet();

		$activeSheet->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		$activeSheet->setCellValue('A8', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
	
		$activeSheet->setCellValue('D9',date('Y', strtotime($endDate)));


		$spreadsheet->setActiveSheetIndex(4);
		$activeSheet = $spreadsheet->getActiveSheet(4);

		$activeSheet->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		$activeSheet->setCellValue('A7', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
	
		$activeSheet->setCellValue('F8',date('Y', strtotime($endDate)));
		$activeSheet->setCellValue('F54', $cashBegBal);

		$spreadsheet->setActiveSheetIndex(5);
		$activeSheet = $spreadsheet->getActiveSheet();

		$activeSheet->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		$activeSheet->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
	
		$activeSheet->setCellValue('H13',date('Y', strtotime($endDate)));
		$activeSheet->setCellValue('A14',"Beginning Balance as of ".date('F j, Y', strtotime($startDate)));
		$activeSheet->setCellValue('A24',"Ending Balance as of ".date('F j, Y', strtotime($endDate)));
		
		$spreadsheet->setActiveSheetIndex(1);
		$activeSheet = $spreadsheet->getActiveSheet();
		$activeSheet->setCellValue('B32',ucfirst($_SESSION['fname']. ' '.ucfirst($_SESSION['lname'])));
		$activeSheet->setCellValue('B33',$_SESSION['position']);

		$spreadsheet->setActiveSheetIndex(3);
		$activeSheet = $spreadsheet->getActiveSheet();
		$activeSheet->setCellValue('B51',ucfirst($_SESSION['fname']. ' '.ucfirst($_SESSION['lname'])));
		$activeSheet->setCellValue('B52',$_SESSION['position']);

		$spreadsheet->setActiveSheetIndex(4);
		$activeSheet = $spreadsheet->getActiveSheet();
		$activeSheet->setCellValue('A59',ucfirst($_SESSION['fname']. ' '.ucfirst($_SESSION['lname'])));
		$activeSheet->setCellValue('A60',$_SESSION['position']);

		$spreadsheet->setActiveSheetIndex(5);
		$activeSheet = $spreadsheet->getActiveSheet();
		$activeSheet->setCellValue('A30',ucfirst($_SESSION['fname']. ' '.ucfirst($_SESSION['lname'])));
		$activeSheet->setCellValue('A31',$_SESSION['position']);

		// 7th sheet - Trial Balance (uses the same carry-forward logic as generateTB)
		$spreadsheet->setActiveSheetIndex(6);
		$activeSheet = $spreadsheet->getActiveSheet();

		$tbResult = $this->buildTrialBalanceTotalsWithCarry($brgyId, $startDate, $endDate);

		$activeSheet->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		$activeSheet->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		$activeSheet->setCellValue('A215', ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
		$activeSheet->setCellValue('A216', $_SESSION['position']);

		$tbResultMap = array();
		if (!empty($tbResult)) {
			foreach ($tbResult as $item) {
				$tbResultMap[$item->code] = array(
					'debit' => $item->total_debit,
					'credit' => $item->total_credit,
				);
			}
		}

		$tbRow = 11;
		while ($activeSheet->getCell('B' . $tbRow)->getValue() !== null) {
			$accountCode = $activeSheet->getCell('B' . $tbRow)->getValue();

			if (isset($tbResultMap[$accountCode])) {
				$activeSheet->setCellValue('C' . $tbRow, $tbResultMap[$accountCode]['debit']);
				$activeSheet->setCellValue('D' . $tbRow, $tbResultMap[$accountCode]['credit']);
			}

			$tbRow++;
		}

		$spreadsheet->setActiveSheetIndex(0);



		
		

		// Save the Excel file with the updated data
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Financial Statements ' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
	return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
					
		// Set a session flashdata with success message
		// $this->session->set_flashdata('success_message', 'Successfully downloaded the Trial Balance');
		// echo '<script>
			
		// 	setTimeout(function() {
		// 		window.location.href = "/tb";
		// 	}, 2000);
		// </script>';
	// 	redirect('/tb_page');
	// 	echo '<script>
		
	//     setTimeout(function() {
	//         window.location.href = "/tb";
	//     }, 2000);
	// </script>';


		
	}

	
	public function generatefpos_file($result,$startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
		
		//get data
		$result = $this->getfpos_data($startDate,$endDate);
		// $this->output
		// ->set_content_type('application/json')
		// ->set_output(json_encode($data));


		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/fpos.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

		// Assuming $result contains your trial balance result (array or object)
		// Replace this with your method to get the trial balance result

		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();

					
		// Set the name of the barangay in the merged cell (A1 to C1)
		
		$spreadsheet->getActiveSheet()->setCellValue('A8', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A11', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		// $spreadsheet->getActiveSheet()->setCellValue('A215',ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
		// $spreadsheet->getActiveSheet()->setCellValue('A216',$_SESSION['position']);

		$sheet->setCellValue('F12',date('Y', strtotime($endDate)));
		
						
		//Create a map to store database results by account code
		$resultMap = array();
		foreach ($result as $item) {
			
			$accountCode = $item->acc_code;
			$balance = $item->net_balance;
			$resultMap[$accountCode] = array('bal' => $balance);
		}

					
		//Iterate through the Excel file rows once
		$row = 17;
		$end_row=553;
		while ($row <= $end_row) {
			$accountCode = $sheet->getCell('J' . $row)->getValue();

			if (!empty($accountCode)){
				if (isset($resultMap[$accountCode])) {
				
					$bal = $resultMap[$accountCode]['bal'];
					
	
					// Update the debit and credit columns in the corresponding row
					
					$sheet->setCellValue('F' . $row, $bal);
					
				}else{
					$sheet->setCellValue('F' . $row, 0);
				}
				

			}
			
			// Check if the account code exists in the result map
			

			$row++;
		}
			
		$spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(0);
		//get 2nd sheet
		$sheet2 = $spreadsheet->getSheet(1);
		$sheet2->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		$sheet2->setCellValue('A8', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
	
		$sheet2->setCellValue('D9',date('Y', strtotime($endDate)));


		

		// Save the Excel file with the updated data
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Financial Position ' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
			



		
	}

	
	public function generatecf_file($result,$startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
	
		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/cf.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

		// Assuming $result contains your trial balance result (array or object)
		// Replace this with your method to get the trial balance result

		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();

					
		// Set the name of the barangay in the merged cell (A1 to C1)
		
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A7', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		// $spreadsheet->getActiveSheet()->setCellValue('A215',ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
		// $spreadsheet->getActiveSheet()->setCellValue('A216',$_SESSION['position']);

		$sheet->setCellValue('F8',date('Y', strtotime($endDate)));
		// $sheet->setCellValue('D12',0);
		// $sheet->setCellValue('D13',0);
		// $sheet->setCellValue('D14',0);
		// $sheet->setCellValue('D15',0);
		// $sheet->setCellValue('D16',0);

		// $sheet->setCellValue('D19',0);
		// $sheet->setCellValue('D20',0);
		// $sheet->setCellValue('D21',0);
		// $sheet->setCellValue('D22',0);
		// $sheet->setCellValue('D23',0);
		// $sheet->setCellValue('D24',0);

		// $sheet->setCellValue('D30',0);
		// $sheet->setCellValue('D31',0);
		// $sheet->setCellValue('D32',0);
		// $sheet->setCellValue('D33',0);
		// $sheet->setCellValue('D34',0);

		// $sheet->setCellValue('D37',0);
		// $sheet->setCellValue('D38',0);
		// $sheet->setCellValue('D39',0);
		// $sheet->setCellValue('D40',0);

		// $sheet->setCellValue('D44',0);




		

					
					
		// Create a map to store database results by account code
		// $resultMap = array();
		// foreach ($result as $item) {
			
		// 	$accountCode = $item->code;
		// 	$debit = $item->total_debit;
		// 	$credit = $item->total_credit;
		// 	$resultMap[$accountCode] = array('debit' => $debit, 'credit' => $credit);
		// }

					
		// Iterate through the Excel file rows once
		// $row = 11;
		// while ($sheet->getCell('B' . $row)->getValue() !== null) {
		// 	$accountCode = $sheet->getCell('B' . $row)->getValue();
			
		// 	// Check if the account code exists in the result map
		// 	if (isset($resultMap[$accountCode])) {
				
		// 		$debit = $resultMap[$accountCode]['debit'];
		// 		$credit = $resultMap[$accountCode]['credit'];

		// 		// Update the debit and credit columns in the corresponding row
				
		// 		$sheet->setCellValue('C' . $row, $debit);
		// 		$sheet->setCellValue('D' . $row, $credit);
		// 	}

		// 	$row++;
		// }
			

		// Update data starting from the 11th row
		// $row = 11;
		// foreach ($result as $item) {
		// 	$sheet->setCellValue('A' . $row, $item->account_name);
		// 	$sheet->setCellValue('B' . $row, $item->account_code);
		// 	$sheet->setCellValue('C' . $row, $item->total_debit);
		// 	$sheet->setCellValue('D' . $row, $item->total_credit);
		// 	$row++;
		// }

		// Auto-size columns for better readability
		

		// Save the Excel file with the updated data
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Statement of Cash Flows' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
					
		// Set a session flashdata with success message
		// $this->session->set_flashdata('success_message', 'Successfully downloaded the Trial Balance');
		// echo '<script>
			
		// 	setTimeout(function() {
		// 		window.location.href = "/tb";
		// 	}, 2000);
		// </script>';
	// 	redirect('/tb_page');
	// 	echo '<script>
		
	//     setTimeout(function() {
	//         window.location.href = "/tb";
	//     }, 2000);
	// </script>';


		
	}

	
	public function generatecae_file($result,$startDate,$endDate){
		$this->load->helper('download');
		$this->load->helper('file');
	
		// Load PhpSpreadsheet library
		

		// Load the existing template
		$templatePath = FCPATH .'assets/templates/cae.xlsx';
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

		// Assuming $result contains your trial balance result (array or object)
		// Replace this with your method to get the trial balance result

		// Get the active sheet
		$sheet = $spreadsheet->getActiveSheet();

					
		// Set the name of the barangay in the merged cell (A1 to C1)
		
		$spreadsheet->getActiveSheet()->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
		$spreadsheet->getActiveSheet()->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
		// $spreadsheet->getActiveSheet()->setCellValue('A215',ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
		// $spreadsheet->getActiveSheet()->setCellValue('A216',$_SESSION['position']);

		$sheet->setCellValue('H12',date('Y', strtotime($endDate)));
		// $sheet->setCellValue('D12',0);
		// $sheet->setCellValue('D13',0);
		// $sheet->setCellValue('D14',0);
		// $sheet->setCellValue('D15',0);
		// $sheet->setCellValue('D16',0);

		// $sheet->setCellValue('D19',0);
		// $sheet->setCellValue('D20',0);
		// $sheet->setCellValue('D21',0);
		// $sheet->setCellValue('D22',0);
		// $sheet->setCellValue('D23',0);
		// $sheet->setCellValue('D24',0);

		// $sheet->setCellValue('D30',0);
		// $sheet->setCellValue('D31',0);
		// $sheet->setCellValue('D32',0);
		// $sheet->setCellValue('D33',0);
		// $sheet->setCellValue('D34',0);

		// $sheet->setCellValue('D37',0);
		// $sheet->setCellValue('D38',0);
		// $sheet->setCellValue('D39',0);
		// $sheet->setCellValue('D40',0);

		// $sheet->setCellValue('D44',0);




		

					
					
		// Create a map to store database results by account code
		// $resultMap = array();
		// foreach ($result as $item) {
			
		// 	$accountCode = $item->code;
		// 	$debit = $item->total_debit;
		// 	$credit = $item->total_credit;
		// 	$resultMap[$accountCode] = array('debit' => $debit, 'credit' => $credit);
		// }

					
		// Iterate through the Excel file rows once
		// $row = 11;
		// while ($sheet->getCell('B' . $row)->getValue() !== null) {
		// 	$accountCode = $sheet->getCell('B' . $row)->getValue();
			
		// 	// Check if the account code exists in the result map
		// 	if (isset($resultMap[$accountCode])) {
				
		// 		$debit = $resultMap[$accountCode]['debit'];
		// 		$credit = $resultMap[$accountCode]['credit'];

		// 		// Update the debit and credit columns in the corresponding row
				
		// 		$sheet->setCellValue('C' . $row, $debit);
		// 		$sheet->setCellValue('D' . $row, $credit);
		// 	}

		// 	$row++;
		// }
			

		// Update data starting from the 11th row
		// $row = 11;
		// foreach ($result as $item) {
		// 	$sheet->setCellValue('A' . $row, $item->account_name);
		// 	$sheet->setCellValue('B' . $row, $item->account_code);
		// 	$sheet->setCellValue('C' . $row, $item->total_debit);
		// 	$sheet->setCellValue('D' . $row, $item->total_credit);
		// 	$row++;
		// }

		// Auto-size columns for better readability
		

		// Save the Excel file with the updated data
		$currentDateTime = date('F-Y', strtotime($endDate));
		$excelFileName = 'Statement of Changes in Net Assets Equity' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
		$excelFilePath = FCPATH . 'temp/' . $excelFileName;

		 // Set the response headers for Excel file download
		//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
		//  header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		// $writer->save('php://output');
		$writer->save($excelFilePath);
		
					
		// Trigger the download of the modified Excel file
		force_download($excelFileName, file_get_contents($excelFilePath));
		delete_files($excelFilePath);
					
		// Set a session flashdata with success message
		// $this->session->set_flashdata('success_message', 'Successfully downloaded the Trial Balance');
		// echo '<script>
			
		// 	setTimeout(function() {
		// 		window.location.href = "/tb";
		// 	}, 2000);
		// </script>';
	// 	redirect('/tb_page');
	// 	echo '<script>
		
	//     setTimeout(function() {
	//         window.location.href = "/tb";
	//     }, 2000);
	// </script>';


		
	}

	public function generatetb_file($result,$startDate,$endDate){
			$this->load->helper('download');
			$this->load->helper('file');
		
			// Load PhpSpreadsheet library
			
	
			// Load the existing template
			$templatePath = FCPATH .'assets/templates/tb.xls';
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
	
			// Assuming $result contains your trial balance result (array or object)
			// Replace this with your method to get the trial balance result
	
			// Get the active sheet
			$sheet = $spreadsheet->getActiveSheet();

						
			// Set the name of the barangay in the merged cell (A1 to C1)
			
			$spreadsheet->getActiveSheet()->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
			$spreadsheet->getActiveSheet()->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
			$spreadsheet->getActiveSheet()->setCellValue('A215',ucfirst($_SESSION['fname']).' '.ucfirst($_SESSION['lname']));
			$spreadsheet->getActiveSheet()->setCellValue('A216',$_SESSION['position']);


						
			// Create a map to store database results by account code
			$resultMap = array();
			foreach ($result as $item) {
				
				$accountCode = $item->code;
				$debit = $item->total_debit;
				$credit = $item->total_credit;
				$resultMap[$accountCode] = array('debit' => $debit, 'credit' => $credit);
			}

						
			// Iterate through the Excel file rows once
			$row = 11;
			while ($sheet->getCell('B' . $row)->getValue() !== null) {
				$accountCode = $sheet->getCell('B' . $row)->getValue();
				
				// Check if the account code exists in the result map
				if (isset($resultMap[$accountCode])) {
					
					$debit = $resultMap[$accountCode]['debit'];
					$credit = $resultMap[$accountCode]['credit'];

					// Update the debit and credit columns in the corresponding row
					
					$sheet->setCellValue('C' . $row, $debit);
					$sheet->setCellValue('D' . $row, $credit);
				}

				$row++;
			}
				
	
			// Update data starting from the 11th row
			// $row = 11;
			// foreach ($result as $item) {
			// 	$sheet->setCellValue('A' . $row, $item->account_name);
			// 	$sheet->setCellValue('B' . $row, $item->account_code);
			// 	$sheet->setCellValue('C' . $row, $item->total_debit);
			// 	$sheet->setCellValue('D' . $row, $item->total_credit);
			// 	$row++;
			// }
	
			// Auto-size columns for better readability
			$this->addlog("Generate Trial Balance",$_SESSION['fname'].' '.$_SESSION['lname']);
			
	
			// Save the Excel file with the updated data
			$currentDateTime = date('F-Y', strtotime($endDate));
			$excelFileName = 'Trial Balance_' .ucfirst($_SESSION['currbrgy']).'-'.$currentDateTime . '.xlsx';
			$excelFilePath = FCPATH . 'temp/' . $excelFileName;

			 // Set the response headers for Excel file download
			//  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			//  header('Content-Disposition: attachment;filename="'.$excelFileName.'"');
			//  header('Cache-Control: max-age=0');

			$writer = new Xlsx($spreadsheet);
			// $writer->save('php://output');
			$writer->save($excelFilePath);
			return $this->respondWithSpreadsheetFile($excelFileName, $excelFilePath);
						
			// Set a session flashdata with success message
			// $this->session->set_flashdata('success_message', 'Successfully downloaded the Trial Balance');
			// echo '<script>
				
			// 	setTimeout(function() {
			// 		window.location.href = "/tb";
			// 	}, 2000);
			// </script>';
		// 	redirect('/tb_page');
		// 	echo '<script>
            
        //     setTimeout(function() {
        //         window.location.href = "/tb";
        //     }, 2000);
        // </script>';


			
		}
	

	public function generateexcel(){
		$startDate = $this->input->post('start_date'); // Assuming you're using POST method
        $endDate = $this->input->post('end_date');

        $this->load->model('Your_model');
        $accountData = $this->Your_model->getAccountDataForExcel($startDate, $endDate);

        // Load the CI_Spreadsheet library
        $this->load->library('CI_Spreadsheet');



        // Get the active sheet
        $sheet = $spreadsheet->getActiveSheet();

        // Set the data starting row (adjust as needed)
        $startRow = 2;

        // Populate the data
        foreach ($accountData as $data) {
            $sheet->setCellValue('A' . $startRow, $data['account_title']);
            $sheet->setCellValue('B' . $startRow, $data['account_code']);
            $sheet->setCellValue('C' . $startRow, $data['total_debit']);
            $sheet->setCellValue('D' . $startRow, $data['total_credit']);
            $startRow++;
        }

        // Save the Excel file
        $filename = 'account_data_' . date('Y-m-d') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filename);

        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // Output the Excel file to the browser
        $writer->save('php://output');
	}

	public function addmeeting(){
		$meeting = array(
			'meeting_name'=> $this->input->post('title'),
			'meeting_desc'=> $this->input->post('desc'),
			'color'=> $this->input->post('color'),
			'start_date'=> $this->input->post('start'),
			'end_date'=> $this->input->post('end')

		);

	 $res = $this->db->insert('tbl_meetings',$meeting);
	 if($res){
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res));
	 
	}else{
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode(false));
		}
	}

	public function addrespondent(){
		
if(isset($_FILES["rpic"])){
    $image  = $_FILES["rpic"];
    $folder = $_SERVER['DOCUMENT_ROOT']."/dirs/assets/uploads/";
    $target = $folder.$image["name"];

  	if ( move_uploaded_file( $image["tmp_name"], $target ) ) {
           
           
           $resp_array = array(
				"fullname"=> $this->input->post('fname'),
				"pic" => $image["name"],
				"date_added"=> date('F j, Y')
		   );
	$res = $this->db->insert('tbl_respondents',$resp_array);
		
	if($res){
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res));
	}else{
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode(false));
	}



      }else{
        echo json_encode(array(
            "error"=>1,
            "message"=>"File upload Error"
        ));
      }


}else{
    echo "error";
}
	}

	public function getmeetings(){
		$this->db->order_by('meet_id','desc');
		$res = $this->db->get('tbl_meetings');
		if($res){
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res->result_array()));
	 
	}else{
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode(false));
		}
	}


	public function getrespondents(){
		$this->db->order_by('resp_id', 'desc');
		$res = $this->db->get('tbl_respondents');
		if($res){
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res->result_array()));
	 
	}else{
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode(false));
		}
	}


	
	public function getmembers($id){
		if($id == 0){
			$this->db->where('has_team',0);
			$this->db->order_by('resp_id', 'desc');
		$res = $this->db->get('tbl_respondents');
		if($res){
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res->result_array()));
	 
	}else{
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode(false));
		}
	}else{
		$this->db->where('has_team',0);
			$this->db->where('resp_id !=',$id);
			$this->db->order_by('resp_id', 'desc');
		$res = $this->db->get('tbl_respondents');
		if($res){
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res->result_array()));
	 
	}else{
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode(false));
		}


		}
	}


	public function addteam(){
		$members = $this->input->post('members');
		$cMembers = count($members);
		$team_arr = array(
			'tname' =>$this->input->post('tname'),
			'leader' =>$this->input->post('tleader'),
			'date_created'=> date('F j, Y')

		);

		$res = $this->db->insert('tbl_teams',$team_arr);
		$insert_id = $this->db->insert_id();
		if($res){
			for($i=0;$i < $cMembers;$i++){
				
				$team_mem_arr = array(
				   'team_id' => $insert_id,
					'member_id' => $members[$i]
				);
		
				$this->db->insert('tbl_team_members',$team_mem_arr);
				$mdata = array(
					'has_team'=> 1
					);
					$this->db->update('tbl_respondents', $mdata, array('resp_id' => $members[$i]));	
				}
				$this->output
		->set_content_type('application/json')
		->set_output(json_encode(true));
		}
	}

	public function getteams(){
		$query = $this->db->select('t.team_id as tid,t.tname as team_name, tr.fullname as team_leader,t.date_created as d_created, COUNT(tm.member_id) as member_count')
                  ->from('tbl_teams as t')
                  ->join('tbl_team_members as tm', 't.team_id = tm.team_id', 'left')
				  ->join('tbl_respondents as tr', 't.leader = tr.resp_id')
                  ->group_by('t.team_id')
                  ->get();
		$this->output
				  ->set_content_type('application/json')
				  ->set_output(json_encode($query->result_array()));	  
	}

	public function setbalance(){
		$date=strtotime($this->input->post('year').'-01-01');
		$bdate = date('Y-m-d',$date);
		$brgyId = $this->getSelectedBrgyId();
		$year = (int) $this->input->post('year');

		$b_array = array(
			'acc_code'=>$this->input->post('acc_code'),
			'bal_date'=>$bdate,
			'debit'=>$this->input->post('debit'),
			'credit'=>$this->input->post('credit'),
			'date_created'=> date('Y-m-d')
		);
		if($this->db->field_exists('brgy_id', 'tbl_begbal')){
			$b_array['brgy_id'] = $brgyId;
		}
		if($this->db->field_exists('year', 'tbl_begbal')){
			$b_array['year'] = $year;
		}

		$res = $this->db->insert('tbl_begbal',$b_array);

		if($res){
			$this->output
			->set_content_type('application/json')
			->set_output(json_encode(true));
		}else{
			$this->output
			->set_content_type('application/json')
			->set_output(json_encode(false));
		}
	}


	public function download_excel_template(){
		$this->load->helper('download');
		$templatePath = FCPATH .'assets/templates/beginning_balance_template.xlsx';

		if (file_exists($templatePath)) {
            // Force the file to be downloaded
            force_download($templatePath, NULL);
			
        } else {
            // If the file doesn't exist, you can handle the error accordingly
            echo 'The template file does not exist.';
        }

	}

	public function uploadbb() {
		$this->load->library('upload');

        $config['upload_path'] = './assets/uploads/';
        $config['allowed_types'] = 'xlsx|xls|csv';
        $config['max_size'] = 2048 * 10 * 2; // 2MB max size

        $this->upload->initialize($config);

        if ($this->upload->do_upload('fileInput')) {
            // File successfully uploaded
            $file_data = $this->upload->data();
            $file_path = $file_data['full_path'];

            // Check the file extension
            $extension = pathinfo($file_data['file_name'], PATHINFO_EXTENSION);

            if ($extension == 'xlsx' || $extension == 'xls') {
                // Load the Excel file and convert to CSV or JSON
                $this->load_excel($file_path);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file format. Please upload an Excel or CSV file.']);
            }
        } else {
            // Error in file upload
            echo json_encode(['status' => 'error', 'message' => $this->upload->display_errors()]);
        }
    }

	private function load_excel($file_path) {
        // Load the Excel file using PhpSpreadsheet
        try {
            $spreadsheet = IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rowdata = [];

			$rowdata = [];

			// Start reading from row 11, and only read columns A, B, C, and D
			$startRow = 11;
			foreach ($worksheet->getRowIterator($startRow) as $row) {
				$rowData = [];

				$valueA = $worksheet->getCell('A' . $row->getRowIndex())->getCalculatedValue();
				$valueB = $worksheet->getCell('B' . $row->getRowIndex())->getCalculatedValue();
				$valueC = $worksheet->getCell('C' . $row->getRowIndex())->getCalculatedValue();
				$valueD = $worksheet->getCell('D' . $row->getRowIndex())->getCalculatedValue();

				//Check if either column A or B is null (empty or blank), and stop the loop if true
				if (is_null($valueA) || is_null($valueB)) {
					break;  // Exit the loop if a null value is found in column A or B
				}

				// Add the values to the rowData array
				$rowData[] = $valueA;
				$rowData[] = $valueB;
				$rowData[] = $valueC;
				$rowData[] = $valueD;

				// Add the row data to the final result
				$rowdata[] = $rowData;
			}
		
			$this->session->set_userdata('data', $rowdata);
		
		// $data['alink'] = "BB";
		// $data['title'] = "BB";
		// $data['data'] = $rowdata;
			redirect('bb_page');
		// $this->load->view('templates/header.php',$data);
		// $this->load->view('templates/sidebar.php',$data);
		// $this->load->view('admin/beginning_balance',$data);
		// $this->load->view('templates/footer.php');

			
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error reading Excel file: ' . $e->getMessage()]);
        }
    }
		
	
}
