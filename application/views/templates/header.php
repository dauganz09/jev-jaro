<!DOCTYPE html>
<html lang="en">

<!-- Mirrored from preschool.dreamguystech.com/template/teacher-dashboard.html by HTTrack Website Copier/3.x [XR&CO'2014], Wed, 30 Aug 2023 05:47:54 GMT -->
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
<title>JEV - JARO  <?php echo $title;?></title>

<link rel="shortcut icon" href="<?php echo base_url('assets'); ?>/img/favicon.png">

<link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;0,900;1,400;1,500;1,700&amp;display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/plugins/bootstrap/css/bootstrap.min.css">

<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/plugins/feather/feather.css">
<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/plugins/icons/feather/feather.css">

<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/plugins/icons/flags/flags.css">

<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/plugins/fontawesome/css/fontawesome.min.css">
<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/plugins/fontawesome/css/all.min.css">

<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/plugins/simple-calendar/simple-calendar.css">
<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/plugins/toastr/toatr.css">
<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/plugins/datatables/datatables.min.css">
<!-- Select2 CSS -->
<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/plugins/select2/css/select2.min.css">
		
		<!-- Datepicker CSS -->
		<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/css/bootstrap-datetimepicker.min.css">

<link rel="stylesheet" href="<?php echo base_url('assets'); ?>/css/style.css">

<style>
    /* Add this style to change cursor on hover */
    #tbl_accounts tbody tr:hover {
        cursor: pointer;
		background-color: #e0e0e0;
    }

    #tbl_acclist tbody tr:hover {
        cursor: pointer;
		background-color: #e0e0e0;
    }

    #tbl_acclist tbody tr.selected {
        background-color: #a7cdf2;
    }

    .text-wrap{
    white-space:normal;
}
.width-200{
    width:250px;
}


</style>
</head>
<body>

<div class="main-wrapper">

<div class="header">

<div class="header-left">
<a href="#" class="logo">
<img src="<?php echo base_url('assets'); ?>/img/jaro_logo.jpg" alt="Logo">
JARO - JEV
</a>
<a href="#" class="logo logo-small">
<img src="<?php echo base_url('assets'); ?>/img/jaro_logo.jpg" alt="Logo" width="30" height="30">
</a>
</div>

<div class="menu-toggle">
<a href="javascript:void(0);" id="toggle_btn">
<i class="fas fa-bars"></i>
</a>
</div>




<a class="mobile_btn" id="mobile_btn">
<i class="fas fa-bars"></i>
</a>

<ul class="nav user-menu">





<li class="nav-item dropdown has-arrow new-user-menus">
<a href="#" class="dropdown-toggle nav-link" data-bs-toggle="dropdown">
<span class="user-img">
<img class="rounded-circle" src="<?php echo base_url('assets'); ?>/img/profiles/avatar-03.jpg" width="31" alt="MDRRMO">
<div class="user-text">
<h6><?php echo $_SESSION['fname'].' '.$_SESSION['lname']; ?></h6>
<p class="text-muted mb-0"><?php echo $_SESSION['position'];?></p>
</div>
</span>
</a>
<div class="dropdown-menu">


<a class="dropdown-item" href="<?php echo base_url('logout'); ?>">Logout</a>
</div>
</li>

</ul>

</div>
