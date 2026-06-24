<?php

require_once "db.php";
require_once "modules/Files.php";

$files = new Files($pdo);

$token = $_GET['token'] ?? null;

if (!$token) {
    die("Geen token opgegeven");
}

$file = $files->getByToken($token);

if (!$file) {
    die("Bestand niet gevonden");
}

$path = __DIR__ . "/modules/data/" . $file['directory'] . "/" . $file['stored_filename'];

if (!file_exists($path)) {
    die("Bestand bestaat niet meer");
}

// raw=1 -> hand back the encrypted bytes so the browser's JS can decrypt them.
// The server only ever sends ciphertext; it cannot read the file itself.
if (isset($_GET['raw'])) {
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weet Ik Veel — Download</title>
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
            <div class="form form--solo">
                <span class="eyebrow">End-to-end versleuteld</span>
                <h1>Bestand ontsleutelen</h1>

                <p id="status" class="status-line">Bezig met ophalen en ontsleutelen...</p>

                <div id="result" class="result-block" hidden></div>

                <p class="swap-line swap-line--center">
                    <a href="login.php" class="link-muted">Terug naar inloggen</a>
                </p>
            </div>
        </div>
    </div>

    <noscript>Deze pagina heeft JavaScript nodig voor de ontsleuteling.</noscript>

<script>
// ---- helper: base64url -> bytes ----
function base64urlToBytes(str) {
    str = str.replace(/-/g, '+').replace(/_/g, '/');
    const bin = atob(str);
    const out = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out;
}

const statusEl = document.getElementById('status');
const resultEl = document.getElementById('result');

(async () => {
    const params = new URLSearchParams(location.search);
    const token = params.get('token');

    // The key lives in the #fragment, which the server never received.
    const hash = new URLSearchParams(location.hash.slice(1));
    const keyB64 = hash.get('k');

    if (!keyB64) {
        statusEl.classList.add('is-error');
        statusEl.textContent = 'Geen sleutel in de link — ontsleutelen onmogelijk.';
        return;
    }

    try {
        // 1. Fetch the encrypted bytes from the server.
        const res = await fetch('download.php?raw=1&token=' + encodeURIComponent(token));
        const blob = new Uint8Array(await res.arrayBuffer());

        // 2. Split off the IV we prepended during upload (first 12 bytes).
        const iv = blob.slice(0, 12);
        const ciphertext = blob.slice(12);

        // 3. Rebuild the key from the link and decrypt (AES-GCM also verifies integrity).
        const key = await crypto.subtle.importKey(
            'raw', base64urlToBytes(keyB64), { name: 'AES-GCM' }, false, ['decrypt']
        );
        const plaintext = new Uint8Array(
            await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, ciphertext)
        );

        // 4. Read back the [nameLen][name][content] layout we built when encrypting.
        const nameLen = new DataView(plaintext.buffer).getUint16(0);
        const name = new TextDecoder().decode(plaintext.slice(2, 2 + nameLen));
        const content = plaintext.slice(2 + nameLen);

        // 5. Offer the decrypted file as a normal download.
        const url = URL.createObjectURL(new Blob([content]));
        const a = document.createElement('a');
        a.href = url;
        a.download = name;
        a.className = 'btn';
        a.textContent = 'Download ' + name;

        statusEl.classList.add('is-success');
        statusEl.textContent = 'Ontsleuteld!';

        const icon = document.createElement('div');
        icon.className = 'download-ready';
        icon.innerHTML =
            '<span class="download-ready__icon">&#8595;</span>' +
            '<span class="download-ready__name"></span>';
        icon.querySelector('.download-ready__name').textContent = name;
        icon.appendChild(a);

        resultEl.appendChild(icon);
        resultEl.hidden = false;
    } catch (err) {
        statusEl.classList.add('is-error');
        statusEl.textContent = 'Kon het bestand niet ontsleutelen (verkeerde of beschadigde link).';
    }
})();
</script>

</body>
</html>