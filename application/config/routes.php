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
$route['rrr_page'] = 'administrator/rrr_page';
$route['sba_page'] = 'administrator/sba_page';
$route['aging_page'] = 'administrator/aging_page';
$route['viewjev/(:num)'] = 'administrator/viewjev_page/$1';

$route['bb_page'] = 'administrator/bb_page';
$route['db_backup'] = 'administrator/db_backup';
$route['logs'] = 'administrator/logs';

//beginning balance
$route['savebb'] = 'administrator/savebb';
$route['vbb_page'] = 'administrator/vbb_page';
$route['downloadtemplate'] = 'administrator/download_excel_template';
$route['uploadbb'] = 'administrator/uploadbb';
$route['viewbb/(:num)/(:num)'] = 'administrator/viewbb_page/$1/$2';