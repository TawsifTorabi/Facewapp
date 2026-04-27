let selectedFiles = [];
let currentPage = 1;
const PER_PAGE = 9;

let uploadBtn = null;
let uploadBar = null;

let uploadedBytes = 0;
let startTime = 0;

let expandedJobs = new Set();

window.addEventListener("DOMContentLoaded", () => {
  uploadBtn = document.getElementById("uploadBtn");
});

// let startTime = Date.now();

uploadBtn = document.getElementById("uploadBtn");

//===========================
//File Selection Logics
//===========================

const dropZone = document.getElementById("dropZone");
const fileInput = document.getElementById("targets");

dropZone.onclick = () => fileInput.click();

fileInput.onchange = (e) => {
  addFiles(e.target.files);
};

dropZone.ondrop = (e) => {
  e.preventDefault();
  addFiles(e.dataTransfer.files);
};

dropZone.ondragover = (e) => e.preventDefault();

function addFiles(files) {
  for (let f of files) {
    selectedFiles.push(f);
  }
  renderPreview();
}

function renderPreview() {
  const grid = document.getElementById("previewGrid");

  grid.innerHTML = "";

  const start = (currentPage - 1) * PER_PAGE;
  const pageFiles = selectedFiles.slice(start, start + PER_PAGE);

  pageFiles.forEach((file, index) => {
    const url = URL.createObjectURL(file);

    grid.innerHTML += `
      <div class="col-4 preview-tile">
        <img src="${url}">
        <button class="remove-btn" onclick="removeFile(${start + index})">×</button>
      </div>
    `;
  });

  document.getElementById("fileCount").innerText = selectedFiles.length;
  document.getElementById("totalSize").innerText = formatSize(totalSize());

  document.getElementById("pageInfo").innerText =
    `Page ${currentPage} / ${Math.ceil(selectedFiles.length / PER_PAGE)}`;
}

function removeFile(index) {
  selectedFiles.splice(index, 1);
  renderPreview();
}

function nextPage() {
  if (currentPage * PER_PAGE < selectedFiles.length) {
    currentPage++;
    renderPreview();
  }
}

function prevPage() {
  if (currentPage > 1) {
    currentPage--;
    renderPreview();
  }
}

function totalSize() {
  return selectedFiles.reduce((acc, f) => acc + f.size, 0);
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + " B";
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
  return (bytes / (1024 * 1024)).toFixed(2) + " MB";
}

function updateSpeed(bytes) {
  if (!uploadBtn) return;

  uploadedBytes += bytes;

  const elapsed = (Date.now() - startTime) / 1000;
  const speed = uploadedBytes / elapsed;

  const mbps = (speed / (1024 * 1024)).toFixed(2);

  document.getElementById("uploadSpeed").innerText = `Speed: ${mbps} Mb/s`;
}

function setUploadProgress(percent) {
  if (!uploadBtn) return;

  uploadBtn.disabled = true;

  uploadBtn.innerHTML = `
    <div class="w-100 bg-light rounded" style="height:38px; overflow:hidden;">
      <div id="uploadBar"
        style="
          width:${percent}%;
          height:100%;
          background:#0d6efd;
          color:white;
          display:flex;
          align-items:center;
          justify-content:center;
          transition:width 0.2s ease;
          font-size:12px;
        ">
        ${Math.round(percent)}%
      </div>
    </div>
  `;

  uploadBar = document.getElementById("uploadBar");
}

//===========================
//File Upload Logics
//===========================

const CHUNK_SIZE = 2 * 1024 * 1024; // 2MB (mobile safe)

async function uploadFileResumable(file, fileId, onProgress) {
  const token = localStorage.getItem("token");

  const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

  for (let i = 0; i < totalChunks; i++) {
    const start = i * CHUNK_SIZE;
    const end = Math.min(file.size, start + CHUNK_SIZE);
    const chunk = file.slice(start, end);

    let res = await fetch("/api/upload_chunk_resumable.php", {
      method: "POST",
      headers: {
        Authorization: "Bearer " + token,
      },
      body: (() => {
        const form = new FormData();
        form.append("file_id", fileId);
        form.append("chunk_index", i);
        form.append("total_chunks", totalChunks);
        form.append("file_name", file.name);
        form.append("chunk", chunk);
        return form;
      })(),
    });

    if (!res.ok) {
      throw new Error("Chunk upload failed at " + i);
    }

    onProgress(Math.round(((i + 1) / totalChunks) * 100));

    updateSpeed(chunk.size);

    if (uploadBar) {
      const progress = Math.round((uploadedBytes / totalSize()) * 100);
      setUploadProgress(progress);
    }
  }
}

function generateUUID() {
  if (crypto.randomUUID) return crypto.randomUUID();

  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
    const r = (crypto.getRandomValues(new Uint8Array(1))[0] % 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

async function uploadFiles(files, onProgress) {
  let results = [];

  for (let f of files) {
    const fileId = generateUUID();

    await uploadFileResumable(f, fileId, (p) => {
      onProgress(f.name, p);
    });

    results.push(fileId);
  }

  return results;
}

async function createJob() {
  const swap = document.getElementById("swap").files[0];
  const targets = selectedFiles;

  if (!swap || targets.length === 0) {
    showToast("Please select files", "error");
    return;
  }

  const btn = uploadBtn;

  if (!btn) {
    console.error("uploadBtn not found");
    return;
  }

  try {
    // =====================
    // RESET STATE
    // =====================
    uploadedBytes = 0;
    startTime = Date.now();

    btn.disabled = true;
    btn.innerText = "Preparing upload...";

    const token = localStorage.getItem("token");

    // =====================
    // SWAP UPLOAD
    // =====================
    const swapId = generateUUID();

    await uploadFileResumable(swap, swapId, (p) => {
      btn.innerText = `Uploading swap... ${p}%`;
    });

    await fetch("/api/merge_file.php", {
      method: "POST",
      headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `file_id=${swapId}&file_name=${swap.name}`,
    });

    // =====================
    // TARGET UPLOADS
    // =====================
    btn.innerText = "Uploading targets...";

    const targetIds = await uploadFiles(targets, (name, p) => {
      btn.innerText = `Uploading ${name}... ${p}%`;
    });

    const mergedTargets = [];

    for (let i = 0; i < targetIds.length; i++) {
      await fetch("/api/merge_file.php", {
        method: "POST",
        headers: {
          Authorization: "Bearer " + token,
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `file_id=${targetIds[i]}&file_name=${targets[i].name}`,
      });

      mergedTargets.push(targets[i].name);
    }

    // =====================
    // SHOW FINAL LOADING UI
    // =====================
    btn.innerHTML = `
      <div class="progress w-100">
        <div id="uploadBar" class="progress-bar" style="width:0%"></div>
      </div>
    `;

    uploadBar = document.getElementById("uploadBar");

    // =====================
    // CREATE JOB
    // =====================
    btn.innerText = "Creating job...";

    const res = await fetch("/api/jobs/create.php", {
      method: "POST",
      headers: Auth.headers(),
      body: JSON.stringify({
        swap_image: swap.name,
        targets: mergedTargets,
      }),
    });

    const data = await res.json();

    if (!data.success) {
      throw new Error(data.error || "Job creation failed");
    }

    showToast("Job created successfully!", "success");
    setTimeout(() => {
      loadJobs(true);
    }, 1000);
  } catch (err) {
    console.error(err);
    showToast(err.message, "error");
  } finally {
    if (btn) {
      uploadBtn.disabled = false;
      uploadBtn.innerHTML = "Create Job";
      uploadBar = null;
    }
  }
}

let jobCache = {};

async function loadJobs(force = false) {
  if (force) {
    jobCache = {};
    document.getElementById("jobs").innerHTML = "";
  }

  try {
    const res = await fetch("/api/jobs/list.php", {
      headers: {
        Authorization: "Bearer " + localStorage.getItem("token"),
      },
    });

    const data = await res.json();
    const box = document.getElementById("jobs");

    data.jobs.forEach((j) => {
      const swapImageUrl = `/api/image_proxy.php?file=uploads/user_${j.user_id}/${j.swap_image}`;

      const isCompleted = j.status === "completed";
      const hasErrors = j.status === "completed_with_errors";

      let statusColor = "text-dark";
      if (isCompleted) statusColor = "text-success";
      if (hasErrors) statusColor = "text-warning";
      if (j.status === "processing") statusColor = "text-primary";

      const html = `
        <div class="job-card-glass border rounded p-3 mb-3">

        <!-- HEADER -->
        <div class="d-flex align-items-start gap-3">

          <!-- THUMB -->
          <img 
            src="${swapImageUrl}"
            onclick="openPreview('${swapImageUrl}')"
            class="swap-thumb"
            style="width:64px;height:64px;object-fit:cover;border-radius:14px;cursor:pointer;"
          >

          <!-- INFO -->
          <div style="flex:1; min-width:0">

            <div class="d-flex justify-content-between align-items-center">
              <b style="font-size:14px;">Meme Job #${j.id}</b>

              <span class="badge ${
                j.status === "completed"
                  ? "bg-success"
                  : j.status === "processing"
                    ? "bg-primary"
                    : j.status === "completed_with_errors"
                      ? "bg-warning text-dark"
                      : "bg-secondary"
              }">
                ${j.status}
              </span>
            </div>

            <div class="mt-1 small fw-semibold text-white">
              Progress: ${j.progress}%
            </div>

            <!-- mini stats -->
            <div class="mt-1 small">
              <span>✅ ${j.done_images || 0}</span>
              <span class="mx-2">|</span>
              <span>❌ ${j.failed_images || 0}</span>
            </div>

          </div>

          <!-- ACTIONS -->
          <div class="d-flex flex-column gap-2">

            <a 
              href="/view_results.php?job_id=${j.id}" 
              class="btn btn-sm btn-primary"
            >
              View
            </a>

            ${
              isCompleted || hasErrors
                ? `<button class="btn btn-sm btn-success" onclick="downloadZip(${j.id})">
                    ZIP
                  </button>`
                : ""
            }

            <button 
              class="btn btn-sm btn-outline-light"
              onclick="toggleJob(${j.id})">
              Details
            </button>

          </div>

        </div>

        <!-- PROGRESS BAR -->
        <div class="progress mt-3" style="height:6px;">
          <div 
            class="progress-bar"
            style="width:${j.progress}%; transition: width 0.4s ease;"
          ></div>
        </div>

        <!-- DETAILS -->
        <div id="details_${j.id}" class="job-details d-none mt-3">

          <div class="mt-1 small text-white">
            🕒 Created: ${j.created_at || "N/A"}
          </div>

        </div>

      </div>
      `;

      const existing = document.getElementById(`job_${j.id}`);

      if (!existing) {
        const wrapper = document.createElement("div");
        wrapper.id = `job_${j.id}`;
        wrapper.innerHTML = html;
        box.appendChild(wrapper);
      } else {
        existing.innerHTML = html;
      }

      const details = document.getElementById(`details_${j.id}`);

      if (expandedJobs.has(j.id)) {
        details.classList.remove("d-none");
      } else {
        details.classList.add("d-none");
      }

      jobCache[j.id] = j;
    });
  } catch (err) {
    console.error("Failed to load jobs:", err);
  }
}

function toggleJob(id) {
  const el = document.getElementById("details_" + id);

  if (!el) return;

  const isHidden = el.classList.contains("d-none");

  if (isHidden) {
    el.classList.remove("d-none");
    expandedJobs.add(id);
  } else {
    el.classList.add("d-none");
    expandedJobs.delete(id);
  }
}

function openPreview(src) {
  const viewer = document.getElementById("viewer");
  const img = document.getElementById("viewerImg");

  img.src = src;
  viewer.style.display = "flex";
}

function renderJobs(jobs) {
  const box = document.getElementById("jobs");

  jobs.forEach((j) => {
    const old = jobCache[j.id];
    const changed = JSON.stringify(old) !== JSON.stringify(j);

    if (!changed) return;

    jobCache[j.id] = j;

    let el = document.getElementById("job_" + j.id);

    if (!el) {
      el = document.createElement("div");
      el.id = "job_" + j.id;
      el.className = "border rounded p-2 mb-2 bg-white";
      box.appendChild(el);
    }

    const swapImageUrl = `/api/image_proxy.php?file=uploads/user_${j.user_id}/${j.swap_image}`;

    const isDone = j.progress >= 100;
    const hasErrors = j.status === "completed_with_errors";

    el.innerHTML = `
      <div class="d-flex flex-column gap-2">

        <!-- HEADER -->
        <div class="d-flex align-items-center gap-3">

          <img 
            src="${swapImageUrl}"
            class="swap-thumb"
            onclick="openPreview('${swapImageUrl}')"
          >

          <div style="flex:1">
            <b>Job #${j.id}</b><br>
            <span class="${getStatusColor(j.status)}">
              ${j.status}
            </span>
          </div>

          <button class="btn btn-sm btn-outline-secondary"
            onclick="toggleJob(${j.id})">
            Details
          </button>

        </div>

        <!-- PROGRESS -->
        <div class="progress" style="height:6px;">
          <div class="progress-bar" 
            style="width:${j.progress}%"></div>
        </div>

        <!-- DETAILS -->
        <div id="details_${j.id}" class="job-details d-none">

          <small>
            ✅ Done: ${j.done_images || 0} |
            ❌ Failed: ${j.failed_images || 0}
          </small>

          <div class="d-flex gap-2 mt-2">

            <a href="/view_results.php?job_id=${j.id}"
              class="btn btn-sm btn-primary w-100">
              View
            </a>

            ${
              isDone || hasErrors
                ? `
              <button class="btn btn-sm btn-success w-100"
                onclick="downloadZip(${j.id})">
                ZIP
              </button>
            `
                : ""
            }

          </div>

        </div>

      </div>
    `;
  });
}

async function downloadZip(jobId) {
  try {
    const res = await fetch(`/api/jobs/create_zip.php?job_id=${jobId}`, {
      method: "GET",
      headers: {
        Authorization: "Bearer " + localStorage.getItem("token"),
      },
    });

    if (!res.ok) {
      throw new Error("Failed to download zip");
    }

    const blob = await res.blob();

    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");

    a.href = url;
    a.download = `job_${jobId}.zip`;

    document.body.appendChild(a);
    a.click();

    a.remove();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    console.error(err);
    alert("ZIP download failed");
  }
}

setInterval(loadJobs, 3000);
window.onload = loadJobs;

function showToast(msg, type = "info") {
  const el = document.getElementById("toast");

  el.innerText = msg;

  el.style.background =
    type === "success" ? "#198754" : type === "error" ? "#dc3545" : "#333";

  el.style.display = "block";

  setTimeout(() => {
    el.style.display = "none";
  }, 3000);
}
