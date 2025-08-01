<?php
// File: login.php
// =================================================================
// DESCRIPTION: หน้าสำหรับให้ผู้ใช้เข้าสู่ระบบ
// =================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

require_once "db_connect.php";

$email = $password = "";
$email_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["email"]))) {
        $email_err = "กรุณากรอกอีเมล";
    } else {
        $email = trim($_POST["email"]);
    }
    
    if (empty(trim($_POST["password"]))) {
        $password_err = "กรุณากรอกรหัสผ่าน";
    } else {
        $password = trim($_POST["password"]);
    }
    
    if (empty($email_err) && empty($password_err)) {
        $sql = "SELECT id, name, email, password, role, status, assigned_shelter_id FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = $email;
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $name, $email_db, $hashed_password, $role, $status, $assigned_shelter_id);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            if ($status === 'Active') {
                                session_regenerate_id(true);
                                
                                $_SESSION["loggedin"] = true;
                                $_SESSION["user_id"] = $id;
                                $_SESSION["name"] = $name;
                                $_SESSION["email"] = $email_db;
                                $_SESSION["role"] = $role;
                                $_SESSION["assigned_shelter_id"] = $assigned_shelter_id;
                                
                                unset($_SESSION['permissions']);

                                header("location: index.php");
                                exit;
                            } elseif ($status === 'Pending') {
                                $login_err = "บัญชีของคุณกำลังรอการอนุมัติจากผู้ดูแลระบบ";
                            } elseif ($status === 'Inactive') {
                                $login_err = "บัญชีของคุณถูกปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ";
                            }
                        } else {
                            $login_err = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
                        }
                    }
                } else {
                    $login_err = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
                }
            } else {
                $login_err = "มีบางอย่างผิดพลาด กรุณาลองใหม่อีกครั้ง";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Sarabun', sans-serif; }</style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md bg-white p-8 rounded-xl shadow-lg">
        <div class="text-center mb-8">
            <img src="https://www.pao-sisaket.go.th/image/logo.png" alt="Logo" class="mx-auto h-16 w-auto mb-4">
            <h1 class="text-2xl font-bold text-gray-800">ระบบจัดการศูนย์ช่วยเหลือ</h1>
            <p class="text-gray-500">อบจ.ศรีสะเกษ</p>
        </div>

        <?php if (isset($_GET['registration']) && $_GET['registration'] === 'pending'): ?>
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4" role="alert">
            <p class="font-bold">สมัครสมาชิกสำเร็จ!</p>
            <p>บัญชีของคุณจะพร้อมใช้งานหลังได้รับการอนุมัติจากผู้ดูแลระบบ</p>
        </div>
        <?php endif; ?>

        <?php if (!empty($login_err)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?= htmlspecialchars($login_err); ?></p>
            </div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">อีเมล</label>
                <input type="email" name="email" id="email" class="mt-1 block w-full px-3 py-2 border <?= (!empty($email_err)) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg" value="<?= htmlspecialchars($email); ?>" required>
                <?php if(!empty($email_err)): ?><span class="text-red-500 text-sm"><?= htmlspecialchars($email_err); ?></span><?php endif; ?>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                <input type="password" name="password" id="password" class="mt-1 block w-full px-3 py-2 border <?= (!empty($password_err)) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg" required>
                <?php if(!empty($password_err)): ?><span class="text-red-500 text-sm"><?= htmlspecialchars($password_err); ?></span><?php endif; ?>
            </div>
            <div>
                <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2.5 px-4 rounded-lg hover:bg-indigo-700 transition-colors">เข้าสู่ระบบ</button>
            </div>
        </form>
        <p class="text-center text-sm text-gray-500 mt-6">
            ยังไม่มีบัญชี? <a href="register.php" class="font-medium text-indigo-600 hover:text-indigo-500">สมัครสมาชิกที่นี่</a>
        </p>
    </div>
</body>
</html>
