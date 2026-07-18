<?php
require_once 'includes/auth.php';
$auth->requireAuth();
$auth->requireRole(['owner', 'admin']);
$user = $auth->getCurrentUser();

$dbPath = 'database/livechat.db';
if (!file_exists($dbPath)) die("Database tidak ditemukan.");

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

$selectedTable = $_GET['table'] ?? '';

// --- LOGIKA AJAX (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $table = $_POST['table'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) exit;

    try {
        if ($_POST['action'] === 'update') {
            $stmt = $pdo->prepare("UPDATE $table SET {$_POST['column']} = ? WHERE {$_POST['pk_name']} = ?");
            $stmt->execute([$_POST['value'], $_POST['pk_value']]);
            echo json_encode(['status' => 'success']);
        } 
        elseif ($_POST['action'] === 'insert') {
            $fields = $_POST['data']; 
            $cols = implode(", ", array_keys($fields));
            $placeholders = implode(", ", array_fill(0, count($fields), "?"));
            $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)");
            $stmt->execute(array_values($fields));
            echo json_encode(['status' => 'success']);
        }
        elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE {$_POST['pk_name']} = ?");
            $stmt->execute([$_POST['pk_value']]);
            echo json_encode(['status' => 'success']);
        }
        elseif ($_POST['action'] === 'duplicate') {
            $pkName = $_POST['pk_name'];
            $pkValue = $_POST['pk_value'];
            
            // Ambil data asli (kecuali primary key jika itu auto-increment)
            $stmt = $pdo->query("PRAGMA table_info($table)");
            $columnsInfo = $stmt->fetchAll();
            
            $colsToSelect = [];
            foreach ($columnsInfo as $c) {
                if (!$c['pk']) { $colsToSelect[] = $c['name']; }
            }
            
            $colString = implode(", ", $colsToSelect);
            $query = "INSERT INTO $table ($colString) SELECT $colString FROM $table WHERE $pkName = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$pkValue]);
            
            echo json_encode(['status' => 'success']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SQLite Admin Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1e62ff; --bg: #f4f7fa; --text: #334155; --border: #e2e8f0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar { width: 260px; background: #fff; border-right: 1px solid var(--border); padding: 20px; overflow-y: auto; flex-shrink: 0; }
        .table-link { display: flex; align-items: center; gap: 10px; padding: 10px 15px; text-decoration: none; color: var(--text); border-radius: 8px; margin-bottom: 5px; font-size: 14px; }
        .table-link.active { background: var(--primary); color: white; }

        .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .header { padding: 20px 30px; background: #fff; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-danger { background: #ef4444; color: white; padding: 6px; }
        .btn-copy { background: #6366f1; color: white; padding: 6px; }

        .content-area { flex: 1; padding: 20px; overflow: auto; }
        .card { background: white; border-radius: 8px; border: 1px solid var(--border); overflow: hidden; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f8fafc; padding: 12px; border-bottom: 2px solid var(--border); position: sticky; top: 0; z-index: 5; text-align: left; }
        td { border-bottom: 1px solid #f1f5f9; padding: 0; }

        .cell-input { width: 100%; border: none; padding: 12px; font-size: 13px; background: transparent; outline: none; }
        .cell-input:focus { background: #f0f7ff; box-shadow: inset 0 0 0 1px var(--primary); }
        .cell-input.saving { background: #fffbeb; }

        .action-group { display: flex; gap: 4px; justify-content: center; padding: 5px; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 25px; border-radius: 12px; width: 400px; max-width: 90%; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; margin-bottom: 5px; font-weight: 600; }
        .form-group input { width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px; box-sizing: border-box; }

        #toast { position: fixed; bottom: 20px; right: 20px; padding: 12px 20px; background: #334155; color: white; border-radius: 8px; display: none; z-index: 200; }
    </style>
</head>
<body>

<div id="toast"></div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-top:0">Tambah Data Baru</h3>
        <form id="addForm">
            <div id="formInputs"></div>
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<div class="sidebar">
    <h2><i class="fa-solid fa-database"></i> LiveChat DB</h2>
    <?php foreach ($tables as $t): ?>
        <a href="?table=<?= $t['name'] ?>" class="table-link <?= $selectedTable === $t['name'] ? 'active' : '' ?>">
            <i class="fa-solid fa-table"></i> <?= htmlspecialchars($t['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="main">
    <?php if ($selectedTable): 
        $columns = $pdo->query("PRAGMA table_info($selectedTable)")->fetchAll();
        $pkName = 'rowid'; 
        foreach ($columns as $c) { if ($c['pk']) { $pkName = $c['name']; break; } }
        $data = $pdo->query("SELECT " . ($pkName === 'rowid' ? 'rowid, ' : '') . "* FROM $selectedTable LIMIT 500")->fetchAll();
    ?>
        <div class="header">
            <h1>Tabel: <?= htmlspecialchars($selectedTable) ?></h1>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fa-solid fa-plus"></i> Tambah Data
            </button>
        </div>

        <div class="content-area">
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th style="width:80px; text-align:center">Aksi</th>
                            <?php foreach ($columns as $col): ?>
                                <th><?= htmlspecialchars($col['name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr data-pk="<?= htmlspecialchars($row[$pkName]) ?>">
                                <td>
                                    <div class="action-group">
                                        <button class="btn btn-copy" title="Salin Data" onclick="duplicateRow('<?= $pkName ?>', '<?= htmlspecialchars($row[$pkName]) ?>')">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                        <button class="btn btn-danger" title="Hapus Data" onclick="deleteRow(this, '<?= $pkName ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                                <?php foreach ($columns as $col): 
                                    $cName = $col['name'];
                                    $isPK = ($cName === $pkName && $col['pk']);
                                ?>
                                    <td>
                                        <input type="text" class="cell-input" 
                                               value="<?= htmlspecialchars($row[$cName] ?? '') ?>"
                                               <?= $isPK ? 'readonly style="color:#94a3b8"' : '' ?>
                                               onblur="updateCell(this, '<?= $cName ?>', '<?= $pkName ?>')">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const selectedTable = '<?= $selectedTable ?>';

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 2000);
}

function updateCell(input, col, pkName) {
    const pkVal = input.closest('tr').dataset.pk;
    if (input.value === input.defaultValue) return;

    input.classList.add('saving');
    const fd = new FormData();
    fd.append('action', 'update'); fd.append('table', selectedTable);
    fd.append('column', col); fd.append('value', input.value);
    fd.append('pk_name', pkName); fd.append('pk_value', pkVal);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(res => res.json()).then(res => {
            input.classList.remove('saving');
            if(res.status === 'success') { showToast('Terupdate'); input.defaultValue = input.value; }
        });
}

function duplicateRow(pkName, pkValue) {
    if (!confirm('Salin/Duplikat baris ini?')) return;
    const fd = new FormData();
    fd.append('action', 'duplicate');
    fd.append('table', selectedTable);
    fd.append('pk_name', pkName);
    fd.append('pk_value', pkValue);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(res => res.json()).then(res => {
            if(res.status === 'success') location.reload();
            else alert(res.message);
        });
}

function deleteRow(btn, pkName) {
    if (!confirm('Hapus baris ini?')) return;
    const tr = btn.closest('tr');
    const fd = new FormData();
    fd.append('action', 'delete'); fd.append('table', selectedTable);
    fd.append('pk_name', pkName); fd.append('pk_value', tr.dataset.pk);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(res => res.json()).then(res => {
            if(res.status === 'success') tr.remove();
        });
}

function openModal() {
    const container = document.getElementById('formInputs');
    container.innerHTML = '';
    <?php if($selectedTable): foreach($columns as $col): if(!$col['pk']): ?>
        container.innerHTML += `<div class="form-group">
            <label><?= $col['name'] ?></label>
            <input type="text" name="data[<?= $col['name'] ?>]" required>
        </div>`;
    <?php endif; endforeach; endif; ?>
    document.getElementById('addModal').style.display = 'flex';
}

function closeModal() { document.getElementById('addModal').style.display = 'none'; }

document.getElementById('addForm').onsubmit = function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'insert');
    fd.append('table', selectedTable);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(res => res.json()).then(res => {
            if(res.status === 'success') location.reload();
            else alert(res.message);
        });
};
</script>
</body>
</html>