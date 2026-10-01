/**
 * Enterprise Asset & Inventory Management Portal
 * Powered by Dojo Toolkit 1.17+ AMD Architecture & Modern JavaScript
 */

require([
  "dojo/dom",
  "dojo/on",
  "dojo/request",
  "dojo/dom-construct",
  "dojo/dom-class",
  "dojo/query",
  "dojo/json",
  "dojo/domReady!"
], function(dom, on, request, domConstruct, domClass, query, JSON) {

  // State
  let currentAssets = [];
  let currentCategories = [];
  let activeAssetForAdjustment = null;

  // DOM Elements
  const assetTableBody = dom.byId("assetTableBody");
  const searchInput = dom.byId("searchInput");
  const categoryFilter = dom.byId("categoryFilter");
  const statusFilter = dom.byId("statusFilter");
  const refreshBtn = dom.byId("refreshBtn");
  const addAssetBtn = dom.byId("addAssetBtn");

  // Modals
  const addModal = dom.byId("addModal");
  const adjustModal = dom.byId("adjustModal");
  const historyModal = dom.byId("historyModal");

  // Forms
  const addAssetForm = dom.byId("addAssetForm");
  const adjustStockForm = dom.byId("adjustStockForm");

  // Toast
  function showToast(message, type = "success") {
    const container = dom.byId("toastContainer");
    const toast = domConstruct.create("div", {
      className: "toast " + type,
      innerHTML: "<span>" + (type === "success" ? "✓" : "⚠") + "</span> " + message
    }, container);

    setTimeout(function() {
      domConstruct.destroy(toast);
    }, 4000);
  }

  // Load Dashboard Statistics
  function loadStats() {
    request.get("api/stats.php", { handleAs: "json" }).then(
      function(response) {
        if (response.success && response.data) {
          const d = response.data;
          dom.byId("statTotalAssets").textContent = d.total_asset_types || 0;
          dom.byId("statTotalUnits").textContent = d.total_inventory_items || 0;
          dom.byId("statValuation").textContent = "$" + Number(d.total_valuation || 0).toLocaleString("en-US", { minimumFractionDigits: 2 });
          dom.byId("statLowStock").textContent = d.low_stock_alerts || 0;
        }
      },
      function(err) {
        console.error("Failed to load statistics:", err);
      }
    );
  }

  // Load Categories for Dropdowns
  function loadCategories() {
    request.get("api/categories.php", { handleAs: "json" }).then(
      function(response) {
        if (response.success && response.data) {
          currentCategories = response.data;
          
          // Populate filter dropdown
          let filterHtml = '<option value="">All Categories</option>';
          let formCatHtml = '<option value="">Select Category</option>';

          response.data.forEach(function(cat) {
            filterHtml += '<option value="' + cat.code + '">' + cat.name + ' (' + cat.code + ')</option>';
            formCatHtml += '<option value="' + cat.id + '">' + cat.name + '</option>';
          });

          categoryFilter.innerHTML = filterHtml;
          dom.byId("addCategory").innerHTML = formCatHtml;
        }
      },
      function(err) {
        console.error("Failed to load categories:", err);
      }
    );
  }

  // Render Asset Table Rows
  function renderTable(assets) {
    domConstruct.empty(assetTableBody);

    if (!assets || assets.length === 0) {
      domConstruct.create("tr", {
        innerHTML: '<td colspan="8" style="text-align:center; padding:32px; color:#64748b;">No inventory assets found matching your criteria.</td>'
      }, assetTableBody);
      return;
    }

    assets.forEach(function(item) {
      let badgeClass = "badge-in-stock";
      if (item.status === "Low Stock") badgeClass = "badge-low-stock";
      if (item.status === "Out of Stock") badgeClass = "badge-out-of-stock";

      const tr = domConstruct.create("tr", {}, assetTableBody);
      tr.innerHTML = 
        '<td><strong>' + item.asset_tag + '</strong></td>' +
        '<td>' + item.name + '<div style="font-size:0.75rem; color:#64748b;">Loc: ' + (item.location || "N/A") + '</div></td>' +
        '<td><span style="font-size:0.8rem; background:#e2e8f0; padding:2px 6px; border-radius:4px;">' + item.category_name + '</span></td>' +
        '<td><strong>' + item.quantity + '</strong> <span style="font-size:0.75rem; color:#64748b;">(Min ' + item.reorder_level + ')</span></td>' +
        '<td>$' + Number(item.unit_price).toFixed(2) + '</td>' +
        '<td><span class="badge ' + badgeClass + '">' + item.status + '</span></td>' +
        '<td>' + (item.supplier || "Standard") + '</td>' +
        '<td>' +
          '<div class="actions">' +
            '<button class="btn btn-secondary btn-sm btn-adjust" data-id="' + item.id + '" title="Adjust Stock">Stock</button>' +
            '<button class="btn btn-secondary btn-sm btn-history" data-id="' + item.id + '" title="Audit Trail">Audit</button>' +
            '<button class="btn btn-danger btn-sm btn-delete" data-id="' + item.id + '" title="Delete Asset">×</button>' +
          '</div>' +
        '</td>';
    });

    // Attach Event Listeners to Action Buttons using Dojo on
    query(".btn-adjust", assetTableBody).forEach(function(btn) {
      on(btn, "click", function(e) {
        const id = btn.getAttribute("data-id");
        openAdjustModal(id);
      });
    });

    query(".btn-history", assetTableBody).forEach(function(btn) {
      on(btn, "click", function(e) {
        const id = btn.getAttribute("data-id");
        openHistoryModal(id);
      });
    });

    query(".btn-delete", assetTableBody).forEach(function(btn) {
      on(btn, "click", function(e) {
        const id = btn.getAttribute("data-id");
        deleteAsset(id);
      });
    });
  }

  // Fetch Assets with Active Filters
  function loadAssets() {
    const search = searchInput.value.trim();
    const category = categoryFilter.value;
    const status = statusFilter.value;

    let queryStr = [];
    if (search) queryStr.push("search=" + encodeURIComponent(search));
    if (category) queryStr.push("category=" + encodeURIComponent(category));
    if (status) queryStr.push("status=" + encodeURIComponent(status));

    const url = "api/assets.php" + (queryStr.length ? "?" + queryStr.join("&") : "");

    request.get(url, { handleAs: "json" }).then(
      function(response) {
        if (response.success) {
          currentAssets = response.data || [];
          renderTable(currentAssets);
        }
      },
      function(err) {
        showToast("Error loading assets: " + err.message, "error");
      }
    );
  }

  // Open Adjust Modal
  function openAdjustModal(id) {
    const asset = currentAssets.find(function(a) { return String(a.id) === String(id); });
    if (!asset) return;

    activeAssetForAdjustment = asset;
    dom.byId("adjustAssetTag").textContent = asset.asset_tag;
    dom.byId("adjustAssetName").textContent = asset.name;
    dom.byId("adjustCurrentQty").textContent = asset.quantity;
    dom.byId("adjustAmount").value = 1;
    dom.byId("adjustReason").value = "";
    dom.byId("adjustType").value = "IN";

    domClass.add(adjustModal, "active");
  }

  // Open History Modal
  function openHistoryModal(id) {
    request.get("api/assets.php?id=" + id, { handleAs: "json" }).then(
      function(response) {
        if (response.success && response.data) {
          const asset = response.data;
          dom.byId("historyAssetTitle").textContent = asset.name + " (" + asset.asset_tag + ")";
          const list = dom.byId("historyList");
          domConstruct.empty(list);

          const txs = asset.transactions || [];
          if (txs.length === 0) {
            list.innerHTML = '<div style="color:#64748b; padding:12px 0;">No transaction records logged.</div>';
          } else {
            txs.forEach(function(tx) {
              const sign = tx.quantity > 0 ? "+" : "";
              const color = tx.quantity > 0 ? "#15803d" : "#b91c1c";
              domConstruct.create("div", {
                className: "history-item",
                innerHTML:
                  '<div class="tx-header">' +
                    '<span>' + tx.transaction_type + '</span>' +
                    '<span style="color:' + color + ';">' + sign + tx.quantity + ' units (Balance: ' + tx.new_quantity + ')</span>' +
                  '</div>' +
                  '<div>Reason: ' + tx.reason + '</div>' +
                  '<div class="tx-meta">By: ' + (tx.performed_by || "System Admin") + ' | ' + tx.created_at + '</div>'
              }, list);
            });
          }

          domClass.add(historyModal, "active");
        }
      },
      function(err) {
        showToast("Failed to load audit history: " + err.message, "error");
      }
    );
  }

  // Delete Asset
  function deleteAsset(id) {
    if (!confirm("Are you sure you want to delete asset #" + id + "?")) {
      return;
    }

    request.del("api/assets.php?id=" + id, { handleAs: "json" }).then(
      function(response) {
        if (response.success) {
          showToast(response.message || "Asset deleted successfully.");
          loadAssets();
          loadStats();
        } else {
          showToast(response.error || "Delete failed", "error");
        }
      },
      function(err) {
        showToast("Error deleting asset: " + err.message, "error");
      }
    );
  }

  // Event Handlers for Filters
  on(searchInput, "input", function() {
    clearTimeout(window._searchTimer);
    window._searchTimer = setTimeout(loadAssets, 250);
  });

  on(categoryFilter, "change", loadAssets);
  on(statusFilter, "change", loadAssets);
  on(refreshBtn, "click", function() {
    loadAssets();
    loadStats();
    showToast("Data refreshed.");
  });

  // Modal Close Buttons
  query(".modal-close, .modal-cancel").forEach(function(btn) {
    on(btn, "click", function() {
      query(".modal-overlay").forEach(function(modal) {
        domClass.remove(modal, "active");
      });
    });
  });

  // Open Add Modal
  on(addAssetBtn, "click", function() {
    addAssetForm.reset();
    domClass.add(addModal, "active");
  });

  // Submit Add Asset Form
  on(addAssetForm, "submit", function(e) {
    e.preventDefault();

    const payload = {
      asset_tag: dom.byId("addAssetTag").value.trim(),
      name: dom.byId("addName").value.trim(),
      category_id: parseInt(dom.byId("addCategory").value, 10),
      quantity: parseInt(dom.byId("addQuantity").value, 10),
      unit_price: parseFloat(dom.byId("addPrice").value),
      reorder_level: parseInt(dom.byId("addReorder").value, 10),
      location: dom.byId("addLocation").value.trim(),
      supplier: dom.byId("addSupplier").value.trim()
    };

    request.post("api/assets.php", {
      data: JSON.stringify(payload),
      headers: { "Content-Type": "application/json" },
      handleAs: "json"
    }).then(
      function(response) {
        if (response.success) {
          showToast("New asset registered successfully!");
          domClass.remove(addModal, "active");
          loadAssets();
          loadStats();
        } else {
          showToast(response.error || "Failed to create asset", "error");
        }
      },
      function(err) {
        let msg = "Validation or network error";
        if (err.response && err.response.data && err.response.data.error) {
          msg = err.response.data.error;
        }
        showToast(msg, "error");
      }
    );
  });

  // Submit Stock Adjustment Form
  on(adjustStockForm, "submit", function(e) {
    e.preventDefault();
    if (!activeAssetForAdjustment) return;

    const type = dom.byId("adjustType").value;
    const amount = parseInt(dom.byId("adjustAmount").value, 10);
    const reason = dom.byId("adjustReason").value.trim();
    const adjustment = type === "IN" ? amount : -amount;

    const payload = {
      adjustment: adjustment,
      reason: reason || (type === "IN" ? "Restock intake" : "Engineering deployment"),
      performed_by: "Awanish Chaurasiya"
    };

    request.post("api/assets.php?id=" + activeAssetForAdjustment.id + "&action=adjust", {
      data: JSON.stringify(payload),
      headers: { "Content-Type": "application/json" },
      handleAs: "json"
    }).then(
      function(response) {
        if (response.success) {
          showToast("Stock quantity adjusted successfully!");
          domClass.remove(adjustModal, "active");
          loadAssets();
          loadStats();
        } else {
          showToast(response.error || "Adjustment failed", "error");
        }
      },
      function(err) {
        let msg = "Error adjusting stock";
        if (err.response && err.response.data && err.response.data.error) {
          msg = err.response.data.error;
        }
        showToast(msg, "error");
      }
    );
  });

  // Initialize
  loadCategories();
  loadStats();
  loadAssets();
});
