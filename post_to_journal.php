<?php
require_once __DIR__ . '/auth.php';
if(!function_exists('db')) require_once __DIR__ . '/config.php';
$pdo=db();

$errors=[];$ok=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $what=$_POST['what']??'all';
  try{
    if($what==='receipt'||$what==='all') $pdo->exec("UPDATE receipt_vouchers SET posted=1 WHERE posted=0");
    if($what==='payment'||$what==='all') $pdo->exec("UPDATE payment_vouchers SET posted=1 WHERE posted=0");
    if($what==='adjust'||$what==='all')  $pdo->exec("UPDATE adjustment_entries SET posted=1 WHERE posted=0");
    $ok='تم الترحيل/التعليم بنجاح.';
  }catch(Throwable $e){ $errors[]='خطأ: '.$e->getMessage(); }
}
$rc=(int)$pdo->query("SELECT COUNT(*) FROM receipt_vouchers WHERE posted=0")->fetchColumn();
$pc=(int)$pdo->query("SELECT COUNT(*) FROM payment_vouchers WHERE posted=0")->fetchColumn();
$ac=(int)$pdo->query("SELECT COUNT(*) FROM adjustment_entries WHERE posted=0")->fetchColumn();
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ترحيل العمليات إلى قيود اليومية - موردن</title>
<style>body{margin:0;font-family:Tahoma;background:#f5f8fc}.header{display:flex;justify-content:space-between;align-items:center;background:#0c2140;color:#fff;padding:12px}a{color:#64ffda;text-decoration:none}.wrap{padding:18px}.card{background:#fff;border:1px solid #dfe7f5;border-radius:12px;padding:16px}.btn{border:0;border-radius:10px;padding:10px 14px;background:#13c2ب3;color:#062427;font-weight:700;cursor:pointer}</style></head>
<body>
<div class="header"><div>ترحيل العمليات إلى قيود اليومية</div><nav><a href="./dashboard.php">الرجوع</a></nav></div>
<div class="wrap">
  <div class="card">
    <?php foreach(($errors??[]) as $e):?><div style="background:#fee;border:1px solid #fbb;padding:8px;border-radius:8px;margin:6px 0"><?=htmlspecialchars($e)?></div><?php endforeach;?>
    <?php if($ok):?><div style="background:#e6fff7;border:1px solid #a7f3د0;padding:8px;border-radius:8px;margin:6px 0"><?=htmlspecialchars($ok)?></div><?php endif;?>
    <p>غير مُرحل: قبض (<?=$rc?>) — صرف (<?=$pc?>) — تسويات (<?=$ac?>)</p>
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
      <select name="what">
        <option value="all">الكل</option>
        <option value="receipt">سندات قبض فقط</option>
        <option value="payment">سندات صرف فقط</option>
        <option value="adjust">قيود تسوية فقط</option>
      </select>
      <button class="btn" type="submit">ترحيل</button>
    </form>
  </div>
</div>
</body></html>
