<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Chart of Accounts — Philippine government COA / PPSAS-style classification
|--------------------------------------------------------------------------
| Values stored in tbl_accounts.account_class. Labels align with common COA
| account-group numbering (first digit of UACS account code).
*/

$config['coa_account_classes'] = array(
	'' => '(unspecified)',
	'Assets' => 'Assets (typically 1xxxxxxx)',
	'Liabilities' => 'Liabilities (typically 2xxxxxxx)',
	'Net_Assets_Equity' => 'Net Assets / Equity (typically 3xxxxxxx)',
	'Revenue' => 'Revenue (typically 4xxxxxxx)',
	'Expenses' => 'Expenses (typically 5xxxxxxx)',
	'Memorandum' => 'Memorandum / Other',
);

$config['coa_code_hint'] = 'Use your COA / UACS account code (often 8 digits, e.g. 10102020).';
