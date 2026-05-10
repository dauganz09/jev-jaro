<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Aging Schedule of Unliquidated Cash Advances
|--------------------------------------------------------------------------
| COA account codes on tbl_jevdata.acc_code (inclusive). Adjust if your LGU uses a different range.
*/

$config['aging_report'] = array(
	'acc_code_start' => '10305010',
	'acc_code_end' => '10305040',
);
