<!DOCTYPE html>
<html>

<head>
    <title>Facewapp Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="/assets/api.js"></script>
    <script src="/assets/auth.js"></script>
</head>


<body class="bg-light">

    <div class="container mt-5" style="max-width: 420px;">

        <div class="card shadow-sm">
            <div class="card-body text-center">

                <h4 class="mb-4">Facewapp Login</h4>

                <button id="loginBtn" class="btn btn-primary w-100" onclick="oneClickLogin()">
                    One Click Login
                </button>

                <div id="status" class="mt-3 text-muted small"></div>

            </div>
        </div>

    </div>

    <script>
        async function oneClickLogin() {

            const btn = document.getElementById("loginBtn");
            const status = document.getElementById("status");

            btn.disabled = true;
            btn.innerText = "Checking session...";
            status.innerText = "";

            try {

                const res = await fetch(`${API_BASE}?action=session_login`, {
                    method: "GET",
                    credentials: "include"
                });

                const data = await res.json();

                console.log(data);

                if (data.logged_in && data.user) {

                    // =====================
                    // SAFE STORAGE (ONLY TOKEN IS TRUTH)
                    // =====================

                    if (data.token) {
                        localStorage.setItem("token", data.token);
                    }

                    localStorage.setItem("user", JSON.stringify(data.user));

                    status.innerText = "Login successful. Redirecting...";

                    setTimeout(() => {
                        window.location.href = "/dashboard.php";
                    }, 500);

                } else {

                    btn.disabled = false;
                    btn.innerText = "One Click Login";
                    status.innerText = "Not logged in on main app";
                }

            } catch (err) {

                console.error(err);

                btn.disabled = false;
                btn.innerText = "One Click Login";
                status.innerText = "Server error";
            }
        }
    </script>
</body>

</html>