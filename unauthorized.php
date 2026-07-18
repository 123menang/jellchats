<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Unauthorized - LiveChat Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f7fa;
        }
        .unauthorized-container {
            text-align: center;
            padding: 40px;
        }
        .unauthorized-icon {
            font-size: 80px;
            color: #ff1a1a;
            margin-bottom: 24px;
        }
        .unauthorized-container h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 12px;
        }
        .unauthorized-container p {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 24px;
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #1e62ff;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: #1a4fd1;
        }
    </style>
</head>
<body>
    <div class="unauthorized-container">
        <div class="unauthorized-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <h1>Access Denied</h1>
        <p>You don't have permission to access this page.</p>
        <a href="index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</body>
</html>
