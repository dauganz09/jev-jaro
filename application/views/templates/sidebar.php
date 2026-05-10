
<div class="sidebar" id="sidebar">
<div class="sidebar-inner slimscroll">
<div id="sidebar-menu" class="sidebar-menu">
<ul>
<li class="menu-title">
<span>Main Menu</span>
</li>
<li class="<?php echo ($alink=='Dashboard') ? 'active' : '' ?>">
    <a href="<?php echo base_url('dashboard'); ?>"><i class="feather-grid"></i> <span> Dashboard</span></a>
</li>


<li class="<?php echo ($alink=='JEVLIST') ? 'active' : '' ?>">
<a href="<?php echo base_url('jevlist') ?>"><i class="fas fa-book"></i> <span>JEV LIST</span> </a>
</li>

<li class="<?php echo ($alink=='JEV' || $alink=='VJEV') ? 'active' : '' ?>">
<a href="<?php echo base_url('jev') ?>"><i class="fas fa-book"></i> <span> JEV</span> </a>
</li>

<li class="<?php echo ($alink=='JL') ? 'active' : '' ?>">
<a href="<?php echo base_url('journals') ?>"><i class="fas fa-book"></i> <span> Journals</span> </a>
</li>

<li class="<?php echo ($alink=='GL') ? 'active' : '' ?>">
<a href="<?php echo base_url('gl') ?>"><i class="fas fa-book"></i> <span> GL/SL</span> </a>
</li>


<li class="<?php echo ($alink=='TB') ? 'active' : '' ?>">
<a href="<?php echo base_url('tb_page') ?>"><i class="fas fa-book"></i> <span> Trial Balance</span> </a>
</li>

<li class="<?php echo ($alink=='FS') ? 'active' : '' ?>">
<a href="<?php echo base_url('fs_page') ?>"><i class="fas fa-book"></i> <span>FS</span> </a>
</li>
<li class="menu-title"> 
								<span>Other Reports</span>

</li>

<li class="<?php echo ($alink=='RRR') ? 'active' : '' ?>">
<a href="<?php echo base_url('rrr_page') ?>"><i class="fas fa-book"></i> <span>RRR</span> </a>
</li>

<li class="<?php echo ($alink=='SBA') ? 'active' : '' ?>">
<a href="<?php echo base_url('sba_page') ?>"><i class="fas fa-book"></i> <span>Statement of CBAA</span> </a>
</li>


<li class="<?php echo ($alink=='AGING') ? 'active' : '' ?>">
<a href="<?php echo base_url('aging_page') ?>"><i class="fas fa-book"></i> <span>Aging Report</span> </a>
</li>

<li class="<?php echo ($alink=='BANKRECON' || $alink=='BANKACCTS') ? 'active' : '' ?>">
<a href="<?php echo base_url('bank_recon') ?>"><i class="fas fa-university"></i> <span>Bank Reconciliation</span> </a>
</li>

<li class="menu-title"> 
								<span>System Maintenance</span>

</li>
<li class="<?php echo ($alink=='COA') ? 'active' : '' ?>">
<a href="<?php echo base_url('chart_of_accounts') ?>"><i class="fas fa-list-ol"></i> <span>Chart of Accounts</span> </a>
</li>
<li class="<?php echo ($alink=='BB') ? 'active' : '' ?>">
<a href="<?php echo base_url('bb_page') ?>"><i class="fas fa-book"></i> <span>Beginning Balance</span> </a>
</li>
<li class="<?php echo ($alink=='VBB') ? 'active' : '' ?>">
<a href="<?php echo base_url('vbb_page') ?>"><i class="fas fa-book"></i> <span>View Beginning Balance</span> </a>
</li>
<li class="<?php echo ($alink=='DB') ? 'active' : '' ?>">
<a href="<?php echo base_url('db_backup') ?>"><i class="fas fa-book"></i> <span>Backup Database</span> </a>
</li>

<li class="<?php echo ($alink=='LOGS') ? 'active' : '' ?>">
<a href="<?php echo base_url('logs') ?>"><i class="fas fa-book"></i> <span>System Logs</span> </a>
</li>

							


</ul>
</li>
</ul>
</div>
</div>
</div>