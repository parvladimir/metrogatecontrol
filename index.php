<?php
declare(strict_types=1);
require __DIR__ . '/app/auth.php';
auth_require_login();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Metro Logistics – Gate Control</title>
  <link rel="stylesheet" href="/assets/css/app.css?v=20260114a">
  <link rel="stylesheet" href="/assets/css/hotfix.css?v=hotfix7">
  <script src="/assets/js/hotfix.js?v=hotfix1"></script>
</head>
<body>
  <div class="app">
    <header class="top card">
      <div class="brand">
        <div class="brand__logoWrap">
          <img class="brand__logo" src="/assets/img/metro-logistics.svg" alt="Metro Logistics">
        </div>
        <div class="brand__sub">Gate Control</div>
      </div>

      <div class="karcher">KÄRCHER</div>

      <div class="top__actions" id="topActions"></div>
    </header>

    <!-- Mobile tabs -->
    <div class="tabs card" id="tabs">
      <button class="tab active" id="tabToday" type="button">Today</button>
      <button class="tab" id="tabSchedule" type="button">Schedule</button>
    </div>

    <section class="filters card">
      <div class="f">
        <label>Date</label>
        <input type="date" id="workDate">
      </div>

      <div class="f">
        <label>Carrier</label>
        <select id="carrier"></select>
      </div>

      <div class="f">
        <label>Time</label>
        <select id="time"></select>
      </div>

      <div class="f f-grow">
        <label>Search</label>
        <input id="search" type="search" placeholder="Gate (T153) or trailer/WB/TU...">
      </div>
    </section>

    <!-- ===== Reminders (DB) ===== -->
    <section class="panel card" id="panelReminders">
      <div class="panel__head">
        <div class="panel__title">Reminders</div>
        <div class="panel__hint">DB templates + sticky alerts (pseudo-cron poll every minute).</div>
      </div>

      <div class="remGrid">
        <!-- Left: list -->
        <div class="remList">
          <div class="remList__head">
            <div class="remList__title">Templates</div>
            <button class="btn secondary" id="btnRemNew" type="button">+ New</button>
          </div>
          <div id="remindersList" class="remindersList"></div>
        </div>

        <!-- Right: form -->
        <div class="remForm">
          <div class="remForm__head">
            <div class="remForm__title" id="remFormTitle">New reminder</div>
            <button class="btn secondary" id="btnRemClear" type="button">Clear</button>
          </div>

          <input type="hidden" id="remId" value="">

          <div class="row">
            <div class="f">
              <label>Active</label>
              <select id="remActive">
                <option value="1">On</option>
                <option value="0">Off</option>
              </select>
            </div>

            <div class="f">
              <label>Type</label>
              <select id="remType">
                <option value="daily">Daily (no carrier)</option>
                <option value="schedule">Schedule (carrier pickup)</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="f">
              <label>Carrier (required for Schedule)</label>
              <select id="remCarrier"></select>
            </div>

            <div class="f">
              <label>Time (HH:MM)</label>
              <input id="remTime" placeholder="11:30">
            </div>
          </div>

          <div class="row">
            <div class="f">
              <label>Minutes before</label>
              <input id="remBefore" type="number" min="0" max="1440" value="40">
            </div>

            <div class="f">
              <label>Info</label>
              <input value="For exact time set 0" disabled>
            </div>
          </div>

          <div class="row">
            <div class="f f-grow">
              <label>Message</label>
              <input id="remMessage" placeholder="Soon: close TU / prepare docs.">
            </div>
          </div>

          <div class="sheet__actions">
            <button id="btnRemDelete" class="btn secondary" type="button" style="display:none">Delete</button>
            <button id="btnRemSave" class="btn primary" type="button">Save reminder</button>
          </div>
        </div>
      </div>
    </section>

    <main class="layout">
      <section class="panel card" id="panelSchedule">
        <div class="panel__head">
          <div class="panel__title">Schedule</div>
          <div class="panel__hint">Tap → selects carrier</div>
        </div>
        <div id="scheduleList" class="schedule"></div>
      </section>

      <section class="panel card" id="panelToday">
        <div class="panel__head">
          <div class="panel__title">Today</div>
          <div id="gatesHint" class="panel__hint"></div>
        </div>

        <!-- carrier tiles will be rendered here -->
        <div id="gatesGrid"></div>
      </section>
    </main>
  </div>

  <!-- bottom sheet editor (slots) -->
  <div id="sheetBack" class="sheetBack hidden"></div>
  <div id="sheet" class="sheet hidden" role="dialog" aria-modal="true">
    <div class="sheet__head">
      <div>
        <div id="sheetTitle" class="sheet__title">Edit</div>
        <div id="sheetSub" class="sheet__sub"></div>
      </div>
      <button id="sheetClose" class="iconBtn" type="button">✕</button>
    </div>

    <div class="sheet__body">
      <div class="row">
        <div class="f">
          <label>Date/Time</label>
          <input id="fEventAt" type="datetime-local">
        </div>
        <div class="f">
          <label>Load % (step 5)</label>
          <input id="fPct" type="number" min="0" max="100" step="5">
        </div>
      </div>

      <div id="timeChips" class="chips"></div>

      <div class="row">
        <div class="f">
          <label>Trailer / WB</label>
          <input id="fTrailer" placeholder="e.g. WI-QY-2102 / 516770">
        </div>
        <div class="f">
          <label>TU number</label>
          <input id="fTU" placeholder="e.g. 90002988">
        </div>
      </div>

      <div class="row" id="sealWrap">
        <div class="f">
          <label>Seal number (only if full)</label>
          <input id="fSeal" placeholder="e.g. 9016140">
        </div>
      </div>

      <div class="row">
        <div class="f">
          <label>Pallets</label>
          <input id="fPallets" inputmode="numeric" placeholder="e.g. 6">
        </div>
        <div class="f">
          <label>Rollis</label>
          <input id="fRollis" inputmode="numeric" placeholder="e.g. 12">
        </div>
        <div class="f">
          <label>Packages</label>
          <input id="fPackages" inputmode="numeric" placeholder="e.g. 496">
        </div>
      </div>

      <div class="row">
        <div class="f f-grow">
          <label>Remark</label>
          <input id="fRemark" placeholder="e.g. only 1 WB / no pickup today">
        </div>
      </div>

      <div class="sheet__actions">
        <button id="btnClear" class="btn secondary" type="button">Clear</button>
        <button id="btnSave" class="btn primary" type="button">Save</button>
      </div>
    </div>
  </div>

  <div id="toast" class="toast hidden"></div>

  <script>
    window.__BASE__ = '';
  </script>
  <script src="/assets/js/app.js?v=20260118g"></script>

<footer style="position:fixed;left:0;right:0;bottom:0;padding:10px 16px;font-size:12px;opacity:.75;display:flex;justify-content:space-between;pointer-events:none;">
  <div>Developed by Volodymyr Parashchak</div>
  <div style="pointer-events:auto;"><a href="/logout.php" style="color:inherit;">Logout</a></div>
</footer>
</body>
</html>
