<?php
// dashboard.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php'; // يحمي الصفحة ويحمّل config.php
$name = !empty($_SESSION['full_name']) ? $_SESSION['full_name'] : ($_SESSION['username'] ?? 'مستخدم');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>لوحة التحكم - موردن</title>
<style>
:root{
  --bg:#eef3f9; --panel:#f5f8fc; --head:#c7d5ea; --head-text:#2f3d57;
  --text:#1f2937; --muted:#6b7280; --accent:#64ffda; --border:#d4dfef;
}
*{box-sizing:border-box}
body{margin:0;font-family:Tahoma,Arial,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:#0c2140;color:#fff}
.header a{color:var(--accent);text-decoration:none}

.layout{display:grid;grid-template-columns:280px 1fr;gap:16px;align-items:start;padding:16px}
.side{
  position:sticky; top:16px;
  background:var(--panel); border:1px solid var(--border); border-radius:12px; padding:10px;
  box-shadow:0 6px 18px rgba(0,0,0,.05)
}
.group{border:1px solid var(--border); border-radius:10px; background:#fff; overflow:hidden; margin:10px 0}
.group-head{
  width:100%; text-align:right; padding:10px 12px; cursor:pointer; border:0;
  background:linear-gradient(#e8eef8,#d9e4f5); color:var(--head-text);
  font-weight:700; border-bottom:1px solid var(--border)
}
.group-head::before{content:"▾"; margin-left:6px; font-weight:400}
.group.closed .group-head::before{content:"▸"}
.group-body{list-style:none; margin:0; padding:8px 12px; background:#fff}
.group-body li{padding:6px 2px; border-bottom:1px dashed #e7eaf3}
.group-body li:last-child{border-bottom:0}
.group-body a{color:#1f2a44; text-decoration:none}
.group-body a:hover{color:#0d5e56; text-decoration:underline}

.content{
  background:#fff; border:1px solid var(--border); border-radius:12px; padding:18px;
  box-shadow:0 6px 18px rgba(0,0,0,.05)
}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:8px}
.kpi{background:#f7fafc;border:1px solid var(--border);border-radius:12px;padding:14px}
.kpi h3{margin:0 0 6px;font-size:15px;color:#6b7280}
.kpi .v{font-size:22px;font-weight:700;color:#1f2937}
</style>
</head>
<body>
  <div class="header">
    <div>مرحباً، <?= htmlspecialchars($name,ENT_QUOTES,'UTF-8') ?></div>
    <nav><a href="./logout.php">تسجيل الخروج</a></nav>
  </div>

  <div class="layout">
    <!-- القائمة الجانبية -->
    <?php require __DIR__ . '/menu.php'; ?>

    <!-- محتوى الصفحة -->
    <main class="content">
      <h2>لوحة التحكم</h2>
      <p class="muted">اختر العملية من القائمة الجانبية.</p>

      <div class="kpis">
        <div class="kpi"><h3>سريع</h3><div class="v"><a href="./suppliers.php">إدارة الموردين</a></div></div>
        <div class="kpi"><h3>سريع</h3><div class="v"><a href="./inventory.php">إدارة المخزون</a></div></div>
        <div class="kpi"><h3>تقارير</h3><div class="v"><a href="./suppliers_report.php">تقارير الموردين</a></div></div>
      </div>
    </main>
  </div>

<script>
// طيّ/فتح الأقسام
document.querySelectorAll('.group').forEach(g=>{
  const head = g.querySelector('.group-head');
  head.addEventListener('click', ()=> g.classList.toggle('closed'));
});
// مبدئياً الأقسام مفتوحة (لو تبغى مغلقة افتراضياً، فعّل السطر التالي)
// document.querySelectorAll('.group').forEach(g=>g.classList.add('closed'));
</script>
</body>
</html>
