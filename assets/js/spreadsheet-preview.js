(function (window, $) {
  function base64ToArrayBuffer(base64Data) {
    var binaryString = window.atob(base64Data);
    var length = binaryString.length;
    var bytes = new Uint8Array(length);

    for (var index = 0; index < length; index++) bytes[index] = binaryString.charCodeAt(index);

    return bytes.buffer;
  }

  function normalizeSheetRows(rows) {
    var maxColumns = 0;
    for (var rowIndex = 0; rowIndex < rows.length; rowIndex++) if (rows[rowIndex].length > maxColumns) maxColumns = rows[rowIndex].length;

    for (var currentRow = 0; currentRow < rows.length; currentRow++) {
      while (rows[currentRow].length < maxColumns) rows[currentRow].push("");
    }

    return rows;
  }

  function showPreviewError(message) {
    if (window.Swal) {
      window.Swal.fire({
        icon: "error",
        title: "Preview failed",
        text: message
      });
      return;
    }

    window.alert(message);
  }

  function showPreviewNotice(message) {
    if (window.Swal) {
      window.Swal.fire({
        icon: "info",
        title: "No preview data",
        text: message
      });
      return;
    }

    window.alert(message);
  }

  window.initSpreadsheetPreview = function (config) {
    var hotInstance = null;
    var workbook = null;
    var $form = $(config.formSelector);
    var $previewCard = $(config.previewCardSelector);
    var $sheetSelector = $(config.sheetSelector);
    var $previewButton = $(config.buttonSelector);
    var containerElement = document.querySelector(config.containerSelector);

    if (!$form.length || !$previewButton.length || !containerElement) return;
    if ($previewButton.data("previewBound")) return;
    $previewButton.data("previewBound", true);

    function renderSheet(sheetName) {
      if (!workbook || !workbook.Sheets[sheetName]) return;

      var worksheet = workbook.Sheets[sheetName];
      var rows = window.XLSX.utils.sheet_to_json(worksheet, {
        header: 1,
        defval: ""
      });
      var normalizedRows = normalizeSheetRows(rows);
      var mergeCells = (worksheet["!merges"] || []).map(function (range) {
        return {
          row: range.s.r,
          col: range.s.c,
          rowspan: range.e.r - range.s.r + 1,
          colspan: range.e.c - range.s.c + 1
        };
      });

      if (hotInstance) hotInstance.destroy();

      hotInstance = new window.Handsontable(containerElement, {
        data: normalizedRows,
        rowHeaders: true,
        colHeaders: true,
        width: "100%",
        height: 620,
        readOnly: true,
        copyPaste: true,
        manualColumnResize: true,
        manualRowResize: true,
        contextMenu: false,
        licenseKey: "non-commercial-and-evaluation",
        mergeCells: mergeCells
      });
    }

    function renderWorkbookFromResponse(response) {
      var workbookBuffer = base64ToArrayBuffer(response.content);
      workbook = window.XLSX.read(workbookBuffer, { type: "array" });
      var sheetNames = workbook.SheetNames || [];
      $('label[for="preview_sheet_selector"]').show();
      $sheetSelector.show();

      if (!sheetNames.length) {
        showPreviewError("Workbook has no visible sheets.");
        return;
      }

      $sheetSelector.empty();
      $.each(sheetNames, function (_, sheetName) {
        $sheetSelector.append($("<option>", { value: sheetName, text: sheetName }));
      });

      $sheetSelector.off("change.preview").on("change.preview", function () {
        renderSheet($(this).val());
      });

      renderSheet(sheetNames[0]);
      $previewCard.show();
    }

    function applyIframeLayoutFixes(iframe) {
      if (!iframe || !iframe.contentWindow || !iframe.contentWindow.document) return;
      var doc = iframe.contentWindow.document;
      var style = doc.createElement("style");
      style.type = "text/css";
      style.textContent = [
        "html, body {",
        "  overflow: auto !important;",
        "  overflow-x: auto !important;",
        "  overflow-y: auto !important;",
        "  width: auto !important;",
        "  max-width: none !important;",
          "  min-width: max-content !important;",
        "}",
        "table, div, section, article {",
        "  max-width: none !important;",
        "}",
        "table {",
        "  width: auto !important;",
          "  min-width: max-content !important;",
        "}",
        "img {",
        "  max-width: none !important;",
        "}"
      ].join("\n");
      doc.head.appendChild(style);

      var contentWidth = 0;
      if (doc.documentElement) contentWidth = Math.max(contentWidth, doc.documentElement.scrollWidth || 0);
      if (doc.body) contentWidth = Math.max(contentWidth, doc.body.scrollWidth || 0);

      var containerWidth = containerElement ? containerElement.clientWidth : 0;
      var targetWidth = Math.max(contentWidth, containerWidth, 900);

      iframe.style.display = "block";
      iframe.style.minWidth = "100%";
      iframe.style.width = targetWidth + "px";
      iframe.style.height = "620px";
      iframe.style.overflow = "auto";
      iframe.setAttribute("scrolling", "yes");
    }

    function renderHtmlPreview(previewHtml) {
      if (hotInstance) {
        hotInstance.destroy();
        hotInstance = null;
      }

      $sheetSelector.empty().hide();
      $('label[for="preview_sheet_selector"]').hide();
      containerElement.innerHTML = '<iframe title="Excel Preview" style="border:0;background:#fff;" sandbox="allow-same-origin"></iframe>';
      var iframe = containerElement.querySelector('iframe');
      if (iframe && iframe.contentWindow && iframe.contentWindow.document) {
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(previewHtml);
        iframe.contentWindow.document.close();
        applyIframeLayoutFixes(iframe);
      }
      $previewCard.show();
    }

    function renderHtmlSheetsPreview(previewSheets) {
      if (!previewSheets || !previewSheets.length) {
        showPreviewError("Workbook has no sheets to preview.");
        return;
      }

      if (hotInstance) {
        hotInstance.destroy();
        hotInstance = null;
      }

      containerElement.innerHTML = '<iframe title="Excel Preview" style="border:0;background:#fff;" sandbox="allow-same-origin"></iframe>';
      var iframe = containerElement.querySelector('iframe');

      function writeSheetHtml(sheetHtml) {
        if (!iframe || !iframe.contentWindow || !iframe.contentWindow.document) return;
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(sheetHtml);
        iframe.contentWindow.document.close();
        applyIframeLayoutFixes(iframe);
      }

      $sheetSelector.empty();
      $.each(previewSheets, function (_, sheet) {
        $sheetSelector.append($("<option>", { value: sheet.name, text: sheet.name }));
      });

      $('label[for="preview_sheet_selector"]').show();
      $sheetSelector.show();
      $sheetSelector.off("change.preview").on("change.preview", function () {
        var selectedSheetName = $(this).val();
        var selectedSheet = previewSheets.find(function (sheet) {
          return sheet.name === selectedSheetName;
        });
        if (selectedSheet) writeSheetHtml(selectedSheet.html);
      });

      writeSheetHtml(previewSheets[0].html);
      $previewCard.show();
    }

    $previewButton.on("click", function (event) {
      event.preventDefault();

      $.ajax({
        url: config.previewUrl,
        method: "POST",
        data: $form.serialize(),
        dataType: "json",
        success: function (response) {
          if (response && response.success === true && response.previewSheets && response.previewSheets.length) {
            renderHtmlSheetsPreview(response.previewSheets);
            return;
          }

          if (response && response.success === true && response.previewHtml) {
            renderHtmlPreview(response.previewHtml);
            return;
          }

          if (!response || response.success !== true || !response.content) {
            var errorMessage = response && response.message ? response.message : "The server did not return a preview payload.";
            showPreviewNotice(errorMessage);
            return;
          }

          renderWorkbookFromResponse(response);
        },
        error: function (xhr) {
          var responseMessage = "Unable to generate preview.";
          if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            responseMessage = xhr.responseJSON.message;
          } else if (xhr && xhr.responseText) {
            try {
              var parsed = JSON.parse(xhr.responseText);
              if (parsed && parsed.message) responseMessage = parsed.message;
            } catch (jsonParseError) {
              if (xhr.status) responseMessage = "Unable to generate preview (HTTP " + xhr.status + ").";
            }
          }
          showPreviewError(responseMessage);
        }
      });
    });
  };

  $(function () {
    var $previewButton = $("#preview_report_btn");
    if (!$previewButton.length) return;

    var defaultUrl = $previewButton.data("previewUrl");
    if (!defaultUrl) return;

    window.initSpreadsheetPreview({
      formSelector: "#report_form",
      buttonSelector: "#preview_report_btn",
      previewCardSelector: "#preview_card",
      sheetSelector: "#preview_sheet_selector",
      containerSelector: "#preview_grid_container",
      previewUrl: defaultUrl
    });
  });
})(window, window.jQuery);
