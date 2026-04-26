<?php

require_once "../../../core/db.php";

$job_id = $_GET['job_id'];

$res = $conn->query("
    SELECT status, progress FROM jobs WHERE id=$job_id
");

echo json_encode($res->fetch_assoc());