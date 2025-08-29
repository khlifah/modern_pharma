<?php
require_once __DIR__ . '/auth.php';
if (!function_exists('db')) require_once __DIR__ . '/config.php';
require_once __DIR__ . '/accounting.php';
$pdo = db();

/* الجداول الأساسية */
$pdo->exec("
CREATE TABLE IF NOT EXISTS suppliers(
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(50) NULL,
  address VARCHAR(255) NULL,
  opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS products(
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(100) UNIQUE,
  name VARCHAR(200) NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  cost_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  sale_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS purchase_invoices(
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* تأكيد الأعمدة الناقصة في purchase_invoices
   (الدالة col_exists مستخدمة من accounting.php) */
if (!col_exists($pdo, 'purchase_invoices', 'supplier_id')) {
  $pdo->exec("ALTER TABLE purchase_invoices ADD COLUMN supplier_id INT NULL AFTER invoice_date;");
}
if (!col_exists($pdo, 'purchase_invoices', 'note')) {
  $pdo->exec("ALTER TABLE purchase_invoices ADD COLUMN note VARCHAR(255) NULL AFTER supplier_id;");
}
if (!col_exists($pdo, 'purchase_invoices', 'total_amount')) {
  $pdo->exec("ALTER TABLE purchase_invoices ADD COLUMN total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER note;");
}

$pdo->exec("
CREATE TABLE IF NOT EXISTS purchase_items(
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  cost_price DECIMAL(14,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (invoice_id) REFERENCES purchase_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$suppliers = $pdo->query("SELECT id,name FROM suppliers ORDER BY name")->fetchAll();
$products  = $pdo->query("SELECT id,name,sku,quantity,cost_price FROM products ORDER BY name")->fetchAll();

$errors=[]; $ok=null;

/* حفظ الفاتورة */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save'){
  $date = $_POST['invoice_date'] ?? date('Y-m-d');
  $sid  = (int)($_POST['supplier_id'] ?? 0);
  $note = trim($_POST['note'] ?? '');

  $pids   = $_POST['item_product_id'] ?? [];
  $qtys   = $_POST['item_qty'] ?? [];
  $costs  = $_POST['item_cost'] ?? [];

  $items=[]; $total=0;
  for($i=0;$i<count($pids);$i++){
    $pid = (int)($pids[$i] ?? 0);
    $q   = (int)($qtys[$i] ?? 0);
    $c   = (float)($costs[$i] ?? 0);
    if ($pid>0 && $q>0 && $c>=0){
      $lt = round($q*$c,2);
      $items[] = ['pid'=>$pid,'qty'=>$q,'cost'=>$c,'line'=>$lt];
      $total  += $lt;
    }
  }
  if (!$items) $errors[]='أضف سطرًا واحدًا على الأقل.';

  if (!$errors){
    try{
      $pdo->beginTransaction();

      // رأس الفاتورة
      $st = $pdo->prepare("INSERT INTO purchase_invoices(invoice_date,supplier_id,note,total_amount) VALUES (?,?,?,?)");
      $st->execute([$date, $sid?:null, $note?:null, $total]);
      $inv_id = (int)$pdo->lastInsertId();

      // عناصر + زيادة المخزون
      $sti = $pdo->prepare("INSERT INTO purchase_items(invoice_id,product_id,quantity,cost_price,line_total) VALUES (?,?,?,?,?)");
      $up  = $pdo->prepare("UPDATE products SET quantity=quantity+?, cost_price=? WHERE id=?");
      foreach ($items as $r){
        $sti->execute([$inv_id,$r['pid'],$r['qty'],$r['cost'],$r['line']]);
        $up->execute([$r['qty'],$r['cost'],$r['pid']]);
      }

      // قيد يومية: مدين مخزون / دائن دائنون-مورد (أو مشتريات تحت التسوية)
      $acc_ap = $sid>0 ? "دائنون - مورد #$sid" : "مشتريات تحت التسوية";
      post_journal($pdo, 'purchase', $inv_id, $date, 'فاتورة مشتريات', [
        ['account'=>'المخزون', 'debit'=>$total, 'credit'=>0],
        ['account'=>$acc_ap,   'debit'=>0,      'credit'=>$total],
      ]);

      $pdo->commit();
      $ok = "تم حفظ فاتورة المشتريات #$inv_id وترحيل المخزون والقيد.";
      $_POST=[];
    }catch(Throwable $e){
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $errors[] = 'فشل الحفظ: '.$e->getMessage();
    }
  }
}

/* قائمة آخر الفواتير */
$list = $pdo->query("
  SELECT pi.id,pi.invoice_date,pi.total_amount, s.name supplier
  FROM purchase_invoices pi
  LEFT JOIN suppliers s ON s.id=pi.supplier_id
  ORDER BY pi.id DESC LIMIT 50
")->fetchAll();

$title='فاتورة مشتريات';
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $title ?> - موردن</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Tahoma,Arial,sans-serif;background:#f5f8fc;color:#1f2937}
.header{display:flex;justify-content:space-between;align-items:center;background:#0c2140;color:#fff;padding:12px 16px}
a{color:#64ffda;text-decoration:none}.wrap{padding:18px;display:grid;gap:16px}
.card{background:#fff;border:1px solid #dfe7f5;border-radius:12px;padding:16px}
label{display:block;margin:8px 0 4px}input,select,textarea{width:100%;padding:10px;border-radius:10px;border:1px solid #cfd9ec}
table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border-bottom:1px solid #e7eaf3;padding:8px;text-align:right}th{color:#6b7280}
.btn{border:0;border-radius:10px;padding:9px 12px;background:#13c2b3;color:#062427;font-weight:700;cursor:pointer}
.total{font-weight:700}
</style></head><body>
<div class="header"><div><?= $title ?></div><nav><a href="./dashboard.php">الرجوع</a></nav></div>
<div class="wrap">
  <div class="card">
    <?php foreach($errors as $e):?><div style="background:#ffecec;border:1px solid #ffb3b3;padding:10px;border-radius:10px;margin:6px 0"><?=htmlspecialchars($e,ENT_QUOTES,'UTF-8')?></div><?php endforeach;?>
    <?php if($ok):?><div style="background:#e6fff7;border:1px solid #a7f3d0;padding:10px;border-radius:10px;margin:6px 0"><?=htmlspecialchars($ok,ENT_QUOTES,'UTF-8')?></div><?php endif;?>

    <form method="post" id="f">
      <input type="hidden" name="action" value="save">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
        <div><label>تاريخ الفاتورة</label><input type="date" name="invoice_date" value="<?=htmlspecialchars($_POST['invoice_date']??date('Y-m-d'),ENT_QUOTES,'UTF-8')?>"></div>
        <div>
          <label>المورد (اختياري)</label>
          <select name="supplier_id">
            <option value="">— اختر —</option>
            <?php foreach($suppliers as $s):?>
              <option value="<?=$s['id']?>" <?=((int)($_POST['supplier_id']??0)===$s['id'])?'selected':''?>><?=htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8')?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div style="grid-column:1/-1"><label>ملاحظة</label><textarea name="note" rows="2"><?=htmlspecialchars($_POST['note']??'',ENT_QUOTES,'UTF-8')?></textarea></div>
      </div>

      <h3 style="margin-top:12px">عناصر الفاتورة</h3>
      <table id="t">
        <thead><tr><th>الصنف</th><th>متوفر</th><th>كمية</th><th>سعر الشراء</th><th>الإجمالي</th><th>—</th></tr></thead>
        <tbody>
          <tr>
            <td>
              <select name="item_product_id[]" class="prod">
                <option value="">— اختر —</option>
                <?php foreach($products as $p):?>
                <option value="<?=$p['id']?>" data-qty="<?=$p['quantity']?>" data-cost="<?=$p['cost_price']?>">
                  <?=htmlspecialchars($p['name'],ENT_QUOTES,'UTF-8')?><?=$p['sku']?' ('.htmlspecialchars($p['sku'],ENT_QUOTES,'UTF-8').')':''?>
                </option>
                <?php endforeach;?>
              </select>
            </td>
            <td class="avail">0</td>
            <td><input type="number" name="item_qty[]" class="qty" min="1" step="1" value="1"></td>
            <td><input type="number" name="item_cost[]" class="cost" min="0" step="0.01" value="0"></td>
            <td class="line">0.00</td>
            <td><button type="button" onclick="delRow(this)" class="btn" style="background:#ff4d5a;color:#fff">حذف</button></td>
          </tr>
        </tbody>
        <tfoot>
          <tr><td colspan="6"><button type="button" class="btn" onclick="addRow()">+ إضافة سطر</button></td></tr>
          <tr><td colspan="4" class="total">الإجمالي</td><td class="total" id="gt">0.00</td><td></td></tr>
        </tfoot>
      </table>

      <div style="margin-top:10px"><button class="btn" type="submit">حفظ</button></div>
    </form>
  </div>

  <div class="card">
    <h3>آخر فواتير</h3>
    <table>
      <thead><tr><th>#</th><th>التاريخ</th><th>المورد</th><th>الإجمالي</th></tr></thead>
      <tbody>
        <?php if(!$list):?><tr><td colspan="4">لا يوجد</td></tr>
        <?php else: foreach($list as $r):?>
          <tr><td><?=$r['id']?></td><td><?=$r['invoice_date']?></td><td><?=htmlspecialchars($r['supplier']??'',ENT_QUOTES,'UTF-8')?></td><td><?=number_format($r['total_amount'],2)?></td></tr>
        <?php endforeach; endif;?>
      </tbody>
    </table>
  </div>
</div>

<script>
function recalc(){
  let total=0;
  document.querySelectorAll('#t tbody tr').forEach(tr=>{
    const q=parseFloat(tr.querySelector('.qty').value||0);
    const c=parseFloat(tr.querySelector('.cost').value||0);
    const l=(q>0&&c>=0)?q*c:0;
    tr.querySelector('.line').textContent=l.toFixed(2);
    total+=l;
  });
  document.getElementById('gt').textContent=total.toFixed(2);
}
function bind(tr){
  const prod=tr.querySelector('.prod'), qty=tr.querySelector('.qty'), cost=tr.querySelector('.cost'), avail=tr.querySelector('.avail');
  prod.addEventListener('change', ()=>{
    const o=prod.selectedOptions[0];
    const q=o?parseInt(o.getAttribute('data-qty')||'0',10):0;
    const c=o?parseFloat(o.getAttribute('data-cost')||'0'):0;
    avail.textContent=isNaN(q)?'0':q;
    if(!cost.value||parseFloat(cost.value)===0) cost.value=isNaN(c)?0:c;
    recalc();
  });
  qty.addEventListener('input', recalc);
  cost.addEventListener('input', recalc);
}
function addRow(){
  const tb=document.querySelector('#t tbody'), tr=tb.rows[0].cloneNode(true);
  tr.querySelector('.prod').selectedIndex=0;
  tr.querySelector('.avail').textContent='0';
  tr.querySelector('.qty').value=1;
  tr.querySelector('.cost').value=0;
  tr.querySelector('.line').textContent='0.00';
  tb.appendChild(tr); bind(tr); recalc();
}
function delRow(btn){
  const tb=document.querySelector('#t tbody');
  if(tb.rows.length===1){
    const tr=tb.rows[0];
    tr.querySelector('.prod').selectedIndex=0;
    tr.querySelector('.avail').textContent='0';
    tr.querySelector('.qty').value=1;
    tr.querySelector('.cost').value=0;
    tr.querySelector('.line').textContent='0.00';
  } else btn.closest('tr').remove();
  recalc();
}
bind(document.querySelector('#t tbody tr')); recalc();
</script>
</body></html>
