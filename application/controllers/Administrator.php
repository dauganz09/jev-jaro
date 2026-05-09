<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class Administrator extends CI_Controller {
	private $previewMode = false;

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

			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'success' => true,
					'fileName' => $excelFileName,
					'previewSheets' => $previewSheets
				)));
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
		$this->db->order_by('account_id','asc');
		$res = $this->db->get('tbl_accounts');
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res->result_array()));
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

		$this->db->where('jev_no',$res->row_array()['jev_no']);
		$this->db->where('jev_id',$res->row_array()['jev_id']);
		$this->db->order_by('acc_code','ASC');
		$res2 = $this->db->get('tbl_jevdata');

		$data['jd'] = $res2->result_array();

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


	//functionality



	//jev

	public function savejev(){
		$acct_t = $this->input->post('acct_t');
		$acct_c = $this->input->post('acct_c');
		$debit = $this->input->post('debit');
		$credit = $this->input->post('credit');
		$cat = count($acct_t);
		$cac = count($acct_c);

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
		$cat = count($acct_t);
		$cac = count($acct_c);

		


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

		if($gtype == 'sp'){

		if($ltype == 's'){
			$this->generateGeneralLedger($acc_code,$acc_name,$startDate,$endDate);
		
		}elseif($ltype=='ss'){
			$this->generateGeneralLedgerSS($acc_code,$acc_name,$startDate,$endDate);
		}else{
			$this->generateGeneralLedger2($acc_code,$acc_name,$startDate,$endDate);
		}
	}else{
		if($ltype == 's'){
			$this->generateGeneralLedgerAll($startDate,$endDate);
		
		}elseif($ltype=='ss'){
			$this->generateGeneralLedgerSSAll($startDate,$endDate);
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
		
		$begbald = 0;
		$begbalc= 0;
		$this->db->where('acc_code',$a->code);
		$this->db->where('YEAR(bal_date)', $startYear);
		$res2 = $this->db->get('tbl_begbal');

		if($res2->num_rows()>0){
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res2->row_array()));
		return;
			
		}


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
		
		$begbald = 0;
		$begbalc= 0;
		$this->db->where('acc_code',$a->code);
		$this->db->where('YEAR(year)', $startYear);
		$res2 = $this->db->get('tbl_begbal');

		if($res2->num_rows()>0){
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res2->row_array()));
		return;
			
		}


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
		$startyear = date('Y',strtotime($startDate));
		$this->load->helper('download');
		$this->load->helper('file');
		$begbald = 0;
		$begbalc= 0;
		$this->db->where('acc_code',$acc_code);
		$this->db->where('YEAR(year)', $startYear);
		$res2 = $this->db->get('tbl_begbal');

		if($res2->num_rows()>0){
			$this->output
		->set_content_type('application/json')
		->set_output(json_encode($res2->row_array()));
		return;
			
		}

		

	
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
		$startyear = date('Y',strtotime($startDate));
		$this->load->helper('download');
		$this->load->helper('file');
		$begbald = 0;
		$begbalc= 0;
		$this->db->where('acc_code',$acc_code);
		$this->db->where('YEAR(year)', $startYear);
		$res2 = $this->db->get('tbl_begbal');

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

	public function generateGJ(){
		$startDate = date('Y-m-d', strtotime($this->input->post('sdate')));
		$endDate = date('Y-m-d', strtotime($this->input->post('edate')));
		$jtype = $this->getType($this->input->post('j_type'));
				
		$this->db->select('j.jev_no, j.jev_date,j.type,j.particulars,j.payor_payee, jd.acc_code, jd.acc_title, jd.debit, jd.credit');
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
        // If not, create a new JEV entry
        $organizedJevData[$jevDate][$jevNo] = array(
            'jev_no' => $jevNo,
			'parts'=>$parts,
			'payor_payee'=>$payor_payee,
			'type'=>$type,
            'jev_data' => array(),
        );
    }

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

		$row = 11;
		
		foreach($result as $date => $jev_no){
			
			
			foreach($jev_no as $k => $v){
				

				
				$sheet->setCellValue('B' . $row,$date);
				$sheet->setCellValue('C' . $row,$v['jev_no']);
				$sheet->setCellValue('E' . $row,$v['payor_payee']);
				// $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
								
				
				
					$newrow=0;
					foreach($v['jev_data'] as $l =>$m){
						
						//$sheet->setCellValue('D' . ($row+$merge),$m['acc_code']);
						// $sheet->setCellValue('E' . ($row+$merge),$merge);
						//$sheet->setCellValue('F' . ($row+$merge),$m['debit']);
						//$sheet->setCellValue('G' . ($row+$merge),$m['credit']);
						// $sheet->setCellValue('H' . ($row+$merge),$merge);
						if($m['acc_code']==="10305020"){
							$sheet->setCellValue('G' . $row,$m['credit']);
							
						
						}elseif($m['acc_code']==="20201010"){
							if($m['credit'] == 0){
							$sheet->setCellValue('K' . ($row+$newrow),$m['acc_code']);
							$sheet->setCellValue('L' . ($row+$newrow),$m['debit']);
							$sheet->setCellValue('M' . ($row+$newrow),$m['credit']);
							
							$newrow++;
							}else{
							$sheet->setCellValue('H' . $row,$m['credit']);
							$newrow++;
							
							}
						}elseif($m['acc_code']==="50101010"){
							if($m['debit'] == 0){
								$sheet->setCellValue('K' . ($row+$newrow),$m['acc_code']);
								$sheet->setCellValue('L' . ($row+$newrow),$m['debit']);
								$sheet->setCellValue('M' . ($row+$newrow),$m['credit']);
								
								$newrow++;
								}else{
							
							$sheet->setCellValue('I' . $row,$m['debit']);
							$newrow++;
								}

						}elseif($m['acc_code']==="20201030"){
							if($m['debit'] == 0){
								$sheet->setCellValue('K' . ($row+$newrow),$m['acc_code']);
								$sheet->setCellValue('L' . ($row+$newrow),$m['debit']);
								$sheet->setCellValue('M' . ($row+$newrow),$m['credit']);
								
								$newrow++;
								}else{
							$sheet->setCellValue('J' . $row,$m['debit']);
							$newrow++;
								}
						
						}else{
							$sheet->setCellValue('L' . ($row+$newrow),$m['acc_code']);
							$sheet->setCellValue('M' . ($row+$newrow),$m['debit']);
							$sheet->setCellValue('N' . ($row+$newrow),$m['credit']);
							
							$newrow++;
							
						}
						
					
					
							
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

		$row = 11;
		
		foreach($result as $date => $jev_no){
			
			
			foreach($jev_no as $k => $v){
				

				
				$sheet->setCellValue('B' . $row,$date);
				$sheet->setCellValue('C' . $row,$v['jev_no']);
				$sheet->setCellValue('E' . $row,$v['payor_payee']);
				// $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
								
				
				
					$newrow=0;
					foreach($v['jev_data'] as $l =>$m){
						
						//$sheet->setCellValue('D' . ($row+$merge),$m['acc_code']);
						// $sheet->setCellValue('E' . ($row+$merge),$merge);
						//$sheet->setCellValue('F' . ($row+$merge),$m['debit']);
						//$sheet->setCellValue('G' . ($row+$merge),$m['credit']);
						// $sheet->setCellValue('H' . ($row+$merge),$merge);
						if($m['acc_code']==="10102020"){
							$sheet->setCellValue('F' . $row,$m['credit']);
							
						
						}elseif($m['acc_code']==="20201010"){
							if($m['credit'] == 0){
							$sheet->setCellValue('L' . ($row+$newrow),$m['acc_code']);
							$sheet->setCellValue('M' . ($row+$newrow),$m['debit']);
							$sheet->setCellValue('N' . ($row+$newrow),$m['credit']);
							
							$newrow++;
							}else{
							$sheet->setCellValue('G' . $row,$m['credit']);
							
							}
						}elseif($m['acc_code']==="10305020"){
							
							$sheet->setCellValue('H' . $row,$m['debit']);
							$newrow++;

						}elseif($m['acc_code']==="10305040"){
							$sheet->setCellValue('I' . $row,$m['debit']);
							$newrow++;
						}elseif($m['acc_code']==="10305010"){
							$sheet->setCellValue('J' . $row,$m['debit']);
							$newrow++;
						}elseif($m['acc_code']==="50204020"){
							$sheet->setCellValue('K' . $row,$m['debit']);
							$newrow++;
						}else{
							$sheet->setCellValue('L' . ($row+$newrow),$m['acc_code']);
							$sheet->setCellValue('M' . ($row+$newrow),$m['debit']);
							$sheet->setCellValue('N' . ($row+$newrow),$m['credit']);
							
							$newrow++;
							
						}
						
					
					
							
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

		$row = 12;
		
		foreach($result as $date => $jev_no){
			
			
			foreach($jev_no as $k => $v){
				

				
				$sheet->setCellValue('B' . $row,$date);
				$sheet->setCellValue('C' . $row,$v['jev_no']);
				$sheet->setCellValue('E' . $row,$v['payor_payee']);
				// $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
								
				
				
					$newrow=0;
					foreach($v['jev_data'] as $l =>$m){
						
						//$sheet->setCellValue('D' . ($row+$merge),$m['acc_code']);
						// $sheet->setCellValue('E' . ($row+$merge),$merge);
						//$sheet->setCellValue('F' . ($row+$merge),$m['debit']);
						//$sheet->setCellValue('G' . ($row+$merge),$m['credit']);
						// $sheet->setCellValue('H' . ($row+$merge),$merge);
						if($m['acc_code']==="20201010"){
							$sheet->setCellValue('F' . $row,$m['credit']);

						
						}elseif($m['acc_code']==="20201070"){
							$sheet->setCellValue('G' . $row,$m['credit']);
						}elseif($m['acc_code']==="40102040"){
							$sheet->setCellValue('H' . $row,$m['credit']);

						}elseif($m['acc_code']==="40105020"){
							$sheet->setCellValue('I' . $row,$m['credit']);
						}elseif($m['acc_code']==="10102010"){
							$sheet->setCellValue('M' . $row,$m['debit']);
						}elseif($m['acc_code']==="10101010"){
							$sheet->setCellValue('N' . $row,$m['debit']);
						}elseif(substr($m['acc_code'], 0, 1) >= "2"){
							$sheet->setCellValue('J' . ($row+$newrow),$m['acc_code']);
							$sheet->setCellValue('K' . ($row+$newrow),$m['debit']);
							$sheet->setCellValue('L' . ($row+$newrow),$m['credit']);
							
							$newrow++;
							
						}else{
							$sheet->setCellValue('P' . ($row+$newrow),$m['acc_code']);
							$sheet->setCellValue('Q' . ($row+$newrow),$m['debit']);
							$sheet->setCellValue('R' . ($row+$newrow),$m['credit']);
							$newrow++;
						}
						
					
					
							
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
			$spreadsheet->getActiveSheet()->setCellValue('A4', 'Barangay ' . $_SESSION['currbrgy']);
			$spreadsheet->getActiveSheet()->setCellValue('A6', 'As of: '.date('F j, Y', strtotime($startDate)).' - '.date('F j, Y', strtotime($endDate)));
			$spreadsheet->getActiveSheet()->setCellValue('A7', 'Fund: General Fund');

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
				$sheet->setCellValue('F'.($main_row+5),'Judy G. Parado, CPA');
				$sheet->getStyle('F'.($main_row+5))
				->getFont()
				->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE);
				$sheet->mergeCells('F'.($main_row+6).':G'.($main_row+6));
				$sheet->setCellValue('F'.($main_row+6),'Municipal Accountant');

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
	
	// Convert the date strings to 'YYYY-MM-DD' format
	$startDate = date('Y-m-d', strtotime($this->input->post('sdate')));
	$endDate = date('Y-m-d', strtotime($this->input->post('edate')));
	
	//$this->getrrr_data($startDate,$endDate);
	
	$this->generateag_file($startDate,$endDate);
	
	

}


public function generateag_file($startDate,$endDate){
	$this->load->helper('download');
	$this->load->helper('file');
	
	//get data
	$result =$this->getaging_data("10305010","10305040",$startDate,$endDate);
	
	// $this->output
	// ->set_content_type('application/json')
	// ->set_output(json_encode($result));


	// Load PhpSpreadsheet library
	

	// Load the existing template
	$templatePath = FCPATH .'assets/templates/aging.xlsx';
	$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);

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
		$aging_category='';
		$check_date = strtotime($item->check_date);
		$current_date = time();
		$difference = $current_date - $check_date;
		$days_difference = floor($difference / (60 * 60 * 24));

    	$days_difference = floor($difference / (60 * 60 * 24));
		$sheet->setCellValue('A'.$row,$item->acc_title);
		$sheet->setCellValue('B'.$row,$item->payee);
		$sheet->setCellValue('C'.$row,$item->particulars);
		$sheet->setCellValue('D'.$row,$item->check_no);
		$sheet->setCellValue('E'.$row,date('Y-m-d',strtotime($item->check_date)));
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
	$sql = "SELECT
	jd.debit,
	jd.check_no,
	jd.check_date,
	jd.payee,
	jd.payor,
	jd.acc_title,
	j.particulars
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
	return  $query->result();
	// $this->output
	// ->set_content_type('application/json')
	// ->set_output(json_encode($query->result()));
	


}else{
	$this->session->set_flashdata('error', 'No Data Available for the specific Data range!');
		redirect('/aging_page');
}

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




		$b_array = array(
			'acc_code'=>$this->input->post('acc_code'),
			'bal_date'=>$bdate,
			'debit'=>$this->input->post('debit'),
			'credit'=>$this->input->post('credit'),
			'date_created'=> date('Y-m-d')
		);

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
