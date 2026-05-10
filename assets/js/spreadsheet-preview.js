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

  function escapeHtmlPdfNotice(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/"/g, "&quot;");
  }

  function showPdfFallbackNotice(message, opts) {
    opts = opts || {};
    var html = escapeHtmlPdfNotice(message);
    if (opts.summary) {
      html += "<br><br><strong>Summary</strong><br>" + escapeHtmlPdfNotice(opts.summary);
    }
    if (opts.traceFiles && opts.traceFiles.length) {
      html += "<br><br><strong>Trace files on this machine</strong><br><small>";
      html += opts.traceFiles.map(function (f) { return escapeHtmlPdfNotice(f); }).join("<br>");
      html += "</small>";
    }
    if (window.Swal) {
      window.Swal.fire({
        icon: "info",
        title: "PDF preview",
        html: html
      });
      return;
    }

    window.alert(message + (opts.summary ? "\n\n" + opts.summary : ""));
  }

  window.revokeSpreadsheetPdfPreview = function (containerElement) {
    if (!containerElement || !containerElement.getAttribute) return;
    var previousUrl = containerElement.getAttribute("data-pdf-blob-url");
    if (previousUrl) {
      window.URL.revokeObjectURL(previousUrl);
      containerElement.removeAttribute("data-pdf-blob-url");
    }
  };

  /**
   * @param {object} opts
   * @param {HTMLElement} opts.container
   * @param {string} opts.base64Pdf
   * @param {string} [opts.sheetSelector] — jQuery selector to empty/hide (e.g. #preview_sheet_selector)
   * @param {string} [opts.labelSelector] — jQuery selector for sheet label
   */
  window.renderSpreadsheetPdfPreview = function (opts) {
    if (!opts || !opts.container || !opts.base64Pdf) return;

    window.revokeSpreadsheetPdfPreview(opts.container);

    var buffer = base64ToArrayBuffer(opts.base64Pdf);
    var bytes = new Uint8Array(buffer);
    var blob = new Blob([bytes], { type: "application/pdf" });
    var objectUrl = window.URL.createObjectURL(blob);
    opts.container.setAttribute("data-pdf-blob-url", objectUrl);

    opts.container.innerHTML =
      '<iframe title="PDF Preview" src="' +
      objectUrl +
      '" style="border:0;width:100%;height:620px;background:#fff;"></iframe>';

    if (opts.sheetSelector) {
      $(opts.sheetSelector).empty().hide();
    }
    if (opts.labelSelector) {
      $(opts.labelSelector).hide();
    }
  };

  window.initSpreadsheetPreview = function (config) {
    var hotInstance = null;
    var workbook = null;
    var $form = $(config.formSelector);
    var $previewCard = $(config.previewCardSelector);
    var $sheetSelector = $(config.sheetSelector);
    var $previewButton = $(config.buttonSelector);
    var containerElement = document.querySelector(config.containerSelector);
    var previewFormat = config.previewFormat ? String(config.previewFormat) : "";

    if (!$form.length || !$previewButton.length || !containerElement) return;
    if ($previewButton.data("previewBound")) return;
    $previewButton.data("previewBound", true);

    function prepareContainerForNewPreview() {
      window.revokeSpreadsheetPdfPreview(containerElement);
      if (hotInstance) {
        hotInstance.destroy();
        hotInstance = null;
      }
      workbook = null;
    }

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
      prepareContainerForNewPreview();
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
      prepareContainerForNewPreview();

      $sheetSelector.empty().hide();
      $('label[for="preview_sheet_selector"]').hide();
      containerElement.innerHTML = '<iframe title="Excel Preview" style="border:0;background:#fff;" sandbox="allow-scripts allow-same-origin"></iframe>';
      var iframe = containerElement.querySelector("iframe");
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

      prepareContainerForNewPreview();

      containerElement.innerHTML = '<iframe title="Excel Preview" style="border:0;background:#fff;" sandbox="allow-scripts allow-same-origin"></iframe>';
      var iframe = containerElement.querySelector("iframe");

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

    function renderPdfFromResponse(response) {
      prepareContainerForNewPreview();
      window.renderSpreadsheetPdfPreview({
        container: containerElement,
        base64Pdf: response.previewPdf,
        sheetSelector: config.sheetSelector,
        labelSelector: 'label[for="preview_sheet_selector"]'
      });
      $previewCard.show();
    }

    $previewButton.on("click", function (event) {
      event.preventDefault();

      var postData = $form.serialize();
      if (previewFormat) {
        postData += (postData.length ? "&" : "") + "preview_format=" + encodeURIComponent(previewFormat);
      }

      $.ajax({
        url: config.previewUrl,
        method: "POST",
        data: postData,
        dataType: "json",
        success: function (response) {
          if (response && response.success === true && response.previewPdf) {
            renderPdfFromResponse(response);
            return;
          }

          if (response && response.success === true && response.previewSheets && response.previewSheets.length) {
            if (response.pdfFallbackMessage && previewFormat === "pdf") {
              showPdfFallbackNotice(response.pdfFallbackMessage, {
                traceFiles: response.pdfFailureTraceFiles,
                summary: response.pdfFailureSummary
              });
            }
            renderHtmlSheetsPreview(response.previewSheets);
            return;
          }

          if (response && response.success === true && response.previewHtml) {
            if (response.pdfFallbackMessage && previewFormat === "pdf") {
              showPdfFallbackNotice(response.pdfFallbackMessage, {
                traceFiles: response.pdfFailureTraceFiles,
                summary: response.pdfFailureSummary
              });
            }
            renderHtmlPreview(response.previewHtml);
            return;
          }

          if (!response || response.success !== true || !response.content) {
            var errorMessage = response && response.message ? response.message : "The server did not return a preview payload.";
            showPreviewNotice(errorMessage);
            return;
          }

          if (response.pdfFallbackMessage && previewFormat === "pdf") {
            showPdfFallbackNotice(response.pdfFallbackMessage, {
              traceFiles: response.pdfFailureTraceFiles,
              summary: response.pdfFailureSummary
            });
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

  window.jevShowPdfFallbackNotice = showPdfFallbackNotice;

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
