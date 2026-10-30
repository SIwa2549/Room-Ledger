/* =====================================================================
   ไฟล์: assets/js/app.js
   ต้นแบบหน้าจอ (Prototype) ด้วย "ข้อมูลตัวอย่าง" (Mock Data) เท่านั้น
   ทุกการแก้ไขอยู่ในหน่วยความจำของเบราว์เซอร์ ปิดหน้าแล้วหาย ยังไม่ได้บันทึกลง MySQL
   เมื่อทำ Backend: แทนที่ข้อมูล MOCK ด้วยข้อมูลจาก PHP (SELECT ... ด้วย Prepared Statement)
   ===================================================================== */
(function () {
  'use strict';

  // ---------- ข้อมูลตัวอย่าง (MOCK DATA) ----------
  const BILL_MONTH = 'ต.ค. 2569';               // รอบบิลปัจจุบัน
  const rates = { water: 18, elec: 8, flat: 150 }; // บาท/หน่วย, บาท/หน่วย, เหมาจ่ายบาท/เดือน

  // room: no, floor, price, water (meter|flat_rate), status (available|occupied|maintenance)
  // tenant, phone, since, prev = เลขมิเตอร์เดือนก่อน, cur = เลขเดือนนี้ (null = ยังไม่จด)
  // overdue = ยอดค้างชำระ (บาท), overdueDays, issue = ปัญหาที่แจ้งซ่อม
  const rooms = [
    r('301', 3, 4000, 'meter',     'occupied',   'กนกพร ศรีสุข',     '081-234-5501', '2025-06-01', 1204, 5630, {w: 1216, e: 5752}),
    r('302', 3, 4000, 'meter',     'occupied',   'ธนพล วงศ์ใหญ่',     '089-110-2290', '2024-11-15', 980,  4412, null, 4384, 12),
    r('303', 3, 4000, 'flat_rate', 'available'),
    r('304', 3, 4000, 'meter',     'occupied',   'ปิยะธิดา แก้วมณี',   '062-554-8812', '2026-01-10', 655,  3021, null),
    r('305', 3, 4000, 'meter',     'maintenance', null, null, null, 410, 2208, null, 0, 0, 'แอร์ไม่เย็น รอช่างเปลี่ยนคอมเพรสเซอร์'),
    r('306', 3, 4000, 'flat_rate', 'occupied',   'ณัฐวุฒิ บุญมา',     '095-003-4417', '2025-08-01', 0,    6120, {w: 0, e: 6248}),
    r('201', 2, 3800, 'meter',     'occupied',   'อรอุมา ทองดี',      '086-778-1203', '2023-07-01', 1530, 7810, {w: 1541, e: 7932}),
    r('202', 2, 3800, 'meter',     'occupied',   'ภานุวัฒน์ ชัยมงคล',  '083-901-5566', '2025-02-20', 802,  3994, null),
    r('203', 2, 3800, 'flat_rate', 'available'),
    r('204', 2, 3800, 'meter',     'occupied',   'สุดารัตน์ พรหมมา',   '064-221-0098', '2024-05-01', 1102, 5207, null, 4266, 7),
    r('205', 2, 3800, 'meter',     'occupied',   'วีรยุทธ อินทร์แก้ว',  '092-640-7731', '2026-03-01', 377,  1890, {w: 389, e: 2011}),
    r('206', 2, 3800, 'flat_rate', 'occupied',   'ชลธิชา มีสุข',       '087-312-6640', '2025-10-01', 0,    4470, null),
    r('101', 1, 3500, 'meter',     'occupied',   'ศุภกร รัตนพันธ์',    '080-455-2219', '2024-09-01', 1688, 8105, {w: 1701, e: 8240}),
    r('102', 1, 3500, 'meter',     'available'),
    r('103', 1, 3500, 'meter',     'occupied',   'พิมพ์ชนก สายทอง',    '061-876-3354', '2025-12-01', 512,  2730, null, 3911, 3),
    r('104', 1, 3500, 'meter',     'maintenance', null, null, null, 220, 990, null, 0, 0, 'ท่อน้ำใต้ซิงก์รั่ว ซึมลงพื้น'),
    r('105', 1, 3500, 'flat_rate', 'occupied',   'กิตติพัฒน์ นาคสวัสดิ์', '098-120-4478', '2026-02-01', 0, 3350, {w: 0, e: 3470}),
    r('106', 1, 3500, 'meter',     'available'),
  ];

  function r(no, floor, price, water, status, tenant, phone, since, pw, pe, cur, overdue, overdueDays, issue) {
    return { no, floor, price, water, status, tenant: tenant || null, phone: phone || null, since: since || null,
             prev: { w: pw || 0, e: pe || 0 }, cur: cur || null, overdue: overdue || 0, overdueDays: overdueDays || 0,
             issue: issue || null, history: makeHistory(price, water, status, overdue) };
  }

  // ประวัติบิลย้อนหลัง 3 เดือน (ตัวอย่าง)
  function makeHistory(price, water, status, overdue) {
    if (status !== 'occupied') return [];
    const months = ['ก.ย. 2569', 'ส.ค. 2569', 'ก.ค. 2569'];
    return months.map((m, i) => {
      const e = 96 + ((price / 100 + i * 7) % 40);
      const w = water === 'meter' ? 9 + (i % 4) : null;
      const total = price + e * 8 + (w === null ? 150 : w * 18);
      return { month: m, eUnits: e, wUnits: w, total, paid: !(i === 0 && overdue) };
    });
  }

  // ---------- เครื่องมือช่วย ----------
  const $ = (s, el = document) => el.querySelector(s);
  const baht = n => '฿' + Number(n).toLocaleString('th-TH');
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const thDate = iso => { const [y, m, d] = iso.split('-').map(Number); return `${d} ${['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'].at(m - 1)} ${y + 543}`; };

  // สถานะที่แสดงผล: ค้างชำระเป็นกรณีย่อยของ "มีผู้เช่า"
  const viewStatus = room => room.status === 'maintenance' ? 'repair' : room.status === 'available' ? 'free' : room.overdue > 0 ? 'overdue' : 'rented';
  const LABEL = { free: 'ว่าง', rented: 'มีผู้เช่า', overdue: 'ค้างชำระ', repair: 'ซ่อมบำรุง' };
  const pill = st => `<span class="pill pill-${st}">${LABEL[st]}</span>`;

  function counts() {
    const c = { total: rooms.length, free: 0, rented: 0, overdue: 0, repair: 0 };
    rooms.forEach(x => c[viewStatus(x)]++);
    c.occupied = c.rented + c.overdue;
    return c;
  }

  // ---------- ช่องห้องในผังอาคาร ----------
  function roomTile(x) {
    const st = viewStatus(x);
    let meta = 'ว่าง พร้อมเข้าอยู่';
    if (st === 'rented')  meta = x.tenant;
    if (st === 'overdue') meta = 'ค้าง ' + baht(x.overdue);
    if (st === 'repair')  meta = 'ซ่อมบำรุง';
    return `<button class="room room--${st}" type="button" data-room="${x.no}" aria-label="ห้อง ${x.no} ${LABEL[st]}">
      <span class="room-no">${x.no}</span><span class="room-meta">${esc(meta)}</span></button>`;
  }

  function buildingHTML(list) {
    const floors = [...new Set(rooms.map(x => x.floor))].sort((a, b) => b - a); // ชั้นบนสุดอยู่ด้านบน
    const rows = floors.map(f => {
      const inFloor = list.filter(x => x.floor === f);
      if (!inFloor.length) return '';
      return `<div class="floor"><span class="floor-label">ชั้น ${f}</span>${inFloor.map(roomTile).join('')}</div>`;
    }).join('');
    return rows || `<div class="empty"><p>ไม่พบห้องที่ตรงกับตัวกรอง ลองล้างคำค้นหาหรือเลือก "ทั้งหมด"</p></div>`;
  }

  // ---------- หน้า Dashboard ----------
  function renderDashboard() {
    const c = counts();
    const pct = n => (n / c.total * 100) + '%';
    const debtors = rooms.filter(x => x.overdue > 0).sort((a, b) => b.overdueDays - a.overdueDays);
    const occupied = rooms.filter(x => x.status === 'occupied');
    const done = occupied.filter(x => x.cur).length;
    const debtSum = debtors.reduce((s, x) => s + x.overdue, 0);

    $('#view').innerHTML = `
      <header class="page-head">
        <div><h1>ภาพรวมหอพัก</h1><p>รอบบิล ${BILL_MONTH}</p></div>
        <a class="btn btn-primary" href="#rooms">จดมิเตอร์ประจำเดือน</a>
      </header>

      <section class="summary" aria-label="สรุปสถานะห้อง">
        <div class="summary-top">
          <div class="summary-total"><strong class="num">${c.total}</strong><span class="muted">ห้องทั้งหมด</span></div>
          <ul class="summary-stats">
            <li><span class="dot dot-free"></span>ว่าง <b class="num">${c.free}</b></li>
            <li><span class="dot dot-rented"></span>มีผู้เช่า <b class="num">${c.occupied}</b></li>
            <li><span class="dot dot-overdue"></span>ค้างชำระ <b class="num">${c.overdue}</b></li>
            <li><span class="dot dot-repair"></span>ซ่อมบำรุง <b class="num">${c.repair}</b></li>
          </ul>
        </div>
        <div class="stack-bar" role="img" aria-label="สัดส่วนห้อง ว่าง ${c.free} มีผู้เช่า ${c.rented} ค้างชำระ ${c.overdue} ซ่อม ${c.repair}">
          <span class="seg-rented" style="width:${pct(c.rented)}"></span><span class="seg-overdue" style="width:${pct(c.overdue)}"></span><span class="seg-free" style="width:${pct(c.free)}"></span><span class="seg-repair" style="width:${pct(c.repair)}"></span>
        </div>
      </section>

      <div class="dash-grid">
        <section class="building" aria-label="ผังห้องตามชั้น">
          <div class="building-head"><h2>ผังห้อง</h2><span class="muted" style="font-size:var(--fs-xs)">กดที่ห้องเพื่อดูรายละเอียด</span></div>
          ${buildingHTML(rooms)}
          <div class="ground" aria-hidden="true"></div>
        </section>

        <aside class="side">
          <section class="panel" aria-label="ห้องค้างชำระ">
            <div class="panel-head"><h3>ค้างชำระ</h3><span class="num" style="font-weight:600;color:var(--overdue)">${baht(debtSum)}</span></div>
            ${debtors.length ? `<ul class="debt-list">${debtors.map(x => `<li><button class="debt-item" type="button" data-room="${x.no}">
                <span class="debt-room">${x.no}</span>
                <span style="min-width:0"><span class="debt-name" style="display:block">${esc(x.tenant)}</span><span class="debt-days">เกินกำหนด ${x.overdueDays} วัน</span></span>
                <span class="debt-amt">${baht(x.overdue)}</span></button></li>`).join('')}</ul>`
              : '<p class="muted" style="font-size:var(--fs-sm)">ทุกห้องชำระครบแล้ว</p>'}
          </section>

          <section class="panel" aria-label="ความคืบหน้าการจดมิเตอร์">
            <div class="panel-head"><h3>จดมิเตอร์ ${BILL_MONTH}</h3><span class="num muted" style="font-size:var(--fs-sm)">${done} / ${occupied.length} ห้อง</span></div>
            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="${occupied.length}" aria-valuenow="${done}"><span style="width:${occupied.length ? done / occupied.length * 100 : 0}%"></span></div>
            <p class="muted" style="font-size:var(--fs-sm)">${occupied.length - done ? `เหลืออีก ${occupied.length - done} ห้องที่ยังไม่ได้จดเลขมิเตอร์` : 'จดครบทุกห้องแล้ว'}</p>
            ${occupied.length - done ? `<button class="btn" type="button" data-next-meter>จดห้องถัดไป</button>` : ''}
          </section>
        </aside>
      </div>`;
  }

  // ---------- หน้า Room Management ----------
  const roomUI = { filter: 'all', q: '', layout: 'grid' };

  function renderRooms() {
    const c = counts();
    const chips = [['all', 'ทั้งหมด', c.total], ['free', 'ว่าง', c.free], ['rented', 'มีผู้เช่า', c.rented], ['overdue', 'ค้างชำระ', c.overdue], ['repair', 'ซ่อมบำรุง', c.repair]];
    $('#view').innerHTML = `
      <header class="page-head"><div><h1>ห้องพัก</h1><p>${c.total} ห้อง ใน 3 ชั้น</p></div></header>
      <div class="toolbar">
        <label class="search"><span class="visually-hidden" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">ค้นหาห้องหรือชื่อผู้เช่า</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input id="room-search" type="search" placeholder="ค้นหาเลขห้องหรือชื่อผู้เช่า" value="${esc(roomUI.q)}" autocomplete="off"></label>
        <div class="segmented" role="group" aria-label="รูปแบบการแสดง">
          <button type="button" data-layout="grid" aria-pressed="${roomUI.layout === 'grid'}">ผังอาคาร</button>
          <button type="button" data-layout="list" aria-pressed="${roomUI.layout === 'list'}">รายการ</button>
        </div>
      </div>
      <div class="chips" role="group" aria-label="กรองตามสถานะ">
        ${chips.map(([k, l, n]) => `<button class="chip" type="button" data-filter="${k}" aria-pressed="${roomUI.filter === k}">${l} <span class="count">${n}</span></button>`).join('')}
      </div>
      <div id="room-results"></div>`;
    renderRoomResults();
  }

  function renderRoomResults() {
    const q = roomUI.q.trim().toLowerCase();
    const list = rooms.filter(x => (roomUI.filter === 'all' || viewStatus(x) === roomUI.filter) &&
      (!q || x.no.includes(q) || (x.tenant || '').toLowerCase().includes(q)));
    const box = $('#room-results');
    if (roomUI.layout === 'grid') {
      box.innerHTML = `<section class="building" aria-label="ผังห้องตามชั้น">${buildingHTML(list)}<div class="ground" aria-hidden="true"></div></section>`;
      return;
    }
    box.innerHTML = list.length ? `<div class="table-wrap"><table>
      <thead><tr><th>ห้อง</th><th>สถานะ</th><th>ผู้เช่า</th><th class="right">ค่าเช่า/เดือน</th><th>ค่าน้ำ</th><th class="right">ยอดค้าง</th></tr></thead>
      <tbody>${list.map(x => `<tr class="clickable" data-room="${x.no}" tabindex="0">
        <td class="num"><b>${x.no}</b></td><td>${pill(viewStatus(x))}</td><td>${x.tenant ? esc(x.tenant) : '<span class="muted">-</span>'}</td>
        <td class="right num">${baht(x.price)}</td><td>${x.water === 'meter' ? 'ตามมิเตอร์' : 'เหมาจ่าย'}</td>
        <td class="right num" style="${x.overdue ? 'color:var(--overdue);font-weight:600' : ''}">${x.overdue ? baht(x.overdue) : '-'}</td></tr>`).join('')}</tbody></table></div>`
      : `<div class="empty"><p>ไม่พบห้องที่ตรงกับ "${esc(roomUI.q)}"</p><button class="btn btn-sm" type="button" data-clear>ล้างการค้นหา</button></div>`;
  }

  // ---------- Room Detail (ลิ้นชักด้านข้าง) ----------
  let openNo = null;

  function openRoom(no) {
    const x = rooms.find(y => y.no === no);
    if (!x) return;
    openNo = no;
    const st = viewStatus(x);
    const drawer = $('#drawer');
    drawer.innerHTML = `
      <div class="drawer-head">
        <div class="drawer-title"><h2 id="drawer-title">ห้อง ${x.no}</h2>${pill(st)}</div>
        <button class="btn btn-ghost icon-btn" type="button" data-close aria-label="ปิด"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
      </div>

      <section><h3>ข้อมูลห้อง</h3>
        <dl class="facts"><dt>ชั้น</dt><dd>${x.floor}</dd><dt>ค่าเช่า</dt><dd>${baht(x.price)} / เดือน</dd>
          <dt>ค่าน้ำ</dt><dd>${x.water === 'meter' ? `ตามมิเตอร์ ${rates.water} บาท/หน่วย` : `เหมาจ่าย ${baht(rates.flat)} / เดือน`}</dd>
          <dt>ค่าไฟ</dt><dd>${rates.elec} บาท/หน่วย</dd></dl>
      </section>

      ${x.status === 'maintenance' ? `<section><h3>งานซ่อม</h3><div class="empty"><p>${esc(x.issue)}</p>
          <button class="btn btn-sm" type="button" data-repair-done>ซ่อมเสร็จ เปิดให้เช่าได้</button></div></section>` : ''}

      <section><h3>ผู้เช่า</h3>
        ${x.tenant ? `<dl class="facts"><dt>ชื่อ</dt><dd>${esc(x.tenant)}</dd><dt>โทร</dt><dd>${esc(x.phone)}</dd><dt>เข้าพักตั้งแต่</dt><dd>${thDate(x.since)}</dd></dl>`
          : `<div class="empty"><p>${x.status === 'maintenance' ? 'ยังไม่มีผู้เช่า ห้องนี้อยู่ระหว่างซ่อม' : 'ห้องนี้ว่าง ยังไม่มีผู้เช่า'}</p>${x.status === 'available' ? '<a class="btn btn-sm" href="#tenants" data-close>ไปหน้าผู้เช่า</a>' : ''}</div>`}
      </section>

      ${x.overdue ? `<section><h3>ยอดค้างชำระ</h3><div class="bill">
          <div class="bill-row"><span>บิล ก.ย. 2569 · เกินกำหนด ${x.overdueDays} วัน</span><span style="color:var(--overdue);font-weight:600">${baht(x.overdue)}</span></div>
          <div><button class="btn btn-sm" type="button" data-mark-paid>บันทึกว่าชำระแล้ว</button></div></div></section>` : ''}

      ${x.status === 'occupied' ? meterFormHTML(x) : ''}

      ${x.history.length ? `<section><h3>ประวัติบิล</h3><div class="table-wrap"><table>
          <thead><tr><th>เดือน</th><th class="right">ไฟ (หน่วย)</th><th class="right">น้ำ</th><th class="right">รวม</th><th>สถานะ</th></tr></thead>
          <tbody>${x.history.map(h => `<tr><td>${h.month}</td><td class="right num">${h.eUnits}</td><td class="right num">${h.wUnits === null ? 'เหมาจ่าย' : h.wUnits + ' หน่วย'}</td>
            <td class="right num">${baht(h.total)}</td><td>${h.paid ? '<span class="pill pill-paid">ชำระแล้ว</span>' : pill('overdue')}</td></tr>`).join('')}</tbody></table></div></section>` : ''}`;
    $('#backdrop').hidden = false;
    drawer.hidden = false;
    drawer.focus();
    if (x.status === 'occupied') updateBillPreview();
  }

  function closeRoom() {
    $('#backdrop').hidden = true;
    $('#drawer').hidden = true;
    const back = openNo && document.querySelector(`[data-room="${openNo}"]`);
    openNo = null;
    if (back) back.focus();
  }

  // ---------- ฟอร์มจดมิเตอร์ + คำนวณบิล ----------
  function meterFormHTML(x) {
    const cur = x.cur || {};
    return `<section><h3>จดมิเตอร์ ${BILL_MONTH}</h3>
      <form class="form" id="meter-form" novalidate>
        <div class="field-row">
          <div class="field"><label for="m-elec">เลขมิเตอร์ไฟ</label>
            <input id="m-elec" name="elec" inputmode="numeric" value="${cur.e ?? ''}" aria-describedby="m-elec-hint m-elec-err">
            <span class="hint" id="m-elec-hint">เดือนก่อน ${x.prev.e.toLocaleString('th-TH')}</span><span class="error" id="m-elec-err"></span></div>
          ${x.water === 'meter' ? `<div class="field"><label for="m-water">เลขมิเตอร์น้ำ</label>
            <input id="m-water" name="water" inputmode="numeric" value="${cur.w ?? ''}" aria-describedby="m-water-hint m-water-err">
            <span class="hint" id="m-water-hint">เดือนก่อน ${x.prev.w.toLocaleString('th-TH')}</span><span class="error" id="m-water-err"></span></div>`
          : `<div class="field"><span class="hint" style="padding-top:28px">ห้องนี้ค่าน้ำเหมาจ่าย ${baht(rates.flat)} ไม่ต้องจดมิเตอร์น้ำ</span></div>`}
        </div>
        <div class="bill" id="bill-preview" aria-live="polite"></div>
        <button class="btn btn-primary" type="submit">${x.cur ? 'บันทึกการแก้ไขบิล' : 'บันทึกบิล ' + BILL_MONTH}</button>
      </form></section>`;
  }

  // ตรวจค่าที่กรอก คืน {value} หรือ {error}
  function readMeter(input, prev, label) {
    const raw = input.value.trim().replace(/,/g, '');
    if (raw === '') return { error: `กรอกเลขมิเตอร์${label}` };
    if (!/^\d+$/.test(raw)) return { error: 'กรอกเป็นตัวเลขจำนวนเต็มเท่านั้น' };
    const v = Number(raw);
    if (v < prev) return { error: `ต้องไม่น้อยกว่าเลขเดือนก่อน (${prev.toLocaleString('th-TH')})` };
    if (v - prev > 5000) return { error: `ใช้ไป ${(v - prev).toLocaleString('th-TH')} หน่วย มากผิดปกติ ตรวจเลขอีกครั้ง` };
    return { value: v };
  }

  function updateBillPreview(showErrors) {
    const x = rooms.find(y => y.no === openNo);
    const form = $('#meter-form');
    if (!x || !form) return null;
    const e = readMeter($('#m-elec'), x.prev.e, 'ไฟ');
    const w = x.water === 'meter' ? readMeter($('#m-water'), x.prev.w, 'น้ำ') : { value: null };

    [['m-elec', e], ['m-water', w]].forEach(([id, res]) => {
      const input = document.getElementById(id);
      if (!input) return;
      const msg = showErrors || input.value.trim() ? (res.error || '') : '';
      document.getElementById(id + '-err').textContent = msg;
      input.setAttribute('aria-invalid', msg ? 'true' : 'false');
    });

    const eUnits = e.error ? null : e.value - x.prev.e;
    const wUnits = x.water === 'meter' ? (w.error ? null : w.value - x.prev.w) : null;
    const elecCost = eUnits === null ? null : eUnits * rates.elec;
    const waterCost = x.water === 'meter' ? (wUnits === null ? null : wUnits * rates.water) : rates.flat;
    const ready = elecCost !== null && waterCost !== null;
    const fmt = v => v === null ? '-' : baht(v);

    $('#bill-preview').innerHTML = `
      <div class="bill-row"><span>ค่าเช่า</span><span>${baht(x.price)}</span></div>
      <div class="bill-row"><span>ค่าไฟ ${eUnits === null ? '' : `${eUnits} หน่วย × ${rates.elec}`}</span><span>${fmt(elecCost)}</span></div>
      <div class="bill-row"><span>ค่าน้ำ ${x.water === 'meter' ? (wUnits === null ? '' : `${wUnits} หน่วย × ${rates.water}`) : 'เหมาจ่าย'}</span><span>${fmt(waterCost)}</span></div>
      <div class="bill-row bill-total"><span>รวม ${BILL_MONTH}</span><span>${ready ? baht(x.price + elecCost + waterCost) : 'กรอกเลขมิเตอร์ให้ครบ'}</span></div>`;
    return ready ? { e: e.value, w: x.water === 'meter' ? w.value : 0 } : null;
  }

  // ---------- หน้าผู้เช่า / แจ้งซ่อม / อัตราค่าบริการ ----------
  function renderTenants() {
    const list = rooms.filter(x => x.tenant).sort((a, b) => a.no.localeCompare(b.no));
    $('#view').innerHTML = `
      <header class="page-head"><div><h1>ผู้เช่า</h1><p>${list.length} คน · การเพิ่มและแก้ไขผู้เช่าจะทำในเฟสถัดไป</p></div></header>
      <div class="table-wrap"><table><thead><tr><th>ชื่อ</th><th>ห้อง</th><th>โทร</th><th>เข้าพักตั้งแต่</th><th>สถานะบิล</th></tr></thead>
      <tbody>${list.map(x => `<tr class="clickable" data-room="${x.no}" tabindex="0"><td>${esc(x.tenant)}</td><td class="num">${x.no}</td><td class="num">${esc(x.phone)}</td><td>${thDate(x.since)}</td>
        <td>${x.overdue ? pill('overdue') : '<span class="pill pill-paid">ปกติ</span>'}</td></tr>`).join('')}</tbody></table></div>`;
  }

  function renderMaintenance() {
    const list = rooms.filter(x => x.status === 'maintenance');
    $('#view').innerHTML = `
      <header class="page-head"><div><h1>แจ้งซ่อม</h1><p>${list.length ? `${list.length} ห้องกำลังซ่อม ปล่อยเช่าไม่ได้จนกว่าจะเสร็จ` : 'ไม่มีห้องที่กำลังซ่อม'}</p></div></header>
      ${list.length ? `<div class="table-wrap"><table><thead><tr><th>ห้อง</th><th>ปัญหา</th><th>สถานะ</th></tr></thead>
        <tbody>${list.map(x => `<tr class="clickable" data-room="${x.no}" tabindex="0"><td class="num"><b>${x.no}</b></td><td style="white-space:normal">${esc(x.issue)}</td><td>${pill('repair')}</td></tr>`).join('')}</tbody></table></div>`
        : `<div class="empty"><p>ทุกห้องพร้อมใช้งาน</p></div>`}`;
  }

  function renderRates() {
    $('#view').innerHTML = `
      <header class="page-head"><div><h1>อัตราค่าบริการ</h1><p>ใช้คำนวณบิลรอบ ${BILL_MONTH} เป็นต้นไป บิลเก่าคงราคาเดิม</p></div></header>
      <section class="panel" style="max-width:560px">
        <form class="form" id="rates-form" novalidate>
          ${rateField('r-elec', 'ค่าไฟ (บาท/หน่วย)', rates.elec, 'การไฟฟ้าส่วนภูมิภาคคิดประมาณ 4-5 บาท หอพักส่วนใหญ่คิด 7-9 บาท')}
          ${rateField('r-water', 'ค่าน้ำตามมิเตอร์ (บาท/หน่วย)', rates.water, 'ใช้กับห้องที่ตั้งค่าน้ำแบบ "ตามมิเตอร์"')}
          ${rateField('r-flat', 'ค่าน้ำเหมาจ่าย (บาท/เดือน)', rates.flat, 'ใช้กับห้องที่ตั้งค่าน้ำแบบ "เหมาจ่าย"')}
          <div><button class="btn btn-primary" type="submit">บันทึกอัตรา</button></div>
        </form>
      </section>`;
  }
  const rateField = (id, label, v, hint) => `<div class="field"><label for="${id}">${label}</label>
    <input id="${id}" inputmode="decimal" value="${v}" aria-describedby="${id}-hint ${id}-err"><span class="hint" id="${id}-hint">${hint}</span><span class="error" id="${id}-err"></span></div>`;

  // ---------- Router (#dashboard, #rooms, ...) ----------
  const VIEWS = { dashboard: renderDashboard, rooms: renderRooms, tenants: renderTenants, maintenance: renderMaintenance, rates: renderRates };
  function route() {
    const name = VIEWS[location.hash.slice(1)] ? location.hash.slice(1) : 'dashboard';
    document.querySelectorAll('[data-nav]').forEach(a => { if (a.dataset.nav === name) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
    VIEWS[name]();
    $('#view').scrollIntoView({ block: 'start' });
  }

  let toastTimer;
  function toast(msg) {
    const t = $('#toast');
    t.textContent = msg; t.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(() => { t.hidden = true; }, 3200);
  }

  // ---------- Events ----------
  document.addEventListener('click', ev => {
    const el = ev.target.closest('button, a, tr.clickable');
    if (!el) return;
    if (el.matches('[data-close]')) { closeRoom(); return; }
    if (el.dataset.room && !el.closest('#drawer')) { openRoom(el.dataset.room); return; }
    if (el.dataset.filter) { roomUI.filter = el.dataset.filter; renderRooms(); $(`[data-filter="${roomUI.filter}"]`).focus(); return; }
    if (el.dataset.layout) { roomUI.layout = el.dataset.layout; renderRooms(); $(`[data-layout="${roomUI.layout}"]`).focus(); return; }
    if (el.matches('[data-clear]')) { roomUI.q = ''; roomUI.filter = 'all'; renderRooms(); $('#room-search').focus(); return; }
    if (el.matches('[data-next-meter]')) { const n = rooms.find(x => x.status === 'occupied' && !x.cur); if (n) openRoom(n.no); return; }
    if (el.matches('[data-mark-paid]')) {
      const x = rooms.find(y => y.no === openNo); x.overdue = 0; x.overdueDays = 0; x.history.forEach(h => { h.paid = true; });
      toast(`ห้อง ${x.no} ชำระแล้ว (ข้อมูลตัวอย่าง ยังไม่บันทึกลงฐานข้อมูล)`); openRoom(x.no); route(); return;
    }
    if (el.matches('[data-repair-done]')) {
      const x = rooms.find(y => y.no === openNo); x.status = 'available'; x.issue = null;
      toast(`ห้อง ${x.no} เปิดให้เช่าแล้ว (ข้อมูลตัวอย่าง)`); openRoom(x.no); route(); return;
    }
    if (el.matches('[data-logout]')) { toast('ในระบบจริง ปุ่มนี้จะไปที่ logout.php'); }
  });

  document.addEventListener('keydown', ev => {
    if (ev.key === 'Escape' && !$('#drawer').hidden) closeRoom();
    if (ev.key === 'Enter' && ev.target.matches('tr.clickable')) openRoom(ev.target.dataset.room);
  });

  document.addEventListener('input', ev => {
    if (ev.target.id === 'room-search') { roomUI.q = ev.target.value; renderRoomResults(); }
    if (ev.target.closest('#meter-form')) updateBillPreview(false);
  });

  document.addEventListener('submit', ev => {
    ev.preventDefault();
    if (ev.target.id === 'meter-form') {
      const res = updateBillPreview(true);
      if (!res) { const bad = ev.target.querySelector('[aria-invalid="true"]'); if (bad) bad.focus(); return; }
      const x = rooms.find(y => y.no === openNo);
      x.cur = res;
      toast(`บันทึกบิลห้อง ${x.no} แล้ว (ข้อมูลตัวอย่าง ยังไม่บันทึกลงฐานข้อมูล)`);
      route(); openRoom(x.no);
    }
    if (ev.target.id === 'rates-form') {
      const checks = [['r-elec', 'elec', 1, 30], ['r-water', 'water', 1, 100], ['r-flat', 'flat', 0, 2000]];
      let ok = true, first = null;
      const next = {};
      checks.forEach(([id, key, min, max]) => {
        const input = document.getElementById(id), raw = input.value.trim(), v = Number(raw);
        let msg = '';
        if (raw === '') msg = 'กรอกอัตรานี้';
        else if (!/^\d+(\.\d{1,2})?$/.test(raw)) msg = 'กรอกเป็นตัวเลข ทศนิยมไม่เกิน 2 ตำแหน่ง';
        else if (v < min || v > max) msg = `ต้องอยู่ระหว่าง ${min}-${max}`;
        document.getElementById(id + '-err').textContent = msg;
        input.setAttribute('aria-invalid', msg ? 'true' : 'false');
        if (msg) { ok = false; first = first || input; } else next[key] = v;
      });
      if (!ok) { first.focus(); return; }
      Object.assign(rates, next);
      toast('บันทึกอัตราแล้ว (ข้อมูลตัวอย่าง ยังไม่บันทึกลงฐานข้อมูล)');
    }
  });

  $('#backdrop').addEventListener('click', closeRoom);
  window.addEventListener('hashchange', () => { closeRoom(); route(); });
  route();
})();
