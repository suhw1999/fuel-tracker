<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 油耗统计</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            font-size: 1em;
        }

        body {
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #575757;
        }

        .login-container {
            background: #575757;
            border: 3px solid #fff;
            border-radius: 10px;
            padding: 30px;
            max-width: 400px;
            width: 300px;
            text-align: center;
            margin: 30px;
        }

        .login-title {
            color: #fff;
            font-size: 24px;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .form-input {
            width: 240px;
            height: 60px;
            border: 3px solid #fff;
            border-radius: 10px;
            background: #575757;
            color: #fff;
            font-weight: bold;
            text-align: center;
            line-height: 60px;
            margin: 0;
        }

        .form-input:focus {
            outline: none;
        }

        .form-input::placeholder {
            color: #ccc;
        }

        .error-message {
            background: #f44336;
            color: #fff;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 3px solid #fff;
        }

        .login-container nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
        }

        .login-container nav ul li {
            --c: #fff;
            color: var(--c);
            width: 240px;
            height: 60px;
            border: 3px solid var(--c);
            border-radius: 10px;
            text-align: center;
            line-height: 60px;
            font-weight: bold;
            cursor: pointer;
            margin: 20px 0 0 0;
            position: relative;
            overflow: hidden;
            z-index: 1;
            transition: 0.5s;
            background: #575757;
        }

        .login-container nav ul li:hover {
            color: #222;
        }

        .login-container nav ul li span {
            position: absolute;
            width: 25%;
            height: 100%;
            background-color: var(--c);
            border-radius: 50%;
            transform: translateY(150%);
            left: calc((var(--n) - 1) * 25%);
            transition: 0.5s;
            transition-delay: calc((var(--n) - 1) * 0.1s);
            z-index: -1;
        }

        .login-container nav ul li:hover span {
            transform: translateY(0) scale(2);
        }

        .login-container nav ul li span:nth-child(1) {
            --n: 1;
        }

        .login-container nav ul li span:nth-child(2) {
            --n: 2;
        }

        .login-container nav ul li span:nth-child(3) {
            --n: 3;
        }

        .login-container nav ul li span:nth-child(4) {
            --n: 4;
        }

        @media (max-width: 480px) {
            .login-container {
                width: 280px;
                margin: 20px;
                padding: 20px;
            }
            .form-input {
                width: 200px;
            }
            .login-container nav ul li {
                width: 200px;
            }
            .login-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">油耗统计</h1>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="error-message">密码错误，请重试</div>
        <?php endif; ?>

        <form method="POST" class="login-form" id="loginForm">
            <input type="password"
                   id="password"
                   name="password"
                   class="form-input"
                   placeholder="请输入访问密码"
                   required
                   autofocus>
            <nav>
                <ul>
                    <li id="submitBtn" onclick="document.getElementById('loginForm').submit()">
                        登录
                        <span></span><span></span><span></span><span></span>
                    </li>
                </ul>
            </nav>
        </form>
    </div>

    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.style.pointerEvents = 'none';
        submitBtn.style.opacity = '0.6';
        const spans = submitBtn.querySelectorAll('span');
        submitBtn.textContent = '登录中...';
        spans.forEach(span => submitBtn.appendChild(span));
    });

    // 自动聚焦到密码输入框
    document.getElementById('password').focus();
    </script>
</body>
</html>
