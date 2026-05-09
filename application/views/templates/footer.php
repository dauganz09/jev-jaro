
<footer>
<p>JARO JEV SYSTEM| Copyright © 2024</p>
</footer>

</div>

</div>


<script src="<?php echo base_url('assets'); ?>/js/jquery-3.6.0.min.js"></script>

<script src="<?php echo base_url('assets'); ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/js/moment.js"></script>
<script src="<?php echo base_url('assets'); ?>/js/feather.min.js"></script>

<script src="<?php echo base_url('assets'); ?>/plugins/slimscroll/jquery.slimscroll.min.js"></script>

<script src="<?php echo base_url('assets'); ?>/plugins/apexchart/apexcharts.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/plugins/apexchart/chart-data.js"></script>

<?php if($this->uri->segment(1) == 'dashboard'): ?>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js'></script>
<?php endif; ?>
<!-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> -->
<script src="<?php echo base_url('assets'); ?>/js/sweetalert.js"></script>
<!-- Select2 JS -->
<script src="<?php echo base_url('assets'); ?>/plugins/select2/js/select2.min.js"></script>

<!-- Datepicker Core JS -->
<script src="<?php echo base_url('assets'); ?>/plugins/moment/moment.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/js/bootstrap-datetimepicker.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/js/circle-progress.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/plugins/toastr/toastr.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/plugins/toastr/toastr.js"></script>
<script src="<?php echo base_url('assets'); ?>/plugins/datatables/datatables.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/js/script.js"></script>
<?php if(
	$this->uri->segment(1) == 'tb_page' ||
	$this->uri->segment(1) == 'fs_page' ||
	$this->uri->segment(1) == 'journals' ||
	$this->uri->segment(1) == 'gl'
): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable@14.5.0/dist/handsontable.full.min.css">
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/handsontable@14.5.0/dist/handsontable.full.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/js/spreadsheet-preview.js"></script>
<?php endif; ?>
<?php if($this->uri->segment(1) == 'dashboard' || $this->uri->segment(1) == 'jev'  || $this->uri->segment(1) == 'jevlist' || $this->uri->segment(1) == 'vbb_page'): ?>
<script src="<?php echo base_url('assets'); ?>/js/scripts.js"></script>
<!-- Mask JS -->
<script src="<?php echo base_url('assets'); ?>/js/jquery.maskedinput.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/js/mask.js"></script>
<?php endif; ?>
<?php if($this->uri->segment(1) == 'viewjev'):?>
<script src="<?php echo base_url('assets'); ?>/js/viewjev.js"></script>
<script src="<?php echo base_url('assets'); ?>/js/jquery.maskedinput.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/js/mask.js"></script>
<?php endif;?>
<?php if($this->uri->segment(1) == 'tb_page'):?>
    <script src="<?php echo base_url('assets'); ?>/js/reports.js"></script>
<?php endif;?>

<?php if($this->uri->segment(1) == 'journals'):?>
    <script src="<?php echo base_url('assets'); ?>/js/journals.js"></script>
<?php endif;?>


<?php if($this->uri->segment(1) == 'fs_page'):?>
    <script src="<?php echo base_url('assets'); ?>/js/fs.js"></script>
<?php endif;?>


<?php if($this->uri->segment(1) == 'gl'):?>
    <script src="<?php echo base_url('assets'); ?>/js/gl.js"></script>
<?php endif;?>



<?php if($this->uri->segment(1) == 'rrr_page'):?>
    <script src="<?php echo base_url('assets'); ?>/js/sba.js"></script>
<?php endif;?>


<?php if($this->uri->segment(1) == 'sba_page'):?>
    <script src="<?php echo base_url('assets'); ?>/js/rrr.js"></script>
<?php endif;?>


<?php if($this->uri->segment(1) == 'aging_page'):?>
    <script src="<?php echo base_url('assets'); ?>/js/aging.js"></script>
<?php endif;?>


<?php if($this->uri->segment(1) == 'logs'):?>
    <script src="<?php echo base_url('assets'); ?>/js/log.js"></script>
<?php endif;?>


<?php if($this->uri->segment(1) == 'bb_page' || $this->uri->segment(1) == 'uploadbb'):?>
    <script src="<?php echo base_url('assets'); ?>/js/bb_scripts.js"></script>
<!-- Mask JS -->
<script src="<?php echo base_url('assets'); ?>/js/jquery.maskedinput.min.js"></script>
<script src="<?php echo base_url('assets'); ?>/js/mask.js"></script>
<?php endif;?>

<script src="<?php echo base_url('assets'); ?>/js/resp.js"></script>

</body>

<!-- Mirrored from preschool.dreamguystech.com/template/teacher-dashboard.html by HTTrack Website Copier/3.x [XR&CO'2014], Wed, 30 Aug 2023 05:47:59 GMT -->
</html>