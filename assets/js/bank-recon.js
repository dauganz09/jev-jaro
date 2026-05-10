$(document).ready(function () {
  const BASE_URL = "http://localhost/jev-jaro/";

  function fmt(num) {
    const n = parseFloat(num) || 0;
    return n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function getReconKey() {
    return {
      bank_account_id: $("#br_bank_account").val(),
      year: $("#br_year").val(),
      month: $("#br_month").val(),
    };
  }

  let currentReconId = null;

  function loadBankAccounts(selectOnly = false, done) {
    $.get(`${BASE_URL}api/bank_accounts`, {}, function (rows) {
      if (!selectOnly) renderBankAccountsTable(rows || []);
      const $sel = $("#br_bank_account");
      if ($sel.length) {
        $sel.empty();
        (rows || []).forEach((r) => {
          if (String(r.is_active) !== "1") return;
          $sel.append(
            $("<option>", {
              value: r.bank_account_id,
              text: `${r.bank_name} - ${r.account_no} (${r.account_name})`,
            })
          );
        });
      }
      if (typeof done === "function") done();
    });
  }

  function renderBankAccountsTable(rows) {
    const $tb = $("#tbl_bank_accounts tbody");
    if (!$tb.length) return;
    $tb.empty();
    rows.forEach((r) => {
      const status = String(r.is_active) === "1" ? "Active" : "Inactive";
      const $tr = $("<tr>");
      $tr.append(`<td>${r.bank_name}</td>`);
      $tr.append(`<td>${r.branch || ""}</td>`);
      $tr.append(`<td>${r.account_no}</td>`);
      $tr.append(`<td>${r.account_name}</td>`);
      $tr.append(`<td>${r.cash_in_bank_acc_code}</td>`);
      $tr.append(`<td>${status}</td>`);
      $tr.append(
        `<td class="text-end">
          <button class="btn btn-sm btn-outline-primary ba_edit" data-id="${r.bank_account_id}">Edit</button>
          <button class="btn btn-sm btn-outline-secondary ba_toggle" data-id="${r.bank_account_id}" data-active="${r.is_active}">${String(r.is_active)==="1"?"Deactivate":"Activate"}</button>
        </td>`
      );
      $tb.append($tr);
    });
  }

  $(document).on("click", "#ba_add_btn", function () {
    Swal.fire({
      title: "Add Bank Account",
      html:
        '<input id="ba_bank" class="swal2-input" placeholder="Bank name">' +
        '<input id="ba_branch" class="swal2-input" placeholder="Branch (optional)">' +
        '<input id="ba_no" class="swal2-input" placeholder="Account no">' +
        '<input id="ba_name" class="swal2-input" placeholder="Account name">' +
        '<input id="ba_coa" class="swal2-input" placeholder="Cash in Bank COA (default 10102020)">',
      focusConfirm: false,
      showCancelButton: true,
      preConfirm: () => ({
        bank_account_id: "",
        bank_name: document.getElementById("ba_bank").value,
        branch: document.getElementById("ba_branch").value,
        account_no: document.getElementById("ba_no").value,
        account_name: document.getElementById("ba_name").value,
        cash_in_bank_acc_code: document.getElementById("ba_coa").value,
      }),
    }).then((result) => {
      if (!result.isConfirmed) return;
      $.post(`${BASE_URL}api/bank_accounts/save`, result.value, function (res) {
        if (res && res.success) {
          Swal.fire({ title: "Saved", icon: "success", timer: 1200 });
          loadBankAccounts();
        } else Swal.fire({ title: "Failed", text: (res && res.message) || "", icon: "error" });
      });
    });
  });

  $(document).on("click", ".ba_edit", function () {
    const id = $(this).data("id");
    $.get(`${BASE_URL}api/bank_accounts`, {}, function (rows) {
      const r = (rows || []).find((x) => String(x.bank_account_id) === String(id));
      if (!r) return;
      Swal.fire({
        title: "Edit Bank Account",
        html:
          `<input id="ba_bank" class="swal2-input" placeholder="Bank name" value="${r.bank_name}">` +
          `<input id="ba_branch" class="swal2-input" placeholder="Branch" value="${r.branch || ""}">` +
          `<input id="ba_no" class="swal2-input" placeholder="Account no" value="${r.account_no}">` +
          `<input id="ba_name" class="swal2-input" placeholder="Account name" value="${r.account_name}">` +
          `<input id="ba_coa" class="swal2-input" placeholder="Cash in Bank COA" value="${r.cash_in_bank_acc_code}">`,
        focusConfirm: false,
        showCancelButton: true,
        preConfirm: () => ({
          bank_account_id: r.bank_account_id,
          bank_name: document.getElementById("ba_bank").value,
          branch: document.getElementById("ba_branch").value,
          account_no: document.getElementById("ba_no").value,
          account_name: document.getElementById("ba_name").value,
          cash_in_bank_acc_code: document.getElementById("ba_coa").value,
        }),
      }).then((result) => {
        if (!result.isConfirmed) return;
        $.post(`${BASE_URL}api/bank_accounts/save`, result.value, function (res) {
          if (res && res.success) {
            Swal.fire({ title: "Saved", icon: "success", timer: 1200 });
            loadBankAccounts();
          } else Swal.fire({ title: "Failed", text: (res && res.message) || "", icon: "error" });
        });
      });
    });
  });

  $(document).on("click", ".ba_toggle", function () {
    const id = $(this).data("id");
    const isActive = String($(this).data("active")) === "1";
    $.post(`${BASE_URL}api/bank_accounts/toggle`, { bank_account_id: id, is_active: isActive ? 0 : 1 }, function (res) {
      if (res && res.success) loadBankAccounts();
    });
  });

  function initYearSelect() {
    const $y = $("#br_year");
    if (!$y.length) return;
    const cur = new Date().getFullYear();
    for (let i = 0; i < 5; i++) {
      const yr = cur - i;
      $y.append($("<option>", { value: yr, text: yr }));
    }
  }

  function setReconEnabled(on) {
    $("#br_add_line_btn, #br_add_item_btn, #br_preview_btn, #br_export_btn").prop("disabled", !on);
  }

  function clearReconTables() {
    $("#tbl_stmt_lines tbody").empty();
    $("#tbl_book_lines tbody").empty();
    $("#tbl_matches tbody").empty();
    $("#tbl_items tbody").empty();
  }

  function fetchReconMeta() {
    const key = getReconKey();
    if (!key.bank_account_id) return;
    $.get(`${BASE_URL}api/bank_recon/get`, key, function (res) {
      if (!res || !res.success) return;
      $("#br_book_balance").text(fmt(res.book_ending_balance));
      if (res.recon_id) {
        currentReconId = res.recon_id;
        if (res.statement_ending_balance !== undefined && res.statement_ending_balance !== null)
          $("#br_stmt_ending").val(res.statement_ending_balance);
        $("#br_stmt_as_of").val(res.statement_as_of_date || "");
        $("#br_explanatory").val(res.explanatory_comment || "");
        setReconEnabled(true);
        reloadReconData();
      } else {
        currentReconId = null;
        $("#br_stmt_ending").val("");
        $("#br_stmt_as_of").val(res.statement_as_of_date || "");
        $("#br_explanatory").val("");
        setReconEnabled(false);
        clearReconTables();
      }
    });
  }

  function reloadReconData() {
    if (!currentReconId) return;
    $.get(`${BASE_URL}api/bank_recon/lines`, { recon_id: currentReconId }, function (res) {
      renderStatementLines((res && res.lines) || []);
      renderMatches((res && res.matches) || []);
    });
    $.get(`${BASE_URL}api/bank_recon/items`, { recon_id: currentReconId }, function (res) {
      renderItems((res && res.items) || []);
    });
    $.get(`${BASE_URL}api/bank_recon/book_lines`, { recon_id: currentReconId }, function (res) {
      renderBookLines((res && res.lines) || []);
      $("#br_book_balance").text(fmt(res && res.book_ending_balance));
    });
  }

  function renderStatementLines(lines) {
    const $tb = $("#tbl_stmt_lines tbody");
    $tb.empty();
    (lines || []).forEach((l) => {
      const $tr = $("<tr>");
      $tr.append(`<td>${l.txn_date}</td>`);
      $tr.append(`<td>${l.description}</td>`);
      $tr.append(`<td>${l.reference || ""}</td>`);
      $tr.append(`<td class="text-end">${fmt(l.amount)}</td>`);
      $tr.append(
        `<td class="text-end">
          <button class="btn btn-sm btn-outline-primary br_suggest" data-id="${l.statement_line_id}">Suggest</button>
          <button class="btn btn-sm btn-outline-danger br_line_del" data-id="${l.statement_line_id}">Del</button>
        </td>`
      );
      $tb.append($tr);
    });
  }

  function renderBookLines(lines) {
    const $tb = $("#tbl_book_lines tbody");
    $tb.empty();
    (lines || []).forEach((l) => {
      $tb.append(`<tr><td>${l.jev_date}</td><td>${l.jev_no}</td><td>${l.ref}</td><td class="text-end">${fmt(l.net)}</td></tr>`);
    });
  }

  function renderMatches(rows) {
    const $tb = $("#tbl_matches tbody");
    $tb.empty();
    (rows || []).forEach((r) => {
      $tb.append(
        `<tr>
          <td>${r.txn_date} ${r.description} (${fmt(r.stmt_amount)})</td>
          <td>${r.jev_date} ${r.jev_no} ${r.ref} (${fmt(r.book_net)})</td>
          <td class="text-end">${fmt(r.matched_amount)}</td>
          <td class="text-end"><button class="btn btn-sm btn-outline-danger br_match_del" data-id="${r.match_id}">Remove</button></td>
        </tr>`
      );
    });
  }

  function renderItems(rows) {
    const $tb = $("#tbl_items tbody");
    $tb.empty();
    (rows || []).forEach((r) => {
      const canCreateJev = ["bank_charge", "interest_income", "bank_debit_memo", "bank_credit_memo"].includes(r.item_type) && !r.linked_jev_id;
      $tb.append(
        `<tr>
          <td>${r.item_type}</td>
          <td>${r.reference || ""}</td>
          <td>${r.notes || ""}</td>
          <td class="text-end">${fmt(r.amount)}</td>
          <td class="text-end">
            ${canCreateJev ? `<button class="btn btn-sm btn-outline-success br_item_jev" data-id="${r.recon_item_id}">Create JEV</button>` : ``}
            <button class="btn btn-sm btn-outline-danger br_item_del" data-id="${r.recon_item_id}">Del</button>
          </td>
        </tr>`
      );
    });
  }

  $(document).on("click", "#br_load_btn", function () {
    const key = getReconKey();
    const stmt = $("#br_stmt_ending").val();
    if (!key.bank_account_id) return Swal.fire({ title: "Select bank account", icon: "warning" });
    $.post(
      `${BASE_URL}api/bank_recon/upsert`,
      {
        bank_account_id: key.bank_account_id,
        year: key.year,
        month: key.month,
        statement_ending_balance: stmt,
        statement_as_of_date: $("#br_stmt_as_of").val(),
        explanatory_comment: $("#br_explanatory").val(),
      },
      function (res) {
        if (res && res.success) {
          currentReconId = res.recon_id;
          setReconEnabled(true);
          if (res.statement_as_of_date) $("#br_stmt_as_of").val(res.statement_as_of_date);
          if (res.explanatory_comment !== undefined) $("#br_explanatory").val(res.explanatory_comment);
          $("#br_book_balance").text(fmt(res.book_ending_balance));
          reloadReconData();
        } else Swal.fire({ title: "Failed", text: (res && res.message) || "", icon: "error" });
      }
    );
  });

  $(document).on("click", "#br_add_line_btn", function () {
    $.post(
      `${BASE_URL}api/bank_recon/lines/add`,
      {
        recon_id: currentReconId,
        txn_date: $("#br_line_date").val(),
        description: $("#br_line_desc").val(),
        reference: $("#br_line_ref").val(),
        amount: $("#br_line_amt").val(),
      },
      function (res) {
        if (res && res.success) {
          $("#br_line_desc,#br_line_ref,#br_line_amt").val("");
          reloadReconData();
        }
      }
    );
  });

  $(document).on("click", ".br_line_del", function () {
    $.post(`${BASE_URL}api/bank_recon/lines/delete`, { statement_line_id: $(this).data("id") }, function (res) {
      if (res && res.success) reloadReconData();
    });
  });

  $(document).on("click", "#br_add_item_btn", function () {
    $.post(
      `${BASE_URL}api/bank_recon/items/add`,
      {
        recon_id: currentReconId,
        item_type: $("#br_item_type").val(),
        amount: $("#br_item_amt").val(),
        reference: $("#br_item_ref").val(),
        notes: $("#br_item_notes").val(),
      },
      function (res) {
        if (res && res.success) {
          $("#br_item_amt,#br_item_ref,#br_item_notes").val("");
          reloadReconData();
        }
      }
    );
  });

  $(document).on("click", ".br_item_del", function () {
    $.post(`${BASE_URL}api/bank_recon/items/delete`, { recon_item_id: $(this).data("id") }, function (res) {
      if (res && res.success) reloadReconData();
    });
  });

  $(document).on("click", ".br_item_jev", function () {
    const id = $(this).data("id");
    Swal.fire({
      title: "Create JEV from recon item?",
      text: "This will create a draft GJ entry and link it back to the recon item.",
      showCancelButton: true,
      confirmButtonText: "Create",
    }).then((r) => {
      if (!r.isConfirmed) return;
      $.post(`${BASE_URL}api/bank_recon/items/create_jev`, { recon_item_id: id }, function (res) {
        if (res && res.success) {
          Swal.fire({ title: "Created", text: `JEV No: ${res.jev_no}`, icon: "success" });
          reloadReconData();
        } else Swal.fire({ title: "Failed", text: (res && res.message) || "", icon: "error" });
      });
    });
  });

  $(document).on("click", ".br_suggest", function () {
    const stmtLineId = $(this).data("id");
    $.get(`${BASE_URL}api/bank_recon/suggest`, { recon_id: currentReconId, statement_line_id: stmtLineId }, function (res) {
      const opts = (res && res.suggestions) || [];
      if (!opts.length) return Swal.fire({ title: "No suggestions", icon: "info" });
      const html =
        '<div style="text-align:left">' +
        opts
          .map(
            (o) =>
              `<div class="mb-2">
                <button class="btn btn-sm btn-outline-primary br_apply_match" data-stmt="${stmtLineId}" data-jev="${o.jevdata_id}" data-amt="${o.matched_amount}">Match</button>
                <span class="ms-2">${o.jev_date} ${o.jev_no} ${o.ref} | net ${fmt(o.book_net)}</span>
              </div>`
          )
          .join("") +
        "</div>";
      Swal.fire({ title: "Suggestions", html: html, width: 800, showConfirmButton: false });
    });
  });

  $(document).on("click", ".br_apply_match", function () {
    const stmt = $(this).data("stmt");
    const jev = $(this).data("jev");
    const amt = $(this).data("amt");
    $.post(`${BASE_URL}api/bank_recon/match/add`, { recon_id: currentReconId, statement_line_id: stmt, jevdata_id: jev, matched_amount: amt }, function (res) {
      if (res && res.success) {
        Swal.close();
        reloadReconData();
      }
    });
  });

  $(document).on("click", ".br_match_del", function () {
    $.post(`${BASE_URL}api/bank_recon/match/delete`, { match_id: $(this).data("id") }, function (res) {
      if (res && res.success) reloadReconData();
    });
  });

  $(document).on("click", "#br_export_btn", function () {
    window.location = `${BASE_URL}brs?recon_id=${currentReconId}`;
  });

  $(document).on("click", "#br_preview_btn", function () {
    $.get(`${BASE_URL}brs_preview`, { recon_id: currentReconId }, function (res) {
      if (!res || res.success !== true || !res.previewSheets || !res.previewSheets.length) {
        return Swal.fire({ title: "Preview failed", text: (res && res.message) || "No preview payload.", icon: "error" });
      }

      $("#preview_card").show();
      var $sheetSelector = $("#preview_sheet_selector");
      var $label = $('label[for="preview_sheet_selector"]');
      var $container = $("#preview_grid_container");
      $container.html('<iframe title="Excel Preview" style="border:0;background:#fff;width:100%;height:620px;" sandbox="allow-same-origin"></iframe>');
      var iframe = $container.find("iframe")[0];

      function writeHtml(html) {
        if (!iframe || !iframe.contentWindow || !iframe.contentWindow.document) return;
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(html);
        iframe.contentWindow.document.close();
      }

      $sheetSelector.empty();
      res.previewSheets.forEach((s) => $sheetSelector.append($("<option>", { value: s.name, text: s.name })));
      $label.show();
      $sheetSelector.show();
      $sheetSelector.off("change.brpreview").on("change.brpreview", function () {
        var selected = res.previewSheets.find((s) => s.name === $(this).val());
        if (selected) writeHtml(selected.html);
      });

      writeHtml(res.previewSheets[0].html);
    });
  });

  initYearSelect();

  if ($("#tbl_bank_accounts").length) {
    loadBankAccounts(false);
  } else {
    loadBankAccounts(true, function () {
      $("#br_bank_account, #br_year, #br_month").on("change", fetchReconMeta);
      fetchReconMeta();
    });
  }
});

