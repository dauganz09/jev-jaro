$(document).ready(function () {
	const BASE_URL = "http://localhost/jev-jaro/";

	//reports logic
	var currentYear = new Date().getFullYear();
	var startYear = currentYear - 5;
	var endYear = currentYear;

	for (var year = endYear; year >= startYear; year--) {
		$("#tb_year").append(
			$("<option>", {
				value: year,
				text: year,
			})
		);
	}

	for (var year = endYear; year >= startYear; year--) {
		$("#bb_year").append(
			$("<option>", {
				value: year,
				text: year,
			})
		);
	}

	var currentDate = new Date();

	//hide divs
	$("#col_div").show();
	$("#ckd_div").hide();
	$("#csd_div").hide();
	$("#gj_div").hide();

	//end

	//accounts table jev

	const tbl_accounts = $("#tbl_accounts").DataTable({
		pagingType: "full_numbers",
		lengthMenu: [
			[-1, 70, 100],
			["All", 70, 100],
		],
		scrollY: "300px",
		scrollCollapse: true,

		searchable: true,
		responsive: true,
		language: {
			search: "_INPUT_",
			info: "Showing _START_ to _END_ of _TOTAL_ Accounts",
			loadingRecords: "Loading Accounts.....",
			searchPlaceholder: "Search Accounts....",
			infoFiltered: "(filtered from _MAX_ total Accounts)",
			zeroRecords: "No Accounts found",
		},

		columnDefs: [],
	});

	//end

	//accounts table jev

	const tbl_jev = $("#tbl_jevlist").DataTable({
		pagingType: "full_numbers",
		lengthMenu: [
			[30, 50, 70, 100, -1],
			[30, 50, 70, 100, "All"],
		],

		searchable: true,
		responsive: true,
		language: {
			search: "_INPUT_",
			info: "Showing _START_ to _END_ of _TOTAL_ JEV",
			loadingRecords: "Loading JEV.....",
			searchPlaceholder: "Search JEV....",
			infoFiltered: "(filtered from _MAX_ total JEV)",
			zeroRecords: "No JEV found",
		},

		columnDefs: [
			{
				render: function (data, type, full, meta) {
					return "<div class='text-wrap width-200'>" + data + "</div>";
				},
				targets: 3,
			},
		],
	});

	//end

	// bb list
	const tbl_bb = $("#tbl_bblist").DataTable({
		pagingType: "full_numbers",
		lengthMenu: [
			[30, 50, 70, 100, -1],
			[30, 50, 70, 100, "All"],
		],

		searchable: true,
		responsive: true,
		language: {
			search: "_INPUT_",
			info: "Showing _START_ to _END_ of _TOTAL_ Balances",
			loadingRecords: "Loading JEV.....",
			searchPlaceholder: "Search JEV....",
			infoFiltered: "(filtered from _MAX_ total Balances)",
			zeroRecords: "No Beginning Balance found",
		},
	});

	// end bb list

	// load respondents
	const loadJev = function () {
		let brgyid = $("#brgy").val();
		//load

		$.get(`${BASE_URL}getjevlist/${brgyid}`, function (response) {
			console.log(response);

			tbl_jev.clear();

			$.each(response, function (i, key) {
				let type = "";
				let status;
				if (key.type == "COL") {
					type = "Cash Receipt/Collections";
				} else if (key.type == "CKD") {
					type = "Check Disbursement";
				} else if (type == "CSD") {
					type = "Cash Disbursement";
				} else {
					type = "General Journal";
				}

				let date = new Date(key.jev_date);
				let fdate = date.toLocaleDateString();

				if (key.status == 0) {
					status = `<span class="label bg-warning">Pending</span>`;
				} else {
					status = `<span class="label bg-success">Approved</span>`;
				}

				tbl_jev.row.add([
					key.jev_no,
					type,
					fdate,
					key.particulars,
					key.payor_payee,
					status,
					`<a href="${BASE_URL}viewjev/${key.jev_id}" class="btn btn-sm bg-success-light me-2">
      <i class="feather-eye"></i>
      </a>
      <button class="btn btn-sm bg-danger me-2 delete-jev" data-id="${key.jev_id}"> <i class="feather-delete"></i></button>
      
     `,
				]);
			});
			tbl_jev.draw();
		});

		//end lod
	};

	loadJev();

	//load beginning balances
	const loadBB = function () {
		let brgyid = $("#brgy").val();
		//load

		$.get(`${BASE_URL}getbbs/${brgyid}`, function (response) {
			tbl_bb.clear();

			$.each(response, function (i, key) {
				tbl_bb.row.add([
					i + 1,
					key.year,
					key.total_credit,

					`<a href="${BASE_URL}viewbb/${brgyid}/${key.year}" class="btn btn-sm bg-success-light me-2">
      <i class="feather-eye"></i>
      </a>
      <button class="btn btn-sm bg-danger me-2 delete-bb"> <i class="feather-delete"></i></button>
     `,
				]);
			});
			tbl_bb.draw();
		});
	};

	loadBB();

	$("#filter_bb").click(function () {
		let selectedYear = $("#bb_year").val();
		let brgyid = $("#brgy").val();

		$.get(`${BASE_URL}getbbs2/${brgyid}/${selectedYear}`, function (response) {
			tbl_bb.clear();

			$.each(response, function (i, key) {
				tbl_bb.row.add([
					i + 1,
					key.year,
					key.total_credit,

					`<a href="${BASE_URL}viewbb/${brgyid}/${selectedYear}" class="btn btn-sm bg-success-light me-2">
      <i class="feather-eye"></i>
      </a>
      <button class="btn btn-sm bg-danger me-2 delete-bb"> <i class="feather-delete"></i></button>
     `,
				]);
			});
			tbl_bb.draw();
		});
	});

	// end load

	// listen to row click
	// Handle row click event
	$("#tbl_accounts tbody").on("click", "tr", function () {
		// Get data from the clicked row
		var rowData = tbl_accounts.row(this).data();

		// Log the data to the console (you can do anything with it)
		console.log(rowData);

		// If you want to access specific columns, you can use indexes
		var code = rowData[0];
		var title = rowData[1];

		// Get the input fields that triggered the modal
		var triggeringInputs = $("#account_modal").data("triggeringInputs");

		// Set values from the clicked row to the triggering input fields
		triggeringInputs.code.val(code);
		triggeringInputs.title.val(title);

		$("#account_modal").modal("hide");
	});

	//end

	//keyboard
	// Handle keyboard navigation
	$("#tbl_accounts tbody").on("keydown", "tr", function (e) {
		if (e.keyCode === 38) {
			// Up arrow key
			var prevRow = $(this).prev("tr");
			if (prevRow.length > 0) {
				prevRow.focus();
			}
		} else if (e.keyCode === 40) {
			// Down arrow key
			var nextRow = $(this).next("tr");
			if (nextRow.length > 0) {
				nextRow.focus();
			}
		}
	});

	//end

	// add respondents
	$("#addrespbtn").click(function () {
		console.log("clicked");
	});

	// end add respondents

	//delete jev
	$("#tbl_jevlist").on("click", ".delete-jev", function () {
		let jevid = $(this).attr("data-id");
		Swal.fire({
			title: "Are you sure?",
			text: "You won't be able to revert this!",
			icon: "warning",
			showCancelButton: true,
			confirmButtonColor: "#3085d6",
			cancelButtonColor: "#d33",
			confirmButtonText: "Yes, delete it!",
		}).then((result) => {
			if (result.isConfirmed) {
				$.post(`${BASE_URL}deletejev`, { jevid: jevid }, function (res) {
					if (res) {
						Swal.fire({
							title: "Deleted!",
							text: "JEV has been deleted.",
							icon: "success",
						});

						window.location.assign(`${BASE_URL}jevlist`);
					} else {
						Swal.fire({
							title: "Error!",
							text: "JEV cannot be deleted.",
							icon: "error",
						});
					}
				});
			}
		});
	});

	//end delete jev

	// jev table

	$(".add-table-items").on("click", ".remove-btn", function () {
		$(this).closest(".add-row").remove();
		updateTotal();
		updateSaveButton();
		return false;
	});

	$(document).on("click", ".add-btn", function () {
		var experiencecontent =
			'<tr class="add-row">' +
			"<td>" +
			'<input type="text" class="form-control">' +
			"</td>" +
			"<td>" +
			'<input type="text" class="form-control acct_t input-fields">' +
			"</td>" +
			"<td>" +
			'<input type="text" class="form-control acct_c input-fields">' +
			"</td>" +
			"<td>" +
			'<input type="text" class="form-control debitInput"  placeholder="0.00">' +
			"</td>" +
			"<td>" +
			'<input type="text" class="form-control creditInput"  placeholder="0.00">' +
			"</td>" +
			'<td class="add-remove text-end">' +
			'<a href="javascript:void(0);" class="remove-btn"><i class="fe fe-trash-2"></i></a>' +
			"</td>" +
			"</tr>";

		$(".add-table-items").append(experiencecontent);
		// Update the total when values change
		updateTotal();

		// Enable or disable the save button based on totals
		updateSaveButton();
		return false;
	});

	// end jev table

	//datepicker

	$("#jev_date").datetimepicker({
		format: "MM/D/YYYY",
		defaultDate: currentDate,
		showTodayButton: true,
		icons: {
			up: "fas fa-angle-up",
			down: "fas fa-angle-down",
			next: "fas fa-angle-right",
			previous: "fas fa-angle-left",
		},
	});

	$("#or_date").datetimepicker({
		format: "MM/D/YYYY",
		defaultDate: currentDate,
		showTodayButton: true,
		icons: {
			up: "fas fa-angle-up",
			down: "fas fa-angle-down",
			next: "fas fa-angle-right",
			previous: "fas fa-angle-left",
		},
	});

	$("#chk_date").datetimepicker({
		format: "MM/D/YYYY",
		defaultDate: currentDate,
		showTodayButton: true,
		icons: {
			up: "fas fa-angle-up",
			down: "fas fa-angle-down",
			next: "fas fa-angle-right",
			previous: "fas fa-angle-left",
		},
	});

	$("#sdate").datetimepicker({
		format: "MM/D/YYYY",
		defaultDate: currentDate,
		showTodayButton: true,
		icons: {
			up: "fas fa-angle-up",
			down: "fas fa-angle-down",
			next: "fas fa-angle-right",
			previous: "fas fa-angle-left",
		},
	});

	$("#edate").datetimepicker({
		format: "MM/D/YYYY",
		defaultDate: currentDate,
		showTodayButton: true,
		icons: {
			up: "fas fa-angle-up",
			down: "fas fa-angle-down",
			next: "fas fa-angle-right",
			previous: "fas fa-angle-left",
		},
	});

	//end

	//account title

	// Event handler for input keypress
	$(document).on("keypress", ".acct_t", function (e) {
		// Check if the key pressed is 'Enter' (key code 13)
		if (e.which === 13) {
			// Get the value of the clicked input
			var nameValue = $(this).val();
			var codeInput = $(this).closest("tr").find(".acct_c");
			var titleInput = $(this).closest("tr").find(".acct_t");
			let modal = $("#account_modal");
			modal.data("triggeringInputs", { code: codeInput, title: titleInput });
			modal.modal("show");
			getAccounts();
			tbl_accounts.search(nameValue).draw();
		}
	});

	// Event handler for input keypress
	$(document).on("keypress", ".acct_c", function (e) {
		// Check if the key pressed is 'Enter' (key code 13)
		if (e.which === 13) {
			// Get the value of the clicked input
			let nameValue = $(this).val();

			var codeInput = $(this).closest("tr").find(".acct_c");
			var titleInput = $(this).closest("tr").find(".acct_t");
			let modal = $("#account_modal");
			modal.data("triggeringInputs", { code: codeInput, title: titleInput });
			modal.modal("show");
			getAccounts();
			tbl_accounts.search(nameValue).draw();
		}
	});

	//end

	//generate tb
	$("#generate_tb").click(function () {
		let sdate = $("#sdate").val();
		let edate = $("#edate").val();

		$.post(`${BASE_URL}tb`, {}, function (res) {});
	});

	//end

	//change curr barangay
	$("#save_curr_brgy").click(function () {
		let brgy_id = $("#curr_brgy").val();
		let brgy = $("#curr_brgy option:selected").text();
		console.log(brgy_id, brgy);
		$.post(
			`${BASE_URL}changecurrbrgy`,
			{ brgy_id: brgy_id, brgy: brgy },
			function (res) {
				if (res) {
					toastr.success("Change Current Barangay!", "Success", {
						closeButton: !0,
						tapToDismiss: !1,
						positionClass: "toast-top-center",
					});
					location.reload();
				}
			}
		);
	});

	//end

	// Event handler for input change
	$(document).on("input", ".debitInput, .creditInput", function () {
		// Update the total when values change
		console.log("test");
		updateTotal();
		// Enable or disable the save button based on totals
		updateSaveButton();
	});

	// save jev
	$("#saveBtn").click(function () {
		let type = $('input[name="jev_type"]:checked').val();
		console.log("clicked save");

		let hasBlankInput = false;
		var firstBlankInput = null;

		$(".input-fields").each(function () {
			// Check if the input value is blank

			if ($(this).val() === "") {
				hasBlankInput = true;
				firstBlankInput = $(this);

				return false; // Break out of the loop if a blank input is found
			}
		});

		if (hasBlankInput) {
			alert("Please fill in black account title/code input fields.");
			firstBlankInput.focus();
		} else {
			// save jev
			if (type == "COL") {
				let jev_no = $("#jev_no").val();
				let jev_date = $("#jev_date").val();
				let fund = $("#fund").val();
				let payor = $("#payor").val();
				let or_no = $("#or_no").val();
				let or_date = $("#or_date").val();
				let parts = $("#parts").val();
				let resp_center = $("#resp_center").val();
				let brgy = $("#brgy").val();
				let acct_t = $(".acct_t")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				let acct_c = $(".acct_c")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				let debit = $(".debitInput")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();
				let credit = $(".creditInput")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				$.post(
					`${BASE_URL}savejev`,
					{
						jev_no: jev_no,
						jev_date: jev_date,
						fund: fund,
						payor: payor,
						or_no: or_no,
						or_date: or_date,
						parts: parts,
						resp_center: resp_center,
						brgy: brgy,
						acct_t: acct_t,
						acct_c: acct_c,
						debit: debit,
						credit: credit,
						type: type,
					},
					function (res) {
						if (res) {
							Swal.fire({
								title: `JEV Data Saved!!`,
								icon: "success",
								timer: 5000,
							});
							location.reload(true);
						} else {
							Swal.fire({
								title: `JEV No  already exists in the database!!`,
								icon: "error",
								timer: 5000,
							});
						}
					}
				);
			} else if (type == "CKD") {
				let jev_no = $("#jev_no").val();
				let jev_date = $("#jev_date").val();
				let fund = $("#fund").val();
				let payor = $("#payor").val();
				let v_no = $("#v_no").val();
				let chk_no = $("#chk_no").val();
				let chk_date = $("#chk_date").val();
				let bank_acct = $("#bank_acct").val();
				let parts = $("#parts").val();
				let resp_center = $("#resp_center").val();
				let brgy = $("#brgy").val();
				let acct_t = $(".acct_t")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				let acct_c = $(".acct_c")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				let debit = $(".debitInput")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();
				let credit = $(".creditInput")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				$.post(
					`${BASE_URL}savejev`,
					{
						jev_no: jev_no,
						jev_date: jev_date,
						fund: fund,
						payor: payor,
						v_no: v_no,
						chk_no: chk_no,
						chk_date: chk_date,
						bank_acct: bank_acct,
						parts: parts,
						resp_center: resp_center,
						brgy: brgy,
						acct_t: acct_t,
						acct_c: acct_c,
						debit: debit,
						credit: credit,
						type: type,
					},
					function (res) {
						if (res) {
							Swal.fire({
								title: `JEV Data Saved!!`,
								icon: "success",
								timer: 5000,
							});
							location.reload(true);
						} else {
							Swal.fire({
								title: `JEV No  already exists in the database!!`,
								icon: "error",
								timer: 5000,
							});
						}
					}
				);
			} else if (type == "CSD") {
				let jev_no = $("#jev_no").val();
				let jev_date = $("#jev_date").val();
				let fund = $("#fund").val();
				let payor = $("#payor").val();
				let vc_no = $("#vc_no").val();

				let parts = $("#parts").val();
				let resp_center = $("#resp_center").val();
				let brgy = $("#brgy").val();
				let acct_t = $(".acct_t")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				let acct_c = $(".acct_c")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				let debit = $(".debitInput")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();
				let credit = $(".creditInput")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				$.post(
					`${BASE_URL}savejev`,
					{
						jev_no: jev_no,
						jev_date: jev_date,
						fund: fund,
						payor: payor,
						vc_no: vc_no,
						parts: parts,
						resp_center: resp_center,
						brgy: brgy,
						acct_t: acct_t,
						acct_c: acct_c,
						debit: debit,
						credit: credit,
						type: type,
					},
					function (res) {
						if (res) {
							Swal.fire({
								title: `JEV Data Saved!!`,
								icon: "success",
								timer: 5000,
							});
							location.reload(true);
						} else {
							Swal.fire({
								title: `JEV No  already exists in the database!!`,
								icon: "error",
								timer: 5000,
							});
						}
					}
				);
			} else {
				let jev_no = $("#jev_no").val();
				let jev_date = $("#jev_date").val();
				let fund = $("#fund").val();
				let payor = $("#payor").val();
				let iv_no = $("#iv_no").val();
				let iv_date = $("#iv_date").val();
				let po_no = $("#po_no").val();
				let po_date = $("#po_date").val();
				let chk_no = $("#chk_no").val();
				let chk_date = $("#chk_date").val();

				let parts = $("#parts").val();
				let resp_center = $("#resp_center").val();
				let brgy = $("#brgy").val();
				let acct_t = $(".acct_t")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				let acct_c = $(".acct_c")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				let debit = $(".debitInput")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();
				let credit = $(".creditInput")
					.map(function () {
						console.log($(this).val());
						return $(this).val();
					})
					.get();

				$.post(
					`${BASE_URL}savejev`,
					{
						jev_no: jev_no,
						jev_date: jev_date,
						fund: fund,
						payor: payor,
						iv_no: iv_no,
						iv_date: iv_date,
						po_no: po_no,
						po_date: po_date,
						chk_no: chk_no,
						chk_date: chk_date,
						parts: parts,
						resp_center: resp_center,
						brgy: brgy,
						acct_t: acct_t,
						acct_c: acct_c,
						debit: debit,
						credit: credit,
						type: type,
					},
					function (res) {
						if (res) {
							Swal.fire({
								title: `JEV Data Saved!!`,
								icon: "success",
								timer: 5000,
							});
							location.reload(true);
						} else {
							Swal.fire({
								title: `JEV No  already exists in the database!!`,
								icon: "error",
								timer: 5000,
							});
						}
					}
				);
			}
		}
	});

	//end

	//save and approve jev

	//end

	// Function to update the total
	function updateTotal() {
		var totalDebit = 0;
		var totalCredit = 0;

		// Loop through all rows and sum up debit and credit values
		$(".debitInput").each(function () {
			totalDebit += parseFloat($(this).val()) || 0;
		});

		$(".creditInput").each(function () {
			totalCredit += parseFloat($(this).val()) || 0;
		});

		// Format the total values with commas for thousands
		var formattedTotalDebit = totalDebit.toLocaleString("en-US", {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		});
		var formattedTotalCredit = totalCredit.toLocaleString("en-US", {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		});

		// Update the total values in the table footer
		$("#totalDebit").text(formattedTotalDebit);
		$("#totalCredit").text(formattedTotalCredit);
	}

	// Function to enable or disable the save button based on totals
	function updateSaveButton() {
		var totalDebit = parseFloat($("#totalDebit").text().replace(",", ""));
		var totalCredit = parseFloat($("#totalCredit").text().replace(",", ""));

		// Disable the save button if totals are not equal or both are zero
		if (totalDebit !== totalCredit || totalDebit === 0) {
			$("#saveBtn").prop("disabled", true);
			$("#saveaBtn").prop("disabled", true);
		} else {
			$("#saveBtn").prop("disabled", false);
			$("#saveaBtn").prop("disabled", false);
		}
	}

	// radio on change
	$('input[name="jev_type"]').change(function () {
		// Get the value of the selected radio button
		var selectedValue = $('input[name="jev_type"]:checked').val();
		console.log(selectedValue);

		if (selectedValue == "COL") {
			$("#col_div").show();
			$("#ckd_div").hide();
			$("#csd_div").hide();
			$("#gj_div").hide();
		} else if (selectedValue == "CKD") {
			$("#col_div").hide();
			$("#ckd_div").show();
			$("#csd_div").hide();
			$("#gj_div").hide();
		} else if (selectedValue == "CSD") {
			$("#col_div").hide();
			$("#ckd_div").hide();
			$("#csd_div").show();
			$("#gj_div").hide();
		} else if (selectedValue == "GJ") {
			$("#col_div").hide();
			$("#ckd_div").hide();
			$("#csd_div").hide();
			$("#gj_div").show();
		}
	});

	//end

	//populate accounts table
	function getAccounts() {
		$.get(`${BASE_URL}getaccounts`, function (res) {
			tbl_accounts.clear();

			$.each(res, function (i, key) {
				tbl_accounts.row.add([key.code, key.name]);
			});
			tbl_accounts.draw();
		});
	}

	//end

	//filter jev list
	$("#filter_jevlist").click(function () {
		var selectedMonth = $("#tb_month").val();
		var selectedYear = $("#tb_year").val();

		console.log(selectedMonth, selectedYear);

		$.post(
			`${BASE_URL}/getjevlist2`,
			{ month: selectedMonth, year: selectedYear },
			function (response) {
				console.log(response);

				$("#tot_jevs").text(response.total_jev);
				$("#tot_d").text(response.total_debit);
				$("#tot_c").text(response.total_credit);

				tbl_jev.clear();

				$.each(response.jevs, function (i, key) {
					let type = "";
					let status;
					if (key.type == "COL") {
						type = "Cash Receipt/Collections";
					} else if (key.type == "CKD") {
						type = "Check Disbursement";
					} else if (type == "CSD") {
						type = "Cash Disbursement";
					} else {
						type = "General Journal";
					}

					let date = new Date(key.jev_date);
					let fdate = date.toLocaleDateString();

					if (key.status == 0) {
						status = `<span class="label bg-warning">Pending</span>`;
					} else {
						status = `<span class="label bg-success">Approved</span>`;
					}

					tbl_jev.row.add([
						key.jev_no,
						type,
						fdate,
						key.particulars,
						key.payor_payee,
						status,
						`<a href="${BASE_URL}viewjev/${key.jev_id}" class="btn btn-sm bg-success-light me-2">
       <i class="feather-eye"></i>
       </a>
       <button class="btn btn-sm bg-danger me-2 delete-jev" data-id="${key.jev_id}"> <i class="feather-delete"></i></button>
      `,
					]);
				});
				tbl_jev.draw();
			}
		);
	});

	//end
});
