<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Journal column routing (COA code → Excel column + amount field)
|--------------------------------------------------------------------------
| Used by CRJ / CKDJ / CSDJ generators. Keys are exact account codes as stored
| in tbl_jevdata.acc_code. Extend this file when your COA adds accounts.
|
| 'col' / target cells use the same mapping as the original hard-coded logic.
| 'amount' is debit or credit from the line.
|
| Special: nested rules use credit_zero / debit_zero to mirror legacy branches.
*/

$config['journal_column_map'] = array(
	'crj' => array(
		'exact' => array(
			'20201010' => array('col' => 'F', 'amount' => 'credit'),
			'20201070' => array('col' => 'G', 'amount' => 'credit'),
			'40102040' => array('col' => 'H', 'amount' => 'credit'),
			'40105020' => array('col' => 'I', 'amount' => 'credit'),
			'10102010' => array('col' => 'M', 'amount' => 'debit'),
			'10101010' => array('col' => 'N', 'amount' => 'debit'),
		),
		'liability_first_digit_min' => 2,
		'liability_cols' => array('acc' => 'J', 'debit' => 'K', 'credit' => 'L'),
		'other_cols' => array('acc' => 'P', 'debit' => 'Q', 'credit' => 'R'),
	),
	'ckdj' => array(
		'exact' => array(
			'10102020' => array('col' => 'F', 'amount' => 'credit'),
			'10305020' => array('col' => 'H', 'amount' => 'debit'),
			'10305040' => array('col' => 'I', 'amount' => 'debit'),
			'10305010' => array('col' => 'J', 'amount' => 'debit'),
			'50204020' => array('col' => 'K', 'amount' => 'debit'),
		),
		'nested' => array(
			'20201010' => array(
				array('when' => 'credit_zero', 'cols' => array('acc' => 'L', 'debit' => 'M', 'credit' => 'N')),
				array('when' => 'else', 'col' => 'G', 'amount' => 'credit'),
			),
		),
		'other_cols' => array('acc' => 'L', 'debit' => 'M', 'credit' => 'N'),
	),
	'csdj' => array(
		'exact' => array(
			'10305020' => array('col' => 'G', 'amount' => 'credit'),
		),
		'nested' => array(
			'20201010' => array(
				array('when' => 'credit_zero', 'cols' => array('acc' => 'K', 'debit' => 'L', 'credit' => 'M')),
				array('when' => 'else', 'col' => 'H', 'amount' => 'credit'),
			),
			'50101010' => array(
				array('when' => 'debit_zero', 'cols' => array('acc' => 'K', 'debit' => 'L', 'credit' => 'M')),
				array('when' => 'else', 'col' => 'I', 'amount' => 'debit'),
			),
			'20201030' => array(
				array('when' => 'debit_zero', 'cols' => array('acc' => 'K', 'debit' => 'L', 'credit' => 'M')),
				array('when' => 'else', 'col' => 'J', 'amount' => 'debit'),
			),
		),
		'other_cols' => array('acc' => 'L', 'debit' => 'M', 'credit' => 'N'),
	),
);
