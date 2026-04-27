<!DOCTYPE html>
<html>

<head>
    <title>Facewapp</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <style>
        /* =========================
        JOB CARD
        ========================= */
        .job-card {
            background: #fff;
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        /* =========================
        HEADER LAYOUT
        ========================= */
        .job-header {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* =========================
        THUMBNAIL
        ========================= */
        .swap-thumb {
            width: 52px;
            height: 52px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            cursor: pointer;
        }

        /* =========================
        INFO BLOCK
        ========================= */
        .job-info {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        .job-info b {
            font-size: 14px;
        }

        /* status text */
        .status-text {
            font-weight: 500;
        }

        /* =========================
        ACTION BUTTONS
        ========================= */
        .job-actions {
            display: flex;
            gap: 6px;
        }

        .job-actions .btn {
            font-size: 12px;
            padding: 6px 8px;
            white-space: nowrap;
        }

        /* =========================
        PROGRESS BAR
        ========================= */
        .progress {
            height: 5px;
            border-radius: 10px;
            overflow: hidden;
            background: #eee;
        }

        .progress-bar {
            transition: width 0.4s ease;
        }

        /* =========================
        DETAILS SECTION
        ========================= */
        .job-details {
            margin-top: 8px;
            font-size: 12px;
            color: #666;
        }

        /* =========================
        MOBILE OPTIMIZATION
        ========================= */
        @media (max-width: 576px) {

            body {
                font-size: 13px;
            }

            .job-header {
                flex-direction: row;
                align-items: flex-start;
            }

            .swap-thumb {
                width: 45px;
                height: 45px;
            }

            .job-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .job-actions .btn {
                width: 100%;
            }

            .job-card {
                padding: 8px;
                border-radius: 10px;
            }
        }

        /* =========================
        FULLSCREEN IMAGE VIEWER
        ========================= */
        .image-viewer {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .image-viewer img {
            max-width: 95%;
            max-height: 90%;
            border-radius: 10px;
        }
    </style>
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


    <div id="toast" style="
  position: fixed;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  background: #333;
  color: #fff;
  padding: 10px 14px;
  border-radius: 8px;
  display: none;
  z-index: 9999;
  font-size: 14px;
"></div>


<script>
    // window.onload = () => Auth.bootstrapSSO();
    Auth.startAutoRefresh();
</script>
</body>

</html>