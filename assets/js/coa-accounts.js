$(document).ready(function () {
  var BASE_URL = window.COA_BASE_URL || "";
  if (BASE_URL && BASE_URL.slice(-1) !== "/") BASE_URL += "/";

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/"/g, "&quot;");
  }

  function classLabel(val) {
    var m = window.COA_CLASSES || {};
    return m[val] || val || "—";
  }

  function activeLabel(r) {
    var inactive = String(r.is_active) === "0" || r.is_active === 0;
    return inactive ? "No" : "Yes";
  }

  var tbl_coa = $("#tbl_coa").DataTable({
    pagingType: "full_numbers",
    lengthMenu: [
      [30, 50, 70, 100, -1],
      [30, 50, 70, 100, "All"],
    ],
    pageLength: 30,
    order: [[1, "asc"]],
    searchable: true,
    responsive: true,
    columnDefs: [
      { visible: false, searchable: false, targets: 0 },
      {
        render: function (data, type, row, meta) {
          return "<div class='text-wrap width-200'>" + data + "</div>";
        },
        targets: 4,
      },
      { orderable: false, targets: [6] },
    ],
    language: {
      search: "_INPUT_",
      info: "Showing _START_ to _END_ of _TOTAL_ accounts",
      loadingRecords: "Loading accounts…",
      searchPlaceholder: "Search accounts…",
      infoFiltered: "(filtered from _MAX_ total accounts)",
      zeroRecords: "No accounts found",
      lengthMenu: "Show _MENU_ accounts",
    },
  });

  function refreshCoaTable() {
    $.get(BASE_URL + "api/coa_accounts", function (res) {
      tbl_coa.clear();
      if (!res || !res.success || !res.accounts) {
        tbl_coa.draw();
        return;
      }
      res.accounts.forEach(function (r) {
        tbl_coa.row.add([
          r.account_id,
          "<code>" + esc(r.code) + "</code>",
          esc(r.name),
          esc(classLabel(r.account_class)),
          r.notes ? esc(r.notes) : "",
          activeLabel(r),
          '<button type="button" class="btn btn-sm btn-outline-primary coa_edit">Edit</button> ' +
            '<button type="button" class="btn btn-sm btn-outline-danger coa_del">Delete</button>',
        ]);
      });
      tbl_coa.draw();
    });
  }

  function formHtml(r) {
    r = r || {};
    var opts = window.COA_CLASSES || {};
    var sel = '<select id="coa_class" class="swal2-input" style="display:block;width:100%">';
    Object.keys(opts).forEach(function (k) {
      var selAttr = String(r.account_class || "") === String(k) ? " selected" : "";
      sel += '<option value="' + esc(k) + '"' + selAttr + ">" + esc(opts[k]) + "</option>";
    });
    sel += "</select>";
    var hint = window.COA_CODE_HINT || "";
    return (
      '<label class="small text-muted">' +
      esc(hint) +
      "</label>" +
      '<input id="coa_code" class="swal2-input" placeholder="Account code (UACS)" value="' +
      esc(r.code) +
      '">' +
      '<input id="coa_name" class="swal2-input" placeholder="Account title" value="' +
      esc(r.name) +
      '">' +
      '<label class="small">Classification</label>' +
      sel +
      '<textarea id="coa_notes" class="swal2-textarea" placeholder="Notes (optional)">' +
      esc(r.notes) +
      "</textarea>" +
      '<label><input type="checkbox" id="coa_active" ' +
      (String(r.is_active) !== "0" && r.is_active !== 0 ? "checked" : "") +
      "> Active (show in JEV picker)</label>"
    );
  }

  $("#coa_add_btn").on("click", function () {
    Swal.fire({
      title: "Add account",
      html: formHtml({ is_active: 1 }),
      focusConfirm: false,
      showCancelButton: true,
      width: 560,
      preConfirm: function () {
        return {
          account_id: "",
          code: document.getElementById("coa_code").value,
          name: document.getElementById("coa_name").value,
          account_class: document.getElementById("coa_class").value,
          notes: document.getElementById("coa_notes").value,
          is_active: document.getElementById("coa_active").checked ? 1 : 0,
        };
      },
    }).then(function (result) {
      if (!result.isConfirmed) return;
      $.post(BASE_URL + "api/coa_accounts/save", result.value, function (res) {
        if (res && res.success) {
          Swal.fire({ title: "Saved", icon: "success", timer: 1200 });
          refreshCoaTable();
        } else Swal.fire({ title: "Failed", text: (res && res.message) || "", icon: "error" });
      });
    });
  });

  $("#tbl_coa").on("click", ".coa_edit", function () {
    var row = tbl_coa.row($(this).closest("tr"));
    var d = row.data();
    if (!d) return;
    var id = d[0];
    $.get(BASE_URL + "api/coa_accounts", function (res) {
      var acc = (res.accounts || []).find(function (x) {
        return String(x.account_id) === String(id);
      });
      if (!acc) return;
      Swal.fire({
        title: "Edit account",
        html: formHtml(acc),
        focusConfirm: false,
        showCancelButton: true,
        width: 560,
        preConfirm: function () {
          return {
            account_id: acc.account_id,
            code: document.getElementById("coa_code").value,
            name: document.getElementById("coa_name").value,
            account_class: document.getElementById("coa_class").value,
            notes: document.getElementById("coa_notes").value,
            is_active: document.getElementById("coa_active").checked ? 1 : 0,
          };
        },
      }).then(function (result) {
        if (!result.isConfirmed) return;
        $.post(BASE_URL + "api/coa_accounts/save", result.value, function (res2) {
          if (res2 && res2.success) {
            Swal.fire({ title: "Saved", icon: "success", timer: 1200 });
            refreshCoaTable();
          } else Swal.fire({ title: "Failed", text: (res2 && res2.message) || "", icon: "error" });
        });
      });
    });
  });

  $("#tbl_coa").on("click", ".coa_del", function () {
    var row = tbl_coa.row($(this).closest("tr"));
    var d = row.data();
    if (!d) return;
    var id = d[0];
    Swal.fire({
      title: "Delete account?",
      text: "Only allowed if the code was never used on a JEV line.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Delete",
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $.post(BASE_URL + "api/coa_accounts/delete", { account_id: id }, function (res) {
        if (res && res.success) {
          Swal.fire({ title: "Deleted", icon: "success", timer: 1200 });
          refreshCoaTable();
        } else Swal.fire({ title: "Cannot delete", text: (res && res.message) || "", icon: "error" });
      });
    });
  });

  refreshCoaTable();
});
