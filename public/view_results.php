<?php

require_once "./../core/db.php";
require_once "./../core/auth.php";

$job_id = intval($_GET['job_id'] ?? 0);

$res = $conn->query("
    SELECT output_image
    FROM job_images
    WHERE job_id = $job_id
    AND status='completed'
");
?>

<!DOCTYPE html>
<html>

<head>
    <title>Job Results</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .img-card {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            background: #fff;
        }

        .img-preview {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }

        @media (max-width: 576px) {
            .img-preview {
                height: 180px;
            }
        }
    </style>
</head>

<body class="bg-light">

    <div class="container py-3">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="/dashboard.php">Back</a>
            <div>
                <h5 class="mb-0">Job Results</h5>
                <small class="text-muted">Job #<?= $job_id ?></small>
            </div>

            <button class="btn btn-success btn-sm"
                onclick="downloadZip(<?= $job_id ?>)">
                Download ZIP
            </button>
        </div>

        <!-- GRID -->
        <style>
            /* ===== IMAGE CLOUD STYLE ===== */
            .cloud-gallery {
                column-count: 2;
                column-gap: 10px;
            }

            @media (min-width: 768px) {
                .cloud-gallery {
                    column-count: 4;
                }
            }

            .cloud-item {
                break-inside: avoid;
                margin-bottom: 10px;
                border-radius: 12px;
                overflow: hidden;
                background: #fff;
                box-shadow: 0 1px 6px rgba(0, 0, 0, 0.08);
            }

            .cloud-item img {
                width: 100%;
                height: auto;
                display: block;
                cursor: pointer;
                transition: transform 0.2s ease;
            }

            .cloud-item img:active {
                transform: scale(0.98);
            }

            .cloud-actions {
                padding: 6px;
            }
        </style>

        <div class="cloud-gallery">

            <?php while ($row = $res->fetch_assoc()): ?>

                <?php
                $img = "/api/image_proxy.php?file=results/" . $row['output_image'];
                ?>

                <div class="cloud-item">

                    <img
                        src="<?= $img ?>"
                        onclick="openViewer('<?= $img ?>')">

                    <div class="cloud-actions">
                        <a href="<?= $img ?>"
                            download
                            class="btn btn-sm btn-primary w-100">
                            Download
                        </a>
                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    </div>

    <!-- SIMPLE ZIP FUNCTION -->
    <script>
        function downloadZip(jobId) {
            fetch(`/api/jobs/create_zip.php?job_id=${jobId}`, {
                    headers: {
                        "Authorization": "Bearer " + localStorage.getItem("token")
                    }
                })
                .then(res => res.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement("a");

                    a.href = url;
                    a.download = `job_${jobId}.zip`;

                    document.body.appendChild(a);
                    a.click();

                    a.remove();
                    window.URL.revokeObjectURL(url);
                })
                .catch(err => {
                    alert("Download failed");
                    console.error(err);
                });
        }
    </script>

    <!-- LIGHTBOX -->
    <div id="viewer" onclick="closeViewer()"
        style="
        display:none;
        position:fixed;
        top:0; left:0;
        width:100%; height:100%;
        background:rgba(0,0,0,0.9);
        z-index:9999;
        align-items:center;
        justify-content:center;
     ">

        <img id="viewerImg" style="max-width:95%; max-height:95%; border-radius:10px;">
    </div>

    <script>
        function openViewer(src) {
            const v = document.getElementById("viewer");
            const img = document.getElementById("viewerImg");

            img.src = src;
            v.style.display = "flex";
        }

        function closeViewer() {
            document.getElementById("viewer").style.display = "none";
        }
    </script>

</body>

</html>