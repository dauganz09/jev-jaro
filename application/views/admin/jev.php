<!-- Page Wrapper -->
<div class="page-wrapper">
				<div class="content container-fluid">
			
					<!-- Page Header -->
					<div class="page-header invoices-page-header">
						
<div>
<h2>Current Barangay: <?php  echo $_SESSION['currbrgy']; ?></h2>
<input type="hidden" id="brgy" value="<?php  echo $_SESSION['currbrgyid']; ?>">
</div>
						<div class="row align-items-center">
							
							<div class="col-auto">
								<div class="invoices-create-btn">
										
									
								</div>
							</div>
						</div>
					</div>
					<!-- /Page Header -->
					
					<div class="row">
						<div class="col-md-12">
							<div class="card invoices-add-card">
								<div class="card-body">
									<form action="#" class="invoices-form">
										<div class="invoices-main-form">
											<div class="row">
												<div class="col-xl-4 col-md-6 col-sm-12 col-12">
													
													<div class="form-group">
														<label>JEV No.</label>
														<input class="form-control" id="jev_no" type="text" placeholder="Enter JEV No">
														<span class="form-text text-muted">e.g 24-01-0001</span>
													</div>
												</div>
												<div class="col-xl-2 col-md-2 col-sm-12 col-12">
                                                <div class="form-group">
														<label>Date</label>
														<input class="form-control" id="jev_date" type="text">
													</div>

<!-- 													
													<div class="invoice-details-box">
														
														<div class="invoice-inner-footer">
															<div class="row align-items-center">
																<div class="col-lg-6 col-md-6">
																	<div class="invoice-inner-date">
																		<span>
																			Date <input class="form-control datetimepicker" type="text" placeholder="15/02/2022">
																		</span>
																	</div>
																</div>
																
															</div>
														</div>
													</div> -->
												</div>
												<div class="col-xl-3 col-md-3 col-sm-12 col-12">
                                                <div class="form-group">
														<label>Fund</label>
														<input class="form-control" id="fund" type="text" value="General Fund" readonly>
													</div>

<!-- 													
													<div class="invoice-details-box">
														
														<div class="invoice-inner-footer">
															<div class="row align-items-center">
																<div class="col-lg-6 col-md-6">
																	<div class="invoice-inner-date">
																		<span>
																			Date <input class="form-control datetimepicker" type="text" placeholder="15/02/2022">
																		</span>
																	</div>
																</div>
																
															</div>
														</div>
													</div> -->
												</div>
												
											</div>

                                            <div class="row">
												<div class="col-xl-2 col-md-2 col-lg-2 col-sm-12 col-12">
													
													<div class="form-group">
														
														<input class="form-check-input" name="jev_type" value="COL" type="radio" checked>
                                                        <label class="form-check-label" for="gender_male">
															Cash Receipt
															</label>
													</div>
												</div>
												<div class="col-xl-2 col-md-2 col-lg-2 col-sm-12 col-12">
                                                <div class="form-group">
														
														<input class="form-check-input" name="jev_type" value="CKD"  type="radio">
                                                        <label class="form-check-label" for="gender_male">
															Check Disbursement
															</label>
													</div>
												</div>

                                                <div class="col-xl-2 col-md-2 col-lg-2 col-sm-12 col-12">
                                                <div class="form-group">
														
														<input class="form-check-input" name="jev_type" value="CSD"  type="radio">
                                                        <label class="form-check-label" for="gender_male">
															Cash Disbursement
															</label>
													</div>
												</div>

                                                <!-- <div class="col-xl-3 col-md-3 col-sm-12 col-12">
                                                <div class="form-group">
														
														<input class="form-check-input" name="jev_type" value="GJ" type="radio">
                                                        <label class="form-check-label" for="gender_male">
															General Journal
															</label>
													</div>
												</div> -->
												<div class="col-xl-2 col-md-2 col-lg-2 col-sm-12 col-12">
                                                <div class="form-group">
														
														<input class="form-check-input" name="jev_type" value="GJ"  type="radio">
                                                        <label class="form-check-label" for="gender_male">
															General Journal
															</label>
													</div>
												</div>
                                            
												
											</div>
											
												
                                                    
                                               
										</div>
										
										<div class="invoice-add-table">
											
											<div class="table-responsive">
												<table class="table table-center add-table-items" id="dc_table">
													<thead>
														<tr>
															<th>Center</th>
															<th>Accounts and Explanation</th>
                                                            
															<th >Account Code</th>
															<th>Debit</th>
															<th>Credit</th>
                                                            <th></th>
															
														</tr>
													</thead>
													<tbody>
														<tr class="add-row">
															<td>
																<input type="text" class="form-control">
															</td>
															<td >
																<input type="text" class="form-control acct_t input-fields">
															</td>
                                                           
															<td>
																<input type="text" class="form-control acct_c input-fields">
															</td>
															<td>
																<input type="text" class="form-control debitInput"  placeholder="0.00">
															</td>
															<td>
																<input type="text" class="form-control creditInput"  placeholder="0.00">
															</td>
															
															<td class="add-remove text-end">
																<a href="javascript:void(0);" class="add-btn me-2"><i class="fas fa-plus-circle"></i></a> 
															</td>
														</tr>
													</tbody>
                                                    <tfooter>
                                                    <tr>
															<th>
																
															<th >
																
															</th>
                                                           
															<th>
																Total
															</th>
															<th id="totalDebit">
																0.00
															</th>
															<th id="totalCredit">
																0.00
															</th>
															
															
														</tr>
                                                    </tfooter>
												</table>
                                               
											</div>
										</div>
											
	<!--Accounts Modal-->
	<div class="modal fade" id="account_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" >
		<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Select Account</h5>
					<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
						<i aria-hidden="true" class="fa fa-times"></i>
					</button>
				</div>
				<div class="modal-body">
				
					<table class="table border-0  table-hover  mb-0 table-striped" id="tbl_accounts">
							<thead>
								
								
								<th>Account Code</th>
								<th>Account Title</th>

								
							</thead>
							<tbody>


							</tbody>
					</table>
				
                    


				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light-primary font-weight-bold" data-bs-dismiss="modal">Close</button>
					
				</div>
			</div>
		</div>
	</div>
	<!-- end modal -->
										<div class="row">
											<div class="col-lg-6 col-md-6">
                                                    <div class="form-group">
														<label>Payor/Payee</label>
														<input class="form-control" id="payor" type="text">
													</div>
													<div id="col_div">
														<div class="form-group">
															<label>OR No./AR No.</label>
															<input class="form-control col-lg-4" id="or_no" type="text" placeholder="Enter OR No">
														</div>
														<div class="form-group">
															<label>OR Date.</label>
															<input class="form-control col-lg-4" id="or_date" id="or_date" type="text">
														</div>
													</div>
													<div id="ckd_div">
														<div class="form-group">
															<label>Voucher No.</label>
															<input class="form-control col-lg-4" id="v_no" type="text">
														</div>
														<div class="form-group">
															<label>Check No.</label>
															<input class="form-control col-lg-4" id="chk_no"  type="text">
														</div>
														<div class="form-group">
															<label>Check Date</label>
															<input class="form-control col-lg-4" id="chk_date"  type="text">
														</div>
															<div class="form-group">
															<label>Bank Account</label>
															<input class="form-control col-lg-4" id="bank_acct"  type="text">
														</div>

													</div>
													<div id="csd_div">
														<div class="form-group">
															<label>Voucher No.</label>
															<input class="form-control col-lg-4" id="vc_no" type="text">
														</div>
														
													</div>

													<div id="gj_div">
														<div class="form-group">
															<label>Invoice No.</label>
															<input class="form-control col-lg-4" id="iv_no" type="text" placeholder="Enter Invoice No">
														</div>
														<div class="form-group">
															<label>Invoice Date.</label>
															<input class="form-control col-lg-4" id="iv_date" id="or_date" type="text">
														</div>
														<div class="form-group">
															<label>PO No.</label>
															<input class="form-control col-lg-4" id="po_no" type="text" placeholder="Enter PO No">
														</div>
														<div class="form-group">
															<label>PO Date.</label>
															<input class="form-control col-lg-4" id="po_date" id="or_date" type="text">
														</div>
														<div class="form-group">
															<label>Check No.</label>
															<input class="form-control col-lg-4" id="chk_no"  type="text">
														</div>
														<div class="form-group">
															<label>Check Date</label>
															<input class="form-control col-lg-4" id="chk_date"  type="text">
														</div>


														
													</div>
                                                   
												
												
											</div>
											<div class="col-lg-6 col-md-6">
                                            <div class="form-group">
														<label>Particulars</label>
														<textarea class="form-control" id="parts" rows="20"></textarea>
										    </div>
											<div class="form-group">
														<label>Resp Center.</label>
														<input class="form-control col-lg-4" id="resp_center" type="text" placeholder="Resp Center">
													</div>
												
											</div>
                                            
                                          
											</div>
										</div>
									</form>
                                     <div class="row">
                                     <div class="col-lg-6 col-md-6">
                                                <label>Prepared by: </label>
                                                <div><u><?php echo $_SESSION['fname'].' '.$_SESSION['lname']; ?></u></div>
                                     </div>  
                                     <div class="col-lg-6 col-md-6">
                                                <label>Approved by: </label>
											    <div><u>JUDY G. PARADO, CPA</u></div>
                                     </div>   


                                    </div>
                                    <div class="row">
                                     <div class="col-lg-6 col-md-6">
                                                <label> </label>
											    <div><?php echo $_SESSION['position'];?></div>
                                     </div>  
                                     <div class="col-lg-6 col-md-6">
                                                <label></label>
											    <div>Municipal Accountant</div>
                                     </div>   


                                    </div>
                                    <hr>
                                    <div class="row">
                                        
                                            <div class="col-lg-6 col-md-6">
                                            
												
											</div>
											
											<div class="col-lg-3 col-md-6">
											<div class="form-group">
											<button   class="btn btn-primary btn-lg">
												Save Template
											</button>
											</div>
											</div>
											<div class="col-lg-3 col-md-6">
                                            <div class="form-group">
														
														<button class="btn btn-primary btn-lg" id="saveBtn" disabled> SAVE</button>
																	    </div>
											
											</div>
                                            
                                          
											</div>
										</div>

								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- /Page Wrapper -->


			
	<!--Accounts Modal-->
	<div class="modal fade" id="account_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" >
		<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Select Account</h5>
					<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
						<i aria-hidden="true" class="fa fa-times"></i>
					</button>
				</div>
				<div class="modal-body">
				
					<table class="table border-0  table-hover  mb-0 table-striped" id="tbl_accounts">
							<thead>
								
								
								<th>Account Code</th>
								<th>Account Title</th>

								
							</thead>
							<tbody>


							</tbody>
					</table>
				
                    


				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light-primary font-weight-bold" data-bs-dismiss="modal">Close</button>
					
				</div>
			</div>
		</div>
	</div>
