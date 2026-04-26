<!DOCTYPE html>
<html>
<head>
    <title>Facewapp</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="/assets/api.js"></script>
    <script src="/assets/auth.js"></script>
    <script src="/assets/dashboard.js"></script>
</head>

<body class="bg-light">

<div class="container mt-4">

    <h3>Face Swap Dashboard</h3>

    <div id="auth-status">Checking session...</div>

    <button onclick="Auth.logout()" class="btn btn-danger btn-sm mb-3">
        Logout
    </button>

    <!-- JOB UI -->
    <div class="card p-3 mb-3">

        <input type="file" id="swap" class="form-control mb-2">
        <input type="file" id="targets" multiple class="form-control mb-2">

        <button class="btn btn-primary" onclick="createJob()">
            Create Job
        </button>

    </div>

    <div id="jobs"></div>

</div>

<script>
(async function initAuth() {

    const status = document.getElementById("auth-status");

    try {
        const r = await Auth.checkLogin();

        if (!r.logged_in) {
            window.location.href = "/login.php";
            return;
        }

        status.innerText = "Logged in";

    } catch (e) {
        console.error(e);
        window.location.href = "/login.php";
    }

})();
</script>

</body>
</html>