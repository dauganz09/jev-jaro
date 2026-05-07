<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Fs extends CI_Model { 

    
	public function __construct()
	{
		parent::__construct();
		
	}

    public function getJevDataWithAccountCode($startDate, $endDate, $brgy, $accountCode, $subtractCredits = true) {
        $this->db->select('j.jev_no, j.jev_date, j.brgy, SUM(jd.debit) AS total_debit, SUM(jd.credit) AS total_credit');
    
        if ($subtractCredits) {
            $this->db->select('IFNULL(SUM(jd.debit), 0) AS total_debit, IFNULL(SUM(jd.credit), 0) AS total_credit');
            $this->db->select('IFNULL(SUM(jd.debit - jd.credit), 0) AS net_balance');
        } else {
            $this->db->select('IFNULL(SUM(jd.debit), 0) AS total_debit, IFNULL(SUM(jd.credit), 0) AS total_credit');
            $this->db->select('IFNULL(SUM(jd.credit - jd.debit), 0) AS net_balance');
        }
    
        $this->db->from('tbl_jev j');
        $this->db->join('tbl_jevdata jd', 'j.jev_no = jd.jev_no', 'left');
        $this->db->where('j.jev_date BETWEEN "' . $startDate . '" AND "' . $endDate . '"');
        $this->db->where('j.brgy', $brgy);
    
        if (is_array($accountCode)) {
            // If $accountCode is an array, consider it as a range
            $this->db->where('jd.acc_code BETWEEN "' . $accountCode[0] . '" AND "' . $accountCode[1] . '"');
        } else {
            // If $accountCode is a single value, consider it as a specific account code
            $this->db->where('jd.acc_code', $accountCode);
        }
    
        $this->db->group_by('j.jev_no, j.jev_date, j.brgy');
        
        return $query = $this->db->get();
        
    }
    


}


?>