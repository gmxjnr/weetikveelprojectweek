<?php
session_start();

require_once "db.php";
require_once "modules/Files.php";
require_once "modules/Logs.php";

$files = new Files($pdo);
$logs = new Logs($pdo);


// When the browser sends the (already-encrypted) file via fetch, we answer with
// JSON instead of HTML. The server never sees the real file here, only ciphertext.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Buffer output so a stray PHP warning can't corrupt the JSON response.
    ob_start();
    $response = ['success' => false, 'message' => 'Upload mislukt!'];

    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $response = ['success' => false, 'message' => 'Upload error'];
        } else {
            $uploaderId = $_SESSION['user_id'] ?? null;
            $token = $files->uploadFile($uploaderId, $_FILES['file']);
            if ($token) {
                $response = ['success' => true, 'token' => $token];

                $logs->logAction($uploaderId, 'file_upload', "File uploaded: {$token}");
            } else {
                $response = ['success' => false, 'message' => 'Bestand kon niet worden opgeslagen'];
                $logs->logAction($uploaderId, 'file_upload_failed', "File upload failed: {$_FILES['file']['name']}");
            }
        }
    } catch (Throwable $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }

    ob_end_clean(); // discard any warning HTML PHP may have printed
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weet Ik Veel — Upload</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- ambient background -->
    <div class="aurora" aria-hidden="true">
        <span class="blob blob-a"></span>
        <span class="blob blob-b"></span>
        <span class="blob blob-c"></span>
    </div>

    <div class="card card--solo">
        <div class="pane pane--solo">
            <form id="uploadForm" class="form form--solo">
                <span class="eyebrow">End-to-end versleuteld</span>
                <h1>Bestand uploaden</h1>

                <p id="message" class="status-line"></p>

                <label class="dropzone" id="dropzone">
                    <span class="dropzone__icon">&#8593;</span>
                    <span class="dropzone__filename" id="fileName">Klik of sleep een bestand hierheen</span>
                    <input type="file" id="fileInput" required>
                </label>

                <button type="submit" class="btn">Upload</button>

                <div id="result" class="result-block" hidden>
                    <span class="result-block__label">Deelbare link (de sleutel staat achter de # en wordt nooit naar de server gestuurd):</span>
                    <a id="shareLink" class="share-link" href="#" target="_blank"></a>
                </div>

                <p class="swap-line swap-line--center">
                    <a href="login.php" class="link-muted">Terug naar inloggen</a>
                </p>
            </form>
        </div>
    </div>

    <noscript>Deze pagina heeft JavaScript nodig voor de versleuteling.</noscript>

<script>
// ---- helper: bytes -> base64url (safe to put in a URL) ----
function bytesToBase64url(bytes) {
    let bin = '';
    for (const b of bytes) bin += String.fromCharCode(b);
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

const form = document.getElementById('uploadForm');
const fileInput = document.getElementById('fileInput');
const fileNameEl = document.getElementById('fileName');
const dropzoneEl = document.getElementById('dropzone');
const messageEl = document.getElementById('message');
const resultEl = document.getElementById('result');
const shareLinkEl = document.getElementById('shareLink');

// purely cosmetic: show the chosen filename + drag styling on the dropzone
fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    fileNameEl.textContent = file ? file.name : 'Klik of sleep een bestand hierheen';
});
['dragover', 'dragenter'].forEach(evt =>
    dropzoneEl.addEventListener(evt, (e) => { e.preventDefault(); dropzoneEl.classList.add('is-dragover'); })
);
['dragleave', 'drop'].forEach(evt =>
    dropzoneEl.addEventListener(evt, () => dropzoneEl.classList.remove('is-dragover'))
);

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = fileInput.files[0];
    if (!file) return;

    messageEl.classList.remove('is-error', 'is-success');
    messageEl.textContent = 'Versleutelen...';

    // 1. Generate a fresh random AES-GCM key (256-bit) for this file.
    const key = await crypto.subtle.generateKey(
        { name: 'AES-GCM', length: 256 },
        true,                         // extractable, so we can put it in the link
        ['encrypt', 'decrypt']
    );

    // 2. Random IV (number used once). AES-GCM needs a fresh 12-byte IV per message.
    const iv = crypto.getRandomValues(new Uint8Array(12));

    // 3. Build the plaintext: [filename length (2 bytes)][filename][file bytes].
    //    This way the real filename is encrypted too — the server never learns it.
    const fileBytes = new Uint8Array(await file.arrayBuffer());
    const nameBytes = new TextEncoder().encode(file.name);
    const plaintext = new Uint8Array(2 + nameBytes.length + fileBytes.length);
    new DataView(plaintext.buffer).setUint16(0, nameBytes.length);
    plaintext.set(nameBytes, 2);
    plaintext.set(fileBytes, 2 + nameBytes.length);

    // 4. Encrypt. The output already includes the GCM authentication tag.
    const ciphertext = new Uint8Array(
        await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, plaintext)
    );

    // 5. Upload blob = iv + ciphertext (we prepend the IV so download can read it back).
    const blob = new Uint8Array(iv.length + ciphertext.length);
    blob.set(iv, 0);
    blob.set(ciphertext, iv.length);

    // 6. Send only the encrypted bytes to the server.
    const fd = new FormData();
    fd.append('file', new Blob([blob], { type: 'application/octet-stream' }), 'encrypted.bin');

    const res = await fetch('upload.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.success) {
        messageEl.classList.add('is-error');
        messageEl.textContent = data.message || 'Upload mislukt!';
        return;
    }

    // 7. Export the key and put it in the link's #fragment (never sent to the server).
    const rawKey = new Uint8Array(await crypto.subtle.exportKey('raw', key));
    const keyB64 = bytesToBase64url(rawKey);

    const base = location.href.substring(0, location.href.lastIndexOf('/') + 1);
    const link = base + 'download.php?token=' + data.token + '#k=' + keyB64;

    messageEl.classList.add('is-success');
    messageEl.textContent = 'Upload gelukt!';
    shareLinkEl.href = link;
    shareLinkEl.textContent = link;
    resultEl.hidden = false;
    form.reset();
    fileNameEl.textContent = 'Klik of sleep een bestand hierheen';
});
</script>

</body>
</html>