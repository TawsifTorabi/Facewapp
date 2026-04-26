const CHUNK_SIZE = 5;

async function uploadInChunks(files) {
  let uploaded = [];

  for (let i = 0; i < files.length; i += CHUNK_SIZE) {
    const chunk = files.slice(i, i + CHUNK_SIZE);
    const form = new FormData();

    chunk.forEach((f) => form.append("files[]", f));

    let res;

    try {
      res = await fetch("/api/upload_chunk.php", {
        method: "POST",
        headers: {
          Authorization: "Bearer " + localStorage.getItem("token"),
        },
        body: form,
      });
    } catch (err) {
      console.error("Network error:", err);
      throw new Error("Upload request failed");
    }

    // 🔥 IMPORTANT: check HTTP status first
    if (!res || !res.ok) {
      const text = await res?.text();
      console.error("Server error:", text);
      throw new Error("Chunk upload failed (HTTP " + res?.status + ")");
    }

    let data;

    try {
      data = await res.json();
    } catch (err) {
      const text = await res.text();
      console.error("Invalid JSON response:", text);
      throw new Error("Server returned invalid JSON");
    }

    if (!data.files) {
      throw new Error("Invalid upload response format");
    }

    uploaded = uploaded.concat(data.files);
  }

  return uploaded;
}

async function createJob() {
  const swap = document.getElementById("swap").files[0];
  const targets = Array.from(document.getElementById("targets").files);

  if (!swap || targets.length === 0) {
    alert("Select files");
    return;
  }

  // =====================
  // 1. upload swap
  // =====================
  const swapForm = new FormData();
  swapForm.append("files[]", swap);

  const swapRes = await fetch("/api/upload_chunk.php", {
    method: "POST",
    headers: {
      Authorization: "Bearer " + localStorage.getItem("token"),
    },
    body: swapForm,
  });

  const swapData = await swapRes.json();

  if (!swapData.files || !swapData.files.length) {
    alert("Swap upload failed");
    return;
  }

  const swapFile = swapData.files[0];

  // =====================
  // 2. upload targets
  // =====================
  const targetFiles = await uploadInChunks(targets);

  // =====================
  // 3. create job
  // =====================
  const res = await fetch("/api/jobs/create.php", {
    method: "POST",
    headers: {
      ...Auth.headers(),
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      swap_image: swapFile,
      targets: targetFiles,
    }),
  });

  const data = await res.json();

  console.log(data);

  loadJobs();
}

async function loadJobs() {
  try {
    const res = await fetch("/api/jobs/list.php", {
      headers: {
        Authorization: "Bearer " + localStorage.getItem("token"),
      },
    });

    const data = await res.json();

    const box = document.getElementById("jobs");
    box.innerHTML = "";

    data.jobs.forEach((j) => {
      box.innerHTML += `
                <div class="border p-2 mb-2">
                    <b>Job #${j.id}</b><br>
                    Status: ${j.status}<br>
                    Progress: ${j.progress}%
                </div>
            `;
    });
  } catch (err) {
    console.error("Failed to load jobs:", err);
  }
}

setInterval(loadJobs, 3000);
window.onload = loadJobs;
