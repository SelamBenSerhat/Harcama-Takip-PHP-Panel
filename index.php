<?php
session_start();

if (!isset($_GET['code']) || $_GET['code'] !== 'lyoko') {
    http_response_code(403);
    exit('Erişim engellendi.');
}

header('Content-Type: text/html; charset=utf-8');

$dataFile = "data.json";
$data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];

$defaultPattern = [1,2,5,8];

if (!isset($data["__pattern"])) {
    $data["__pattern"] = $defaultPattern;
    file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$gunler = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];

$authenticated = false;
if (isset($_COOKIE['pattern_auth']) && $_COOKIE['pattern_auth'] === '1') {
    $_SESSION['authenticated'] = true;
}
if (!empty($_SESSION['authenticated'])) {
    $authenticated = true;
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'login') {
        $inputPattern = isset($_POST['pattern']) ? explode(',', $_POST['pattern']) : [];
        $storedPattern = $data["__pattern"];
        $inputPatternInt = array_map('intval', $inputPattern);

        if ($inputPatternInt === $storedPattern) {
            $_SESSION['authenticated'] = true;
            setcookie('pattern_auth', '1', time()+3600*24*30, "/");
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "msg" => "Desen yanlış"]);
        }
        exit;
    }

    if ($action === 'logout') {
        session_destroy();
        setcookie('pattern_auth', '', time()-3600, "/");
        echo json_encode(["success" => true]);
        exit;
    }

    if ($action === 'save_data') {
        if (!$authenticated) {
            echo json_encode(["success" => false, "msg" => "Yetkisiz"]);
            exit;
        }
        $gun = $_POST['gun'] ?? '';
        $menu_cost = (int)($_POST['menu_cost'] ?? 0);
        $yakit = (int)($_POST['yakit'] ?? 0);
        $ekstra = (int)($_POST['ekstra'] ?? 0);
        $total = $menu_cost + $yakit + $ekstra;

        $data[$gun] = [
            "menu_cost" => $menu_cost,
            "yakıt" => $yakit,
            "ekstra" => $ekstra,
            "total" => $total
        ];
        file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(["success" => true]);
        exit;
    }

    if ($action === 'reset_data') {
        if (!$authenticated) {
            echo json_encode(["success" => false, "msg" => "Yetkisiz"]);
            exit;
        }
        $patternKeep = $data["__pattern"];
        $data = ["__pattern" => $patternKeep];
        file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(["success" => true]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>ABYS HTS</title>
<style>
  body { font-family: Arial, sans-serif; margin:0; padding:0; background:#f9f9f9; text-align:center; }
  h2 { padding:15px; background:#4a90e2; color:#fff; margin:0 0 10px 0; }
  button { background:#4a90e2; color:#fff; border:none; padding:10px 18px; font-size:16px; border-radius:5px; cursor:pointer; }
  button:hover { background:#357ABD; }
  .week-tabs { display:flex; justify-content:center; gap:5px; margin-bottom:10px; flex-wrap: wrap; }
  .week-tab { background:#d0d7e5; padding:8px 12px; border-radius:6px; cursor:pointer; font-weight:bold; }
  .week-tab.active { background:#4a90e2; color:#fff; }
  .takvim { display:grid; grid-template-columns: repeat(7, 1fr); gap:8px; padding: 0 10px 20px; }
  @media(max-width:480px) { .takvim { grid-template-columns: repeat(2, 1fr); } }
  .gun { background:#fff; border-radius:8px; padding:10px 8px; box-shadow:0 0 6px rgb(0 0 0 / 0.1); cursor:pointer; min-height:120px; font-size:14px; display:flex; flex-direction:column; justify-content:center; }
  .gun strong { margin-bottom:8px; }
  .gun .yemek, .gun .yakit, .gun .ekstra, .gun .total { margin:2px 0; }
  .toplam { font-size:18px; font-weight:700; margin-bottom:15px; }
  .modal {
    display:none; position:fixed; top:0; left:0; right:0; bottom:0;
    background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:9999;
  }
  .modal-content {
    background:#fff; border-radius:12px; padding:20px; max-width:360px; width:90%; box-sizing:border-box; text-align:center;
  }
  .close {
    float:right; font-size:22px; cursor:pointer; font-weight:bold; user-select:none;
  }
  label {
    display:block; margin-top:12px; font-weight:600; text-align:left;
  }
  input[type=number], input[type=text] {
    width:100%; margin-top:6px; padding:8px; font-size:15px; border-radius:5px; border:1px solid #ccc;
  }
  button[type=submit] {
    margin-top:18px; width:100%;
  }
  #patternPanel {
    display: grid;
    grid-template-columns: repeat(3, 90px);
    grid-template-rows: repeat(3, 90px);
    gap: 25px;
    justify-content: center;
    margin: 30px auto;
    max-width: 320px;
  }
  .pattern-dot {
    width: 90px;
    height: 90px;
    background: #ddd;
    border-radius: 50%;
    box-shadow: 0 0 8px rgba(0,0,0,0.1);
    cursor: pointer;
    user-select: none;
    transition: background-color 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .pattern-dot.active {
    background: #4a90e2;
  }
</style>
</head>
<body>

<h2>ABYS HTS - Harcama Takip Sistemi</h2>

<div id="lockScreen" style="display:<?= $authenticated ? 'none' : 'block' ?>;">
  <p>Lütfen deseni çizerek giriş yapınız.</p>
  <div id="patternPanel">
    <?php for ($i=1; $i<=9; $i++): ?>
      <div class="pattern-dot" data-id="<?= $i ?>"></div>
    <?php endfor; ?>
  </div>
  <div id="patternStatus" style="margin-top:15px; font-weight:bold; min-height:24px; color:#c33;"></div>
</div>

<div id="mainContent" style="display:<?= $authenticated ? 'block' : 'none' ?>; max-width:960px; margin:auto;">
  <div class="week-tabs" id="weekTabs">
    <?php for ($i=1; $i<=4; $i++): ?>
      <div class="week-tab<?= $i === 1 ? ' active' : '' ?>" data-week="<?= $i ?>">Hafta <?= $i ?></div>
    <?php endfor; ?>
  </div>

  <?php
  $totalAy = 0;
  for ($hafta = 1; $hafta <= 4; $hafta++) {
    echo "<div class='takvim' id='hafta{$hafta}' style='" . ($hafta === 1 ? "" : "display:none") . "'>";
    foreach ($gunler as $gun) {
      $gunId = "hafta{$hafta}_" . strtolower($gun);
      echo "<div class='gun' data-gun='$gunId'>";
      echo "<strong>$gun</strong>";
      if (isset($data[$gunId])) {
        $veri = $data[$gunId];
        echo "<div class='yemek'>Yemek: {$veri['menu_cost']} TL</div>";
        echo "<div class='yakit'>Yakıt: {$veri['yakıt']} TL</div>";
        echo "<div class='ekstra'>Ekstra: {$veri['ekstra']} TL</div>";
        echo "<div class='total'>Toplam: {$veri['total']} TL</div>";
        $totalAy += $veri['total'];
      } else {
        echo "<div style='margin-top:10px; font-size:12px; color:#777;'>Tıklayıp veri gir</div>";
      }
      echo "</div>";
    }
    echo "</div>";
  }
  ?>

  <div class="toplam">Toplam Aylık Gider: <span id="totalAy"><?= $totalAy ?></span> TL</div>

  <div style="display:flex; justify-content:center; gap:10px; flex-wrap: wrap; margin-bottom:40px;">
    <button id="btnReset" style="background:#e04e4e;">Listeyi Temizle</button>
    <button id="btnLogout" style="background:#999;">Çıkış Yap</button>
  </div>
</div>

<div id="dataModal" class="modal">
  <div class="modal-content">
    <span class="close" id="dataModalClose">×</span>
    <h3 id="gunBaslik">Gün</h3>
    <form id="dataForm">
      <input type="hidden" name="gun" id="gunInput" />
      <label for="menu_cost">Yemek Tutarı (TL):</label>
      <input type="number" name="menu_cost" id="menu_cost" min="0" value="0" required />
      <label for="yakit">Yakıt (TL):</label>
      <input type="number" name="yakit" id="yakit" min="0" value="0" required />
      <label for="ekstra">Ekstra Harcama (TL):</label>
      <input type="number" name="ekstra" id="ekstra" min="0" value="0" required />
      <button type="submit">Kaydet ve Hesapla</button>
    </form>
  </div>
</div>

<div id="resetModal" class="modal">
  <div class="modal-content">
    <span class="close" id="resetModalClose">×</span>
    <h3>Listeyi Temizlemek İstediğinize Emin misiniz?</h3>
    <p>Lütfen onaylamak için aşağıya <strong>VERİYİSİL</strong> yazınız.</p>
    <input type="text" id="resetConfirmInput" autocomplete="off" />
    <button id="confirmResetBtn" disabled>Temizle</button>
  </div>
</div>

<script>
(() => {
  const patternPanel = document.getElementById('patternPanel');
  const patternStatus = document.getElementById('patternStatus');
  let patternInput = [];

  function clearPattern() {
    patternInput = [];
    patternPanel.querySelectorAll('.pattern-dot').forEach(d => d.classList.remove('active'));
  }

  patternPanel.querySelectorAll('.pattern-dot').forEach(dot => {
    dot.addEventListener('click', () => {
      const id = parseInt(dot.dataset.id);
      if (!patternInput.includes(id)) {
        patternInput.push(id);
        dot.classList.add('active');
      }
    });
  });

  patternPanel.addEventListener('click', async () => {
    if (patternInput.length < 4) {
      patternStatus.style.color = '#c33';
      patternStatus.textContent = 'En az 4 nokta seçmelisiniz.';
      return;
    }
    patternStatus.style.color = '#000';
    patternStatus.textContent = 'Giriş kontrol ediliyor...';

    const res = await fetch(location.href, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=login&pattern=' + encodeURIComponent(patternInput.join(','))
    });
    const data = await res.json();

    if (data.success) {
      patternStatus.style.color = 'green';
      patternStatus.textContent = 'Giriş başarılı! Yükleniyor...';
      setTimeout(() => location.reload(), 1000);
    } else {
      patternStatus.style.color = '#c33';
      patternStatus.textContent = data.msg || 'Hatalı desen!';
      clearPattern();
    }
  });

  document.querySelectorAll('.week-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.week-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const hafta = tab.getAttribute('data-week');
      for (let i = 1; i <= 4; i++) {
        document.getElementById('hafta' + i).style.display = (i == hafta) ? 'grid' : 'none';
      }
    });
  });

  const dataModal = document.getElementById('dataModal');
  const dataModalClose = document.getElementById('dataModalClose');
  const gunBaslik = document.getElementById('gunBaslik');
  const gunInput = document.getElementById('gunInput');
  const dataForm = document.getElementById('dataForm');

  document.querySelectorAll('.gun').forEach(gunDiv => {
    gunDiv.addEventListener('click', () => {
      gunInput.value = gunDiv.dataset.gun;
      gunBaslik.textContent = gunDiv.querySelector('strong').textContent;
      dataForm.menu_cost.value = 0;
      dataForm.yakit.value = 0;
      dataForm.ekstra.value = 0;
      dataModal.style.display = 'flex';
    });
  });

  dataModalClose.addEventListener('click', () => {
    dataModal.style.display = 'none';
  });

  window.addEventListener('click', (e) => {
    if (e.target === dataModal) dataModal.style.display = 'none';
    if (e.target === resetModal) resetModal.style.display = 'none';
  });

  dataForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(dataForm);
    formData.append('action', 'save_data');

    const res = await fetch(location.href, { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
      location.reload();
    } else {
      alert(data.msg || 'Kaydetme sırasında hata oluştu.');
    }
  });

  document.getElementById('btnLogout').addEventListener('click', async () => {
    const res = await fetch(location.href, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=logout'
    });
    const data = await res.json();
    if (data.success) {
      location.reload();
    } else {
      alert('Çıkış yapılamadı.');
    }
  });

  const btnReset = document.getElementById('btnReset');
  const resetModal = document.getElementById('resetModal');
  const resetModalClose = document.getElementById('resetModalClose');
  const resetConfirmInput = document.getElementById('resetConfirmInput');
  const confirmResetBtn = document.getElementById('confirmResetBtn');

  btnReset.addEventListener('click', () => {
    resetConfirmInput.value = '';
    confirmResetBtn.disabled = true;
    resetModal.style.display = 'flex';
  });

  resetModalClose.addEventListener('click', () => {
    resetModal.style.display = 'none';
  });

  resetConfirmInput.addEventListener('input', () => {
    confirmResetBtn.disabled = resetConfirmInput.value.trim() !== 'VERİYİSİL';
  });

  confirmResetBtn.addEventListener('click', async () => {
    confirmResetBtn.disabled = true;
    confirmResetBtn.textContent = 'Siliniyor...';

    const res = await fetch(location.href, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=reset_data'
    });

    const data = await res.json();

    if (data.success) {
      resetModal.style.display = 'none';
      location.reload();
    } else {
      alert(data.msg || 'Temizleme başarısız.');
      confirmResetBtn.disabled = false;
      confirmResetBtn.textContent = 'Temizle';
    }
  });
})();
</script>

</body>
</html>
