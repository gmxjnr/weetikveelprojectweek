<?php
session_start();

require_once "db.php";
require_once "modules/Files.php";

$files = new Files($pdo);

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
    <title>Upload File</title>
</head>
<body>

<h2>Upload file (end-to-end versleuteld)</h2>

<p id="message"></p>

<div id="result" hidden>
    <p>Deelbare link (de sleutel staat achter de # en wordt nooit naar de server gestuurd):</p>
    <a id="shareLink" href="#" target="_blank"></a>
</div>

<form id="uploadForm">
    <input type="file" id="fileInput" required>
    <button type="submit">Upload</button>
</form>

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
const messageEl = document.getElementById('message');
const resultEl = document.getElementById('result');
const shareLinkEl = document.getElementById('shareLink');

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = fileInput.files[0];
    if (!file) return;

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
        messageEl.textContent = data.message || 'Upload mislukt!';
        return;
    }

    // 7. Export the key and put it in the link's #fragment (never sent to the server).
    const rawKey = new Uint8Array(await crypto.subtle.exportKey('raw', key));
    const keyB64 = bytesToBase64url(rawKey);

    const base = location.href.substring(0, location.href.lastIndexOf('/') + 1);
    const link = base + 'download.php?token=' + data.token + '#k=' + keyB64;

    messageEl.textContent = 'Upload gelukt!';
    shareLinkEl.href = link;
    shareLinkEl.textContent = link;
    resultEl.hidden = false;
    form.reset();
});
</script>

</body>
</html>
