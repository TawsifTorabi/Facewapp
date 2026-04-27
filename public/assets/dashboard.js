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
  }
}


function generateUUID() {
  if (crypto.randomUUID) return crypto.randomUUID();

  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function(c) {
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





//=============================
//  Job Functions
//=============================
async function createJob() {
  const btn = document.querySelector("button[onclick='createJob()']");
  const swap = document.getElementById("swap").files[0];
  const targets = Array.from(document.getElementById("targets").files);

  if (!swap || targets.length === 0) {
    showToast("Please select files", "error");
    return;
  }

  try {
    btn.disabled = true;
    btn.innerText = "Uploading...";

    const token = localStorage.getItem("token");

    // =====================
    // 1. RESUMABLE SWAP
    // =====================
    const swapId = generateUUID();

    await uploadFileResumable(swap, swapId, (p) => {
      console.log("Swap upload:", p);
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
    // 2. RESUMABLE TARGETS
    // =====================
    const targetIds = await uploadFiles(targets, (name, p) => {
      console.log(name, p);
    });

    // merge targets
    const mergedTargets = [];

    for (let i = 0; i < targetIds.length; i++) {
      const id = targetIds[i];

      await fetch("/api/merge_file.php", {
        method: "POST",
        headers: {
          Authorization: "Bearer " + token,
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `file_id=${id}&file_name=${targets[i].name}`,
      });

      mergedTargets.push(targets[i].name);
    }

    // =====================
    // 3. CREATE JOB (NOW CONSISTENT)
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

    if (!data.success) throw new Error(data.error);

    showToast("Job created!", "success");
    loadJobs();
  } catch (err) {
    console.error(err);
    showToast(err.message, "error");
  } finally {
    btn.disabled = false;
    btn.innerText = "Create Job";
  }
}

let jobCache = {};

async function loadJobs() {
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

      const existing = document.getElementById(`job_${j.id}`);

      const html = `
        <div class="border rounded p-2 mb-2 bg-white">

          <!-- HEADER -->
          <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">

            <!-- THUMB -->
            <img 
              src="${swapImageUrl}"
              onclick="openPreview('${swapImageUrl}')"
              style="
                width:60px;
                height:60px;
                object-fit:cover;
                border-radius:6px;
                flex-shrink:0;
                cursor:pointer;
              "
            >

            <!-- INFO -->
            <div style="flex:1; min-width:0">
              <b>Job #${j.id}</b><br>

              <span class="${statusColor}">
                Status: ${j.status}
              </span><br>

              Progress: ${j.progress}%<br>

              <small class="text-muted">
                ✅ ${j.done_images || 0} | ❌ ${j.failed_images || 0}
              </small>
            </div>

            <!-- ACTIONS -->
            <div class="d-flex flex-sm-column flex-row gap-2">

              <a 
                href="/view_results.php?job_id=${j.id}" 
                class="btn btn-sm btn-primary w-100"
              >
                View
              </a>

              ${
                isCompleted || hasErrors
                  ? `
                    <button 
                      class="btn btn-sm btn-success w-100"
                      onclick="downloadZip(${j.id})">
                      ZIP
                    </button>
                  `
                  : ""
              }

              <button 
                class="btn btn-sm btn-outline-secondary w-100"
                onclick="toggleJob(${j.id})">
                Details
              </button>

            </div>

          </div>

          <!-- PROGRESS BAR -->
          <div class="progress mt-2" style="height:6px;">
            <div 
              class="progress-bar"
              role="progressbar"
              style="width:${j.progress}%; transition: width 0.5s ease;">
            </div>
          </div>

          <!-- DETAILS -->
          <div id="details_${j.id}" class="job-details d-none mt-2">
            <small>
              Job created at: ${j.created_at || "N/A"}
            </small>
          </div>

        </div>
      `;

      // update only changed jobs
      if (!existing) {
        const wrapper = document.createElement("div");
        wrapper.id = `job_${j.id}`;
        wrapper.innerHTML = html;
        box.appendChild(wrapper);
      } else {
        existing.innerHTML = html;
      }

      jobCache[j.id] = j;
    });
  } catch (err) {
    console.error("Failed to load jobs:", err);
  }
}

function toggleJob(id) {
  const el = document.getElementById("details_" + id);
  el.classList.toggle("d-none");
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
