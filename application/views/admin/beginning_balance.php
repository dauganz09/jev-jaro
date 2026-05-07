<!-- Page Wrapper -->
 <?php
 $totalCredit = 0;
 $totalDebit = 0;
 
 ?>
<div class="page-wrapper">
    
				<div class="content container-fluid">
			
					<!-- Page Header -->
					<div class="page-header invoices-page-header">
						
<div>
<h2>Current Barangay: <?php  echo $_SESSION['currbrgy']; ?> - Set Beginning Balance</h2>
<input type="hidden" id="brgy" value="<?php  echo $_SESSION['currbrgyid']; ?>">
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
                                                <div class="col-xl-4 col-md-4 col-sm-12">
                                                    <button type="button" class="btn btn-success" id="upload_bb">
                                                        Upload Beginning Balance
                                                    </button>
                                                </div>
                                            </div>
                                            <hr>
											<div class="row">
                                                
												<div class="col-xl-2 col-md-2 col-sm-12">
                                                    <div class="form-group">
                                                    <label for="">Year</label>
                                                        <select class="form-control"  id="bb_year">
                                                        
                                                        
                                                        </select>
                                                    </div>
                                                </div>
											</div>
                                            
										</div>
										
										<div class="invoice-add-table">
											
											<div class="table-responsive">
												<table class="table table-center add-table-items" id="dc_table">
													<thead>
														<tr>
															
															<th>Accounts and Explanation</th>
                                                            
															<th >Account Code</th>
															<th>Debit</th>
															<th>Credit</th>
                                                            <th></th>
															
														</tr>
													</thead>
													<tbody>
                                                    <?php
                                                    
                                                    
                                                    if (!empty($this->session->data)): 
                                                            $first_key = key($this->session->data);

                                                        ?>
                                                        
                                                        <?php foreach($this->session->data as $key => $r):
                                                            $totalDebit +=$r[2];
                                                            $totalCredit +=$r[3];
                                                            ?>
                                                            
                                                            <?php if ($key === $first_key):?>
                                                                <tr class="add-row">
															
															<td >
																<input type="text" value="<?php echo $r[0]; ?>"  class="form-control acct_t input-fields">
															</td>
                                                           
															<td>
																<input type="text" value="<?php echo $r[1]; ?>" class="form-control acct_c input-fields">
															</td>
															<td>
																<input type="text" value="<?php echo $r[2]; ?>" class="form-control debitInput"  placeholder="0.00">
															</td>
															<td>
																<input type="text" value="<?php echo $r[3]; ?>" class="form-control creditInput"  placeholder="0.00">
															</td>
															
															<td class="add-remove text-end">
																<a href="javascript:void(0);" class="add-btn me-2"><i class="fas fa-plus-circle"></i></a> 
															</td>
														</tr>
                                                            
                                                            <?php
                                                             continue;
                                                            endif;
                                                            
                                                            ?>
														<tr class="add-row">
															
															<td >
																<input type="text" value="<?php echo $r[0]; ?>"  class="form-control acct_t input-fields">
															</td>
                                                           
															<td>
																<input type="text" value="<?php echo $r[1]; ?>" class="form-control acct_c input-fields">
															</td>
															<td>
																<input type="text" value="<?php echo $r[2]; ?>" class="form-control debitInput"  placeholder="0.00">
															</td>
															<td>
																<input type="text" value="<?php echo $r[3]; ?>" class="form-control creditInput"  placeholder="0.00">
															</td>
															
															<td class="add-remove text-end">
                                                                <a href="javascript:void(0);" class="remove-btn"><i class="fe fe-trash-2"></i></a>
															</td>
														</tr>
                                                        <?php endforeach;
                                                           
                                                        ?>
                                                         <?php else:?>
                                                         <tr class="add-row">
															
															<td >
																<input type="text"  class="form-control acct_t input-fields">
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

                                                        <?php endif;?>
													</tbody>
                                                    <tfooter>
                                                    <tr>
															
																<th></th>
															</th>
                                                           
															<th>
																Total
															</th>
															<th id="totalDebit">
																<?php echo $totalDebit; ?>
															</th>
															<th id="totalCredit">
                                                                    <?php echo $totalCredit; ?>
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
										
									</form>
                                     
                                    
                                    <hr>
                                    <div class="row">
											<div class="col-lg-3 col-md-6">
                                            <div class="form-group">
														
														<button class="btn btn-primary btn-lg "  id="saveBtn" <?php $totalCredit !== $totalDebit ?? 'disabled' ?>> SAVE</button>
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

    <div class="modal fade" id="upload_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" >
		<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Upload Beginning Balance</h5>
					<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
						<i aria-hidden="true" class="fa fa-times"></i>
					</button>
				</div>
				<div class="modal-body">
                    <div class="row">
                                                <div class="col-xl-4 col-md-4 col-sm-12">
                                                        <a href="<?php echo BASE_URL('downloadtemplate')?>" class="btn btn-success" >
                                                        <i class="fas fa-download"></i>
                                                        Download Template
</a>
                                                    </div>
                                    

                    </div>
                    <hr>
                   
                                                <div class="col-xl-12 col-md-12 col-sm-12">
                                                    <form action="<?php echo BASE_URL('uploadbb'); ?>" method="POST" enctype="multipart/form-data">
                                                        <input type="file" class="form-control" id="fileInput" name="fileInput" accept=".xlsx, .xls, .csv" required />
                                                        
                                                </div>
                    

				</div>
				<div class="modal-footer">
                
					<button type="button" class="btn btn-light-primary font-weight-bold" data-bs-dismiss="modal">Close</button>
                  
                    <button type="submit" class="btn btn-primary font-weight-bold">Upload</button>
                    </form>
					
				</div>
			</div>
		</div>
	</div>
