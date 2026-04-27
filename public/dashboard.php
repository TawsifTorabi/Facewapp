<!DOCTYPE html>
<html>

<head>
    <title>Facewapp</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <link rel="icon" type="image/x-icon" href="/images/favicon.jpg">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Press+Start+2P&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top, #0f172a, #020617);
            color: #e5e7eb;
        }

        /* =========================
   APP HEADER
========================= */
        h3 {
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #fff;
        }

        /* =========================
   AUTH STATUS
========================= */
        #auth-status {
            font-size: 12px;
            opacity: 0.7;
        }

        /* =========================
   GLASS CARD (UPLOAD PANEL)
========================= */
        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            color: #fff;
        }

        /* =========================
   DROPZONE (MEME STYLE)
========================= */
        .upload-dropzone {
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.03);
            transition: 0.2s;
        }

        .upload-dropzone:hover {
            background: rgba(99, 102, 241, 0.15);
            border-color: #6366f1;
        }

        /* =========================
   PREVIEW TILES (MODERN GRID)
========================= */
        .preview-tile {
            position: relative;
        }

        .preview-tile img {
            width: 100%;
            height: 110px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: 0.2s;
        }

        .preview-tile img:hover {
            transform: scale(1.02);
        }

        .remove-btn {
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 12px;
        }

        /* =========================
   BUTTONS (MEME STYLE)
========================= */
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            font-weight: 600;
            border-radius: 12px;
        }

        .btn-primary:hover {
            filter: brightness(1.1);
        }

        .btn-outline-secondary {
            border-radius: 10px;
            border-color: rgba(255, 255, 255, 0.2);
            color: #e5e7eb;
        }

        /* =========================
   JOB FEED CARDS (IMPORTANT)
========================= */
        #jobs .border {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 16px;
            padding: 12px;
            margin-bottom: 12px;
            backdrop-filter: blur(8px);
        }

        /* Job title vibe */
        #jobs b {
            color: #fff;
        }

        /* Status glow */
        .text-success {
            color: #22c55e !important;
        }

        .text-warning {
            color: #facc15 !important;
        }

        .text-primary {
            color: #60a5fa !important;
        }

        /* =========================
   PROGRESS BAR (NEON STYLE)
========================= */
        .progress {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg, #22c55e, #3b82f6, #a855f7);
        }

        /* =========================
   THUMBNAIL (MEME FEED STYLE)
========================= */
        .swap-thumb {
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* =========================
   TOAST (CLEAN FLOAT)
========================= */
        #toast {
            background: rgba(0, 0, 0, 0.85) !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        /* =========================
   SMALL UI TEXT
========================= */
        .small,
        small {
            color: rgba(255, 255, 255, 0.6);
        }

        /* =========================
   MOBILE TUNING
========================= */
        @media (max-width: 576px) {
            h3 {
                font-size: 18px;
            }
        }
    </style>
    </style>
    <script src="/assets/api.js"></script>
    <script src="/assets/auth.js"></script>
</head>

<body class="bg-light">

    <div class="container mt-4">

        <h3 class="fw-bold mb-1" style="
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 1.8rem;
            letter-spacing: -1px;
            color: #e5e7eb;
            ">
            <span style="color:#22d3ee;">F</span>
            <span style="color:#a78bfa;">A</span>
            <span style="color:#f472b6;">C</span>
            <span style="color:#fbbf24;">E</span>
            <span style="color:#34d399;">W</span>
            <span style="color:#60a5fa;">A</span>
            <span style="color:#fb7185;">P</span>
            <span style="color:#c084fc;">P</span>

            <span style="
                margin-left:8px;
                font-size:1rem;
                color:#94a3b8;
                font-weight:500;
            ">
                Studio
            </span>
        </h3>

        <div class="small mb-3" style="color:#94a3b8;">
            Generate memes, swap faces, and ship viral edits in seconds.
        </div>

        <div id="auth-status">Checking session...</div>

        <button onclick="Auth.logout()" class="btn btn-danger btn-sm mb-3">
            Logout
        </button>

        <div class="row">
            <div class="col">
                <div class="card p-3 mb-3">

                    <!-- SWAP -->
                    <label class="form-label">Swap Image</label>
                    <input type="file" id="swap" class="form-control mb-3">

                    <!-- TARGET UPLOADER -->
                    <label class="form-label">Target Images</label>

                    <div id="dropZone" class="upload-dropzone mb-2">
                        <span>Click or Drop Images Here</span>
                        <input type="file" id="targets" multiple hidden>
                    </div>

                    <!-- PREVIEW GRID -->
                    <div id="previewGrid" class="row g-2"></div>

                    <!-- PAGINATION -->
                    <div class="d-flex justify-content-between mt-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="prevPage()">Prev</button>
                        <span id="pageInfo"></span>
                        <button class="btn btn-sm btn-outline-secondary" onclick="nextPage()">Next</button>
                    </div>

                    <!-- INFO -->
                    <div class="mt-2 small text-muted">
                        Total Files: <span id="fileCount">0</span> |
                        Total Size: <span id="totalSize">0 MB</span>
                        <div id="uploadSpeed" class="text-muted small mt-2"></div>
                    </div>


                    <!-- BUTTON -->
                    <button id="uploadBtn" class="btn btn-primary mt-3" onclick="createJob()">
                        Create Job
                    </button>

                </div>
            </div>
            <div class="col">
                <!-- JOB UI -->
                <div id="jobs"></div>
            </div>
        </div>


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

    <script src="/assets/dashboard.js"></script>
    <script>
        // window.onload = () => Auth.bootstrapSSO();
        Auth.startAutoRefresh();
        window.onload = () => {
            uploadBtn = document.getElementById("uploadBtn");
            loadJobs();
        };
    </script>
</body>

</html>