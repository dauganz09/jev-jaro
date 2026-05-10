<?php
defined('BASEPATH') OR exit('No direct script access allowed');


$route['default_controller'] = 'administrator';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

//admin routes
$route['dashboard'] = 'administrator/dashboard';
$route['logout'] = 'administrator/logout';
$route['incident_report'] = 'administrator/incident_report_page';
$route['incident_list'] = 'administrator/incident_list_page';
$route['teams'] = 'administrator/teams_page';
$route['respondents'] = 'administrator/respondents_page';
$route['addmeeting'] = 'administrator/addmeeting';
$route['getmeetings'] = 'administrator/getmeetings';
$route['addrespondent'] = 'administrator/addrespondent';
$route['getrespondents'] = 'administrator/getrespondents';
$route['getmembers/(:num)'] = 'administrator/getmembers/$1';
$route['addteam'] = 'administrator/addteam';
$route['getteams'] = 'administrator/getteams';
$route['setbalance'] = 'administrator/setbalance';
$route['getjevlist2'] = 'administrator/getjevlist2';


//jev
$route['jev'] = 'administrator/jev_page';
$route['userlogin'] = 'administrator/userlogin';
$route['changecurrbrgy'] = 'administrator/changecurrbrgy';
$route['savejev'] = 'administrator/savejev';
$route['deletejev'] = 'administrator/deletejev';

//GET
$route['getaccounts'] = 'administrator/getaccounts';
$route['getsubsidiaries'] = 'administrator/getsubsidiaries';
$route['createsubsidiary'] = 'administrator/createsubsidiary';
$route['tb'] = 'administrator/generateTB';
$route['gj'] = 'administrator/generateGJ';
$route['fs'] = 'administrator/generateFS';
$route['ledger'] ='administrator/generateLedger';
$route['tb_preview'] = 'administrator/previewTB';
$route['gj_preview'] = 'administrator/previewGJ';
$route['fs_preview'] = 'administrator/previewFS';
$route['ledger_preview'] = 'administrator/previewLedger';
$route['getjevlist/(:num)'] = 'administrator/getjevlist/$1';
$route['getbbs/(:num)'] = 'administrator/getbbs/$1';
$route['getbbs2/(:num)/(:num)'] = 'administrator/getbbs2/$1/$2';
$route['rrr'] = 'administrator/generateRRR';
$route['sba'] = 'administrator/generateSBA';
$route['aging'] = 'administrator/generateAG';

//update
$route['updatejev'] = 'administrator/updatejev';

//pages
$route['tb_page'] = 'administrator/tb_page';
$route['fs_page'] = 'administrator/fs_page';
$route['gl'] = 'administrator/gl_page';
$route['jevlist'] = 'administrator/jevlist_page';
$route['journals'] = 'administrator/journals_page';
$route['bank_accounts'] = 'administrator/bank_accounts_page';
$route['bank_recon'] = 'administrator/bank_recon_page';
$route['chart_of_accounts'] = 'administrator/chart_of_accounts_page';
$route['rrr_page'] = 'administrator/rrr_page';
$route['sba_page'] = 'administrator/sba_page';
$route['aging_page'] = 'administrator/aging_page';
$route['viewjev/(:num)'] = 'administrator/viewjev_page/$1';

$route['bb_page'] = 'administrator/bb_page';
$route['db_backup'] = 'administrator/db_backup';
$route['logs'] = 'administrator/logs';

// bank reconciliation APIs
$route['api/bank_accounts'] = 'administrator/api_bank_accounts';
$route['api/bank_accounts/save'] = 'administrator/api_bank_account_save';
$route['api/bank_accounts/toggle'] = 'administrator/api_bank_account_toggle';
$route['api/coa_accounts'] = 'administrator/api_coa_accounts';
$route['api/coa_accounts/save'] = 'administrator/api_coa_account_save';
$route['api/coa_accounts/delete'] = 'administrator/api_coa_account_delete';
$route['api/bank_recon/upsert'] = 'administrator/api_bank_recon_upsert';
$route['api/bank_recon/get'] = 'administrator/api_bank_recon_get';
$route['api/bank_recon/lines'] = 'administrator/api_bank_recon_lines';
$route['api/bank_recon/lines/add'] = 'administrator/api_bank_recon_line_add';
$route['api/bank_recon/lines/delete'] = 'administrator/api_bank_recon_line_delete';
$route['api/bank_recon/book_lines'] = 'administrator/api_bank_recon_book_lines';
$route['api/bank_recon/suggest'] = 'administrator/api_bank_recon_suggest';
$route['api/bank_recon/match/add'] = 'administrator/api_bank_recon_match_add';
$route['api/bank_recon/match/delete'] = 'administrator/api_bank_recon_match_delete';
$route['api/bank_recon/items'] = 'administrator/api_bank_recon_items';
$route['api/bank_recon/items/add'] = 'administrator/api_bank_recon_item_add';
$route['api/bank_recon/items/delete'] = 'administrator/api_bank_recon_item_delete';
$route['api/bank_recon/items/create_jev'] = 'administrator/api_bank_recon_item_create_jev';
$route['brs'] = 'administrator/generateBRS';
$route['brs_preview'] = 'administrator/previewBRS';

//beginning balance
$route['savebb'] = 'administrator/savebb';
$route['vbb_page'] = 'administrator/vbb_page';
$route['downloadtemplate'] = 'administrator/download_excel_template';
$route['uploadbb'] = 'administrator/uploadbb';
$route['viewbb/(:num)/(:num)'] = 'administrator/viewbb_page/$1/$2';